<?php

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('custom/mmicommon/class/mmi_generic.class.php');

class MMI_Product_Replenish extends MMI_Generic
{

// CLASS

/**
 * On va recalculer les seuils de réapprovisionnement et d'alerte
 * en fonction des commandes passées sur l'année écoulée
 * et mettre à jour les produits
 * 
 * On va aussi recalculer la consommation par jour, semaine, mois, année
 * On pourra stocker les données (stats) dans une table dédiée
 * On pourra ainsi faire des stats poussées et afficher des graphs facilement sur les produits (les plus commandés)
 * et ajuster les seuils en conséquence
 * 
 * On va considérer le délai de réappro d'un fournisseur, et de réception, paramétrés en extrafield
 * On calculera aussi les délais moyen, et ecart type de réappro et de réception des commandes
 * 
 * Contraintes alimentaires / périssabilité :
 * Stock maximum autorisé = min(capacité, quantité vendable avant péremption).
 * 
 */

const MOD_NAME = 'mmiproduct';

/** Fenêtre d'analyse par défaut (jours) pour le calcul des seuils. */
const DEFAULT_ANALYSE_DAYS = 90;

/** @var string Sortie lisible pour le cron Dolibarr */
public $output = '';
/** @var int Code d'erreur pour le cron Dolibarr */
public $error = 0;

/**
 * Construit la requête EOQ de calcul des seuils de réappro
 * (stock de sécurité, point de commande, quantité à commander) par produit.
 *
 * Partagée entre la page d'analyse (replenish_fourn.php) et le recalcul
 * automatique (seuils_refresh / cron) pour garantir un calcul identique.
 *
 * @param array $options  analyse_days_nb, filter_supplier_id, filter_product_pro, fk_product_categorie
 * @return string         Requête SQL
 */
public static function buildSeuilsQuery($options = [])
{
	$analyse_days_nb = !empty($options['analyse_days_nb']) ? (int) $options['analyse_days_nb'] : self::DEFAULT_ANALYSE_DAYS;
	$filter_supplier_id = !empty($options['filter_supplier_id']) ? (int) $options['filter_supplier_id'] : 0;
	$filter_product_pro = !empty($options['filter_product_pro']);
	$fk_product_categorie = !empty($options['fk_product_categorie']) ? (int) $options['fk_product_categorie'] : 0;

	// Génération des jours de la fenêtre d'analyse
	$sql_select_days = 'SELECT 0 n';
	for ($i = 1; $i <= $analyse_days_nb; $i++)
		$sql_select_days .= ' UNION ALL SELECT '.$i;

	$sql = 'SELECT
		p.rowid AS product_id,
		p.ref,
		p.label,
		p2.supplier_ref,
		p2.fk_soc_fournisseur AS supplier_id,
		p2.replenish_batch_ddm_delay,
		p2.replenish_service_level,
		p2.replenish_analysis_days,
		p2.replenish_safety_stock,
		p2.replenish_reorder_point,
		p2.replenish_manual,

		dstock.days AS stock_days,

		SUM(COALESCE(ds.total_qty, 0)) AS cmd_qty,
		SUM(COALESCE(ds.total_nb, 0)) AS cmd_nb,
		SUM(COALESCE(ds.total_qty, 0)) / dstock.days AS d,

		STDDEV_POP(COALESCE(ds.total_qty, 0)) AS sigma_d,

		COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days AS freq,

		STDDEV_POP(COALESCE(ds.total_qty, 0))
			* SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
			AS sigma_corrected,

		IF (s2.reception_delay>0, s2.reception_delay, 10) AS L,
		IF (s2.replenish_delay>0, s2.replenish_delay, 28) AS T,
		IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) AS Z,

		ROUND(
			IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
			(
				STDDEV_POP(COALESCE(ds.total_qty, 0))
				* SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
			)
		, 2) AS safety_stock,

		ROUND(
			(SUM(COALESCE(ds.total_qty, 0)) / dstock.days) * IF (s2.reception_delay>0, s2.reception_delay, 10)
			+
			IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
			(
				STDDEV_POP(COALESCE(ds.total_qty, 0))
				* SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
			)
		, 2) AS reorder_point,

		COALESCE(ps.reel, 0) AS stock_current,

		ROUND(
			(
				(SUM(COALESCE(ds.total_qty, 0)) / dstock.days) * (IF (s2.reception_delay>0, s2.reception_delay, 10) + IF (s2.replenish_delay>0, s2.replenish_delay, 28))
				+
				IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
				(
					STDDEV_POP(COALESCE(ds.total_qty, 0))
					* SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
				)
			),
			0
		) AS order_qty,

		IF(pfp.packaging>0, pfp.packaging, 1) AS packaging

	FROM '.MAIN_DB_PREFIX.'product AS p

	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields AS p2
		ON p2.fk_object=p.rowid'

	.($filter_product_pro ?
	' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product AS kp
		ON kp.fk_product=p2.fk_object'
	:'')

	.' LEFT JOIN '.MAIN_DB_PREFIX.'product_stock AS ps
		ON ps.fk_product = p.rowid

	LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s
		ON s.rowid=p2.fk_soc_fournisseur

	LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price AS pfp
		ON pfp.fk_product=p.rowid AND pfp.fk_soc=s.rowid

	LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields AS s2
		ON s2.fk_object=s.rowid

	CROSS JOIN (
		SELECT DATE_SUB(CURDATE(), INTERVAL n DAY) AS day
		FROM (
		   '.$sql_select_days.'
		) AS days
	) AS d

	LEFT JOIN (
		SELECT
			c.`date` as day,
			c.fk_product,
			c.order_nb AS total_nb,
			c.order_qty AS total_qty
		FROM '.MAIN_DB_PREFIX.'product_stats c
		WHERE c.`date` >= DATE_SUB(CURDATE(), INTERVAL '.$analyse_days_nb.' DAY)
	) AS ds
	ON ds.day = d.day AND ds.fk_product = p.rowid

	LEFT JOIN (
		SELECT COUNT(*) AS days, c.fk_product
		FROM '.MAIN_DB_PREFIX.'product_stock_stats c
		WHERE c.`date` >= DATE_SUB(CURDATE(), INTERVAL '.$analyse_days_nb.' DAY)
			AND c.stock_phy>0
		GROUP BY c.fk_product
	) AS dstock
	ON dstock.fk_product=p.rowid

	WHERE p.fk_product_type=0'
	.($filter_supplier_id ? ' AND p2.fk_soc_fournisseur='.$filter_supplier_id :'')
	.($filter_product_pro ? ' AND kp.fk_categorie='.$fk_product_categorie :'')

	.' GROUP BY p.rowid

	HAVING SUM(COALESCE(ds.total_qty, 0)) > 0';

	return $sql;
}

/**
 * Recalcule et écrit les seuils de réappro (seuil d'alerte = point de commande,
 * stock désiré = quantité max) sur les produits, à partir du calcul EOQ.
 *
 * Les produits marqués « ajustés manuellement » (extrafield replenish_manual)
 * sont **exclus** : leurs seuils ne sont jamais écrasés par le recalcul auto.
 *
 * @param array $options  Voir buildSeuilsQuery() (filtre fournisseur, fenêtre, etc.)
 * @return int            Nombre de produits mis à jour (-1 si erreur SQL)
 */
public static function seuils_refresh($options = [])
{
	global $db;

	$sql = self::buildSeuilsQuery($options);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog(__METHOD__.' SQL error: '.$db->lasterror(), LOG_ERR);
		return -1;
	}

	$n = 0;
	$skipped = 0;
	while ($row = $db->fetch_object($resql)) {
		// Override manuel : on ne touche pas aux seuils de ce produit
		if (!empty($row->replenish_manual)) {
			$skipped++;
			continue;
		}

		$usql = 'UPDATE '.MAIN_DB_PREFIX.'product AS p'
			.' INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields AS p2 ON p2.fk_object=p.rowid'
			.' SET'
			.' p.seuil_stock_alerte='.(int) ceil($row->reorder_point)
			.', p.desiredstock='.(int) ceil($row->order_qty)
			.', p2.replenish_safety_stock='.(float) $row->safety_stock
			.', p2.replenish_reorder_point='.(float) $row->reorder_point
			.' WHERE p.rowid='.(int) $row->product_id;
		if ($db->query($usql))
			$n++;
	}
	$db->free($resql);

	dol_syslog(__METHOD__.': '.$n.' produits mis à jour, '.$skipped.' exclus (override manuel)', LOG_INFO);
	return $n;
}

public static function supplier_infos()
{
	// Récupérer le délai de réception (entre validation commande et réception)
	// Actuellement on n'a pas la bonne valeur
	// Il faudra donc une valeur par défaut dans le fournisseur
	// On pourra considérer pour un fournisseur que la valeur saisie est celle pour tous ses produits
	// Il faut pouvoir surcharger pour certains produits non standard
	// Année avant période avant
	$sql_base = 'SELECT p.rowid, p.label,'
		.' 		SUM(cd.qty) qty_tot,'
		.'  	ROUND(AVG(cd.qty), 2) qty_moy,'
		.'  	ROUND(MAX(cd.qty), 2) qty_max,'
		.'  	COUNT(DISTINCT c.rowid) cmd_nb,'
		.' 	FROM `'.MAIN_DB_PREFIX.'commande_fournisseur` c'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'commande_fournisseurdet` cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'product` p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut${c_fk_status}'; // Statut commande expédiée

}

public static function batch_delay()
{
	// Récupérer le délai de DDM ou DLC moyen à réception de marchandise par produit
	// Dans le but de limiter les commandes sur les produits périssables
		$sql_base = 'SELECT p.rowid, p.ref, p.label, DATEDIFF(rd.sellby, r.date_valid) ddm_delay'
			.' 	FROM `'.MAIN_DB_PREFIX.'commande_fournisseur` c'
			.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_commande_fournisseurdet` cd ON cd.fk_commande=c.rowid'
			.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_product` p ON p.rowid=cd.fk_product'
			.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_commande_fournisseur_dispatch` rd ON rd.fk_commandefourndet=cd.rowid'
			.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_reception` r ON r.rowid=rd.fk_reception'
		.' 	WHERE c.fk_statut${c_fk_status}'; // Statut commande recue

}

/**
 * Calculate product consumption
 * 
 * @return void
 */
public static function conso_refresh()
{
	global $db;

	// @todo config
	$delai = 90; // Délai en jours pour la période "avant"

	// Année avant période avant
	$sql_base = 'SELECT p.rowid, p.ref, p.label'
		.' 		,SUM(cd.qty) qty_tot'
		.'  	,ROUND(MIN(cd.qty), 2) qty_min'
		.'  	,ROUND(AVG(cd.qty), 2) qty_moy'
		.'  	,ROUND(MAX(cd.qty), 2) qty_max'
		.'  	,COUNT(DISTINCT c.rowid) cmd_nb'
		.' 	FROM `'.MAIN_DB_PREFIX.'_commande` AS c'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_commandedet` AS cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'_product` AS p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut${c_fk_status}' // Statut commande (expédiée...)
		.'    AND p.fk_product_type=0'; // Uniquement produits stockés (exclure services)

	// Année avant période avant (pour comparaison avec maintenant)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.(365+$delai)
		.'    AND DATEDIFF (c.date_commande, NOW())<-'.(365)
		.' 	GROUP BY p.rowid';
	$sql = str_replace('${c_fk_status}', '=2', $sql); // Statut commande expédiée
	echo $sql.'<br /><br />';
	$list = [];
	$resql = $db->query($sql);
	if ($resql) {
		while($row = $db->fetch_object($resql)) {
			$list[$row->rowid] = $row;
		}
		$db->free($resql);
	}
	var_dump($list);

	
	// Année avant période après (pour projection)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.(365)
		.'    AND DATEDIFF (c.date_commande, NOW())<-'.(365-$delai)
		.' 	GROUP BY p.rowid';
	$sql = str_replace('${c_fk_status}', '=2', $sql); // Statut commande expédiée
	echo $sql.'<br /><br />';
	$list = [];
	$resql = $db->query($sql);
	if ($resql) {
		while($row = $db->fetch_object($resql)) {
			$list[$row->rowid] = $row;
		}
		$db->free($resql);
	}
	var_dump($list);

	// Année courante période avant (pour comparaison avec avant)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.$delai
		.' 	GROUP BY p.rowid';
	$sql = str_replace('${c_fk_status}', '>=1', $sql); // Statut commande expédiée
	echo $sql.'<br /><br />';
	$list = [];
	$resql = $db->query($sql);
	if ($resql) {
		while($row = $db->fetch_object($resql)) {
			$list[$row->rowid] = $row;
		}
		$db->free($resql);
	}
	var_dump($list);

}

/**
 * Calculate product stats
 * 
 * @return void
 */
public static function stats_refresh($options=[])
{
	global $db;

	$date_today = date('Y-m-d');

	$documents_types = [
		'propal' => [
			'prefix' => 'd',
			'object_table' => 'propal',
			'object_field_date' => 'datec',
			'object_status' => '1,2,4', // Valide (attente)
			'lines_table' => 'propaldet',
			'lines_field_join' => 'fk_propal',
		],
		'commande' => [
			'prefix' => 'c',
			'object_table' => 'commande',
			'object_field_date' => 'date_commande',
			'object_status' => '1, 2, 3', // Validée, Expédiée & Livrée (cloturée)
			'lines_table' => 'commandedet',
			'lines_field_join' => 'fk_commande',
		],
		'facture' => [
			'prefix' => 'f',
			'object_table' => 'facture',
			'object_field_date' => 'datef',
			'object_status' => '1,2', // Validée & cloturée
			'lines_table' => 'facturedet',
			'lines_field_join' => 'fk_facture',
		],
		'expedition' => [
			'prefix' => 'e',
			'object_table' => 'expedition',
			'object_field_date' => 'date_valid',
			'object_status' => '1,2', // Expédiée
			'lines_table' => 'expeditiondet',
			'lines_field_join' => 'fk_expedition',
			'orig_lines_table' => 'commandedet',
			'orig_lines_field_join' => 'fk_origin_line',
		],
		'reception' => [
			'prefix' => 'r',
			'object_table' => 'reception',
			'object_field_date' => 'date_valid',
			'object_status' => '1,2', // Reçue
			'lines_table' => 'commande_fournisseur_dispatch',
			'lines_field_join' => 'fk_reception',
		],
		'stockmvt' => [
			'prefix' => 'm',
			'object_table' => 'stock_mouvement',
			'object_field_date' => 'datem',
		],
	];

	$list = [];
	$stock = [];

	foreach($documents_types as $name=>$cfg) {
		$f_qty = isset($cfg['lines_table']) ?'cd.qty' :'c.value';

		// Année avant période avant
		$sql_base = 'SELECT p.rowid, DATE(c.'.$cfg['object_field_date'].') AS `date`'
			.'		, p.ref, p.label'
			.'		, SUM('.$f_qty.') AS '.$cfg['prefix'].'_qty_tot'
			.'		, ROUND(MIN('.$f_qty.'), 2)  AS '.$cfg['prefix'].'_qty_min'
			.'		, ROUND(AVG('.$f_qty.'), 2)  AS '.$cfg['prefix'].'_qty_moy'
			.'		, ROUND(MAX('.$f_qty.'), 2)  AS '.$cfg['prefix'].'_qty_max'
			.'		, COUNT(DISTINCT c.rowid)  AS '.$cfg['prefix'].'_nb'
			.'	FROM `'.MAIN_DB_PREFIX.$cfg['object_table'].'` AS c'
			.(isset($cfg['lines_table'])
				?	'	INNER JOIN `'.MAIN_DB_PREFIX.$cfg['lines_table'].'` AS cd ON cd.'.$cfg['lines_field_join'].'=c.rowid'
					.(isset($cfg['orig_lines_table'])
						? '	INNER JOIN `'.MAIN_DB_PREFIX.$cfg['orig_lines_table'].'` AS cdo ON cdo.rowid=cd.'.$cfg['orig_lines_field_join']
							.'	INNER JOIN `'.MAIN_DB_PREFIX.'product` AS p ON p.rowid=cdo.fk_product'
						: '	INNER JOIN `'.MAIN_DB_PREFIX.'product` AS p ON p.rowid=cd.fk_product'
					)
				: '	INNER JOIN `'.MAIN_DB_PREFIX.'product` AS p ON p.rowid=c.fk_product'
			)
			.'	INNER JOIN `'.MAIN_DB_PREFIX.'product_extrafields` AS p2 ON p2.fk_object=p.rowid'
			.'	WHERE 1'
			.(isset($cfg['object_status'])
				? '	AND c.fk_statut IN ('.$cfg['object_status'].')' // Statut commande (expédiée...)
				: ''
			)
			.(!empty($options['fk_soc_fournisseur']) ?'		AND p2.fk_soc_fournisseur='.$options['fk_soc_fournisseur'] :'')
			.'		AND p.fk_product_type=0'; // Uniquement produits stockés (exclure services)

		// Cumulé par jour
		$sql = $sql_base
			.'	GROUP BY p.rowid, DATE(c.'.$cfg['object_field_date'].')';
		$resql = $db->query($sql);
		if ($resql) {
			while($row = $db->fetch_assoc($resql)) {
				// Remove errors
				$year = substr($row['date'], 0, 4);
				if ($year<2020 || $year>2100)
					continue;

				if (!isset($list[$row['rowid']][$row['date']])) {
					$list[$row['rowid']][$row['date']] = $row;
				}
				else {
					$list[$row['rowid']][$row['date']] = array_merge($list[$row['rowid']][$row['date']], $row);
				}
			}
			$db->free($resql);
		}
		else {
			dol_syslog(__METHOD__.' SQL error: '.$db->lasterror(), LOG_ERR);
		}
	}

	foreach($list as $rowid=>&$list2) {
		ksort($list2);
		$n = count($list2);
		$i = 0;
		$s = 0;
		$sql_todo = [];

		foreach($list2 as $date=>$row) {
			$i++;

			// Rien on n'enregistre rien
			if (! (empty($row['p_nb']) && empty($row['c_nb']) && empty($row['f_nb']) && empty($row['e_nb']) && empty($row['r_nb']) && empty($row['m_nb']))) {
				$sql_todo[] = '('.$rowid.', "'.$date.'"'
					.', "'.(isset($row['p_nb']) ?$row['p_nb'] :'0').'", "'.(isset($row['p_qty_tot']) ?$row['p_qty_tot'] :'0').'"'
					.', "'.(isset($row['c_nb']) ?$row['c_nb'] :'0').'", "'.(isset($row['c_qty_tot']) ?$row['c_qty_tot'] :'0').'"'
					.', "'.(isset($row['f_nb']) ?$row['f_nb'] :'0').'", "'.(isset($row['f_qty_tot']) ?$row['f_qty_tot'] :'0').'"'
					.', "'.(isset($row['e_nb']) ?$row['e_nb'] :'0').'", "'.(isset($row['e_qty_tot']) ?$row['e_qty_tot'] :'0').'"'
					.', "'.(isset($row['r_nb']) ?$row['r_nb'] :'0').'", "'.(isset($row['r_qty_tot']) ?$row['r_qty_tot'] :'0').'"'
					.', "'.(isset($row['m_nb']) ?$row['m_nb'] :'0').'", "'.(isset($row['m_qty_tot']) ?$row['m_qty_tot'] :'0').'"'
					.')';
			}

			if (count($sql_todo)>=100 || $i==$n) {
				$sql = 'REPLACE INTO '.MAIN_DB_PREFIX.'product_stats
					(`fk_product`, `date`'
					.', `propal_nb`, `propal_qty`'
					.', `order_nb`, `order_qty`'
					.', `invoice_nb`, `invoice_qty`'
					.', `expe_nb`, `expe_qty`'
					.', `recep_nb`, `recep_qty`'
					.', `mvts_nb`, `mvts_qty`)'
					.' VALUES '.implode(', ', $sql_todo);
				$resql = $db->query($sql);
				$sql_todo = [];
			}

			// Stock change
			$s2 = $s;
			$s += isset($row['m_qty_tot']) ?(float)$row['m_qty_tot'] :0;

			// Stop if no stock (no need to insert empty rows)
			if ($s2<=0 && $s<=0)
				continue;

			$stock[$rowid][$date] = [
				'rowid' => $rowid,
				'date' => $date,
				's_phy' => $s,
			];
			// Report le stock sur les jours suivants sans mouvement, jusqu'à aujourd'hui
			// (les dates avec mouvement réel seront écrasées par l'itération suivante)
			$nextdate = date('Y-m-d', strtotime('+1 day', strtotime($date)));
			while(!isset($list2[$nextdate]) && $nextdate<$date_today) {
				$stock[$rowid][$nextdate] = [
					'rowid' => $rowid,
					'date' => $nextdate,
					's_phy' => $s,
				];
				$nextdate = date('Y-m-d', strtotime('+1 day', strtotime($nextdate)));
			}
		}

		// Clean... otherwise PAF!
		unset($list[$rowid]);

		// Stock
		if (isset($stock[$rowid])) {
			$sql_todo  = [];
			$n = count($stock[$rowid]);
			$i = 0;

			foreach($stock[$rowid] as $date=>$row) {
				$i++;

				$sql_todo[] = '('.$rowid.', "'.$date.'", "'.$row['s_phy'].'")';

				if (count($sql_todo)>=100 || $i==$n) {
					$sql = 'REPLACE INTO '.MAIN_DB_PREFIX.'product_stock_stats
						(`fk_product`, `date`, `stock_phy`)'
						.' VALUES '.implode(', ', $sql_todo);
					$resql = $db->query($sql);
					$sql_todo = [];
				}
				// @todo stock_theo on verra plus tard...
			}

			// Clean... otherwise PAF!
			unset($stock[$rowid]);
		}
	}
}

public static function graph($fk_product)
{

}

/**
 * Tâche planifiée (cron Dolibarr, jobtype "method") : rafraîchit les stats
 * produits puis recalcule les seuils de réappro (hors produits en override manuel).
 *
 * @param string    $params  Paramètres optionnels du cron (inutilisés)
 * @param User      $user     Utilisateur courant (fourni par le cron)
 * @return int                0 si OK, <0 si erreur (convention cron Dolibarr)
 */
public function cronReplenishRefresh($params = '', $user = null)
{
	dol_syslog(__METHOD__.' start', LOG_INFO);

	// 1. Rafraîchir les stats produits (commandes, stock, etc.) sur la période
	self::stats_refresh();

	// 2. Recalculer les seuils à partir des stats (hors override manuel)
	$n = self::seuils_refresh();

	if ($n < 0) {
		$this->error++;
		$this->output = 'Erreur lors du recalcul des seuils de réappro (voir syslog).';
		dol_syslog(__METHOD__.' error', LOG_ERR);
		return -1;
	}

	$this->output = 'Réappro : stats rafraîchies et seuils recalculés pour '.$n.' produit(s).';
	dol_syslog(__METHOD__.' done: '.$n.' produits', LOG_INFO);
	return 0;
}

}

MMI_Product_Replenish::__init();

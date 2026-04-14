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

public static function seuils_refresh()
{
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product p2'
		.' INNER JOIN ('
		.' 	SELECT p.rowid, p.label,'
		.' 		SUM(cd.qty) qty_tot, ROUND(AVG(cd.qty), 2) qty_moy, ROUND(MAX(cd.qty), 2) qty_max, COUNT(DISTINCT c.rowid) cmd_nb,'
		.' 		SUM(cd.qty)/365*2*30 qty_reappro, SUM(cd.qty)/365*3*7 qty_alert,'
		.' 		IF(MAX(cd.qty)+AVG(cd.qty) > SUM(cd.qty)/365*2*30, CEIL(MAX(cd.qty)+AVG(cd.qty)), CEIL(SUM(cd.qty)/365*2*30)) qty_reappro_opti, IF((2*MAX(cd.qty)+AVG(cd.qty))/3 > SUM(cd.qty)/365*3*7, CEIL((2*MAX(cd.qty)+AVG(cd.qty))/3), CEIL(SUM(cd.qty)/365*3*7)) qty_alert_opti'
		.' 	FROM `'.MAIN_DB_PREFIX.'commande` c'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'commandedet` cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `'.MAIN_DB_PREFIX.'product` p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut>=1 AND p.rowid NOT IN (1, 2) AND DATEDIFF (c.date_commande, NOW())>=-365'
		.' 	GROUP BY p.rowid'
		.' ) AS p3'
		.' ON p3.rowid=p2.rowid'
		.' SET p2.desiredstock=p3.qty_reappro_opti, p2.seuil_stock_alerte=p3.qty_alert_opti';

	echo $sql.'<br /><br />';

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

	// @todo config
	$delai = 90; // Délai en jours pour la période "avant"

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
		echo $sql.'<br /><br />';
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
					//$list[$row['rowid']][$row['date']][$cfg['prefix'].'_nb'] = $row[$cfg['prefix'].'_nb'];
					//$list[$row['rowid']][$row['date']][$cfg['prefix'].'_qty_tot'] = $row[$cfg['prefix'].'_qty_tot'];
				}
			}
			$db->free($resql);
		}
		else {
			var_dump($resql);
			var_dump($db->error());
		}
		//var_dump($list);

		// ON stoppe ici
		continue;

		// Année avant période avant (pour comparaison avec maintenant)
		$sql = $sql_base
			.'    AND DATEDIFF (c.'.$cfg['object_field_date'].', NOW())>=-'.(365+$delai)
			.'    AND DATEDIFF (c.'.$cfg['object_field_date'].', NOW())<-'.(365)
			.' 	GROUP BY p.rowid';
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
			.'    AND DATEDIFF (c.'.$cfg['object_field_date'].', NOW())>=-'.(365)
			.'    AND DATEDIFF (c.'.$cfg['object_field_date'].', NOW())<-'.(365-$delai)
			.' 	GROUP BY p.rowid';
		//echo $sql.'<br /><br />';
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
			.'    AND DATEDIFF (c.'.$cfg['object_field_date'].', NOW())>=-'.$delai
			.' 	GROUP BY p.rowid';
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
	//die();

	//var_dump($list);
	foreach($list as $rowid=>&$list2) {
		ksort($list2);
		$n = count($list2);
		$i = 0;
		$s = 0;
		//var_dump($list2); die();

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
				echo $sql.'<br /><br />';
				//continue;
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
			// Check if next dates exists, until now
			$nextdate = date('Y-m-d', strtotime('+1 day', strtotime($date)));
			while(!isset($list2[$rowid][$nextdate]) && $nextdate<$date_today) {
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
					echo $sql.'<br /><br />';
					//continue;
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

}

MMI_Product_Replenish::__init();

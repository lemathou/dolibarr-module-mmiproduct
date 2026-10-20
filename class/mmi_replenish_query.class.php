<?php
/**
 * mmi_replenish_query — construit la requête EOQ des seuils de réappro (RM3491).
 *
 * Classe SANS dépendance Dolibarr : tous les réglages (préfixe de tables, fenêtre,
 * plancher de jours, segments, filtres) arrivent en paramètres, ce qui rend la
 * requête testable unitairement (test/unit/mmi_replenish_query_test.php).
 * MMI_Product_Replenish::buildSeuilsQuery() résout les constantes Dolibarr puis délègue ici.
 *
 * Modèle (cf. CDC réappro § 5) — par produit, sur sa fenêtre d'analyse :
 *   d        = ventes / jours_calcul
 *   σ_d      = écart-type des ventes/jour sur les jours « en stock » (ou toute la fenêtre
 *              si le produit a été en stock moins de min_stock_days jours)
 *   freq     = min(jours avec vente / jours_calcul, 1)
 *   σ        = σ_d × √freq
 *   safety   = Z × √L × σ
 *   reorder  = d × L + safety          → seuil_stock_alerte
 *   order    = d × (L + T) + safety    → desiredstock (« Cmd Max »)
 *
 * jours_calcul = jours avec stock > 0 sur la fenêtre, PLANCHONNÉ à min_stock_days ;
 * si le produit n'a eu aucun jour de stock, la fenêtre entière.
 * Pourquoi (RM3491) : diviser par les seuls jours en stock, sans plancher, extrapolait
 * la demande d'un produit en rupture 89 jours sur 90 jusqu'à ×90 (OT0020 : 6 vendus → Cmd
 * Max 231), et un produit jamais en stock donnait NULL, écrit en 0 par le recalcul.
 *
 * Multiplication des lignes (RM3491) : les stocks par entrepôt et les prix fournisseur
 * sont agrégés en sous-requêtes (1 ligne/produit) — un produit sur 2 entrepôts voyait
 * ses ventes, sa demande et sa Cmd Max doublées.
 */
class mmi_replenish_query
{
	const DEFAULT_ANALYSE_DAYS = 90;
	const DEFAULT_MIN_STOCK_DAYS = 30;
	const DEFAULT_Z = 1.28;
	const DEFAULT_L = 10;
	const DEFAULT_T = 28;
	const DEFAULT_STOCKCHECK_CATEGORY = 7;
	const DEFAULT_PRO_SERVICE_LEVEL = 1.65;
	const DEFAULT_STOCKCHECK_SERVICE_LEVEL = 1.96;
	/** Garde-fou : taille maximale du calendrier généré (jours). */
	const MAX_WINDOW_DAYS = 730;

	/**
	 * Normalise les options (valeurs par défaut, types, bornes).
	 *
	 * @param array $o  prefix, analyse_days_nb, max_window_days, min_stock_days,
	 *                  per_product_window, filter_supplier_id, filter_product_pro,
	 *                  fk_product_categorie, product_ids, seg_pro_cat, seg_sc_cat, pro_sl, sc_sl
	 * @return array
	 */
	public static function normalize(array $o)
	{
		$n = [];
		$n['prefix'] = isset($o['prefix']) ? (string) $o['prefix'] : 'llx_';
		$n['analyse_days_nb'] = !empty($o['analyse_days_nb']) ? (int) $o['analyse_days_nb'] : self::DEFAULT_ANALYSE_DAYS;
		$n['analyse_days_nb'] = min(max(1, $n['analyse_days_nb']), self::MAX_WINDOW_DAYS);
		// Plancher du dénominateur : 0 ou absent → défaut ; 1 = pas de plancher
		$n['min_stock_days'] = isset($o['min_stock_days']) && (int) $o['min_stock_days'] > 0
			? (int) $o['min_stock_days'] : self::DEFAULT_MIN_STOCK_DAYS;
		$n['per_product_window'] = !isset($o['per_product_window']) || !empty($o['per_product_window']);
		$maxw = !empty($o['max_window_days']) ? (int) $o['max_window_days'] : 0;
		$n['max_window_days'] = min(max($n['analyse_days_nb'], $maxw), self::MAX_WINDOW_DAYS);
		$n['filter_supplier_id'] = !empty($o['filter_supplier_id']) ? (int) $o['filter_supplier_id'] : 0;
		$n['filter_product_pro'] = !empty($o['filter_product_pro']);
		$n['fk_product_categorie'] = !empty($o['fk_product_categorie']) ? (int) $o['fk_product_categorie'] : 0;
		$n['product_ids'] = self::sanitizeProductIds(isset($o['product_ids']) ? $o['product_ids'] : null);
		$n['seg_pro_cat'] = !empty($o['seg_pro_cat']) ? (int) $o['seg_pro_cat'] : 0;
		$n['seg_sc_cat'] = !empty($o['seg_sc_cat']) ? (int) $o['seg_sc_cat'] : self::DEFAULT_STOCKCHECK_CATEGORY;
		$n['pro_sl'] = !empty($o['pro_sl']) && (float) $o['pro_sl'] > 0 ? (float) $o['pro_sl'] : self::DEFAULT_PRO_SERVICE_LEVEL;
		$n['sc_sl'] = !empty($o['sc_sl']) && (float) $o['sc_sl'] > 0 ? (float) $o['sc_sl'] : self::DEFAULT_STOCKCHECK_SERVICE_LEVEL;
		return $n;
	}

	/**
	 * Normalise une liste d'IDs produit en tableau d'entiers > 0 (dédoublonné).
	 *
	 * @param mixed $ids  Tableau d'IDs, ou null si pas de restriction par paquet
	 * @return int[]|null  [] si liste fournie mais vide, null si pas de restriction
	 */
	public static function sanitizeProductIds($ids)
	{
		if ($ids === null)
			return null;
		if (!is_array($ids))
			$ids = [$ids];
		$out = [];
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id > 0)
				$out[$id] = $id;
		}
		return array_values($out);
	}

	/**
	 * Expression SQL du coefficient Z (niveau de service) : le plus prudent entre le Z
	 * propre du produit, celui du segment PRO et celui du segment StockCheck (RM1854).
	 *
	 * @param array $n  options normalisées
	 * @return string
	 */
	public static function zExpr(array $n)
	{
		$z = 'GREATEST(IF(p2.replenish_service_level > 0, p2.replenish_service_level, '.self::DEFAULT_Z.')';
		if ($n['seg_pro_cat'])
			$z .= ', IF(COALESCE(seg.is_pro, 0) = 1, '.$n['pro_sl'].', 0)';
		if ($n['seg_sc_cat'])
			$z .= ', IF(COALESCE(seg.is_sc, 0) = 1, '.$n['sc_sl'].', 0)';
		return $z.')';
	}

	/**
	 * Construit la requête EOQ.
	 *
	 * @param array $options  Voir normalize()
	 * @return string         Requête SQL (une ligne par produit vendu sur sa fenêtre)
	 */
	public static function build(array $options = [])
	{
		$n = self::normalize($options);
		$pfx = $n['prefix'];
		$N = $n['analyse_days_nb'];
		$MIN = $n['min_stock_days'];
		$MAXW = $n['max_window_days'];

		// Calendrier : n = 0 (aujourd'hui) … MAXW-1
		$days_sql = 'SELECT 0 AS n';
		for ($i = 1; $i < $MAXW; $i++)
			$days_sql .= ' UNION ALL SELECT '.$i;

		// Fenêtre du produit : son extrafield replenish_analysis_days s'il est renseigné, sinon la fenêtre demandée
		$win = $n['per_product_window'] ? 'COALESCE(NULLIF(p2.replenish_analysis_days, 0), '.$N.')' : (string) $N;

		$qty = 'COALESCE(ds.order_qty, 0)';
		$sum_qty = 'SUM('.$qty.')';
		$stock_days = 'COUNT(pss.fk_product)';
		// Dénominateur : jours avec stock, plancher MIN ; fenêtre entière si jamais en stock
		$denom = 'IF('.$stock_days.' = 0, MAX('.$win.'), GREATEST('.$stock_days.', '.$MIN.'))';
		$d = '('.$sum_qty.' / '.$denom.')';
		$sigma_all = 'STDDEV_POP('.$qty.')';
		$sigma_stock = 'STDDEV_POP(CASE WHEN pss.fk_product IS NOT NULL THEN '.$qty.' END)';
		$sigma_d = 'IF('.$stock_days.' < '.$MIN.', '.$sigma_all.', '.$sigma_stock.')';
		$sale_days = 'COUNT(CASE WHEN '.$qty.' > 0 THEN 1 END)';
		$freq = 'LEAST('.$sale_days.' / '.$denom.', 1)';
		$sigma_corr = '('.$sigma_d.' * SQRT('.$freq.'))';
		$L = 'IF(s2.reception_delay > 0, s2.reception_delay, '.self::DEFAULT_L.')';
		$T = 'IF(s2.replenish_delay > 0, s2.replenish_delay, '.self::DEFAULT_T.')';
		$Z = self::zExpr($n);
		$safety = '('.$Z.' * SQRT('.$L.') * '.$sigma_corr.')';
		$reorder = '('.$d.' * '.$L.' + '.$safety.')';
		$order = '('.$d.' * ('.$L.' + '.$T.') + '.$safety.')';

		$seg_cats = array_values(array_unique(array_filter([$n['seg_pro_cat'], $n['seg_sc_cat']])));

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

		MAX('.$win.') AS window_days,
		'.$stock_days.' AS stock_days,
		'.$denom.' AS calc_days,

		'.$sum_qty.' AS cmd_qty,
		SUM(COALESCE(ds.order_nb, 0)) AS cmd_nb,
		'.$d.' AS d,

		'.$sigma_d.' AS sigma_d,
		'.$freq.' AS freq,
		'.$sigma_corr.' AS sigma_corrected,

		'.$L.' AS L,
		'.$T.' AS T,
		'.$Z.' AS Z,

		ROUND('.$safety.', 2) AS safety_stock,
		ROUND('.$reorder.', 2) AS reorder_point,
		COALESCE(ps.reel, 0) AS stock_current,
		ROUND('.$order.', 2) AS order_qty,

		IF(pfp.packaging > 0, pfp.packaging, 1) AS packaging

	FROM '.$pfx.'product AS p

	LEFT JOIN '.$pfx.'product_extrafields AS p2
		ON p2.fk_object = p.rowid'

		.($n['filter_product_pro'] ? '
	LEFT JOIN '.$pfx.'categorie_product AS kp
		ON kp.fk_product = p.rowid' : '')

		.'
	LEFT JOIN (
		SELECT fk_product, SUM(reel) AS reel
		FROM '.$pfx.'product_stock
		GROUP BY fk_product
	) AS ps
		ON ps.fk_product = p.rowid

	LEFT JOIN '.$pfx.'societe AS s
		ON s.rowid = p2.fk_soc_fournisseur

	LEFT JOIN (
		SELECT fk_product, fk_soc, MAX(packaging) AS packaging
		FROM '.$pfx.'product_fournisseur_price
		GROUP BY fk_product, fk_soc
	) AS pfp
		ON pfp.fk_product = p.rowid AND pfp.fk_soc = s.rowid

	LEFT JOIN '.$pfx.'societe_extrafields AS s2
		ON s2.fk_object = s.rowid

	INNER JOIN (
		SELECT n, DATE_SUB(CURDATE(), INTERVAL n DAY) AS day
		FROM ('.$days_sql.') AS days
	) AS d
		ON d.n < '.$win.'

	LEFT JOIN '.$pfx.'product_stats AS ds
		ON ds.fk_product = p.rowid AND ds.`date` = d.day

	LEFT JOIN '.$pfx.'product_stock_stats AS pss
		ON pss.fk_product = p.rowid AND pss.`date` = d.day AND pss.stock_phy > 0'

		.($seg_cats ? '
	LEFT JOIN (
		SELECT fk_product,
			MAX(CASE WHEN fk_categorie = '.$n['seg_pro_cat'].' THEN 1 ELSE 0 END) AS is_pro,
			MAX(CASE WHEN fk_categorie = '.$n['seg_sc_cat'].' THEN 1 ELSE 0 END) AS is_sc
		FROM '.$pfx.'categorie_product
		WHERE fk_categorie IN ('.implode(',', $seg_cats).')
		GROUP BY fk_product
	) AS seg
		ON seg.fk_product = p.rowid' : '')

		.'
	WHERE p.fk_product_type = 0'
		.($n['filter_supplier_id'] ? ' AND p2.fk_soc_fournisseur = '.$n['filter_supplier_id'] : '')
		.($n['filter_product_pro'] ? ' AND kp.fk_categorie = '.$n['fk_product_categorie'] : '')
		.($n['product_ids'] !== null ? ' AND p.rowid IN ('.(count($n['product_ids']) ? implode(',', $n['product_ids']) : '0').')' : '')

		.'
	GROUP BY p.rowid
	HAVING '.$sum_qty.' > 0';

		return $sql;
	}

	/**
	 * Clause WHERE commune aux produits candidats (hors ventes) — sert à la remise à
	 * zéro des produits non vendus et à la todo-list du traitement par paquets.
	 * Suppose les alias p (product), p2 (extrafields) et kp (categorie_product si filtre PRO).
	 *
	 * @param array $n  options normalisées
	 * @return string
	 */
	public static function candidatesWhere(array $n)
	{
		return ' p.fk_product_type = 0'
			.($n['filter_supplier_id'] ? ' AND p2.fk_soc_fournisseur = '.$n['filter_supplier_id'] : '')
			.($n['filter_product_pro'] ? ' AND kp.fk_categorie = '.$n['fk_product_categorie'] : '')
			.($n['product_ids'] !== null ? ' AND p.rowid IN ('.(count($n['product_ids']) ? implode(',', $n['product_ids']) : '0').')' : '');
	}

	/**
	 * Requête de remise à zéro des seuils des produits candidats SANS vente sur leur
	 * fenêtre (= absents du calcul), hors override manuel.
	 *
	 * @param array $options      Voir normalize()
	 * @param int[] $computed_ids IDs produit retournés par le calcul (conservés)
	 * @return string
	 */
	public static function buildResetQuery(array $options, array $computed_ids)
	{
		$n = self::normalize($options);
		$pfx = $n['prefix'];
		$ids = self::sanitizeProductIds($computed_ids);
		return 'UPDATE '.$pfx.'product AS p'
			.' INNER JOIN '.$pfx.'product_extrafields AS p2 ON p2.fk_object = p.rowid'
			.($n['filter_product_pro'] ? ' LEFT JOIN '.$pfx.'categorie_product AS kp ON kp.fk_product = p.rowid' : '')
			.' SET p.seuil_stock_alerte = 0, p.desiredstock = 0,'
			.' p2.replenish_safety_stock = NULL, p2.replenish_reorder_point = NULL'
			.' WHERE'.self::candidatesWhere($n)
			.' AND COALESCE(p2.replenish_manual, 0) = 0'
			.' AND (p.seuil_stock_alerte > 0 OR p.desiredstock > 0)'
			.(count($ids) ? ' AND p.rowid NOT IN ('.implode(',', $ids).')' : '');
	}
}

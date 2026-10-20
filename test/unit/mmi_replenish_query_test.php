<?php
/**
 * Tests unitaires de mmi_replenish_query (requête EOQ des seuils de réappro) — RM3491.
 * Sans dépendance (ni Dolibarr ni phpunit) : php7.4 test/unit/mmi_replenish_query_test.php
 * Code de sortie 0 si tout passe.
 */

require_once __DIR__.'/../../class/mmi_replenish_query.class.php';

$fail = 0;
$n = 0;
function check($label, $cond, $detail = '')
{
	global $fail, $n;
	$n++;
	echo ($cond ? 'ok   ' : 'FAIL ').$label.(!$cond && $detail !== '' ? ' — '.$detail : '')."\n";
	if (!$cond)
		$fail++;
}
function squash($s)
{
	return preg_replace('/\s+/', ' ', $s);
}
$Q = 'mmi_replenish_query';

// --- normalize
$d = $Q::normalize([]);
check('défauts : fenêtre 90, plancher 30, StockCheck 7, Z 1.65/1.96', $d['analyse_days_nb'] === 90 && $d['min_stock_days'] === 30 && $d['seg_sc_cat'] === 7 && $d['pro_sl'] == 1.65 && $d['sc_sl'] == 1.96);
check('max_window_days ≥ fenêtre', $Q::normalize(['analyse_days_nb'=>120, 'max_window_days'=>30])['max_window_days'] === 120);
check('max_window_days borné à 730', $Q::normalize(['max_window_days'=>5000])['max_window_days'] === 730);
check('plancher 0 → défaut, 1 → 1 (pas de plancher)', $Q::normalize(['min_stock_days'=>0])['min_stock_days'] === 30 && $Q::normalize(['min_stock_days'=>1])['min_stock_days'] === 1);
check('product_ids dédoublonnés, entiers > 0', $Q::sanitizeProductIds(['3', 3, '0', -1, 'x', 7]) === [3, 7]);
check('product_ids null = pas de restriction, [] = liste vide', $Q::sanitizeProductIds(null) === null && $Q::sanitizeProductIds([]) === []);

// --- build : structure de la requête
$sql = squash($Q::build(['analyse_days_nb'=>90, 'seg_pro_cat'=>146]));
check('stocks agrégés par produit (plus de ligne par entrepôt)', strpos($sql, 'SELECT fk_product, SUM(reel) AS reel FROM llx_product_stock GROUP BY fk_product') !== false);
check('plus de LEFT JOIN direct sur llx_product_stock', !preg_match('/LEFT JOIN llx_product_stock AS ps ON/', $sql));
check('prix fournisseur agrégés (1 ligne produit × fournisseur)', strpos($sql, 'SELECT fk_product, fk_soc, MAX(packaging) AS packaging FROM llx_product_fournisseur_price GROUP BY fk_product, fk_soc') !== false);
check('plancher du dénominateur : GREATEST(jours stock, 30)', strpos($sql, 'GREATEST(COUNT(pss.fk_product), 30)') !== false);
check('jamais en stock → fenêtre entière (pas de NULL)', strpos($sql, 'IF(COUNT(pss.fk_product) = 0, MAX(COALESCE(NULLIF(p2.replenish_analysis_days, 0), 90))') !== false);
check('fenêtre par produit appliquée au calendrier', strpos($sql, 'ON d.n < COALESCE(NULLIF(p2.replenish_analysis_days, 0), 90)') !== false);
check('σ sur les jours en stock quand ≥ plancher, sinon toute la fenêtre', strpos($sql, 'IF(COUNT(pss.fk_product) < 30, STDDEV_POP(COALESCE(ds.order_qty, 0)), STDDEV_POP(CASE WHEN pss.fk_product IS NOT NULL THEN COALESCE(ds.order_qty, 0) END))') !== false);
check('fréquence bornée à 1', strpos($sql, 'LEAST(COUNT(CASE WHEN COALESCE(ds.order_qty, 0) > 0 THEN 1 END) /') !== false);
check('calendrier de 90 jours (0..89)', strpos($sql, 'UNION ALL SELECT 89)') !== false && strpos($sql, 'UNION ALL SELECT 90') === false);
check('Z = plus prudent entre produit, PRO (1.65) et StockCheck (1.96)', strpos($sql, 'GREATEST(IF(p2.replenish_service_level > 0, p2.replenish_service_level, 1.28), IF(COALESCE(seg.is_pro, 0) = 1, 1.65, 0), IF(COALESCE(seg.is_sc, 0) = 1, 1.96, 0))') !== false);
check('segments : sous-requête seg sur les catégories 146 et 7', strpos($sql, 'WHERE fk_categorie IN (146,7)') !== false);
check('produits stockés uniquement, vendus sur la fenêtre', strpos($sql, 'WHERE p.fk_product_type = 0') !== false && strpos($sql, 'HAVING SUM(COALESCE(ds.order_qty, 0)) > 0') !== false);
check('colonnes attendues par la page et seuils_refresh', preg_match('/AS product_id,.*AS stock_days,.*AS calc_days,.*AS cmd_qty,.*AS d,.*AS safety_stock,.*AS reorder_point,.*AS stock_current,.*AS order_qty,.*AS packaging/', $sql) === 1);

// --- build : filtres
$sql = squash($Q::build(['filter_supplier_id'=>42]));
check('filtre fournisseur', strpos($sql, 'AND p2.fk_soc_fournisseur = 42') !== false);
$sql = squash($Q::build(['filter_product_pro'=>1, 'fk_product_categorie'=>146]));
check('filtre PRO : join catégorie + where', strpos($sql, 'LEFT JOIN llx_categorie_product AS kp ON kp.fk_product = p.rowid') !== false && strpos($sql, 'AND kp.fk_categorie = 146') !== false);
$sql = squash($Q::build(['product_ids'=>[5, 9, 5]]));
check('paquet d\'IDs', strpos($sql, 'AND p.rowid IN (5,9)') !== false);
$sql = squash($Q::build(['product_ids'=>[]]));
check('paquet vide → aucun produit (IN (0))', strpos($sql, 'AND p.rowid IN (0)') !== false);
$sql = squash($Q::build(['per_product_window'=>false, 'analyse_days_nb'=>60]));
check('fenêtre globale si per_product_window=false', strpos($sql, 'ON d.n < 60') !== false && strpos($sql, 'replenish_analysis_days, 0)') === false);
$sql = squash($Q::build(['prefix'=>'xx_']));
check('préfixe de tables paramétrable', strpos($sql, 'FROM xx_product AS p') !== false && strpos($sql, 'llx_') === false);
$sql = squash($Q::build(['min_stock_days'=>10]));
check('plancher paramétrable', strpos($sql, 'GREATEST(COUNT(pss.fk_product), 10)') !== false);

// --- buildResetQuery
$r = squash($Q::buildResetQuery(['filter_supplier_id'=>42, 'product_ids'=>[1, 2, 3]], [2]));
check('reset : met seuil/désiré à 0 et vide les extrafields calculés', strpos($r, 'SET p.seuil_stock_alerte = 0, p.desiredstock = 0, p2.replenish_safety_stock = NULL, p2.replenish_reorder_point = NULL') !== false);
check('reset : respecte filtre fournisseur et paquet', strpos($r, 'AND p2.fk_soc_fournisseur = 42') !== false && strpos($r, 'AND p.rowid IN (1,2,3)') !== false);
check('reset : épargne les produits calculés et les manuels', strpos($r, 'AND p.rowid NOT IN (2)') !== false && strpos($r, 'AND COALESCE(p2.replenish_manual, 0) = 0') !== false);
check('reset : ne touche que les produits ayant des seuils', strpos($r, 'AND (p.seuil_stock_alerte > 0 OR p.desiredstock > 0)') !== false);
$r = squash($Q::buildResetQuery([], []));
check('reset : sans produit calculé, pas de NOT IN', strpos($r, 'NOT IN') === false);

echo "\n$n test(s), $fail échec(s)\n";
exit($fail ? 1 : 0);

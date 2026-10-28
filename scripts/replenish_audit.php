#!/usr/bin/php7.4
<?php
/**
 * replenish_audit.php — audit rejouable du calcul des quantités à recommander (RM3491).
 *
 * LECTURE SEULE (SELECT + table temporaire). Rejouable sur n'importe quel env, prod comprise.
 *
 * Usage (depuis ce dossier) :
 *   php7.4 replenish_audit.php [--days=90] [--ratio=3] [--top=25] [--min-days=N] [--no-recalc] [--out=/chemin/rapport.md]
 *
 *   --days      fenêtre d'analyse en jours (défaut 90, comme la page Stats réappro)
 *   --ratio     seuil « grosse différence » : Cmd Max ≥ ratio × vendu, ou recalcul ≥ ratio × stocké (défaut 3)
 *   --top       nombre de lignes dans chaque liste détaillée (défaut 25)
 *   --min-days  plancher du dénominateur à simuler (défaut : constante du module)
 *   --no-recalc ne pas simuler le recalcul (sections 1 à 5 seulement)
 *   --out       écrit aussi le rapport markdown dans ce fichier
 *
 * Sections :
 *   1. Fraîcheur des stats et état du cron (cause racine de l'audit du 2026-10-20)
 *   2. Paramètres effectifs du calcul
 *   3. Produits vendus sur la fenêtre à seuils 0 (« à 0 alors qu'elle en vend »)
 *   4. Produits sans vente sur la fenêtre à seuils > 0 (seuils périmés) — dont visibles en réappro
 *   5. Cmd Max stockée ≥ ratio × vendu (« valeurs étranges ») + défauts de structure (multi-entrepôt, prix doublons)
 *   6. Simulation du recalcul avec le code courant : NULL, plancher, écarts vs stocké, grosses différences
 *   7. Résumé
 */

chdir(__DIR__);
require_once __DIR__.'/../master_load.inc.php';
dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

global $db, $langs;

// ---- Options
$opt = ['days'=>90, 'ratio'=>3, 'top'=>25, 'min-days'=>null, 'no-recalc'=>false, 'out'=>null];
foreach (array_slice($argv, 1) as $a) {
	if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
		$k = $m[1];
		if (!array_key_exists($k, $opt)) {
			fwrite(STDERR, "Option inconnue : --$k\n");
			exit(2);
		}
		$opt[$k] = isset($m[2]) ? $m[2] : true;
	}
}
$DAYS = max(1, (int) $opt['days']);
$RATIO = max(1.1, (float) $opt['ratio']);
$TOP = max(1, (int) $opt['top']);
$MIN = $opt['min-days'] !== null ? (int) $opt['min-days'] : MMI_Product_Replenish::minStockDays();

$out = '';
function w($line = '')
{
	global $out;
	$out .= $line."\n";
	echo $line."\n";
}
/** Exécute une requête et rend les lignes (assoc). */
function rows($sql)
{
	global $db;
	$r = $db->query($sql);
	if (!$r) {
		w('> ERREUR SQL : '.$db->lasterror());
		return [];
	}
	$list = [];
	while ($row = $db->fetch_array($r, MYSQLI_ASSOC))
		$list[] = $row;
	$db->free($r);
	return $list;
}
function one($sql)
{
	$r = rows($sql);
	return $r ? $r[0] : [];
}
/** Table markdown. */
function table(array $list, array $cols, $max)
{
	if (!$list) {
		w('_(aucune ligne)_');
		return;
	}
	w('| '.implode(' | ', array_values($cols)).' |');
	w('|'.str_repeat('---|', count($cols)));
	$i = 0;
	foreach ($list as $row) {
		$cells = [];
		foreach (array_keys($cols) as $k)
			$cells[] = isset($row[$k]) ? str_replace('|', '/', (string) $row[$k]) : '';
		w('| '.implode(' | ', $cells).' |');
		if (++$i >= $max) {
			if (count($list) > $max)
				w('| … | '.(count($list) - $max).' ligne(s) de plus |'.str_repeat(' |', count($cols) - 2));
			break;
		}
	}
}
$P = MAIN_DB_PREFIX;
$since = 'DATE_SUB(CURDATE(), INTERVAL '.($DAYS - 1).' DAY)'; // fenêtre = DAYS jours, aujourd\'hui inclus (comme la requête EOQ)

w('# Audit réappro — quantités à recommander');
w();
w('Base `'.$db->database_name.'` · '.date('Y-m-d H:i').' · fenêtre '.$DAYS.' j · plancher '.$MIN.' j · ratio « grosse différence » ×'.$RATIO);
w();

// ---------------------------------------------------------------- 1. Fraîcheur
w('## 1. Fraîcheur des stats et cron');
w();
$f = one("SELECT (SELECT MAX(`date`) FROM {$P}product_stats) AS stats, (SELECT COUNT(*) FROM {$P}product_stats) AS n_stats,
	(SELECT MAX(`date`) FROM {$P}product_stock_stats) AS stock_stats, (SELECT COUNT(*) FROM {$P}product_stock_stats) AS n_stock_stats");
$age = !empty($f['stats']) ? (int) floor((time() - strtotime($f['stats'])) / 86400) : null;
w('- Dernière journée de ventes dans `product_stats` : **'.($f['stats'] ?: 'aucune').'**'.($age !== null ? ' (il y a '.$age.' j)' : '').' — '.(int) $f['n_stats'].' lignes');
w('- Dernière journée dans `product_stock_stats` : **'.($f['stock_stats'] ?: 'aucune').'** — '.(int) $f['n_stock_stats'].' lignes');
$stale = ($age === null || $age > 7);
w('- Verdict : '.($stale ? '⚠ **STATS PÉRIMÉES** — le calcul des seuils ne voit pas les ventes récentes ; le recalcul sur '.$DAYS.' j est '.($age !== null && $age >= $DAYS ? '**vide**' : 'tronqué') : '✓ stats fraîches'));
$cron = rows("SELECT rowid, label, status, datestart, datelastrun, datenextrun, lastresult, nbrun, frequency, unitfrequency FROM {$P}cronjob WHERE methodename='cronReplenishRefresh' OR label='MMIProductReplenishRefreshCron'");
if (!$cron)
	w('- Cron réappro : ⚠ **absent de llx_cronjob** (réactiver le module pour l\'enregistrer)');
foreach ($cron as $c)
	w('- Cron `'.$c['label'].'` : '.($c['status'] ? '✓ actif' : '⚠ **désactivé**').' · dernier run : '.($c['datelastrun'] ?: 'jamais').' · prochain : '.($c['datenextrun'] ?: '—').' · résultat : '.($c['lastresult'] === null ? '—' : $c['lastresult']).' · '.(int) $c['nbrun'].' exécution(s) · toutes les '.$c['frequency'].' × '.$c['unitfrequency'].' s');
$others = rows("SELECT label, datelastrun FROM {$P}cronjob WHERE status=1 AND datelastrun IS NOT NULL ORDER BY datelastrun DESC LIMIT 1");
if ($others)
	w('- Dernier job cron Dolibarr exécuté (le cron système tourne-t-il ?) : `'.$others[0]['label'].'` le '.$others[0]['datelastrun']);
w();

// ---------------------------------------------------------------- 2. Paramètres
w('## 2. Paramètres effectifs');
w();
w('- Plancher dénominateur `MMIPRODUCT_REPLENISH_MIN_STOCK_DAYS` : '.MMI_Product_Replenish::minStockDays().' j'.($MIN != MMI_Product_Replenish::minStockDays() ? ' (simulé ici : '.$MIN.')' : ''));
w('- Remise à zéro des produits sans vente `MMIPRODUCT_REPLENISH_RESET_NO_SALES` : '.(getDolGlobalInt('MMIPRODUCT_REPLENISH_RESET_NO_SALES', 1) ? 'oui' : 'non'));
w('- Segments : PRO cat '.getDolGlobalInt('MMIPRODUCT_PRO_PRODUCT_CATEGORY').' (Z '.(getDolGlobalString('MMIPRODUCT_PRO_SERVICE_LEVEL') ?: '1.65 déf.').') · StockCheck cat '.(getDolGlobalInt('MMIPRODUCT_STOCKCHECK_PRODUCT_CATEGORY') ?: '7 déf.').' (Z '.(getDolGlobalString('MMIPRODUCT_STOCKCHECK_SERVICE_LEVEL') ?: '1.96 déf.').')');
$sup = one("SELECT COUNT(*) n, SUM(s2.reception_delay>0) recept, SUM(s2.replenish_delay>0) replen FROM {$P}societe s LEFT JOIN {$P}societe_extrafields s2 ON s2.fk_object=s.rowid WHERE s.fournisseur=1 AND s.status=1");
w('- Fournisseurs actifs : '.(int) $sup['n'].' · délai de réception renseigné : '.(int) $sup['recept'].' · délai entre commandes renseigné : '.(int) $sup['replen'].' (sinon L=10 j, T=28 j)');
$ef = one("SELECT SUM(COALESCE(p2.replenish_manual,0)=1) manual, SUM(p2.replenish_analysis_days>0) win, SUM(p2.replenish_service_level>0) sl FROM {$P}product p LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid WHERE p.fk_product_type=0");
w('- Produits « ajustés manuellement » (jamais recalculés) : '.(int) $ef['manual'].' · fenêtre propre : '.(int) $ef['win'].' · niveau de service propre : '.(int) $ef['sl']);
w();

// ---------------------------------------------------------------- ventes réelles (commandes), indépendantes des stats
$db->query("DROP TEMPORARY TABLE IF EXISTS tmp_audit_sales");
$db->query("CREATE TEMPORARY TABLE tmp_audit_sales AS
	SELECT cd.fk_product, SUM(cd.qty) qty, COUNT(DISTINCT c.rowid) nb, MAX(c.date_commande) last_order
	FROM {$P}commande c JOIN {$P}commandedet cd ON cd.fk_commande=c.rowid JOIN {$P}product pp ON pp.rowid=cd.fk_product
	WHERE c.fk_statut IN (1,2,3) AND c.date_commande >= $since AND pp.fk_product_type = 0
	GROUP BY cd.fk_product");
$db->query("ALTER TABLE tmp_audit_sales ADD PRIMARY KEY (fk_product)");
$v = one("SELECT (SELECT COUNT(*) FROM tmp_audit_sales) n_cmd,
	(SELECT COUNT(*) FROM (SELECT fk_product FROM {$P}product_stats WHERE `date` >= $since GROUP BY fk_product HAVING SUM(order_qty)>0) t) n_stats,
	(SELECT COUNT(*) FROM tmp_audit_sales s LEFT JOIN (SELECT fk_product, SUM(order_qty) q FROM {$P}product_stats WHERE `date` >= $since GROUP BY fk_product) ps ON ps.fk_product=s.fk_product WHERE IFNULL(ps.q,0)=0) absents");

// ---------------------------------------------------------------- 3. Vendus à 0
w('## 3. Produits vendus sur la fenêtre avec seuil ET stock désiré à 0');
w();
w('Ventes réelles de produits stockés (commandes validées/expédiées/livrées) : **'.(int) $v['n_cmd'].'** produits vendus · vus par les stats : '.(int) $v['n_stats'].' · **vendus mais absents des stats : '.(int) $v['absents'].'**'.((int) $v['absents'] > 0 ? ' ⚠' : ''));
$where_zero = "p.fk_product_type=0 AND IFNULL(p.desiredstock,0)=0 AND IFNULL(p.seuil_stock_alerte,0)=0 AND COALESCE(p2.replenish_manual,0)=0";
$z = one("SELECT COUNT(*) n, IFNULL(SUM(s.qty),0) qty FROM tmp_audit_sales s JOIN {$P}product p ON p.rowid=s.fk_product LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid WHERE $where_zero");
w('**'.(int) $z['n'].' produit(s)** vendus ('.(float) $z['qty'].' unités) n\'apparaissent jamais en réappro (hors « ajustés manuellement »).');
w();
table(rows("SELECT p.ref, LEFT(p.label,60) label, s.qty vendu, s.nb cmd, DATE(s.last_order) derniere_cmd, p.stock, DATE(p.datec) cree_le
	FROM tmp_audit_sales s JOIN {$P}product p ON p.rowid=s.fk_product LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE $where_zero ORDER BY s.qty DESC"),
	['ref'=>'Réf', 'label'=>'Produit', 'vendu'=>'Vendu', 'cmd'=>'Cmd', 'derniere_cmd'=>'Dernière cmd', 'stock'=>'Stock', 'cree_le'=>'Créé le'], $TOP);
$z2 = one("SELECT COUNT(*) n FROM tmp_audit_sales s JOIN {$P}product p ON p.rowid=s.fk_product WHERE p.fk_product_type=0 AND IFNULL(p.desiredstock,0)=0 AND p.seuil_stock_alerte>0");
w();
w('Vendus avec seuil > 0 mais stock désiré à 0 : '.(int) $z2['n']);
w();

// ---------------------------------------------------------------- 4. Sans vente à seuils > 0
w('## 4. Produits sans vente sur la fenêtre avec stock désiré > 0 (seuils périmés)');
w();
$where_stale = "p.fk_product_type=0 AND p.tosell=1 AND s.fk_product IS NULL AND p.desiredstock>0 AND COALESCE(p2.replenish_manual,0)=0";
$st = one("SELECT COUNT(*) n, IFNULL(SUM(p.desiredstock),0) desired, SUM(p.stock < GREATEST(p.seuil_stock_alerte,0)) visibles
	FROM {$P}product p LEFT JOIN tmp_audit_sales s ON s.fk_product=p.rowid LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid WHERE $where_stale");
$stm = one("SELECT COUNT(*) n FROM {$P}product p LEFT JOIN tmp_audit_sales s ON s.fk_product=p.rowid LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE p.fk_product_type=0 AND p.tosell=1 AND s.fk_product IS NULL AND p.desiredstock>0 AND COALESCE(p2.replenish_manual,0)=1");
w('**'.(int) $st['n'].' produit(s)** en vente sans aucune vente sur '.$DAYS.' j gardent un stock désiré > 0 ('.(int) $st['desired'].' unités cumulées), **dont '.(int) $st['visibles'].' sous leur seuil → affichés en réappro**. (+ '.(int) $stm['n'].' ajustés manuellement, volontairement conservés.)');
w();
table(rows("SELECT p.ref, LEFT(p.label,60) label, p.desiredstock desire, p.seuil_stock_alerte seuil, p.stock,
	(SELECT MAX(c.date_commande) FROM {$P}commande c JOIN {$P}commandedet cd ON cd.fk_commande=c.rowid WHERE cd.fk_product=p.rowid AND c.fk_statut IN (1,2,3)) derniere_vente
	FROM {$P}product p LEFT JOIN tmp_audit_sales s ON s.fk_product=p.rowid LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE $where_stale ORDER BY p.desiredstock DESC"),
	['ref'=>'Réf', 'label'=>'Produit', 'desire'=>'Désiré', 'seuil'=>'Seuil', 'stock'=>'Stock', 'derniere_vente'=>'Dernière vente'], $TOP);
w();

// ---------------------------------------------------------------- 5. Valeurs étranges + structure
w('## 5. Cmd Max stockée ≥ ×'.$RATIO.' le vendu sur la fenêtre, et défauts de structure');
w();
$where_big = "p.fk_product_type=0 AND p.desiredstock >= $RATIO*s.qty AND p.desiredstock>=10";
$bg = one("SELECT COUNT(*) n, SUM(COALESCE(p2.replenish_manual,0)=1) manual FROM tmp_audit_sales s JOIN {$P}product p ON p.rowid=s.fk_product LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid WHERE $where_big");
w('**'.(int) $bg['n'].' produit(s)** vendus dont la Cmd Max stockée vaut au moins '.$RATIO.' × le vendu (et ≥ 10), dont '.(int) $bg['manual'].' ajustés manuellement (saisonniers assumés).');
w();
table(rows("SELECT p.ref, LEFT(p.label,50) label, s.qty vendu, p.desiredstock desire, p.seuil_stock_alerte seuil, p.stock, ROUND(p.desiredstock/s.qty,1) ratio, IF(COALESCE(p2.replenish_manual,0)=1,'oui','') manuel
	FROM tmp_audit_sales s JOIN {$P}product p ON p.rowid=s.fk_product LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE $where_big ORDER BY p.desiredstock/s.qty DESC"),
	['ref'=>'Réf', 'label'=>'Produit', 'vendu'=>'Vendu', 'desire'=>'Cmd Max', 'seuil'=>'Seuil', 'stock'=>'Stock', 'ratio'=>'Ratio', 'manuel'=>'Manuel'], $TOP);
w();
$mw = one("SELECT COUNT(*) n FROM (SELECT fk_product FROM {$P}product_stock GROUP BY fk_product HAVING COUNT(*)>1) t");
$dp = one("SELECT COUNT(*) n FROM (SELECT pfp.fk_product FROM {$P}product_fournisseur_price pfp JOIN {$P}product_extrafields p2 ON p2.fk_object=pfp.fk_product AND p2.fk_soc_fournisseur=pfp.fk_soc GROUP BY pfp.fk_product HAVING COUNT(*)>1) t");
w('Structure : '.(int) $mw['n'].' produit(s) stockés sur plusieurs entrepôts · '.(int) $dp['n'].' produit(s) avec plusieurs prix chez leur fournisseur désigné (tous deux neutralisés par l\'agrégation du calcul, RM3491).');
w();

// ---------------------------------------------------------------- 6. Simulation du recalcul
$sim = [];
if (empty($opt['no-recalc'])) {
	w('## 6. Simulation du recalcul (code courant, fenêtre '.$DAYS.' j, plancher '.$MIN.' j)');
	w();
	$sql = MMI_Product_Replenish::buildSeuilsQuery(['analyse_days_nb'=>$DAYS, 'min_stock_days'=>$MIN]);
	$t0 = microtime(true);
	$db->query("DROP TEMPORARY TABLE IF EXISTS tmp_audit_eoq");
	if (!$db->query("CREATE TEMPORARY TABLE tmp_audit_eoq AS ".$sql)) {
		w('> ERREUR SQL recalcul : '.$db->lasterror());
	}
	else {
		$db->query("ALTER TABLE tmp_audit_eoq ADD PRIMARY KEY (product_id)");
		$sim = one("SELECT COUNT(*) n, SUM(order_qty IS NULL OR reorder_point IS NULL) nulls, SUM(stock_days < $MIN) floored, SUM(stock_days = 0) never_in_stock,
			SUM(COALESCE(replenish_manual,0)=1) manual, SUM(order_qty >= $RATIO*cmd_qty AND order_qty>=10) big_vs_sales FROM tmp_audit_eoq");
		w(sprintf('Calcul en %.1f s : **%d produits** vendus sur leur fenêtre · quantités NULL : %d · plancher appliqué (moins de %d j de stock) : %d · jamais en stock : %d · ajustés manuellement (non écrits) : %d · Cmd Max ≥ ×%s le vendu : %d',
			microtime(true) - $t0, $sim['n'], $sim['nulls'], $MIN, $sim['floored'], $sim['never_in_stock'], $sim['manual'], $RATIO, $sim['big_vs_sales']));
		w();
		$dist = rows("SELECT CASE WHEN order_qty/cmd_qty<0.5 THEN '< 0,5' WHEN order_qty/cmd_qty<1 THEN '0,5 – 1' WHEN order_qty/cmd_qty<2 THEN '1 – 2' WHEN order_qty/cmd_qty<4 THEN '2 – 4' ELSE '≥ 4' END tranche, COUNT(*) n, ROUND(AVG(order_qty),1) cmd_max_moy FROM tmp_audit_eoq GROUP BY tranche ORDER BY MIN(order_qty/cmd_qty)");
		w('Ratio Cmd Max recalculée / vendu sur la fenêtre :');
		w();
		table($dist, ['tranche'=>'Tranche', 'n'=>'Produits', 'cmd_max_moy'=>'Cmd Max moyenne'], 10);
		w();
		$diff = one("SELECT COUNT(*) n, SUM(ABS(p.desiredstock-CEIL(e.order_qty))>0) changed, SUM(p.desiredstock >= $RATIO*CEIL(e.order_qty) AND p.desiredstock>=10) stored_big, SUM(CEIL(e.order_qty) >= $RATIO*p.desiredstock AND CEIL(e.order_qty)>=10) calc_big
			FROM tmp_audit_eoq e JOIN {$P}product p ON p.rowid=e.product_id WHERE COALESCE(e.replenish_manual,0)=0");
		w('Écart recalcul vs seuils stockés (hors manuels) : **'.(int) $diff['changed'].' / '.(int) $diff['n'].'** Cmd Max changeraient · stocké ≥ ×'.$RATIO.' recalcul : '.(int) $diff['stored_big'].' · recalcul ≥ ×'.$RATIO.' stocké : '.(int) $diff['calc_big']);
		w();
		w('### Grosses différences entre la Cmd Max stockée et le recalcul (hors manuels)');
		w();
		table(rows("SELECT p.ref, LEFT(p.label,50) label, e.cmd_qty vendu, e.stock_days jours_stock, e.calc_days jours_calc, p.desiredstock stocke, CEIL(e.order_qty) recalcul, p.stock,
			ROUND(GREATEST(p.desiredstock, 1)/GREATEST(CEIL(e.order_qty),1), 1) ratio
			FROM tmp_audit_eoq e JOIN {$P}product p ON p.rowid=e.product_id
			WHERE COALESCE(e.replenish_manual,0)=0 AND ((p.desiredstock >= $RATIO*CEIL(e.order_qty) AND p.desiredstock>=10) OR (CEIL(e.order_qty) >= $RATIO*p.desiredstock AND CEIL(e.order_qty)>=10))
			ORDER BY ABS(p.desiredstock-CEIL(e.order_qty)) DESC"),
			['ref'=>'Réf', 'label'=>'Produit', 'vendu'=>'Vendu', 'jours_stock'=>'J. stock', 'jours_calc'=>'J. calc', 'stocke'=>'Cmd Max stockée', 'recalcul'=>'Recalcul', 'stock'=>'Stock', 'ratio'=>'Stocké/recalc'], $TOP);
		w();
		$reset = one("SELECT COUNT(*) n FROM {$P}product p INNER JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid LEFT JOIN tmp_audit_eoq e ON e.product_id=p.rowid
			WHERE p.fk_product_type=0 AND e.product_id IS NULL AND COALESCE(p2.replenish_manual,0)=0 AND (p.seuil_stock_alerte>0 OR p.desiredstock>0)");
		w('Produits que la remise à zéro toucherait (sans vente sur la fenêtre, seuils > 0, non manuels) : **'.(int) $reset['n'].'**'.($stale ? ' — ⚠ sautée tant que les stats sont périmées' : ''));
		w();
	}
}

// ---------------------------------------------------------------- 7. Résumé
w('## 7. Résumé');
w();
w('| Indicateur | Valeur | Attendu après correction |');
w('|---|---|---|');
w('| Âge des stats (jours) | '.($age === null ? '∞' : $age).' | ≤ 7 (cron hebdo actif) |');
w('| Cron réappro actif | '.($cron && $cron[0]['status'] ? 'oui' : 'NON').' | oui |');
w('| Vendus sur la fenêtre à seuils 0 (non manuels) | '.(int) $z['n'].' | 0 |');
w('| Sans vente à stock désiré > 0 (non manuels) | '.(int) $st['n'].' | 0 (remise à zéro) |');
w('| … dont affichés en réappro | '.(int) $st['visibles'].' | 0 |');
w('| Cmd Max stockée ≥ ×'.$RATIO.' vendu (non manuels) | '.((int) $bg['n'] - (int) $bg['manual']).' | ≈ 0 (saisonniers → manuels) |');
if ($sim) {
	w('| Recalcul : quantités NULL | '.(int) $sim['nulls'].' | 0 |');
	w('| Recalcul : Cmd Max ≥ ×'.$RATIO.' vendu | '.(int) $sim['big_vs_sales'].' | ≈ 0 |');
}
w();

if (!empty($opt['out'])) {
	file_put_contents($opt['out'], $out);
	fwrite(STDERR, "Rapport écrit : ".$opt['out']."\n");
}
exit(0);

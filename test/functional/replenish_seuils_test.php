<?php
/**
 * Calcul des seuils de réappro (buildSeuilsQuery / seuils_refresh) — RM3491.
 *
 * Teste sur une base Dolibarr réelle, dans une transaction ANNULÉE à la fin : aucune
 * donnée ne reste. Fixtures : un fournisseur et 7 produits de test dont les stats de
 * ventes/stock sont insérées directement (fenêtre = aujourd'hui et les jours précédents).
 *
 *   P1 : stocké sur 2 entrepôts, 2 prix chez son fournisseur, 60 j en stock, 20 vendus
 *        → ventes NON doublées, stock = somme des entrepôts, packaging = max
 *   P2 : 6 vendus un seul jour, 1 seul jour en stock → demande plancher 6/30, pas 6/j
 *   P3 : 3 vendus, jamais en stock → calcul sur la fenêtre entière, quantité non NULL
 *   P4 : aucune vente, seuils 50/20 → remis à zéro par seuils_refresh
 *   P5 : aucune vente, seuils 40/10, ajusté manuellement → intact
 *   P6 : vendu, ajusté manuellement, seuils 99/9 → intact (compté skipped_manual)
 *   P7 : fenêtre propre 30 j, ventes à J-5 (4) et J-50 (100) → seules les 4 comptent
 *
 * Usage : php7.4 test/functional/replenish_seuils_test.php
 * Code de sortie 0 si tout passe. NE PAS lancer en prod.
 */

$script_file = basename(__FILE__);
chdir(__DIR__.'/../..');
require_once __DIR__.'/../../master_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
dol_include_once('custom/mmiproduct/class/mmi_product_replenish.class.php');

global $db, $conf, $user;

$conf->global->MAIN_DISABLE_ALL_MAILS = 1;
$conf->global->MMIPRESTASYNC_SYNC = 0;
$conf->global->MMIPRODUCT_REPLENISH_MIN_STOCK_DAYS = 30;
$conf->global->MMIPRODUCT_REPLENISH_RESET_NO_SALES = 1;

if (empty($user) || empty($user->id)) {
	$user = new User($db);
	$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
	$user->fetch($db->fetch_object($resql)->rowid);
	$user->getrights();
}

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
function q($sql)
{
	global $db;
	$r = $db->query($sql);
	if (!$r)
		die("SQL KO : ".$db->lasterror()."\n".$sql."\n");
	return $r;
}
function day($minus)
{
	return date('Y-m-d', strtotime('-'.$minus.' days'));
}
$P = MAIN_DB_PREFIX;

$db->begin();

// ---- Fournisseur de test
$suffix = dol_print_date(dol_now(), '%H%M%S');
$soc = new Societe($db);
$soc->name = 'TEST-RM3491-'.$suffix;
$soc->fournisseur = 1;
$soc->client = 0;
$soc->status = 1;
$soc->country_id = 1;
$soc->get_codefournisseur($soc, 1);
$soc->code_compta_fournisseur = '401RM3491';
if ($soc->create($user) <= 0)
	die("Création fournisseur KO : ".$soc->error.' '.json_encode($soc->errors)."\n");
$SOC = (int) $soc->id;

// ---- Entrepôts existants (2 au moins requis), sinon on en crée
$ents = [];
$r = q("SELECT rowid FROM {$P}entrepot WHERE statut=1 ORDER BY rowid LIMIT 2");
while ($o = $db->fetch_object($r))
	$ents[] = (int) $o->rowid;
while (count($ents) < 2) {
	q("INSERT INTO {$P}entrepot (ref, entity, datec, statut, fk_user_author) VALUES ('TEST-RM3491-E".count($ents)."', 1, NOW(), 1, ".(int) $user->id.")");
	$ents[] = (int) $db->last_insert_id($P.'entrepot');
}

// ---- Produits de test
function mkproduct($ref, $extra = [], $seuil = 0, $desired = 0)
{
	global $db, $user, $P, $SOC;
	$p = new Product($db);
	global $suffix;
	$p->ref = $ref.'-'.$suffix;
	$p->label = 'Produit de test '.$ref;
	$p->type = 0;
	$p->status = 1;
	$p->status_buy = 1;
	if ($p->create($user) <= 0)
		die("Création produit $ref KO : ".$p->error."\n");
	q("UPDATE {$P}product SET seuil_stock_alerte=".(int) $seuil.", desiredstock=".(int) $desired." WHERE rowid=".(int) $p->id);
	$cols = ['fk_object'=>(int) $p->id, 'fk_soc_fournisseur'=>$SOC] + $extra;
	$sets = [];
	foreach ($cols as $k=>$v)
		$sets[] = "`$k`=".($v === null ? 'NULL' : (int) $v);
	q("INSERT INTO {$P}product_extrafields SET ".implode(', ', $sets)." ON DUPLICATE KEY UPDATE ".implode(', ', $sets));
	return (int) $p->id;
}
function sale($pid, $minus, $qty, $nb = 1)
{
	global $P;
	q("REPLACE INTO {$P}product_stats (fk_product, `date`, order_nb, order_qty) VALUES (".(int) $pid.", '".day($minus)."', ".(int) $nb.", ".(float) $qty.")");
}
function instock($pid, $from, $to, $qty = 10)
{
	global $P;
	$vals = [];
	for ($i = $from; $i <= $to; $i++)
		$vals[] = "(".(int) $pid.", '".day($i)."', ".(float) $qty.")";
	q("REPLACE INTO {$P}product_stock_stats (fk_product, `date`, stock_phy) VALUES ".implode(',', $vals));
}

$P1 = mkproduct('TEST-RM3491-P1');
q("INSERT INTO {$P}product_stock (fk_product, fk_entrepot, reel) VALUES ($P1, {$ents[0]}, 5), ($P1, {$ents[1]}, 3)");
q("INSERT INTO {$P}product_fournisseur_price (entity, datec, fk_product, fk_soc, ref_fourn, quantity, price, unitprice, tva_tx, packaging, fk_user) VALUES
	(1, NOW(), $P1, $SOC, 'TEST-RM3491-P1-A', 1, 10, 10, 5.5, 6, ".(int) $user->id."),
	(1, NOW(), $P1, $SOC, 'TEST-RM3491-P1-B', 1, 9, 9, 5.5, 12, ".(int) $user->id.")");
for ($i = 1; $i <= 10; $i++)
	sale($P1, $i, 2);
instock($P1, 0, 59);

$P2 = mkproduct('TEST-RM3491-P2');
sale($P2, 3, 6);
instock($P2, 3, 3);

$P3 = mkproduct('TEST-RM3491-P3');
sale($P3, 10, 3);

$P4 = mkproduct('TEST-RM3491-P4', [], 20, 50);

$P5 = mkproduct('TEST-RM3491-P5', ['replenish_manual'=>1], 10, 40);

$P6 = mkproduct('TEST-RM3491-P6', ['replenish_manual'=>1], 9, 99);
sale($P6, 2, 5);
instock($P6, 0, 59);

$P7 = mkproduct('TEST-RM3491-P7', ['replenish_analysis_days'=>30]);
sale($P7, 5, 4);
sale($P7, 50, 100);
instock($P7, 0, 59);

$ids = [$P1, $P2, $P3, $P4, $P5, $P6, $P7];

// ---- Calcul
$sql = MMI_Product_Replenish::buildSeuilsQuery(['analyse_days_nb'=>90, 'product_ids'=>$ids]);
$rows = [];
$r = q($sql);
while ($o = $db->fetch_object($r))
	$rows[(int) $o->product_id] = $o;

check('5 produits vendus calculés (P4 et P5 sans vente exclus)', count($rows) === 5 && !isset($rows[$P4]) && !isset($rows[$P5]), count($rows).' lignes');

$o = $rows[$P1];
check('P1 : ventes non doublées par les 2 entrepôts / 2 prix (20)', (float) $o->cmd_qty == 20, $o->cmd_qty);
check('P1 : stock = somme des entrepôts (8)', (float) $o->stock_current == 8, $o->stock_current);
check('P1 : conditionnement = max des prix (12)', (float) $o->packaging == 12, $o->packaging);
check('P1 : 60 jours en stock, demande 20/60', (int) $o->stock_days === 60 && abs((float) $o->d - 20 / 60) < 0.001, $o->stock_days.' j, d='.$o->d);
check('P1 : fenêtre 90 j par défaut', (int) $o->window_days === 90, $o->window_days);

$o = $rows[$P2];
check('P2 : 1 jour en stock → dénominateur plancher 30', (int) $o->stock_days === 1 && (int) $o->calc_days === 30, $o->stock_days.' / '.$o->calc_days);
check('P2 : demande 6/30 = 0,2 et non 6/j', abs((float) $o->d - 0.2) < 0.001, $o->d);
check('P2 : Cmd Max raisonnable (≤ 12)', $o->order_qty !== null && (float) $o->order_qty <= 12, $o->order_qty);

$o = $rows[$P3];
check('P3 : jamais en stock → fenêtre entière (90) et quantité non NULL', (int) $o->stock_days === 0 && (int) $o->calc_days === 90 && $o->order_qty !== null && $o->reorder_point !== null, $o->calc_days.' / '.var_export($o->order_qty, true));
check('P3 : demande 3/90', abs((float) $o->d - 3 / 90) < 0.001, $o->d);

$o = $rows[$P7];
check('P7 : fenêtre propre 30 j appliquée (ventes à J-50 ignorées)', (int) $o->window_days === 30 && (float) $o->cmd_qty == 4, $o->window_days.' j, '.$o->cmd_qty.' vendus');

// ---- Écriture des seuils
$n_upd = MMI_Product_Replenish::seuils_refresh(['analyse_days_nb'=>90, 'product_ids'=>$ids]);
$rf = MMI_Product_Replenish::$last_refresh;
check('seuils_refresh : 4 mis à jour (P1 P2 P3 P7), 1 exclu manuel (P6), 0 NULL', $n_upd === 4 && $rf['skipped_manual'] === 1 && $rf['skipped_null'] === 0, json_encode($rf));
check('seuils_refresh : stats fraîches (fixtures du jour) → remise à zéro active', empty($rf['stale']) && $rf['reset'] === 1, json_encode($rf));

function seuils($pid)
{
	global $db, $P;
	$r = q("SELECT p.seuil_stock_alerte s, p.desiredstock d, p2.replenish_reorder_point rp FROM {$P}product p LEFT JOIN {$P}product_extrafields p2 ON p2.fk_object=p.rowid WHERE p.rowid=".(int) $pid);
	return $db->fetch_object($r);
}
$s = seuils($P4);
check('P4 : sans vente → seuil et stock désiré remis à 0, extrafield vidé', (int) $s->s === 0 && (int) $s->d === 0 && $s->rp === null, json_encode($s));
$s = seuils($P5);
check('P5 : sans vente mais manuel → 40/10 intacts', (int) $s->s === 10 && (int) $s->d === 40, json_encode($s));
$s = seuils($P6);
check('P6 : vendu mais manuel → 99/9 intacts', (int) $s->s === 9 && (int) $s->d === 99, json_encode($s));
$s = seuils($P2);
check('P2 : seuils écrits (> 0, ≤ 12)', (int) $s->d > 0 && (int) $s->d <= 12 && $s->rp !== null, json_encode($s));
$s = seuils($P3);
check('P3 : seuils écrits (pas de 0 par NULL)', (int) $s->d > 0 && (int) $s->s >= 0 && $s->rp !== null, json_encode($s));

// ---- Garde-fou stats périmées : on vide la fenêtre récente des stats → la remise à zéro doit être sautée
q("UPDATE {$P}product SET seuil_stock_alerte=20, desiredstock=50 WHERE rowid=$P4");
q("DELETE FROM {$P}product_stats WHERE `date` >= '".day(30)."'"); // annulé par le rollback final
check('garde-fou : stats déclarées périmées', MMI_Product_Replenish::statsAreStale() === true, MMI_Product_Replenish::statsLastDate());
MMI_Product_Replenish::seuils_refresh(['analyse_days_nb'=>90, 'product_ids'=>$ids]);
$rf = MMI_Product_Replenish::$last_refresh;
$s = seuils($P4);
check('garde-fou : remise à zéro SAUTÉE quand les stats sont périmées (P4 garde 50/20)', !empty($rf['stale']) && $rf['reset'] === 0 && (int) $s->d === 50, json_encode($rf).' '.json_encode($s));

$db->rollback();

echo "\n$n test(s), $fail échec(s)\n";
exit($fail ? 1 : 0);

<?php
/**
 * Bascule des produits d'un fournisseur vers un autre — RM3235.
 *
 * Teste mmi_supplier_switch (aperçu, bascule, collision, désactivation) sur une base
 * Dolibarr réelle, dans une transaction ANNULÉE à la fin : aucune donnée ne reste.
 * Mails et synchro Presta coupés en mémoire.
 *
 * Usage : php7.4 test/functional/supplier_switch_test.php
 * Code de sortie 0 si tout passe. NE PAS lancer en prod.
 */

$script_file = basename(__FILE__);
chdir(__DIR__.'/../..');
require_once __DIR__.'/../../master_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
dol_include_once('custom/mmiproduct/class/mmi_supplier_switch.class.php');

global $db, $conf, $user;

$conf->global->MAIN_DISABLE_ALL_MAILS = 1;
$conf->global->MMIPRESTASYNC_SYNC = 0;
$conf->global->MMI_PRODUCT_PRODUCT_DEFAULT_SUPPLIER_UPDATE = 1;

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
function mk_supplier($suffix)
{
	global $db, $user;
	$s = new Societe($db);
	$s->name = 'TEST-RM3235-'.$suffix.'-'.dol_print_date(dol_now(), '%H%M%S');
	$s->fournisseur = 1;
	$s->client = 0;
	$s->status = 1;
	$s->country_id = 1;
	$s->get_codefournisseur($s, 1);
	$s->code_compta_fournisseur = '401RM3235'.substr($suffix, 0, 4);
	$id = $s->create($user);
	if ($id <= 0) {
		echo "création tiers KO : ".$s->error.' '.json_encode($s->errors)."\n";
		exit(2);
	}
	return $s;
}
function mk_product($suffix)
{
	global $db, $user;
	$p = new Product($db);
	$p->ref = 'TEST-RM3235-'.$suffix.'-'.dol_print_date(dol_now(), '%H%M%S');
	$p->label = 'Test RM3235 bascule';
	$p->type = Product::TYPE_PRODUCT;
	$p->status = 1;
	$p->status_buy = 1;
	$id = $p->create($user);
	if ($id <= 0) {
		echo "création produit KO : ".$p->error."\n";
		exit(2);
	}
	return $p;
}
function add_price($product_id, $soc, $ref, $price)
{
	global $db, $user;
	$pf = new ProductFournisseur($db);
	$pf->id = $product_id;
	$pf->fetch($product_id);
	$r = $pf->update_buyprice(1, $price, $user, 'HT', $soc, 0, $ref, 20);
	return $r > 0 ? (int) $pf->product_fourn_price_id : $r;
}
function state($product_id)
{
	global $db;
	$p = new Product($db);
	$p->fetch($product_id);
	return [
		'fk_soc'=>(int) ($p->array_options['options_fk_soc_fournisseur'] ?? 0),
		'ref'=>(string) ($p->array_options['options_supplier_ref'] ?? ''),
	];
}
function line($pfp_id)
{
	global $db;
	$resql = $db->query('SELECT fk_soc, ref_fourn FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE rowid='.((int) $pfp_id));
	$o = $db->fetch_object($resql);
	return $o ? ['fk_soc'=>(int) $o->fk_soc, 'ref'=>$o->ref_fourn] : null;
}
function soc_status($socid)
{
	global $db;
	$resql = $db->query('SELECT status FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.((int) $socid));
	$o = $db->fetch_object($resql);
	return $o ? (int) $o->status : -1;
}

$db->begin();

// --- 1. Aperçu
$from = mk_supplier('FROM');
$to = mk_supplier('TO');
$p1 = mk_product('P1');
$p2 = mk_product('P2');
$l1 = add_price($p1->id, $from, 'REF-1', 10);
$l2 = add_price($p2->id, $from, 'REF-2', 20);
check('préparation : 2 prix chez la source', $l1 > 0 && $l2 > 0);

$prev = mmi_supplier_switch::preview($db, $from->id, $to->id);
check('aperçu : nombre de prix', $prev['lines'] === 2, json_encode($prev));
check('aperçu : nombre de produits', $prev['products'] === 2, json_encode($prev));
check('aperçu : produits dont c\'est le fournisseur par défaut', $prev['defaults'] === 2, json_encode($prev));
check('aperçu : aucune collision', $prev['collisions'] === array());

$prev0 = mmi_supplier_switch::preview($db, $from->id, 0);
check('aperçu sans cible : compteurs quand même calculés', $prev0['lines'] === 2 && $prev0['error'] === '');

// --- 2. Bascule sans désactivation
$res = mmi_supplier_switch::run($db, $user, $from->id, $to->id, false);
check('bascule réussie', !empty($res['ok']), $res['error']);
check('compteurs du rapport', $res['moved'] === 2 && $res['products'] === 2, json_encode($res));
check('prix 1 transféré', line($l1)['fk_soc'] === (int) $to->id);
check('prix 2 transféré', line($l2)['fk_soc'] === (int) $to->id);
check('réfs fournisseur conservées', line($l1)['ref'] === 'REF-1' && line($l2)['ref'] === 'REF-2');
check('fournisseur par défaut du produit 1 basculé', state($p1->id)['fk_soc'] === (int) $to->id, json_encode(state($p1->id)));
check('fournisseur par défaut du produit 2 basculé', state($p2->id)['fk_soc'] === (int) $to->id);
check('réf du produit conservée', state($p1->id)['ref'] === 'REF-1');
check('le fournisseur source n\'est PAS supprimé', soc_status($from->id) >= 0);
check('le fournisseur source reste actif (option décochée)', soc_status($from->id) === 1);
check('la source n\'a plus aucun produit', mmi_supplier_switch::is_empty($db, $from->id));
check('nouvelle bascule sans prix → refus explicite', mmi_supplier_switch::run($db, $user, $from->id, $to->id)['error'] !== '');

// --- 3. Collision de réf fournisseur
$from2 = mk_supplier('FROM2');
$to2 = mk_supplier('TO2');
$p3 = mk_product('P3');
$l3 = add_price($p3->id, $from2, 'REF-DUP', 10);
$l4 = add_price($p3->id, $to2, 'REF-DUP', 12);
$prev = mmi_supplier_switch::preview($db, $from2->id, $to2->id);
check('aperçu : collision détectée', $prev['collisions'] === array('REF-DUP'), json_encode($prev['collisions']));
$res = mmi_supplier_switch::run($db, $user, $from2->id, $to2->id);
check('collision → bascule refusée', empty($res['ok']));
check('collision → message nommant la réf', strpos($res['error'], 'REF-DUP') !== false, $res['error']);
check('collision → rien n\'a bougé', line($l3)['fk_soc'] === (int) $from2->id);

// --- 4. Désactivation du fournisseur source
$from3 = mk_supplier('FROM3');
$to3 = mk_supplier('TO3');
$p4 = mk_product('P4');
$l5 = add_price($p4->id, $from3, 'REF-4', 10);
$res = mmi_supplier_switch::run($db, $user, $from3->id, $to3->id, true);
check('bascule avec désactivation réussie', !empty($res['ok']), $res['error']);
check('source désactivée', soc_status($from3->id) === 0 && !empty($res['deactivated']));
check('cible toujours active', soc_status($to3->id) === 1);

// --- 5. Cibles invalides
check('cible absente → refus', mmi_supplier_switch::run($db, $user, $from->id, 0)['error'] !== '');
check('cible = source → refus', mmi_supplier_switch::run($db, $user, $to->id, $to->id)['error'] !== '');

$db->rollback();

$nb = (int) $db->fetch_object($db->query('SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'societe WHERE nom LIKE "TEST-RM3235-%"'))->nb;
$nb += (int) $db->fetch_object($db->query('SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'product WHERE ref LIKE "TEST-RM3235-%"'))->nb;
check('rollback : aucune donnée de test restante', $nb === 0, "restant=$nb");

echo "\n".($n - $fail)."/$n OK\n";
exit($fail ? 1 : 0);

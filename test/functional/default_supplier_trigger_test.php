<?php
/**
 * Test d'intégration du trigger « fournisseur par défaut » (RM3233) sur une base Dolibarr réelle.
 * Tout se passe dans une transaction ANNULÉE à la fin : aucune donnée ne reste.
 * Mails et synchro Presta coupés en mémoire pour la durée du script.
 *
 * Usage : php7.4 test/functional/default_supplier_trigger_test.php (depuis n'importe où)
 * Code de sortie 0 si tout passe. NE PAS lancer en prod.
 */

$script_file = basename(__FILE__);
// Le chargeur mmicommon cherche master.inc.php relativement au cwd (3 niveaux max) : se placer à la racine du module
chdir(__DIR__.'/../..');
require_once __DIR__.'/../../master_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';

global $db, $conf, $user;

// Garde-fous : jamais d'email, jamais de notification de synchro, trigger actif
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
function state($id)
{
	global $db;
	$p = new Product($db);
	$p->fetch($id);
	return [
		'fk_soc'=>(int) ($p->array_options['options_fk_soc_fournisseur'] ?? 0),
		'ref'=>(string) ($p->array_options['options_supplier_ref'] ?? ''),
	];
}
function add_price($product_id, $soc_id, $ref, $price)
{
	global $db, $user;
	$fourn = new Fournisseur($db);
	$fourn->fetch($soc_id);
	$pf = new ProductFournisseur($db);
	$pf->id = $product_id;
	$pf->fetch($product_id);
	$r = $pf->update_buyprice(1, $price, $user, 'HT', $fourn, 0, $ref, 20);
	return $r > 0 ? (int) $pf->product_fourn_price_id : $r;
}
function update_price($product_id, $pfp_id, $soc_id, $ref, $price)
{
	global $db, $user;
	$fourn = new Fournisseur($db);
	$fourn->fetch($soc_id);
	$pf = new ProductFournisseur($db);
	$pf->fetch($product_id);
	$pf->product_fourn_price_id = $pfp_id;
	return $pf->update_buyprice(1, $price, $user, 'HT', $fourn, 0, $ref, 20);
}
/** Déplacement d'une ligne vers un autre fournisseur, comme le feront la bascule (RM3235) et la fusion (RM3234) */
function move_price($product_id, $pfp_id, $soc_id)
{
	global $db, $user;
	$db->query('UPDATE '.MAIN_DB_PREFIX.'product_fournisseur_price SET fk_soc='.((int) $soc_id).' WHERE rowid='.((int) $pfp_id));
	$pf = new ProductFournisseur($db);
	$pf->fetch($product_id);
	$pf->product_fourn_price_id = $pfp_id;
	$pf->product_id = $product_id;
	$pf->fourn_id = $soc_id;
	return $pf->call_trigger('SUPPLIER_PRODUCT_BUYPRICE_MODIFY', $user);
}
function events($product_id)
{
	global $db;
	$resql = $db->query('SELECT COUNT(*) nb FROM '.MAIN_DB_PREFIX.'actioncomm WHERE code="AC_MMI_DEFAULT_SUPPLIER" AND elementtype="product" AND fk_element='.((int) $product_id));
	return (int) $db->fetch_object($resql)->nb;
}

// 3 fournisseurs actifs existants
$socs = [];
$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE fournisseur=1 AND status=1 ORDER BY rowid LIMIT 3');
while ($o = $db->fetch_object($resql))
	$socs[] = (int) $o->rowid;
if (count($socs) < 3) {
	echo "Il faut 3 fournisseurs actifs en base.\n";
	exit(2);
}
list($S1, $S2, $S3) = $socs;

$db->begin();

$product = new Product($db);
$product->ref = 'TEST-RM3233-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
$product->label = 'Test RM3233 fournisseur par défaut';
$product->type = Product::TYPE_PRODUCT;
$product->status = 1;
$product->status_buy = 1;
$pid = $product->create($user);
check('création du produit de test', $pid > 0, $product->error);

// 1. Premier prix : devient le défaut
$id1 = add_price($pid, $S1, 'R1', 10);
$s = state($pid);
check('1er prix → défaut initialisé', $id1 > 0 && $s['fk_soc'] === $S1 && $s['ref'] === 'R1', json_encode($s));

// 2. Prix d'un 2e fournisseur : le défaut ne bouge pas
sleep(1);
$id2 = add_price($pid, $S2, 'R2', 9);
$s = state($pid);
check('2e fournisseur ajouté → défaut inchangé', $id2 > 0 && $s['fk_soc'] === $S1 && $s['ref'] === 'R1', json_encode($s));

// 3. Modification du prix du 2e fournisseur : le défaut ne bouge pas
update_price($pid, $id2, $S2, 'R2', 8);
$s = state($pid);
check('prix secondaire modifié → défaut inchangé', $s['fk_soc'] === $S1 && $s['ref'] === 'R1', json_encode($s));

// 4. Modification de la réf du défaut : supplier_ref suit
update_price($pid, $id1, $S1, 'R1b', 10);
$s = state($pid);
check('réf du défaut modifiée → supplier_ref à jour', $s['fk_soc'] === $S1 && $s['ref'] === 'R1b', json_encode($s));

// 5. Bascule de la ligne du défaut vers S3 : S3 devient le défaut, réf conservée, pas de notification
$ret = move_price($pid, $id1, $S3);
$s = state($pid);
check('ligne du défaut déplacée → la cible devient le défaut', $ret >= 0 && $s['fk_soc'] === $S3 && $s['ref'] === 'R1b', json_encode($s));
check('bascule → aucune notification', events($pid) === 0);

// 6. Suppression du dernier prix du défaut : le plus récent restant (S2) le remplace + notification
$pf = new ProductFournisseur($db);
$pf->fetch($pid);
$pf->remove_product_fournisseur_price($id1);
$s = state($pid);
check('dernier prix du défaut supprimé → le plus récent le remplace', $s['fk_soc'] === $S2 && $s['ref'] === 'R2', json_encode($s));
check('remplacement → événement agenda créé', events($pid) === 1);

// 7. Suppression du dernier prix : défaut vidé + notification
$pf = new ProductFournisseur($db);
$pf->fetch($pid);
$pf->remove_product_fournisseur_price($id2);
$s = state($pid);
check('plus aucun prix → défaut vidé', $s['fk_soc'] === 0 && $s['ref'] === '', json_encode($s));
check('défaut vidé → 2e événement agenda', events($pid) === 2);

// 8. Trigger désactivé : aucun effet
$conf->global->MMI_PRODUCT_PRODUCT_DEFAULT_SUPPLIER_UPDATE = 0;
$id3 = add_price($pid, $S1, 'R3', 5);
$s = state($pid);
check('constante à 0 → trigger inactif', $id3 > 0 && $s['fk_soc'] === 0, json_encode($s));

$db->rollback();

$resql = $db->query('SELECT COUNT(*) nb FROM '.MAIN_DB_PREFIX.'product WHERE ref LIKE "TEST-RM3233-%"');
check('rollback : aucune donnée de test restante', (int) $db->fetch_object($resql)->nb === 0);

echo "\n".($n - $fail)."/$n OK\n";
exit($fail ? 1 : 0);

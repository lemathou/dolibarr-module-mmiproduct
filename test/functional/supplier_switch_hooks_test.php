<?php
/**
 * Hooks d'interface de la bascule fournisseur — RM3235.
 * Rend le bouton et le formulaire de confirmation hors navigateur, pour vérifier qu'ils
 * se construisent sans erreur et ne s'affichent que là où il faut.
 * Aucune écriture en base.
 *
 * Usage : php7.4 test/functional/supplier_switch_hooks_test.php
 */

$script_file = basename(__FILE__);
chdir(__DIR__.'/../..');
require_once __DIR__.'/../../master_load.inc.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
dol_include_once('custom/mmiproduct/class/actions_mmiproduct.class.php');

global $db, $conf, $user, $langs;

if (empty($user) || empty($user->id)) {
	$user = new User($db);
	$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
	$user->fetch($db->fetch_object($resql)->rowid);
	$user->getrights();
}
// En CLI, MAIN_LANG_DEFAULT vaut « auto » et retombe sur l'anglais. On reconstruit un objet
// de traduction en français (la langue de l'instance) : changer la langue d'un Translate déjà
// chargé ne recharge pas les fichiers.
$langs = new Translate('', $conf);
$langs->setDefaultLang('fr_FR');
$langs->loadLangs(array('mmiproduct@mmiproduct'));
$_SERVER['PHP_SELF'] = '/societe/card.php';

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

// Un fournisseur réel et un tiers non fournisseur
$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE fournisseur=1 AND status=1 LIMIT 1');
$supplier = new Societe($db);
$supplier->fetch($db->fetch_object($resql)->rowid);
$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE fournisseur=0 AND client=1 AND status=1 LIMIT 1');
$customer = new Societe($db);
$customer->fetch($db->fetch_object($resql)->rowid);

$actions = new ActionsMMIProduct($db);

// 1. Bouton sur la fiche d'un fournisseur
$action = '';
$parameters = array('currentcontext'=>'thirdpartycard', 'context'=>'thirdpartycard');
ob_start();
$r = $actions->addMoreActionsButtons($parameters, $supplier, $action, $GLOBALS['hookmanager']);
$html = ob_get_clean();
check('bouton : hook sans erreur', $r === 0);
check('bouton : présent sur un fournisseur', strpos($html, 'mmi_switch_supplier') !== false, substr(strip_tags($html), 0, 120));
check('bouton : libellé traduit', strpos($html, 'Basculer les produits') !== false, substr(strip_tags($html), 0, 120));

// 2. Pas de bouton sur un tiers qui n'est pas fournisseur
$action = '';
ob_start();
$actions->addMoreActionsButtons($parameters, $customer, $action, $GLOBALS['hookmanager']);
$html = ob_get_clean();
check('bouton : absent sur un tiers non fournisseur', strpos($html, 'mmi_switch_supplier') === false);

// 3. Pas de bouton hors du contexte fiche tiers
$action = '';
$parameters2 = array('currentcontext'=>'productcard', 'context'=>'productcard');
ob_start();
$actions->addMoreActionsButtons($parameters2, $supplier, $action, $GLOBALS['hookmanager']);
$html = ob_get_clean();
check('bouton : absent hors fiche tiers', strpos($html, 'mmi_switch_supplier') === false);

// 4. Formulaire de confirmation
$action = 'mmi_switch_supplier';
$actions->resprints = '';
$r = $actions->formConfirm($parameters, $supplier, $action, $GLOBALS['hookmanager']);
$form_html = $actions->resprints;
check('formulaire : hook sans erreur', $r === 0);
check('formulaire : action de confirmation', strpos($form_html, 'confirm_mmi_switch_supplier') !== false);
check('formulaire : sélecteur de fournisseur cible', strpos($form_html, 'mmi_switch_to') !== false);
check('formulaire : case de désactivation', strpos($form_html, 'mmi_switch_deactivate') !== false);
check('formulaire : compteurs de l\'aperçu affichés', preg_match('/ligne\(s\)|produit\(s\)/', strip_tags($form_html)) === 1, substr(strip_tags($form_html), 0, 200));

// 5. Pas de formulaire sans l'action
$action = '';
$actions->resprints = '';
$actions->formConfirm($parameters, $supplier, $action, $GLOBALS['hookmanager']);
check('formulaire : absent sans l\'action', strpos((string) $actions->resprints, 'confirm_mmi_switch_supplier') === false);

echo "\n".($n - $fail)."/$n OK\n";
exit($fail ? 1 : 0);

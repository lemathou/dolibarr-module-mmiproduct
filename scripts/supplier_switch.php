#!/bin/php
<?php
/**
 * Copyright (C) 2026       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

/**
 * \file    scripts/supplier_switch.php
 * \ingroup mmiproduct
 * \brief   Bascule en masse des produits d'un fournisseur vers un autre (RM3235/RM3224).
 *
 * Même traitement que le bouton « Basculer les produits » de la fiche fournisseur : les
 * prix d'achat changent de tiers (réf fournisseur conservée), le trigger met à jour le
 * fournisseur par défaut des produits, la synchro Presta est notifiée. Les fournisseurs
 * source sont CONSERVÉS avec leur historique ; ils ne sont désactivés que sur demande.
 *
 * Utile quand plusieurs fournisseurs basculent d'un coup (changement de distributeur),
 * ou pour garder une trace rejouable de l'opération de MEP.
 *
 * Usage (php7.4, depuis CE dossier — cf. mmicommon/_master_load.inc.php) :
 *   php7.4 supplier_switch.php --from=14,3203 --to=19776              # simulation (défaut)
 *   php7.4 supplier_switch.php --from=14,3203 --to=19776 --confirm    # exécution
 *   php7.4 supplier_switch.php --from=14 --to=19776 --confirm --deactivate
 *
 * Options :
 *   --from=ID[,ID…]  fournisseurs source (obligatoire)
 *   --to=ID          fournisseur cible (obligatoire)
 *   --confirm        exécute ; sans cette option, rien n'est écrit
 *   --deactivate     désactive un fournisseur source s'il n'a plus aucun produit après
 *   --backup=CHEMIN  fichier SQL de restauration (défaut : ./supplier_switch_restore_<ts>.sql)
 *   --no-backup      ne pas écrire de fichier de restauration
 *
 * Le fichier de restauration contient l'état AVANT (fk_soc des prix d'achat et
 * fournisseur par défaut des produits) sous forme de requêtes UPDATE rejouables.
 */

$script_file = basename(__FILE__);
require_once __DIR__.'/../master_load.inc.php';
dol_include_once('custom/mmiproduct/class/mmi_supplier_switch.class.php');

global $db, $user;

// -- Arguments --
$opts = array('from'=>'', 'to'=>0, 'confirm'=>false, 'deactivate'=>false, 'backup'=>null, 'no-backup'=>false);
foreach (array_slice($argv, 1) as $arg) {
	if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
		if (!array_key_exists($m[1], $opts)) {
			echo "Option inconnue : ".$arg."\n";
			exit(2);
		}
		$opts[$m[1]] = isset($m[2]) ? $m[2] : true;
	} else {
		echo "Argument inattendu : ".$arg."\n";
		exit(2);
	}
}
$sources = array_values(array_filter(array_map('intval', explode(',', (string) $opts['from']))));
$cible = (int) $opts['to'];
if (empty($sources) || empty($cible)) {
	echo "Usage : php7.4 ".$script_file." --from=ID[,ID] --to=ID [--confirm] [--deactivate] [--backup=CHEMIN|--no-backup]\n";
	exit(2);
}

// -- Utilisateur : celui qui portera les modifications (traçabilité Dolibarr) --
if (empty($user) || empty($user->id)) {
	$user = new User($db);
	$resql = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'user WHERE admin=1 AND statut=1 ORDER BY rowid LIMIT 1');
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		echo "Aucun utilisateur administrateur actif.\n";
		exit(2);
	}
	$user->fetch($obj->rowid);
	$user->getrights();
}

// -- Contrôle de la cible --
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
$socdest = new Societe($db);
if ($socdest->fetch($cible) <= 0 || empty($socdest->fournisseur)) {
	echo "La cible ".$cible." n'existe pas ou n'est pas un fournisseur.\n";
	exit(2);
}
echo "cible : ".$socdest->name." (id ".$cible.")\n";
echo "mode  : ".($opts['confirm'] ? "EXÉCUTION" : "simulation (ajouter --confirm pour exécuter)")."\n\n";

// -- Aperçu, et refus avant écriture si une réf fournisseur est en double --
$total_lines = 0;
$stop = false;
foreach ($sources as $src) {
	$soc = new Societe($db);
	if ($soc->fetch($src) <= 0) {
		echo "source ".$src." : introuvable\n";
		$stop = true;
		continue;
	}
	$p = mmi_supplier_switch::preview($db, $src, $cible);
	echo "source ".$src." (".$soc->name.") : ".$p['lines']." prix, ".$p['products']." produits, "
		.$p['defaults']." produit(s) dont c'est le fournisseur par défaut";
	if (!empty($p['collisions'])) {
		echo ", ".count($p['collisions'])." COLLISION(S) : ".implode(', ', array_slice($p['collisions'], 0, 10));
		$stop = true;
	}
	if ($p['error'] !== '') {
		echo ", ERREUR : ".$p['error'];
		$stop = true;
	}
	echo "\n";
	$total_lines += $p['lines'];
}
if ($stop) {
	echo "\nRien n'a été fait : corrigez les points ci-dessus (réfs en double à supprimer ou renommer).\n";
	exit(1);
}
if (empty($total_lines)) {
	echo "\nAucun prix d'achat à basculer.\n";
	exit(0);
}

// -- Sauvegarde de restauration --
if (!$opts['confirm']) {
	echo "\nSimulation terminée. ".$total_lines." prix d'achat seraient basculés.\n";
	exit(0);
}
if (empty($opts['no-backup'])) {
	$path = $opts['backup'] ? $opts['backup'] : __DIR__.'/supplier_switch_restore_'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').'.sql';
	$in = implode(',', $sources);
	$sql = array(
		'-- Restauration de la bascule fournisseur — état AVANT, '.dol_print_date(dol_now(), 'standard'),
		'-- Sources : '.$in.' ; cible : '.$cible,
	);
	$resql = $db->query('SELECT rowid, fk_soc FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc IN ('.$in.') ORDER BY rowid');
	while ($obj = $db->fetch_object($resql)) {
		$sql[] = 'UPDATE '.MAIN_DB_PREFIX.'product_fournisseur_price SET fk_soc='.((int) $obj->fk_soc).' WHERE rowid='.((int) $obj->rowid).';';
	}
	$resql = $db->query('SELECT fk_object, fk_soc_fournisseur, supplier_ref FROM '.MAIN_DB_PREFIX.'product_extrafields WHERE fk_soc_fournisseur IN ('.$in.') ORDER BY fk_object');
	while ($obj = $db->fetch_object($resql)) {
		$ref = ($obj->supplier_ref === null) ? 'NULL' : "'".$db->escape($obj->supplier_ref)."'";
		$sql[] = 'UPDATE '.MAIN_DB_PREFIX.'product_extrafields SET fk_soc_fournisseur='.((int) $obj->fk_soc_fournisseur).', supplier_ref='.$ref.' WHERE fk_object='.((int) $obj->fk_object).';';
	}
	if (file_put_contents($path, implode("\n", $sql)."\n") === false) {
		echo "Impossible d'écrire la sauvegarde ".$path." — abandon.\n";
		exit(2);
	}
	echo "\nsauvegarde : ".$path." (".(count($sql) - 2)." requêtes de restauration)\n";
}

// -- Exécution --
echo "\n";
$ok = true;
foreach ($sources as $src) {
	$t = microtime(true);
	$res = mmi_supplier_switch::run($db, $user, $src, $cible, !empty($opts['deactivate']));
	echo "source ".$src." : ".($res['ok'] ? 'OK' : 'ECHEC')
		." | prix basculés=".$res['moved']
		." | produits=".$res['products']
		.(!empty($res['deactivated']) ? " | source désactivée" : "")
		." | durée=".round(microtime(true) - $t, 1)."s"
		.($res['error'] ? " | ERREUR : ".$res['error'] : "")."\n";
	if (empty($res['ok'])) {
		$ok = false;
	}
}

// -- Contrôle final --
echo "\ncontrôle :\n";
foreach (array_merge($sources, array($cible)) as $id) {
	$resql = $db->query('SELECT
		(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc='.((int) $id).') as prix,
		(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'product_extrafields WHERE fk_soc_fournisseur='.((int) $id).') as defauts');
	$obj = $db->fetch_object($resql);
	echo "  tiers ".$id." : ".$obj->prix." prix d'achat, ".$obj->defauts." produit(s) par défaut\n";
}

exit($ok ? 0 : 1);

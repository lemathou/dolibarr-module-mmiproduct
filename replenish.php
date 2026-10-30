<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2013      Florian Henry	  	<florian.henry@open-concept.pro>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   \file       htdocs/custom/mmiproduct/replenish.php
 *   \brief      Tableau de bord réappro fournisseurs (vue d'ensemble « où commander »)
 *   \ingroup    mmiproduct
 */

require_once 'main_load.inc.php';

$help_url = '';
$page_name = 'MMIProductStockReplenish';

// Security check
if ($user->socid) {
	$socid = $user->socid;
}
$result = restrictedArea($user, 'produit|service');

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once '../mmicommon/lib/mmi_1.lib.php';

// Translations
$langs->loadLangs(array("errors", "admin", $modulecontext));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$product_info_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_INFO_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_INFO_SEUIL/100 :0.5;
$product_warn_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_WARN_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_WARN_SEUIL/100 :0.5;
$product_alert_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_ALERT_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_ALERT_SEUIL/100 :0.5;

$prestasync = !empty($conf->mmiprestasync->enabled);

$vue = GETPOST('vue') ?: 'bloc';

// Rattachement produit → fournisseur (RM1566)
// En mode prestasync, le fournisseur d'un produit est celui DÉSIGNÉ par l'extrafield
// product_extrafields.fk_soc_fournisseur. La table product_fournisseur_price, elle,
// conserve les lignes de prix anciennes ou secondaires : s'en servir seule fait
// apparaître dans le réappro des fournisseurs qui ne fournissent plus rien (cas
// consenza) et gonfle le nombre de produits des autres.
// On substitue donc à la table des prix une vue « fournisseur effectif » : le
// fournisseur désigné quand il est renseigné et bien fournisseur, à défaut les
// fournisseurs des lignes de prix (produits sans désignation exploitable).
// Colonnes exposées : fk_product, fk_soc — les seules utilisées par les requêtes.
$table_prod_fourn = MAIN_DB_PREFIX.'product_fournisseur_price';
if ($prestasync) {
	$table_prod_fourn = '(SELECT pfe.fk_object fk_product, pfe.fk_soc_fournisseur fk_soc
			FROM '.MAIN_DB_PREFIX.'product_extrafields pfe
			INNER JOIN '.MAIN_DB_PREFIX.'societe pfs ON pfs.rowid=pfe.fk_soc_fournisseur AND pfs.fournisseur=1
		UNION
		SELECT pfp.fk_product, pfp.fk_soc
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price pfp
			INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields pfe ON pfe.fk_object=pfp.fk_product
			LEFT JOIN '.MAIN_DB_PREFIX.'societe pfs ON pfs.rowid=pfe.fk_soc_fournisseur AND pfs.fournisseur=1
			WHERE pfs.rowid IS NULL)';
}

/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);


llxHeader('', $langs->trans($page_name), $help_url);

print load_fiche_titre($langs->trans($page_name), '', 'title_setup');

echo '<div id="altmenu">';
echo '<p id="refresh"><a href="?refresh">Actualiser</a></p>';
if ($vue=='list') {
	echo '<p id="list"><a href="?vue=bloc">Vue carte</a></p>';
} else {
	echo '<p id="list"><a href="?vue=list">Vue liste</a></p>';
}
echo '</div>';

// Configuration header
//$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
//$head = mmiPrepareHead();
//print dol_get_fiche_head($head, 'about', $langs->trans($page_name), 0, $modulecontext);

?>
<style>
	.fourn {
		float: left;
		width: 250px;
		margin: 5px;
		border: 1px solid gray;
		padding: 4px;
		height: 135px;
	}
	.fourn h3 {
		margin: 0;
		padding: 0 10px;
		background-color: gray;
	}
	.fourn.nb_info h3 {
		background-color: yellow;
	}
	.fourn.nb_warn h3 {
		background-color: orange;
	}
	.fourn.nb_alert h3 {
		background-color: red;
	}
	.fourn .nb {
		float: right;
		font-weight: bold;
		margin-left: 10px;
	}
	.fourn .nb_alert a {
		color: red;
	}
	.fourn .nb_warn a {
		color: orange;
	}
	.fourn .nb_info a {
		color: green;
	}
	.fourn p {
		margin: 0;
	}
	.fourn textarea {
		width: 240px;
	}

	#altmenu {
		float: right;
		margin-top: -50px;
		margin-bottom: 0;
	}
</style>

<script>
$(document).ready(function(){
	// Édition de la note fournisseur, valable pour les deux vues (carte .fourn et ligne <tr data-id>)
	$('#fournisseurs').on('change', '[data-id] textarea', function(){
		var id = $(this).closest('[data-id]').data('id');
		var note = $(this).val();
		$.post('replenish_note_ajax.php', {id: id, note: note}, function(r){
			if (r=="1")
				alert("Mis à jour OK")
			else
				alert("Message : "+r)
		});
	});
});
</script>

<div id="fournisseurs">
<?php

$l = [];

// Fournisseurs
$sql = 'SELECT s2.*, s.*, COUNT(DISTINCT p.rowid) product_nb
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields s2 ON s2.fk_object=s.rowid
	LEFT JOIN '.$table_prod_fourn.' ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND  p2.p_decli_disabled IS NULL))' :'').'
	GROUP BY s.rowid
	'.($prestasync ?'HAVING product_nb > 0' :'').'
	ORDER BY s.nom';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	$l[$row['rowid']] = array_merge($row, ['alert_nb'=>0, 'warn_nb'=>0, 'info_nb'=>0, 'products'=>[]]);
}

// Produits
$sql = 'SELECT DISTINCT ps.fk_soc, p.rowid, p.seuil_stock_alerte, p.desiredstock, p.stock qty
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields s2 ON s2.fk_object=s.rowid
	LEFT JOIN '.$table_prod_fourn.' ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND  p2.p_decli_disabled IS NULL))' :'').'
	GROUP BY p.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	// Garde : le LEFT JOIN produit une ligne fk_soc/rowid à NULL pour les fournisseurs
	// sans produit — sans elle, une entrée fantôme sans nom se crée dans la liste (RM1566)
	if (!isset($l[$row['fk_soc']]) || $row['rowid']===null)
		continue;
	$l[$row['fk_soc']]['products'][$row['rowid']] = array_merge($row, ['cmd_qty'=>0, 'cmd_expe_qty'=>0, 'fcmd_qty'=>0, 'fcmd_recpt_qty'=>0]);
}

// Commandes client en cours (pour calcul stock)
$sql = 'SELECT ps.fk_soc, p.rowid, SUM(IF(cd.qty > 0, cd.qty, 0)) cmd_qty
	FROM '.$table_prod_fourn.' ps
	INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'commande c
	INNER JOIN '.MAIN_DB_PREFIX.'commandedet cd ON cd.fk_commande=c.rowid AND cd.fk_product=p.rowid
	WHERE 1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND p2.p_decli_disabled IS NULL))' :'').'
		AND c.fk_statut IN ('.implode(',', [Commande::STATUS_VALIDATED, Commande::STATUS_SHIPMENTONPROCESS]).')
	GROUP BY p.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	if (isset($l[$row['fk_soc']]['products'][$row['rowid']]))
		$l[$row['fk_soc']]['products'][$row['rowid']] = array_merge($l[$row['fk_soc']]['products'][$row['rowid']], $row);
}
// Commandes client en cours (pour info)
$sql = 'SELECT ps.fk_soc, COUNT(DISTINCT c.rowid) as cmd_encours_nb
	FROM '.$table_prod_fourn.' ps
	INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'commande c
	INNER JOIN '.MAIN_DB_PREFIX.'commande_extrafields c2 ON c2.fk_object=c.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'commandedet cd ON cd.fk_commande=c.rowid AND cd.fk_product=p.rowid
	WHERE 1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND p2.p_decli_disabled IS NULL))' :'').'
		AND c.fk_statut IN ('.implode(',', [Commande::STATUS_VALIDATED, Commande::STATUS_SHIPMENTONPROCESS]).')
		AND (c2.rowid IS NULL OR c2.expe_ok=1)
	GROUP BY ps.fk_soc';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	// Garde : ne jamais créer une entrée fournisseur absente de la liste construite
	// plus haut (sinon carte vide affichée pour un fournisseur sans produit — RM1566)
	if (isset($l[$row['fk_soc']]))
		$l[$row['fk_soc']]['cmd_encours_nb'] = $row['cmd_encours_nb'];
}

// Expé Commandes client en cours
$sql = 'SELECT ps.fk_soc, p.rowid, SUM(IF(cdd.qty > 0, IF(cdd.qty>=cd.qty, cd.qty, cdd.qty), 0)) cmd_expe_qty
	FROM '.$table_prod_fourn.' ps
	INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'commande c
	INNER JOIN '.MAIN_DB_PREFIX.'commandedet cd ON cd.fk_commande=c.rowid AND cd.fk_product=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'expeditiondet cdd ON cdd.fk_elementdet=cd.rowid
	WHERE 1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND p2.p_decli_disabled IS NULL))' :'').'
		AND c.fk_statut IN ('.implode(',', [Commande::STATUS_VALIDATED, Commande::STATUS_SHIPMENTONPROCESS]).')
	GROUP BY p.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	if (isset($l[$row['fk_soc']]['products'][$row['rowid']]))
		$l[$row['fk_soc']]['products'][$row['rowid']] = array_merge($l[$row['fk_soc']]['products'][$row['rowid']], $row);
}

// Commandes fournisseur en cours (pour calcul stock produi)
$sql = 'SELECT ps.fk_soc, p.rowid, SUM(IF(scd.qty > 0, scd.qty, 0)) fcmd_qty
	FROM '.$table_prod_fourn.' ps
	INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	INNER JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur sc ON sc.fk_soc=ps.fk_soc
	INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseurdet scd ON scd.fk_commande=sc.rowid AND scd.fk_product=p.rowid
	WHERE 1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND p2.p_decli_disabled IS NULL))' :'').'
		AND sc.fk_statut IN ('.implode(',', [CommandeFournisseur::STATUS_ACCEPTED, CommandeFournisseur::STATUS_ORDERSENT, CommandeFournisseur::STATUS_RECEIVED_PARTIALLY]).')
	GROUP BY p.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	if (isset($l[$row['fk_soc']]['products'][$row['rowid']]))
		$l[$row['fk_soc']]['products'][$row['rowid']] = array_merge($l[$row['fk_soc']]['products'][$row['rowid']], $row);
}
// Commandes fournisseur en cours (pour affichage infoirmatif)
$sql = 'SELECT sc.fk_soc,
		SUM(IF(sc.fk_statut IN ('.implode(',', [CommandeFournisseur::STATUS_ORDERSENT, CommandeFournisseur::STATUS_RECEIVED_PARTIALLY]).'),1,0)) as cmd_f_encours_nb,
		SUM(IF(sc.fk_statut IN ('.implode(',', [CommandeFournisseur::STATUS_VALIDATED, CommandeFournisseur::STATUS_ACCEPTED]).'),1,0)) as cmd_f_valid_nb,
		SUM(IF(sc.fk_statut="0",1,0)) as cmd_f_draft_nb
	FROM '.MAIN_DB_PREFIX.'commande_fournisseur sc
	WHERE sc.fk_statut IN ('.implode(',', [CommandeFournisseur::STATUS_DRAFT, CommandeFournisseur::STATUS_VALIDATED, CommandeFournisseur::STATUS_ACCEPTED, CommandeFournisseur::STATUS_ORDERSENT, CommandeFournisseur::STATUS_RECEIVED_PARTIALLY]).')
	GROUP BY sc.fk_soc';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	// Garde : cette requête ne filtre pas sur les produits, elle peut donc remonter
	// un fournisseur absent de la liste affichée (RM1566)
	if (!isset($l[$row['fk_soc']]))
		continue;
	$l[$row['fk_soc']]['cmd_f_encours_nb'] = $row['cmd_f_encours_nb'];
	$l[$row['fk_soc']]['cmd_f_valid_nb'] = $row['cmd_f_valid_nb'];
	$l[$row['fk_soc']]['cmd_f_draft_nb'] = $row['cmd_f_draft_nb'];
}

// Réceptions commandes fournisseur en cours
$sql = 'SELECT ps.fk_soc, p.rowid, SUM(IF(scdd.qty > 0, IF(scdd.qty>=scd.qty, scd.qty, scdd.qty), 0)) fcmd_recpt_qty
	FROM '.$table_prod_fourn.' ps
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur sc ON sc.fk_soc=ps.fk_soc
	LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseurdet scd ON scd.fk_commande=sc.rowid AND scd.fk_product=p.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'receptiondet_batch scdd ON scdd.fk_elementdet=scd.rowid
	WHERE 1
		'.($prestasync ?'AND (p2.rowid IS NULL OR (p2.p_active=1 AND p2.p_decli_disabled IS NULL))' :'').'
		AND sc.fk_statut IN ('.implode(',', [CommandeFournisseur::STATUS_ACCEPTED, CommandeFournisseur::STATUS_ORDERSENT, CommandeFournisseur::STATUS_RECEIVED_PARTIALLY]).')
	GROUP BY p.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	if (isset($l[$row['fk_soc']]['products'][$row['rowid']]))
		$l[$row['fk_soc']]['products'][$row['rowid']] = array_merge($l[$row['fk_soc']]['products'][$row['rowid']], $row);
}

// @todo calculer à la mano !

foreach($l as &$row) {
	foreach($row['products'] as &$rowp) {
		// Stock projeté = stock physique − (commandes client restant à expédier) + (commandes fournisseur restant à recevoir)
		$rowp['stock'] = $rowp['qty'] - $rowp['cmd_qty'] + $rowp['cmd_expe_qty'] + $rowp['fcmd_qty'] - $rowp['fcmd_recpt_qty'];
		if ($rowp['stock'] <= 0)
			$row['alert_nb']++;
		elseif (empty($rowp['seuil_stock_alerte']) || $rowp['stock'] <= $rowp['seuil_stock_alerte'])
			$row['warn_nb']++;
		elseif (!empty($rowp['desiredstock']) && $rowp['stock'] <= ($rowp['seuil_stock_alerte']+$rowp['desiredstock'])/2)
			$row['info_nb']++;
	}
}
if (!empty($row))
	unset($row);

if ($vue=='list') {
	echo '<div class="div-table-responsive-no-min">';
	echo '<table class="noborder">';
	echo '<thead>';
	echo '<tr class="liste_titre">';
		echo '<th style="max-width: 500px;text-align: left;">Fournisseur</th>';
		echo '<th width="100">Produits</th>';
		echo '<th width="200">Commandes</th>';
		echo '<th width="50">Info</th>';
		echo '<th width="50">Warn</th>';
		echo '<th width="50">Alertes</th>';
		echo '<th></th>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';
}

foreach($l as $id=>$row) {
	$fourn_name = (strlen($row['nom'])>20 ?substr($row['nom'], 0, 18).'...' :$row['nom']);
	$pnb = max(1, (int) $row['product_nb']); // garde anti division par zéro (fournisseurs sans produit)

	$products_link = '<a href="/product/list.php?search_options_fk_soc_fournisseur='.$id.'">'.$row['product_nb'].'</a>';
	$cmd_link = '<a href="/fourn/commande/list.php?socid='.$id.'">'.($row['cmd_f_encours_nb'] ?$row['cmd_f_encours_nb'] :0).' cmd'.(!empty($row['cmd_f_valid_nb']) ?' +'.$row['cmd_f_valid_nb'].' à valider' :'').(!empty($row['cmd_f_draft_nb']) ?' +'.$row['cmd_f_draft_nb'].' brouillon' :'').'</a>';

	$info_link = ($row['info_nb']>0) ?'<a href="/product/stock/replenish.php?fk_supplier='.$id.($prestasync ?'&p_active=1&useddm30asstock&stockalertzero=1&includeproductswithoutdesiredqty=on' :'').'">'.$row['info_nb'].'</a>' :'';
	$warn_link = ($row['warn_nb']>0) ?'<a href="/product/stock/replenish.php?fk_supplier='.$id.($prestasync ?'&p_active=1&useddm30asstock&stockalertzero=1&includeproductswithoutdesiredqty=on' :'').'">'.$row['warn_nb'].'</a>' :'';
	$alert_link = ($row['alert_nb']>0) ?'<a href="/product/stock/replenish.php?fk_supplier='.$id.($prestasync ?'&p_active=1&useddm30asstock&stockalertzero=1&includeproductswithoutdesiredqty=on' :'').'">'.$row['alert_nb'].'</a>' :'';

	if ($vue=='list') {
		echo '<tr data-id="'.$row['rowid'].'">';
		echo '<td>'.$fourn_name.'</td>';
		echo '<td>'.$products_link.'</td>';
		echo '<td>'.$cmd_link.'</td>';
		echo '<td>'.$info_link.'</td>';
		echo '<td>'.$warn_link.'</td>';
		echo '<td>'.$alert_link.'</td>';
		echo '<td><textarea rows="2">'.dol_escape_htmltag($row['replenish_note']).'</textarea></td>';
		echo '<td></td>';
		echo '</tr>';
	}
	else {
		echo '<div class="fourn'.($row['alert_nb']/$pnb>=$product_alert_seuil ?' nb_alert' : '').(($row['alert_nb']+$row['warn_nb'])/$pnb>=$product_warn_seuil ?' nb_warn' : '').(($row['alert_nb']+$row['warn_nb']+$row['info_nb'])/$pnb>=$product_info_seuil ?' nb_info' : '').'" data-id="'.$row['rowid'].'">';
		echo '<h3>'.$fourn_name.'</h3>';
		if (!empty($info_link))
			echo '<p class="nb nb_info">'.$info_link.'</p>';
		if (!empty($warn_link))
			echo '<p class="nb nb_warn">'.$warn_link.'</p>';
		if (!empty($alert_link))
			echo '<p class="nb nb_alert">'.$alert_link.'</p>';
		echo '<p>'.$products_link.' produits</p>';
		echo '<p>'.$cmd_link.'</p>';
		//echo '<p>'.($row['cmd_encours_nb'] ?$row['cmd_encours_nb'] :0).' cmd. cli. en cours</p>';
		echo '<div><textarea>'.dol_escape_htmltag($row['replenish_note']).'</textarea></div>';
		echo '</div>';
	}
}

if ($vue=='list') {
	echo '</tbody>';
	echo '</table>';
	echo '</div>';
}

if (!empty($row))
	unset($row);
?>
</div>

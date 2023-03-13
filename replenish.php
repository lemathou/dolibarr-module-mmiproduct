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
 *   \file       htdocs/product/note.php
 *   \brief      Tab for notes on products
 *   \ingroup    societe
 */

require_once 'env.inc.php';
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
require_once '../mmicommon/lib/mmi_1.lib.php';

// Translations
$langs->loadLangs(array("errors", "admin", $modulecontext));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');


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
		height: 70px;
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
</style>

<div id="fournisseurs">
<?php

$l = [];

$sql = 'SELECT s2.*, s.*, COUNT(DISTINCT p.rowid) product_nb
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'societe_extrafields s2 ON s2.fk_object=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		AND p2.p_active=1
	GROUP BY s.rowid
	ORDER BY s.nom';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	$l[$row['rowid']] = $row;
}

$sql = 'SELECT s.rowid, COUNT(DISTINCT p.rowid) product_alert_nb
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		AND p2.p_active=1
		AND (p.seuil_stock_alerte IS NOT NULL AND p.stock <= 0)
		AND (p.desiredstock=0 OR p.desiredstock>0)
	GROUP BY s.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	$l[$row['rowid']] = array_merge($l[$row['rowid']], $row);
}

$sql = 'SELECT s.rowid, COUNT(DISTINCT p.rowid) product_warn_nb
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		AND p2.p_active=1
		AND (p.seuil_stock_alerte IS NOT NULL AND p.stock > 0 AND p.stock <= p.seuil_stock_alerte)
		AND (p.desiredstock=0 OR p.desiredstock>0)
	GROUP BY s.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	$l[$row['rowid']] = array_merge($l[$row['rowid']], $row);
}

$sql = 'SELECT s.rowid, COUNT(DISTINCT p.rowid) product_info_nb
	FROM '.MAIN_DB_PREFIX.'societe s
	LEFT JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price ps ON ps.fk_soc=s.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=ps.fk_product
	LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
	WHERE s.fournisseur=1
		AND p2.p_active=1
		AND (p.seuil_stock_alerte IS NOT NULL AND p.desiredstock>0)
		AND (p.stock > p.seuil_stock_alerte AND p.stock < (p.desiredstock+p.seuil_stock_alerte)/2)
	GROUP BY s.rowid';
$q = $db->query($sql);
while($row=$q->fetch_assoc()) {
	$l[$row['rowid']] = array_merge($l[$row['rowid']], $row);
}

foreach($l as $id=>$row) {
	echo '<div class="fourn'.($row['product_alert_nb']>0 ?' nb_alert' : '').($row['product_warn_nb']>0 ?' nb_warn' : '').($row['product_info_nb']>0 ?' nb_info' : '').'">';
	echo '<h3>'.$row['nom'].'</h3>';
	if ($row['product_info_nb']>0)
		echo '<p class="nb nb_info"><a href="/product/stock/replenish.php?fk_supplier='.$id.'">'.$row['product_alert_nb'].'</a></p>';
	if ($row['product_warn_nb']>0)
		echo '<p class="nb nb_warn"><a href="/product/stock/replenish.php?fk_supplier='.$id.'">'.$row['product_warn_nb'].'</a></p>';
	if ($row['product_alert_nb']>0)
		echo '<p class="nb nb_alert"><a href="/product/stock/replenish.php?fk_supplier='.$id.'">'.$row['product_alert_nb'].'</a></p>';
	echo '<p><a href="/product/list.php?search_options_fk_soc_fournisseur='.$id.'">'.$row['product_nb'].' produits</a></p>';
	echo '</div>';
}
?>
</div>

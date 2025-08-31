<?php

/* Copyright (C) 2025      Mathieu Moulin       <mathieu@iprospective.fr>
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

require_once 'main_load.inc.php';

// Config

// Produits à suivre pour les clients PRO
$fk_categorie = 146;
$fk_categorie = 7;

// Filter par client
$filter_customer_id = GETPOSTINT('customer_id');
$filter_supplier_id = GETPOSTINT('supplier_id');
$filter_month_nb = GETPOSTINT('filter_month_nb');
if (empty($filter_month_nb))
	$filter_month_nb = 6;
$show_cmd_list = GETPOSTINT('show_cmd_list');
$show_product_cmd_only = GETPOSTINT('show_product_cmd_only');

//

$help_url = '';
$page_name = 'MMIProductStockReplenishPRO';

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

// Products

$list = [];
$sql = 'SELECT p.rowid, p.ref, p.label, p2.fk_soc_fournisseur, p2.supplier_ref, p.stock, COUNT(DISTINCT psl.rowid) lots_nb, GROUP_CONCAT(DISTINCT pl.sellby, ";", pl.batch, ";", psl.qty SEPARATOR "\n") AS lots_list'
	.' FROM llx_product AS p'
	.' INNER JOIN llx_product_extrafields AS p2 ON p2.fk_object=p.rowid'
	.' INNER JOIN llx_categorie_product AS kp ON kp.fk_product=p.rowid'
	.' LEFT JOIN llx_product_lot AS pl ON pl.fk_product=p.rowid'
	.' LEFT JOIN llx_product_stock AS ps ON ps.fk_product=p.rowid'
	.' LEFT JOIN llx_product_batch AS psl ON psl.fk_product_stock=ps.rowid AND psl.batch=pl.batch AND psl.qty>0'
	.' WHERE kp.fk_categorie='.$fk_categorie
	.' AND ps.fk_entrepot=1'
	.' GROUP BY p.rowid';

$resql = $db->query($sql);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list[$row->rowid] = $row;
	}
	$db->free($resql);
}

// Customers

$list_customer = [];
$sql = 'SELECT s.rowid, s.nom'
	.' FROM llx_societe AS s'
	.' INNER JOIN llx_societe_extrafields AS cs2 ON cs2.fk_object=s.rowid'
	.' WHERE cs2.pro=1'
	.' ORDER BY s.nom ASC';
$resql = $db->query($sql);
//var_dump($sql, $resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list_customer[$row->rowid] = $row;
	}
	$db->free($resql);
}
//var_dump($list_customer);

// Suppliers

$list_supplier = [];
$sql = 'SELECT DISTINCT s.rowid, s.nom'
	.' FROM llx_societe AS s'
	.' INNER JOIN llx_product_extrafields AS p2 ON p2.fk_soc_fournisseur=s.rowid'
	.' INNER JOIN llx_categorie_product AS kp ON kp.fk_product=p2.fk_object'
	.' WHERE kp.fk_categorie='.$fk_categorie
	.' ORDER BY s.nom ASC';
$resql = $db->query($sql);
//
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list_supplier[$row->rowid] = $row;
	}
	$db->free($resql);
}
//var_dump($list_supplier);

// Détail Produits commandés par les clients PRO

$sql = 'SELECT cd.fk_product AS rowid, c.fk_soc AS cust_id, COUNT(DISTINCT c.rowid) AS cmd_nb, GROUP_CONCAT(DISTINCT c.rowid, ",", c.ref SEPARATOR ";") cmd_list, SUM(cd.qty) AS cmd_qte'
	.' FROM llx_commandedet AS cd'
	.' INNER JOIN llx_product AS p ON p.rowid=cd.fk_product'
	.' INNER JOIN llx_product_extrafields AS p2 ON p2.fk_object=cd.fk_product'
	.' INNER JOIN llx_categorie_product AS kp ON kp.fk_product=cd.fk_product'
	.' INNER JOIN llx_commande AS c ON c.rowid=cd.fk_commande'
	.' INNER JOIN llx_societe AS cs ON cs.rowid=c.fk_soc'
	.' INNER JOIN llx_societe_extrafields AS cs2 ON cs2.fk_object=cs.rowid'
	.' WHERE kp.fk_categorie='.$fk_categorie
	.' AND cs2.pro=1'
	.' AND c.fk_statut >= 1 AND DATEDIFF(c.date_commande, NOW())>=-'.($filter_month_nb*30)
	.(!empty($filter_customer_id) ?' AND c.fk_soc='.$filter_customer_id :'')
	.(!empty($filter_supplier_id) ?' AND p2.fk_soc_fournisseur='.$filter_supplier_id :'')
	.' GROUP BY p.rowid, c.fk_soc';

$resql = $db->query($sql);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list[$row->rowid]->cmd_nb += $row->cmd_nb;
		$list[$row->rowid]->cmd_qte += $row->cmd_qte;
		$list[$row->rowid]->cmd_list = explode(';', $row->cmd_list);
		$list[$row->rowid]->customers[$row->cust_id] = $row;
		$list_customer[$row->cust_id]->products[$row->fk_product] = $row;
		$list_customer[$row->cust_id]->cmd_nb += $row->cmd_nb;
		$list_customer[$row->cust_id]->cmd_qte += $row->cmd_qte;
	}
	$db->free($resql);
}

//var_dump($list[235]);

// DISPLAY

echo '<form>';
echo '<p>';
echo '<label for="filter_month_nb">Nombre de mois à analyser :</label> ';
echo '<input name="filter_month_nb" id="filter_month_nb" value="'.$filter_month_nb.'" size="2" onchange="this.form.submit();" />';
echo '</p>';
echo '<p>';
echo '<label for="customer_id">Filtrer par client :</label> ';
echo '<select name="customer_id" id="customer_id" onchange="this.form.submit();">';
echo '<option value="">-- Tous les clients --</option>';
echo '<optgroup label="Clients avec commandes">';
foreach($list_customer as $customer) {
	if (empty($customer->cmd_nb))
		continue;
	$selected = ($customer->rowid == $filter_customer_id) ?' selected="selected"' :'';
	echo '<option value="'.$customer->rowid.'"'.$selected.'>'.$customer->nom.(!empty($customer->cmd_nb) ?' ('.$customer->cmd_nb.')' :'').'</option>';
}
echo '</optgroup>';
echo '<optgroup label="Clients sans commandes">';
foreach($list_customer as $customer) {
	if (! empty($customer->cmd_nb))
		continue;
	$selected = ($customer->rowid == $filter_customer_id) ?' selected="selected"' :'';
	echo '<option value="'.$customer->rowid.'"'.$selected.'>'.$customer->nom.(!empty($customer->cmd_nb) ?' ('.$customer->cmd_nb.')' :'').'</option>';
}
echo '</optgroup>';
echo '</select>';
echo '</p>';
echo '<p>';
echo '<label for="supplier_id">Filtrer par fournisseur :</label> ';
echo '<select name="supplier_id" id="supplier_id" onchange="this.form.submit();">';
echo '<option value="">-- Tous les fournisseurs --</option>';
foreach($list_supplier as $supplier) {
	$selected = ($supplier->rowid == $filter_supplier_id) ?' selected="selected"' :'';
	echo '<option value="'.$supplier->rowid.'"'.$selected.'>'.$supplier->nom.'</option>';
}
echo '</select>';
echo '</p>';
echo '<p>';
echo '<label for="show_cmd_list">Afficher le détail des commandes clients :</label> ';
echo '<input type="checkbox" name="show_cmd_list" id="show_cmd_list" value="1"'.($show_cmd_list ?' checked' :'').' onchange="this.form.submit();" />';
echo '</p>';
echo '<p>';
echo '<label for="show_product_cmd_only">Afficher uniquement les produits commandés :</label> ';
echo '<input type="checkbox" name="show_product_cmd_only" id="show_product_cmd_only" value="1"'.($show_product_cmd_only ?' checked' :'').' onchange="this.form.submit();" />';
echo '</p>';
echo '</form>';

$resql = $db->query($sql);
echo '<table class="noborder" width="100%">';
echo '<thead>';
echo '<tr class="liste_titre">';
echo '<th>Réf</th>';
echo '<th>Réf fourn</th>';
echo '<th>Fournisseur</th>';
echo '<th>Nom</th>';
echo '<th>Stock</th>';
echo '<th>Lots</th>';
//echo '<th>Nb clients</th>';
echo '<th>Qty cmd</th>';
echo '<th>Cmd</th>';
echo '</tr>';
echo '</thead>';

//var_dump($list);

if (!empty($list)) {
	echo '<tbody>';
	foreach($list as $row) {
		if (empty($row->cmd_qte) && !empty($show_product_cmd_only))
			continue;
		if(!empty($filter_supplier_id) && $row->fk_soc_fournisseur != $filter_supplier_id)
			continue;

		$lots = [];
		foreach(explode("\n", $row->lots_list) as $lot) {
			if (empty($lot))
				continue;
			$lot = explode(';', $lot);
			$lots[] = '<span title="'.$lot[1].'">'.$lot[0].'</span>&nbsp;:&nbsp;'.$lot[2];
		}

		echo '<tr class="product">';
		echo '<td>'.$row->ref.'</td>';
		echo '<td>'.$row->supplier_ref.'</td>';
		echo '<td>'.$list_supplier[$row->fk_soc_fournisseur]->nom.'</td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.$row->rowid.'" target="_blank">'.$row->label.'</td>';
		echo '<td align="right">'.$row->stock.'</td>';
		echo '<td>'.implode('<br />', $lots).'</td>';
		//echo '<td align="right">'.$row->cust_nb.'</td>';
		echo '<td align="right">'.$row->cmd_qte.'</td>';
		echo '<td align="right">'.$row->cmd_nb.'</td>';
		//echo '<td><div class="hidden2">'.implode('<br />', $row->cmd_list).'</div></td>';
		//echo '<td>'.implode('<br />', $cust_list).'</td>';
		if (!empty($row->customers)) {
			echo '</tr>';
			foreach($row->customers as $cust_info) {
				$cust = $list_customer[$cust_info->cust_id];
				echo '<tr class="customer">';
				echo '<td colspan="3">&nbsp;</td>';
				echo '<td colspan="3"><a href="'.DOL_URL_ROOT.'/societe/soc.php?socid='.$cust->cust_id.'" target="_blank">'.$cust->nom.'</a></td>';
				echo '<td align="right">'.$cust_info->cmd_qte.'</td>';
				if (!empty($show_cmd_list)) {
					echo '<td><div class="hidden2">';
					foreach($row->cmd_list as $cmd) {
						$cmd = explode(',', $cmd);
						echo '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.$cmd[0].'" target="_blank">'.$cmd[1].'</a><br />';
					}
					echo '</div></td>';
				}
				else
					echo '<td></td>';
				echo '<td></td>';
				echo '</tr>';
			}
		}
		else {
			echo '</tr>';
		}
	}
	echo '</tbody>';
	$db->free($resql);
}
echo '</table>';

?>

<style>
tr.product > td {
	border-top: 1px solid black;
}
.hidden {
	display: none;
}
</style>

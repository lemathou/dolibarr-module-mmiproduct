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

// Config

// Produits à suivre pour les clients PRO
$fk_product_categorie = getDolGlobalInt('MMIPRODUCT_PRO_PRODUCT_CATEGORY');
//$fk_customer_categorie = getDolGlobalInt('MMIPRODUCT_PRO_CUSTOMER_CATEGORY'); // Us&e extrafied pro in soc.

$filter_entrepot_id = 1;

$url_params = [];
// Uniquement les produits taggués PRO
$filter_product_pro = GETPOSTINT('filter_product_pro');
if (!empty($filter_product_pro))
	$url_params[] = 'filter_product_pro=1';
// Filter par client
$filter_customer_id = GETPOSTINT('customer_id');
if (!empty($filter_customer_id))
	$url_params[] = 'customer_id='.$filter_customer_id;
// Filter par fournisseur
$filter_supplier_id = GETPOSTINT('supplier_id');
if (!empty($filter_supplier_id))
	$url_params[] = 'supplier_id='.$filter_supplier_id;
// Filter par nb de mois
$filter_month_nb = GETPOSTINT('filter_month_nb');
if (empty($filter_month_nb))
	$filter_month_nb = 3;
$analyse_days_nb = $filter_month_nb*30;
$url_params[] = 'filter_month_nb='.$filter_month_nb;
// Options d'affichage
// Détail des commandes clients
$show_cmd_list = GETPOSTINT('show_cmd_list');
if (!empty($show_cmd_list))
	$url_params[] = 'show_cmd_list=1';
// Afficher uniquement les produits commandés
$filter_product_cmd_only = GETPOSTINT('filter_product_cmd_only');
if (!empty($filter_product_cmd_only))
	$url_params[] = 'filter_product_cmd_only=1';
// Afficher une ligne par client
$show_customers_detail = GETPOSTINT('show_customers_detail');
if (!empty($show_customers_detail))
	$url_params[] = 'show_customers_detail=1';
// Tri
$sort = GETPOST('sort', 'aZ09');
$sortorder = (int)GETPOSTINT('sortorder');
//var_dump($sort, $sortorder);
/*
if (!empty($sort))
	$url_params[] = 'sort='.urlencode($sort);
if (!empty($sortorder))
	$url_params[] = 'sortorder='.urlencode($sortorder);
*/
$url_params_str = !empty($url_params) ?'?'.implode('&', $url_params) :'';


$product_info_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_INFO_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_INFO_SEUIL/100 :0.5;
$product_warn_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_WARN_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_WARN_SEUIL/100 :0.5;
$product_alert_seuil = !empty($conf->global->MMI_PRODUCT_REPLENISH_ALERT_SEUIL) ?$conf->global->MMI_PRODUCT_REPLENISH_ALERT_SEUIL/100 :0.5;

$prestasync = !empty($conf->mmiprestasync->enabled);

$help_url = '';
$page_name = 'MMIProductStockReplenishFournStats';

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

/*
 * Actions
 */

$regen = GETPOST($regen);

if ($regen) {

}


/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans($page_name), $help_url);

print load_fiche_titre($langs->trans($page_name), '', 'title_setup');

echo '<div style="float: right;"><p><a href="'.$url_params_str.'&sort='.$sort.'&sortorder='.$sortorder.'&regen=1">Régénérer les stats en base de donnée</a></p></div>';

// Suppliers
$list_supplier = [];
$sql_suppliers = 'SELECT DISTINCT s.rowid, s.nom, COUNT(DISTINCT p2.rowid) AS pdt_nb'
	.' FROM `llx_societe` AS s'
	.' INNER JOIN `llx_product_extrafields` AS p2 ON p2.fk_soc_fournisseur=s.rowid'
	.($filter_product_pro ?' LEFT JOIN `llx_categorie_product` AS kp ON kp.fk_product=p2.fk_object' :'')
	.' WHERE 1'
	.' AND s.fournisseur=1'
	.($prestasync ?' AND (p2.p_active=1 AND (p2.p_decli_disabled IS NULL OR p2.p_decli_disabled=0))' :'')
	.($filter_product_pro ?' AND kp.fk_categorie='.$fk_product_categorie :'')
	.' GROUP BY s.rowid'
	.' ORDER BY s.nom ASC';
$resql = $db->query($sql_suppliers);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list_supplier[$row->rowid] = $row;
	}
	$db->free($resql);
}
//var_dump($list_supplier);

// Products

$list = [];

// Days
$sql_select_days = 'SELECT 0 n';
for($i=1;$i<=$analyse_days_nb;$i++)
	$sql_select_days .= ' UNION ALL SELECT '.$i;
// Fuck cannot change
// IF(p2.replenish_analysis_days, p2.replenish_analysis_days, '.$analyse_days_nb.')

// Service Level
$service_levels = [
	'90' => 1.28,
	'95' => 1.65,
	'97.5' => 1.96,
	'99' => 2.33,
];

// Replenish delay (entre 2 commandes)
// T = 28

// Reception delay (livraison commande)
// L = 10

// Stats produit par jour (stock, devis, commandes, commandes fourn, réceptions, expéditions, etc.)
// @todo migrer dans un script autonome @deprecated
// Commandes
$list_p = [];
$sql_p_cmd = 'SELECT
	p.rowid,
	DATE(c.date_commande) as day,
	COUNT(DISTINCT c.rowid) AS cmd_nb,
	SUM(cl.qty) AS cmd_qty

	FROM `llx_product` AS p
	
	INNER JOIN llx_commandedet AS cl
		ON cl.fk_product = p.rowid
	INNER JOIN llx_commande AS c
		ON c.rowid = cl.fk_commande
	
	WHERE c.fk_statut IN (1,2,3)

	GROUP BY p.rowid, DATE(c.date_commande)';
$resql = $db->query($sql_p_cmd);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list_p[$row->rowid][$row->day] = $row;
	}
	$db->free($resql);
}
//var_dump($list_p);

// Grosse requête de ouf

$sql = 'SELECT 
    p.rowid AS product_id,
    p.ref,
    p.label,
    p2.supplier_ref,
    p2.fk_soc_fournisseur AS supplier_id,
	p2.replenish_batch_ddm_delay,
	p2.replenish_service_level,
	p2.replenish_analysis_days,
	p2.replenish_safety_stock,
	p2.replenish_reorder_point,

	dstock.days AS stock_days,
    
    SUM(COALESCE(ds.total_qty, 0)) AS cmd_qty,
    SUM(COALESCE(ds.total_nb, 0)) AS cmd_nb,
    SUM(COALESCE(ds.total_qty, 0)) / dstock.days AS d,
    
    STDDEV_POP(COALESCE(ds.total_qty, 0)) AS sigma_d,

    COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days AS freq,

    STDDEV_POP(COALESCE(ds.total_qty, 0)) 
        * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days) 
        AS sigma_corrected,
    
    IF (s2.reception_delay>0, s2.reception_delay, 10) AS L,
    IF (s2.replenish_delay>0, s2.replenish_delay, 28) AS T,
    IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) AS Z,
    
    ROUND(
        IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
        (
            STDDEV_POP(COALESCE(ds.total_qty, 0)) 
            * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
        )
    , 2) AS safety_stock,
    
    ROUND(
        (SUM(COALESCE(ds.total_qty, 0)) / dstock.days) * IF (s2.reception_delay>0, s2.reception_delay, 10)
        +
        IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
        (
            STDDEV_POP(COALESCE(ds.total_qty, 0)) 
            * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
        )
    , 2) AS reorder_point,
    
    COALESCE(ps.reel, 0) AS stock_current,
    
    ROUND(
		(
			(SUM(COALESCE(ds.total_qty, 0)) / dstock.days) * (IF (s2.reception_delay>0, s2.reception_delay, 10) + IF (s2.replenish_delay>0, s2.replenish_delay, 28))
			+
			IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
			(
				STDDEV_POP(COALESCE(ds.total_qty, 0)) 
				* SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / dstock.days)
			)
		),
        0
    ) AS order_qty,

	IF(pfp.packaging>0, pfp.packaging, 1) AS packaging

FROM llx_product AS p

LEFT JOIN llx_product_extrafields AS p2
	ON p2.fk_object=p.rowid'

.($filter_product_pro ?
' LEFT JOIN llx_categorie_product AS kp
	ON kp.fk_product=p2.fk_object'
:'')

.' LEFT JOIN llx_product_stock AS ps
    ON ps.fk_product = p.rowid

LEFT JOIN llx_societe AS s
	ON s.rowid=p2.fk_soc_fournisseur

LEFT JOIN llx_product_fournisseur_price AS pfp
	ON pfp.fk_product=p.rowid AND pfp.fk_soc=s.rowid

LEFT JOIN llx_societe_extrafields AS s2
	ON s2.fk_object=s.rowid

CROSS JOIN (
    SELECT DATE_SUB(CURDATE(), INTERVAL n DAY) AS day
    FROM (
       '.$sql_select_days.'
    ) AS days
) AS d

LEFT JOIN (
    SELECT 
        c.`date` as day,
        c.fk_product,
        c.order_nb AS total_nb,
        c.order_qty AS total_qty
    FROM llx_product_stats c
    WHERE c.`date` >= DATE_SUB(CURDATE(), INTERVAL '.$analyse_days_nb.' DAY)
) AS ds
ON ds.day = d.day AND ds.fk_product = p.rowid

LEFT JOIN (
    SELECT COUNT(*) AS days, c.fk_product
    FROM llx_product_stock_stats c
    WHERE c.`date` >= DATE_SUB(CURDATE(), INTERVAL '.$analyse_days_nb.' DAY)
	GROUP BY c.fk_product
) AS dstock
ON dstock.fk_product=p.rowid

WHERE p.fk_product_type=0'
.(!empty($filter_supplier_id) ? ' AND p2.fk_soc_fournisseur='.$filter_supplier_id :'')
.($filter_product_pro ?' AND kp.fk_categorie='.$fk_product_categorie :'')

.' GROUP BY p.rowid

HAVING SUM(COALESCE(ds.total_qty, 0)) > 0';

// Fields list
$fields = [
	'ref' => ['label'=>'Réf', 'sortable'=>true],
	'supplier_ref' => ['label'=>'Réf fourn', 'sortable'=>true],
	'supplier_id' => ['label'=>'Fournisseur', 'sortable'=>false],
	'label' => ['label'=>'Nom', 'sortable'=>true],

	'stock_days' => ['label'=>'days', 'desc'=>'Jours avec stock'],
	'd' => ['label'=>'d', 'desc'=>'Vente moyenne'],
	'sigma_d' => ['label'=>'σd', 'desc'=>'Vente moyenne'],
	'freq' => ['label'=>'freq', 'desc'=>'Dispersion vente moyenne'],
	'sigma_corrected' => ['label'=>'σfix', 'desc'=>'Dispersion Vente moyenne corrigée'],

	'L' => ['label'=>'L', 'desc'=>'Délai de réception commande fournisseur', 'sortable'=>true],
	'T' => ['label'=>'T', 'desc'=>'Délai entre deux commandes fournisseur', 'sortable'=>true],
	'Z' => ['label'=>'Z', 'desc'=>'Taux de service (1.28 <-> 90%)', 'sortable'=>true],

	'safety_stock' => ['label'=>'safety stock', 'sortable'=>true],
	'reorder_point' => ['label'=>'reorder point', 'sortable'=>true],

	'stock_current' => ['label'=>'stock réel', 'sortable'=>true],
	'stock_theo' => ['label'=>'stock théo.', 'sortable'=>true],

	'packaging' => ['label'=>'Pack.', 'sortable'=>true],
	'order_qty' => ['label'=>'Cmd Max', 'sortable'=>true],

	'order_todo' => ['label'=>'Cmd Todo', 'sortable'=>true],

	'lots_nb' => ['label'=>'Lots', 'align'=>'right', 'sortable'=>true],
	//'cust_nb' => ['label'=>'Nb clients', 'align'=>'right'],
	'cmd_qty' => ['label'=>'Cmd Qty', 'align'=>'right', 'sortable'=>true],
	'cmd_nb' => ['label'=>'Cmd Nb', 'sortable'=>true],
];

$resql = $db->query($sql);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		// Stock théorique (moins en-cours, commandes en attente, lots périmés...)
		$row->stock_theo = $row->stock_current;

		// Paquets à commander
		if ($row->stock_theo >= $row->order_qty) {
			$row->order_todo = 0;
		}
		elseif ($row->packaging>0) {
			if ($row->packaging != 1) {
				$order_packs = round(($row->order_qty-$row->stock_theo)/$row->packaging, 2);
				$row->order_todo = ceil($order_packs)*$row->packaging;
			}
			else {
				$row->order_todo = $row->order_qty - $row->stock_theo;
			}
		}
		else {
			// Anomalie
			$row->order_todo = 0;
		}
		$list[$row->product_id] = $row;

		// Regen
		if ($regen) {
			$sql = 'UPDATE `llx_product` AS p'
				.' INNER JOIN `llx_product_extrafields` AS p2 ON p2.fk_object=p.rowid'
				.' SET'
				.' p.seuil_stock_alerte="'.ceil($row->reorder_point).'",'
				.' p.desiredstock="'.ceil($row->order_qty).'",'
				.' p2.replenish_safety_stock="'.ceil($row->safety_stock).'",'
				.' p2.replenish_reorder_point="'.ceil($row->reorder_point).'"'
				.' WHERE p.rowid='.$row->product_id;
			$q = $db->query($sql);
			//echo '<p>'.$sql.'</p>'; var_dump($q);
		}
	}
	$db->free($resql);
}
else {
	echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
}

// Sort list
if (!empty($sort)) {
	$sort_func = function($a, $b) use ($sort, $sortorder) {
		$val_a = $a->$sort;
		$val_b = $b->$sort;
		if (is_string($val_a))
			$val_a = strtolower($val_a);
		if (is_string($val_b))
			$val_b = strtolower($val_b);
		if ($val_a == $val_b)
			return 0;
		if ($sortorder)
			return ($val_a < $val_b) ? 1 : -1;
		else
			return ($val_a < $val_b) ? -1 : 1;
	};
	usort($list, $sort_func);
}

//var_dump($list[235]);

// DISPLAY

?>

<style>
tr.liste_titre th a {
	font-weight: bold;
	border: 1px solid transparent;
	padding: 1px;
}
tr.liste_titre th a.active {
	color: red;
	border-color: red;
}
tr.product > td {
	border-top: 1px solid black;
}
.hidden {
	display: none;
}
</style>

<?php

// Formulaire

echo '<form>';
//echo '<input type="hidden" name="token" value="'.newToken().'" />';
echo '<input type="hidden" name="sort" value="'.$sort.'" />';
echo '<input type="hidden" name="sortorder" value="'.$sortorder.'" />';

echo '<p>';
echo '<label for="filter_product_pro">Uniquement les produits taggués PRO :</label> ';
echo '<input type="checkbox" name="filter_product_pro" id="filter_product_pro" value="1"'.($filter_product_pro ?' checked' :'').' onchange="this.form.submit();" />';
echo '</p>';

echo '<p>';
echo '<label for="filter_product_cmd_only">Uniquement les produits avec des commandes :</label> ';
echo '<input type="checkbox" name="filter_product_cmd_only" id="filter_product_cmd_only" value="1"'.($filter_product_cmd_only ?' checked' :'').' onchange="this.form.submit();" />';
echo '</p>';

echo '<p>';
echo '<label for="filter_month_nb">Nombre de mois (max) à analyser :</label> ';
echo '<input name="filter_month_nb" id="filter_month_nb" value="'.$filter_month_nb.'" size="2" onchange="this.form.submit();" />';
echo '</p>';

echo '<p>';
echo '<label for="supplier_id">Filtrer par fournisseur :</label> ';
echo '<select name="supplier_id" id="supplier_id" onchange="this.form.submit();">';
echo '<option value="">-- Tous les fournisseurs --</option>';
foreach($list_supplier as $supplier) {
	$selected = ($supplier->rowid == $filter_supplier_id) ?' selected="selected"' :'';
	echo '<option value="'.$supplier->rowid.'"'.$selected.'>'.$supplier->nom.' ('.$supplier->pdt_nb.')</option>';
}
echo '</select>';
echo '</p>';

echo '</form>';

// Colonnes

$resql = $db->query($sql);
echo '<table class="noborder" width="100%">';
echo '<thead>';
echo '<tr class="liste_titre">';
foreach($fields as $fieldname=>$field) {
	echo '<th>'
		.$field['label']
		.(!empty($field['desc']) ?'<span'.' title="'.$field['desc'].'" class="fa fa-info-circle" style="cursor: help;"></span>' :'')
		.($field['sortable'] ?'&nbsp;<a href="'.$url_params_str.'&sort='.$fieldname.'&sortorder=0"'.(($sort===$fieldname && $sortorder===0) ?' class="active"' :'').'>&#8595;</a><a href="'.$url_params_str.'&sort='.$fieldname.'&sortorder=1"'.(($sort===$fieldname && $sortorder===1) ?' class="active"' :'').'>&#8593;</a>' :'')
		.'</th>';
}
echo '</tr>';
echo '</thead>';

//var_dump($list);

// Liste

if (!empty($list)) {
	echo '<tbody>';
	foreach($list as $row) {
		if (empty($row->cmd_nb) && !empty($filter_product_cmd_only))
			continue;
		if(!empty($filter_supplier_id) && $row->supplier_id != $filter_supplier_id)
			continue;

		$lots = [];
		foreach(explode("\n", $row->lots_list) as $lot) {
			if (empty($lot))
				continue;
			$lot = explode(';', $lot);
			$lots[] = '<span title="'.$lot[1].'">'.$lot[0].'</span>&nbsp;:&nbsp;'.$lot[2];
		}

		// Update
		if ($update) {

		}

		// Display
		echo '<tr class="product">';
		echo '<td>'.$row->ref.'</td>';
		echo '<td>'.$row->supplier_ref.'</td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$row->supplier_id.'" target="_blank">'.$list_supplier[$row->supplier_id]->nom.'</a></td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.$row->product_id.'" target="_blank">'.$row->label.'</td>';

		echo '<td align="right">'.round($row->stock_days, 2).'</td>';
		echo '<td align="right">'.round($row->d, 2).'</td>';
		echo '<td align="right">'.round($row->signa_d, 2).'</td>';
		echo '<td align="right">'.round($row->freq, 2).'</td>';
		echo '<td align="right">'.round($row->signa_corrected, 2).'</td>';

		echo '<td align="right">'.round($row->L, 2).'</td>';
		echo '<td align="right">'.round($row->T, 2).'</td>';
		echo '<td align="right">'.round($row->Z, 2).'</td>';

		echo '<td align="right">'.round($row->safety_stock, 2).'</td>';
		echo '<td align="right">'.round($row->reorder_point, 2).'</td>';

		echo '<td align="right">'.round($row->stock_current, 2).'</td>';
		echo '<td align="right">'.round($row->stock_theo, 2).'</td>';

		echo '<td align="right">'.round($row->packaging, 2).'</td>';
		echo '<td align="right">'.round($row->order_qty, 2).'</td>';

		echo '<td align="right">'.round($row->order_todo, 2).'</td>';

		echo '<td>'.implode('<br />', $lots).'</td>';
		//echo '<td align="right">'.$row->cust_nb.'</td>';
		echo '<td align="right">'.$row->cmd_qty.'</td>';
		echo '<td align="right">'.$row->cmd_nb.'</td>';
		echo '</tr>';
	}
	echo '</tbody>';
}
echo '</table>';

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
$show_product_cmd_only = GETPOSTINT('show_product_cmd_only');
if (!empty($show_product_cmd_only))
	$url_params[] = 'show_product_cmd_only=1';
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

$help_url = '';
$page_name = 'MMIProductStockReplenishFournStats';

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

// Days
$sql_select_days = 'SELECT 0 n';
for($i=1;$i<=$analyse_days_nb;$i++)
	$sql_select_days .= ' UNION ALL SELECT '.$i;
// Fuck cannot change
// IF(p2.replenish_analysis_days, p2.replenish_analysis_days, '.$analyse_days_nb.')

// Service Level
// 1.28 for 90% service level, 1.65 for 95%, 1.96 for 97.5%, and 2.33 for 99%


// Replenish delay (entre 2 commandes)
// T = 28

// Reception delay (livraison commande)
// L = 10


$sql = 'SELECT 
    p.rowid AS product_id,
    p.ref,
    p.label,
    p2.supplier_ref,
    p2.fk_soc_fournisseur AS fourn_id,
	p2.replenish_batch_ddm_delay,
	p2.replenish_service_level,
	p2.replenish_analysis_days,
	p2.replenish_safety_stock,
	p2.replenish_reorder_point,
    
    SUM(COALESCE(ds.total_qty, 0)) / '.$analyse_days_nb.' AS d,
    
    STDDEV_POP(COALESCE(ds.total_qty, 0)) AS sigma_d,

    COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / '.$analyse_days_nb.' AS freq,

    STDDEV_POP(COALESCE(ds.total_qty, 0)) 
        * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / '.$analyse_days_nb.') 
        AS sigma_corrected,
    
    IF (s2.reception_delay>0, s2.reception_delay, 10) AS L,
    IF (s2.replenish_delay>0, s2.replenish_delay, 28) AS T,
    IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) AS Z,
    
    ROUND(
        IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
        (
            STDDEV_POP(COALESCE(ds.total_qty, 0)) 
            * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / '.$analyse_days_nb.')
        )
    , 2) AS safety_stock,
    
    ROUND(
        (SUM(COALESCE(ds.total_qty, 0)) / '.$analyse_days_nb.') * IF (s2.reception_delay>0, s2.reception_delay, 10)
        +
        IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
        (
            STDDEV_POP(COALESCE(ds.total_qty, 0)) 
            * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / '.$analyse_days_nb.')
        )
    , 2) AS reorder_point,
    
    COALESCE(ps.reel, 0) AS stock_current,
    
    GREATEST(
        ROUND(
            (
                (SUM(COALESCE(ds.total_qty, 0)) / '.$analyse_days_nb.') * (IF (s2.reception_delay>0, s2.reception_delay, 10) + IF (s2.replenish_delay>0, s2.replenish_delay, 28))
                +
                IF (p2.replenish_service_level>0, p2.replenish_service_level, 1.28) * SQRT(IF (s2.reception_delay>0, s2.reception_delay, 10)) *
                (
                    STDDEV_POP(COALESCE(ds.total_qty, 0)) 
                    * SQRT(COUNT(CASE WHEN COALESCE(ds.total_qty, 0) > 0 THEN 1 END) / '.$analyse_days_nb.')
                )
            )
            - COALESCE(ps.reel, 0)
        , 0),
        0
    ) AS qty_to_order

FROM llx_product AS p

LEFT JOIN llx_product_extrafields AS p2
	ON p2.fk_object=p.rowid

LEFT JOIN llx_product_stock AS ps
    ON ps.fk_product = p.rowid

LEFT JOIN llx_societe AS s
	ON s.rowid=p2.fk_soc_fournisseur

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
        DATE(c.date_commande) as day,
        cl.fk_product,
        SUM(cl.qty) AS total_qty
    FROM llx_commandedet cl
    JOIN llx_commande c ON c.rowid = cl.fk_commande
    WHERE c.date_commande >= DATE_SUB(CURDATE(), INTERVAL '.$analyse_days_nb.' DAY)
      AND c.fk_statut IN (1,2,3)
    GROUP BY day, cl.fk_product
) AS ds 
ON ds.day = d.day AND ds.fk_product = p.rowid

WHERE p.fk_product_type=0
'.($filter_supplier_id ?' AND p2.fk_soc_fournisseur='.$filter_supplier_id : '').'
GROUP BY p.rowid

HAVING SUM(COALESCE(ds.total_qty, 0)) > 0';

$resql = $db->query($sql);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list[$row->product_id] = $row;
	}
	$db->free($resql);
}

// Suppliers

$list_supplier = [];
$sql = 'SELECT DISTINCT s.rowid, s.nom'
	.' FROM llx_societe AS s'
	.' INNER JOIN llx_product_extrafields AS p2 ON p2.fk_soc_fournisseur=s.rowid'
	.($filter_product_pro ?' LEFT JOIN llx_categorie_product AS kp ON kp.fk_product=p2.fk_object' :'')
	.' WHERE 1'
	.($filter_product_pro ?' AND kp.fk_categorie='.$fk_product_categorie :'')
	.' ORDER BY s.nom ASC';
$resql = $db->query($sql);
//echo '<pre>'.$sql.'</pre>'; var_dump($resql, $db->lastqueryerror, $db->lasterror);
if ($resql) {
	while($row = $db->fetch_object($resql)) {
		$list_supplier[$row->rowid] = $row;
	}
	$db->free($resql);
}
//var_dump($list_supplier);

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
echo '<label for="filter_month_nb">Nombre de mois à analyser :</label> ';
echo '<input name="filter_month_nb" id="filter_month_nb" value="'.$filter_month_nb.'" size="2" onchange="this.form.submit();" />';
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
echo '</form>';

// Colonnes

$resql = $db->query($sql);
echo '<table class="noborder" width="100%">';
echo '<thead>';
echo '<tr class="liste_titre">';
$fields = [
	'ref' => ['label'=>'Réf', 'sortable'=>true],
	'supplier_ref' => ['label'=>'Réf fourn', 'sortable'=>true],
	'fourn_id' => ['label'=>'Fournisseur', 'sortable'=>false],
	'label' => ['label'=>'Nom', 'sortable'=>true],

	'd' => ['label'=>'d',],
	'sigma_d' => ['label'=>'sigma_d',],
	'freq' => ['label'=>'freq',],
	'sigma_corrected' => ['label'=>'sigma_corrected',],

	'L' => ['label'=>'L', 'sortable'=>true],
	'T' => ['label'=>'T', 'sortable'=>true],
	'Z' => ['label'=>'Z', 'sortable'=>true],

	'safety_stock' => ['label'=>'safety stock', 'sortable'=>true],
	'reorder_point' => ['label'=>'reorder point', 'sortable'=>true],
	'stock_current' => ['label'=>'stock', 'sortable'=>true],
	'qty_to_order' => ['label'=>'Commander', 'sortable'=>true],

	'lots_nb' => ['label'=>'Lots', 'align'=>'right', 'sortable'=>true],
	//'cust_nb' => ['label'=>'Nb clients', 'align'=>'right'],
	'cmd_qte' => ['label'=>'Qty cmd', 'align'=>'right', 'sortable'=>true],
	'cmd_nb' => ['label'=>'Cmd', 'sortable'=>true],
];
foreach($fields as $fieldname=>$field) {
	echo '<th>'
		.$field['label']
		.($field['sortable'] ?'&nbsp;<a href="'.$url_params_str.'&sort='.$fieldname.'&sortorder=0"'.(($sort===$fieldname && $sortorder===0) ?' class="active"' :'').'>&#8595;</a>&nbsp;<a href="'.$url_params_str.'&sort='.$fieldname.'&sortorder=1"'.(($sort===$fieldname && $sortorder===1) ?' class="active"' :'').'>&#8593;</a>' :'')
		.'</th>';
}
echo '</tr>';
echo '</thead>';

//var_dump($list);

// Liste

if (!empty($list)) {
	echo '<tbody>';
	foreach($list as $row) {
		if (empty($row->cmd_qte) && !empty($show_product_cmd_only))
			continue;
		if(!empty($filter_supplier_id) && $row->fourn_id != $filter_supplier_id)
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
		echo '<td>'.$list_supplier[$row->fourn_id]->nom.'</td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.$row->product_id.'" target="_blank">'.$row->label.'</td>';

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
		echo '<td align="right">'.round($row->qty_to_order, 2).'</td>';

		echo '<td>'.implode('<br />', $lots).'</td>';
		//echo '<td align="right">'.$row->cust_nb.'</td>';
		echo '<td align="right">'.$row->cmd_qte.'</td>';
		echo '<td align="right">'.$row->cmd_nb.'</td>';
		//echo '<td><div class="hidden2">'.implode('<br />', $row->cmd_list).'</div></td>';
		//echo '<td>'.implode('<br />', $cust_list).'</td>';
		if (!empty($row->customers) && !empty($show_customers_detail)) {
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
tr.liste_titre th a {
	font-weight: bold;
	border: 2px solid transparent;
	padding: 1px 4px;
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

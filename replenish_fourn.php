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
dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

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

$regen = GETPOSTINT('regen');

// Fraîcheur des stats : le calcul ne voit que les ventes présentes dans llx_product_stats.
// Si le cron ne tourne pas, la fenêtre d'analyse se vide et la régénération n'écrit rien (RM3491).
$stats_last_date = MMI_Product_Replenish::statsLastDate();
$stats_stale = MMI_Product_Replenish::statsAreStale();

// Régénération des seuils : même méthode que le cron et le recalcul par paquets
// (plus de boucle UPDATE dupliquée dans la page — RM3491). Respecte l'override manuel,
// ignore les quantités NULL et remet à zéro les produits sans vente (sauf stats périmées).
if ($regen) {
	$regen_n = MMI_Product_Replenish::seuils_refresh([
		'analyse_days_nb' => $analyse_days_nb,
		'filter_supplier_id' => $filter_supplier_id,
		'filter_product_pro' => $filter_product_pro,
		'fk_product_categorie' => $fk_product_categorie,
	]);
	$r = MMI_Product_Replenish::$last_refresh;
	if ($regen_n < 0) {
		setEventMessages('Erreur SQL lors du recalcul des seuils (voir syslog)', null, 'errors');
	}
	elseif (empty($r['computed'])) {
		setEventMessages('Aucun produit vendu sur la fenêtre d\'analyse : aucun seuil recalculé. Dernières stats connues : '
			.($r['stats_last_date'] ? dol_print_date(strtotime($r['stats_last_date']), 'day') : 'aucune')
			.' — lancez « Recalculer stats + seuils (par paquets) ».', null, 'warnings');
	}
	else {
		setEventMessages('Seuils recalculés : '.(int) $r['updated'].' produit(s) mis à jour, '.(int) $r['reset'].' produit(s) sans vente remis à zéro, '
			.(int) $r['skipped_manual'].' exclu(s) (ajustés manuellement), '.(int) $r['skipped_null'].' ignoré(s) (calcul impossible)'
			.(!empty($r['stale']) ? ' — stats périmées : remise à zéro des produits sans vente sautée' : ''), null, 'mesgs');
	}
}


/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans($page_name), $help_url);

print load_fiche_titre($langs->trans($page_name), '', 'title_setup');

// Bandeau de fraîcheur des stats (RM3491)
echo '<div class="'.($stats_stale ? 'warning' : 'info').'" style="margin-bottom: 6px;">'
	.'Ventes connues jusqu\'au <b>'.($stats_last_date ? dol_print_date(strtotime($stats_last_date), 'day') : 'aucune').'</b>'
	.($stats_stale ? ' — <b>stats périmées</b> : le calcul des seuils ne voit pas les ventes récentes. Lancez « Recalculer stats + seuils (par paquets) » et vérifiez que le cron « '.$langs->trans('MMIProductReplenishRefreshCron').' » est actif.' : '')
	.' · plancher demande : '.MMI_Product_Replenish::minStockDays().' jours de stock'
	.'</div>';

echo '<div style="float: right;"><p><a href="'.$url_params_str.'&sort='.$sort.'&sortorder='.$sortorder.'&regen=1">Régénérer les stats en base de donnée</a></p></div>';

// Recalcul complet (stats + seuils) par paquets en AJAX, avec barre de progression.
// Évite les timeouts web sur l'ensemble du catalogue (RM1864).
$ajax_url = dol_buildpath('/custom/mmiproduct/ajax.php', 1);
echo '<div id="replenish-batch" style="clear: right; float: right; text-align: right; margin: 4px 0;">';
echo '<button type="button" id="replenish-batch-btn" class="button">Recalculer stats + seuils (par paquets)</button>';
echo '<div id="replenish-batch-progress" style="display: none; margin-top: 6px;">';
echo '<div id="replenish-batch-phase"></div>';
echo '<progress id="replenish-batch-bar" value="0" max="100" style="width: 260px; vertical-align: middle;"></progress> ';
echo '<span id="replenish-batch-text"></span>';
echo '</div>';
echo '</div>';

echo "\n<script>\n"
	.'(function(){'."\n"
	.'	var btn = document.getElementById("replenish-batch-btn"); if (!btn) return;'."\n"
	.'	var url = "'.dol_escape_js($ajax_url).'";'."\n"
	.'	var size = 50;'."\n"
	.'	var acc = {updated: 0, reset: 0, skipped_manual: 0};'."\n"
	.'	var params = {'
		.' fk_soc_fournisseur: '.(int) $filter_supplier_id.','
		.' filter_product_pro: '.($filter_product_pro ? 1 : 0).','
		.' analyse_days_nb: '.(int) $analyse_days_nb
	.' };'."\n"
	.'	var box = document.getElementById("replenish-batch-progress");'."\n"
	.'	var phaseEl = document.getElementById("replenish-batch-phase");'."\n"
	.'	var bar = document.getElementById("replenish-batch-bar");'."\n"
	.'	var txt = document.getElementById("replenish-batch-text");'."\n"
	.'	function call(step, offset){'."\n"
	.'		var body = new URLSearchParams();'."\n"
	.'		body.set("action", "replenish_refresh");'."\n"
	.'		body.set("step", step);'."\n"
	.'		body.set("offset", offset);'."\n"
	.'		body.set("size", size);'."\n"
	.'		body.set("fk_soc_fournisseur", params.fk_soc_fournisseur);'."\n"
	.'		body.set("filter_product_pro", params.filter_product_pro);'."\n"
	.'		body.set("analyse_days_nb", params.analyse_days_nb);'."\n"
	.'		body.set("acc_updated", acc.updated); body.set("acc_reset", acc.reset); body.set("acc_skipped_manual", acc.skipped_manual);'."\n"
	.'		return fetch(url, {method: "POST", headers: {"Content-Type": "application/x-www-form-urlencoded"}, body: body.toString()}).then(function(r){ return r.json(); });'."\n"
	.'	}'."\n"
	.'	function runPhase(step, label, baseFrac, spanFrac){'."\n"
	.'		return new Promise(function(resolve, reject){'."\n"
	.'			var offset = 0;'."\n"
	.'			(function nextBatch(){'."\n"
	.'				call(step, offset).then(function(res){'."\n"
	.'					if (!res || !res.r){ reject((res && res.msg) ? res.msg : "Erreur serveur"); return; }'."\n"
	.'					if (res.refresh){ acc.updated = res.refresh.updated_total; acc.reset = res.refresh.reset_total; acc.skipped_manual = res.refresh.skipped_manual_total; }'."\n"
	.'					var doneCount = Math.min(res.offset + res.processed, res.total);'."\n"
	.'					var frac = res.total ? (doneCount / res.total) : 1;'."\n"
	.'					bar.value = Math.round((baseFrac + spanFrac * frac) * 100);'."\n"
	.'					phaseEl.textContent = label;'."\n"
	.'					txt.textContent = " " + doneCount + " / " + res.total;'."\n"
	.'					if (res.done){ resolve(res); } else { offset = res.next_offset; nextBatch(); }'."\n"
	.'				}).catch(reject);'."\n"
	.'			})();'."\n"
	.'		});'."\n"
	.'	}'."\n"
	.'	btn.addEventListener("click", function(){'."\n"
	.'		btn.disabled = true; box.style.display = "block"; bar.value = 0;'."\n"
	.'		runPhase("stats", "Étape 1/2 : recalcul des stats…", 0, 0.5)'."\n"
	.'			.then(function(){ return runPhase("seuils", "Étape 2/2 : recalcul des seuils…", 0.5, 0.5); })'."\n"
	.'			.then(function(res){'."\n"
	.'				bar.value = 100; txt.textContent = "";'."\n"
	.'				var rf = res.refresh || {}; phaseEl.textContent = "Terminé ✓" + (res.updated !== null ? " (" + (rf.updated_total || res.updated) + " seuils mis à jour, " + (rf.reset_total || 0) + " produits sans vente remis à zéro, " + (rf.skipped_manual_total || 0) + " exclus manuels)" : "");'."\n"
	.'				var a = document.createElement("a"); a.href = window.location.href; a.textContent = "Recharger la page"; a.style.marginLeft = "8px";'."\n"
	.'				phaseEl.appendChild(a); btn.disabled = false;'."\n"
	.'			})'."\n"
	.'			.catch(function(err){ phaseEl.textContent = "Erreur : " + err; btn.disabled = false; });'."\n"
	.'	});'."\n"
	.'})();'."\n"
	."</script>\n";

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

// Products

$list = [];

// Requête EOQ de calcul des seuils, factorisée et partagée avec le cron
// (MMI_Product_Replenish::seuils_refresh). Voir buildSeuilsQuery() pour le détail.
$sql = MMI_Product_Replenish::buildSeuilsQuery([
	'analyse_days_nb' => $analyse_days_nb,
	'filter_supplier_id' => $filter_supplier_id,
	'filter_product_pro' => $filter_product_pro,
	'fk_product_categorie' => $fk_product_categorie,
]);

// Fields list
$fields = [
	'ref' => ['label'=>'Réf', 'sortable'=>true],
	'supplier_ref' => ['label'=>'Réf fourn', 'sortable'=>true],
	'supplier_id' => ['label'=>'Fournisseur', 'sortable'=>false],
	'label' => ['label'=>'Nom', 'sortable'=>true],

	'stock_days' => ['label'=>'days', 'desc'=>'Jours avec stock sur la fenêtre'],
	'calc_days' => ['label'=>'calc', 'desc'=>'Jours retenus pour la demande (plancher '.MMI_Product_Replenish::minStockDays().' j, ou fenêtre entière si jamais en stock)'],
	'd' => ['label'=>'d', 'desc'=>'Vente moyenne'],
	'sigma_d' => ['label'=>'σd', 'desc'=>'Dispersion Vente moyenne'],
	'freq' => ['label'=>'freq', 'desc'=>'Fréquence vente moyenne'],
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
		// @todo calcul réel du stock théorique (étape 3 RM1444) — pour l'instant = stock réel
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

	}
	$db->free($resql);
}
else {
	dol_print_error($db);
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


// Liste

if (!empty($list)) {
	echo '<tbody>';
	foreach($list as $row) {
		if (empty($row->cmd_nb) && !empty($filter_product_cmd_only))
			continue;
		if(!empty($filter_supplier_id) && $row->supplier_id != $filter_supplier_id)
			continue;

		$lots = [];
		foreach(explode("\n", $row->lots_list ?? '') as $lot) {
			if (empty($lot))
				continue;
			$lot = explode(';', $lot);
			$lots[] = '<span title="'.$lot[1].'">'.$lot[0].'</span>&nbsp;:&nbsp;'.$lot[2];
		}

		// Display
		echo '<tr class="product">';
		echo '<td>'.$row->ref.'</td>';
		echo '<td>'.$row->supplier_ref.'</td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$row->supplier_id.'" target="_blank">'.$list_supplier[$row->supplier_id]->nom.'</a></td>';
		echo '<td><a href="'.DOL_URL_ROOT.'/product/stock/product.php?id='.$row->product_id.'" target="_blank">'.$row->label.'</td>';

		echo '<td align="right">'.round($row->stock_days, 2).'</td>';
		echo '<td align="right">'.round($row->d, 2).'</td>';
		echo '<td align="right">'.round($row->sigma_d, 2).'</td>';
		echo '<td align="right">'.round($row->freq, 2).'</td>';
		echo '<td align="right">'.round($row->sigma_corrected, 2).'</td>';

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
		echo '<td align="right">'.$row->cmd_qty.'</td>';
		echo '<td align="right">'.$row->cmd_nb.'</td>';
		echo '</tr>';
	}
	echo '</tbody>';
}
echo '</table>';

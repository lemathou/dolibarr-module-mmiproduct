<style>
#pricemargin caption {
	text-align: left;
	font-weight: bold;
}
#pricemargin .price, #pricemargin .price input, #pricemargin .price select {
	text-align: right;
}
</style>
<script>
var fk_product = <?php echo $object->id; ?>;
var calc_type;
// Revient
var revient_price;
// Actual
var actual_price;
var actual_margin_coeff;
var actual_min_price;
var actual_min_margin_coeff;
// Public
var public_price;
// Concurrent
var concurrent_price;
// Fourn
var fourn_unitprice;
var fourn_margin_coeff;
var fourn_margin_min_coeff;
// Categ
var categ_margin_coeff;
var categ_margin_min_coeff;
// prix de vente
var sell_price;
var sell_margin_coeff;
var sell_margin_tx_marge;
var sell_margin_tx_maque;
// Prix de vente mini
var sell_min_price;
var sell_min_coeff;
var sell_min_tx_marge;
var sell_min_tx_marque;

$(document).ready(function() {
	revient_price = parseFloat($('#revient_price input').val());
	actual_price = parseFloat($('#actual_price').data('value'));
	actual_margin_coeff = parseFloat($('#actual_margin_coeff').data('value'));
	actual_min_price = parseFloat($('#actual_min_price').data('value'));
	actual_min_margin_coeff = parseFloat($('#actual_min_margin_coeff').data('value'));
	public_price = parseFloat($('#public_price').data('value'));
	concurrent_price = parseFloat($('#concurrent_price').data('value'));
	categ_margin_coeff = parseFloat($('#categ option:selected').data('value'));
	calc_type = $('#calc_type').val();

	$('#calc_type').change(function() {
		calc_type = $(this).val();
		calc_price();
	});
	$('#sell_price input').keyup(function() {
		$('#calc_type').val('sell_price');
	}).change(function() {
		sell_price = $(this).val();
		calc_margin();
	});
	$('#sell_min_coeff input').change(function() {
		sell_min_coeff = $(this).val();
		calc_margin();
	});
	$('#categ select').change(function() {
		categ_margin_coeff = parseFloat($('option:selected', this).data('coeff'));
		categ_margin_tx_marge = 100*(categ_margin_coeff-1);
		categ_margin_tx_marque = 100*(categ_margin_coeff-1)/categ_margin_coeff;
		$('#categ_margin_coeff').text(num_round(categ_margin_coeff));
		$('#categ_margin_tx_marge').text(num_round(categ_margin_tx_marge)+'%');
		$('#categ_margin_tx_marque').text(num_round(categ_margin_tx_marque)+'%');

		categ_margin_min_coeff = parseFloat($('option:selected', this).data('min_coeff'));
		categ_margin_min_tx_marge = 100*(categ_margin_min_coeff-1);
		categ_margin_min_tx_marque = 100*(categ_margin_min_coeff-1)/categ_margin_min_coeff;
		$('#categ_margin_min_coeff').text(categ_margin_min_coeff>0 ?num_round(categ_margin_min_coeff) :'');
		$('#categ_margin_min_tx_marge').text(categ_margin_min_coeff>0 ?num_round(categ_margin_min_tx_marge)+'%' :'');
		$('#categ_margin_min_tx_marque').text(categ_margin_min_coeff>0 ?num_round(categ_margin_min_tx_marque)+'%' :'');

		// Lien
		$('#categ_update_link').attr('href', '/categories/viewcat.php?id='+$(this).val()+'&type=0');

		calc_price();
	});
	$('#fourn select').change(function() {
		// public
		fourn_unitprice = $('option:selected', this).data('unitprice');
		$('#fourn_unitprice').data('value', fourn_unitprice).text(num_round(fourn_unitprice));
		fourn_unitprice = parseFloat(fourn_unitprice);
		// Remise
		fourn_remise_percent = $('option:selected', this).data('remise_percent');
		$('#fourn_remise_percent').data('value', fourn_remise_percent).text(num_round(fourn_remise_percent));
		fourn_remise_percent = parseFloat(fourn_remise_percent);
		// Unit Remisé
		fourn_unitprice_remise = fourn_unitprice*(1-fourn_remise_percent/100);
		$('#fourn_unitprice_remise').data('value', fourn_unitprice_remise).text(num_round(fourn_unitprice_remise));
		// Shipping
		fourn_shipping_price = $('option:selected', this).data('shipping_price');
		if (fourn_shipping_price=='')
			fourn_shipping_price = 0;
		$('#fourn_shipping_price').data('value', fourn_shipping_price).text(num_round(fourn_shipping_price));
		fourn_shipping_price = parseFloat(fourn_shipping_price);
		// Revient
		revient_price = fourn_unitprice_remise + fourn_shipping_price;
		$('#revient_price input').val(revient_price);
		// Coeff
		fourn_margin_coeff = $('option:selected', this).data('fourn_margin_coeff');
		$('#fourn_margin_coeff').data('value', fourn_margin_coeff).text(fourn_margin_coeff);
		$('#fourn_margin_tx_marge').text(fourn_margin_coeff>0 ?num_round(100*(fourn_margin_coeff-1))+' %' :'');
		$('#fourn_margin_tx_marque').text(fourn_margin_coeff>0 ?num_round(100*(fourn_margin_coeff-1)/fourn_margin_coeff)+' %' :'');
		fourn_margin_min_coeff = $('option:selected', this).data('fourn_margin_min_coeff');
		$('#fourn_margin_min_coeff').data('value', fourn_margin_min_coeff).text(fourn_margin_min_coeff);
		$('#fourn_margin_min_tx_marge').text(fourn_margin_min_coeff>0 ?num_round(100*(fourn_margin_min_coeff-1))+' %' :'');
		$('#fourn_margin_min_tx_marque').text(fourn_margin_min_coeff >0 ?num_round(100*(fourn_margin_min_coeff-1)/fourn_margin_min_coeff)+' %' :'');

		// Lien
		$('#fourn_price_update_link').attr('href', '/product/fournisseurs.php?id='+fk_product+'&socid='+$('option:selected', this).data('fk_soc')+'&action=update_price&rowid='+$(this).val());
		$('#fourn_update_link').attr('href', '/societe/card.php?socid='+$('option:selected', this).data('fk_soc'));

		calc_price();
	});

	// Actions
	$('#fourn select').change();
	$('#categ select').change();
});

/**
 * To avoid problems with MacOS...
 */
function num_parse(number_string)
{
	if (typeof number_string === 'string')
		return parseFloat(number_string.replace(',', '.'))
	if (isNaN(number_string))
		return 0;
	return number_string;
}

function num_round(number)
{
	console.log(number);
	return Math.round(number*100)/100;
}

function calc_price()
{
	if (calc_type=='public_price') {
		sell_price = public_price;
	}
	else if (calc_type=='four_margin_coeff') {
		sell_price = revient_price*fourn_margin_coeff;
		sell_min_coeff = fourn_margin_min_coeff;
		$('#sell_min_coeff input').val(num_round(sell_min_coeff));
	}
	else if (calc_type=='concurrent') {
		sell_price = concurrent_price;
	}
	else if (calc_type=='category_margin') {
		sell_price = revient_price*categ_margin_coeff;
		sell_min_coeff = categ_margin_min_coeff;
		$('#sell_min_coeff input').val(num_round(sell_min_coeff));
	}
	else if (calc_type=='sell_price') {
		if (sell_price == undefined || sell_price == NaN || sell_price == '') {
			sell_price = actual_price;
		}
		if (sell_min_coeff == undefined || sell_min_coeff == NaN || sell_min_coeff == '') {
			sell_min_coeff = actual_min_margin_coeff;
			$('#sell_min_coeff input').val(sell_min_coeff);
		}
	}
	//alert(sell_min_coeff);

	$('#calc_sell_price .calc_price').text(num_round(actual_price));
	$('#calc_sell_price .calc_margin_coeff').text(num_round(actual_price/revient_price));
	$('#calc_sell_price .calc_margin_tx_marge').text(num_round(100*(actual_price-revient_price)/revient_price)+' %');
	$('#calc_sell_price .calc_margin_tx_marque').text(num_round(100*(actual_price-revient_price)/actual_price)+' %')
	$('#calc_sell_price .calc_min_price').text(num_round(actual_min_price));
	$('#calc_sell_price .calc_margin_min_coeff').text(num_round(actual_min_price/revient_price));
	$('#calc_sell_price .calc_margin_min_tx_marge').text(num_round(100*(actual_min_price-revient_price)/revient_price)+' %');
	$('#calc_sell_price .calc_margin_min_tx_marque').text(num_round(100*(actual_min_price-revient_price)/actual_min_price)+' %')

	$('#calc_public_price .calc_price').text(public_price>0 ?num_round(public_price) :'');
	$('#calc_public_price .calc_margin_coeff').text(public_price>0 ?num_round(public_price/revient_price) :'');
	$('#calc_public_price .calc_margin_tx_marge').text(public_price>0 ?num_round(100*(public_price-revient_price)/revient_price)+' %' :'');
	$('#calc_public_price .calc_margin_tx_marque').text(public_price>0 ?num_round(100*(public_price-revient_price)/public_price)+' %' :'')

	$('#calc_four_margin_coeff .calc_price').text(num_round(revient_price*fourn_margin_coeff));
	$('#calc_four_margin_coeff .calc_margin_coeff').text(num_round(fourn_margin_coeff));
	$('#calc_four_margin_coeff .calc_margin_tx_marge').text(num_round(100*(fourn_margin_coeff-1))+' %');
	$('#calc_four_margin_coeff .calc_margin_tx_marque').text(num_round(100*(fourn_margin_coeff-1)/fourn_margin_coeff)+' %');
	$('#calc_four_margin_coeff .calc_min_price').text(num_round(revient_price*fourn_margin_min_coeff));
	$('#calc_four_margin_coeff .calc_margin_min_coeff').text(num_round(fourn_margin_min_coeff));
	$('#calc_four_margin_coeff .calc_margin_min_tx_marge').text(num_round(100*(fourn_margin_min_coeff-1))+' %');
	$('#calc_four_margin_coeff .calc_margin_min_tx_marque').text(num_round(100*(fourn_margin_min_coeff-1)/fourn_margin_min_coeff)+' %');

	$('#calc_concurrent .calc_price').text(num_round(concurrent_price));
	$('#calc_concurrent .calc_margin_coeff').text(num_round(concurrent_price/revient_price));
	$('#calc_concurrent .calc_margin_tx_marge').text(num_round(100*(concurrent_price-revient_price)/revient_price)+' %');
	$('#calc_concurrent .calc_margin_tx_marque').text(num_round(100*(concurrent_price-revient_price)/concurrent_price)+' %');

	$('#calc_category_margin .calc_price').text(num_round(revient_price*categ_margin_coeff));
	$('#calc_category_margin .calc_margin_coeff').text(num_round(categ_margin_coeff));
	$('#calc_category_margin .calc_margin_tx_marge').text(num_round(100*(categ_margin_coeff-1))+' %');
	$('#calc_category_margin .calc_margin_tx_marque').text(num_round(100*(categ_margin_coeff-1)/categ_margin_coeff)+' %');
	$('#calc_category_margin .calc_min_price').text(categ_margin_min_coeff>0 ?num_round(revient_price*categ_margin_min_coeff) :'');
	$('#calc_category_margin .calc_margin_min_coeff').text(categ_margin_min_coeff>0 ?num_round(categ_margin_min_coeff) :'');
	$('#calc_category_margin .calc_margin_min_tx_marge').text(categ_margin_min_coeff>0 ?num_round(100*(categ_margin_min_coeff-1))+' %' :'');
	$('#calc_category_margin .calc_margin_min_tx_marque').text(categ_margin_min_coeff>0 ?num_round(100*(categ_margin_min_coeff-1)/categ_margin_min_coeff)+' %' :'');

	$('#sell_price input').val(num_round(sell_price)).change();
	//alert(sell_min_coeff);
}

function calc_margin()
{
	sell_coeff = sell_price/revient_price;
	sell_tx_marge = 100*(sell_price-revient_price)/revient_price;
	sell_tx_marque = 100*(sell_price-revient_price)/sell_price;
	$('#sell_coeff input').val(num_round(sell_coeff));
	$('#sell_tx_marge').text((sell_tx_marge>0 ?'+' :'')+num_round(sell_tx_marge)+'%');
	$('#sell_tx_marque').text(num_round(sell_tx_marque)+'%');

	sell_min_coeff = parseFloat($('#sell_min_coeff input').val());
	sell_min_tx_marge = sell_min_coeff>0 ?100*(sell_min_coeff-1) :'';
	sell_min_tx_marque = sell_min_coeff>0 ?100*(sell_min_coeff-1)/sell_min_coeff :'';
	sell_min_price = revient_price*sell_min_coeff;
	//$('#sell_coeff input').val(num_round(sell_coeff));
	$('#sell_min_price input').val(num_round(sell_min_price));
	$('#sell_min_tx_marge').text((sell_min_tx_marge>0 ?'+' :'')+num_round(sell_min_tx_marge)+'%');
	$('#sell_min_tx_marque').text(num_round(sell_min_tx_marque)+'%');
}
</script>
<div>
<?php
$calc_type_list = [
	'sell_price' => ['label'=>'Prix final fixé'],
	'public_price' => ['label'=>'Prix public fournisseur fixé'],
	'four_margin_coeff' => ['label'=>'Coeff/Marge fournisseur fixée'],
	'concurrent' => ['label'=>'Prix similaire à la concurrence'],
	'category_margin' => ['label'=>'Marge définie par la catégorie'],
	//'' => ['label'=>''],
];
$margin_calc_type = $object->array_options['options_margin_calc_type'];

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
$product_fourn_static = new ProductFournisseur($db);
$product_fourn_list = $product_fourn_static->list_product_fournisseur_price($object->id, '', '');
//var_dump($product_fourn_list);
$fourn_list = [];
$product_fourn = NULL;
$product_fourn_extra = NULL;
$product_fourn_list_extra = [];
foreach($product_fourn_list as $pf) {
	if (empty($fourn_list[$pf->fourn_id])) {
		$fourn_list[$pf->fourn_id] = new Fournisseur($db);
		$fourn_list[$pf->fourn_id]->fetch($pf->fourn_id);
	}
	//var_dump($product_fourn);
	$sql = 'SELECT *';
	$sql .= " FROM ".MAIN_DB_PREFIX."product_fournisseur_price_extrafields";
	$sql .= " WHERE fk_object = ".((int) $pf->product_fourn_price_id);
	$q = $db->query($sql);
	if($pfe=$db->fetch_object($q))
		$product_fourn_list_extra[$pf->product_fourn_price_id] = $pfe;
	//var_dump($product_fourn_extra);
	//var_dump($object->array_options['options_fk_soc_fournisseur'], $pf);
	if (!empty($product_fourn))
		continue;
	if (!empty($object->array_options['options_fk_soc_fournisseur']) && $pf->fourn_id != $object->array_options['options_fk_soc_fournisseur'])
		continue;
	
	$product_fourn = $pf;
	break;
}
$revient = $product_fourn->fourn_unitprice*(1-$product_fourn->fourn_remise_percent/100) + $product_fourn_list_extra[$product_fourn->product_fourn_price_id]->shipping_price;

//var_dump($object);
?>
<h3>Assistance calcul de marge</h3>
<form method="POST" action="">
<input name="action" type="hidden" value="margin_calc_update" />
<table id="pricemargin">
	<thead>

	</thead>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Prix public conseillé :</h4>
		</td>
	</tr>
	<tr>
		<td>Prix public conseillé :</td>
		<td><span id="public_price" data-value="<?php echo $public_price ?>"><?php echo number_format($public_price, 2, ',', ' '); ?></span></td>
	</tr>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Prix d'achat fournisseur</h4>
		</td>
	</tr>
	<tr>
		<td>Prix d'achat fournisseur utilisé :</td>
		<td class="price" id="fourn"><select name="product_fourn_price_id"><?php foreach($product_fourn_list as $pf)
			echo '<option value="'.$pf->product_fourn_price_id.'"'.($product_fourn && $product_fourn->product_fourn_price_id==$pf->product_fourn_price_id ?' selected' :'').' data-unitprice="'.$pf->fourn_unitprice.'" data-fk_soc="'.$pf->fourn_id.'" data-remise_percent="'.$pf->fourn_remise_percent.'" data-shipping_price="'.$product_fourn_list_extra[$pf->product_fourn_price_id]->shipping_price.'" data-fourn_margin_coeff="'.$fourn_list[$pf->fourn_id]->array_options['options_margin_coeff'].'" data-fourn_margin_min_coeff="'.$fourn_list[$pf->fourn_id]->array_options['options_margin_min_coeff'].'">'.($pf->fourn_name.' - '.$pf->fourn_ref.' - '.price_format($pf->fourn_unitprice)).'</option>';
		?></select></td>
		<td>
			<a href="javascript:;" id="fourn_price_update_link">Modifier prix</a>
			| <a href="javascript:;" id="fourn_update_link">Modifier fournisseur</a>
		</td>
	</tr>
	<tr>
		<td>Prix public fournisseur :</td>
		<td class="price" id="fourn_unitprice" data-value=""></td>
		<td id="fourn_ispublic"></td>
	</tr>
	<tr>
		<td>Remise fournisseur :</td>
		<td class="price" id="fourn_remise_percent"></td>
	</tr>
	<tr>
		<td>Prix d'achat fournisseur :</td>
		<td class="price" id="fourn_unitprice_remise"></td>
	</tr>
	<tr>
		<td>Frais d'acheminement :</td>
		<td class="price" id="fourn_shipping_price"></td>
	</tr>
	<tr>
		<td>Prix de revient :</td>
		<td class="price" id="revient_price"><input type="text" name="revient_price" size="10" value="" onfocus="this.blur()" /></td>
	</tr>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Calcul par fournisseur</h4>
		</td>
	</tr>
	<tr>
		<td>Coeff de marge fournisseur :</td>
		<td class="price" id="fourn_margin_coeff"></td>
		<td id="is_fourn_margin_coeff" style="display: none;">=> On a un coeff de marge fournisseur (à appliquer à tous ses produits)</td>
	</tr>
	<tr>
		<td>Taux de marge fournisseur :</td>
		<td class="price" id="fourn_margin_tx_marge"></td>
		<td id="is_fourn_margin_tx_marge" style="display: none;"></td>
	</tr>
	<tr>
		<td>Taux de marque fournisseur :</td>
		<td class="price" id="fourn_margin_tx_marque"></td>
		<td id="is_fourn_margin_tx_marque" style="display: none;"></td>
	</tr>
	<tr><td colspan="2"><hr /></td></tr>
	<tr>
		<td>Coeff de marge mini fournisseur :</td>
		<td class="price" id="fourn_margin_min_coeff"></td>
		<td id="is_fourn_margin_min_coeff" style="display: none;">=> On a un coeff de marge fournisseur (à appliquer à tous ses produits)</td>
	</tr>
	<tr>
		<td>Taux de marge mini fournisseur :</td>
		<td class="price" id="fourn_margin_min_tx_marge"></td>
		<td id="is_fourn_margin_min_tx_marge" style="display: none;"></td>
	</tr>
	<tr>
		<td>Taux de marque mini fournisseur :</td>
		<td class="price" id="fourn_margin_min_tx_marque"></td>
		<td id="is_fourn_margin_min_tx_marque" style="display: none;"></td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Calcul par catégorie</h4>
		</td>
	</tr>
	<tr>
		<td>Catégorie :</td>
		<td class="price" id="categ"><select name="fk_categorie_default"><?php
		foreach($categ_list as $cat)
			echo '<option value="'.$cat['rowid'].'" data-coeff="'.$cat['margin_coeff'].'" data-min_coeff="'.$cat['margin_min_coeff'].'"'.($categ && $categ['rowid']==$cat['rowid'] ?' selected' :'').'>'.$cat['label'].'</option>';
		?></select></td>
		<td><a href="javascript:;" id="categ_update_link">Modifier le coeff de marge de la catégorie</a></td>
	</tr>
	<tr>
		<td>Coeff de marge catégorie :</td>
		<td class="price" id="categ_margin_coeff"></td>
		<td id="is_categ_margin_coeff" style="display: none;">=> On a un coeff de marge catégorie</td>
	</tr>
	<tr>
		<td>Taux de marge catégorie :</td>
		<td class="price" id="categ_margin_tx_marge"></td>
		<td id="is_categ_margin_tx_marge" style="display: none;"></td>
	</tr>
	<tr>
		<td>Taux de marque catégorie :</td>
		<td class="price" id="categ_margin_tx_marque"></td>
		<td id="is_categ_margin_tx_marque" style="display: none;"></td>
	</tr>
	<tr>
		<td colspan="2"><hr /></td>
	</tr>
	<tr>
		<td>Coeff min de marge catégorie :</td>
		<td class="price" id="categ_margin_min_coeff"></td>
		<td id="is_categ_margin_min_coeff" style="display: none;">=> On a un coeff de marge catégorie</td>
	</tr>
	<tr>
		<td>Taux min de marge catégorie :</td>
		<td class="price" id="categ_margin_min_tx_marge"></td>
		<td id="is_categ_margin_min_tx_marge" style="display: none;"></td>
	</tr>
	<tr>
		<td>Taux min de marque catégorie :</td>
		<td class="price" id="categ_margin_min_tx_marque"></td>
		<td id="is_categ_margin_min_tx_marque" style="display: none;"></td>
	</tr>
	<tr>
		<td></td>
		<td></td>
		<td><a href="/product/card.php?action=edit&id=<?php echo $object->id; ?>">Modifier les catégories du produit</a><br />
		Faire un tableau récap pour mieux comparer ?<br />
		Si on supprime une catégorie ou modifie le taux d'une catégorie ou modifie les carégories du produit,<br />
		il faut répercuter immédiatement sur le prix de vente des produits concernés.</td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Calcul par concurrents</h4>
		</td>
	</tr>
	<tr>
		<td>Prix concurrent médian :</td>
		<td class="price" id="concurrent_price" data-value="<?php echo $pcp_median; ?>"><?php echo price_format($pcp_median); ?></td>
		<td><a href="/custom/mmiproduct/concurrents.php?id=<?php echo $object->id; ?>">Modifier les prix concurrents</a></td>
	</tr>
	<tr>
		<td>Coeff marge concurrent :</td>
		<td class="price"><?php echo num_format($pcp_median/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marge concurrent :</td>
		<td class="price"><?php echo percent_format(100*($pcp_median-$revient)/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marque concurrent :</td>
		<td class="price"><?php echo percent_format(100*($pcp_median-$revient)/$pcp_median); ?></td>
	</tr>
	<tr>
		<td colspan="2">
		<table colspan="2" border="1">
		<tr>
			<th></th>
			<th>Px vente</th>
			<th>Coeff marge</th>
			<th>Tx marge</th>
			<th>Tx marque</th>
		</tr>
		<tr>
			<td>1er quartile (25% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_quartile_25); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_quartile_25/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_25-$revient)/$revient, 2).'%' :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_25-$revient)/$pcp_quartile_25, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>Prix médian (50% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_median); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_median/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_median-$revient)/$revient, 2).'%' :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_median-$revient)/$pcp_median, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>3ème quartile (75% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_quartile_75); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_quartile_75/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_75-$revient)/$revient, 2).'%' :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_75-$revient)/$pcp_quartile_75, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>Prix moyen :</td>
			<td align="right"><?php echo price_format($pcp_avg); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_avg/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_avg-$revient)/$revient, 2).'%' :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_avg-$revient)/$pcp_avg, 2).'%' :'-'; ?></td>
		</tr>
	</table>
	</td>
		<td>Si on ajoute, supprime, modifie un prix concurrent,<br />
		ou même si un jour passe et qu'un ancien prix n'est plus à prendre en compte dans le calcul du prix concurrent,<br />
		il faut répercuter immédiatement sur le prix de vente des produits concernés.</td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Prix de vente actuel</h4>
		</td>
	</tr>
	<tr>
		<td>Prix de vente actuel :</td>
		<td id="actual_price" data-value="<?php echo $object->price; ?>" class="price"><?php echo price_format($object->price); ?></td>
	</tr>
	<tr>
		<td>Coeff marge actuel :</td>
		<td id="actual_margin_coeff" data-value="<?php echo $object->price/$revient; ?>" class="price"><?php echo num_format($object->price/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marge actuel :</td>
		<td class="price"><?php echo percent_format(100*($object->price-$revient)/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marque actuel :</td>
		<td class="price"><?php echo percent_format(100*($object->price-$revient)/$object->price); ?></td>
	</tr>
	<tr><td colspan="2"><hr /></td></tr>
	<tr>
		<td>Prix de vente mini actuel :</td>
		<td id="actual_min_price" data-value="<?php echo $object->price_min; ?>" class="price"><?php echo price_format($object->price_min); ?></td>
	</tr>
	<tr>
		<td>Coeff marge mini actuel :</td>
		<td id="actual_min_margin_coeff" data-value="<?php echo $object->price_min/$revient; ?>" class="price"><?php echo num_format($object->price_min/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marge mini actuel :</td>
		<td class="price"><?php echo percent_format(100*($object->price_min-$revient)/$revient); ?></td>
	</tr>
	<tr>
		<td>Taux marque mini actuel :</td>
		<td class="price"><?php echo percent_format(100*($object->price_min-$revient)/$object->price_min); ?></td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Comparaison méthodes</h4>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<table border="1">
				<thead>
				<tr>
					<th>Type/Méthode</th>
					<th>Px vente</th>
					<th>Coeff marge</th>
					<th>Tx marge</th>
					<th>Tx marque</th>
					<th>min Px vente</th>
					<th>min Coeff marge</th>
					<th>min Tx marge</th>
					<th>min Tx marque</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach($calc_type_list as $i=>$j) {
					echo '<tr id="calc_'.$i.'">';
					echo '<td>'.$j['label'].'</td>';
					echo '<td class="calc_price price"></td>';
					echo '<td class="calc_margin_coeff price"></td>';
					echo '<td class="calc_margin_tx_marge price"></td>';
					echo '<td class="calc_margin_tx_marque price"></td>';
					echo '<td class="calc_min_price price"></td>';
					echo '<td class="calc_margin_min_coeff price"></td>';
					echo '<td class="calc_margin_min_tx_marge price"></td>';
					echo '<td class="calc_margin_min_tx_marque price"></td>';
					echo '</tr>';
				} ?>
				</tbody>
			</table>
			<p>Voir plus tard pour ajouter l'info spécifique de catégorie, prix fournisseur, etc.</p>
		</td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3">
			<hr />
			<h4>Choix</h4>
		</td>
	</tr>
	<tr>
		<td>Règle de calcul de marge :</td>
		<td class="price"><select id="calc_type" name="margin_calc_type">
			<option value="">---</option>
			<?php foreach($calc_type_list as $i=>$j)
			echo '<option value="'.$i.'"'.($margin_calc_type==$i ?' selected' :'').'>'.$j['label'].'</option>';
			?>
		</select></td>
	</tr>
	<tr>
		<td>Prix de vente :</td>
		<td class="price" id="sell_price" data-value=""><input type="text" name="sell_price" value="" size="10" /></td>
	</tr>
	<tr>
		<td>Coeff marge effective :</td>
		<td class="price" id="sell_coeff" data-value=""><input type="text" name="sell_coeff" value="" size="10" onfocus="this.blur()" /></td>
	</tr>
	<tr>
		<td>Taux marge effective :</td>
		<td class="price" id="sell_tx_marge" data-value=""></td>
	</tr>
	<tr>
		<td>Taux marque effective :</td>
		<td class="price" id="sell_tx_marque" data-value=""></td>
	</tr>
	<tr><td colspan="2"><hr /></td></tr>
	<tr>
		<td>Coeff marge mini :</td>
		<td class="price" id="sell_min_coeff" data-value=""><input type="text" name="sell_min_coeff" value="" size="10" /></td>
	</tr>
	<tr>
		<td>Taux marge mini :</td>
		<td class="price" id="sell_min_tx_marge" data-value=""></td>
	</tr>
	<tr>
		<td>Taux marque mini :</td>
		<td class="price" id="sell_min_tx_marque" data-value=""></td>
	</tr>
	<tr>
		<td>Prix de vente mini :</td>
		<td class="price" id="sell_min_price" data-value=""><input type="text" name="sell_min_price" value="" size="10" onfocus="this.blur()" /></td>
	</tr>
	</tbody>
	<tfoot>
	<tr>
		<td></td>
		<td class="price"><input type="submit" class="button button-save" value="Mettre à jour" /></td>
		<td>Si on switch vers un mode de calcul qui comprend un coeff mini, ça va le mettre à jour. Si on se repositionne sur un mode qui nele prend pas en compte, ça va laisser le dernier qui a été mis en place. Est-ce le bon comportement ?</td>
	</tr>
	</tfoot>
</table>
</form>
</div>

<hr />

<h3>Utilisation du bon prix d'achat</h3>
<p>Les fournisseurs ont des politiques de prix selon plusieurs critères :</p>
<ul>
	<li>Volume acheté => remise produit ou port (voir franco)</li>
	<li>Livraison direct client => surcout mais peut être intéressant si le client habite plus loin de notre dépôt.</li>
</ul>
<p>Selon que le produit est acheté en quantité et stocké au dépôt ou bien livré direct depuis le fournisseur, il peut y avoir un impact de prix.</p>
<p>Le PAMP permet de savoir en direct le coût du produit en stock dans un entrepôt donné.</p>
<p>Au moment de la saisie de la ligne de devis (ou autre document) on peut choisir la méthode de calcul du prix d'achat. Si ça part du dépôt le mieux est de choisir le PAMP, si on fait une commande fournisseur, selon les cas, le prix de revient.</p>

<h3>Marge minimum souhaitée</h3>
<p>On peut dissocier la marge sur le produit et sur le transport.</p>

<h3>Coût de transport moyen pour l'approvisionnement (historique de commande fournisseur)</h3>
<p>Facile à calculer si le produit est toujours stockés chez nous sur un même dépôt.</p>
<p>Sinon il faut bien dissocier les situations pour le calcul.</p>

<h3>Méthode de calcul de la marge</h3>
<p>On peut se donner plusieurs familles de produits, et selon les familles ET les prix, des indications de marge à respecter.</p>
<p>On se basera aussi sur les prix concurrent ! on regardera notre marge en considérant qu'on vend à un certain prix.</p>
<p>Par exemple :</p>
<ul>
	<li>Quincaillerie : < 10€ => coeff > 2, <100€ => coeff > 1.7</li>
	<li>Machines : 1000€ => coeff > 1.35, < 5000€ => coeff > 1.25</li>
	<li>Materiaux : ...</li>
</ul>

<h3>Méthode de calcul</h3>
<p>- Appliquer un pourcentage de remise sur prix public pur tout ou partie des produits du fournisseur (par le biais de filtres)<br />
- Bien prendre en compte du transport via le module spé</p>

<p>- Définit un pourcentage de marge, qui permet de calculer automatiquement le prix de vente à partir du prix de revient</p>

<?php

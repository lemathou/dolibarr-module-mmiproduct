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
var revient_price;
var public_price;
var concurrent_price;
var categ_margin_coeff;
var sell_price;
var sell_margin_coeff;
var sell_margin_tx;
$(document).ready(function() {
	revient_price = parseFloat($('#revient_price').data('value'));
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
	$('#categ select').change(function() {
		categ_margin_coeff = parseFloat($('option:selected', this).data('value'));
		$('#categ_margin_coeff').text(num_round(categ_margin_coeff));

		// Lien
		$('#categ_update_link').attr('href', '/categories/viewcat.php?id='+$(this).val()+'&type=0');

		calc_price();
	});
	$('#fourn select').change(function() {
		// public
		public_price = $('option:selected', this).data('unitprice');
		$('#public_price').data('value', public_price).text(num_round(public_price));
		public_price = parseFloat(public_price);
		// Remise
		fourn_remise_percent = $('option:selected', this).data('remise_percent');
		$('#fourn_remise_percent').data('value', fourn_remise_percent).text(num_round(fourn_remise_percent));
		fourn_remise_percent = parseFloat(fourn_remise_percent);
		// Unit Remisé
		fourn_unitprice_remise = public_price*(1-fourn_remise_percent/100);
		$('#fourn_unitprice_remise').data('value', fourn_unitprice_remise).text(num_round(fourn_unitprice_remise));
		// Shipping
		fourn_shipping_price = $('option:selected', this).data('shipping_price');
		if (fourn_shipping_price=='')
			fourn_shipping_price = 0;
		$('#fourn_shipping_price').data('value', fourn_shipping_price).text(num_round(fourn_shipping_price));
		fourn_shipping_price = parseFloat(fourn_shipping_price);
		// Revient
		revient_price = fourn_unitprice_remise + fourn_shipping_price;
		$('#revient_price').data('value', revient_price).text(num_round(revient_price));

		// Lien
		$('#fourn_price_update_link').attr('href', '/product/fournisseurs.php?id='+fk_product+'&socid='+$('option:selected', this).data('fk_soc')+'&action=update_price&rowid='+$(this).val());

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
	else if (calc_type=='concurrent') {
		sell_price = concurrent_price;
	}
	else if (calc_type=='category_margin') {
		sell_price = revient_price*categ_margin_coeff;
	}
	else {
		//sell_price = 0;
	}

	$('#calc_public_price .calc_price').text(num_round(public_price));
	$('#calc_public_price .calc_margin_coeff').text(num_round(public_price/revient_price));
	$('#calc_public_price .calc_margin_tx').text(num_round(100*(public_price-revient_price)/public_price)+' %');
	$('#calc_concurrent .calc_price').text(num_round(concurrent_price));
	$('#calc_concurrent .calc_margin_coeff').text(num_round(concurrent_price/revient_price));
	$('#calc_concurrent .calc_margin_tx').text(num_round(100*(concurrent_price-revient_price)/concurrent_price)+' %');
	$('#calc_category_margin .calc_price').text(num_round(revient_price*categ_margin_coeff));
	$('#calc_category_margin .calc_margin_coeff').text(num_round(categ_margin_coeff));
	$('#calc_category_margin .calc_margin_tx').text(num_round(100*(categ_margin_coeff-1)/categ_margin_coeff)+' %');

	$('#sell_price input').val(num_round(sell_price)).change();
}

function calc_margin()
{
	sell_margin_coeff = sell_price/revient_price;
	sell_margin_tx = 100*(sell_price-revient_price)/sell_price;
	$('#sell_margin').text(num_round(sell_margin_coeff)+' / '+num_round(sell_margin_tx)+'%');
}
</script>
<div>
<?php
$calc_type_list = [
	'sell_price' => ['label'=>'Prix final fixé'],
	'public_price' => ['label'=>'Prix public fournisseur fixé'],
	'concurrent' => ['label'=>'Prix similaire à la concurrence'],
	'category_margin' => ['label'=>'Marge définie par la catégorie'],
	//'' => ['label'=>''],
];
$margin_calc_type = $object->array_options['options_margin_calc_type'];

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
$product_fourn_static = new ProductFournisseur($db);
$product_fourn_list = $product_fourn_static->list_product_fournisseur_price($object->id, '', '');
//var_dump($product_fourn_list);
$product_fourn = NULL;
$product_fourn_extra = NULL;
$product_fourn_list_extra = [];
foreach($product_fourn_list as $pf) {
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
		<td>Prix d'achat fournisseur utilisé :</td>
		<td class="price" id="fourn"><select name="product_fourn_price_id"><?php foreach($product_fourn_list as $pf)
			echo '<option value="'.$pf->product_fourn_price_id.'"'.($product_fourn && $product_fourn->product_fourn_price_id==$pf->product_fourn_price_id ?' selected' :'').' data-unitprice="'.$pf->fourn_unitprice.'" data-fk_soc="'.$pf->fourn_id.'" data-remise_percent="'.$pf->fourn_remise_percent.'" data-shipping_price="'.$product_fourn_list_extra[$pf->product_fourn_price_id]->shipping_price.'">'.($pf->fourn_name.' - '.$pf->fourn_ref.' - '.price_format($pf->fourn_unitprice)).'</option>';
		?></select></td>
		<td><a href="javascript:;" id="fourn_price_update_link">Modifier le prix</a></td>
	</tr>
	<tr>
		<td>Prix public fournisseur :</td>
		<td class="price" id="public_price" data-value=""></td>
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
		<td class="price" id="revient_price" data-value=""></td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3"><hr /></td>
	</tr>
	<tr>
		<td>Catégorie :</td>
		<td class="price" id="categ"><select name="fk_categorie_default"><?php
		foreach($categ_list as $cat)
			echo '<option value="'.$cat['rowid'].'" data-value="'.$cat['margin_coeff'].'"'.($categ && $categ['rowid']==$cat['rowid'] ?' selected' :'').'>'.$cat['label'].'</option>';
		?></select></td>
		<td><a href="javascript:;" id="categ_update_link">Modifier le taux de marge de la catégorie</a></td>
	</tr>
	<tr>
		<td>Taux de marge catégorie :</td>
		<td class="price" id="categ_margin_coeff"></td>
		<td id="is_categ_margin_coeff" style="display: none;">=> On a un taux de marge catégorie</td>
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
		<td colspan="3"><hr /></td>
	</tr>
	<tr>
		<td>Prix concurrent médian :</td>
		<td class="price" id="concurrent_price" data-value="<?php echo $pcp_median; ?>"><?php echo price_format($pcp_median); ?></td>
		<td><a href="/custom/mmiproduct/concurrents.php?id=<?php echo $object->id; ?>">Modifier les prix concurrents</a></td>
	</tr>
	<tr>
		<td>Marge concurrent (Coeff / Tx Marque) :</td>
		<td class="price"><?php echo num_format($pcp_median/$revient).' / '.percent_format(100*($pcp_median-$revient)/$pcp_median); ?></td>
	</tr>
	<tr>
		<td colspan="2">
		<table colspan="2" border="1">
		<tr>
			<th></th>
			<th>Px vente</th>
			<th>Coeff marge</th>
			<th>Tx marque</th>
		</tr>
		<tr>
			<td>1er quartile (25% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_quartile_25); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_quartile_25/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_25-$revient)/$pcp_quartile_25, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>Prix médian (50% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_median); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_median/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_median-$revient)/$pcp_median, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>3ème quartile (75% du bas) :</td>
			<td align="right"><?php echo price_format($pcp_quartile_75); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_quartile_75/$revient, 2) :'-'; ?></td>
			<td align="right"><?php echo $revient ?round(100*($pcp_quartile_75-$revient)/$pcp_quartile_75, 2).'%' :'-'; ?></td>
		</tr>
		<tr>
			<td>Prix moyen :</td>
			<td align="right"><?php echo price_format($pcp_avg); ?></td>
			<td align="right"><?php echo $revient ?round($pcp_avg/$revient, 2) :'-'; ?></td>
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
		<td colspan="3"><hr /></td>
	</tr>
	<tr>
		<td>Prix de vente actuel :</td>
		<td class="price"><?php echo price_format($object->price); ?></td>
	</tr>
	<tr>
		<td>Marge actuelle (Coeff / Tx Marque) :</td>
		<td class="price"><?php echo num_format($object->price/$revient).' / '.percent_format(100*($object->price-$revient)/$object->price); ?></td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3"><hr /></td>
	</tr>
	<tr>
		<td colspan="2">
			<table border="1">
				<thead>
				<tr>
					<th>Type/Méthode</th>
					<th>Px vente</th>
					<th>Coeff marge</th>
					<th>Tx marque</th>
				</tr>
				</thead>
				<tbody>
				<?php foreach($calc_type_list as $i=>$j) {
					echo '<tr id="calc_'.$i.'">';
					echo '<td>'.$j['label'].'</td>';
					echo '<td class="calc_price price"></td>';
					echo '<td class="calc_margin_coeff price"></td>';
					echo '<td class="calc_margin_tx price"></td>';
					echo '</tr>';
				} ?>
				</tbody>
			</table>
		</td>
		<td>
			<p>Voir plus tard pour ajouter l'info spécifique de catégorie, prix fournisseur, etc.</p>
		</td>
	</tr>
	</tbody>
	<tbody>
	<tr>
		<td colspan="3"><hr /></td>
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
		<td>Marge effective :</td>
		<td class="price" id="sell_margin" data-value=""></td>
	</tr>
	</tbody>
	<tfoot>
	<tr>
		<td></td>
		<td class="price"><input type="submit" class="button button-save" value="Mettre à jour" /></td>
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

<h3></h3>
<?php

<?php
$url_len_disp_limit = 50;
?>
<div>
<h3>Historique des prix de la concurrence</h3>

<div style="float: right;margin: 0 10px;">
	<p><a href="?id=<?php echo $id; ?>&action=pc_add"><span class="fa fa-plus-circle valignmiddle btnTitle-icon""></span> Ajouter une url concurrent</a></p>
	<p><a href="?id=<?php echo $id; ?>&action=pcp_add"><span class="fa fa-plus-circle valignmiddle btnTitle-icon""></span> Ajouter un prix</a></p>
</div>

<?php if ($action=='pcp_add') { ?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pcp_add">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('Competitor'); ?></label></td>
		<td><select name="fk_soc"><option value="">--</option><?php foreach ($pc_list as $r) {
			echo '<option value="'.$r['fk_soc'].'"'.(!empty($fk_soc) && $fk_soc==$r['fk_soc'] ?' selected' :'').'>'.$r['nom'].' - '.$r['url'].'</option>';
		} ?></select></td>
	</tr>
	<tr>
		<td><label for="date"><?php echo $langs->trans('Date'); ?></label></td>
		<td><input name="date" type="date" value="<?php echo $datenow; ?>" /></td>
	</tr>
	<tr>
		<td><label for="qte"><?php echo $langs->trans('Quantity'); ?></label></td>
		<td><input name="qte" value="1" /></td>
	</tr>
	<tr>
		<td><label for="price"><?php echo $langs->trans('Price'); ?></label></td>
		<td><input name="price" value="" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Ajouter un prix concurrent" /></td>
	</tr>
</table></form>
<hr />
<?php } elseif ($action=='pcp_edit' && !empty($pcp_edit) && isset($pcp_list[$pcp_edit])) {
	$pcp = $pcp_list[$pcp_edit];
?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pcp_edit&pcp_edit=<?php echo $pcp_edit; ?>">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('Competitor'); ?></label></td>
		<td><?php echo $pcp['nom']; ?></td>
	</tr>
	<tr>
		<td><label for="date"><?php echo $langs->trans('Date'); ?></label></td>
		<td><input name="date" type="date" value="<?php echo $pcp['date']; ?>" /></td>
	</tr>
	<tr>
		<td><label for="qte"><?php echo $langs->trans('Quantity'); ?></label></td>
		<td><input name="qte" value="<?php echo $pcp['qte']; ?>" /></td>
	</tr>
	<tr>
		<td><label for="price"><?php echo $langs->trans('Price'); ?></label></td>
		<td><input name="price" value="<?php echo $pcp['price']; ?>" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Modifier le prix concurrent" /></td>
	</tr>
</table></form>
<hr />
<?php } elseif ($action=='pc_add') { ?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pc_add">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('Competitor'); ?></label></td>
		<td><select name="fk_soc"><option value="">--</option><?php foreach ($s_list as $r) {
			echo '<option value="'.$r['rowid'].'">'.$r['nom'].'</option>';
		} ?></select></td>
	</tr>
	<tr>
		<td><label for="url"><?php echo $langs->trans('URL'); ?></label></td>
		<td><input name="url" value="" size="64" /></td>
	</tr>
	<tr>
		<td><label for="date"><?php echo $langs->trans('Date'); ?></label></td>
		<td><input name="date" type="date" value="<?php echo $datenow; ?>" /></td>
	</tr>
	<tr>
		<td><label for="qte"><?php echo $langs->trans('Quantity'); ?></label></td>
		<td><input name="qte" value="1" /></td>
	</tr>
	<tr>
		<td><label for="price"><?php echo $langs->trans('Price'); ?></label></td>
		<td><input name="price" value="" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Ajouter l'URL du concurrent (et prix si renseigné)" /></td>
	</tr>
</table></form>
<hr />
<?php } elseif ($action=='pc_edit' && !empty($pc_edit) && isset($pc_list[$pc_edit])) {
	$pc = $pc_list[$pc_edit];
?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pc_edit&pc_edit=<?php echo $pc_edit; ?>">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('Competitor'); ?></label></td>
		<td><?php echo $pc['nom']; ?></td>
	</tr>
	<tr>
		<td><label for="url"><?php echo $langs->trans('URL'); ?></label></td>
		<td><input name="url" value="<?php echo $pc['url']; ?>" size="64" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Modifier le concurrent" /></td>
	</tr>
</table></form>
<hr />
<?php } ?>

<?php
$pachat = NULL;
foreach($pfp_list as $pfp) {
	$pachat = $pfp['unitprice'];
}
?>

<table border="1" cellpadding="4">
	<thead>
	<tr>
		<th></th>
		<th><?php echo $langs->trans('Competitor'); ?></th>
		<th><?php echo $langs->trans('URL'); ?></th>
		<th><?php echo $langs->trans('Date'); ?></th>
		<th><?php echo $langs->trans('Quantity'); ?></th>
		<th><?php echo $langs->trans('Price'); ?></th>
		<th><?php echo $langs->trans('SupplierPrice'); ?></th>
		<th><?php echo $langs->trans('Margin'); ?> Coeff</th>
		<th><?php echo $langs->trans('Margin'); ?> Taux</th>
	</tr>
	</thead>
	<tbody>
	<?php
	$competitor_price_list = [];
	foreach($pcp_list as $row) {
		//var_dump($s_list[$row['fk_soc']]);
		$competitor_price_list[] = $row['price'];
		$margin_coeff = $pachat ?round($row['price']/$pachat, 2) :'-';
		$margin_taux = $pachat ?round(100*($row['price']-$pachat)/$row['price'], 2).'%' :'-';
		echo '<tr>';
		echo '<td><a href="?id='.$id.'&action=pcp_edit&pcp_edit='.$row['rowid'].'"><span class="fas fa-pencil-alt"></span>&nbsp;'.$row['rowid'].'</a></td>';
		echo '<td>'.$row['nom'].'<br /><a href="'.$s_list[$row['fk_soc']]['url'].'" target="_blank">'.$s_list[$row['fk_soc']]['url'].'</a></td>';
		echo '<td>';
		foreach ($pc_list_soc_url[$row['fk_soc']] as $url) {
			$len = strlen($url);
			$cut = $len>$url_len_disp_limit;
			echo '<a href="'.$url.'" target="_blank"'.($cut ?' title="'.$url.'"' :'').'>'.($cut ?substr($url, 0, $url_len_disp_limit).'...' :$url).'</a><br />';
		}
		echo '</td>';
		echo '<td>'.implode('/', array_reverse(explode('-', $row['date']))).'</td>';
		echo '<td align="right">'.$row['qte'].'</td>';
		echo '<td align="right">'.$row['price'].'</td>';
		echo '<td align="right">'.round($pachat, 2).'</td>';
		echo '<td align="right">'.$margin_coeff.'</td>';
		echo '<td align="right">'.$margin_taux.'</td>';
		echo '<td><a href="?id='.$id.'&action=pcp_add&fk_soc='.$row['fk_soc'].'"><span class="fa fa-plus-circle valignmiddle btnTitle-icon""></span></a></td>';
		echo '</tr>';
	}
	//$a = array_filter($competitor_price_list);
	$a = $competitor_price_list;
	$competitor_price_avg = count($a)>0 ?round(array_sum($a)/count($a), 2) :'-';
	?>
	</tbody>
	<tr>
		<th style="visibility:hidden;"></th>
		<th colspan="3">Prix médian :</th>
		<th align="right">(1)</th>
		<th align="right"><?php  ?></th>
	</tr>
	<tr>
		<th style="visibility:hidden;"></th>
		<th colspan="3">1er quartile :</th>
		<th align="right">(1)</th>
		<th align="right"><?php  ?></th>
	</tr>
	<tr>
		<th style="visibility:hidden;"></th>
		<th colspan="3">3ème quartile :</th>
		<th align="right">(1)</th>
		<th align="right"><?php  ?></th>
	</tr>
	<tr>
		<th style="visibility:hidden;"></th>
		<th colspan="3">Prix moyen :</th>
		<th align="right">(1)</th>
		<th align="right"><?php echo $competitor_price_avg; ?></th>
		<th align="right"><?php echo round($pachat, 2); ?></th>
	</tr>
	<tr>
		<th style="visibility:hidden;"></th>
		<th colspan="3">Prix actuel :</th>
		<th align="right">(1)</th>
		<th align="right"><?php echo round($object->price, 2); ?></th>
		<th align="right"><?php echo round($pachat, 2); ?></th>
		<th align="right"><?php echo $pachat>0 ?round($object->price/$pachat, 2) :''; ?></th>
		<th align="right"><?php echo $pachat>0 ?round(100*($object->price-$pachat)/$object->price, 2).'%' :''; ?></th>
	</tr>
</table>

<p>Voir comment calculer les prix concurrent mini, maxi, moyen en fct des prix par quantité et de l'historique des prix concurrents.</p>
<p>Dans un premier temps, prend-on le dernier prix de chaque concurrent quelle que soit la quantité ?</p>
<p>On peut aussi reagrder les marges des produits similaires, c'est-à-dire dont les prix sont proches (+- X % à définir) et dans la même catégorie.</p>
<p>On peut aller très loin dans le détail et la précision mais de fait vite s'y perdre en ayant trop d'indiacateurs inexploitables...</p>
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

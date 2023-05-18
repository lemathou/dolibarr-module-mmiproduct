<div>
<h3>Historique des prix de la concurrence</h3>

<div style="float: right;margin: 0 10px;">
	<p><a href="?id=<?php echo $id; ?>&pc_add">Ajouter une url concurrent</a></p>
	<p><a href="?id=<?php echo $id; ?>&pcp_add">Ajouter un prix</a></p>
</div>

<?php if (isset($_GET['pcp_add'])) { ?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pcp_add">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('Competitor'); ?></label></td>
		<td><select name="fk_soc"><option value="">--</option><?php foreach ($pc_list as $r) {
			echo '<option value="'.$r['fk_soc'].'">'.$r['nom'].' - '.$r['url'].'</option>';
		} ?></select></td>
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
<?php } elseif (!empty($pcp_edit)) { ?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pcp_edit&link_id=<?php echo $_GET['pcp_edit']; ?>">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('ResCompetitorource'); ?></label></td>
		<td><?php echo $pcp['soc_nom']; ?></td>
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
<?php } elseif (isset($_GET['pc_add'])) { ?>
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
		<td><input name="url" value="" /></td>
	</tr>
	<tr>
		<td><label for="price"><?php echo $langs->trans('Price'); ?></label></td>
		<td><input name="price" value="<?php echo $pcp['price']; ?>" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Ajouter l'URL du concurrent" /></td>
	</tr>
</table></form>
<hr />
<?php } elseif (!empty($pc_edit)) { ?>
<form method="POST" action="?id=<?php echo $id; ?>&action=pc_edit&link_id=<?php echo $_GET['pc_edit']; ?>">
<table>
	<tr>
		<td><label for="fk_soc"><?php echo $langs->trans('ResCompetitorource'); ?></label></td>
		<td><?php echo $pcp['soc_nom']; ?></td>
	</tr>
	<tr>
		<td><label for="price"><?php echo $langs->trans('Price'); ?></label></td>
		<td><input name="price" value="<?php echo $pcp['price']; ?>" /></td>
	</tr>
	<tr>
		<td></td>
		<td><input type="submit" name="" value="Modifier le concurrent" /></td>
	</tr>
</table></form>
<hr />
<?php } ?>

<table border="1" cellpadding="4">
	<thead>
	<tr>
			<th></th>
			<th>Concurrent</th>
			<th>URL</th>
			<th>Date</th>
			<th>Prix</th>
	</tr>
	</thead>
	<tbody>
	<?php foreach($pcp_list as $row) {
		echo '<tr>';
		echo '<td><a href="?id='.$id.'&pcp_edit='.$row['rowid'].'">'.$row['rowid'].'</a></td>';
		echo '<td>'.$row['nom'].'</td>';
		echo '<td>'.$row['url'].'</td>';
		echo '<td>'.$row['datec'].'</td>';
		echo '<td>'.$row['price'].'</td>';
		echo '</tr>';
	} ?>
	</tbody>
</table>
</div>

<hr />

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

<h3>Prix concurrence</h3>
<p>Prix mini, maxi, moyen.</p>

<h3>Coût de transport moyen pour l'approvisionnement (historique de commande fournisseur)</h3>
<p>Facile à calculer si le produit est toujours stockés chez nous sur un même dépôt.</p>
<p>Sinon il faut bien dissocier les situations pour le calcul.</p>

<h3>Méthode de calcul</h3>
<p></p>

<h3></h3>
<?php

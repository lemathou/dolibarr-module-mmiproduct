<?php

// ACTIONS

// Calcul de marge

if ($action=='margin_calc_update') {
	//var_dump($_POST);

	// Default Supplier price & Supplier
	$product_fourn_price_id = GETPOST('product_fourn_price_id', 'int');
	if ($product_fourn_price_id) {
		$sql = 'SELECT pfp.*, s.nom
			FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
			INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pfp.fk_soc
			WHERE pfp.rowid='.$product_fourn_price_id;
		$q = $db->query($sql);
		if($r=$q->fetch_assoc()) {
			$product_fourn = $r;
			$object->array_options['options_fk_soc_fournisseur'] = $product_fourn['fk_soc'];
		}
		//var_dump($r);
	}
	if (empty($product_fourn)) {
		$object->array_options['options_fk_soc_fournisseur'] = NULL;
	}

	// Default Category
	// @todo vérif avec presta
	$fk_categorie_default = GETPOST('fk_categorie_default', 'int');
	$object->array_options['options_fk_categorie_default'] = $fk_categorie_default;

	// Margin calculation type
	$margin_calc_type = GETPOST('margin_calc_type', 'alphanum');
	$object->array_options['options_margin_calc_type'] = $margin_calc_type;

	// Price update
	$cost_price = GETPOST('revient_price');
	$object->cost_price = $cost_price;
	//var_dump($cost_price);
	$sell_price = GETPOST('sell_price');
	$price_vat_tx = 20;
	$sell_coeff = GETPOST('sell_coeff'); // @todo
	$object->array_options['options_margin_coeff'] = $sell_coeff;
	$object->array_options['options_margin_desired_coeff'] = $sell_coeff;
	$sell_min_coeff = GETPOST('sell_min_coeff'); // @todo
	$object->array_options['options_margin_min_coeff'] = $sell_min_coeff;
	$sell_min_price = GETPOST('sell_min_price');
	$object->update($object->id, $user);
	$res = $object->updatePrice($sell_price, 'HT', $user, $object->tva_tx, !empty($sell_min_price) ?$sell_min_price :NULL);
	//$res = $object->updatePrice($sell_price, 'HT', $user, $object->tva_tx, $sell_price_min);
}

// DONNEES

$product = new Product($db);
$product->fetch($id);

// Prix public
$public_price =  $product->array_options['options_public_price'];

// Prix concurrent
$pcp_list = [];
$pcp_values = [];
$pcp_values_f = [];
$pcp_value_recent = 0;
$date_min = date('Y-m-d', time()-86400*365);
$sql = 'SELECT pcp.*, s.nom, s.url s_url
	FROM `'.MAIN_DB_PREFIX.'product_competitor_price` AS pcp
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pcp.fk_soc
	WHERE pcp.fk_product='.$id.'
	ORDER BY pcp.date DESC';
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	$pcp_list[$r['rowid']] = $r;
	$pcp_values[] = $r['price'];
	if (empty($pcp_value_recent))
		$pcp_value_recent = $r['price'];
	if(!isset($pcp_values_f[$r['fk_soc']]) && $r['date']>=$date_min)
		$pcp_values_f[$r['fk_soc']] = $r['price'];
}
//var_dump($pcp_list);

$pcp_f_nb = count($pcp_values_f);
$pcp_values_ok = $pcp_values_f;
sort($pcp_values_ok);
$pcp_avg = $pcp_f_nb>0 ?round(array_sum($pcp_values_f)/$pcp_f_nb, 2) :'-';
$pcp_median = Median($pcp_values_ok);
$pcp_quartile_25 = Quartile_25($pcp_values_ok);
$pcp_quartile_75 = Quartile_75($pcp_values_ok);
//var_dump($pcp_list);

// Prix fournisseur
$pfp_list = [];
$sql = 'SELECT pfp.*, s.nom, s2.margin_min_coeff
	FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pfp.fk_soc
	INNER JOIN `'.MAIN_DB_PREFIX.'societe_extrafields` AS s2 ON s2.fk_object=s.rowid
	WHERE pfp.fk_product='.$id;
$q = $db->query($sql);
while($r=$q->fetch_assoc())
	$pfp_list[$r['rowid']] = $r;
//var_dump($pcp_list);

// Catégories
$categ_list = [];
$categ = NULL;
$sql = 'SELECT c2.*, c.*
	FROM `'.MAIN_DB_PREFIX.'categorie` AS c
	LEFT JOIN `'.MAIN_DB_PREFIX.'categories_extrafields` AS c2 ON c2.fk_object=c.rowid
	INNER JOIN `'.MAIN_DB_PREFIX.'categorie_product` AS cp ON cp.fk_categorie=c.rowid
	WHERE cp.fk_product='.$id;
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	$categ_list[$r['rowid']] = $r;
	if (!empty($object->array_options['options_fk_categorie_default'])) {
		if ($r['rowid']==$object->array_options['options_fk_categorie_default'])
			$categ = $r;
	}
	elseif (empty($categ)) {
		$categ = $r;
	}
}

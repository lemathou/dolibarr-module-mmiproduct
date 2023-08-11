<?php

// ACTIONS

// URL produit concurrent

$datenow = date('Y-m-d');

$fk_soc = GETPOST('fk_soc', 'int');
$url = GETPOST('url');
$date = GETPOST('date', 'date');
if (!preg_match('/[0-9-\/]*/', $date))
	$date = $datenow;
$qte = GETPOST('qte', 'int');
$price = GETPOST('price', 'int');

$pc_edit = GETPOST('pc_edit', 'int');
$pcp_edit = GETPOST('pcp_edit', 'int');

if ($action == 'pc_add' && !empty($fk_soc) && !empty($url)) {
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_competitor
		(`fk_product`, `fk_soc`, `url`)
		VALUES
		('.$id.', "'.$fk_soc.'", "'.$url.'")';
	//echo $sql;
	$res = $db->query($sql);
	//var_dump($res);
}

if ($action == 'pc_edit' && !empty($pc_edit) && !empty($url)) {
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_competitor
		SET `url`="'.$url.'"
		WHERE rowid='.$pc_edit.'';
		//, `fk_c_type_resource`='.$fk_c_type_resource.'
	$db->query($sql);
}

if (!empty($del = GETPOST('pc_delete', 'int'))) {
	$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'product_competitor
		WHERE rowid='.$del.'';
	$db->query($sql);
}

// Prix

if (($action == 'pcp_add' || $action == 'pc_add') && !empty($fk_soc) && !empty($price)) {
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_competitor_price
		(`fk_product`, `fk_soc`, `date`, `price`, `qte`)
		VALUES
		('.$id.', "'.$fk_soc.'", "'.$date.'", '.$price.', '.$qte.')';
	//echo $sql;
	$res = $db->query($sql);
	//var_dump($res);
}

if ($action == 'pcp_edit' && !empty($pcp_edit) && !empty($price)) {
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_competitor_price
		SET `price`="'.$price.'", `qte`="'.$qte.'", `date`="'.$date.'"
		WHERE rowid='.$pcp_edit.'';
		//, `fk_c_type_resource`='.$fk_c_type_resource.'
	//echo $sql;
	$db->query($sql);
}

if (!empty($del = GETPOST('pcp_delete', 'int'))) {
	$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'product_competitor_price
		WHERE rowid='.$del.'';
	$db->query($sql);
}

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
	$fk_categorie_default = GETPOST('fk_categorie_default', 'int');
	$object->array_options['options_fk_categorie_default'] = $fk_categorie_default;

	// Margin calculation type
	$margin_calc_type = GETPOST('margin_calc_type', 'alphanum');
	$object->array_options['options_margin_calc_type'] = $margin_calc_type;

	// Price update
	$sell_price = GETPOST('sell_price');
	$price_vat_tx = 20;
	$sell_price = $sell_price; // @todo

	$object->update($object->id, $user);
	$res = $object->updatePrice($sell_price, 'HT', $user);
	//$res = $object->updatePrice($sell_price, 'HT', $user, $object->tva_tx, $sell_price_min);
}

// DONNEES

// Concurrent
$s_list = [];
$sql = 'SELECT s2.*, s.*
	FROM `'.MAIN_DB_PREFIX.'societe` AS s
	LEFT JOIN `'.MAIN_DB_PREFIX.'societe_extrafields` s2
		ON s2.fk_object=s.rowid
	WHERE s.fournisseur=1 OR s2.competitor=1';
$q = $db->query($sql);
while($r=$q->fetch_assoc())
	$s_list[$r['rowid']] = $r;
//var_dump($s_list);

// URL Concurrent
$pc_list = [];
$pc_list_soc_url = [];
$sql = 'SELECT pc.*, s.nom
	FROM `'.MAIN_DB_PREFIX.'product_competitor` AS pc
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s
		ON s.rowid=pc.fk_soc
	WHERE pc.fk_product='.$id.'
	ORDER BY pc.rowid';
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	$pc_list[$r['rowid']] = $r;
	$pc_list_soc_url[$r['fk_soc']][] = $r['url'];
}
//var_dump($pc_list);

// Prix concurrent
$pcp_list = [];
$pcp_values = [];
$sql = 'SELECT pcp.*, s.nom, s.url s_url
	FROM `'.MAIN_DB_PREFIX.'product_competitor_price` AS pcp
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pcp.fk_soc
	WHERE pcp.fk_product='.$id.'
	ORDER BY pcp.date DESC';
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	$pcp_list[$r['rowid']] = $r;
	$pcp_values[] = $r['price'];
}
//var_dump($pcp_list);

$pcp_avg = count($pcp_values)>0 ?round(array_sum($pcp_values)/count($pcp_values), 2) :'-';

// Prix fournisseur
$pfp_list = [];
$sql = 'SELECT pfp.*, s.nom
	FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pfp.fk_soc
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

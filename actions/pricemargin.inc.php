<?php

// ACTIONS

// urk produit concurrent

if ($action == 'pc_add') {
	$fk_soc = GETPOST('fk_soc', 'int');
	$url = GETPOST('url');
	$price = GETPOST('price');
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_competitor
		(`fk_product`, `fk_soc`, `url`)
		VALUES
		('.$id.', "'.$fk_soc.'", "'.$url.'")';
	echo $sql;
	$res = $db->query($sql);
	var_dump($res);
}

if ($action == 'pc_edit') {
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_competitor
		SET `url`="'.$url.'"
		WHERE rowid='.$pc_id.'';
		//, `fk_c_type_resource`='.$fk_c_type_resource.'
	$db->query($sql);
}

if (!empty($del = GETPOST('pc_delete', 'int'))) {
	$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'product_competitor
		WHERE rowid='.$del.'';
	$db->query($sql);
}

// Prix

if ($action == 'pcp_add') {
	$fk_soc = GETPOST('fk_soc', 'int');
	$price = GETPOST('price');
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'product_competitor_price
		(`fk_product`, `fk_soc`, `price`)
		VALUES
		('.$id.', "'.$fk_soc.'", '.$price.')';
	echo $sql;
	$res = $db->query($sql);
	var_dump($res);
}

if ($action == 'pcp_edit') {
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_competitor_price
		SET `price`="'.$price.'"
		WHERE rowid='.$pcp_id.'';
		//, `fk_c_type_resource`='.$fk_c_type_resource.'
	$db->query($sql);
}

if (!empty($del = GETPOST('pcp_delete', 'int'))) {
	$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'product_competitor_price
		WHERE rowid='.$del.'';
	$db->query($sql);
}

// DONNEES

// Concurrent
$s_list = [];
$sql = 'SELECT s2.*, s.*
	FROM `'.MAIN_DB_PREFIX.'societe_extrafields` s2
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=s2.fk_object
	WHERE s2.competitor=1';
$q = $db->query($sql);
while($r=$q->fetch_assoc())
	$s_list[$r['rowid']] = $r;
//var_dump($s_list);

// URL Concurrent
$pc_list = [];
$sql = 'SELECT pc.*, s.nom
	FROM `'.MAIN_DB_PREFIX.'product_competitor` AS pc
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pc.fk_soc
	WHERE pc.fk_product='.$object->id;
$q = $db->query($sql);
while($r=$q->fetch_assoc())
	$pc_list[$r['rowid']] = $r;
var_dump($pc_list);

// Prix concurrent
$pcp_list = [];
$sql = 'SELECT pcp.*, s.nom, pc.url
	FROM `'.MAIN_DB_PREFIX.'product_competitor_price` AS pcp
	INNER JOIN `'.MAIN_DB_PREFIX.'product_competitor` AS pc ON pc.fk_soc=pcp.fk_soc
	INNER JOIN `'.MAIN_DB_PREFIX.'societe` AS s ON s.rowid=pcp.fk_soc
	WHERE pc.fk_product='.$object->id;
$q = $db->query($sql);
while($r=$q->fetch_assoc())
	$pcp_list[$r['rowid']] = $r;
var_dump($pcp_list);


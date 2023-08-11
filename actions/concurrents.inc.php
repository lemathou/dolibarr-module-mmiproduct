<?php

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

// ACTIONS

// URL produit concurrent

$datenow = date('Y-m-d');

$fk_soc = GETPOST('fk_soc', 'int');
$url = GETPOST('url');
$date = GETPOST('date', 'date');
if (!preg_match('/[0-9-\/]*/', $date))
	$date = $datenow;
$qte = GETPOST('qte', 'int');
$price = str_replace(',', '.', GETPOST('price', 'float'));

$pc_edit = GETPOST('pc_edit', 'int');
$pcp_edit = GETPOST('pcp_edit', 'int');

if ($action == 'pc_add' && !empty(GETPOST('create_soc'))) {
	$societe = new Societe($db);
	$pos = strpos($url, '://')+3;
	$societe->name = substr($url, $pos, strpos($url, '/', $pos)-$pos);
	$societe->client = 0;
	$societe->fournisseur = 0;
	$societe->array_options['options_competitor'] = 1;
	$result = $societe->create($user);
	$fk_soc = $societe->id;
}

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

// DONNEES

// Concurrent
$s_list = [];
$sql = 'SELECT s2.*, s.*
	FROM `'.MAIN_DB_PREFIX.'societe` AS s
	LEFT JOIN `'.MAIN_DB_PREFIX.'societe_extrafields` s2
		ON s2.fk_object=s.rowid
	WHERE s.fournisseur=1 OR s2.competitor=1
	ORDER BY s.nom';
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

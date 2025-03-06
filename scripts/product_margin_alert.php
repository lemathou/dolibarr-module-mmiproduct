#!/bin/php
<?php

$script_file = basename(__FILE__);

// Include Dolibarr environment
require_once __DIR__.'/../master_load.inc.php';

$days_min = 28;
$days_max = 35;

$coeff = getDolGlobalString('MMIPRODUCT_COEFF_EMAIL_ALERT_COEFF');
if (empty($coeff)) {
	$coeff = 1.2;
}

$email_to = getDolGlobalString('MMIPRODUCT_COEFF_EMAIL_ALERT_TO');
$email_from = getDolGlobalString('MMIPRODUCT_COEFF_EMAIL_ALERT_FROM');
$email_subject = 'Produits dont le prix de vente est en deça d\'un coeff de '.$coeff;
$email_msg = 'Produits dont le prix de vente est en deça d\'un coeff de '.$coeff.' : '."\n";
$email_headers = 'Content-type: text/plain; charset=utf-8'."\r\n"
	.'From: '.$email_from."\r\n";

$sql = 'SELECT p.rowid, p.ref, p.label, ROUND(p.price, 2) AS sell_price, ROUND(pfp.unitprice*(1-pfp.remise_percent/100), 2) AS fourn_price, ROUND(p.price/pfp.unitprice/(1-pfp.remise_percent/100), 3) AS coeff_marge
	FROM `'.MAIN_DB_PREFIX.'product` AS p
	INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price AS pfp ON pfp.fk_product=p.rowid
	INNER JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=pfp.fk_soc
	INNER JOIN '.MAIN_DB_PREFIX.'product_stock ps ON ps.fk_product=pfp.fk_product
	WHERE p.price/pfp.unitprice/(1-pfp.remise_percent/100) < '.$coeff.'
	AND ps.fk_entrepot IN (3,4)
	ORDER BY ROUND(p.price/pfp.unitprice/(1-pfp.remise_percent/100), 3) ASC;';

echo $sql;
$q = $db->query($sql);
while($r=$q->fetch_assoc()) {
	$email_msg .= '* '.$r['ref'].' - '.$r['label'].' - Vente : '.$r['sell_price'].' - Achat : '.$r['fourn_price'].' - Coeff : '.$r['coeff_marge']."\n";
}

//var_dump($msg);

mail($email_to, $email_subject, $email_msg, $email_headers);



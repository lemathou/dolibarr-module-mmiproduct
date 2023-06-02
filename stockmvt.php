<?php
/* Copyright (C) 2023 Mathieu Moulin            <contact@iprospective.fr>
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

/**
 *   \file       mmiproduct/pricemargin.php
 *   \brief      Margin calculation helper
 */

require_once 'env.inc.php';
require_once 'main_load.inc.php';

$confirm = GETPOST('confirm');
$type = GETPOST('type');

echo '<p><a href="?type=all">Tous les produits</a></p>';
echo '<p><a href="?type=simple">Produits simples (sans lots)</a></p>';
echo '<p><a href="?type=batch">Produits avec lots</a></p>';
if (empty($type)) {
	die();
}

$ts = date('YmdHis');

// Produits sans lots !! Sinon il faut gérer différemment...
$sql = 'SELECT p.rowid fk_product, p.datec, p.label, p.fk_product_type, p.tobatch, e.rowid fk_entrepot, e.ref entreprot, s.reel, SUM(m.value) mvt_reel
	FROM '.MAIN_DB_PREFIX.'product p
	INNER JOIN '.MAIN_DB_PREFIX.'entrepot e
	LEFT JOIN '.MAIN_DB_PREFIX.'product_stock s
		ON s.fk_entrepot=e.rowid AND s.fk_product=p.rowid
	LEFT JOIN '.MAIN_DB_PREFIX.'stock_mouvement m
		ON m.fk_entrepot=e.rowid AND m.fk_product=p.rowid
	WHERE 1
	'.($type=='simple' ?'AND p.tobatch=0' :'').'
	'.($type=='batch' ?'AND p.tobatch=1' :'').'
	GROUP BY p.rowid, e.rowid
	HAVING (s.reel IS NULL AND SUM(m.value) IS NOT NULL AND SUM(m.value) != 0)
		OR (s.reel IS NOT NULL AND s.reel != 0 AND SUM(m.value) IS NULL)
		OR (s.reel IS NOT NULL AND SUM(m.value) IS NOT NULL AND s.reel != SUM(m.value));';

echo '<pre>'.$sql.'</pre>';
//var_dump($db);

$q = $db->query($sql);
var_dump($q);
$rec = $l = [];
while($row=$q->fetch_assoc()) {
	echo '<hr />'; var_dump($row);
	$nb = (($row['reel'] ?$row['reel'] :0)-($row['mvt_reel'] ?$row['mvt_reel'] :0));
	if ($nb==0)
		continue;
	$rec = [
		'datem'=>$row['datec'],
		'fk_product'=>$row['fk_product'],
		'fk_entrepot'=>$row['fk_entrepot'],
		'value' => $nb,
		'type_mouvement' => ($nb>0 ?'0' :'1'),
		'fk_user_author' => 1,
		'label'=>'Correctif pour inventaire',
		'inventorycode'=>$ts,
	];
	foreach($rec as &$value) {
		if (is_null($value))
			$value = 'NULLL';
		elseif (!is_numeric($value))
			$value = '"'.$value.'"';
	}
	$l[] = '('.implode(', ', $rec).')';
}

if (!empty($l)) {
	echo '<p><a href="?type='.$type.'&confirm">Appliquer les modifications (bien vérifier avant !)</a></p>';
	echo '<hr />';
	$sql = 'INSERT INTO
		'.MAIN_DB_PREFIX.'stock_mouvement
		(`datem`, `fk_product`, `fk_entrepot`, `value`, `type_mouvement`, `fk_user_author`, `label`, `inventorycode`)
		VALUES
		'.implode(', ', $l);
	echo $sql;
	if ($confirm)
		$db->query($sql);
}
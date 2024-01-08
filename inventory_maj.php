<?php
/* Copyright (C) 2024 Mathieu Moulin            <contact@iprospective.fr>
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

// Blocage
die();

$id = GETPOST('id', 'int');
if (empty($id))
	die('Empty Id');

$sql = 'SELECT i.rowid, p.ref, p.label, i.qty_stock, i.qty_view
	FROM '.MAIN_DB_PREFIX.'inventorydet i
	INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=i.fk_product
	WHERE i.fk_inventory='.$id;
echo '<p>'.$sql.'</p>';
$q = $db->query($sql);
var_dump($q);
$l = [];
while($row=$q->fetch_assoc()) {
	$l[$row['ref']] = $row;
}

// @todo gérer l'upload
$handle = fopen('../../../documents/inventory/inventory-'.$id.'.csv','r');
if(empty($handle))
	die('Input file error');

$cols = fgetcsv($handle, NULL, ';');
var_dump($cols);

$max = GETPOST('max', 'int');
if (empty($max))
	$max = 5000;

$go = GETPOST('go');

$n = 0;
$no = 0;
while ( ($data = fgetcsv($handle, NULL, ';') ) !== FALSE && $data !== NULL ) {
	$nb++;
	if ($nb>$max)
		break;
	//var_dump($data);
	echo '<hr />';
	$ref = $data[0];
	$qty = $data[6];
	if (!empty($l[$ref])) {
		var_dump($l[$ref]);
		// Update stock
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'inventorydet
			SET qty_view='.$qty.'
			WHERE rowid='.$l[$ref]['rowid'];
		echo '<p>'.$sql.'</p>';
		if ($go) {
			$db->query($sql);
		}
	}
	else {
		var_dump($data);
		$no++;
		echo '<p style="color: ref;">Introuvable dans inventory</p>';
	}
}

echo '<hr />';
echo '<p>Introuvabkles : '.$no.'</p>';
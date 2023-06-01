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

$sql = 'SELECT p.rowid fk_product, p.datec, p.label, e.rowid fk_entrepot, e.ref entreprot, s.reel, SUM(m.value) mvt_reel
FROM '.MAIN_DB_PREFIX.'product p
INNER JOIN '.MAIN_DB_PREFIX.'entrepot e
LEFT JOIN '.MAIN_DB_PREFIX.'product_stock s ON s.fk_entrepot=e.rowid AND s.fk_product=p.rowid
LEFT JOIN '.MAIN_DB_PREFIX.'stock_mouvement m ON m.fk_entrepot=e.rowid AND m.fk_product=p.rowid
GROUP BY p.rowid, e.rowid
HAVING (s.reel IS NULL AND SUM(m.value) IS NOT NULL)
  OR (s.reel IS NOT NULL AND SUM(m.value) IS NULL)
  OR (s.reel != SUM(m.value));';

echo '<pre>'.$sql.'</pre>';
//var_dump($db);

$ts = date('YmdHis');

$q = $db->query($sql);
var_dump($q);
$rec = $l = [];
while($row=$q->fetch_assoc()) {
	var_dump($row); echo '<br />';
	$nb = (($row['reel'] ?$row['reel'] :0)-($row['mvt_reel'] ?$row['mvt_reel'] :0));
	if ($nb==0)
		continue;
	//echo '<br />'.$nb.'<hr />';
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
	$l[] = '("'.implode('", "', $rec).'")';
}

$sql = 'INSERT INTO
	'.MAIN_DB_PREFIX.'stock_mouvement
	(`datem`, `fk_product`, `fk_entrepot`, `value`, `type_mouvement`, `fk_user_author`, `label`, `inventorycode`)
	VALUES
	'.implode(', ', $l);
echo $sql;
if ($confirm)
	$db->query($sql);


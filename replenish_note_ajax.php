<?php

/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2013      Florian Henry	  	<florian.henry@open-concept.pro>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
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

require_once 'env.inc.php';
require_once 'main_load.inc.php';

$id = GETPOSTINT('id');
$note = GETPOST('note');

//var_dump($id);
$societe = new Societe($db);
$societe->fetch($id);

$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'societe_extrafields
	WHERE fk_object='.$id;
$q = $db->query($sql);

if (!($row=$q->fetch_assoc())) {
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'societe_extrafields
		(fk_object, replenish_note)
		VALUES
		('.$id.', "'.$db->escape($note).'")';
	//echo $sql;
	$q = $db->query($sql);
	if ($q)
		echo 1;
}
else {
	$sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_extrafields
		SET replenish_note="'.$db->escape($note).'"
		WHERE fk_object='.$row['rowid'];
	//	echo $sql;
	$q = $db->query($sql);
	if ($q)
		echo 1;
}

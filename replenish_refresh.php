<?php

/* Copyright (C) 2025-2026      Mathieu Moulin       <mathieu@iprospective.fr>
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

require_once 'main_load.inc.php';

dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

$options = [];

$fk_soc_fournisseur = GETPOST('fk_soc_fournisseur');
if ($fk_soc_fournisseur)
	$options['fk_soc_fournisseur'] = $fk_soc_fournisseur;

//var_dump($options); die();

MMI_Product_Replenish::stats_refresh($options);
//MMI_Product_Replenish::conso_refresh();
//MMI_Product_Replenish::seuils_refresh();

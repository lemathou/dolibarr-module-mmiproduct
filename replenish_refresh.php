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

// Security check
$result = restrictedArea($user, 'produit|service');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

$langs->loadLangs(array("admin", "mmiproduct@mmiproduct"));

$options = [];

$fk_soc_fournisseur = GETPOSTINT('fk_soc_fournisseur');
if ($fk_soc_fournisseur)
	$options['fk_soc_fournisseur'] = $fk_soc_fournisseur;

// Rafraîchissement des stats de réappro (peut être long sur l'ensemble du catalogue)
@set_time_limit(0);
$t0 = microtime(true);
MMI_Product_Replenish::stats_refresh($options);
$duration = round(microtime(true) - $t0, 2);

/*
 * View
 */

llxHeader('', $langs->trans('MMIProductStockReplenishFournStats'));

print load_fiche_titre($langs->trans('MMIProductStockReplenishFournStats'), '', 'title_setup');

$scope = $fk_soc_fournisseur ? 'fournisseur #'.$fk_soc_fournisseur : 'tous les fournisseurs';
echo '<div class="ok">Stats de réappro recalculées ('.$scope.') en '.$duration.' s.</div>';

echo '<div class="tabsAction">';
echo '<a class="butAction" href="'.dol_buildpath('/custom/mmiproduct/replenish_fourn.php', 1).'">Voir l\'analyse réappro</a>';
echo '<a class="butAction" href="'.dol_buildpath('/custom/mmiproduct/replenish.php', 1).'">Tableau de bord fournisseurs</a>';
echo '</div>';

llxFooter();

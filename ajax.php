<?php
/* Copyright (C) 2026 Mathieu Moulin            <contact@iprospective.fr>
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
 *   \file       mmiproduct/ajax.php
 */

require_once 'main_load.inc.php';

require_once DOL_DOCUMENT_ROOT.'/product/class/productfournisseurprice.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

if (empty($user->id))
	die('Unauthorized');

$action = GETPOST('action', 'alpha');

if ($action == "productinfo") {
	$usercanread = $user->hasRight('produit', 'read');
	if ( !$usercanread )
		die('Unauthorized');

	$id = GETPOST('id', 'int');
	if (empty($id))
		die(json_encode(['r'=>false, 'msg'=>'Empty Id']));

	$p = new Product($db);
	$p->fetch($id);
	if (empty($p->id))
		die(json_encode(['r'=>false, 'msg'=>'Product not found']));

	$p->load_stock();
	$stock = [];
	$lots = [];
	foreach($p->stock_warehouse as $sw_id=>$sw) {
		$stock[$sw_id] = ['name'=>$sw->ref, 'real'=>$sw->real];
		foreach($sw->detail_batch as $batch_id=>$batch) {
			if (empty($batch->qty)) continue;
			if (!isset($lots[$batch_id])) {
				$lots[$batch_id] = [
					'qty'=>$batch->qty,
					'lot_id'=>$batch->lotid,
					'sellby'=>$batch->sellby ?date('Y-m-d', $batch->sellby) : '',
					'eatby'=>$batch->eatby ?date('Y-m-d', $batch->eatby) : '',
				];
			}
			else {
				$lots[$batch_id]['qty'] += $batch->qty;
			}
		}
	}

	echo json_encode([
		'r'=>true,
		'data'=>[
			'id' => $p->id,
			'ref' => $p->ref,
			'label' => $p->label,
			// Price
			'price_ht' => $fp->price,
			'price_ttc' => $fp->price * (1 + $fp->tva_tx / 100),
			'tva_tx' => $fp->tva_tx,
			// Stock
			'stock_real' => $p->stock_reel,
			'stock_virtual' => $p->stock_theorique,
			'stock' => $stock,
			// Lots
			'lots' => $lots,
		],
	]);
}

if ($action == "supplierproductinfo") {
	$usercanread = $user->hasRight('produit', 'read');
	if ( !$usercanread )
		die('Unauthorized');

	$id = GETPOST('id', 'int');
	if (empty($id))
		die(json_encode(['r'=>false, 'msg'=>'Empty Id']));

	$fp = new ProductFournisseurPrice($db);
	$fp->fetch($id);
	if (empty($fp->id))
		die(json_encode(['r'=>false, 'msg'=>'Supplier price not found']));
	//var_dump($fp); die();

	$p = new Product($db);
	$p->fetch($fp->fk_product);
	if (empty($p->id))
		die(json_encode(['r'=>false, 'msg'=>'Product not found']));

	$p->load_stock();
	$stock = [];
	$lots = [];
	foreach($p->stock_warehouse as $sw_id=>$sw) {
		$stock[$sw_id] = ['name'=>$sw->ref, 'real'=>$sw->real];
		foreach($sw->detail_batch as $batch_id=>$batch) {
			if (empty($batch->qty)) continue;
			if (!isset($lots[$batch_id])) {
				$lots[$batch_id] = [
					'qty'=>$batch->qty,
					'lot_id'=>$batch->lotid,
					'sellby'=>$batch->sellby ?date('Y-m-d', $batch->sellby) : '',
					'eatby'=>$batch->eatby ?date('Y-m-d', $batch->eatby) : '',
				];
			}
			else {
				$lots[$batch_id]['qty'] += $batch->qty;
			}
		}
	}

	echo json_encode([
		'r'=>true,
		'data'=>[
			'id' => $p->id,
			'ref' => $p->ref,
			'id_supplier' => $fp->id,
			'ref_supplier' => $fp->ref_fourn,
			'label' => $p->label,
			// Price
			'price_ht' => $fp->price,
			'price_ttc' => $fp->price * (1 + $fp->tva_tx / 100),
			'tva_tx' => $fp->tva_tx,
			// Stock
			'stock_real' => $p->stock_reel,
			'stock_virtual' => $p->stock_theorique,
			'stock' => $stock,
			// Lots
			'lots' => $lots,
		],
	]);
}

if ($action == "replenish_refresh") {
	// Recalcul réappro par paquets (un paquet d'IDs produit par appel), piloté en
	// AJAX par replenish_fourn.php. Deux étapes successives : step=stats puis step=seuils,
	// dans le même ordre que le cron cronReplenishRefresh().
	if (!$user->hasRight('produit', 'read'))
		die(json_encode(['r'=>false, 'msg'=>'Unauthorized']));

	dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

	$step = GETPOST('step', 'aZ09') == 'seuils' ? 'seuils' : 'stats';
	$offset = GETPOSTINT('offset');
	if ($offset < 0)
		$offset = 0;
	$size = GETPOSTINT('size');
	if ($size < 1)
		$size = 50;
	if ($size > 500)
		$size = 500;

	// Filtres (alignés sur replenish_fourn.php)
	$options = [];
	$supplier_id = GETPOSTINT('fk_soc_fournisseur');
	if ($supplier_id) {
		$options['fk_soc_fournisseur'] = $supplier_id; // utilisé par stats_refresh + todo-list
		$options['filter_supplier_id'] = $supplier_id; // utilisé par buildSeuilsQuery
	}
	if (GETPOSTINT('filter_product_pro')) {
		$options['filter_product_pro'] = 1;
		$options['fk_product_categorie'] = getDolGlobalInt('MMIPRODUCT_PRO_PRODUCT_CATEGORY');
	}
	$analyse_days_nb = GETPOSTINT('analyse_days_nb');
	if ($analyse_days_nb)
		$options['analyse_days_nb'] = $analyse_days_nb;

	// Todo-list stable (ordonnée par rowid) -> total + découpage offset/size
	$ids = MMI_Product_Replenish::getProductIdsToProcess($options);
	if ($ids === null)
		die(json_encode(['r'=>false, 'msg'=>'SQL error building product list']));
	$total = count($ids);
	$batch = array_slice($ids, $offset, $size);
	$processed = count($batch);

	@set_time_limit(0);
	$t0 = microtime(true);
	$updated = null;
	$refresh = null;
	if (!empty($batch)) {
		$options['product_ids'] = $batch;
		if ($step == 'seuils') {
			$updated = MMI_Product_Replenish::seuils_refresh($options);
			$refresh = MMI_Product_Replenish::$last_refresh;
			// Cumuls sur l'ensemble des paquets, renvoyés par le client (RM3491)
			$refresh['updated_total'] = GETPOSTINT('acc_updated') + max(0, (int) $updated);
			$refresh['reset_total'] = GETPOSTINT('acc_reset') + (int) $refresh['reset'];
			$refresh['skipped_manual_total'] = GETPOSTINT('acc_skipped_manual') + (int) $refresh['skipped_manual'];
		}
		else
			MMI_Product_Replenish::stats_refresh($options);
	}

	$next = $offset + $size;
	$done = ($next >= $total);

	echo json_encode([
		'r' => true,
		'step' => $step,
		'offset' => $offset,
		'size' => $size,
		'processed' => $processed,
		'updated' => $updated, // nb produits écrits (étape seuils), null pour stats
		'refresh' => $refresh, // détail seuils_refresh + cumuls (RM3491)
		'total' => $total,
		'next_offset' => $done ? null : $next,
		'done' => $done,
		'duration' => round(microtime(true) - $t0, 3),
	]);
}
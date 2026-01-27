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
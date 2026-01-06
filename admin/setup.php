<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022-2023 MMI Mathieu Moulin <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    mmiproduct/admin/setup.php
 * \ingroup mmiproduct
 * \brief   mmiproduct setup page.
 */

// Load Dolibarr environment
require_once '../main_load.inc.php';

// Parameters
$arrayofparameters = array(
	// Champs supplémentaires
	'MMIPRODUCT_FIELDS'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_FIELD_COMPOSED'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_PUBLIC_PRICE'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_CUSTOM'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_SUPPLIER_DIRECT_DELIVERY'=>array('type'=>'yesno','enabled'=>1),
	'MAIN_SHOW_ADDED_PRODUCT_LABEL'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_TOOLTIP_SHOW_SUPPLIER_REF'=>array('type'=>'yesno','enabled'=>1),

	// Supplier price
	'MMIPRODUCT_SUPPLIER_UPDATE'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_PRODUCT_DEFULT_SUPPLIER_UPDATE'=>array('type'=>'yesno', 'enabled'=>1),

	// Recherche
	'MAIN_SEARCH_PRODUCT'=>array('type'=>'separator', 'enabled'=>1),
	'MAIN_SEARCH_PRODUCT_BY_FOURN_LABEL'=>array('type'=>'yesno','enabled'=>1),
	'MAIN_SEARCH_PRODUCT_BY_FOURN_SHOW_MULTIPLE'=>array('type'=>'yesno','enabled'=>1),

	// Alertes Stock & Commandes fournisseur
	'MMI_PRODUCT_REPLENISH_SETUP'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_FIELD_SEASON_DATE'=>array('type'=>'yesno','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_INFO_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_WARN_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_ALERT_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PROPAL_CLONE_USE_NEW_COST_PRICE'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_STOCK_COMPOSED_AUTO'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_STOCK_COMPOSED_USE_RESERVED'=>array('type'=>'yesno','enabled'=>1),
	
	'MMIPRODUCT_ALERT_EMAIL_FROM'=>array('type'=>'string','enabled'=>1),
	'MMIPRODUCT_ALERT_EMAIL_TO'=>array('type'=>'string','enabled'=>1),
	
	// Objets référents Produits montrer shipped qty
	'MMIPRODUCT_STATS'=>array('type'=>'separator', 'enabled'=>1),
	'PRODUCT_ORDERS_STATS_SHOW_SHIPPED_QTY'=>array('type'=>'yesno', 'enabled'=>1),

	'MMI_PRODUCT_PRICEMARGIN_SETUP'=>array('type'=>'separator','enabled'=>1),
	'MMIPRODUCT_PRICEMARGIN'=>array('type'=>'yesno','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CALC_DECIMAL'=>array('type'=>'int','enabled'=>1),
	
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_SETUP'=>array('type'=>'separator','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_CONFIRM_POPUP'=>array('type'=>'yesno','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_WARN'=>array('type'=>'yesno','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_MIN_1'=>array('type'=>'number','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_MIN_1_COLOR'=>array('type'=>'color','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_MIN_2'=>array('type'=>'number','enabled'=>1),
	'MMI_PRODUCT_PRICEMARGIN_CONTROL_MIN_2_COLOR'=>array('type'=>'color','enabled'=>1),

	// Alerte coeff
	'MMIPRODUCT_COEFF_EMAIL_ALERT'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_COEFF_EMAIL_ALERT_COEFF'=>array('type'=>'decimal','enabled'=>1),
	'MMIPRODUCT_COEFF_EMAIL_ALERT_FROM'=>array('type'=>'string','enabled'=>1),
	'MMIPRODUCT_COEFF_EMAIL_ALERT_TO'=>array('type'=>'string','enabled'=>1),

	'MMIPRODUCT_STOCK'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_STOCKMOVEMENTLIST_STOCK'=>array('type'=>'yesno','enabled'=>1),

	'MMIPRODUCT_PRICE_CONCURRENT'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_PRICE_CONCURRENT_DAYS_MAX'=>array('type'=>'int','enabled'=>1),

	// Produits PRO
	'MMIPRODUCT_PRO'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_PRO_PRODUCT_CATEGORY'=>array('type'=>'int', 'enabled'=>1),
	//'MMIPRODUCT_PRO_CUSTOMER_CATEGORY'=>array('type'=>'int', 'enabled'=>1), // Use extrafield pro in soc

	// Divers
	'MISC'=>array('type'=>'separator', 'enabled'=>1),
	'MMIPRODUCT_ORDER_SEARCH_IDPROD_FOCUS'=>array('type'=>'yesno','enabled'=>1),
	//'MMIPRODUCT_MARGIN_DATE_AUTOCHANGE'=>array('type'=>'yesno','enabled'=>1),
	// Prix fournisseur
	'MMIPRODUCT_SUPPLIER_PRICE_REDUCTED_DISPLAY'=>array('type'=>'yesno','enabled'=>1),
);

require_once('../../mmicommon/admin/mmisetup_1.inc.php');

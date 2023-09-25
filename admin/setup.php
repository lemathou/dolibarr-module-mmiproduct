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
require_once '../env.inc.php';
require_once '../main_load.inc.php';

// Parameters
$arrayofparameters = array(
	'MMIPRODUCT_FIELD_COMPOSED'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_PUBLIC_PRICE'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_CUSTOM'=>array('type'=>'yesno', 'enabled'=>1),
	'MMIPRODUCT_FIELD_SUPPLIER_DIRECT_DELIVERY'=>array('type'=>'yesno','enabled'=>1),
	'MAIN_SHOW_ADDED_PRODUCT_LABEL'=>array('type'=>'yesno','enabled'=>1),
	'MAIN_SEARCH_PRODUCT_BY_FOURN_LABEL'=>array('type'=>'yesno','enabled'=>1),
	'MAIN_SEARCH_PRODUCT_BY_FOURN_SHOW_MULTIPLE'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_PRICEMARGIN'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_FIELD_SEASON_DATE'=>array('type'=>'yesno','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_INFO_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_WARN_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PRODUCT_REPLENISH_ALERT_SEUIL'=>array('type'=>'int','enabled'=>1),
	'MMI_PROPAL_CLONE_USE_NEW_COST_PRICE'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_STOCK_COMPOSED_AUTO'=>array('type'=>'yesno','enabled'=>1),
	'MMIPRODUCT_STOCK_COMPOSED_USE_RESERVED'=>array('type'=>'yesno','enabled'=>1),
	
	'MMIPRODUCT_ALERT_EMAIL_FROM'=>array('type'=>'string','enabled'=>1),
	'MMIPRODUCT_ALERT_EMAIL_TO'=>array('type'=>'string','enabled'=>1),

	'MMIPRODUCT_ORDER_SEARCH_IDPROD_FOCUS'=>array('type'=>'yesno','enabled'=>1),
);

require_once('../../mmicommon/admin/mmisetup_1.inc.php');

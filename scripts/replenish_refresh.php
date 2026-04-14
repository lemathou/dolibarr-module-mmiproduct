#!/bin/php
<?php

$script_file = basename(__FILE__);

// Include Dolibarr environment
require_once __DIR__.'/../master_load.inc.php';

dol_include_once('mmiproduct/class/mmi_product_replenish.class.php');

echo 'truc';

MMI_Product_Replenish::stats_refresh();
//MMI_Product_Replenish::conso_refresh();
//MMI_Product_Replenish::seuils_refresh();

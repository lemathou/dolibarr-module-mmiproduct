#!/bin/php
<?php

$script_file = basename(__FILE__);

// Include Dolibarr environment
require_once __DIR__.'/../master_load.inc.php';

dol_include_once('mmiproduct/class/mmiproduct_replenish.class.php');

MMIProduct_Replenish::stock_conso_regen();
MMIProduct_Replenish::stock_replenish_seuils_refresh();

<?php

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmargin.class.php';

class MMIProduct_Margin
{

// CLASS

const MOD_NAME = 'mmiproduct';

protected static $db;
protected static $error = 0;
protected static $errors = [];

public static function __init()
{
	global $db;

	static::$db = $db;
}

public static function margin_calc($object)
{
	$formmargin = new FormMargin(static::$db);
	return $formmargin->getMarginInfosArray($object);
}

}

MMIProduct_Margin::__init();

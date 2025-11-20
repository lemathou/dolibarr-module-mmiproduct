<?php

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

dol_include_once('custom/mmicommon/class/mmi_stats.class.php');

class MMIProduct_Price
{

// CLASS

const MOD_NAME = 'mmiproduct';

protected static $db;
protected static $error = 0;
protected static $errors = [];

protected static $margin_calc_types = ['category_margin', 'sell_price', 'public_price', 'fourn_public_price', 'concurrent', 'four_margin_coeff'];

public static function __init()
{
	global $db;

	static::$db = $db;
}

public static function _errors_reset()
{
	static::$error = 0;
	static::$errors = [];
}

public static function _errors_get()
{
	return static::$errors;
}

public static function _error_get()
{
	return static::$error;
}

public static function _margin_calc_types()
{
	return static::$margin_calc_types;
}


/**
 * Update Product margin and sell price calculation type
 * 
 * @param Product $object
 * @param String $margin_calc_type
 * @param Array $options
 **/
public static function product_calc_type_update($object, $margin_calc_type, $options=[])
{
	global $conf, $user, $langs;

	$db = static::$db;

	if (!in_array($margin_calc_type, static::$margin_calc_types)) {
		static::$error++;
		static::$errors[] = 'Wrong calc type'.(is_string($margin_calc_type) ?' : '.$margin_calc_type :'');
		return -1;
	}

	// Update calc type in product
	$object->array_options['options_margin_calc_type'] = $margin_calc_type;

	// Update default category in product
	if (!empty($options['cat'])) {
		$object->array_options['options_fk_categorie_default'] = $options['cat']->id;
		// Add cat
		$object->setCategoriesCommon([$options['cat']->id], Categorie::TYPE_PRODUCT, false);
	}
	// Update default fourn in product
	if (!empty($options['fourn']))
		$object->array_options['options_fk_soc_fournisseur'] = $options['fourn']->id;
	// Update public price in product
	if (!empty($options['public_price']))
		$object->array_options['options_public_price'] = $options['public_price'];
	
	// Fixed sell price
	if ($margin_calc_type == 'sell_price') {
		if (empty($options['sell_price'])) {
			static::$error++;
			static::$errors[] = 'Sell price not possible for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$res = $object->updatePrice($options['sell_price'], 'HT', $user, $object->tva_tx, isset($sell_min_price) ?$sell_min_price :NULL);
		//var_dump($object, $res);
		if($res < 0) {
			var_dump($object->errors);
			static::$error++;
			static::$errors[] = $object->errors;

			return -1;
		}
	}

	$res = $object->update($object->id, $user);
	var_dump($object, $res);
	if($res < 0) {
		var_dump($object->errors);
		static::$error++;
		static::$errors[] = $object->errors;

		return -1;
	}

	return static::product_price_update($object);
}

public static function product_price_update(Product $object, $cascade=true)
{
	global $conf, $user, $langs;

	$db = static::$db;
	$margin_calc_type = $object->array_options['options_margin_calc_type'];
	//var_dump($object);

	if ($margin_calc_type == 'category_margin') {
		if (!empty($object->array_options['options_fk_categorie_default'])) {
			$cat = new Categorie($db);
			$cat->fetch($object->array_options['options_fk_categorie_default']);
		}
		else {
			static::$error++;
			static::$errors[] = 'Missing Categorie for product : '.$object->label;

			return -1;
		}

		if (empty($cat->array_options['options_margin_coeff'])) {
			static::$error++;
			static::$errors[] = 'Missing Categorie coeff for product : '.$object->label;

			return -1;
		}

		if (empty($object->cost_price)) {
			static::$error++;
			static::$errors[] = 'No cost price for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$cost_price = $object->cost_price;
		$coeff = $cat->array_options['options_margin_coeff'];
		$coeff_min = $cat->array_options['options_margin_min_coeff'];
		$sell_price = $cost_price*$coeff;
		$sell_min_price = $cost_price*$coeff_min;
	}
	
	elseif ($margin_calc_type == 'four_margin_coeff') {
		// Nouveau fourn
		if (!empty($object->array_options['options_fk_soc_fournisseur'])) {
			$fourn = new Fournisseur($db);
			$fourn->fetch($object->array_options['options_fk_soc_fournisseur']);
		}
		else {
			static::$error++;
			static::$errors[] = 'Missing default Fournisseur for product : '.$object->label;

			return -1;
		}

		if (empty($fourn->array_options['options_margin_coeff'])) {
			static::$error++;
			static::$errors[] = 'Missing suplier coeff for product : '.$object->label;

			return -1;
		}

		// @todo récup prix fournisseur le plus bas dans ce cas précis
		$sql = 'SELECT pfp.unitprice, pfp.remise_percent
			FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
			WHERE pfp.fk_product='.$object->id.' AND pfp.fk_soc='.$object->array_options['options_fk_soc_fournisseur'].'
			ORDER BY IF(pfp.remise_percent>0, pfp.unitprice*(1-pfp.remise_percent/100), pfp.unitprice)
			LIMIT 1';
		$q = static::$db->query($sql);
		if($r=$q->fetch_assoc()) {
			$cost_price = $r['unitprice']*(1-$r['remise_percent']/100);
		}
		else {
			static::$error++;
			static::$errors[] = 'Missing fournisseur price for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$coeff = $fourn->array_options['options_margin_coeff'];
		$coeff_min = $fourn->array_options['options_margin_min_coeff'];
		$sell_price = $cost_price*$coeff;
		$sell_min_price = $cost_price*$coeff_min;
	}
	
	elseif ($margin_calc_type == 'public_price') {
		if (empty($object->array_options['options_public_price'])) {
			static::$error++;
			static::$errors[] = 'Public price not defined for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$coeff = 0;
		//$coeff_min = NULL; // No change ?
		$sell_price = $object->array_options['options_public_price'];
	}
	
	elseif ($margin_calc_type == 'fourn_public_price') {
		// Nouveau fourn
		if (!empty($object->array_options['options_fk_soc_fournisseur'])) {
			$fourn = new Fournisseur($db);
			$fourn->fetch($object->array_options['options_fk_soc_fournisseur']);
		}
		else {
			static::$error++;
			static::$errors[] = 'Missing default Fournisseur for product : '.$object->label;

			return -1;
		}

		// @todo récup prix fournisseur le plus bas dans ce cas précis
		$sql = 'SELECT pfp.unitprice, pfp.remise_percent
			FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
			WHERE pfp.fk_product='.$object->id.' AND pfp.fk_soc='.$object->array_options['options_fk_soc_fournisseur'].'
			ORDER BY IF(pfp.remise_percent>0, pfp.unitprice*(1-pfp.remise_percent/100), pfp.unitprice)
			LIMIT 1';
		$q = static::$db->query($sql);
		if($r=$q->fetch_assoc()) {
			$cost_price = $r['unitprice']*(1-$r['remise_percent']/100);
			$sell_price = $r['unitprice'];
		}
		else {
			static::$error++;
			static::$errors[] = 'Missing fournisseur price for product : '.$object->label;

			return -1;
		}
	}
	
	elseif ($margin_calc_type == 'concurrent') {
		// Prix concurrent
		$pcp_list = [];
		$pcp_values = [];
		$pcp_values_f = [];
		$pcp_value_recent = 0;
		$date_min = date('Y-m-d', time()-86400*365);
		$sql = 'SELECT pcp.*
			FROM `'.MAIN_DB_PREFIX.'product_competitor_price` AS pcp
			WHERE pcp.fk_product='.$object->id.'
			ORDER BY pcp.date DESC';
		$q = $db->query($sql);
		while($r=$q->fetch_assoc()) {
			$pcp_list[$r['rowid']] = $r;
			$pcp_values[] = $r['price'];
			if (empty($pcp_value_recent))
				$pcp_value_recent = $r['price'];
			if(!isset($pcp_values_f[$r['fk_soc']]) && $r['date']>=$date_min)
				$pcp_values_f[$r['fk_soc']] = $r['price'];
		}

		if (empty($pcp_values_f)) {
			static::$error++;
			static::$errors[] = 'Competitor price not defined for product : '.$object->label;

			return -1;
		}
		//var_dump($pcp_list);

		//$pcp_f_nb = count($pcp_values_f);
		$pcp_values_ok = $pcp_values_f;
		sort($pcp_values_ok);
		//$pcp_avg = $pcp_f_nb>0 ?round(array_sum($pcp_values_f)/$pcp_f_nb, 2) :'-';
		$pcp_median = mmi_stats::Median($pcp_values_ok);
		//$pcp_quartile_25 = mmi_stats::Quartile_25($pcp_values_ok);
		//$pcp_quartile_75 = mmi_stats::Quartile_75($pcp_values_ok);

		// Calc new price
		$coeff = 0;
		//$coeff_min = NULL; // No change ?
		$sell_price = $pcp_median;
	}
	// Fixed sell price
	elseif ($margin_calc_type == 'sell_price') {
		if (empty($options['sell_price'])) {
			static::$error++;
			static::$errors[] = 'Sell price not possible for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$coeff = NULL;
		//$coeff_min = NULL; // No change ?
		$sell_price = $object->price;
	}
	// Fixed sell coeff (@todo : option introuvable)
	elseif ($margin_calc_type == 'sell_coeff') {
		if (empty($options['sell_coeff'])) {
			static::$error++;
			static::$errors[] = 'Sell coeff not possible for product : '.$object->label;

			return -1;
		}
		if (empty($object->cost_price)) {
			static::$error++;
			static::$errors[] = 'No cost price for product : '.$object->label;

			return -1;
		}

		// Calc new price
		$cost_price = $object->cost_price;
		$coeff = $options['sell_coeff'];
		//$coeff_min = NULL; // No change ?
		$sell_price = $cost_price*$coeff;
	}
	// Bad type
	else {
		static::$error++;
		static::$errors[] = 'Calculation price type not handled for product : '.$object->label;
	}

	// Coeffs
	if (isset($coeff))
		$object->array_options['options_margin_desired_coeff'] = $coeff;
	if (isset($coeff_min)) 
		$object->array_options['options_margin_min_coeff'] = $coeff_min;

	// Update object
	$res = $object->update($object->id, $user, ! $cascade);
	//var_dump($object, $res);
	if($res < 0) {
		var_dump($object->errors);
		static::$error++;
		static::$errors[] = $object->errors;

		return -1;
	}

	// Rounding forced
	if ($rouding = getDolGlobalInt('MMI_PRODUCT_PRICEMARGIN_CALC_DECIMAL')) {
		$sell_price = price2num($sell_price, $rouding);
		if (isset($sell_min_price))
			$sell_min_price = price2num($sell_min_price, $rouding);
	}
	
	// Price update
	$res = $object->updatePrice($sell_price, 'HT', $user, $object->tva_tx, isset($sell_min_price) ?$sell_min_price :NULL); //, 0, 0, 0, 0, [], '', ! $cascade
	//var_dump($object, $res);
	if($res < 0) {
		var_dump($object->errors);
		static::$error++;
		static::$errors[] = $object->errors;

		return -1;
	}

	return 0;
}

}

MMIProduct_Price::__init();

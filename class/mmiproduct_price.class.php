<?php

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Décale l'execution à la mort de l'objet => à la fin du script puisque singleton
 */
class MMIProduct_Price
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

	if (!in_array($margin_calc_type, ['category_margin', 'sell_price', 'public_price', 'concurrent', 'four_margin_coeff'])) {
		static::$error++;
		static::$errors[] = 'Wrong calc type'.(is_string($margin_calc_type) ?' : '.$margin_calc_type :'');
		return -1;
	}

	// Update calc type in product
	$object->array_options['options_margin_calc_type'] = $margin_calc_type;

	// Update default category in product
	if (!empty($options['cat']))
		$object->array_options['options_fk_categorie_default'] = $options['cat']->id;
	// Update default fourn in product
	if (!empty($options['fourn']))
		$object->array_options['options_fk_soc_fournisseur'] = $options['fourn']->id;
	// Update public price in product
	if (!empty($options['public_price']))
		$object->array_options['options_public_price'] = $options['public_price'];
	
	if ($margin_calc_type == 'category_margin') {
		if (!empty($options['cat'])) {
			$cat = $options['cat'];
			// Add cat
			$object->setCategoriesCommon([$cat->id], Categorie::TYPE_PRODUCT, false);
			// Set cat as default
			$object->array_options['options_fk_categorie_default'] = $cat->id;
		}
		elseif (!empty($object->array_options['options_fk_categorie_default'])) {
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
		if (!empty($options['fourn'])) {
			$fourn = $options['fourn'];
			$object->array_options['options_fk_soc_fournisseur'] = $fourn->id;
		}
		elseif (!empty($object->array_options['options_fk_soc_fournisseur'])) {
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

		// @todo récup prix de revient et pas prix fournisseur ?
		$sql = 'SELECT pfp.price
			FROM `'.MAIN_DB_PREFIX.'product_fournisseur_price` AS pfp
			WHERE pfp.fk_product='.$object->id.' AND pfp.fk_soc='.$object->array_options['options_fk_soc_fournisseur'];
		$q = static::$db->query($sql);
		if($r=$q->fetch_assoc()) {
			$cost_price = $r['price'];
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
		$pcp_median = Median($pcp_values_ok);
		//$pcp_quartile_25 = Quartile_25($pcp_values_ok);
		//$pcp_quartile_75 = Quartile_75($pcp_values_ok);

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
		$sell_price = $options['sell_price'];
	}
	// Fixed sell coeff
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
	$res = $object->update($object->id, $user);
	//var_dump($object, $res);
	if($res < 0) {
		var_dump($object->errors);
		static::$error++;
		static::$errors[] = $object->errors;

		return -1;
	}

	// Price update
	$res = $object->updatePrice($sell_price, 'HT', $user, $object->tva_tx, isset($sell_min_price) ?$sell_min_price :NULL);
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

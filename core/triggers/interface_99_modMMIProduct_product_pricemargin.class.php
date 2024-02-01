<?php
/**
 * Copyright (C) 2024       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for MyModule module
 */
class InterfaceProduct_PriceMargin extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	public static function __init()
	{

	}

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MMI Product Sell price and margin calculation triggers";
		$this->version = 'development';
		$this->picto = 'logo@mmiproduct';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->mmiproduct->enabled) || empty($conf->global->MMIPRODUCT_PRICEMARGIN)) return 0;

		global $db;
		$langs->loadLangs(array("mmiproduct@mmiproduct"));

		//var_dump($action); var_dump($object);
		switch($action) {
			case 'PRODUCT_MODIFY':
				//var_dump($object); //die();
				$price_update = false;
				$product = $object;
				if (empty($product->array_options['options_margin_calc_type']))
					break;
				// Recalcul prix selon prix public
				if (!empty($product->array_options['options_margin_calc_type'] == 'public_price')) {
					$public_price = $product->array_options['options_public_price'];
					$sell_price = $public_price;
					if ($public_price != (float)$product->price) {
						$product->array_options['options_margin_desired_coeff'] = NULL;
						//echo 'PRICE UPDATE';
						//var_dump($public_price, (float)$product->price);
						//var_dump($cost_price, $product->cost_price, $product->array_options['options_margin_desired_coeff'], $product->array_options);
						$product->price = $public_price;
						$price_update = true;
					}
				}
				if (!empty($product->cost_price)) {
					// Recalcul prix selon coeff désiré
					if (!empty($product->array_options['options_margin_desired_coeff']) && $product->array_options['options_margin_calc_type'] != 'public_price') {
						$sell_price = round($product->cost_price*$product->array_options['options_margin_desired_coeff'], 5);
						if ($sell_price != (float)$product->price) {
							//echo 'PRICE UPDATE';
							//var_dump($sell_price, (float)$product->price);
							//var_dump($cost_price, $product->cost_price, $product->array_options['options_margin_desired_coeff'], $product->array_options);
							$product->price = $sell_price;
							$price_update = true;
						}
					}
					// Recalcul prix min selon coeff désiré
					if (!empty($product->array_options['options_margin_min_coeff'])) {
						$sell_min_price = round($product->cost_price*$product->array_options['options_margin_min_coeff'], 5);
						if ($sell_min_price != (float)$product->price_min) {
							// echo 'PRICE MIN UPDATE';
							// var_dump($sell_min_price, (float)$product->price_min);
							// var_dump($cost_price, $product->cost_price, $product->array_options['options_margin_min_coeff'], $product->array_options);
							$product->price_min = $sell_min_price;
							$price_update = true;
						}
					}
				}
				// Need at least a sell_price
				if ($price_update && !empty($sell_price)) {
					//var_dump($product);
					//$ret = $product->update($product->id, $user);
					$ret = $product->updatePrice($sell_price, 'HT', $user, $product->tva_tx, !empty($sell_min_price) ?$sell_min_price :NULL);
					//var_dump($ret);
				}
				//var_dump($sell_price, $product->price, $sell_min_price, $product->price_min, $product->array_options); die();
				break;
			case 'SUPPLIER_PRODUCT_BUYPRICE_CREATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_UPDATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_MODIFY':
				//var_dump($object); die();
				$product = new Product($db);
				// S'il manque l'un des deux il faut tout mettre à jour, sinon on aura des infos inconsistantes
				if (empty($object->fourn_id) && !empty($object->product_fourn_price_id)) {
					//$object->fetch_product_fournisseur_price($object->product_fourn_price_id);
				}
				if (!empty($object->product_id) && !empty($object->fourn_id)) {
					//$product->fetch($object->product_id);
					
				}
				//var_dump($product->array_options); die();
				break;
			case 'COMPANY_MODIFY':
				$societe = $object;
				//var_dump($societe); die();
				$sql = 'SELECT DISTINCT p.rowid, p2.margin_calc_type, p2.margin_calc_options, p2.fk_categorie_default, p2.fk_soc_fournisseur, p2.margin_desired_coeff, p2.margin_min_coeff
					FROM '.MAIN_DB_PREFIX.'product p
					LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
					WHERE p2.margin_calc_type="four_margin_coeff" AND p2.fk_soc_fournisseur='.$societe->id;
				// Filtrer les produits dont la catégorie choisie est la bonne pour recalculer le prix de vente
				// On aura le même problème avec les fournisseurs
				//echo $sql; die();
				$q = $this->db->query($sql);
				//var_dump($q); die();
				while($row=$q->fetch_assoc()) {
					//var_dump($row); die();
					$l[] = $row;
					$product = new Product($this->db);
					$product->fetch($row['rowid']);
					$update = false;
					if ($product->array_options['options_margin_desired_coeff'] != $societe->array_options['options_margin_coeff']) {
						//var_dump($product->array_options['options_margin_desired_coeff'], $societe->array_options['options_margin_coeff']);
						$product->array_options['options_margin_desired_coeff'] = $societe->array_options['options_margin_coeff'];
						$update = true;
					}
					if ($product->array_options['options_margin_min_coeff'] != $societe->array_options['options_margin_min_coeff']) {
						//var_dump($product->array_options['options_margin_min_coeff'], $societe->array_options['options_margin_min_coeff']);
						$product->array_options['options_margin_min_coeff'] = $societe->array_options['options_margin_min_coeff'];
						$update = true;
					}
					if ($update) {
						$product->update($product->id, $user);
					}
				}
				//var_dump($l); die();
				break;
			case 'CATEGORY_MODIFY':
				$category = $object;
				$sql = 'SELECT p.rowid, p2.margin_calc_type, p2.margin_calc_options, p2.fk_categorie_default, p2.fk_soc_fournisseur, p2.margin_desired_coeff, p2.margin_min_coeff
					FROM '.MAIN_DB_PREFIX.'product p
					LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=p.rowid
					WHERE p2.margin_calc_type="category_margin" AND p2.fk_categorie_default='.$category->id;
				// Filtrer les produits dont la catégorie choisie est la bonne pour recalculer le prix de vente
				// On aura le même problème avec les fournisseurs
				//echo $sql; die();
				//var_dump($category); die();
				$q = $this->db->query($sql);
				while($row=$q->fetch_assoc()) {
					$l[] = $row;
					$product = new Product($this->db);
					$product->fetch($row['rowid']);
					$update = false;
					if ($product->array_options['options_margin_desired_coeff'] != $category->array_options['options_margin_coeff']) {
						//var_dump($product->array_options['options_margin_desired_coeff'], $category->array_options['options_margin_min_coeff']);
						$product->array_options['options_margin_desired_coeff'] = $category->array_options['options_margin_coeff'];
						$update = true;
					}
					if ($product->array_options['options_margin_min_coeff'] != $category->array_options['options_margin_min_coeff']) {
						//var_dump($product->array_options['options_margin_min_coeff'], $category->array_options['options_margin_min_coeff']);
						$product->array_options['options_margin_min_coeff'] = $category->array_options['options_margin_min_coeff'];
						$update = true;
					}
					if ($update) {
						$product->update($product->id, $user);
					}
				}
				//var_dump($l); die();
				break;
		}
		
		return 0;
	}
}

InterfaceProduct_PriceMargin::__init();

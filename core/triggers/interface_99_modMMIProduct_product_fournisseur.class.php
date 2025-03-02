<?php
/**
 * Copyright (C) 2022       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for MyModule module
 */
class InterfaceProduct_Fournisseur extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	protected static $_stock_instance;

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
		$this->description = "MMI Product Stock Composed triggers";
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
		if (empty($conf->mmiproduct->enabled)) return 0;

		$langs->loadLangs(array("mmiproduct@mmiproduct"));

		//var_dump($action); var_dump($object);
		switch($action) {
			case 'SUPPLIER_PRODUCT_BUYPRICE_CREATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_UPDATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_MODIFY':
				//var_dump($object); die();
				/** @var ProductFournisseur $object */
				$id = $object->product_fourn_price_id;
				$product = new Product($this->db);
				$pfp = new ProductFournisseurPrice($this->db);
				$pfp->fetch($id);
				// S'il manque l'un des deux il faut tout mettre à jour, sinon on aura des infos inconsistantes
				if (empty($object->fourn_id) && !empty($object->product_fourn_price_id)) {
					$object->fetch_product_fournisseur_price($object->product_fourn_price_id);
				}
				if (!empty($object->product_id) && !empty($object->fourn_id)) {
					$product->fetch($object->product_id);
					// Manque une info => on modifie tout
					if (empty($product->array_options['options_supplier_ref']) || empty($product->array_options['options_fk_soc_fournisseur'])) {
						$product->array_options['options_fk_soc_fournisseur'] = $object->fourn_id;
						$product->array_options['options_supplier_ref'] = $pfp->ref_fourn;
						$ret = $product->update($product->id, $user);
					}
					// Modif uniquement réf fourn
					elseif ($product->array_options['options_fk_soc_fournisseur'] == $object->fourn_id && $product->array_options['options_supplier_ref'] != $pfp->ref_fourn) {
						$product->array_options['options_supplier_ref'] = $pfp->ref_fourn;
						$ret = $product->update($product->id, $user);
					}
				}
				break;
			case 'SUPPLIER_PRODUCT_BUYPRICE_DELETE':
				if (empty($object->product_fourn_price_id))
					return 0;
				/** @var ProductFournisseur $object */
				$id = $object->product_fourn_price_id;
				$pfp = new ProductFournisseurPrice($this->db);
				$productfournisseurprice = new ProductFournisseurPrice($this->db);
				$res = $productfournisseurprice->fetch($object->product_fourn_price_id);
				if ($res > 0) {
					$product = new Product($this->db);
					$product->fetch($productfournisseurprice->fk_product);
					if ($product->array_options['options_supplier_ref'] == $productfournisseurprice->ref_fourn && $product->array_options['options_fk_soc_fournisseur'] == $productfournisseurprice->fk_soc) {
						$records = $pfp->fetchAll('', '', 0, 0, ['fk_product' => $product->id]);
						// Check if there us still a product supplier price
						if (count($records) > 1) {
							foreach($records as $record) {
								// We take the first price an set it as default product supplier & ref
								if ($record->id != $id) {
									$product->array_options['options_supplier_ref'] = $record->ref_fourn;
									$product->array_options['options_fk_soc_fournisseur'] = $record->fk_soc;
									$product->update($product->id, $user);
									break;
								}
							}
						}
						// Otherwise, we should remove the supplier & ref from the product
						else {
							$product->array_options['options_supplier_ref'] = '';
							$product->array_options['options_fk_soc_fournisseur'] = NULL;
							$product->update($product->id, $user);
						}
					}
				}
				break;
		}
		
		return 0;
	}
}

InterfaceProduct_Fournisseur::__init();

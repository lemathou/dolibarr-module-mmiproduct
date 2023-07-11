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

		global $db;
		$langs->loadLangs(array("mmiproduct@mmiproduct"));

		//var_dump($action); var_dump($object);
		switch($action) {
			case 'SUPPLIER_PRODUCT_BUYPRICE_CREATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_UPDATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_MODIFY':
				//var_dump($object); die();
				$product = new Product($db);
				// S'il manque l'un des deux il faut tout mettre à jour, sinon on aura des infos inconsistantes
				if (empty($object->fourn_id) && !empty($object->product_fourn_price_id)) {
					$object->fetch_product_fournisseur_price($object->product_fourn_price_id);
				}
				if (!empty($object->product_id) && !empty($object->fourn_id)) {
					$product->fetch($object->product_id);
					if (empty($product->array_options['options_supplier_ref']) || empty($product->array_options['options_fk_soc_fournisseur'])) {
						$product->array_options['options_fk_soc_fournisseur'] = $object->fourn_id;
						$product->array_options['options_supplier_ref'] = $object->ref_supplier;
						$product->update($product->id, $user);
					}
				}
				//var_dump($product->array_options); die();
				break;
		}
		
		return 0;
	}
}

InterfaceProduct_Fournisseur::__init();

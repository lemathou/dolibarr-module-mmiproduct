<?php
/**
 * Copyright (C) 2022-2026       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('custom/mmiproduct/class/mmi_default_supplier.class.php');

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

		switch($action) {
			case 'SUPPLIER_PRODUCT_BUYPRICE_CREATE':
			case 'SUPPLIER_PRODUCT_BUYPRICE_MODIFY':
			case 'SUPPLIER_PRODUCT_BUYPRICE_DELETE':
				if (getDolGlobalInt('MMI_PRODUCT_PRODUCT_DEFAULT_SUPPLIER_UPDATE', 0) == 0)
					return 0;
				/** @var ProductFournisseur $object */
				if (empty($object->product_fourn_price_id))
					return 0;
				// Règle RM3233 : le fournisseur par défaut ne change que s'il n'a plus de prix.
				// DELETE est appelé avant le DELETE SQL : la ligne est exclue du calcul.
				// Un échec ici ne doit pas annuler l'enregistrement du prix : on journalise seulement.
				if (mmi_default_supplier::apply($this->db, $user, $object->product_fourn_price_id, $action == 'SUPPLIER_PRODUCT_BUYPRICE_DELETE') < 0)
					dol_syslog(__METHOD__.' '.$action.' pfp='.$object->product_fourn_price_id.' : échec de la mise à jour du fournisseur par défaut', LOG_ERR);
				return 0;
		}
		
		return 0;
	}
}

InterfaceProduct_Fournisseur::__init();

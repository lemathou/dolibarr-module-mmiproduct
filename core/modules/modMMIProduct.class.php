<?php
/* Copyright (C) 2004-2018  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019  Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020  Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2022 Mathieu Moulin <mathieu@iprospective.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 	\defgroup   mmiproduct     Module MMIProduct
 *  \brief      MMIProduct module descriptor.
 *
 *  \file       htdocs/mmiproduct/core/modules/modMMIProduct.class.php
 *  \ingroup    mmiproduct
 *  \brief      Description and activation file for module MMIProduct
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module MMIProduct
 */
class modMMIProduct extends DolibarrModules
{
	protected $tabs = [];
	protected $dictionaries = [];

	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 437811; // TODO Go on page https://wiki.dolibarr.org/index.php/List_of_modules_id to reserve an id number for your module

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'mmiproduct';

		// Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
		// It is used to group modules by family in module setup page
		$this->family = "products";

		// Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '90';

		// Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
		//$this->familyinfo = array('myownfamily' => array('position' => '01', 'label' => $langs->trans("MyOwnFamily")));
		// Module label (no space allowed), used if translation string 'ModuleMMIProductName' not found (MMIProduct is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleMMIProductDesc' not found (MMIProduct is name of module).
		$this->description = "MMIProductDescription";
		// Used only if file README.md and README-LL.md not found.
		$this->descriptionlong = "MMIProductDescription";

		// Author
		$this->editor_name = 'MMI Mathieu Moulin iProspective';
		$this->editor_url = 'https://iprospective.fr';

		// Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.0';
		// Url to the file with your last numberversion of this module
		//$this->url_last_version = 'http://www.example.com/versionmodule.txt';

		// Key used in llx_const table to save module status enabled/disabled (where MMIPRODUCT is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		// To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'logo@mmiproduct';

		// Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => array(
				//    '/mmiproduct/css/mmiproduct.css.php',
			),
			// Set this to relative path of js file if module must load a js on all pages
			'js' => array(
				//   '/mmiproduct/js/mmiproduct.js.php',
			),
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => array(
				'ordercard',
				'productcard',
				'stockreplenishlist',
				'productservicelist',
				'supplier_proposalcard',
				'stockatdate',
				'pricesuppliercard',
				//   'data' => array(
				//       'hookcontext1',
				//       'hookcontext2',
				//   ),
				//   'entity' => '0',
			),
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/mmiproduct/temp","/mmiproduct/subdir");
		$this->dirs = array("/mmiproduct/temp");

		// Config pages. Put here list of php page, stored into mmiproduct/admin directory, to use to setup module.
		$this->config_page_url = array("setup.php@mmiproduct");

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->depends = array('modMMICommon', 'modProduct');
		$this->requiredby = array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = array("mmiproduct@mmiproduct");

		// Prerequisites
		$this->phpmin = array(5, 6); // Minimum version of PHP required by module
		$this->need_dolibarr_version = array(11, -3); // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext = array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		//$this->automatic_activation = array('FR'=>'MMIProductWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('MMIPRODUCT_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('MMIPRODUCT_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
		$this->const = array();

		// Some keys to add into the overwriting translation tables
		/*$this->overwrite_translation = array(
			'en_US:ParentCompany'=>'Parent company or reseller',
			'fr_FR:ParentCompany'=>'Maison mère ou revendeur'
		)*/

		if (!isset($conf->mmiproduct) || !isset($conf->mmiproduct->enabled)) {
			$conf->mmiproduct = new stdClass();
			$conf->mmiproduct->enabled = 0;
		}

		// Array to add new pages in new tabs
		$this->tabs = array();
		$this->tabs[] = array('data'=>'product:+pricemargin:Calcul Prix et Marge:mmiproduct@mmiproduct:$conf->global->MMIPRODUCT_PRICEMARGIN && $user->rights->mmiproduct->pricemargin->view:custom/mmiproduct/pricemargin.php?id=__ID__');
		$this->tabs[] = array('data'=>'product:+concurrents:Prix concurrents:mmiproduct@mmiproduct:$conf->global->MMIPRODUCT_PRICEMARGIN && $user->rights->mmiproduct->pricemargin->view:custom/mmiproduct/concurrents.php?id=__ID__');
		// Example:
		// $this->tabs[] = array('data'=>'objecttype:+tabname1:Title1:mylangfile@mmiproduct:$user->rights->mmiproduct->read:/mmiproduct/mynewtab1.php?id=__ID__');  					// To add a new tab identified by code tabname1
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@mmiproduct:$user->rights->othermodule->read:/mmiproduct/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');                                                     										// To remove an existing tab identified by code tabname
		//
		// Where objecttype can be
		// 'categories_x'	  to add a tab in category view (replace 'x' by type of category (0=product, 1=supplier, 2=customer, 3=member)
		// 'contact'          to add a tab in contact view
		// 'contract'         to add a tab in contract view
		// 'group'            to add a tab in group view
		// 'intervention'     to add a tab in intervention view
		// 'invoice'          to add a tab in customer invoice view
		// 'invoice_supplier' to add a tab in supplier invoice view
		// 'member'           to add a tab in fundation member view
		// 'opensurveypoll'	  to add a tab in opensurvey poll view
		// 'order'            to add a tab in customer order view
		// 'order_supplier'   to add a tab in supplier order view
		// 'payment'		  to add a tab in payment view
		// 'payment_supplier' to add a tab in supplier payment view
		// 'product'          to add a tab in product view
		// 'propal'           to add a tab in propal view
		// 'project'          to add a tab in project view
		// 'stock'            to add a tab in stock view
		// 'thirdparty'       to add a tab in third party view
		// 'user'             to add a tab in user view

		// Dictionaries
		$this->dictionaries=array(
		);

		// Boxes/Widgets
		// Add here list of php file(s) stored in mmiproduct/core/boxes that contains a class to show a widget.
		$this->boxes = array(
			//  0 => array(
			//      'file' => 'mmiproductwidget1.php@mmiproduct',
			//      'note' => 'Widget provided by MMIProduct',
			//      'enabledbydefaulton' => 'Home',
			//  ),
			//  ...
		);

		// Cronjobs (List of cron jobs entries to add when module is enabled)
		// unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
		$this->cronjobs = array(
			//  0 => array(
			//      'label' => 'MyJob label',
			//      'jobtype' => 'method',
			//      'class' => '/mmiproduct/class/myobject.class.php',
			//      'objectname' => 'MyObject',
			//      'method' => 'doScheduledJob',
			//      'parameters' => '',
			//      'comment' => 'Comment',
			//      'frequency' => 2,
			//      'unitfrequency' => 3600,
			//      'status' => 0,
			//      'test' => '$conf->mmiproduct->enabled',
			//      'priority' => 50,
			//  ),
		);

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;
		// Add here entries to declare new permissions
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'View price and margin tab'; // Permission label
		$this->rights[$r][4] = 'pricemargin';
		$this->rights[$r][5] = 'view'; // In php code, permission will be checked by test if ($user->rights->mmiproject->myobject->read)
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1); // Permission id (must not be already used)
		$this->rights[$r][1] = 'Admin price and margin tab'; // Permission label
		$this->rights[$r][4] = 'pricemargin';
		$this->rights[$r][5] = 'admin'; // In php code, permission will be checked by test if ($user->rights->mmiproject->myobject->read)
		$r++;
		/* BEGIN MODULEBUILDER PERMISSIONS */
		/* END MODULEBUILDER PERMISSIONS */

		// Main menu entries to add
		$this->menu = array();
		$r = 0;
		// Add here entries to declare new menus
		$this->menu[$r++]=array(
			'fk_menu'=>'fk_mainmenu=products,fk_leftmenu=stock',      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'=>'left',                          // This is a Top menu entry
			'titre'=>'MMIProductStockReplenish',
			//'prefix' => img_picto('', $this->picto, 'class="paddingright pictofixedwidth valignmiddle"'),
			'mainmenu'=>'products',
			'leftmenu'=>'stock',
			'url'=>'/custom/mmiproduct/replenish.php',
			'langs'=>'mmiproduct@mmiproduct',
			'position'=>1000+$r,
			'enabled'=>'$conf->mmiproduct->enabled',
			'perms'=>'$user->rights->stock->mouvement->creer && $user->rights->fournisseur->lire', //'$user->rights->mmiproduct->time->user',
			'target'=>'',
			'user'=>2,				                // 0=Menu for internal users, 1=external users, 2=both
		);
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/mmiproduct/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extrafields = new ExtraFields($this->db);

		// Societe
		
		$extrafields->addExtraField('competitor', $langs->trans('Extrafield_competitor'), 'boolean', 55, '', 'societe', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_competitor'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_coeff', $langs->trans('Extrafield_margin_coeff'), 'double', 55, "10,5", 'societe', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_tx_marque', $langs->trans('Extrafield_margin_tx_marque'), 'double', 55, "10,5", 'societe', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_tx_marque'), '($object->array_options["options_margin_coeff"]>0 ?100*($object->array_options["options_margin_coeff"]-1)/$object->array_options["options_margin_coeff"] :NULL)', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_coeff', $langs->trans('Extrafield_margin_min_coeff'), 'double', 55, "10,5", 'societe', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_tx_marque', $langs->trans('Extrafield_margin_min_tx_marque'), 'double', 55, "10,5", 'societe', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_tx_marque'), '($object->array_options["options_margin_min_coeff"]>0 ?100*($object->array_options["options_margin_min_coeff"]-1)/$object->array_options["options_margin_min_coeff"] :NULL)', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		
		// Category

		$extrafields->addExtraField('margin_coeff', $langs->trans('Extrafield_margin_coeff'), 'double', 55, "10,5", 'categorie', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_tx_marque', $langs->trans('Extrafield_margin_tx_marque'), 'double', 55, "10,5", 'categorie', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_tx_marque'), '($object->array_options["options_margin_coeff"]>0 ?100*($object->array_options["options_margin_coeff"]-1)/$object->array_options["options_margin_coeff"] :NULL)', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_coeff', $langs->trans('Extrafield_margin_min_coeff'), 'double', 55, "10,5", 'categorie', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_tx_marque', $langs->trans('Extrafield_margin_min_tx_marque'), 'double', 55, "10,5", 'categorie', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_tx_marque'), '($object->array_options["options_margin_min_coeff"]>0 ?100*($object->array_options["options_margin_min_coeff"]-1)/$object->array_options["options_margin_min_coeff"] :NULL)', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');

		// Products

		// Default supplier and supplier ref
		$extrafields->addExtraField('supplier_ref', $langs->trans('Extrafield_supplier_ref'), 'varchar', 10, 32, 'product', 0, 0, '', "", 1, '', 5, $langs->trans('ExtrafieldToolTip_supplier_ref'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
        $extrafields->addExtraField('fk_soc_fournisseur', $langs->trans('Extrafield_fk_soc_fournisseur'), 'sellist', 10, '', 'product', 0, 0, '', "a:1:{s:7:\"options\";a:1:{s:32:\"societe:nom:rowid::fournisseur=1\";N;}}", 1, '', 5, $langs->trans('ExtrafieldToolTip_fk_soc_fournisseur'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
		// default category
        $extrafields->addExtraField('fk_categorie_default', $langs->trans('Extrafield_fk_categorie_default'), 'int', 100, 11, 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_fk_categorie_default'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
		// Public price
		$extrafields->addExtraField('public_price', $langs->trans('Extrafield_public_price'), 'double', 55, "20,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_public_price'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_PUBLIC_PRICE');
		// Composed product
        $extrafields->addExtraField('compose', $langs->trans('Extrafield_compose'), 'boolean', 10, '', 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_compose'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_COMPOSED');
		// Custom Product
        $extrafields->addExtraField('custom', $langs->trans('Extrafield_product_custom'), 'boolean', 10, '', 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_product_custom'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_CUSTOM');
		// Product direct delivery by supplier
        $extrafields->addExtraField('supplier_direct_delivery', $langs->trans('Extrafield_supplier_direct_delivery'), 'boolean', 10, '', 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_supplier_direct_delivery'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_SUPPLIER_DIRECT_DELIVERY');
		// PRICEMARGIN FIELDS
		// Product competitor average price
		$extrafields->addExtraField('competitor_avg_price', $langs->trans('Extrafield_competitor_avg_price'), 'double', 55, "20,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_competitor_avg_price'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('competitor_note', $langs->trans('Extrafield_competitor_note'), 'varchar', 55, 255, 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_competitor_note'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		// Margin & coeff
		// Effectif
		$extrafields->addExtraField('margin_effective_tx', $langs->trans('Extrafield_margin_effective_tx'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_effective_tx'), '($object->cost_price > 0 && $object->price > 0) ?100*($object->price-$object->cost_price)/$object->price :NULL', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_effective_coeff', $langs->trans('Extrafield_margin_effective_coeff'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_effective_coeff'), '($object->cost_price > 0 && $object->price > 0) ?$object->price/$object->cost_price  :NULL', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		// Désiré (et min) selon config auto
		$extrafields->addExtraField('margin_desired_coeff', $langs->trans('Extrafield_margin_desired_coeff'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_desired_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_desired_tx', $langs->trans('Extrafield_margin_desired_tx'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_effective_tx'), '($object->array_options["options_margin_desired_coeff"] > 0) ?100*($object->array_options["options_margin_desired_coeff"]-1)/$object->array_options["options_margin_desired_coeff"]  :NULL', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_coeff', $langs->trans('Extrafield_margin_min_coeff'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_coeff'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		$extrafields->addExtraField('margin_min_tx_marque', $langs->trans('Extrafield_margin_min_tx_marque'), 'double', 55, "10,5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_margin_min_tx_marque'), '($object->array_options["options_margin_min_coeff"]>0 ?100*($object->array_options["options_margin_min_coeff"]-1)/$object->array_options["options_margin_min_coeff"] :NULL)', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		// margin calculation method
        $extrafields->addExtraField('margin_calc_type', $langs->trans('Extrafield_margin_calc_type'), 'select', 55, '', 'product', 0, 0, '', ['options'=>['sell_price' => 'Prix final fixé', 'public_price' => 'Prix public fournisseur fixé', 'concurrent' => 'Prix similaire à la concurrence', 'category_margin'=>'Marge définie par la catégorie']], 1, '', 1, $langs->trans('ExtrafieldToolTip_margin_calc_type'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
        $extrafields->addExtraField('margin_calc_options', $langs->trans('Extrafield_margin_calc_options'), 'varchar', 55, '256', 'product', 0, 0, '', '', 1, '', -3, $langs->trans('ExtrafieldToolTip_margin_calc_options'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_PRICEMARGIN');
		// Season dates begin, end, price/adjust
		$extrafields->addExtraField('season_date_begin', $langs->trans('Extrafield_season_date_begin'), 'varchar', 10, "5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_season_date_begin'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_SEASON_DATE');
		$extrafields->addExtraField('season_date_end', $langs->trans('Extrafield_season_date_end'), 'varchar', 10, "5", 'product', 0, 0, '', "", 1, '', -1, $langs->trans('ExtrafieldToolTip_season_date_end'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled && $conf->global->MMIPRODUCT_FIELD_SEASON_DATE');
		// logistic cost
        $extrafields->addExtraField('logistic_cost_price', $langs->trans('Extrafield_product_logistic_cost_price'), 'price', 60, "20,5", 'product', 0, 0, '', "", 1, '', 1, $langs->trans('ExtrafieldToolTip_product_logistic_logistic_price'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
		// misc cost
        $extrafields->addExtraField('misc_cost_price', $langs->trans('Extrafield_product_misc_cost_price'), 'price', 60, "20,5", 'product', 0, 0, '', "", 1, '', 1, $langs->trans('ExtrafieldToolTip_product_misc_logistic_price'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
		// shipping cost
        $extrafields->addExtraField('shipping_cost_price', $langs->trans('Extrafield_product_shipping_cost_price'), 'price', 60, "20,5", 'product', 0, 0, '', "", 1, '', 1, $langs->trans('ExtrafieldToolTip_product_shipping_cost_price'), '!$conf->global->MMIFOURNISSEURPRICE_AUTOCALCULATE', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');
		// Garantie
        $extrafields->addExtraField('garantie', $langs->trans('Extrafield_garantie'), 'varchar', 10, 255, 'product', 0, 0, '', "", 1, '', 3, $langs->trans('ExtrafieldToolTip_garantie'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');

		// Product Fournisseur Price
		
		// Supplier shipping price
        $extrafields->addExtraField('shipping_price', $langs->trans('Extrafield_product_supplier_shipping_price'), 'price', 100, "20,5", 'product_fournisseur_price', 0, 0, '', "", 1, '', 1, $langs->trans('ExtrafieldToolTip_product_supplier_shipping_price'), '!$conf->global->MMIFOURNISSEURPRICE_AUTOCALCULATE_ORDERS', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');

		// Commande Fournisseur

		// Supplier shipping price
        $extrafields->addExtraField('shipping_price', $langs->trans('Extrafieldcommande_fournisseur_shipping_price'), 'price', 100, "20,5", 'commande_fournisseur', 0, 0, '', "", 1, '', 1, $langs->trans('ExtrafieldToolTip_commande_fournisseur_shipping_price'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');

		// Suppliers
		// Replenish note
		$extrafields->addExtraField('replenish_note', $langs->trans('Extrafield_replenish_note'), 'varchar', 1, 255, 'societe', 0, 0, '', "", 1, '', 0, $langs->trans('ExtrafieldToolTip_replenish_note'), '', $conf->entity, 'mmiproduct@mmiproduct', '$conf->mmiproduct->enabled');

		// Permissions
		$this->remove($options);

		$sql = array();

		// Document templates
		$moduledir = 'mmiproduct';
		$myTmpObjects = array();
		//$myTmpObjects['MyObject'] = array('includerefgeneration'=>0, 'includedocgeneration'=>0);

		foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
			if ($myTmpObjectKey == 'MyObject') {
				continue;
			}
			if ($myTmpObjectArray['includerefgeneration']) {
				$src = DOL_DOCUMENT_ROOT.'/install/doctemplates/mmiproduct/template_myobjects.odt';
				$dirodt = DOL_DATA_ROOT.'/doctemplates/mmiproduct';
				$dest = $dirodt.'/template_myobjects.odt';

				if (file_exists($src) && !file_exists($dest)) {
					require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
					dol_mkdir($dirodt);
					$result = dol_copy($src, $dest, 0, 0);
					if ($result < 0) {
						$langs->load("errors");
						$this->error = $langs->trans('ErrorFailToCopyFile', $src, $dest);
						return 0;
					}
				}

				$sql = array_merge($sql, array(
					"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'standard_".strtolower($myTmpObjectKey)."' AND type = '".strtolower($myTmpObjectKey)."' AND entity = ".$conf->entity,
					"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('standard_".strtolower($myTmpObjectKey)."','".strtolower($myTmpObjectKey)."',".$conf->entity.")",
					"DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom = 'generic_".strtolower($myTmpObjectKey)."_odt' AND type = '".strtolower($myTmpObjectKey)."' AND entity = ".$conf->entity,
					"INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity) VALUES('generic_".strtolower($myTmpObjectKey)."_odt', '".strtolower($myTmpObjectKey)."', ".$conf->entity.")"
				));
			}
		}

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}

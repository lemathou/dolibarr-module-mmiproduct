<?php
/* Copyright (C) 2022 Mathieu Moulin iProspective <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcategory.class.php';

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('custom/mmiproduct/class/mmiproduct_price.class.php');

/**
 * Class ActionsSfyCustom
 */
class ActionsMMIProduct extends MMI_Actions_1_0
{
	const MOD_NAME = 'mmiproduct';

    protected $stockalertzero;
    protected $useddm30asstock;
    protected $includeproductswithoutdesiredqty;
    protected $salert;
    protected $p_active;
    protected $fk_supplier;

    protected $categ;

    function __construct($db)
    {
        parent::__construct($db);

        // Global context
        $this->stockalertzero = GETPOST('stockalertzero', 'alpha');
        $this->useddm30asstock = GETPOST('useddm30asstock', 'alpha');
        $this->includeproductswithoutdesiredqty = GETPOST('includeproductswithoutdesiredqty', 'alpha');
        $this->salert = GETPOST('salert', 'alpha');
        $this->p_active = GETPOST('p_active', 'alpha');
		$this->fk_supplier = GETPOST('fk_supplier', 'int');

        $this->categ = GETPOST('categ', 'alpha');
    }


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
        $this->resprints = '';

        //var_dump($parameters); die();
		if ($this->in_context($parameters, 'productservicelist')) {
			$this->resprints .= '<option value="margin_config">'.$langs->trans("MMIProductPriceMarginConfig").'</option>';
			//$this->resprints .= '<option value="margin_config">'.$langs->trans("MMIProductPriceMarginConfig").'</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function doPreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;

        $myvalue = '';
        $print = '';
		$error = 0; // Error counter

		if ($this->in_context($parameters, 'productservicelist')) {
			if ($_POST['massaction']=='margin_config') {
				$print .= dol_get_fiche_head(null, '', '');
				$print .= '<h3>Configurateur de prix de vente et calcul de marge en masse</h3>';
				$print .= '<input type="hidden" name="action" value="confirm_margin_config" />';

				// Form sélection
				//$print .= '<p><hr /></p>';
				$print .= '<p>Affecter les produits à la catégorie principale : </p>';
                $formcategory = new FormCategory($this->db);
                $print .= $formcategory->getFilterBox(Categorie::TYPE_PRODUCT, []);

				// Form sélection
				$print .= '<p>Affecter les produits au fournisseur :</p>';
				$print .= '<p>(fournisseur par défaut, lorsque le produit a déjà un prix avec ce fournisseur)</p>';
                $sql = "SELECT s.rowid, s.nom as name, s.code_fournisseur
                    FROM ".$this->db->prefix()."societe as s
                    WHERE s.fournisseur = 1
                    ORDER BY s.nom";
                $q = $this->db->query($sql);
                $print .= '<select name="fk_soc_fournisseur">
                    <option value="">---</option>';
                while($row=$q->fetch_assoc()) {
                    $print .= '<option value="'.$row['rowid'].'">'.$row['name'].'</option>';
                }
                $print .= '</select>';
				
				// Form sélection
				$print .= '<p>Règle de calcul de marge :&nbsp;
                <select id="calc_type" name="margin_calc_type">
                    <option value="">---</option>';
                $calc_type_list = [
                    'sell_price' => ['label'=>'Prix final fixé'],
                    'public_price' => ['label'=>'Prix public fournisseur fixé'],
                    'four_margin_coeff' => ['label'=>'Coeff/Marge fournisseur fixée'],
                    'concurrent' => ['label'=>'Prix similaire à la concurrence'],
                    'category_margin' => ['label'=>'Marge définie par la catégorie'],
                    //'' => ['label'=>''],
                ];
                foreach($calc_type_list as $i=>$j)
                    $print .= '<option value="'.$i.'"'.('category_margin'==$i ?' selected' :'').'>'.$j['label'].'</option>';
                $print .= '</select></p>';
				
				$print .= '<p>Confirmation : <select class="flat width75 marginleftonly marginrightonly" id="confirm" name="confirm"><option value="yes">Oui</option>
<option value="no" selected="">Non</option></select>';
				$print .= '<input class="button valignmiddle confirmvalidatebutton" type="submit" value="Mettre à jour" /></p>';
				$print .= dol_get_fiche_end();
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => $myvalue);
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;

		$error = 0; // Error counter

		if ($this->in_context($parameters, 'productservicelist')) {
			if ($action == 'confirm_margin_config') {
                //var_dump($_POST);
                // Default Category
                $list_categorie_default = GETPOST('search_category_product_list', 'array');
                $fk_categorie_default = !empty($list_categorie_default) ?$list_categorie_default[0] :NULL;
                //var_dump($list_categorie_default, $fk_categorie_default); die();
                $cat = new Categorie($this->db);
                if (!empty($fk_categorie_default))
                    $cat->fetch($fk_categorie_default);
                //$cat->fetch_optionals();
                //var_dump($cat);
                //var_dump($_POST); die();
                $fk_soc_fournisseur = GETPOST('fk_soc_fournisseur');
                $fourn = new Fournisseur($this->db);
                if (!empty($fk_soc_fournisseur))
                    $fourn->fetch($fk_soc_fournisseur);

                $margin_calc_type = GETPOST('margin_calc_type', 'alphanum');

                // 'sell_price' => ['label'=>'Prix final fixé'],
                // 'public_price' => ['label'=>'Prix public fournisseur fixé'],
                // 'concurrent' => ['label'=>'Prix similaire à la concurrence'],
                // 'category_margin' => ['label'=>'Marge définie par la catégorie'],
                if (in_array($margin_calc_type, ['category_margin', 'sell_price', 'public_price', 'concurrent', 'four_margin_coeff'])) {
                    if ($margin_calc_type=='category_margin') {
                        if (!empty($cat) && !empty($cat->id) && empty($cat->array_options['options_margin_coeff'])) {
                            $error++;
                            $this->errors[] = 'Missing coeff on category';
                        }
                    }
                    if ($margin_calc_type=='four_margin_coeff') {
                        if (!empty($fourn) && !empty($fourn->id) && empty($fourn->array_options['options_margin_coeff'])) {
                            $error++;
                            $this->errors[] = 'Missing coeff on fourn';
                        }
                    }

                    $options = [];
                    if (!empty($cat) && !empty($cat->id))
                        $options['cat'] = $cat;
                    if (!empty($fourn) && !empty($fourn->id))
                        $options['fourn'] = $fourn;

                    if (!$error) {
                        $object = new Product($this->db);
                        foreach ($parameters['toselect'] as $objectid) {
                            //echo '<p>COUCOU : '.$objectid.'</p>'; var_dump($margin_calc_type, $options);
                            $object->fetch($objectid);
                            MMIProduct_Price::_errors_reset();
                            $ret = MMIProduct_Price::product_calc_type_update($object, $margin_calc_type, $options);
                            if ($ret < 0) {
                                if (!empty($errors = MMIProduct_Price::_errors_get())) {
                                    //var_dump($errors);
                                    $error += MMIProduct_Price::_error_get();
                                    $this->errors = array_merge($this->errors, $errors);
                                }
                                else {
                                    $error++;
                                    $this->errors[] = 'Wrong calc : '.$object->ref;
                                }
                            }
                        }
                    }
                }
                else {
                    $error++;
                    $this->errors[] = 'Wrong calc type';
                }
            }
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			//$this->errors[] = $errors[0];
			return -1;
		}
	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;

		$error = '';
		$print = '';

		if ($this->in_context($parameters, 'supplier_proposalcard') && $action=='products_add') {
            //var_dump($object);

            $sql = 'SELECT p.label, p.description, p.price_base_type, p.fk_unit, pf.*
                FROM '.MAIN_DB_PREFIX.'product AS p
                INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price AS pf
                    ON pf.fk_product=p.rowid
                WHERE pf.fk_soc='.$object->socid;
            $q = $this->db->query($sql);
            while($row=$q->fetch_assoc()) {
                //var_dump($row);
                $object->addline($row['description'], $row['unitprice'], 1, $row['tva_tx'], $row['txlocaltax1_tx'], $row['txlocaltax2_tx'], $row['fk_product'], $row['remise_percent'], $row['price_base_type'], 0, 0, $type = 0, -1, 0, 0, $row['rowid'], 0, '', 0, $row['ref_fourn'], $row['fk_unit']);
            }
        }

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			return -1;
		}
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = '';
		$print = '';

		if ($this->in_context($parameters, 'supplier_proposalcard')) {
            $link = '?id='.$object->id.'&action=products_add';
            echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIProductsAddAllProducts")."</a>";;
        }
    
		if (! $error) {
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else {
			$this->errors[] = 'Error message';
			return -1;
		}
			
	}

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user;
        
		$error = '';
		$print = '';

		if ($this->in_context($parameters, 'productcard')) {
            $print = "<script type=\"text/javascript\"> $(document).ready(function () { $('input[name=label]').css('width', '100%'); }); </script>";
        }
    
		if (! $error) {
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else {
			$this->errors[] = 'Error message';
			return -1;
		}
			
	}
    function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
        
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            if ($this->fk_supplier) {
				$print .= ', pfp.packaging AS packaging';
			}
        }
    
        if (! $error)  {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    function printFieldListJoin($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            if ($this->categ)
				$print = ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON cp.fk_product = p.rowid';
            if ($this->fk_supplier)
                $print = ' INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price as pfp ON pfp.fk_product = p.rowid';
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            $print = '';
            if ($this->categ)
                $print = ' AND cp.fk_categorie='.$this->categ;
			if ($this->fk_supplier)
				$print = ' AND pfp.fk_soc='.$this->fk_supplier;
        }
        elseif ($this->in_context($parameters, 'stockatdate')) {
            //var_dump($parameters);
            $notnull = GETPOST('notnull');
            if ($notnull)
                $print .= ' AND ps.rowid IS NOT NULL';
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldPreListTitle($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            $print = '<div class="inlin-block">Catégorie <input type="text" name="categ" value="'.$this->categ.'" /></div>';
        }
        elseif ($this->in_context($parameters, 'stockatdate')) {
            //var_dump($parameters);
            $notnull = GETPOST('notnull');
            $print = '<div class="inlin-block"><b>N\'afficher que les produits avec du stock</b> : <input type="checkbox" name="notnull" value="1"'.($notnull ?' checked' :'').' /></div>';
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListFilters($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            if ($this->categ)
                $print .= '&categ='.$this->categ;
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListOption($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            $print = '<input type="hidden" name="categ" value="'.$this->categ.'" />';
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListTitle($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
			if ($this->fk_supplier) {
            	$print = '<td>Emballage</td>';
			}
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            $objp = $parameters['objp'];
			if ($this->fk_supplier) {
				$print = '<td class="right">'.$objp->packaging.'</td>';
			}
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

	function doDisplayMoreInfos($parameters, &$object, &$action, $hookmanager)
    {
		global $conf, $user;

        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'ordercard')) {
			// SI on vient d'ajouter un produit
			// OU on est sur le client caisse
			// OU on utilise le compte doli caisse
			// ALORS focus ajout produit pour faciliter l'utilisation de la douchette
            if(
				($action=='addline' && !empty($conf->global->MMIPRODUCT_ORDER_SEARCH_IDPROD_FOCUS))
				|| (!empty($conf->global->MMIPAYMENTS_CAISSE_USER) && $conf->global->MMIPAYMENTS_CAISSE_USER==$user->id)
				|| (!empty($conf->global->MMIPAYMENTS_CAISSE_COMPANY) && $conf->global->MMIPAYMENTS_CAISSE_COMPANY==$object->thirdparty->id)
			) {
				echo "<script>$(document).ready(function(){ document.location.href = document.location.href+'#search_idprod'; \$('#search_idprod').focus(); });</script>";
			}
        }
    
        if (! $error) {
            $this->resprints = $print;
            return 0; // or return 1 to replace standard code
        }
        else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

	function ObjectExtraFields($parameters, &$object, &$action, $hookmanager)
	{
		$error = 0; // Error counter
		$print = '';
		
		// @todo : mettre les champs dans le module mmiproduct, ne laisser que la partie calcul auto
		if ($this->in_context($parameters, 'pricesuppliercard'))
		{
			global $conf, $langs;

			$form = $parameters['form'];
			$usercancreate = $parameters['usercancreate'];
			// disabled @todo make it realley editable...
			$usercancreate = false;

			// public_price
			print '<tr><td>';
			$textdesc = $langs->trans("ExtrafieldToolTip_public_price");
			$text = $form->textwithpicto($langs->trans("Extrafield_public_price"), $textdesc, 1, 'help', '');
			print $form->editfieldkey($text, 'public_price', $object->array_options['options_public_price'], $object, $usercancreate, 'amount:6');
			print '</td><td>';
			print $form->editfieldval($text, 'public_price', $object->array_options['options_public_price'], $object, $usercancreate, 'amount:6');
			print '</td></tr>';
		}

		if (! $error)
		{
			$this->resprints = $print;
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}

ActionsMMIProduct::__init();

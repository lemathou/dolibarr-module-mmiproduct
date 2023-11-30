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

dol_include_once('custom/mmicommon/class/mmi_actions.class.php');

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

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		if ($this->in_context($parameters, 'supplier_proposalcard') && $action=='products_add') {
            //var_dump($object);

            $sql = 'SELECT p.label, p.description, p.price_base_type, p.fk_unit, pf.*
                FROM '.MAIN_DB_PREFIX.'product AS p
                INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price AS pf
                    ON pf.fk_product=p.rowid
                WHERE pf.fk_soc='.$object->socid;
            $q = $this->db->query($sql);
            while($row=$q->fetch_assoc()) {
                var_dump($row);
                $object->addline($row['description'], $row['unitprice'], 1, $row['tva_tx'], $row['txlocaltax1_tx'], $row['txlocaltax2_tx'], $row['fk_product'], $row['remise_percent'], $row['price_base_type'], 0, 0, $type = 0, -1, 0, 0, $row['rowid'], 0, '', 0, $row['ref_fourn'], $row['fk_unit']);
            }
        }

		return 0;
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

	function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		$error = '';
		$print = '';

		if ($this->in_context($parameters, 'productcard')) {
            $print = "<script type=\"text/javascript\"> $(document).ready(function () { $('input[name=label]').css('width', '100%'); }); </script>";
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
    function printFieldListSelect($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
        
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            if ($this->fk_supplier) {
				$print .= ', pfp.packaging AS packaging';
			}
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

    function printFieldListJoin($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            if ($this->categ)
				$print = ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON cp.fk_product = p.rowid';
            if ($this->fk_supplier)
                $print = ' INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price as pfp ON pfp.fk_product = p.rowid';
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

    function printFieldListWhere($parameters, &$object, &$action, $hookmanager)
    {
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

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldPreListTitle($parameters, &$object, &$action, $hookmanager)
    {
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

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListFilters($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            if ($this->categ)
                $print .= '&categ='.$this->categ;
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

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListOption($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            $print = '<input type="hidden" name="categ" value="'.$this->categ.'" />';
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

    /**
     * Semble servir à afficher des filtrer globaux
     */
    function printFieldListValue($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'stockreplenishlist')) {
            //var_dump($parameters);
            $objp = $parameters['objp'];
			if ($this->fk_supplier) {
				$print = '<td class="right">'.$objp->packaging.'</td>';
			}
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

	function doDisplayMoreInfos($parameters, &$object, &$action, $hookmanager)
    {
        $error = 0; // Error counter
        $print = '';
    
        if ($this->in_context($parameters, 'ordercard')) {
			global $user, $conf;
			
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

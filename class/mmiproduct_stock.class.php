<?php

dol_include_once('custom/mmicommon/class/mmi_delayed.class.php');
// @todo if conf active mmiprestasync
dol_include_once('/custom/mmiprestasync/class/mmi_prestasync.class.php');
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * Décale l'execution à la mort de l'objet => à la fin du script puisque singleton
 */
class MMIProduct_Stock extends MMI_Delayed_1_0
{

// CLASS

/**
 * @var MMIProduct_Stock $_instance
 */
protected static $_instance;

// OBJECT

public function execute_value($value)
{
	$this->stock_calculate($value);
}

/**
 * On regarde dans tous les stocks.
 * @todo : Envisager une option pour filtrer
 */
public function stock_calculate($fk_product)
{
	global $conf, $user;
	
	//die();

	// Emplacements de stock
	$sql = 'SELECT p.fk_default_warehouse, s.rowid, s.fk_entrepot
		FROM `'.MAIN_DB_PREFIX.'product` p
		LEFT JOIN `'.MAIN_DB_PREFIX.'product_stock` s ON s.fk_product=p.rowid
		WHERE p.rowid='.$fk_product;
	//echo '<pre>'.$sql.'</pre>'; die();
	$q = $this->db->query($sql);
	foreach($q as $row) {
		//var_dump($row);
		// @todo choper le premier emplacement de stock qui vient !
		if (empty($fk_product_stock)) {
			$fk_product_stock = $row['rowid'];
		}
		// @todo choper le bon entrepot, regarder le stock ce sera mieux
		if (empty($fk_entrepot)) {
			if (!empty($row['fk_default_warehouse'])) {
				$fk_entrepot = $row['fk_default_warehouse'];
				break;
			}
			elseif (!empty($row['fk_entrepot'])) {
				$fk_entrepot = $row['fk_entrepot'];
				break;
			}
		}
	}
	if (empty($fk_entrepot))
		$fk_entrepot = 1;
	//var_dump($fk_entrepot);
	//trigger_error($fk_entrepot);

	// sync associated product_stock after update
	if (!empty($fk_product_stock) && class_exists('mmi_prestasync'))
		mmi_prestasync::ws_trigger('stock', 'product_stock', 'osync', $fk_product_stock);
		
	// On calcule le stock des produits à DDM non courte

	$sql = 'SELECT p.rowid, pc.qty, p.stock as qte
		FROM `'.MAIN_DB_PREFIX.'product_association` pc
		LEFT JOIN `'.MAIN_DB_PREFIX.'product_extrafields` pp2 ON pp2.fk_object=pc.fk_product_pere
		INNER JOIN `'.MAIN_DB_PREFIX.'product` p ON p.rowid=pc.fk_product_fils
		WHERE pc.fk_product_pere='.$fk_product.'
			AND pc.qty>0
		GROUP BY p.rowid';
	//echo '<pre>'.$sql.'</pre>';
	//trigger_error($sql);

	$q = $this->db->query($sql);
	$qte = NULL;

	// Le plus petit
	foreach($q as $row) {
		//var_dump($row);
		//trigger_error($row);
		// Prise en compte du stock réservé (commandes en cours) par produit
		$row['qty_reserved'] = $this->reserved_qty($row['rowid']);
		$qte_new = floor(round($row['qte']/$row['qty'], 2));
		//$row['qte_tot'];
		if (is_null($qte) || $qte_new<$qte)
			$qte = $qte_new;
	}
	//echo '<pre>'.$qte.'</pre>';
	//die();
	
	// On n'a pas a prendre en compte les encours de kit car on a systématiquement les élements qui le composent dans les commandes.

	$product = new Product($this->db);
	$product->fetch($fk_product);

	// Pas de mouvement si pas de changement
	if ($product->stock_reel != $qte) {
		$sens = $qte-$product->stock_reel > 0 ?0 :1;
		$qte = $product->stock_reel-$qte > 0 ?$product->stock_reel-$qte :$qte-$product->stock_reel;
		//trigger_error($qte);

		if ($qte > 0)
			$res = $product->correct_stock(
				$user,
				$fk_entrepot,
				$qte,
				$sens,
				'Recalcul kit depuis composant',
				0,
				date('YmdHis'),
				'',
				NULL,
				1 // Disable cascade subproducts
			);
	}

	return true;
}

/**
 * @todo prendre en compte les différents types de paramétrages de calcul de stock théorique
 * On ne compte que ce qui a été ajouté via un kit, le reste étant
 */
public function reserved_qty($fk_product)
{
	global $conf;

	$qty = 0;

	// ATTENTION je ne considère pas les commandes en brouillon !!

	// Commandes en  
	// c.fk_statut IN (1, 2) => validés, envoi en cours
	// st.statut >= 1 => Statut presta qui renvoie une commande valide
	$sql = 'SELECT COUNT(DISTINCT c.fk_soc) as nb_customers, COUNT(DISTINCT c.rowid) as nb, COUNT(cd.rowid) as nb_rows, SUM(cd.qty) as qty
		FROM '.MAIN_DB_PREFIX.'commandedet as cd
		INNER JOIN '.MAIN_DB_PREFIX.'commande as c ON c.rowid = cd.fk_commande
		INNER JOIN '.MAIN_DB_PREFIX.'product as cd2 ON cd2.fk_object = cd.rowid
		WHERE cd.fk_product = '.$fk_product.'
			AND c.fk_statut IN (1, 2)';
	//trigger_error($sql);
	$q = $this->db->query($sql);
	foreach($q as $row) {
		//trigger_error($row);
		$qty += $row['qty'] ?: 0;
	}

	// Expéditions effectuées sur les commandes en cours
	// c.fk_statut IN (1, 2) => validés, envoi en cours
	// e.fk_statut IN (1, 2) => validated et closed
	$sql = 'SELECT COUNT(DISTINCT e.fk_soc) as nb_customers, COUNT(DISTINCT e.rowid) as nb, COUNT(ed.rowid) as nb_rows, SUM(ed.qty) as qty
		FROM '.MAIN_DB_PREFIX.'expeditiondet as ed
		INNER JOIN '.MAIN_DB_PREFIX.'commandedet as cd ON cd.rowid=ed.fk_origin_line
		LEFT JOIN '.MAIN_DB_PREFIX.'commandedet_extrafields as cd2 ON cd2.fk_object = cd.rowid
		INNER JOIN '.MAIN_DB_PREFIX.'commande as c ON c.rowid = cd.fk_commande
		INNER JOIN '.MAIN_DB_PREFIX.'expedition as e ON e.rowid = ed.fk_expedition
		WHERE cd.fk_product = '.$fk_product.'
			AND c.fk_statut IN (1, 2)
			AND e.fk_statut IN (1, 2)';
	//trigger_error($sql);
	$q = $this->db->query($sql);
	foreach($q as $row) {
		//trigger_error($row);
		$qty -= $row['qty'] ?: 0;
	}

	//var_dump($row);

	return $qty;
}

}

MMIProduct_Stock::__init();

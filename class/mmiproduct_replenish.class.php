<?php

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

class MMIProduct_Replenish extends MMI_Generic
{

// CLASS

/**
 * On va recalculer les seuils de réapprovisionnement et d'alerte
 * en fonction des commandes passées sur l'année écoulée
 * et mettre à jour les produits
 * 
 * On va aussi recalculer la consommation par jour, semaine, mois, année
 * On pourra stocker les données (stats) dans une table dédiée
 * On pourra ainsi faire des stats poussées et afficher des graphs facilement sur les produits (les plus commandés)
 * et ajuster les seuils en conséquence
 * 
 * On va considérer le délai de réappro d'un fournisseur, et de réception, paramétrés en extrafield
 * On calculera aussi les délais moyen, et ecart type de réappro et de réception des commandes
 * 
 * Contraintes alimentaires / périssabilité :
 * Stock maximum autorisé = min(capacité, quantité vendable avant péremption).
 * 
 */

const MOD_NAME = 'mmiproduct';


public static function stock_replenish_seuils_refresh()
{
	$sql = 'UPDATE llx_product p2'
		.' INNER JOIN ('
		.' 	SELECT p.rowid, p.label,'
		.' 		SUM(cd.qty) qty_tot, ROUND(AVG(cd.qty), 2) qty_moy, ROUND(MAX(cd.qty), 2) qty_max, COUNT(DISTINCT c.rowid) cmd_nb,'
		.' 		SUM(cd.qty)/365*2*30 qty_reappro, SUM(cd.qty)/365*3*7 qty_alert,'
		.' 		IF(MAX(cd.qty)+AVG(cd.qty) > SUM(cd.qty)/365*2*30, CEIL(MAX(cd.qty)+AVG(cd.qty)), CEIL(SUM(cd.qty)/365*2*30)) qty_reappro_opti, IF((2*MAX(cd.qty)+AVG(cd.qty))/3 > SUM(cd.qty)/365*3*7, CEIL((2*MAX(cd.qty)+AVG(cd.qty))/3), CEIL(SUM(cd.qty)/365*3*7)) qty_alert_opti'
		.' 	FROM `llx_commande` c'
		.' 	INNER JOIN `llx_commandedet` cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `llx_product` p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut>=1 AND p.rowid NOT IN (1, 2) AND DATEDIFF (c.date_commande, NOW())>=-365'
		.' 	GROUP BY p.rowid'
		.' ) AS p3'
		.' ON p3.rowid=p2.rowid'
		.' SET p2.desiredstock=p3.qty_reappro_opti, p2.seuil_stock_alerte=p3.qty_alert_opti';

	echo $sql;

}

public static function stock_supplier_replenish_infos()
{
	// Récupérer le délai de réception (entre validation commande et réception)
	// Actuellement on n'a pas la bonne valeur
	// Il faudra donc une valeur par défaut dans le fournisseur
	// On pourra considérer pour un fournisseur que la valeur saisie est celle pour tous ses produits
	// Il faut pouvoir surcharger pour certains produits non standard
	// Année avant période avant
	$sql_base = 'SELECT p.rowid, p.label,'
		.' 		SUM(cd.qty) qty_tot,'
		.'  	ROUND(AVG(cd.qty), 2) qty_moy,'
		.'  	ROUND(MAX(cd.qty), 2) qty_max,'
		.'  	COUNT(DISTINCT c.rowid) cmd_nb,'
		.' 	FROM `llx_commande_fournisseur` c'
		.' 	INNER JOIN `llx_commande_fournisseurdet` cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `llx_product` p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut${c_fk_status}'; // Statut commande expédiée

}

public static function stock_replenish_batch_delay()
{
	// Récupérer le délai de DDM ou DLC moyen à réception de marchandise par produit
	// Dans le but de limiter les commandes sur les produits périssables
		$sql_base = 'SELECT p.rowid, p.label, DATEDIFF(rd.sellby, r.date_valid) ddm_delay'
			.' 	FROM `llx_commande_fournisseur` c'
			.' 	INNER JOIN `llx_commande_fournisseurdet` cd ON cd.fk_commande=c.rowid'
			.' 	INNER JOIN `llx_product` p ON p.rowid=cd.fk_product'
			.' 	INNER JOIN `llx_commande_fournisseur_dispatch` rd ON rd.fk_commandefourndet=cd.rowid'
			.' 	INNER JOIN `llx_reception` r ON r.rowid=rd.fk_reception'
		.' 	WHERE c.fk_statut${c_fk_status}'; // Statut commande recue

}

public static function stock_conso_regen()
{
	$delai = 90; // Délai en jours pour la période "avant"

	// Année avant période avant
	$sql_base = 'SELECT p.rowid, p.label,'
		.' 		SUM(cd.qty) qty_tot,'
		.'  	ROUND(AVG(cd.qty), 2) qty_moy,'
		.'  	ROUND(MAX(cd.qty), 2) qty_max,'
		.'  	COUNT(DISTINCT c.rowid) cmd_nb,'
		.' 	FROM `llx_commande` c'
		.' 	INNER JOIN `llx_commandedet` cd ON cd.fk_commande=c.rowid'
		.' 	INNER JOIN `llx_product` p ON p.rowid=cd.fk_product'
		.' 	WHERE c.fk_statut${c_fk_status}' // Statut commande expédiée
		.'    AND p.fk_product_type=0'; // Uniquement produits stockés, exclure services;

	// Année avant période avant (pour comparaison avec maintenant)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.(365+$delai)
		.'    AND DATEDIFF (c.date_commande, NOW())<-'.(365)
		.' 	GROUP BY p.rowid';
	str_replace('${c_fk_status}', '=2', $sql); // Statut commande expédiée
	echo $sql;
	
	// Année avant période après (pour projection)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.(365)
		.'    AND DATEDIFF (c.date_commande, NOW())<-'.(365-$delai)
		.' 	GROUP BY p.rowid';
	str_replace('${c_fk_status}', '=2', $sql); // Statut commande expédiée
	echo $sql;

	// Année courante période avant (pour comparaison avec avant)
	$sql = $sql_base
		.'    AND DATEDIFF (c.date_commande, NOW())>=-'.$delai
		.' 	GROUP BY p.rowid';
	str_replace('${c_fk_status}', '>=1', $sql); // Statut commande expédiée
	echo $sql;

}

public static function stock_replenish_graph($fk_product)
{

}

}

MMIProduct_Replenish::__init();

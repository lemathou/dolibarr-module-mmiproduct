<?php
/**
 * Copyright (C) 2026       MMI Mathieu Moulin      <contact@iprospective.fr>
 */

/**
 * Bascule des produits d'un fournisseur vers un autre — RM3235.
 *
 * Cas d'usage : un distributeur change (ex. NT FOOD / MASSIMOZERO → Morato Group).
 * Contrairement à la fusion de tiers, on ne supprime RIEN : le fournisseur source garde
 * son historique (commandes, factures) ; seuls ses prix d'achat changent de tiers.
 *
 * Chaque ligne est déplacée puis son trigger SUPPLIER_PRODUCT_BUYPRICE_MODIFY est rejoué :
 * le trigger mmiproduct (RM3233) bascule alors le fournisseur par défaut du produit vers la
 * cible — puisque la source n'a plus de prix — et mmiprestasync notifie la synchro.
 * `ProductFournisseur::update_buyprice()` ne sait pas changer le fournisseur d'une ligne
 * existante (la colonne fk_soc est absente de son UPDATE) : d'où le SQL direct suivi du
 * trigger, même mécanique que le patch du coeur de RM3234.
 */
class mmi_supplier_switch
{
	/**
	 * Ce que la bascule ferait, sans rien écrire.
	 *
	 * @param DoliDB $db
	 * @param int    $from  tiers source
	 * @param int    $to    tiers cible (0 ou -1 : on ne calcule que les compteurs de la source,
	 *                      ce dont l'écran de confirmation a besoin avant le choix de la cible)
	 * @return array ['lines'=>int, 'products'=>int, 'defaults'=>int, 'collisions'=>string[], 'error'=>string]
	 */
	public static function preview($db, $from, $to)
	{
		$r = array('lines'=>0, 'products'=>0, 'defaults'=>0, 'collisions'=>array(), 'error'=>'');
		$from = (int) $from;
		$to = (int) $to;
		if ($from <= 0 || ($to > 0 && $from === $to)) {
			$r['error'] = 'Fournisseur source ou cible invalide.';
			return $r;
		}

		$sql = 'SELECT COUNT(*) as nb, COUNT(DISTINCT fk_product) as nbprod
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc='.$from;
		if ($resql = $db->query($sql)) {
			$obj = $db->fetch_object($resql);
			$r['lines'] = (int) $obj->nb;
			$r['products'] = (int) $obj->nbprod;
		}

		$sql = 'SELECT COUNT(*) as nb FROM '.MAIN_DB_PREFIX.'product_extrafields WHERE fk_soc_fournisseur='.$from;
		if ($resql = $db->query($sql)) {
			$r['defaults'] = (int) $db->fetch_object($resql)->nb;
		}

		if ($to <= 0) {
			return $r;
		}

		// Clé unique uk_product_fournisseur_price_ref (ref_fourn, fk_soc, quantity, entity)
		$sql = 'SELECT DISTINCT o.ref_fourn
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price as o
			INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price as d
				ON d.fk_soc='.$to.' AND d.ref_fourn=o.ref_fourn AND d.quantity=o.quantity AND d.entity=o.entity
			WHERE o.fk_soc='.$from;
		if ($resql = $db->query($sql)) {
			while ($obj = $db->fetch_object($resql)) {
				$r['collisions'][] = $obj->ref_fourn;
			}
		}

		return $r;
	}

	/**
	 * Bascule effective. Transaction : tout passe ou rien.
	 *
	 * @param DoliDB $db
	 * @param User   $user
	 * @param int    $from
	 * @param int    $to
	 * @param bool   $deactivate  désactiver le tiers source s'il n'a plus aucun produit
	 * @return array ['ok'=>bool, 'moved'=>int, 'products'=>int, 'deactivated'=>bool, 'error'=>string]
	 */
	public static function run($db, $user, $from, $to, $deactivate = false)
	{
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

		$res = array('ok'=>false, 'moved'=>0, 'products'=>0, 'deactivated'=>false, 'error'=>'');
		$from = (int) $from;
		$to = (int) $to;

		if ($to <= 0) {
			$res['error'] = 'Aucun fournisseur cible sélectionné.';
			return $res;
		}
		$preview = static::preview($db, $from, $to);
		if ($preview['error'] !== '') {
			$res['error'] = $preview['error'];
			return $res;
		}
		if (!empty($preview['collisions'])) {
			$res['error'] = 'Bascule impossible : le fournisseur cible a déjà un prix d\'achat pour '
				.count($preview['collisions']).' référence(s) de la source ('
				.implode(', ', array_slice($preview['collisions'], 0, 10))
				.(count($preview['collisions']) > 10 ? ', …' : '').'). Supprimez ou renommez ces lignes d\'abord.';
			return $res;
		}
		if (empty($preview['lines'])) {
			$res['error'] = 'Ce fournisseur n\'a aucun prix d\'achat à basculer.';
			return $res;
		}

		// Lignes à déplacer, relevées avant l'UPDATE
		$lines = array();
		$sql = 'SELECT rowid, fk_product FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc='.$from;
		$resql = $db->query($sql);
		if (!$resql) {
			$res['error'] = $db->lasterror();
			return $res;
		}
		while ($obj = $db->fetch_object($resql)) {
			$lines[] = array('rowid'=>(int) $obj->rowid, 'fk_product'=>(int) $obj->fk_product);
		}

		$db->begin();

		$sql = 'UPDATE '.MAIN_DB_PREFIX.'product_fournisseur_price SET fk_soc='.$to.' WHERE fk_soc='.$from;
		if (!$db->query($sql)) {
			$res['error'] = $db->lasterror();
			$db->rollback();
			return $res;
		}

		// Un trigger par ligne déplacée : c'est lui qui met à jour le fournisseur par défaut
		// du produit (RM3233) et qui notifie la synchro Presta.
		$products = array();
		foreach ($lines as $line) {
			$prodfourn = new ProductFournisseur($db);
			$prodfourn->id = $line['fk_product'];
			$prodfourn->product_id = $line['fk_product'];
			$prodfourn->product_fourn_price_id = $line['rowid'];
			$prodfourn->fourn_id = $to;
			if ($prodfourn->call_trigger('SUPPLIER_PRODUCT_BUYPRICE_MODIFY', $user) < 0) {
				$res['error'] = $prodfourn->error ? $prodfourn->error : 'Erreur lors du déclenchement des triggers.';
				$db->rollback();
				return $res;
			}
			$products[$line['fk_product']] = true;
		}

		// Filet : les produits dont la source reste le fournisseur par défaut — ceux qui n'ont
		// aucune ligne de prix chez elle, et ceux qu'un trigger désactivé aurait laissés de
		// côté. On passe par l'objet Product et NON par un UPDATE direct : c'est ce qui
		// déclenche PRODUCT_MODIFY, donc la notification de synchro Presta (RM3289 — sans
		// cela le produit restait chez l'ancien fournisseur côté PrestaShop).
		$orphelins = array();
		$sql = 'SELECT fk_object FROM '.MAIN_DB_PREFIX.'product_extrafields WHERE fk_soc_fournisseur='.$from;
		$resql = $db->query($sql);
		if (!$resql) {
			$res['error'] = $db->lasterror();
			$db->rollback();
			return $res;
		}
		while ($obj = $db->fetch_object($resql)) {
			$orphelins[] = (int) $obj->fk_object;
		}
		foreach ($orphelins as $fk_product) {
			$product = new Product($db);
			if ($product->fetch($fk_product) <= 0) {
				continue;
			}
			$product->array_options['options_fk_soc_fournisseur'] = $to;
			if ($product->update($product->id, $user) < 0) {
				$res['error'] = $product->error ? $product->error : 'Erreur de mise à jour du produit '.$fk_product;
				$db->rollback();
				return $res;
			}
			$products[$fk_product] = true;
		}

		if ($deactivate && static::is_empty($db, $from)) {
			$soc = new Societe($db);
			if ($soc->fetch($from) > 0) {
				$soc->status = 0;
				if ($soc->setStatut(0) < 0) {
					$res['error'] = $soc->error;
					$db->rollback();
					return $res;
				}
				$res['deactivated'] = true;
			}
		}

		$db->commit();

		$res['ok'] = true;
		$res['moved'] = count($lines);
		$res['products'] = count($products);
		return $res;
	}

	/**
	 * Le tiers n'a plus aucun produit rattaché (ni prix d'achat, ni fournisseur par défaut) ?
	 *
	 * @param DoliDB $db
	 * @param int    $socid
	 * @return bool
	 */
	public static function is_empty($db, $socid)
	{
		$socid = (int) $socid;
		$sql = 'SELECT
			(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc='.$socid.') as nbprices,
			(SELECT COUNT(*) FROM '.MAIN_DB_PREFIX.'product_extrafields WHERE fk_soc_fournisseur='.$socid.') as nbdefaults';
		if (!($resql = $db->query($sql))) {
			return false;
		}
		$obj = $db->fetch_object($resql);
		return empty($obj->nbprices) && empty($obj->nbdefaults);
	}
}

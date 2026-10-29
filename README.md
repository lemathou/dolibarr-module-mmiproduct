# MMIProduct — « MMI Gestion produit avancée »

Module Dolibarr (éditeur **iProspective / Mathieu Moulin**, GPL v3) qui étend la gestion produit : calcul de marge et de prix de vente, empilement des coûts de revient, veille concurrentielle, réapprovisionnement (EOQ / niveau de service), mouvements de stock et inventaire.

- **Famille** : products · **Version** : 1.1.2 · **Dolibarr** : ≥ 11 · **PHP** : ≥ 5.6
- **Dépend de** : `modMMICommon`, `modProduct`

## Domaines fonctionnels

### 1. Prix & marge (onglet produit « Calcul Prix et Marge »)
- Onglet `pricemargin.php` (vue `actions/pricemargin.inc.php` + `tpl/pricemargin.tpl.php`), classe `class/mmiproduct_price.class.php`.
- Extrafields de **marge** sur `product`, `categorie` et `societe` (gated par `MMIPRODUCT_PRICEMARGIN`) : `margin_coeff`, `margin_tx_marque`, `margin_min_coeff`, `margin_min_tx_marque`, `margin_effective_tx`, `margin_effective_coeff`, `margin_desired_coeff`, `margin_desired_tx`, `margin_calc_type` (`sell_price` / `public_price` / `concurrent` / `category_margin`), `margin_calc_options`.
- Méthodes de calcul du prix de vente selon `margin_calc_type` (prix final fixé, prix public fournisseur, alignement concurrence, marge de la catégorie) ; précision pilotée par `MMI_PRODUCT_PRICEMARGIN_CALC_DECIMAL`.
- Trigger `InterfaceProduct_PriceMargin` (`interface_99_modMMIProduct_product_pricemargin.class.php`) : recalcul marge / prix de vente, alerte e-mail si le coefficient de marge passe sous la limite.

### 2. Empilement des coûts de revient (consommé par `mmifournisseurprice`)
- Extrafields `price` sur `product` : `logistic_cost_price`, `packaging_cost_price`, `shipping_cost_price`, `misc_cost_price` (toujours créés quand le module est actif).
- Extrafield `shipping_price` sur `product_fournisseur_price` (frais d'acheminement fournisseur saisi) et sur `commande_fournisseur` (frais d'acheminement de la commande).
- ⚠️ **Couplage inter-modules** : ces champs alimentent le calcul du `cost_price` réalisé par le trigger de `mmifournisseurprice`. Ne pas renommer / supprimer sans synchroniser les deux modules.

### 3. Veille concurrentielle (onglet produit « Prix concurrents »)
- Onglet `concurrents.php` (vue `actions/concurrents.inc.php` + `tpl/concurrents.tpl.php`).
- Tables `sql/llx_product_competitor.sql` : `llx_product_competitor` (fk_product, fk_soc, url) + `llx_product_competitor_price` (historique daté). Un concurrent = `societe` avec l'extrafield `competitor = 1`.
- Extrafields produit : `competitor_avg_price`, `competitor_note`. Réglages `MMIPRODUCT_PRICE_CONCURRENT_DAYS_MAX`.
- `competitor_import.php` : import CSV → tables. **⚠️ Actuellement neutralisé** (`die()` ligne 7) — fonctionnalité commencée, non finie. **À conserver** (ne pas supprimer).
- 🟥 **Limitation connue** : selon l'historique de déploiement, les tables `llx_product_competitor*` peuvent ne pas être créées en base si la migration SQL n'a pas tourné.

### 4. Réapprovisionnement (menu Produits ▸ Stock)
- Pages : `replenish.php` (liste de réappro + alertes), `replenish_fourn.php` (analyse réappro par fournisseur — calcul EOQ : stock de sécurité, point de commande, quantité à commander), `replenish_product_pro.php`, `replenish_refresh.php`, `replenish_note_ajax.php`, `product_reappro.php`. Logique dans `class/mmi_product_replenish.class.php`.
- Extrafields produit : `replenish_batch_ddm_delay`, `replenish_service_level` (90/95/97.5/99 %), `replenish_analysis_days`, `replenish_safety_stock` (+ `supplier_ref`, `fk_soc_fournisseur`, `supplier_packaging`, `replenish_note` sur `societe`).
- Recalcul auto hebdo des stats + override manuel ; niveau de service différencié par segment.
- Hooks `stockreplenishlist`, `stockmovementlist`.

### 5. Stock & inventaire
- `stockmvt.php` : régularisation (ajoute un mouvement manquant pour que le stock = somme des mouvements).
- `inventory_maj.php` : mise à jour d'inventaire depuis CSV.
- Trigger `InterfaceProduct_Stock_Composed` : stock des produits composés (box/kit).
- Hook `stockatdate` (stock à une date donnée, limité aux produits à historique réel).

### 6. Divers
- Trigger `InterfaceProduct_Fournisseur` (`interface_99_modMMIProduct_product_fournisseur.class.php`) : logique prix fournisseur (réf, remise, fournisseur par défaut via `MMI_PRODUCT_PRODUCT_DEFAULT_SUPPLIER_UPDATE`).
- Extrafields produit : `public_price`, `compose`, `custom`, `supplier_direct_delivery`, `garantie`, `season_date_begin/end`, `fk_categorie_default`.
- Catégorie « univers » par défaut du produit ; suivi des produits clients PRO (`MMIPRODUCT_PRO_PRODUCT_CATEGORY`).
- `class/api_mmiproduct.class.php` : endpoints API REST.
- Hook `formAddProductToDocumentCard` : affichage du stock (DDM/DLC) à l'ajout d'un produit sur une commande fournisseur.

## Configuration (constantes principales)
`MMIPRODUCT_PRICEMARGIN` (active la famille marge / concurrent), `MMI_PRODUCT_PRICEMARGIN_CALC_DECIMAL`, `MMI_PRODUCT_PRODUCT_DEFAULT_SUPPLIER_UPDATE`, `MMIPRODUCT_FIELD_PUBLIC_PRICE`, `MMIPRODUCT_FIELD_COMPOSED`, `MMIPRODUCT_FIELD_CUSTOM`, `MMIPRODUCT_FIELD_SUPPLIER_DIRECT_DELIVERY`, `MMIPRODUCT_FIELD_SEASON_DATE`, `MMIPRODUCT_PRO_PRODUCT_CATEGORY`, `MMIPRODUCT_PRICE_CONCURRENT_DAYS_MAX`.

## Hooks
`stockreplenishlist`, `stockmovementlist`, `productservicelist`, `stockatdate`, `productcard`, `propalcard`, `ordercard`, `invoicecard`, `ordersuppliercard`, `supplier_proposalcard`, `pricesuppliercard`.

## Permissions
`pricemargin/view` (voir l'onglet Calcul Prix et Marge), `pricemargin/admin` (administrer).

## Limitations connues / à traiter
- Tables `llx_product_competitor*` potentiellement non créées en base ; `competitor_import.php` neutralisé (`die()`).
- Beaucoup d'extrafields de la famille marge / concurrent ne sont créés que si `MMIPRODUCT_PRICEMARGIN` est activé.
- Libellés anglais (en_US) incomplets.
- Quelques traces de debug (`var_dump`) subsistent dans des pages encore en évolution (réappro, import concurrents) — à nettoyer une fois ces fonctionnalités stabilisées.
- Code en cours de normalisation et de mise en compatibilité **Dolibarr v19 → v22** (branche `1653-dolibarr-19-to-22-clean-migration`).

## Licence
Code : GPL v3 (`COPYING`). Documentation : GFDL.

## Prérequis

- Dolibarr 20.0 (branche `20.0`).
- Modules MMI : `mmicommon`. **`mmicommon` doit être activé en premier.**
- Modules cœur : `Product`.

## Installation

1. Copier dans `htdocs/custom/mmiproduct`.
2. Activer `mmicommon` puis le module.
3. Vérifier les droits.

## Licence

GPLv3+ (voir `COPYING`), documentation GFDL.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md).

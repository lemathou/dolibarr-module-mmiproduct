# Changelog — MMIProduct (« MMI Gestion produit avancée »)

Module Dolibarr d'iProspective. Historique reconstruit depuis git (non tagué — regroupé par thème ; branche d'intégration `19.0`).

## Non publié — branche `1653-dolibarr-19-to-22-clean-migration` (2026-06)
- Documentation : `README.md` et `CHANGELOG.md` réels (remplacement du boilerplate ModuleBuilder), à jour de la v1.1.2 (réappro EOQ, empilement de coûts, produits PRO).
- Libellés extrafields de coût différenciés en fr_FR (les 3 « Frais d'acheminement » identiques distingués) + ajoutés en en_US ; libellé `packaging_cost_price` ajouté.
- Correction de coquilles de libellés.
- Nettoyage des traces ModuleBuilder résiduelles (`modulebuilder.txt`, `.tx/config`, `build/makepack-*.conf`, `mmiproductindex.php`, clés `MyPageName`/`MyWidget`).
- En cours : normalisation et préparation compatibilité Dolibarr v19 → v22.

## Réapprovisionnement — EOQ & niveau de service (2024-2026)
- Réappro étape 2 : recalcul auto hebdo + override manuel (RM324). Stabilisation page fournisseur + `stats_refresh` (RM1444). Fiabilisation `replenish.php` : bugs, code mort, stock projeté (RM1870).
- Niveau de service différencié par segment + câblage StockCheck (RM1854). Recalcul stats+seuils en AJAX par paquets (RM1864).
- Page de stats réappro, extrafields réappro (`replenish_service_level`, `replenish_analysis_days`, `replenish_safety_stock`, `replenish_batch_ddm_delay`), liste fournisseurs (vues alternatives), exclusion des fournisseurs sans produits Presta actifs, qté de commande, options d'affichage.

## Prix fournisseur sur documents & packaging
- Prix unitaire fournisseur sur les lignes de document (`19.0-document-fournisseur-price`). Arrondis/décimales du calcul HT & TTC concurrent. Option `MMIPRODUCT_PRICE_CONCURRENT_DAYS_MAX`.
- Valeur de packaging par défaut sur le produit ; fix d'attribution de packaging par défaut.
- Hook `formAddProductToDocumentCard` : stock avec DDM/DLC à l'ajout d'un produit sur commande fournisseur.

## Prix & marge
- Méthode de calcul du prix de vente (prix min, coeff), taux de marque par catégorie, coeff marge fournisseur. Affichage complet de la marge dans l'aide prix produit.
- Option `cost_price` au clonage de propale. Avertissement marge trop basse à la validation d'un document. Calcul prix de vente automatique avec option décimales. Fix triggers, protection d'exécution des fichiers.

## Veille concurrentielle
- URLs concurrents et historique des prix, import et gestion concurrents, fixes remise / suppression prix concurrent.

## Produits clients PRO
- Suivi produits clients PRO (catégorie `MMIPRODUCT_PRO_PRODUCT_CATEGORY`), option « tous produits » (pas seulement PRO), tri.

## Stock & inventaire
- Script d'ajout d'un mouvement de stock manquant (stock = somme des mouvements). Stock à une date (produits à historique réel). MAJ inventaire depuis CSV. Fix quantité réservée, fix stock entrepôt.

## Catégories / saisons / divers
- Univers (sur-catégorie) associé à la catégorie produit + focus recherche idprod. Dates de saison. Champ `garantie` depuis MMIPrestasync. Affichage dynamique du prix remisé, option ligne produit par fournisseur. Helper `mmi_prices::price_format` (MMICommon).

## Initial — 2023-03
- Numéro de module MMI, fichiers manquants, initialisation.

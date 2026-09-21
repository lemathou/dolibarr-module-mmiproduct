# CHANGELOG MMIPRODUCT FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0.0

Initial version

# 1.0.1

Add option to limit decimals used in automatic sell prices calculation
Fix automatic sell price calculation triggers

# 1.0.2

Fix missing dependences with price_format & co, use new helper class in module MMICommon : mmi_prices::price_format & co.

## 1.1.0

Add formAddProductToDocumentCard hook in objectline_create to display stock with DDM/DLC when adding a product in a supplier order.

# 1.1.1

Fix formAddProductToDocumentCard hook

# 1.1.2

Default supplier (RM3233) : the default supplier (fk_soc_fournisseur, supplier_ref, supplier_packaging) no longer changes on any buy price update. It only changes when it has no active buy price left : the created/modified line takes over, or on deletion the most recent remaining price (agenda event + email alert, MMIPRODUCT_DEFAULT_SUPPLIER_ALERT_EMAIL_TO). Rule in class mmi_default_supplier, unit tests in test/unit, integration test in test/functional.

# 1.1.3

Bascule fournisseur (RM3235) : nouveau bouton « Basculer les produits » sur la fiche d'un fournisseur. Les prix d'achat du fournisseur source passent au fournisseur choisi, réf fournisseur comprise, et le fournisseur par défaut des produits suit (via le trigger de RM3233). Le tiers source n'est jamais supprimé — son historique reste — et peut être désactivé s'il n'a plus aucun produit. Refus explicite en cas de réf fournisseur déjà présente chez la cible. Logique dans mmi_supplier_switch (aperçu + exécution en transaction), partagée d'intention avec l'option de fusion du coeur (RM3234). Nouveau contexte de hook thirdpartycard : rafraîchir MAIN_MODULE_MMIPRODUCT_HOOKS au déploiement. Tests dans test/functional.

# 1.1.4

Bascule fournisseur en ligne de commande (RM3224) : scripts/supplier_switch.php, même traitement que le bouton de la fiche fournisseur, pour basculer plusieurs fournisseurs d'un coup et garder une trace rejouable de l'opération. Simulation par défaut (--confirm pour exécuter), refus si une réf fournisseur existe déjà chez la cible, fichier SQL de restauration écrit avant toute modification, contrôle des compteurs en fin de course.

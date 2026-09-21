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

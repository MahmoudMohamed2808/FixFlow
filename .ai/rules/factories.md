---
paths:
  - database/factories/InvoiceFactory.php
---

# Factories

## Factory states must never violate NOT NULL; activity log needs type entries
InvoiceFactory::forWorkOrder falls back to a fresh Client when the work order has no client (client_id is NOT NULL), so the state is safe to use with internal orders. When adding a new activity-logged model, also extend pages::activity-logs.index subjectTypes + translateAction — otherwise it renders raw class names/slugs.

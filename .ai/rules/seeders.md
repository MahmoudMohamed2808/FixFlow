---
paths:
  - database/seeders/AdminUserSeeder.php
---

# Seeders

## Admin account is configured via ADMIN_* .env vars, not hardcoded
Admin bootstrap on deployment reads config/app.php: 'admin_email' (ADMIN_EMAIL), 'admin_name' (ADMIN_NAME), 'admin_password' (ADMIN_PASSWORD) from .env, defaulting to admin@fixflow.test/'مدير النظام'/'password'. Never deploy with the default password — set ADMIN_PASSWORD in the production .env before `db:seed`.

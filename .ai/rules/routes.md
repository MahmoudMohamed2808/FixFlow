---
paths:
  - routes/console.php
---

# Routes

## Scheduler + database queue are required for daily CMMS jobs/mail in production
Two daily producer jobs: 'cmms:generate-work-orders' and 'inventory:notify-low-stock' both scheduled `->daily()` (midnight). Production must run the scheduler (either a single `schedule:run` cron line, or `schedule:work` via Supervisor). Queue uses the `database` driver (no Redis dependency), so a queue worker is needed for mail notifications to be delivered. Full steps in docs/دليل_النشر_Runbook.md.

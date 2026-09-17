---
paths:
  - 'resources/views/pages/work-orders/**'
---

# Work Orders

## Labor entry auth split: add via updateStatus, delete via update
Labor hour entry on pages::work-orders.show: add gated by `updateStatus` (so the assigned technician can log their own hours), delete gated by `update` (supervisor/admin only). Selecting a technician autofills the rate from users.hourly_rate via updatedAddingLaborUserId. Cost column stays correct automatically through WorkOrderLabor saved/deleted → recalculateCost hooks.

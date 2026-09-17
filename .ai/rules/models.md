---
paths:
  - app/Models/ActivityLog.php
  - app/Models/WorkOrder.php
  - app/Models/AssetCategory.php
---

# Models

## Log activities explicitly via ActivityLog::record() in component methods, not observers
ActivityLog uses UPDATED_AT = null (only created_at managed) and a MorphTo subject. Record via `ActivityLog::record($action, $subject)` in the Livewire mutating methods themselves (actions like "update_status:in_progress", "assigned:{id}", "added_part:{id}", "stock_add:{n}") rather than model observers, to control exactly what gets logged and skip seeder/factory noise.

## Work order cost is parts + labor via recalculate hooks
work_orders.cost = parts + labor, kept fresh automatically by saved/deleted hooks on WorkOrderPart and WorkOrderLabor calling recalculateCost() (updateQuietly, no event loop). Never set cost to parts-only: the completion handler in pages::work-orders.show computes parts sum + labor sum explicitly. FixFlow extras the spec lacks and must keep: WO- auto-code creating hook, ActivityLog::record(), Fortify User traits.

## Asset categories are department-scoped but DB-nullable with backfill
Categories are scoped per department (department_id, scopeForDepartment). DB column is deliberately NULLABLE with a backfill migration (existing rows → first department) instead of the spec's required+unique, to avoid breaking existing data; the cross-department parent guard only fires when both sides have departments. Enforce required-ness at form validation when the categories UI is built.

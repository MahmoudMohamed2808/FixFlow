---
paths:
  - app/Policies/UserPolicy.php
---

# Policies

## Prefer deactivating users over deleting; single role via syncRoles
User model has NO soft-deletes and many relations (work_orders.assigned_to/reported_by, assets.created_by) use nullOnDelete, so deleting a user silently erases their assignment/history links. Prefer toggleActive (is_active) over delete. UserPolicy also forbids toggling/deleting yourself (user.id === self). Users page (pages::users.index) is gated by can:users.manage, uses Spatie syncRoles([roleId]) with a single role per user.

---
paths:
  - tests/Feature/PermissionsMatrixTest.php
---

# Feature

## RBAC matrix assertions need an existing model instance, not the class
In permission/matrix tests, pass an existing model instance for instance-y policy methods (delete, updateStatus, assign, adjustStock) — `$user->can('delete', $asset)` — because those methods have a model param and `can('delete', Asset::class)` throws "Too few arguments". Only class-less methods (create, viewAny) take the ::class.

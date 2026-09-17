---
paths:
  - 'resources/views/pages/invoices/**'
---

# Invoices

## Use toast + return, not abort(422), for SFC business-rule guards
Livewire v4 test `call()` silently swallows abort(422) (action no-ops, no exception surfaces, so `toThrow` never fires). For business-rule guards on SFC actions (invalid invoice status transition, editing items of a paid/cancelled invoice), use Flux::toast(danger) + early return instead of abort_unless(..., 422) — better Arabic UX and the no-op is assertable via DB state. Keep abort(404) only for genuine tampering (e.g. deleting another record's child).

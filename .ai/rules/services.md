---
paths:
  - app/Services/FinancialReportService.php
---

# Services

## FinancialReportService needs mutable Carbon — convert first
Service type-hints mutable Illuminate\Support\Carbon, but now() helper and Livewire date handling yield CarbonImmutable in this app (TypeError). Always convert via Carbon::parse($v->format('Y-m-d H:i:s')) before calling summary/expensesByAsset/incomeByClient — see financialRange() in pages::reports.index and financialSummary() in ReportsExportController. Reports page financial type defaults to year-to-date when no dates given.

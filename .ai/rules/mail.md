---
paths:
  - 'app/Mail/*.php'
---

# Mail

## Invoice PDF = print view; email via InvoiceMailable
No PDF package installed (dependency changes need approval), so invoice "PDF" is a self-contained print view (resources/views/invoices/print.blade.php, inline styles, no Tailwind dependency) at invoices.print route (can:invoices.view) with window.print() toolbar; email uses InvoiceMailable (Arabic RTL, inline styles) sent from pages::invoices.show sendEmail gated by updateStatus, with a guard toast when the client has no email. Global @media print .no-print rule lives in resources/css/app.css.

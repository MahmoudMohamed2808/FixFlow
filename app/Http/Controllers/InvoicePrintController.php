<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoicePrintController extends Controller
{
    /**
     * عرض نسخة صالحة للطباعة والحفظ كـ PDF من المتصفح.
     */
    public function __invoke(Request $request, Invoice $invoice)
    {
        return view('invoices.print', [
            'invoice' => $invoice->load(['client', 'workOrder', 'items']),
        ]);
    }
}

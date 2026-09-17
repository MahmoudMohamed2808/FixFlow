<?php

use App\Mail\InvoiceMailable;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
});

it('lets an admin create an invoice with an auto-generated number', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::invoices.index')
        ->call('openCreate')
        ->set('clientId', $client->id)
        ->set('issueDate', now()->toDateString())
        ->set('tax', 50)
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::where('client_id', $client->id)->first();

    expect($invoice)->not->toBeNull();
    expect($invoice->invoice_number)->toBe('INV-00001');
});

it('requires a client and issue date when creating an invoice', function () {
    $admin = User::factory()->create()->assignRole('Admin');

    Livewire::actingAs($admin)
        ->test('pages::invoices.index')
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['clientId', 'issueDate']);
});

it('lets an admin update an invoice', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->create(['tax' => 0]);

    Livewire::actingAs($admin)
        ->test('pages::invoices.index')
        ->call('openEdit', $invoice->id)
        ->set('tax', 200)
        ->call('save')
        ->assertHasNoErrors();

    expect((float) $invoice->fresh()->tax)->toBe(200.0);
});

it('adds items on the show page and recalculates totals', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->create(['tax' => 100]);

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('itemDescription', 'صيانة تكييف')
        ->set('itemQuantity', 2)
        ->set('itemUnitPrice', 750)
        ->call('addItem')
        ->assertHasNoErrors()
        ->assertSee('1,600.00');

    expect($invoice->fresh()->items)->toHaveCount(1);
    expect($invoice->fresh()->total_amount)->toBe('1600.00');
});

it('marks an invoice as paid from the show page', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->create(['status' => 'sent']);

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('markPaid')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->paid_at)->not->toBeNull();
});

it('refuses to modify items of a paid invoice', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->paid()->create();

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('itemDescription', 'بند مرفوض')
        ->set('itemQuantity', 1)
        ->set('itemUnitPrice', 100)
        ->call('addItem')
        ->assertHasNoErrors();

    expect($invoice->fresh()->items)->toHaveCount(0);
});

it('refuses invalid status transitions', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->paid()->create();

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('markPaid')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
});

it('lets an admin delete an invoice with its items', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->for($invoice, 'invoice')->create();

    Livewire::actingAs($admin)
        ->test('pages::invoices.index')
        ->call('askDelete', $invoice->id)
        ->call('delete')
        ->assertHasNoErrors();

    expect(Invoice::find($invoice->id))->toBeNull();
    expect(InvoiceItem::where('invoice_id', $invoice->id)->count())->toBe(0);
});

it('forbids invoice mutations without permission', function () {
    $viewer = User::factory()->create()->assignRole('Viewer');

    Livewire::actingAs($viewer)
        ->test('pages::invoices.index')
        ->call('openCreate')
        ->assertForbidden();
});

it('forbids viewing an invoice without permission', function () {
    $viewer = User::factory()->create()->assignRole('Viewer');
    $invoice = Invoice::factory()->create();

    $this->actingAs($viewer)
        ->get(route('invoices.show', $invoice))
        ->assertForbidden();
});

it('filters invoices by status and client', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->for($client, 'client')->create(['status' => 'paid']);

    Livewire::actingAs($admin)
        ->test('pages::invoices.index')
        ->set('filterStatus', 'paid')
        ->set('filterClientId', $client->id)
        ->assertSee($invoice->invoice_number);
});

it('emails the invoice to the client address', function () {
    Mail::fake();

    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create(['email' => 'client@example.com']);
    $invoice = Invoice::factory()->for($client, 'client')->create(['status' => 'sent']);

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertSent(InvoiceMailable::class, function ($mail) use ($invoice) {
        return $mail->hasTo('client@example.com')
            && str_contains($mail->envelope()->subject, $invoice->invoice_number);
    });
});

it('refuses to email when the client has no address', function () {
    Mail::fake();

    $admin = User::factory()->create()->assignRole('Admin');
    $client = Client::factory()->create(['email' => null]);
    $invoice = Invoice::factory()->for($client, 'client')->create();

    Livewire::actingAs($admin)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('sendEmail')
        ->assertHasNoErrors();

    Mail::assertNothingSent();
});

it('renders the mailable with items and totals', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->for($client, 'client')->create();
    InvoiceItem::factory()->for($invoice, 'invoice')->create([
        'description' => 'بند البريد',
        'quantity' => 2,
        'unit_price' => 300,
    ]);

    $html = (new InvoiceMailable($invoice->fresh()))->render();

    expect($html)
        ->toContain($invoice->invoice_number)
        ->toContain('بند البريد')
        ->toContain('600.00');
});

it('opens the print view for authorized users only', function () {
    $admin = User::factory()->create()->assignRole('Admin');
    $viewer = User::factory()->create()->assignRole('Viewer');
    $invoice = Invoice::factory()->create();

    $this->actingAs($admin)
        ->get(route('invoices.print', $invoice))
        ->assertOk()
        ->assertSee($invoice->invoice_number);

    $this->actingAs($viewer)
        ->get(route('invoices.print', $invoice))
        ->assertForbidden();
});

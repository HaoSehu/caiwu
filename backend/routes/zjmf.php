<?php

declare(strict_types=1);

use App\Http\Controllers\Zjmf\AuthController;
use App\Http\Controllers\Zjmf\BridgeController;
use App\Http\Controllers\Zjmf\FinanceController;
use App\Http\Controllers\Zjmf\ProductController;
use App\Http\Controllers\Zjmf\ReconcileController;
use App\Http\Controllers\Zjmf\ServiceController;
use App\Http\Controllers\Zjmf\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [BridgeController::class, 'health'])->name('health');
Route::get('/ping', [BridgeController::class, 'health'])->name('ping');

Route::middleware(['zjmf.signature:system.health'])->group(function (): void {
    Route::get('/system/health', [BridgeController::class, 'systemHealth'])->name('system.health');
});

Route::middleware(['zjmf.signature:system.reconcile'])->group(function (): void {
    Route::get('/reconcile/payments', [ReconcileController::class, 'payments'])->name('reconcile.payments');
    Route::get('/reconcile/invoices', [ReconcileController::class, 'invoices'])->name('reconcile.invoices');
});

Route::post('/login_api', [AuthController::class, 'login'])
    ->middleware(['zjmf.signature:auth.login'])
    ->name('auth.login');

Route::get('/user', [AuthController::class, 'user'])
    ->middleware(['zjmf.client:client.read'])
    ->name('user.show');

Route::middleware(['zjmf.client:finance.read'])->group(function (): void {
    Route::get('/invoices', [FinanceController::class, 'invoices'])->name('invoices.index');
    Route::get('/invoices/{id}', [FinanceController::class, 'invoice'])->whereNumber('id')->name('invoices.show');
    Route::get('/invoices/{id}/status', [FinanceController::class, 'invoiceStatus'])->whereNumber('id')->name('invoices.status');
    Route::get('/transactions/funds', [FinanceController::class, 'fundTransactions'])->name('transactions.funds');
});

Route::post('/invoices/{id}/fund', [FinanceController::class, 'payInvoiceByBalance'])
    ->middleware(['zjmf.client:finance.write'])
    ->whereNumber('id')
    ->name('invoices.fund');

Route::middleware(['zjmf.client:payment.read'])->group(function (): void {
    Route::get('/funds', [FinanceController::class, 'funds'])->name('funds.index');
    Route::get('/payments', [FinanceController::class, 'payments'])->name('payments.index');
    Route::get('/payments/{id}', [FinanceController::class, 'payment'])->whereNumber('id')->name('payments.show');
});

Route::post('/funds', [FinanceController::class, 'recharge'])
    ->middleware(['zjmf.client:payment.write'])
    ->name('funds.store');

Route::middleware(['zjmf.client:service.read'])->group(function (): void {
    Route::get('/hosts', [ServiceController::class, 'hosts'])->name('hosts.index');
    Route::get('/hosts/{id}', [ServiceController::class, 'host'])->whereNumber('id')->name('hosts.show');
});

Route::middleware(['zjmf.client:ticket.read'])->group(function (): void {
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/page', [TicketController::class, 'page'])->name('tickets.page');
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');
});

Route::middleware(['zjmf.client:ticket.write'])->group(function (): void {
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->whereNumber('id')->name('tickets.reply');
    Route::post('/tickets/{id}/close', [TicketController::class, 'close'])->whereNumber('id')->name('tickets.close');
});

Route::middleware(['zjmf.signature:product.read'])->group(function (): void {
    Route::get('/products', [ProductController::class, 'products'])->name('products.index');
    Route::get('/productsconfig', [ProductController::class, 'productConfig'])->name('products.config');
    Route::post('/products/total', [ProductController::class, 'productsTotal'])->name('products.total');
    Route::get('/hosts/cates', [ProductController::class, 'categories'])->name('hosts.cates');
});

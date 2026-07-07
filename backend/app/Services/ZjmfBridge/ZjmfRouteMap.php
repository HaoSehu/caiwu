<?php

declare(strict_types=1);

namespace App\Services\ZjmfBridge;

class ZjmfRouteMap
{
    /**
     * @return array<int, array{method: string, path: string, scope: string, type: string, target: string}>
     */
    public function all(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/health',
                'scope' => 'public.health',
                'type' => 'read',
                'target' => 'Zjmf\\BridgeController@health',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/system/health',
                'scope' => 'system.health',
                'type' => 'read',
                'target' => 'Zjmf\\BridgeController@systemHealth',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/reconcile/payments',
                'scope' => 'system.reconcile',
                'type' => 'read',
                'target' => 'Zjmf\\ReconcileController@payments',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/reconcile/invoices',
                'scope' => 'system.reconcile',
                'type' => 'read',
                'target' => 'Zjmf\\ReconcileController@invoices',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/login_api',
                'scope' => 'auth.login',
                'type' => 'read',
                'target' => 'Zjmf\\AuthController@login',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/user',
                'scope' => 'client.read',
                'type' => 'read',
                'target' => 'Zjmf\\AuthController@user',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/invoices',
                'scope' => 'finance.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@invoices',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/invoices/{id}',
                'scope' => 'finance.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@invoice',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/invoices/{id}/status',
                'scope' => 'finance.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@invoiceStatus',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/invoices/{id}/fund',
                'scope' => 'finance.write',
                'type' => 'write',
                'target' => 'Zjmf\\FinanceController@payInvoiceByBalance',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/transactions/funds',
                'scope' => 'finance.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@fundTransactions',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/funds',
                'scope' => 'payment.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@funds',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/funds',
                'scope' => 'payment.write',
                'type' => 'write',
                'target' => 'Zjmf\\FinanceController@recharge',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/payments',
                'scope' => 'payment.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@payments',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/payments/{id}',
                'scope' => 'payment.read',
                'type' => 'read',
                'target' => 'Zjmf\\FinanceController@payment',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/hosts',
                'scope' => 'service.read',
                'type' => 'read',
                'target' => 'Zjmf\\ServiceController@hosts',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/hosts/{id}',
                'scope' => 'service.read',
                'type' => 'read',
                'target' => 'Zjmf\\ServiceController@host',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/tickets',
                'scope' => 'ticket.read',
                'type' => 'read',
                'target' => 'Zjmf\\TicketController@index',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/tickets/page',
                'scope' => 'ticket.read',
                'type' => 'read',
                'target' => 'Zjmf\\TicketController@page',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/tickets',
                'scope' => 'ticket.write',
                'type' => 'write',
                'target' => 'Zjmf\\TicketController@store',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/tickets/{id}',
                'scope' => 'ticket.read',
                'type' => 'read',
                'target' => 'Zjmf\\TicketController@show',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/tickets/{id}/reply',
                'scope' => 'ticket.write',
                'type' => 'write',
                'target' => 'Zjmf\\TicketController@reply',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/tickets/{id}/close',
                'scope' => 'ticket.write',
                'type' => 'write',
                'target' => 'Zjmf\\TicketController@close',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/products',
                'scope' => 'product.read',
                'type' => 'read',
                'target' => 'Zjmf\\ProductController@products',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/productsconfig',
                'scope' => 'product.read',
                'type' => 'read',
                'target' => 'Zjmf\\ProductController@productConfig',
            ],
            [
                'method' => 'POST',
                'path' => '/zjmf/v1/products/total',
                'scope' => 'product.read',
                'type' => 'read',
                'target' => 'Zjmf\\ProductController@productsTotal',
            ],
            [
                'method' => 'GET',
                'path' => '/zjmf/v1/hosts/cates',
                'scope' => 'product.read',
                'type' => 'read',
                'target' => 'Zjmf\\ProductController@categories',
            ],
        ];
    }
}

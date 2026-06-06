import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const invoicePanelPath = resolve(__dirname, '../src/pages/client/invoices/components/InvoicePanel.vue')
const invoiceListPath = resolve(__dirname, '../src/pages/client/invoices/index.vue')
const orderListPath = resolve(__dirname, '../src/pages/client/orders/index.vue')
const paymentListPath = resolve(__dirname, '../src/pages/client/payments/index.vue')
const clientLayoutPath = resolve(__dirname, '../src/layout/ClientLayout/index.vue')
const homeHeroPath = resolve(__dirname, '../src/views/website/Home/components/HomeHeroCarousel.vue')

const invoicePanelSource = readFileSync(invoicePanelPath, 'utf-8')
const invoiceListSource = readFileSync(invoiceListPath, 'utf-8')
const orderListSource = readFileSync(orderListPath, 'utf-8')
const paymentListSource = readFileSync(paymentListPath, 'utf-8')
const clientLayoutSource = readFileSync(clientLayoutPath, 'utf-8')
const homeHeroSource = readFileSync(homeHeroPath, 'utf-8')

for (const [name, source] of [
  ['账单管理', invoiceListSource],
  ['订单管理', orderListSource],
  ['充值记录', paymentListSource],
]) {
  assert.match(
    source,
    /InvoicePanel/,
    `${name} page should render the shared bill panel`,
  )
}

assert.match(
  orderListSource,
  /ORDER_BILL_TYPES/,
  'order management should constrain the shared bill panel to order-like bill types',
)

assert.match(
  paymentListSource,
  /RECHARGE_BILL_TYPES/,
  'recharge records should constrain the shared bill panel to recharge bills',
)

assert.doesNotMatch(
  clientLayoutSource,
  /route\.path\.startsWith\('\/client\/invoices'\)\s*\|\|\s*route\.path\.startsWith\('\/client\/orders'\)/,
  'order management should keep its own sidebar active state while sharing the bill panel',
)

assert.match(
  invoicePanelSource,
  /resolveInvoiceTitle\(row\)/,
  'shared client bill panel should render a normalized title helper',
)

assert.match(
  invoicePanelSource,
  /resolveInvoiceSubtitle\(row\)/,
  'shared client bill panel should render a normalized subtitle helper',
)

assert.doesNotMatch(
  invoicePanelSource,
  /{{[^}]*row\.summary[^}]*}}/s,
  'shared client bill panel must not interpolate the summary object directly',
)

assert.doesNotMatch(
  invoicePanelSource,
  /row\.summary\s*\|\|/,
  'shared client bill panel must not use summary object as a string fallback',
)

assert.doesNotMatch(
  homeHeroSource,
  /发票|开票/,
  'client visible homepage defaults should not advertise invoice issuing',
)

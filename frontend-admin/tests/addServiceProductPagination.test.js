import assert from 'node:assert/strict'

import { fetchAllAddServiceProducts } from '../src/views/admin/Users/detail/composables/addServiceProducts.js'

async function testFetchesEveryActiveProductPageForAddServiceTree() {
  const firstPage = Array.from({ length: 100 }, (_, index) => ({ id: index + 1 }))
  const secondPage = [{ id: 101 }, { id: 102 }, { id: 103 }]
  const calls = []

  const products = await fetchAllAddServiceProducts({
    list: async (params) => {
      calls.push(params)
      return {
        data: {
          list: params.page === 1 ? firstPage : secondPage,
          total: 103,
        },
      }
    },
  }, { status: 1 })

  assert.equal(products.length, 103)
  assert.deepEqual(products.map((product) => product.id).slice(-3), [101, 102, 103])
  assert.deepEqual(calls.map((params) => params.page), [1, 2])
  assert.deepEqual(calls.map((params) => params.page_size), [100, 100])
  assert.ok(calls.every((params) => params.status === 1))
}

await testFetchesEveryActiveProductPageForAddServiceTree()

console.log('addServiceProductPagination tests passed')

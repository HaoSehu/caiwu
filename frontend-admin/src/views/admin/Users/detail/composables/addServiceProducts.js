const ADD_SERVICE_PRODUCTS_PAGE_SIZE = 100

export async function fetchAllAddServiceProducts(productApi, params = {}) {
  const products = []
  let page = 1
  let total = Infinity

  do {
    const res = await productApi.list({
      ...params,
      page,
      page_size: ADD_SERVICE_PRODUCTS_PAGE_SIZE,
    })
    const list = Array.isArray(res.data?.list) ? res.data.list : []
    products.push(...list)

    const reportedTotal = Number(res.data?.total)
    if (Number.isFinite(reportedTotal) && reportedTotal >= 0) {
      total = reportedTotal
    }

    if (!list.length || list.length < ADD_SERVICE_PRODUCTS_PAGE_SIZE) {
      break
    }

    page += 1
  } while (products.length < total)

  return products
}

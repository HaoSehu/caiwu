import request from '@/utils/request'
import {
  normalizeContentDetailPayload,
  normalizeContentListPayload,
  normalizeContentOverviewPayload,
  normalizeSiteHomePayload,
  withNormalizedData,
} from './contentNormalizer'

function normalizeSiteProductParams(payload = {}) {
  return { ...payload }
}

export default {
  config: (config) => request.get('/site/config', config),
  home: (config) => request.get('/site/home', config).then((response) => withNormalizedData(response, normalizeSiteHomePayload)),
  homeHero: (config) => request.get('/site/home-hero', config),
  productTypes: (config) => request.get('/site/product-types', config),
  productGroups: (params, config = {}) => request.get('/site/product-categories', { ...config, params }),
  productGroupChildren: (groupId, config) => request.get(`/site/product-categories/${groupId}/children`, config),
  productGroupCatalog: (groupId, config) => request.get(`/site/product-categories/${groupId}/catalog`, config),
  products: (params, config = {}) => request.get('/site/products', { ...config, params: normalizeSiteProductParams(params) }),
  productStock: (id, config) => request.get(`/site/products/${id}/stock`, config),
  product: (id, config) => request.get(`/site/products/${id}`, config),
  productQuote: (id, data, config) => request.post(`/site/products/${id}/quote`, data, config),
  contentOverview: (config) => request.get('/site/content/overview', config).then((response) => withNormalizedData(response, normalizeContentOverviewPayload)),
  notices: (params, config = {}) => request.get('/site/notices', { ...config, params }).then((response) => withNormalizedData(response, normalizeContentListPayload)),
  noticeDetail: (id, config) => request.get(`/site/notices/${id}`, config).then((response) => withNormalizedData(response, normalizeContentDetailPayload)),
  helpArticles: (params, config = {}) => request.get('/site/help-articles', { ...config, params }).then((response) => withNormalizedData(response, normalizeContentListPayload)),
  helpDetail: (id, config) => request.get(`/site/help-articles/${id}`, config).then((response) => withNormalizedData(response, normalizeContentDetailPayload)),
}

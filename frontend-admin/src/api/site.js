import request from '@/utils/request'

export default {
  config: () => request.get('/site/config'),
}

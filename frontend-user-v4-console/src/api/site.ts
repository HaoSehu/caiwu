import request from '@/utils/request';

export default {
  config: (config?: Record<string, unknown>) => request.get('/site/config', config),
};

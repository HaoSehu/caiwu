<template>
  <div class="api-directory-page">
    <section class="api-layout">
      <t-card :bordered="false" class="api-sidebar">
        <template #title>接口分类</template>
        <div class="api-meta-grid">
          <article>
            <span>接口</span><strong>{{ apiMeta.total || apiItems.length }}</strong>
          </article>
          <article>
            <span>模块</span><strong>{{ apiMeta.moduleCount || apiModules.length }}</strong>
          </article>
          <article>
            <span>权限</span><strong>{{ apiMeta.accessCounts?.permission || 0 }}</strong>
          </article>
          <article>
            <span>登录</span><strong>{{ apiMeta.accessCounts?.auth || 0 }}</strong>
          </article>
        </div>
        <div class="api-category-list">
          <button type="button" :class="{ active: selectedApiModule === 'all' }" @click="selectedApiModule = 'all'">
            <span>全部接口</span>
            <strong>{{ apiItems.length }}</strong>
          </button>
          <button
            v-for="item in apiModules"
            :key="item.key"
            type="button"
            :class="{ active: selectedApiModule === item.key }"
            @click="selectedApiModule = item.key"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.count }}</strong>
          </button>
        </div>
      </t-card>

      <div class="api-main">
        <t-card :bordered="false">
          <div class="api-directory-head">
            <div>
              <h2>API 接口页</h2>
              <p>
                数据来源：{{ apiMeta.dataSource || '-' }}，生成时间：{{ apiMeta.generatedAt || '-' }}，基础地址：{{
                  apiMeta.baseURL || '/api'
                }}
              </p>
            </div>
            <t-button theme="default" variant="base" @click="resetApiFilters">重置</t-button>
          </div>
          <div class="api-filter-grid">
            <t-input v-model="keywordInput" clearable placeholder="搜索路径、权限码、控制器或源码文件">
              <template #suffix-icon><search-icon /></template>
            </t-input>
            <t-select v-model="apiFilters.access" placeholder="访问级别">
              <t-option label="全部级别" value="all" />
              <t-option label="公开" value="public" />
              <t-option label="仅登录" value="auth" />
              <t-option label="登录 + 权限" value="permission" />
            </t-select>
            <t-select v-model="apiFilters.method" placeholder="请求方法">
              <t-option label="全部方法" value="all" />
              <t-option v-for="item in methodOptions" :key="item" :label="item" :value="item" />
            </t-select>
            <t-select v-model="apiFilters.source" placeholder="前端来源">
              <t-option label="全部来源" value="all" />
              <t-option label="管理端前端" value="frontend-admin-v3" />
              <t-option label="官网/用户入口" value="frontend-user-v3-www" />
              <t-option label="用户控制台" value="frontend-user-v4-console" />
              <t-option label="未发现前端调用" value="untracked" />
            </t-select>
          </div>
        </t-card>

        <t-card :bordered="false">
          <template #title>接口明细</template>
          <template #subtitle> 共 {{ apiItems.length }} 条，当前筛选 {{ filteredApiItems.length }} 条 </template>
          <div class="table-scroll">
            <t-table
              row-key="id"
              :data="pagedApiItems"
              :columns="apiColumns"
              hover
              table-layout="fixed"
              aria-label="接口目录列表"
            >
              <template #empty>
                <t-empty :description="hasActiveApiFilters ? '未找到匹配结果' : '暂无数据'" />
              </template>
              <template #category="{ row }">
                <div class="stack-cell">
                  <strong>{{ row.scopeLabel }} / {{ row.moduleLabel }}</strong>
                  <span>{{ row.module }}</span>
                </div>
              </template>
              <template #method="{ row }">
                <t-tag :theme="methodTheme(row.method)" variant="light">{{ row.method }}</t-tag>
              </template>
              <template #path="{ row }">
                <div class="stack-cell">
                  <strong>{{ row.callPath }}</strong>
                  <span>{{ row.backendPath }}</span>
                </div>
              </template>
              <template #access="{ row }">
                <div class="tag-stack">
                  <t-tag :theme="accessTheme(row.access)" variant="light">{{ row.accessLabel }}</t-tag>
                  <t-tag v-if="row.permission" variant="light">{{ row.permission }}</t-tag>
                  <span v-else>无需额外权限码</span>
                </div>
              </template>
              <template #source="{ row }">
                <div class="tag-stack">
                  <t-tag v-for="label in row.sourceAppLabels" :key="label" variant="light">{{ label }}</t-tag>
                  <span v-if="!row.sourceAppLabels.length">未发现前端调用</span>
                </div>
              </template>
              <template #handler="{ row }">
                <div class="stack-cell">
                  <strong>{{ row.handler }}</strong>
                  <span>{{ row.sourceFiles.length }} 个源码命中</span>
                </div>
              </template>
              <template #actions="{ row }">
                <t-button theme="primary" variant="text" @click="copyApiPath(row.backendPath)">复制</t-button>
              </template>
            </t-table>
          </div>
          <div v-if="filteredApiTotal > 0" class="pagination-row">
            <t-pagination
              :current="apiPager.page"
              :page-size="apiPager.page_size"
              :total="filteredApiTotal"
              :page-size-options="apiPager.pageSizeOptions"
              show-jumper
              @change="handleApiPageChange"
            />
          </div>
        </t-card>
      </div>
    </section>
  </div>
</template>
<script setup lang="ts">
import { debounce } from 'lodash-es';
import { SearchIcon } from 'tdesign-icons-vue-next';
import type { PrimaryTableCol } from 'tdesign-vue-next';
import { computed, reactive, ref, watch } from 'vue';

import { PAGE_SIZE_OPTIONS } from '@/constants/pagination';
import apiCatalogData from '@/data/apiCatalog.generated.json';
import { copyToClipboard } from '@/utils/format';

defineOptions({ name: 'AdminApiDirectory' });

interface ApiCatalogItem extends Record<string, unknown> {
  id: string;
  scope: string;
  scopeLabel: string;
  module: string;
  moduleLabel: string;
  method: string;
  methods?: string[];
  callPath: string;
  backendPath: string;
  access: string;
  accessLabel: string;
  permission?: string;
  handler?: string;
  sourceApps: string[];
  sourceAppLabels: string[];
  sourceFiles: string[];
}

interface ApiCatalogMeta {
  total?: number;
  moduleCount?: number;
  dataSource?: string;
  generatedAt?: string;
  baseURL?: string;
  accessCounts?: Record<string, number>;
}

const selectedApiModule = ref('all');
const apiFilters = reactive({
  keyword: '',
  access: 'all',
  method: 'all',
  source: 'all',
});
// 搜索框单独接管输入，debounce 后再写回筛选条件，避免逐字符 filter 全量目录数据
const keywordInput = ref('');
const syncKeyword = debounce((value: string) => {
  apiFilters.keyword = value;
}, 250);
watch(keywordInput, (value) => syncKeyword(value));
// 目录为构建期静态数据，前端分页即可，避免一次渲染全部行
const apiPager = reactive({
  page: 1,
  page_size: PAGE_SIZE_OPTIONS[0],
  pageSizeOptions: PAGE_SIZE_OPTIONS,
});

const apiCatalog = apiCatalogData as { meta?: ApiCatalogMeta; items?: ApiCatalogItem[] };
const apiMeta = apiCatalog.meta || {};
const apiItems = (apiCatalog.items || []).map((item) => ({
  ...item,
  sourceApps: item.sourceApps || [],
  sourceAppLabels: item.sourceAppLabels || [],
  sourceFiles: item.sourceFiles || [],
}));

const apiColumns: PrimaryTableCol<ApiCatalogItem>[] = [
  { colKey: 'category', title: '所属分类', minWidth: 220 },
  { colKey: 'method', title: '方法', width: 100 },
  { colKey: 'path', title: '路径', minWidth: 340 },
  { colKey: 'access', title: '访问控制', minWidth: 210 },
  { colKey: 'source', title: '前端来源', minWidth: 170 },
  { colKey: 'handler', title: '控制器方法', minWidth: 220 },
  { colKey: 'actions', title: '操作', fixed: 'right', width: 90 },
];

// 空态文案区分「未筛选但无数据」与「筛选后无匹配」（docs/DESIGN.md 术语表）
const hasActiveApiFilters = computed(
  () =>
    Boolean(apiFilters.keyword.trim()) ||
    apiFilters.access !== 'all' ||
    apiFilters.method !== 'all' ||
    apiFilters.source !== 'all' ||
    selectedApiModule.value !== 'all',
);
const apiModules = computed(() => {
  const moduleMap = new Map<string, { key: string; label: string; count: number }>();
  apiItems.forEach((item) => {
    const key = `${item.scope}:${item.module}`;
    const current = moduleMap.get(key) || { key, label: `${item.scopeLabel} / ${item.moduleLabel}`, count: 0 };
    current.count += 1;
    moduleMap.set(key, current);
  });
  return Array.from(moduleMap.values()).sort((left, right) => right.count - left.count);
});
const methodOptions = computed(() => Array.from(new Set(apiItems.map((item) => item.method))).sort());
const filteredApiItems = computed(() => {
  const keyword = apiFilters.keyword.trim().toLowerCase();
  return apiItems.filter((item) => {
    if (selectedApiModule.value !== 'all' && `${item.scope}:${item.module}` !== selectedApiModule.value) return false;
    if (apiFilters.access !== 'all' && item.access !== apiFilters.access) return false;
    if (apiFilters.method !== 'all' && item.method !== apiFilters.method) return false;
    if (apiFilters.source === 'untracked' && item.sourceApps.length > 0) return false;
    if (
      apiFilters.source !== 'all' &&
      apiFilters.source !== 'untracked' &&
      !item.sourceApps.includes(apiFilters.source)
    ) {
      return false;
    }
    if (!keyword) return true;
    return [
      item.scopeLabel,
      item.moduleLabel,
      item.method,
      item.callPath,
      item.backendPath,
      item.accessLabel,
      item.permission,
      item.handler,
      ...item.sourceAppLabels,
      ...item.sourceFiles,
    ]
      .join(' ')
      .toLowerCase()
      .includes(keyword);
  });
});
const filteredApiTotal = computed(() => filteredApiItems.value.length);
const pagedApiItems = computed(() => {
  const start = (apiPager.page - 1) * apiPager.page_size;
  return filteredApiItems.value.slice(start, start + apiPager.page_size);
});
// 筛选条件变化后回到第一页，否则会停在越界页看到空表
watch(
  () => [apiFilters.keyword, apiFilters.access, apiFilters.method, apiFilters.source, selectedApiModule.value],
  () => {
    apiPager.page = 1;
  },
);

function handleApiPageChange(pageInfo: { current?: number; pageSize?: number }) {
  const current = Number(pageInfo.current ?? 1);
  const pageSize = Number(pageInfo.pageSize ?? apiPager.page_size);
  if (pageSize !== apiPager.page_size) {
    apiPager.page_size = pageSize;
    apiPager.page = 1;
    return;
  }
  apiPager.page = current;
}

function resetApiFilters() {
  selectedApiModule.value = 'all';
  syncKeyword.cancel();
  keywordInput.value = '';
  apiFilters.keyword = '';
  apiFilters.access = 'all';
  apiFilters.method = 'all';
  apiFilters.source = 'all';
  apiPager.page = 1;
}

function copyApiPath(path: string) {
  void copyToClipboard(path, '接口路径已复制');
}

function methodTheme(method: string) {
  if (method === 'GET') return 'primary';
  if (method === 'POST') return 'success';
  if (method === 'PUT') return 'warning';
  if (method === 'DELETE') return 'danger';
  return 'default';
}

function accessTheme(access: string) {
  if (access === 'permission') return 'warning';
  if (access === 'auth') return 'success';
  return 'default';
}
</script>

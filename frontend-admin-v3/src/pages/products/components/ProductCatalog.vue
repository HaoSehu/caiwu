<template>
  <div class="catalog-page">
    <t-card :bordered="false" class="catalog-shell">
      <aside class="catalog-side">
        <div class="category-panel-head">
          <div class="category-panel-title">
            <strong>商品分类</strong>
          </div>
          <t-space size="small">
            <t-button
              shape="square"
              variant="text"
              :loading="categoryLoading"
              aria-label="刷新分类列表"
              @click="loadCategories"
            >
              <template #icon><refresh-icon /></template>
            </t-button>
            <t-tooltip content="管理一级分类">
              <t-button shape="square" variant="text" aria-label="管理一级分类" @click="openTypeManagerDialog()">
                <template #icon><setting-icon /></template>
              </t-button>
            </t-tooltip>
            <t-button theme="primary" size="small" aria-label="新建二级分类" @click="openSecondCategoryDialog()">
              <template #icon><add-icon /></template>
              新建二级
            </t-button>
          </t-space>
        </div>
        <div class="category-toolbar">
          <t-input v-model="categoryKeyword" clearable placeholder="搜索分类">
            <template #prefix-icon><search-icon /></template>
          </t-input>
        </div>

        <div v-if="categoryTreeRows.length" class="category-tree" role="tree" aria-label="商品分类树">
          <div
            v-for="{ node: item, level, childCount } in categoryTreeRows"
            :key="categoryIdKey(item)"
            class="category-tree-row"
            :class="{
              active: isCategoryRowActive(item),
              'active-parent': isCategoryRowParentOfSelected(item),
              disabled: categorySortLoadingId,
            }"
            :style="{ '--category-level': level }"
            role="treeitem"
            :aria-level="level + 1"
            :aria-expanded="isCategoryExpanded(item) ? 'true' : 'false'"
            :aria-selected="isCategoryRowActive(item) ? 'true' : 'false'"
          >
            <button
              type="button"
              class="category-expand"
              :class="{ visible: childCount > 0, expanded: isCategoryExpanded(item) }"
              :disabled="childCount <= 0"
              :aria-label="
                isCategoryExpanded(item) ? `折叠${categoryDisplayName(item)}` : `展开${categoryDisplayName(item)}`
              "
              @click.stop="toggleCategoryExpanded(item)"
            />
            <button type="button" class="category-drag" aria-label="排序占位" disabled>::</button>
            <button type="button" class="category-tree-main" @click="handleCategorySelect(item)">
              <span class="category-title-line">
                <span class="category-name" :class="{ 'is-hidden': !isCategoryVisible(item) }">
                  {{ categoryDisplayName(item) }}
                </span>
              </span>
            </button>
            <t-dropdown
              v-if="productGroupLevel(item) > 1"
              class="category-menu"
              trigger="click"
              placement="bottom-right"
              :options="categoryMenuOptions(item)"
              @click="handleCategoryMenuClick(item, $event)"
            >
              <t-button
                class="category-menu-trigger"
                size="small"
                shape="square"
                variant="text"
                :aria-label="`更多操作：${categoryDisplayName(item)}`"
                >...</t-button
              >
            </t-dropdown>
          </div>
        </div>
        <t-empty v-else-if="categoryOptions.length" description="未找到匹配结果" />
        <t-empty v-else description="暂无分类" />
      </aside>

      <section class="catalog-main product-table-card">
        <div class="catalog-filter-form">
          <div class="catalog-filter-item">
            <label class="catalog-filter-label">商品名称</label>
            <t-input
              v-model="catalogFilters.keyword"
              class="catalog-filter-control"
              clearable
              placeholder="请输入商品名称"
              aria-label="商品名称"
              @enter="handleCatalogSearch"
              @clear="handleCatalogSearch"
            >
              <template #suffix-icon><search-icon /></template>
            </t-input>
          </div>
          <div class="catalog-filter-item">
            <label class="catalog-filter-label">显示状态</label>
            <t-select
              v-model="catalogFilters.status"
              class="catalog-filter-control"
              clearable
              placeholder="请选择显示状态"
              aria-label="显示状态"
              @change="handleCatalogSearch"
            >
              <t-option :value="1" label="显示中" />
              <t-option :value="0" label="已隐藏" />
            </t-select>
          </div>
          <div class="catalog-filter-item">
            <label class="catalog-filter-label">商品范围</label>
            <t-select
              v-model="catalogFilters.lifecycle_status"
              class="catalog-filter-control"
              placeholder="请选择商品范围"
              aria-label="商品范围"
              @change="handleCatalogSearch"
            >
              <t-option
                v-for="item in lifecycleStatusOptions"
                :key="item.value"
                :value="item.value"
                :label="item.label"
              />
            </t-select>
          </div>
          <div class="catalog-filter-actions">
            <t-button theme="primary" @click="handleCatalogSearch">查询</t-button>
            <t-button theme="default" variant="base" @click="handleCatalogReset">重置</t-button>
            <t-button theme="primary" @click="router.push({ name: 'AdminProductCreate' })">
              <template #icon><add-icon /></template>
              新建商品
            </t-button>
          </div>
        </div>

        <button type="button" class="mobile-catalog-sidebar-entry" @click="mobileCatalogDrawerVisible = true">
          <span>筛选分类</span>
          <strong>{{ selectedProductTypeLabel }} / {{ selectedCategoryLabel }}</strong>
        </button>

        <div class="table-toolbar">
          <div class="table-toolbar-info" aria-live="polite">
            <template v-if="selectedProductKeys.length">
              已选 <strong>{{ selectedProductKeys.length }}</strong> 个商品
            </template>
            <template v-else>共 {{ productTotal }} 个商品</template>
          </div>
          <div class="table-toolbar-actions">
            <template v-if="selectedProductKeys.length">
              <t-button size="small" variant="outline" @click="openBatchCategoryDialog">批量归类</t-button>
              <t-button
                size="small"
                variant="outline"
                :loading="splitProductPreviewLoading"
                @click="openSplitProductDialog"
              >
                拆分商品
              </t-button>
              <t-button size="small" variant="outline" @click="openProvisionHostnameDialog">批量主机名</t-button>
              <t-button size="small" variant="text" @click="clearProductSelection">清空选择</t-button>
            </template>
          </div>
        </div>

        <div class="table-scroll">
          <t-table
            row-key="id"
            :data="products"
            :columns="productColumns"
            :loading="productLoading"
            :selected-row-keys="selectedProductKeys"
            hover
            table-layout="fixed"
            aria-label="商品列表"
            @select-change="handleProductSelectChange"
            @sort-change="handleProductSortChange"
          >
            <template #empty>
              <t-empty
                :description="
                  productLoading
                    ? '加载中…'
                    : catalogFilters.keyword ||
                        catalogFilters.status !== '' ||
                        catalogFilters.lifecycle_status !== 'active'
                      ? '未找到匹配结果'
                      : '暂无数据'
                "
              />
            </template>
            <template #name="{ row }">
              <t-tooltip :content="productSpecDisplayName(row)">
                <span class="product-name" :class="{ 'is-hidden': !isProductVisible(row) }">
                  {{ productSpecDisplayName(row) }}
                </span>
              </t-tooltip>
            </template>
            <template #status="{ row }">
              <t-tag :theme="productVisibilityTheme(row)" variant="light" size="small">
                {{ productVisibilityLabel(row) }}
              </t-tag>
            </template>
            <template #price="{ row }">
              {{ formatMonthlyPrice(row) }}
            </template>
            <template #stock="{ row }">
              {{ formatStock(row.stock) }}
            </template>
            <template #services_count="{ row }">
              {{ formatCount(row.active_services_count ?? row.services_count) }}
            </template>
            <template #operation="{ row }">
              <div class="product-row-actions">
                <template v-if="row.is_deleted">
                  <t-button
                    size="small"
                    variant="text"
                    theme="primary"
                    :loading="productActionLoading === `restore:${row.id}`"
                    @click="handleRestoreProduct(row)"
                    >恢复</t-button
                  >
                  <t-button
                    size="small"
                    variant="text"
                    theme="danger"
                    :loading="productActionLoading === `force:${row.id}`"
                    @click="handleForceDeleteProduct(row)"
                    >彻底删除</t-button
                  >
                </template>
                <template v-else>
                  <t-button
                    size="small"
                    variant="text"
                    theme="primary"
                    @click="router.push({ name: 'AdminProductEdit', params: { id: row.id } })"
                    >编辑</t-button
                  >
                  <t-button
                    size="small"
                    variant="text"
                    :loading="productActionLoading === row.id"
                    @click="handleToggleProduct(row)"
                    >{{ Number(row.status) === 1 ? '隐藏' : '显示' }}</t-button
                  >
                  <t-button size="small" variant="text" theme="danger" @click="handleDeleteProduct(row)">删除</t-button>
                </template>
              </div>
            </template>
          </t-table>
        </div>

        <div v-if="productTotal > 0" class="pagination-row">
          <t-pagination
            :current="productPage"
            :page-size="productPageSize"
            :total="productTotal"
            :page-size-options="[20, 50, 100]"
            show-jumper
            @change="handleProductPageChange"
          />
        </div>
      </section>
    </t-card>

    <t-drawer
      v-model:visible="mobileCatalogDrawerVisible"
      placement="left"
      size="100vw"
      class="mobile-catalog-drawer"
      :footer="false"
    >
      <template #header>
        <div class="mobile-catalog-header">
          <button type="button" class="mobile-catalog-back" @click="mobileCatalogDrawerVisible = false">返回</button>
          <div class="mobile-catalog-title">
            <strong>筛选商品</strong>
            <span>{{ selectedProductTypeLabel }} / {{ selectedCategoryLabel }}</span>
          </div>
        </div>
      </template>

      <div class="mobile-catalog-drawer-body">
        <aside class="mobile-type-sidebar">
          <button
            v-for="item in productTypeOptions"
            :key="item.value"
            type="button"
            class="mobile-type-item"
            :class="{ active: catalogFilters.product_type === item.value }"
            @click="handleMobileProductTypeChange(item.value)"
          >
            <strong>{{ item.label }}</strong>
            <span>{{ item.usage_count || 0 }}</span>
          </button>
          <button
            type="button"
            class="mobile-type-tool mobile-type-tool--icon"
            aria-label="管理一级分类"
            title="管理一级分类"
            @click="openTypeManagerDialog()"
          >
            <setting-icon />
          </button>
        </aside>

        <section class="mobile-category-sidebar">
          <div class="category-panel-head">
            <div class="category-panel-title">
              <strong>商品分类</strong>
            </div>
            <t-space size="small">
              <t-button shape="square" variant="text" :loading="categoryLoading" @click="loadCategories">
                <template #icon><refresh-icon /></template>
              </t-button>
              <t-button theme="primary" size="small" @click="openSecondCategoryDialog()">
                <template #icon><add-icon /></template>
                新建二级
              </t-button>
            </t-space>
          </div>
          <div class="category-toolbar">
            <t-input v-model="categoryKeyword" clearable placeholder="搜索分类或子菜单">
              <template #prefix-icon><search-icon /></template>
            </t-input>
          </div>

          <div
            v-if="mobileCategoryTreeRows.length"
            class="category-tree mobile-category-tree"
            role="tree"
            aria-label="商品分类树"
          >
            <div
              v-for="{ node: item, level, childCount } in mobileCategoryTreeRows"
              :key="categoryIdKey(item)"
              class="category-tree-row"
              :class="{
                active: isCategoryRowActive(item),
                'active-parent': isCategoryRowParentOfSelected(item),
                disabled: categorySortLoadingId,
              }"
              :style="{ '--category-level': level }"
              role="treeitem"
              :aria-level="level + 1"
              :aria-expanded="isCategoryExpanded(item) ? 'true' : 'false'"
              :aria-selected="isCategoryRowActive(item) ? 'true' : 'false'"
            >
              <button
                type="button"
                class="category-expand"
                :class="{ visible: childCount > 0, expanded: isCategoryExpanded(item) }"
                :disabled="childCount <= 0"
                :aria-label="
                  isCategoryExpanded(item) ? `折叠${categoryDisplayName(item)}` : `展开${categoryDisplayName(item)}`
                "
                @click.stop="toggleCategoryExpanded(item)"
              />
              <button type="button" class="category-drag" aria-label="排序占位" disabled>::</button>
              <button type="button" class="category-tree-main" @click="handleMobileCategorySelect(item)">
                <span class="category-title-line">
                  <span class="category-name" :class="{ 'is-hidden': !isCategoryVisible(item) }">
                    {{ categoryDisplayName(item) }}
                  </span>
                </span>
              </button>
              <t-dropdown
                class="category-menu"
                trigger="click"
                placement="bottom-right"
                :options="categoryMenuOptions(item)"
                @click="handleCategoryMenuClick(item, $event)"
              >
                <t-button
                  class="category-menu-trigger"
                  size="small"
                  shape="square"
                  variant="text"
                  :aria-label="`更多操作：${categoryDisplayName(item)}`"
                  >...</t-button
                >
              </t-dropdown>
            </div>
          </div>
          <t-empty v-else-if="categoryKeyword.trim()" description="未找到匹配结果" />
          <t-empty v-else description="暂无分类" />
        </section>
      </div>
    </t-drawer>

    <t-drawer
      v-model:visible="productDialogVisible"
      :header="editingProduct ? '编辑商品' : '新建商品'"
      size="860px"
      class="product-edit-drawer"
      :close-on-overlay-click="false"
      :footer="false"
      @close="closeProductDrawer"
    >
      <div class="product-drawer-shell">
        <div class="product-drawer">
          <aside class="product-drawer-nav">
            <button
              v-for="section in productDrawerSections"
              :key="section.key"
              type="button"
              class="product-drawer-nav-item"
              :class="[{ active: activeProductDrawerSection === section.key }]"
              :aria-current="activeProductDrawerSection === section.key ? 'step' : undefined"
              @click="setProductDrawerSection(section.key)"
            >
              <strong
                >{{ section.label
                }}<span
                  v-if="activeProductDrawerSection !== section.key && productDrawerSectionValid(section.key)"
                  class="nav-dot nav-dot--done"
                  aria-label="已完成"
                  >●</span
                ></strong
              >
              <span>{{ section.description }}</span>
            </button>
          </aside>

          <div class="product-drawer-main">
            <t-form
              ref="productFormRef"
              class="product-drawer-form"
              :data="productForm"
              :rules="productRules"
              label-width="120px"
            >
              <section v-show="activeProductDrawerSection === 'basic'" class="product-drawer-section" data-title="详情">
                <div class="product-drawer-grid">
                  <t-form-item label="商品名称" name="display_name">
                    <t-input v-model="productForm.display_name" />
                  </t-form-item>
                  <t-form-item label="所属分类" name="selected_product_group_key">
                    <t-select v-model="productForm.selected_product_group_key" filterable clearable>
                      <t-option
                        v-for="item in selectableProductGroupOptions"
                        :key="productGroupOptionKey(item)"
                        :label="productGroupOptionLabel(item)"
                        :value="productGroupOptionKey(item)"
                      />
                    </t-select>
                  </t-form-item>
                  <t-form-item label="状态" name="status"
                    ><t-switch v-model="productForm.status" :custom-value="[1, 0]"
                  /></t-form-item>
                </div>
              </section>

              <section
                v-show="activeProductDrawerSection === 'pricing'"
                class="product-drawer-section"
                data-title="定价"
              >
                <div class="product-pricing-actions">
                  <span class="pricing-hint">输入月付价格后，选择方案并自动计算其他周期</span>
                  <t-select
                    v-model="productPricingPlan"
                    size="small"
                    style="width: 150px"
                    @change="syncProductPricingCycles"
                  >
                    <t-option
                      v-for="item in productPricingPlanOptions"
                      :key="item.value"
                      :label="item.label"
                      :value="item.value"
                    />
                  </t-select>
                </div>
                <div class="product-drawer-grid">
                  <t-form-item label="月付价格" name="monthly_price">
                    <t-input-number v-model="productForm.monthly_price" :min="0" style="width: 100%" />
                  </t-form-item>
                  <t-form-item label="季付价格" name="quarterly_price">
                    <t-input-number v-model="productForm.quarterly_price" :min="0" style="width: 100%" />
                  </t-form-item>
                  <t-form-item label="半年付价格" name="semiannually_price">
                    <t-input-number v-model="productForm.semiannually_price" :min="0" style="width: 100%" />
                  </t-form-item>
                  <t-form-item label="年付价格" name="annually_price">
                    <t-input-number v-model="productForm.annually_price" :min="0" style="width: 100%" />
                  </t-form-item>
                </div>
              </section>

              <section
                v-show="activeProductDrawerSection === 'interface'"
                class="product-drawer-section product-interface-section"
                data-title="自动开通"
              >
                <div class="product-interface-tip">配置开通模块、支付方式和上架状态。</div>
                <div class="product-interface-panel">
                  <t-form-item label="提供商" name="supplier_id">
                    <t-select
                      v-model="productForm.supplier_id"
                      filterable
                      clearable
                      placeholder="请选择提供商接口"
                      :loading="productSupplierLoading"
                      @change="handleProductSupplierChange"
                    >
                      <t-option
                        v-for="item in productSupplierOptions"
                        :key="item.id"
                        :label="supplierOptionLabel(item)"
                        :value="item.id"
                      >
                        <div class="supplier-option-row">
                          <span>{{ item.name || item.id }}</span>
                          <span>{{ supplierInterfaceTypeLabel(item) }}</span>
                        </div>
                      </t-option>
                    </t-select>
                  </t-form-item>
                  <t-form-item label="提供商商品" name="upstream_product_id">
                    <div class="supplier-product-row">
                      <t-cascader
                        v-model="productForm.upstream_product_id"
                        :options="supplierProductCascaderOptions"
                        filterable
                        clearable
                        placeholder="请选择提供商商品"
                        :loading="supplierProductLoading"
                        :disabled="!productForm.supplier_id"
                        value-mode="onlyLeaf"
                        :show-all-levels="false"
                      />
                      <t-button
                        theme="primary"
                        :loading="supplierProductLoading"
                        :disabled="!productForm.supplier_id"
                        @click="loadProductSupplierProducts(productForm.supplier_id, true)"
                      >
                        同步数据
                      </t-button>
                    </div>
                  </t-form-item>
                  <div class="product-interface-grid">
                    <t-form-item label="自动开通" name="auto_setup">
                      <div class="product-switch-line">
                        <span>手动</span>
                        <t-switch v-model="productForm.auto_setup" :custom-value="[1, 0]" />
                        <span>自动</span>
                      </div>
                    </t-form-item>
                  </div>
                </div>
              </section>

              <section
                v-show="activeProductDrawerSection === 'config'"
                class="product-drawer-section"
                data-title="产品配置"
              >
                <div class="product-config-actions">
                  <t-button
                    size="small"
                    variant="outline"
                    :loading="configTemplateLoading"
                    @click="pullProductConfigTemplate"
                  >
                    拉取模板
                  </t-button>
                  <t-button size="small" theme="primary" variant="outline" @click="openConfigOptionDialog()"
                    >新增配置</t-button
                  >
                </div>
                <div class="config-option-panel">
                  <div class="config-option-count">
                    {{ productForm.config_options.length }} 项配置，保存商品后生效。
                  </div>
                  <div v-if="productForm.config_options.length" class="config-option-list">
                    <article v-for="(item, index) in productForm.config_options" :key="item.uid || item.field || index">
                      <div>
                        <strong>{{ item.name || item.option_name || item.field }}</strong>
                        <span>{{ item.field || '-' }} · {{ item.option_mode || 'select' }}</span>
                      </div>
                      <t-space size="small">
                        <t-button
                          size="small"
                          variant="text"
                          theme="primary"
                          @click="openConfigOptionDialog(item, index)"
                          >编辑</t-button
                        >
                        <t-button size="small" variant="text" theme="danger" @click="removeConfigOption(index)"
                          >删除</t-button
                        >
                      </t-space>
                    </article>
                  </div>
                  <t-empty v-else description="暂无配置项" />
                </div>
              </section>
            </t-form>
          </div>
        </div>

        <div class="product-drawer-footer">
          <t-button variant="outline" @click="closeProductDrawer">取消</t-button>
          <t-button theme="primary" :loading="productSubmitting" @click="submitProduct">保存更改</t-button>
        </div>
      </div>
    </t-drawer>

    <t-dialog
      v-model:visible="configOptionDialogVisible"
      class="config-option-edit-dialog"
      :header="configOptionEditingIndex >= 0 ? '编辑配置项' : '新增配置项'"
      width="760px"
      :confirm-btn="{ content: '保存配置', loading: configOptionSubmitting }"
      @confirm="submitConfigOption"
    >
      <t-form class="config-option-edit-form" :data="configOptionForm" label-align="top">
        <div class="config-option-basic-grid">
          <t-form-item label="配置项名称" name="name" required-mark>
            <t-input v-model="configOptionForm.name" placeholder="例如 CPU、内存" />
          </t-form-item>
          <t-form-item label="配置项类型" name="option_mode" required-mark>
            <t-select v-model="configOptionForm.option_mode" @change="handleConfigOptionModeChange">
              <t-option label="单选（固定选项）" value="select" />
              <t-option label="数量范围" value="range" />
            </t-select>
          </t-form-item>
          <t-form-item label="高级设置">
            <t-checkbox v-model="configOptionForm.advanced" />
          </t-form-item>

          <t-form-item label="配置标识" name="field" required-mark>
            <t-input v-model="configOptionForm.field" placeholder="例如 cpu、memory、os" />
          </t-form-item>
          <t-form-item label="选项说明">
            <t-input v-model="configOptionForm.description" placeholder="例如 镜像管理中的操作系统 ID。" />
          </t-form-item>
          <t-form-item label="选项尾部文字">
            <t-input v-model="configOptionForm.suffix_text" placeholder="请输入选项尾部文字" />
          </t-form-item>
        </div>

        <t-form-item v-if="configOptionForm.option_mode !== 'select'" label="参数值" name="parameter">
          <t-input v-model="configOptionForm.parameter" placeholder="例如 1-16 / 1|1核,2|2核" />
        </t-form-item>

        <div v-else class="config-subitem-table">
          <div class="config-subitem-head">
            <strong>子项列表</strong>
            <t-button size="small" variant="outline" @click="addConfigSubItemRow">新增子项</t-button>
          </div>
          <div class="config-subitem-grid config-subitem-grid--header">
            <span>子项名称</span>
            <span>传参值</span>
            <span>月付价格</span>
            <span>排序</span>
            <span>操作</span>
          </div>
          <div v-for="(row, index) in configOptionSubItemRows" :key="row.uid" class="config-subitem-grid">
            <t-input v-model="row.name" placeholder="例如 CentOS^CentOS-7.6" />
            <t-input v-model="row.value" placeholder="例如 27" />
            <t-input v-model="row.monthly_price" placeholder="0.00" />
            <t-input-number v-model="row.sort_order" :min="0" theme="column" />
            <t-button
              shape="square"
              variant="outline"
              :disabled="configOptionSubItemRows.length <= 1"
              @click="removeConfigSubItemRow(index)"
            >
              <delete-icon />
            </t-button>
          </div>
        </div>

        <div class="config-option-footer-row">
          <t-form-item label="排序" name="sort_order">
            <t-input-number v-model="configOptionForm.sort_order" :min="0" />
          </t-form-item>
          <t-form-item label="开关">
            <t-space>
              <t-checkbox v-model="configOptionForm.required">必选</t-checkbox>
              <t-checkbox v-model="configOptionForm.hidden">隐藏</t-checkbox>
            </t-space>
          </t-form-item>
        </div>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="batchCategoryDialogVisible"
      header="批量归类"
      width="520px"
      :confirm-btn="{ content: '确定', loading: batchCategorySubmitting }"
      @confirm="submitBatchCategory"
    >
      <div class="batch-dialog-summary">将 {{ batchCategoryTargetKeys.length }} 个商品移动到目标分类。</div>
      <t-form :data="batchCategoryForm" label-width="110px">
        <t-form-item label="目标分类" name="target_product_group_key">
          <t-select
            v-model="batchCategoryForm.target_product_group_key"
            filterable
            clearable
            placeholder="请选择目标分类"
          >
            <t-option
              v-for="item in selectableProductGroupOptions"
              :key="productGroupOptionKey(item)"
              :label="productGroupOptionLabel(item)"
              :value="productGroupOptionKey(item)"
            />
          </t-select>
        </t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog
      v-model:visible="splitProductDialogVisible"
      header="拆分商品"
      width="680px"
      :confirm-btn="{
        content: '确定',
        loading: splitProductSubmitting,
        disabled: splitProductPreviewLoading || !splitProductPreviewRows.length,
      }"
      @confirm="submitSplitProducts"
    >
      <div class="split-dialog-intro">
        <strong>按 CPU 与内存子选项生成独立商品</strong>
        <p>系统会读取已选商品的 CPU、内存配置子项，基础规格更新原商品，其余规格生成独立商品。</p>
      </div>

      <div class="split-product-meta">
        <span>已选商品 {{ splitProductTargetKeys.length }} 个</span>
        <span v-if="splitProductPreviewCount">预计处理 {{ splitProductPreviewCount }} 个规格</span>
        <span v-if="splitProductSkippedCount">跳过 {{ splitProductSkippedCount }} 个</span>
      </div>

      <t-loading :loading="splitProductPreviewLoading">
        <div class="split-product-preview">
          <template v-if="splitProductPreviewRows.length">
            <article v-for="row in splitProductPreviewRows" :key="row.key" class="split-product-item">
              <div>
                <strong>{{ row.display_name || '-' }}</strong>
                <span>{{ row.source_display_name || '-' }}</span>
              </div>
              <t-tag :theme="row.action === 'update' ? 'warning' : 'success'" variant="light">
                {{ formatSplitAction(row.action) }}
              </t-tag>
            </article>
          </template>
          <t-empty v-else description="未找到可拆分的 CPU 或内存子选项" />
        </div>
      </t-loading>

      <t-alert theme="warning" message="基础规格沿用原商品；同名拆分商品会更新，不会重复创建。" />
    </t-dialog>

    <t-dialog
      v-model:visible="provisionHostnameDialogVisible"
      header="批量主机名规则"
      width="620px"
      :confirm-btn="{ content: '保存规则', loading: provisionHostnameSubmitting }"
      @confirm="submitProvisionHostname"
    >
      <div class="split-dialog-intro">
        <strong>设置商品开通主机名</strong>
        <p>该配置会在自动开通时优先用于上游 host 参数，保存后会覆盖所选商品的现有规则。</p>
      </div>

      <div class="split-product-meta">
        <span>已选商品 {{ provisionHostnameTargetKeys.length }} 个</span>
        <span v-if="provisionHostnameHasMixedRules">当前包含多种主机名规则</span>
      </div>

      <t-alert
        v-if="provisionHostnameHasMixedRules"
        theme="warning"
        message="当前选中的商品存在不同规则，保存后会统一覆盖为当前设置。"
      />

      <t-form class="hostname-rule-form" :data="provisionHostnameForm" label-width="120px">
        <t-form-item label="开通策略" name="mode">
          <t-radio-group v-model="provisionHostnameForm.mode" variant="default-filled">
            <t-radio-button value="system">跟随上游</t-radio-button>
            <t-radio-button value="fixed">固定主机名</t-radio-button>
            <t-radio-button value="prefix">指定前缀</t-radio-button>
          </t-radio-group>
        </t-form-item>
        <t-form-item v-if="provisionHostnameForm.mode === 'fixed'" label="固定主机名" name="value">
          <t-input v-model="provisionHostnameForm.value" maxlength="200" placeholder="例如 hk-vps-core" />
        </t-form-item>
        <template v-if="provisionHostnameForm.mode === 'prefix'">
          <t-form-item label="主机名前缀" name="value">
            <t-input v-model="provisionHostnameForm.value" maxlength="20" placeholder="例如 hk / sg / us" />
          </t-form-item>
          <t-form-item label="主机名总长度" name="length">
            <t-input-number v-model="provisionHostnameForm.length" :min="4" :max="63" />
          </t-form-item>
        </template>
      </t-form>

      <t-alert theme="info" :message="provisionHostnamePreviewText" />
    </t-dialog>

    <t-dialog
      v-model:visible="categoryDialogVisible"
      :header="categoryDialogHeader"
      width="560px"
      :confirm-btn="{ content: '保存', loading: categorySubmitting }"
      @confirm="submitCategory"
    >
      <t-form ref="categoryFormRef" :data="categoryForm" :rules="categoryRules" label-width="110px">
        <t-form-item label="分类名称" name="name"><t-input v-model="categoryForm.name" /></t-form-item>
        <t-form-item label="一级分类" name="product_type">
          <t-select
            v-model="categoryForm.product_type"
            filterable
            placeholder="请选择一级分类"
            :disabled="categoryProductTypeDisabled"
            @change="handleCategoryProductTypeChange"
          >
            <t-option
              v-for="item in categoryProductTypeOptions"
              :key="item.value"
              :label="item.label"
              :value="item.value"
            />
          </t-select>
        </t-form-item>
        <t-form-item v-if="categoryParentFieldVisible" label="二级分类父级" name="parent_id">
          <t-select
            v-model="categoryForm.parent_id"
            clearable
            filterable
            placeholder="不选择则新增二级分类，选择后新增三级分类"
            :disabled="categoryParentSelectDisabled"
          >
            <t-option
              v-for="item in categoryParentCategoryOptions"
              :key="item.id"
              :label="categoryParentOptionLabel(item)"
              :value="item.id"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="一句话说明" name="slogan"><t-input v-model="categoryForm.slogan" /></t-form-item>
        <t-form-item label="排序" name="sort_order"
          ><t-input-number v-model="categoryForm.sort_order" :min="0"
        /></t-form-item>
        <t-form-item label="前台可见" name="is_visible"
          ><t-switch v-model="categoryForm.is_visible" :custom-value="[1, 0]"
        /></t-form-item>
      </t-form>
    </t-dialog>

    <type-manager-dialog
      v-model:visible="typeManagerDialogVisible"
      :product-types="productTypes"
      :loading="typeLoading"
      :submitting="typeSubmitting"
      :product-count="typeManagerProductCount"
      :hidden-count="typeManagerHiddenCount"
      :submit-type="submitType"
      :toggle-type-hidden="toggleTypeHidden"
      :move-type="moveType"
      :delete-type="deleteType"
      @refresh="loadProductTypes"
    />
  </div>
</template>
<script setup lang="ts">
import { AddIcon, DeleteIcon, RefreshIcon, SearchIcon, SettingIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, PageInfo, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import type { ProductCategoryRecord, ProductRecord } from '@/api/product';
import { productApi } from '@/api/product';

import {
  categoryActiveProductCount,
  categoryDisplayName,
  categoryParentOptionLabel,
  categoryTotalProductCount,
  isProductVisible,
  normalizeCategoryScopeValue,
  productSpecDisplayName,
  productVisibilityLabel,
  productVisibilityTheme,
} from '../composables/catalogDisplay';
import type { ProductConfigOptionRecord } from '../composables/useProductConfigOptions';
import { serializeConfigOptions, useProductConfigOptions } from '../composables/useProductConfigOptions';
import {
  errorMessage,
  findProductGroupByKey,
  flattenCategories,
  formatCount,
  formatMonthlyPrice,
  formatStock,
  isSelectableProductGroup,
  productGroupEffectiveId,
  productGroupLevel,
  productGroupOptionKey,
  productGroupOptionLabel,
  productGroupPayload,
  toPlainRecord,
} from '../composables/useProductShared';
import { normalizeBusinessProductType, useProductTypeManager } from '../composables/useProductTypeManager';
import { useSupplierBinding } from '../composables/useSupplierBinding';
import TypeManagerDialog from './TypeManagerDialog.vue';

type ProvisionHostnameMode = 'system' | 'fixed' | 'prefix';

interface SplitProductPreviewRow {
  key: string;
  product_id: number;
  display_name: string;
  source_product_id: number;
  source_display_name: string;
  action: string;
}

interface CategoryTreeNode {
  item: ProductCategoryRecord;
  children: CategoryTreeNode[];
}

type ProductLifecycleStatus = 'active' | 'deleted' | 'all';

// --- State ---
const router = useRouter();
const categoryLoading = ref(false);
const productLoading = ref(false);
const productSubmitting = ref(false);
const productActionLoading = ref<number | string | null>(null);

const categoryOptions = ref<ProductCategoryRecord[]>([]);
const products = ref<ProductRecord[]>([]);
const productTotal = ref(0);
const productPage = ref(1);
const productPageSize = ref(20);
// 服务端排序状态：sortBy 与后端 sort_by 白名单一致（price/stock/services_count）
const productSort = ref<{ sortBy: string; descending: boolean }>({ sortBy: '', descending: false });
const categoryKeyword = ref('');
const activeProductDrawerSection = ref('basic');
const categoryExpandedKeys = ref<Set<string>>(new Set());
const mobileCatalogDrawerVisible = ref(false);
const typeManagerDialogVisible = ref(false);

const catalogFilters = reactive({
  keyword: '',
  product_type: '',
  product_group_key: '' as string,
  status: '' as number | string,
  lifecycle_status: 'active' as ProductLifecycleStatus,
});
const {
  productTypes,
  typeLoading,
  typeSubmitting,
  typeManagerProductCount,
  typeManagerHiddenCount,
  loadProductTypes,
  submitType,
  toggleTypeHidden,
  moveType,
  deleteType,
} = useProductTypeManager({
  catalogFilters,
  reloadCatalogData,
});

function openTypeManagerDialog() {
  typeManagerDialogVisible.value = true;
}

const productDialogVisible = ref(false);
const editingProduct = ref<ProductRecord | null>(null);
const productFormRef = ref();
const selectedProductKeys = ref<Array<string | number>>([]);
const batchCategoryDialogVisible = ref(false);
const batchCategorySubmitting = ref(false);
const batchCategoryTargetKeys = ref<Array<string | number>>([]);
const batchCategoryForm = reactive({
  target_product_group_key: '' as string,
});
const splitProductDialogVisible = ref(false);
const splitProductPreviewLoading = ref(false);
const splitProductSubmitting = ref(false);
const splitProductTargetKeys = ref<Array<string | number>>([]);
const splitProductPreview = ref<Record<string, unknown> | null>(null);
const provisionHostnameDialogVisible = ref(false);
const provisionHostnameSubmitting = ref(false);
const provisionHostnameTargetKeys = ref<Array<string | number>>([]);
const provisionHostnameHasMixedRules = ref(false);
const provisionHostnameForm = reactive({
  mode: 'system' as ProvisionHostnameMode,
  value: '',
  length: 12,
});
const productForm = reactive({
  display_name: '',
  custom_display_name: '',
  product_spec_display: '',
  selected_product_group_key: '' as string,
  monthly_price: 0,
  quarterly_price: 0,
  semiannually_price: 0,
  annually_price: 0,
  auto_setup: 1,
  status: 1,
  supplier_id: '' as number | string,
  upstream_product_id: '' as number | string,
  config_options: [] as ProductConfigOptionRecord[],
});
const {
  configOptionDialogVisible,
  configOptionSubmitting,
  configOptionEditingIndex,
  configTemplateLoading,
  configOptionForm,
  configOptionSubItemRows,
  addConfigSubItemRow,
  removeConfigSubItemRow,
  handleConfigOptionModeChange,
  openConfigOptionDialog,
  submitConfigOption,
  removeConfigOption,
  pullProductConfigTemplate,
} = useProductConfigOptions(productForm);
const {
  productSupplierOptions,
  productSupplierLoading,
  supplierProductLoading,
  supplierProductCascaderOptions,
  supplierOptionLabel,
  supplierInterfaceTypeLabel,
  handleProductSupplierChange,
  loadProductSupplierProducts,
} = useSupplierBinding(productForm);
const categoryDialogVisible = ref(false);
const editingCategory = ref<ProductCategoryRecord | null>(null);
const creatingThirdCategoryParent = ref<ProductCategoryRecord | null>(null);
const categoryFormRef = ref();
const categorySubmitting = ref(false);
const categorySortLoadingId = ref('');
let categoryRequestVersion = 0;
let productRequestVersion = 0;
const categoryForm = reactive({
  name: '',
  product_type: '' as string,
  parent_id: '' as number | string,
  slogan: '',
  sort_order: 0,
  is_visible: 1,
});

const productPricingPlan = ref('standard');
const productPricingPlanOptions = [
  { value: 'standard', label: '无优惠', ratios: { quarterly: 3, semiannually: 6, annually: 12 } },
  { value: 'annual', label: '年付优惠', ratios: { quarterly: 3, semiannually: 6, annually: 10 } },
  { value: 'bulk', label: '大额优惠', ratios: { quarterly: 2.7, semiannually: 5.1, annually: 8.4 } },
  { value: 'rule1', label: '规则一', ratios: { quarterly: 3, semiannually: 4.8, annually: 9 } },
  { value: 'rule2', label: '规则二', ratios: { quarterly: 2.7, semiannually: 5.1, annually: 9.6 } },
];
const lifecycleStatusOptions = [
  { value: 'active', label: '正常商品' },
  { value: 'deleted', label: '已删除商品' },
  { value: 'all', label: '全部商品' },
] satisfies Array<{ value: ProductLifecycleStatus; label: string }>;
const productDrawerSections = [
  { key: 'basic', label: '详情', description: '名称、分类、状态' },
  { key: 'pricing', label: '定价', description: '多周期价格' },
  { key: 'interface', label: '接口设置', description: '上游商品绑定' },
  { key: 'config', label: '产品配置', description: '规格与可选项' },
];

const productColumns: PrimaryTableCol<ProductRecord>[] = [
  { colKey: 'row-select', type: 'multiple', width: 54, fixed: 'left' },
  { colKey: 'id', title: 'ID', width: 64 },
  { colKey: 'name', title: '商品', minWidth: 200 },
  { colKey: 'effective_product_group_full_name', title: '分类', minWidth: 180 },
  { colKey: 'price', title: '月价格', width: 110, align: 'right', sorter: true },
  { colKey: 'stock', title: '库存', width: 90, align: 'right', sorter: true },
  { colKey: 'services_count', title: '现存', width: 90, align: 'right', sorter: true },
  { colKey: 'status', title: '显示状态', width: 100, align: 'center' },
  { colKey: 'operation', title: '操作', width: 190, fixed: 'right', align: 'center' },
];

const productRules = {
  display_name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  selected_product_group_key: [{ required: true, message: '请选择所属分类', trigger: 'change' }],
};
const categoryRules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
  product_type: [{ required: true, message: '请选择一级分类', trigger: 'change' }],
};

// --- Computeds ---
const productTypeOptions = computed(() => {
  if (productTypes.value.length) return productTypes.value;
  return [{ value: '', label: '全部商品', usage_count: productTotal.value }];
});
const productDrawerSectionValid = (key: string) => {
  switch (key) {
    case 'basic':
      return !!(productForm.display_name || productForm.selected_product_group_key);
    case 'pricing':
      return !!(productForm.monthly_price || productForm.quarterly_price || productForm.annually_price);
    case 'interface':
      return !!(productForm.supplier_id || productForm.upstream_product_id);
    case 'config':
      return productForm.config_options.length > 0;
    default:
      return false;
  }
};
const selectedProductTypeLabel = computed(() => {
  const current = productTypeOptions.value.find((item) => String(item.value) === String(catalogFilters.product_type));
  return current?.label || '全部商品';
});
const categoryProductTypeOptions = computed(() => {
  const options = productTypeOptions.value.filter((item) => String(item.value || '').trim());
  if (options.length) return options;
  return catalogFilters.product_type
    ? [{ value: catalogFilters.product_type, label: selectedProductTypeLabel.value }]
    : [];
});
const categoryTree = computed(() => buildCategoryTree(categoryOptions.value));
// 左树完整展示 一级 → 二级 → 三级 层级，一级节点即商品类型根节点
const displayCategoryTree = computed(() => categoryTree.value);
const filteredCategoryTree = computed(() =>
  filterCategoryTree(displayCategoryTree.value, categoryKeyword.value.trim().toLowerCase()),
);
const categoryTreeRows = computed(() => flattenCategoryTreeRows(filteredCategoryTree.value));
// 移动端抽屉保留 类型侧栏 + 该类型下二级树 的两段式形态
const scopedDisplayCategoryTree = computed(() => {
  const typeCode = String(catalogFilters.product_type || '').trim();
  if (!typeCode) return displayCategoryTree.value;
  const typeRoot = categoryTree.value.find((node) => String(node.item.first_product_group_code || '') === typeCode);
  return typeRoot ? toDisplayCategoryTree([typeRoot]) : [];
});
const mobileCategoryTreeRows = computed(() =>
  flattenCategoryTreeRows(
    filterCategoryTree(scopedDisplayCategoryTree.value, categoryKeyword.value.trim().toLowerCase()),
  ),
);
const scopedCategoryOptions = computed(() => {
  const typeCode = String(catalogFilters.product_type || '').trim();
  if (!typeCode) return categoryOptions.value;
  return categoryOptions.value.filter((item) => String(item.first_product_group_code || '') === typeCode);
});
const selectableProductGroupOptions = computed(() =>
  scopedCategoryOptions.value.filter((item) => isSelectableProductGroup(item)),
);
const editingCategoryHasChildren = computed(() => {
  const current = editingCategory.value;
  if (!current) return false;
  if (Array.isArray(current.children) && current.children.length > 0) return true;
  return Number(current.children_count || 0) > 0;
});
const categoryDialogHeader = computed(() => {
  if (editingCategory.value) return '编辑分类';
  return creatingThirdCategoryParent.value ? '新建三级分类' : '新建二级分类';
});
const categoryParentFieldVisible = computed(() => {
  if (creatingThirdCategoryParent.value) return true;
  return Boolean(editingCategory.value && productGroupLevel(editingCategory.value) === 3);
});
const categoryProductTypeDisabled = computed(() => Boolean(creatingThirdCategoryParent.value));
const categoryParentSelectDisabled = computed(() => {
  if (creatingThirdCategoryParent.value) return true;
  return editingCategoryHasChildren.value || !categoryParentCategoryOptions.value.length;
});
const categoryParentCategoryOptions = computed(() => {
  const editingCategoryKey = editingCategory.value ? productGroupOptionKey(editingCategory.value) : '';
  const selectedFirstGroupCode = String(categoryForm.product_type || catalogFilters.product_type || '');

  return categoryOptions.value.filter((item) => {
    if (editingCategoryKey && productGroupOptionKey(item) === editingCategoryKey) return false;
    if (productGroupLevel(item) !== 2) return false;
    if (
      selectedFirstGroupCode &&
      String(item.first_product_group_code || '') &&
      String(item.first_product_group_code) !== selectedFirstGroupCode
    ) {
      return false;
    }
    return true;
  });
});
const selectedCategoryLabel = computed(() => {
  const selectedKey = String(catalogFilters.product_group_key || '');
  if (!selectedKey) return '全部分类';
  const category = findProductGroupByKey(categoryOptions.value, selectedKey);
  return category ? categoryDisplayName(category) : '全部分类';
});
const splitProductPreviewItems = computed(() => {
  const items = splitProductPreview.value?.items;
  return Array.isArray(items) ? items : [];
});
const splitProductPreviewRows = computed<SplitProductPreviewRow[]>(() =>
  splitProductPreviewItems.value.flatMap((itemValue) => {
    const item = toPlainRecord(itemValue);
    const variants = Array.isArray(item.variants) ? item.variants : [];
    return variants.map((variantValue) => {
      const variant = toPlainRecord(variantValue);
      return {
        key: `${item.source_product_id || 'source'}-${variant.variant_key || variant.display_name || variant.product_id || 'row'}`,
        product_id: Number(variant.product_id || variant.id || 0),
        display_name: String(variant.display_name || '-'),
        source_product_id: Number(item.source_product_id || item.product_id || 0),
        source_display_name: String(variant.source_display_name || item.source_display_name || '-'),
        action: String(variant.action || 'create'),
      };
    });
  }),
);
const splitProductPreviewCount = computed(() => Number(splitProductPreview.value?.preview_count || 0));
const splitProductSkippedCount = computed(() => Number(splitProductPreview.value?.skipped_count || 0));
const provisionHostnamePreviewText = computed(() => {
  if (provisionHostnameForm.mode === 'fixed') {
    const value = provisionHostnameForm.value.trim();
    return value
      ? `开通时将固定提交主机名：${value}。若上游不允许重复主机名，可能会被拒绝。`
      : '固定主机名模式需要填写一个上游允许的 host 值。';
  }

  if (provisionHostnameForm.mode === 'prefix') {
    const prefix = provisionHostnameForm.value.trim() || 'prefix';
    return `开通时将按"${prefix} + 随机后缀"生成主机名，总长度 ${Number(provisionHostnameForm.length || 12)}。`;
  }

  return '默认优先按上游商品规则生成；若上游未返回规则，则回退到系统默认规则或账单快照。';
});
// --- Category tree helpers ---
function categoryIdKey(row: ProductCategoryRecord) {
  return productGroupOptionKey(row);
}

function isCategoryVisible(row: ProductCategoryRecord) {
  const value = row.is_visible;
  return !(value === false || value === 0 || value === '0');
}

function buildCategoryTree(list: ProductCategoryRecord[]): CategoryTreeNode[] {
  const nodeMap = new Map<string, CategoryTreeNode>();
  const roots: CategoryTreeNode[] = [];

  // First pass: create all nodes
  list.forEach((item) => {
    const key = categoryIdKey(item);
    const existingNode = nodeMap.get(key);
    if (existingNode) {
      // Merge children from backend response if node already exists
      const backendChildren = Array.isArray(item.children) ? item.children : [];
      if (backendChildren.length > 0 && existingNode.children.length === 0) {
        existingNode.children = backendChildren.map((child) => ({ item: child, children: [] }));
      }
    } else {
      nodeMap.set(key, { item, children: [] });
    }
  });

  // Second pass: build parent-child relationships using parent_id
  // We need to find parent by trying different level prefixes since parent_id is just a number
  list.forEach((item) => {
    const node = nodeMap.get(categoryIdKey(item));
    if (!node) return;

    const parentId =
      item.parent_id === undefined || item.parent_id === null || item.parent_id === '' ? '' : String(item.parent_id);
    if (!parentId) {
      roots.push(node);
      return;
    }

    // Try to find parent by trying level prefixes (parent level = current level - 1)
    const currentLevel = productGroupLevel(item);
    const parentLevel = currentLevel - 1;
    if (parentLevel < 1) {
      roots.push(node);
      return;
    }

    const parentKey = `${parentLevel}:${parentId}`;
    const parent = nodeMap.get(parentKey);
    if (parent) {
      // Check if node is already in parent's children
      if (!parent.children.some((child) => categoryIdKey(child.item) === categoryIdKey(item))) {
        parent.children.push(node);
      }
    } else {
      roots.push(node);
    }
  });

  return roots;
}

function filterCategoryTree(nodes: CategoryTreeNode[], keyword: string): CategoryTreeNode[] {
  if (!keyword) return nodes;

  return nodes
    .map((node) => {
      const children = filterCategoryTree(node.children, keyword);
      const matched = [node.item.name, node.item.label].some((value) =>
        String(value || '')
          .toLowerCase()
          .includes(keyword),
      );
      if (matched) return { ...node, children: node.children };
      if (children.length) return { ...node, children };
      return null;
    })
    .filter((node): node is CategoryTreeNode => Boolean(node));
}

function toDisplayCategoryTree(nodes: CategoryTreeNode[]): CategoryTreeNode[] {
  const lifted: CategoryTreeNode[] = [];

  nodes.forEach((root) => {
    if (productGroupLevel(root.item) === 1) {
      lifted.push(...root.children);
      return;
    }

    lifted.push(root);
  });

  return lifted;
}

function flattenCategoryTreeRows(
  nodes: CategoryTreeNode[],
  level = 0,
): Array<{ node: ProductCategoryRecord; level: number; childCount: number }> {
  const rows: Array<{ node: ProductCategoryRecord; level: number; childCount: number }> = [];
  const keyword = categoryKeyword.value.trim();

  nodes.forEach((treeNode) => {
    const childCount = treeNode.children.length;
    rows.push({ node: treeNode.item, level, childCount });

    if (childCount > 0 && (keyword || categoryExpandedKeys.value.has(categoryIdKey(treeNode.item)))) {
      rows.push(...flattenCategoryTreeRows(treeNode.children, level + 1));
    }
  });

  return rows;
}

function syncCategoryExpandedKeys(nodes: CategoryTreeNode[]) {
  const validKeys = new Set<string>();
  const defaultKeys = new Set<string>();
  const selectedKey = String(catalogFilters.product_group_key || '');
  const typeCode = String(catalogFilters.product_type || '').trim();

  function visit(node: CategoryTreeNode): boolean {
    const key = categoryIdKey(node.item);
    validKeys.add(key);
    const hasSelectedDescendant = node.children.some(visit);
    if (hasSelectedDescendant && node.children.length) defaultKeys.add(key);
    return hasSelectedDescendant || (selectedKey !== '' && key === selectedKey);
  }

  nodes.forEach((node) => visit(node));
  // 全量树下仅默认展开当前一级类型根节点，其余一级节点保持折叠
  if (typeCode) {
    const typeRoot = nodes.find((node) => String(node.item.first_product_group_code || '') === typeCode);
    if (typeRoot && typeRoot.children.length) defaultKeys.add(categoryIdKey(typeRoot.item));
  }

  const preserved = Array.from(categoryExpandedKeys.value).filter((key) => validKeys.has(key));
  categoryExpandedKeys.value = new Set([...defaultKeys, ...preserved]);
}

function findCategoryTreeNode(nodes: CategoryTreeNode[], key: number | string): CategoryTreeNode | null {
  const targetKey = String(key);
  for (const node of nodes) {
    if (categoryIdKey(node.item) === targetKey) return node;
    const matchedChild = findCategoryTreeNode(node.children, targetKey);
    if (matchedChild) return matchedChild;
  }
  return null;
}

function firstDisplayCategoryNode(node: CategoryTreeNode): ProductCategoryRecord {
  if (isSelectableProductGroup(node.item)) return node.item;
  for (const child of node.children) {
    const matched = firstDisplayCategoryNode(child);
    if (isSelectableProductGroup(matched)) return matched;
  }
  return node.item;
}

// 全量树下的默认选中：定位当前一级类型根节点，选中其首个可挂筛选的子分类；已选分类仍在该类型下时保留
function ensureCatalogSelection() {
  const typeCode = String(catalogFilters.product_type || '').trim();
  const typeRoot = typeCode
    ? categoryTree.value.find((node) => String(node.item.first_product_group_code || '') === typeCode)
    : null;
  if (!typeRoot) {
    catalogFilters.product_group_key = '';
    return;
  }

  const selectedKey = String(catalogFilters.product_group_key || '');
  const selectedNode = selectedKey ? findCategoryTreeNode([typeRoot], selectedKey) : null;
  catalogFilters.product_group_key = selectedNode
    ? categoryIdKey(firstDisplayCategoryNode(selectedNode))
    : categoryIdKey(firstDisplayCategoryNode(typeRoot));
}

function isCategoryExpanded(row: ProductCategoryRecord) {
  return categoryExpandedKeys.value.has(categoryIdKey(row));
}

function isCategoryRowActive(row: ProductCategoryRecord) {
  const selectedKey = String(catalogFilters.product_group_key || '');
  if (productGroupLevel(row) === 1) {
    // 一级节点即商品类型：未选中具体分类时按当前类型高亮
    return !selectedKey && String(row.first_product_group_code || '') === String(catalogFilters.product_type || '');
  }
  if (!selectedKey) return false;
  // 仅高亮精确选中的行，父节点不再显示选中背景，避免父子背景连成一片
  return categoryIdKey(row) === selectedKey;
}

function isCategoryRowParentOfSelected(row: ProductCategoryRecord) {
  const selectedKey = String(catalogFilters.product_group_key || '');
  if (!selectedKey || categoryIdKey(row) === selectedKey) return false;
  const node = findCategoryTreeNode(categoryTree.value, categoryIdKey(row));
  return Boolean(node?.children.length && findCategoryTreeNode(node.children, selectedKey));
}

function toggleCategoryExpanded(row: ProductCategoryRecord) {
  const next = new Set(categoryExpandedKeys.value);
  const key = categoryIdKey(row);
  if (next.has(key)) {
    next.delete(key);
  } else {
    next.add(key);
  }
  categoryExpandedKeys.value = next;
}

function categoryScopeKey(row: ProductCategoryRecord) {
  return [
    normalizeCategoryScopeValue(row.first_product_group_code || catalogFilters.product_type),
    normalizeCategoryScopeValue(row.parent_id),
  ].join(':');
}

function getCategorySiblings(row: ProductCategoryRecord) {
  const scopeKey = categoryScopeKey(row);
  return categoryOptions.value.filter((item) => categoryScopeKey(item) === scopeKey);
}

function getCategorySiblingIndex(row: ProductCategoryRecord) {
  return getCategorySiblings(row).findIndex((item) => String(item.id) === String(row.id));
}

function canMoveCategory(row: ProductCategoryRecord, direction: 'up' | 'down') {
  if (categoryKeyword.value.trim() || categorySortLoadingId.value) return false;
  const siblings = getCategorySiblings(row);
  const currentIndex = getCategorySiblingIndex(row);
  if (currentIndex < 0) return false;
  return direction === 'up' ? currentIndex > 0 : currentIndex < siblings.length - 1;
}

function categoryMenuOptions(row: ProductCategoryRecord): DropdownOption[] {
  const options: DropdownOption[] = [
    { content: '上移', value: 'up', disabled: !canMoveCategory(row, 'up') },
    { content: '下移', value: 'down', disabled: !canMoveCategory(row, 'down') },
  ];
  if (productGroupLevel(row) === 2) {
    options.push({ content: '新建三级分类', value: 'create-child', divider: true });
  }
  options.push(
    { content: '编辑', value: 'edit', divider: productGroupLevel(row) !== 2 },
    { content: '删除', value: 'delete', theme: 'error' },
  );
  return options;
}

function handleCategoryMenuClick(row: ProductCategoryRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'up' || action === 'down') {
    void handleMoveCategory(row, action);
    return;
  }
  if (action === 'create-child') {
    openThirdCategoryDialog(row);
    return;
  }
  if (action === 'edit') {
    openCategoryDialog(row);
    return;
  }
  if (action === 'delete') {
    void handleDeleteCategory(row);
  }
}

function handleCategoryProductTypeChange(value: unknown) {
  const selectedParent = categoryOptions.value.find(
    (item) => productGroupLevel(item) === 2 && String(productGroupEffectiveId(item)) === String(categoryForm.parent_id),
  );
  if (selectedParent && String(selectedParent.first_product_group_code || '') !== String(value || '')) {
    categoryForm.parent_id = '';
  }
}

function resolveFirstProductGroupId(firstGroupCode: string) {
  const selectedType = categoryProductTypeOptions.value.find((item) => String(item.value) === String(firstGroupCode));
  return selectedType?.first_product_group_id || null;
}

function resolveSelectedParentCategory() {
  const parentId = String(categoryForm.parent_id || '');
  if (!parentId) return null;
  return (
    categoryOptions.value.find(
      (item) => productGroupLevel(item) === 2 && String(productGroupEffectiveId(item)) === parentId,
    ) || null
  );
}

// --- Data loading ---

async function loadCategories() {
  const requestVersion = ++categoryRequestVersion;
  categoryLoading.value = true;
  try {
    // 一次性加载全量分类树，一级节点在左树中作为商品类型根节点展示
    const response = await productApi.categories();
    if (requestVersion !== categoryRequestVersion) return;

    categoryOptions.value = flattenCategories(response.tree);
    ensureCatalogSelection();
    syncCategoryExpandedKeys(displayCategoryTree.value);
  } catch (error) {
    if (requestVersion !== categoryRequestVersion) return;
    categoryOptions.value = [];
    categoryExpandedKeys.value = new Set();
    MessagePlugin.error(errorMessage(error, '加载商品分类失败'));
  } finally {
    if (requestVersion === categoryRequestVersion) categoryLoading.value = false;
  }
}

async function handleMoveCategory(row: ProductCategoryRecord, direction: 'up' | 'down') {
  if (!canMoveCategory(row, direction)) return;

  const siblings = getCategorySiblings(row);
  const currentIndex = getCategorySiblingIndex(row);
  const reference = siblings[direction === 'up' ? currentIndex - 1 : currentIndex + 1];
  if (!reference) return;

  const nextSiblings = [...siblings];
  const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;
  [nextSiblings[currentIndex], nextSiblings[targetIndex]] = [nextSiblings[targetIndex], nextSiblings[currentIndex]];
  const level = productGroupLevel(row);
  const payload: Record<string, unknown> = {
    effective_product_group_level: level,
  };
  if (level === 1) {
    payload.first_product_group_ids = nextSiblings.map(productGroupEffectiveId);
  } else if (level === 2) {
    payload.first_product_group_id = row.first_product_group_id;
    payload.second_product_group_ids = nextSiblings.map(productGroupEffectiveId);
  } else {
    payload.second_product_group_id = row.second_product_group_id;
    payload.third_product_group_ids = nextSiblings.map(productGroupEffectiveId);
  }

  categorySortLoadingId.value = `${row.id}:${direction}`;
  try {
    await productApi.reorderCategory(payload);
    MessagePlugin.success('分类排序已更新');
    await reloadCatalogData();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新分类排序失败'));
  } finally {
    categorySortLoadingId.value = '';
  }
}

function shouldAutoRevealDeletedProducts(row: ProductCategoryRecord | null) {
  if (!row || catalogFilters.lifecycle_status !== 'active') return false;
  return categoryActiveProductCount(row) === 0 && categoryTotalProductCount(row) > 0;
}

async function loadProducts() {
  const requestVersion = ++productRequestVersion;
  const productType = String(catalogFilters.product_type || '').trim();
  productLoading.value = true;
  try {
    const selectedGroup = findProductGroupByKey(categoryOptions.value, catalogFilters.product_group_key);
    if (shouldAutoRevealDeletedProducts(selectedGroup)) {
      catalogFilters.lifecycle_status = 'all';
      MessagePlugin.info('当前分类仅包含已删除商品，已自动切换到“全部商品”');
    }
    const response = await productApi.list({
      keyword: catalogFilters.keyword,
      first_product_group_code: productType,
      status: catalogFilters.status,
      lifecycle_status: catalogFilters.lifecycle_status,
      ...productGroupPayload(selectedGroup),
      ...(productSort.value.sortBy
        ? { sort_by: productSort.value.sortBy, sort_order: productSort.value.descending ? 'desc' : 'asc' }
        : {}),
      page: productPage.value,
      page_size: productPageSize.value,
    });
    if (requestVersion !== productRequestVersion || productType !== catalogFilters.product_type) return;
    products.value = Array.isArray(response.list) ? response.list : [];
    selectedProductKeys.value = [];
    productTotal.value = Number(response.total || 0);
    productPage.value = Number(response.page || productPage.value);
    productPageSize.value = Number(response.page_size || productPageSize.value);
  } catch (error) {
    if (requestVersion !== productRequestVersion || productType !== catalogFilters.product_type) return;
    products.value = [];
    selectedProductKeys.value = [];
    productTotal.value = 0;
    MessagePlugin.error(errorMessage(error, '加载商品列表失败'));
  } finally {
    if (requestVersion === productRequestVersion) productLoading.value = false;
  }
}

async function reloadCatalogData() {
  await loadCategories();
  await loadProducts();
}
// --- Event handlers ---
function handleProductTypeChange(value: string) {
  catalogFilters.product_type = value;
  catalogFilters.product_group_key = '';
  selectedProductKeys.value = [];
  productPage.value = 1;
  syncCategoryExpandedKeys(displayCategoryTree.value);
  void loadProducts();
}

function handleMobileProductTypeChange(value: string) {
  handleProductTypeChange(value);
}

function handleCategorySelect(row: ProductCategoryRecord) {
  // 一级节点等价于原商品类型切换：展示该类型下全部商品
  if (productGroupLevel(row) === 1) {
    handleProductTypeChange(String(row.first_product_group_code || '').trim());
    return;
  }
  const node = findCategoryTreeNode(categoryTree.value, categoryIdKey(row));
  const target = node ? firstDisplayCategoryNode(node) : row;
  const targetKey = categoryIdKey(target);
  if (String(catalogFilters.product_group_key || '') === targetKey) return;
  catalogFilters.product_group_key = targetKey;
  productPage.value = 1;
  void loadProducts();
}

function handleMobileCategorySelect(row: ProductCategoryRecord) {
  handleCategorySelect(row);
  mobileCatalogDrawerVisible.value = false;
}

function handleCatalogSearch() {
  productPage.value = 1;
  void loadProducts();
}

function handleCatalogReset() {
  catalogFilters.keyword = '';
  catalogFilters.status = '';
  catalogFilters.lifecycle_status = 'active';
  productPage.value = 1;
  void loadProducts();
}

function handleProductSelectChange(value: Array<string | number>) {
  selectedProductKeys.value = value;
}

function clearProductSelection() {
  selectedProductKeys.value = [];
}

function selectedProductRows() {
  return products.value.filter((row) => selectedProductKeys.value.some((key) => String(key) === String(row.id)));
}

function selectedProductIdsFromKeys(keys: Array<string | number>) {
  return keys.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0);
}

function openBatchCategoryDialog() {
  const targetRows = selectedProductRows();
  if (!targetRows.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }

  batchCategoryTargetKeys.value = targetRows.map((row) => row.id);
  batchCategoryForm.target_product_group_key = '';
  batchCategoryDialogVisible.value = true;
}

async function openSplitProductDialog() {
  const targetRows = selectedProductRows();
  if (!targetRows.length) {
    MessagePlugin.warning('请先选择需要拆分的商品');
    return;
  }

  splitProductTargetKeys.value = targetRows.map((row) => row.id);
  splitProductPreview.value = null;
  splitProductDialogVisible.value = true;
  splitProductPreviewLoading.value = true;
  try {
    const response = await productApi.splitPreview({
      product_ids: selectedProductIdsFromKeys(splitProductTargetKeys.value),
    });
    splitProductPreview.value = toPlainRecord(response);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载拆分预览失败'));
  } finally {
    splitProductPreviewLoading.value = false;
  }
}

function normalizeProvisionHostnameRule(ruleValue: unknown) {
  const rule = toPlainRecord(ruleValue);
  const modeValue = String(rule.mode || 'system');
  const mode: ProvisionHostnameMode = ['system', 'fixed', 'prefix'].includes(modeValue)
    ? (modeValue as ProvisionHostnameMode)
    : 'system';
  const rawLength = Number(rule.length || 12);

  return {
    mode,
    value: String(rule.value || '').trim(),
    length: Number.isFinite(rawLength) ? Math.min(63, Math.max(4, rawLength)) : 12,
  };
}

function isSameProvisionHostnameRule(left: unknown, right: unknown) {
  const current = normalizeProvisionHostnameRule(left);
  const target = normalizeProvisionHostnameRule(right);
  return (
    current.mode === target.mode && current.value === target.value && Number(current.length) === Number(target.length)
  );
}

function openProvisionHostnameDialog() {
  const targetRows = selectedProductRows();
  if (!targetRows.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }

  const currentRule = normalizeProvisionHostnameRule(targetRows[0]?.provision_hostname);
  provisionHostnameTargetKeys.value = targetRows.map((row) => row.id);
  provisionHostnameHasMixedRules.value = targetRows.some(
    (row) => !isSameProvisionHostnameRule(row.provision_hostname, currentRule),
  );
  provisionHostnameForm.mode = currentRule.mode;
  provisionHostnameForm.value = currentRule.value;
  provisionHostnameForm.length = currentRule.length;
  provisionHostnameDialogVisible.value = true;
}

function resolveProductGroupLabel(key: string) {
  const group = findProductGroupByKey(categoryOptions.value, key);
  return group ? productGroupOptionLabel(group) : '目标分类';
}

async function submitBatchCategory() {
  const productIds = selectedProductIdsFromKeys(batchCategoryTargetKeys.value);
  const targetGroup = findProductGroupByKey(categoryOptions.value, batchCategoryForm.target_product_group_key);

  if (!productIds.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }
  if (!targetGroup) {
    MessagePlugin.warning('请选择目标分类');
    return;
  }

  batchCategorySubmitting.value = true;
  try {
    const response = await productApi.batchUpdateCategory({
      product_ids: productIds,
      ...productGroupPayload(targetGroup, 'target_'),
    });
    const responseRecord = response && typeof response === 'object' ? (response as Record<string, unknown>) : {};
    const updatedCount = Number(responseRecord.updated_count ?? productIds.length);
    const targetLabel = String(
      responseRecord.target_effective_product_group_full_name ||
        responseRecord.target_effective_product_group_name ||
        resolveProductGroupLabel(batchCategoryForm.target_product_group_key),
    );

    if (updatedCount > 0) {
      MessagePlugin.success(`已将 ${updatedCount} 个商品移入「${targetLabel}」`);
    } else {
      MessagePlugin.success(`所选商品已在「${targetLabel}」下`);
    }

    batchCategoryDialogVisible.value = false;
    batchCategoryTargetKeys.value = [];
    await reloadCatalogData();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '批量归类失败'));
  } finally {
    batchCategorySubmitting.value = false;
  }
}

async function submitSplitProducts() {
  const productIds = selectedProductIdsFromKeys(splitProductTargetKeys.value);
  if (!productIds.length) {
    MessagePlugin.warning('请先选择需要拆分的商品');
    return;
  }

  splitProductSubmitting.value = true;
  try {
    const response = await productApi.splitProducts({ product_ids: productIds });
    const responseRecord = toPlainRecord(response);
    const createdCount = Number(responseRecord.created_count || 0);
    const updatedCount = Number(responseRecord.updated_count || 0);
    const skippedCount = Number(responseRecord.skipped_count || 0);
    const changedCount = createdCount + updatedCount;

    if (changedCount > 0) {
      MessagePlugin.success(`已生成 ${createdCount} 个商品，更新 ${updatedCount} 个，跳过 ${skippedCount} 个`);
    } else {
      MessagePlugin.warning(skippedCount > 0 ? `未生成新商品，跳过 ${skippedCount} 个` : '未找到可拆分商品');
    }

    splitProductDialogVisible.value = false;
    splitProductTargetKeys.value = [];
    selectedProductKeys.value = [];
    await reloadCatalogData();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '拆分商品失败'));
  } finally {
    splitProductSubmitting.value = false;
  }
}

async function submitProvisionHostname() {
  const productIds = selectedProductIdsFromKeys(provisionHostnameTargetKeys.value);
  if (!productIds.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }
  if (provisionHostnameForm.mode !== 'system' && !provisionHostnameForm.value.trim()) {
    MessagePlugin.warning(provisionHostnameForm.mode === 'fixed' ? '请输入固定主机名' : '请输入主机名前缀');
    return;
  }

  provisionHostnameSubmitting.value = true;
  try {
    await productApi.batchUpdateProvisionHostname({
      product_ids: productIds,
      provision_hostname: {
        mode: provisionHostnameForm.mode,
        value: provisionHostnameForm.value.trim(),
        length: Number(provisionHostnameForm.length || 12),
      },
    });
    MessagePlugin.success('商品开通主机名规则已更新');
    provisionHostnameDialogVisible.value = false;
    provisionHostnameTargetKeys.value = [];
    selectedProductKeys.value = [];
    await loadProducts();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新主机名规则失败'));
  } finally {
    provisionHostnameSubmitting.value = false;
  }
}

function handleProductPageChange(pageInfo: PageInfo) {
  productPage.value = pageInfo.current;
  productPageSize.value = pageInfo.pageSize;
  void loadProducts();
}

function handleProductSortChange(value: unknown) {
  const sort = (Array.isArray(value) ? value[0] : value) as { sortBy?: unknown; descending?: unknown } | undefined;
  productSort.value = {
    sortBy: String(sort?.sortBy ?? '').trim(),
    descending: sort?.descending === true,
  };
  productPage.value = 1;
  void loadProducts();
}

function syncProductPricingCycles(planValue?: unknown) {
  const planKey = String(planValue || productPricingPlan.value || 'standard');
  const plan = productPricingPlanOptions.find((item) => item.value === planKey) || productPricingPlanOptions[0];
  const monthly = Number(productForm.monthly_price || 0);

  if (monthly <= 0) {
    MessagePlugin.warning('请先填写月付价格');
    return;
  }

  productPricingPlan.value = plan.value;
  productForm.quarterly_price = roundPricingAmount(monthly * plan.ratios.quarterly);
  productForm.semiannually_price = roundPricingAmount(monthly * plan.ratios.semiannually);
  productForm.annually_price = roundPricingAmount(monthly * plan.ratios.annually);
  MessagePlugin.success('已同步其他计费周期价格');
}

function roundPricingAmount(value: number) {
  return Number(value.toFixed(2));
}

function hasPositiveProductPrice() {
  return [
    productForm.monthly_price,
    productForm.quarterly_price,
    productForm.semiannually_price,
    productForm.annually_price,
  ].some((value) => Number(value || 0) > 0);
}

function closeProductDrawer() {
  productDialogVisible.value = false;
}

function setProductDrawerSection(key: string) {
  activeProductDrawerSection.value = key;
}

function resolveProductCustomDisplayNamePayload() {
  const value = String(productForm.display_name || '').trim();
  if (!value) return null;

  const defaultDisplayName = String(productForm.product_spec_display || '').trim();
  return defaultDisplayName && value === defaultDisplayName ? null : value;
}

async function submitProduct() {
  const validateResult = await productFormRef.value?.validate?.();
  if (validateResult !== true) return;
  if (!hasPositiveProductPrice()) {
    activeProductDrawerSection.value = 'pricing';
    MessagePlugin.warning('请至少填写一个大于 0 的计费周期价格');
    return;
  }
  productSubmitting.value = true;
  try {
    const payload = {
      custom_display_name: resolveProductCustomDisplayNamePayload(),
      ...productGroupPayload(findProductGroupByKey(categoryOptions.value, productForm.selected_product_group_key)),
      pricing: {
        monthly: productForm.monthly_price,
        quarterly: productForm.quarterly_price,
        semiannually: productForm.semiannually_price,
        annually: productForm.annually_price,
      },
      auto_setup: productForm.auto_setup,
      status: productForm.status,
      upstream_binding: {
        supplier_id: productForm.supplier_id || undefined,
        upstream_product_id: productForm.upstream_product_id || undefined,
      },
      config_options: serializeConfigOptions(productForm.config_options),
    };
    if (editingProduct.value?.id) {
      await productApi.update(editingProduct.value.id, payload);
      MessagePlugin.success('商品已更新');
    } else {
      await productApi.create(payload);
      MessagePlugin.success('商品已创建');
    }
    productDialogVisible.value = false;
    await loadProducts();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存商品失败'));
  } finally {
    productSubmitting.value = false;
  }
}

async function handleToggleProduct(row: ProductRecord) {
  const newStatus = Number(row.status) !== 1;
  const actionLabel = newStatus ? '显示' : '隐藏';
  const dialog = DialogPlugin.confirm({
    header: `${actionLabel}商品`,
    body: `确定${actionLabel}「${row.display_name || row.name || row.id}」吗？${newStatus ? '' : '隐藏后前台不显示，可在筛选中找回。'}`,
    confirmBtn: { content: '确定', theme: 'primary' },
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = row.id;
      try {
        await productApi.toggleStatus(row.id, newStatus);
        MessagePlugin.success({ content: `商品已${actionLabel}`, duration: 3000 });
        dialog.hide();
        await loadProducts();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '更新状态失败'));
      } finally {
        productActionLoading.value = null;
      }
    },
  });
}

function handleDeleteProduct(row: ProductRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除商品',
    body: `确认删除「${row.display_name || row.name || row.id}」吗？${Number(row.active_services_count ?? row.services_count ?? 0) > 0 ? ` 该商品下有 ${row.active_services_count ?? row.services_count} 个现存服务，删除后可能影响已开通实例。` : ''}`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = row.id;
      try {
        await productApi.delete(row.id);
        MessagePlugin.success({ content: '商品已删除', duration: 3000 });
        dialog.hide();
        if (catalogFilters.lifecycle_status === 'all') {
          MessagePlugin.info('当前筛选为“全部商品”，已删除商品仍会显示在列表中');
        }
        await loadProducts();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除商品失败'));
      } finally {
        productActionLoading.value = null;
      }
    },
  });
}

function handleRestoreProduct(row: ProductRecord) {
  const dialog = DialogPlugin.confirm({
    header: '恢复商品',
    body: `确认恢复《${row.display_name || row.name || row.id}》吗？`,
    confirmBtn: { content: '确定', theme: 'primary' },
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = `restore:${row.id}`;
      try {
        await productApi.restore(row.id);
        MessagePlugin.success('商品已恢复');
        dialog.hide();
        await loadProducts();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '恢复商品失败'));
      } finally {
        productActionLoading.value = null;
      }
    },
  });
}

function handleForceDeleteProduct(row: ProductRecord) {
  const dialog = DialogPlugin.confirm({
    header: '彻底删除商品',
    body: `确认彻底删除《${row.display_name || row.name || row.id}》吗？该操作不可恢复。`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = `force:${row.id}`;
      try {
        await productApi.forceDelete(row.id);
        MessagePlugin.success('商品已彻底删除');
        dialog.hide();
        await loadProducts();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '彻底删除商品失败'));
      } finally {
        productActionLoading.value = null;
      }
    },
  });
}

function openSecondCategoryDialog() {
  openCategoryDialog();
}

function openThirdCategoryDialog(parent: ProductCategoryRecord) {
  openCategoryDialog(undefined, parent);
}

function openCategoryDialog(row?: ProductCategoryRecord, thirdCategoryParent?: ProductCategoryRecord) {
  editingCategory.value = row || null;
  creatingThirdCategoryParent.value = !row && thirdCategoryParent ? thirdCategoryParent : null;
  const level = row ? productGroupLevel(row) : thirdCategoryParent ? 3 : 2;
  const parentFirstGroupCode = thirdCategoryParent?.first_product_group_code || '';
  Object.assign(categoryForm, {
    name: row?.name || row?.label || '',
    product_type: String(
      row?.first_product_group_code ||
        parentFirstGroupCode ||
        catalogFilters.product_type ||
        categoryProductTypeOptions.value[0]?.value ||
        '',
    ),
    parent_id: level === 3 ? row?.second_product_group_id || productGroupEffectiveId(thirdCategoryParent) || '' : '',
    slogan: String(row?.slogan || ''),
    sort_order: Number(row?.sort_order || 0),
    is_visible: Number(row?.is_visible ?? 1),
  });
  categoryDialogVisible.value = true;
}

async function submitCategory() {
  const validateResult = await categoryFormRef.value?.validate?.();
  if (validateResult !== true) return;
  categorySubmitting.value = true;
  try {
    const firstGroupCode = categoryForm.product_type || catalogFilters.product_type || '';
    let firstProductGroupId = resolveFirstProductGroupId(firstGroupCode);
    const selectedParent = resolveSelectedParentCategory();
    const targetLevel = editingCategory.value
      ? productGroupLevel(editingCategory.value)
      : creatingThirdCategoryParent.value
        ? 3
        : 2;
    if (targetLevel === 2 && !firstProductGroupId) {
      await loadProductTypes();
      firstProductGroupId = resolveFirstProductGroupId(firstGroupCode);
    }
    if (targetLevel === 2 && !firstProductGroupId) {
      MessagePlugin.warning('一级分类数据未同步，请刷新一级分类后重试');
      return;
    }
    if (targetLevel === 3 && !selectedParent) {
      MessagePlugin.warning('请选择二级分类父级');
      return;
    }
    const payload: Record<string, unknown> = {
      name: categoryForm.name,
      effective_product_group_level: targetLevel,
      first_product_group_id: targetLevel === 2 ? firstProductGroupId || undefined : undefined,
      first_product_group_code: targetLevel === 1 ? firstGroupCode || undefined : undefined,
      product_type:
        targetLevel === 1
          ? normalizeBusinessProductType(
              categoryProductTypeOptions.value.find((item) => String(item.value) === String(firstGroupCode))
                ?.product_type,
            )
          : undefined,
      second_product_group_id: targetLevel === 3 ? productGroupEffectiveId(selectedParent) || undefined : undefined,
      slogan: categoryForm.slogan,
      sort_order: categoryForm.sort_order,
      is_visible: categoryForm.is_visible,
    };
    if (editingCategory.value?.id) {
      await productApi.updateCategory(productGroupEffectiveId(editingCategory.value), payload);
      MessagePlugin.success('分类已更新');
    } else {
      await productApi.createCategory(payload);
      MessagePlugin.success('分类已创建');
    }
    categoryDialogVisible.value = false;
    await reloadCatalogData();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存分类失败'));
  } finally {
    categorySubmitting.value = false;
  }
}

function handleDeleteCategory(row: ProductCategoryRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除分类',
    body: `确认删除「${row.name || row.label || row.id}」吗？`,
    theme: 'warning',
    confirmBtn: { content: '确定', theme: 'danger' },
    cancelBtn: '取消',
    async onConfirm() {
      categorySubmitting.value = true;
      try {
        await productApi.deleteCategory(productGroupEffectiveId(row), {
          effective_product_group_level: productGroupLevel(row),
        });
        MessagePlugin.success('分类已删除');
        dialog.hide();
        if (catalogFilters.product_group_key === categoryIdKey(row)) {
          catalogFilters.product_group_key = '';
        }
        await reloadCatalogData();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除分类失败'));
      } finally {
        categorySubmitting.value = false;
      }
    },
  });
}

function formatSplitAction(action: string) {
  return action === 'update' ? '更新' : '新建';
}

// --- Init ---
async function loadCatalog() {
  await loadProductTypes();
  await reloadCatalogData();
}

onMounted(() => {
  void loadCatalog();
});
</script>

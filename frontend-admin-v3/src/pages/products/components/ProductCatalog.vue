<template>
  <div>
    <t-card :bordered="false" class="type-card">
      <div class="type-strip">
        <t-button
          v-for="item in productTypeOptions"
          :key="item.value"
          :theme="catalogFilters.product_type === item.value ? 'primary' : 'default'"
          :variant="catalogFilters.product_type === item.value ? 'base' : 'outline'"
          @click="handleProductTypeChange(item.value)"
        >
          {{ item.label }}
          <span class="type-count">{{ item.usage_count || 0 }}</span>
        </t-button>
        <t-button variant="outline" :loading="typeLoading" @click="loadProductTypes">刷新一级分类</t-button>
        <t-button variant="outline" @click="openTypeManagerDialog()">管理一级分类</t-button>
      </div>
    </t-card>

    <div class="catalog-layout">
      <t-card :bordered="false" class="category-panel">
        <div class="category-panel-head">
          <div class="category-panel-title">
            <strong>商品分类</strong>
            <span>{{ visibleCategoryCount }} / {{ categoryOptions.length }}</span>
          </div>
          <t-space size="small">
            <t-button shape="square" variant="text" :loading="categoryLoading" @click="loadCategories">
              <template #icon><refresh-icon /></template>
            </t-button>
            <t-button theme="primary" size="small" @click="openCategoryDialog()">
              <template #icon><add-icon /></template>
              新增
            </t-button>
          </t-space>
        </div>
        <div class="category-toolbar">
          <t-input v-model="categoryKeyword" clearable placeholder="搜索分类或子菜单">
            <template #prefix-icon><search-icon /></template>
          </t-input>
        </div>

        <div v-if="categoryTreeRows.length" class="category-tree">
          <div
            v-for="{ node: item, level, childCount } in categoryTreeRows"
            :key="String(item.id)"
            class="category-tree-row"
            :class="{ active: isCategoryRowActive(item), 'active-parent': isCategoryRowParentOfSelected(item), disabled: categorySortLoadingId }"
            :style="{ '--category-level': level }"
          >
            <button
              type="button"
              class="category-expand"
              :class="{ visible: childCount > 0, expanded: isCategoryExpanded(item) }"
              :disabled="childCount <= 0"
              @click.stop="toggleCategoryExpanded(item)"
            />
            <button type="button" class="category-drag" aria-label="排序占位" disabled>::</button>
            <button type="button" class="category-tree-main" @click="handleCategorySelect(item)">
              <span class="category-title-line">
                <span class="category-name" :class="{ 'is-hidden': !isCategoryVisible(item) }">
                  {{ categoryDisplayName(item) }}
                </span>
              </span>
              <small class="category-count">{{ categoryCountLabel(item, childCount) }}</small>
            </button>
            <t-dropdown
              class="category-menu"
              trigger="click"
              placement="bottom-right"
              :options="categoryMenuOptions(item)"
              @click="handleCategoryMenuClick(item, $event)"
            >
              <t-button class="category-menu-trigger" size="small" shape="square" variant="text">...</t-button>
            </t-dropdown>
          </div>
        </div>
        <t-empty v-else description="暂无分类" />
      </t-card>

      <t-card :bordered="false" class="product-table-card">
        <button type="button" class="mobile-catalog-sidebar-entry" @click="mobileCatalogDrawerVisible = true">
          <span>筛选分类</span>
          <strong>{{ selectedProductTypeLabel }} / {{ selectedCategoryLabel }}</strong>
        </button>

        <div class="filter-row catalog-filter-row">
          <t-input
            class="catalog-filter-keyword"
            v-model="catalogFilters.keyword"
            clearable
            placeholder="搜索商品名称"
            @enter="handleCatalogSearch"
            @clear="handleCatalogSearch"
          >
            <template #suffix-icon><search-icon /></template>
          </t-input>
          <t-select
            class="catalog-filter-status"
            v-model="catalogFilters.status"
            clearable
            placeholder="显示状态"
            @change="handleCatalogSearch"
          >
            <t-option :value="1" label="显示中" />
            <t-option :value="0" label="已隐藏" />
          </t-select>
          <t-select
            class="catalog-filter-status"
            v-model="catalogFilters.lifecycle_status"
            placeholder="商品范围"
            @change="handleCatalogSearch"
          >
            <t-option v-for="item in lifecycleStatusOptions" :key="item.value" :value="item.value" :label="item.label" />
          </t-select>
          <div class="catalog-filter-actions">
            <t-button theme="primary" @click="handleCatalogSearch">
              <template #icon><search-icon /></template>
              搜索
            </t-button>
            <t-button variant="outline" @click="resetCatalogFilters">
              <template #icon><refresh-icon /></template>
              重置
            </t-button>
            <t-button theme="primary" @click="openProductDialog()">
              <template #icon><add-icon /></template>
              新增商品
            </t-button>
          </div>
        </div>

        <div class="product-batch-bar">
          <span>已选 {{ selectedProductKeys.length }} 个商品</span>
          <t-space size="small">
            <t-button variant="outline" :disabled="!selectedProductKeys.length" @click="openBatchCategoryDialog">
              批量归类
            </t-button>
            <t-button
              variant="outline"
              :disabled="!selectedProductKeys.length"
              :loading="splitProductPreviewLoading"
              @click="openSplitProductDialog"
            >
              拆分商品
            </t-button>
            <t-button variant="outline" :disabled="!selectedProductKeys.length" @click="openProvisionHostnameDialog">
              批量主机名
            </t-button>
            <t-button variant="text" :disabled="!selectedProductKeys.length" @click="clearProductSelection">清空选择</t-button>
          </t-space>
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
            @select-change="handleProductSelectChange"
          >
            <template #name="{ row }">
              <div class="product-name">
                <strong :class="{ 'is-hidden': !isProductVisible(row) }">
                  {{ productSpecDisplayName(row) }}
                </strong>
                <span>{{ productSubtitle(row) }}</span>
              </div>
            </template>
            <template #status="{ row }">
              <span class="visibility-status-text" :class="{ 'is-hidden': !isProductVisible(row), 'is-deleted': row.is_deleted }">
                {{ productVisibilityLabel(row) }}
              </span>
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
              <t-space size="small">
                <template v-if="row.is_deleted">
                  <t-button
                    size="small"
                    variant="text"
                    theme="primary"
                    :loading="productActionLoading === `restore:${row.id}`"
                    @click="handleRestoreProduct(row)"
                  >
                    恢复
                  </t-button>
                  <t-button
                    size="small"
                    variant="text"
                    theme="danger"
                    :loading="productActionLoading === `force:${row.id}`"
                    @click="handleForceDeleteProduct(row)"
                  >
                    彻底删除
                  </t-button>
                </template>
                <template v-else>
                  <t-button size="small" variant="text" theme="primary" @click="openProductDialog(row)">编辑</t-button>
                  <t-button size="small" variant="text" :loading="productActionLoading === row.id" @click="handleToggleProduct(row)">
                    {{ Number(row.status) === 1 ? '隐藏' : '显示' }}
                  </t-button>
                  <t-button size="small" variant="text" theme="danger" @click="handleDeleteProduct(row)">删除</t-button>
                </template>
              </t-space>
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
      </t-card>
    </div>

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
          <button type="button" class="mobile-type-tool" :disabled="typeLoading" @click="loadProductTypes">刷新一级分类</button>
          <button type="button" class="mobile-type-tool" @click="openTypeManagerDialog()">管理一级分类</button>
        </aside>

        <section class="mobile-category-sidebar">
          <div class="category-panel-head">
            <div class="category-panel-title">
              <strong>商品分类</strong>
              <span>{{ visibleCategoryCount }} / {{ categoryOptions.length }}</span>
            </div>
            <t-space size="small">
              <t-button shape="square" variant="text" :loading="categoryLoading" @click="loadCategories">
                <template #icon><refresh-icon /></template>
              </t-button>
              <t-button theme="primary" size="small" @click="openCategoryDialog()">
                <template #icon><add-icon /></template>
                新增
              </t-button>
            </t-space>
          </div>
          <div class="category-toolbar">
            <t-input v-model="categoryKeyword" clearable placeholder="搜索分类或子菜单">
              <template #prefix-icon><search-icon /></template>
            </t-input>
          </div>

          <div v-if="categoryTreeRows.length" class="category-tree mobile-category-tree">
            <div
              v-for="{ node: item, level, childCount } in categoryTreeRows"
              :key="String(item.id)"
              class="category-tree-row"
              :class="{ active: isCategoryRowActive(item), 'active-parent': isCategoryRowParentOfSelected(item), disabled: categorySortLoadingId }"
              :style="{ '--category-level': level }"
            >
              <button
                type="button"
                class="category-expand"
                :class="{ visible: childCount > 0, expanded: isCategoryExpanded(item) }"
                :disabled="childCount <= 0"
                @click.stop="toggleCategoryExpanded(item)"
              />
              <button type="button" class="category-drag" aria-label="排序占位" disabled>::</button>
              <button type="button" class="category-tree-main" @click="handleMobileCategorySelect(item)">
                <span class="category-title-line">
                  <span class="category-name" :class="{ 'is-hidden': !isCategoryVisible(item) }">
                    {{ categoryDisplayName(item) }}
                  </span>
                </span>
                <small class="category-count">{{ categoryCountLabel(item, childCount) }}</small>
              </button>
              <t-dropdown
                class="category-menu"
                trigger="click"
                placement="bottom-right"
                :options="categoryMenuOptions(item)"
                @click="handleCategoryMenuClick(item, $event)"
              >
                <t-button class="category-menu-trigger" size="small" shape="square" variant="text">...</t-button>
              </t-dropdown>
            </div>
          </div>
          <t-empty v-else description="暂无分类" />
        </section>
      </div>
    </t-drawer>

    <t-drawer
      v-model:visible="productDialogVisible"
      :header="editingProduct ? '编辑商品' : '新增商品'"
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
              :class="['product-drawer-nav-item', { active: activeProductDrawerSection === section.key }]"
              @click="setProductDrawerSection(section.key)"
            >
              <strong>{{ section.label }}</strong>
              <span>{{ section.description }}</span>
            </button>
          </aside>

          <div class="product-drawer-main">
            <t-form ref="productFormRef" class="product-drawer-form" :data="productForm" :rules="productRules" label-width="120px">
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
                  <t-form-item label="状态" name="status"><t-switch v-model="productForm.status" :custom-value="[1, 0]" /></t-form-item>
                </div>
              </section>

              <section v-show="activeProductDrawerSection === 'pricing'" class="product-drawer-section" data-title="定价">
                <div class="product-pricing-actions">
                  <t-select v-model="productPricingPlan" size="small" style="width: 150px" @change="syncProductPricingCycles">
                    <t-option v-for="item in productPricingPlanOptions" :key="item.value" :label="item.label" :value="item.value" />
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

              <section v-show="activeProductDrawerSection === 'interface'" class="product-drawer-section product-interface-section" data-title="自动开通">
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
                    <t-form-item label="提供商商品" name="supplier_product_id">
                      <div class="supplier-product-row">
                        <t-cascader
                        v-model="productForm.supplier_product_id"
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
                    <t-form-item label="上架状态" name="status">
                      <div class="product-switch-line">
                        <span>下架</span>
                        <t-switch v-model="productForm.status" :custom-value="[1, 0]" />
                        <span>上架</span>
                      </div>
                    </t-form-item>
                  </div>
                </div>
              </section>

              <section v-show="activeProductDrawerSection === 'config'" class="product-drawer-section" data-title="产品配置">
                <div class="product-config-actions">
                  <t-button size="small" variant="outline" :loading="configTemplateLoading" @click="pullProductConfigTemplate">
                    拉取模板
                  </t-button>
                  <t-button size="small" theme="primary" variant="outline" @click="openConfigOptionDialog()">新增配置</t-button>
                </div>
                <div class="config-option-panel">
                  <div class="config-option-count">{{ productForm.config_options.length }} 项配置，保存商品后生效。</div>
                  <div v-if="productForm.config_options.length" class="config-option-list">
                    <article v-for="(item, index) in productForm.config_options" :key="item.uid || item.field || index">
                      <div>
                        <strong>{{ item.name || item.option_name || item.field }}</strong>
                        <span>{{ item.field || '-' }} · {{ item.option_mode || 'select' }}</span>
                      </div>
                      <t-space size="small">
                        <t-button size="small" variant="text" theme="primary" @click="openConfigOptionDialog(item, index)">编辑</t-button>
                        <t-button size="small" variant="text" theme="danger" @click="removeConfigOption(index)">删除</t-button>
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
              <DeleteIcon />
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
      :confirm-btn="{ content: '确认归类', loading: batchCategorySubmitting }"
      @confirm="submitBatchCategory"
    >
      <div class="batch-dialog-summary">将 {{ batchCategoryTargetKeys.length }} 个商品移动到目标分类。</div>
      <t-form :data="batchCategoryForm" label-width="110px">
        <t-form-item label="目标分类" name="target_product_group_key">
          <t-select v-model="batchCategoryForm.target_product_group_key" filterable clearable placeholder="请选择目标分类">
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
        content: '确认拆分',
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
      :header="editingCategory ? '编辑分类' : '新增分类'"
      width="560px"
      :confirm-btn="{ content: '保存', loading: categorySubmitting }"
      @confirm="submitCategory"
    >
      <t-form ref="categoryFormRef" :data="categoryForm" :rules="categoryRules" label-width="110px">
        <t-form-item label="分类名称" name="name"><t-input v-model="categoryForm.name" /></t-form-item>
        <t-form-item label="二级分类父级" name="product_type">
          <t-select v-model="categoryForm.product_type" filterable placeholder="请选择一级分类" @change="handleCategoryProductTypeChange">
            <t-option v-for="item in categoryProductTypeOptions" :key="item.value" :label="item.label" :value="item.value" />
          </t-select>
        </t-form-item>
        <t-form-item label="三级分类父级" name="parent_id">
          <t-select
            v-model="categoryForm.parent_id"
            clearable
            filterable
            placeholder="不选择则作为二级分类"
            :disabled="editingCategoryHasChildren || !categoryParentCategoryOptions.length"
          >
            <t-option
              v-for="item in categoryParentCategoryOptions"
              :key="item.id"
              :label="item.label || item.name"
              :value="item.id"
            />
          </t-select>
        </t-form-item>
        <t-form-item label="一句话说明" name="slogan"><t-input v-model="categoryForm.slogan" /></t-form-item>
        <t-form-item label="排序" name="sort_order"><t-input-number v-model="categoryForm.sort_order" :min="0" /></t-form-item>
        <t-form-item label="前台可见" name="is_visible"><t-switch v-model="categoryForm.is_visible" :custom-value="[1, 0]" /></t-form-item>
      </t-form>
    </t-dialog>

    <t-dialog v-model:visible="typeManagerDialogVisible" header="管理一级分类" width="760px" :footer="false">
      <div class="type-manager">
        <t-form :data="typeForm" label-width="88px" class="type-form">
          <t-form-item label="一级分类名称" name="label"><t-input v-model="typeForm.label" /></t-form-item>
          <t-form-item label="图标名称" name="icon"><t-input v-model="typeForm.icon" /></t-form-item>
          <t-form-item>
            <t-space>
              <t-button theme="primary" :loading="typeSubmitting" @click="submitType">
                {{ editingTypeValue ? '保存一级分类' : '新增一级分类' }}
              </t-button>
              <t-button variant="outline" @click="resetTypeForm">重置</t-button>
            </t-space>
          </t-form-item>
        </t-form>

        <div class="type-manager-list">
          <article v-for="(item, index) in productTypes" :key="item.value" class="type-manager-item">
            <div>
              <strong>{{ item.label }}</strong>
              <span>{{ item.value }} · {{ item.usage_count || 0 }} 个商品 · {{ item.group_count || 0 }} 个分组</span>
            </div>
            <t-tag v-if="item.is_hidden" theme="warning" variant="light">隐藏</t-tag>
            <t-space size="small" class="type-manager-actions">
              <t-button size="small" variant="text" :disabled="index === 0 || typeSubmitting" @click="moveType(item.value, -1)">上移</t-button>
              <t-button
                size="small"
                variant="text"
                :disabled="index === productTypes.length - 1 || typeSubmitting"
                @click="moveType(item.value, 1)"
              >
                下移
              </t-button>
              <t-button size="small" variant="text" theme="primary" @click="editType(item)">编辑</t-button>
              <t-button size="small" variant="text" @click="toggleTypeHidden(item)">
                {{ item.is_hidden ? '显示' : '隐藏' }}
              </t-button>
              <t-button size="small" variant="text" theme="danger" @click="deleteType(item)">删除</t-button>
            </t-space>
          </article>
          <t-empty v-if="productTypes.length === 0" description="暂无一级分类" />
        </div>
        <div class="type-manager-footer">
          <t-button variant="outline" @click="typeManagerDialogVisible = false">关闭</t-button>
        </div>
      </div>
    </t-dialog>
  </div>
</template>

<script setup lang="ts">
import { AddIcon, DeleteIcon, RefreshIcon, SearchIcon } from 'tdesign-icons-vue-next';
import type { DropdownOption, PageInfo, PrimaryTableCol } from 'tdesign-vue-next';
import { DialogPlugin, MessagePlugin } from 'tdesign-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

import { productApi, type ProductCategoryRecord, type ProductRecord, type ProductTypeRecord } from '@/api/product';
import { supplierApi, type ProviderTypeRecord, type SupplierRecord } from '@/api/supplier';

import {
  errorMessage,
  flattenCategories,
  formatCount,
  formatMonthlyPrice,
  formatStock,
  findProductGroupByKey,
  isSelectableProductGroup,
  mergeProviderTypeOptions,
  normalizeProductIds,
  normalizeProviderTypeOptions,
  productGroupEffectiveId,
  productGroupLevel,
  productGroupOptionKey,
  productGroupOptionLabel,
  productGroupPayload,
  providerTypeFallbackLabels,
  providerTypeLabel,
  toPlainRecord,
} from '../composables/useProductShared';

type ProvisionHostnameMode = 'system' | 'fixed' | 'prefix';

interface SplitProductPreviewRow {
  key: string;
  product_id: number;
  display_name: string;
  source_product_id: number;
  source_display_name: string;
  action: string;
}

interface SupplierBatchProduct {
  id: number;
  name: string;
  type_label: string;
  remote_group_name: string;
  is_connected: boolean;
  connected_display_name: string;
  [key: string]: unknown;
}

interface CategoryTreeNode {
  item: ProductCategoryRecord;
  children: CategoryTreeNode[];
}

interface ProductConfigOptionRecord {
  uid?: string;
  field: string;
  name: string;
  option_name?: string;
  option_mode: string;
  parameter?: string;
  sub?: Array<Record<string, unknown>>;
  sub_items?: Array<Record<string, unknown>>;
  required?: boolean;
  hidden?: boolean;
  sort_order?: number;
  [key: string]: unknown;
}

interface ConfigOptionSubItemFormRow {
  uid: string;
  name: string;
  value: string;
  monthly_price: string;
  sort_order: number;
}

type ProductLifecycleStatus = 'active' | 'deleted' | 'all';

// --- State ---
const summaryLoading = ref(false);
const typeLoading = ref(false);
const typeSubmitting = ref(false);
const categoryLoading = ref(false);
const productLoading = ref(false);
const productSubmitting = ref(false);
const productActionLoading = ref<number | string | null>(null);

const productSummary = reactive({
  groups_total: 0,
  root_groups_total: 0,
  sub_groups_total: 0,
  products_total: 0,
  products_active: 0,
  products_low_stock: 0,
});
const productTypes = ref<ProductTypeRecord[]>([]);
const categoryOptions = ref<ProductCategoryRecord[]>([]);
const products = ref<ProductRecord[]>([]);
const productTotal = ref(0);
const productPage = ref(1);
const productPageSize = ref(20);
const categoryKeyword = ref('');
const activeProductDrawerSection = ref('basic');
const categoryExpandedKeys = ref<Set<string>>(new Set());
const mobileCatalogDrawerVisible = ref(false);
const productSupplierOptions = ref<SupplierRecord[]>([]);
const productSupplierLoading = ref(false);
const supplierProductOptions = ref<SupplierBatchProduct[]>([]);
const supplierProductLoading = ref(false);
const providerTypes = ref<ProviderTypeRecord[]>([]);
const typeManagerDialogVisible = ref(false);
const editingTypeValue = ref('');
const typeForm = reactive({
  label: '',
  icon: '',
});

const catalogFilters = reactive({
  keyword: '',
  product_type: '',
  product_group_key: '' as string,
  status: '' as number | string,
  lifecycle_status: 'active' as ProductLifecycleStatus,
});

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
  supplier_product_id: '' as number | string,
  config_options: [] as ProductConfigOptionRecord[],
});
const configOptionDialogVisible = ref(false);
const configOptionSubmitting = ref(false);
const configOptionEditingIndex = ref(-1);
const configTemplateLoading = ref(false);
const configOptionForm = reactive({
  name: '',
  field: '',
  option_mode: 'select',
  parameter: '',
  sub_items_text: '',
  description: '',
  suffix_text: '',
  advanced: true,
  required: true,
  hidden: false,
  sort_order: 0,
});
const configOptionSubItemRows = ref<ConfigOptionSubItemFormRow[]>([]);
const categoryDialogVisible = ref(false);
const editingCategory = ref<ProductCategoryRecord | null>(null);
const categoryFormRef = ref();
const categorySubmitting = ref(false);
const categorySortLoadingId = ref('');
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
  { colKey: 'id', title: 'ID', width: 80 },
  { colKey: 'name', title: '商品', minWidth: 220 },
  { colKey: 'effective_product_group_full_name', title: '分类', width: 180 },
  { colKey: 'price', title: '月价格', width: 120 },
  { colKey: 'stock', title: '库存', width: 100 },
  { colKey: 'services_count', title: '现存', width: 100 },
  { colKey: 'status', title: '显示状态', width: 110 },
  { colKey: 'operation', title: '操作', width: 180, fixed: 'right' },
];

const productRules = {
  display_name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  selected_product_group_key: [{ required: true, message: '请选择所属分类', trigger: 'change' }],
};
const categoryRules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
  product_type: [{ required: true, message: '请选择二级分类父级', trigger: 'change' }],
};

// --- Computeds ---
const productTypeOptions = computed(() => {
  if (productTypes.value.length) return productTypes.value;
  return [{ value: '', label: '全部商品', usage_count: productSummary.products_total }];
});
const selectedProductTypeLabel = computed(() => {
  const current = productTypeOptions.value.find((item) => String(item.value) === String(catalogFilters.product_type));
  return current?.label || '全部商品';
});
const categoryProductTypeOptions = computed(() => {
  const options = productTypeOptions.value.filter((item) => String(item.value || '').trim());
  if (options.length) return options;
  return catalogFilters.product_type ? [{ value: catalogFilters.product_type, label: selectedProductTypeLabel.value }] : [];
});
const categoryTree = computed(() => buildCategoryTree(categoryOptions.value));
// 顶部 type-strip 已展示一级分类（L1，例如"云服务器"），分类树里跳过该层，
// 将 L2（例如"襄阳"）作为树根展示，L3（例如"高宽"）作为其子节点，形成一级 + 二级菜单。
const displayCategoryTree = computed(() => {
  const lifted: CategoryTreeNode[] = [];
  categoryTree.value.forEach((root) => {
    if (productGroupLevel(root.item) === 1 && root.children.length) {
      lifted.push(...root.children);
    } else {
      lifted.push(root);
    }
  });
  return lifted;
});
const filteredCategoryTree = computed(() => filterCategoryTree(displayCategoryTree.value, categoryKeyword.value.trim().toLowerCase()));
const categoryTreeRows = computed(() => flattenCategoryTreeRows(filteredCategoryTree.value));
const selectableProductGroupOptions = computed(() => categoryOptions.value.filter((item) => isSelectableProductGroup(item)));
const visibleCategoryCount = computed(() => categoryTreeRows.value.length);
const editingCategoryHasChildren = computed(() => {
  const current = editingCategory.value;
  if (!current) return false;
  if (Array.isArray(current.children) && current.children.length > 0) return true;
  return Number(current.children_count || 0) > 0;
});
const categoryParentCategoryOptions = computed(() => {
  const editingCategoryKey = editingCategory.value ? productGroupOptionKey(editingCategory.value) : '';
  const selectedProductType = String(categoryForm.product_type || catalogFilters.product_type || '');

  return categoryOptions.value.filter((item) => {
    if (editingCategoryKey && productGroupOptionKey(item) === editingCategoryKey) return false;
    if (productGroupLevel(item) !== 2) return false;
    if (selectedProductType && String(item.product_type || '') && String(item.product_type) !== selectedProductType) return false;
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
const providerTypeOptions = computed(() => {
  return mergeProviderTypeOptions(providerTypes.value);
});
const selectedProductSupplier = computed(() =>
  productSupplierOptions.value.find((item) => String(item.id) === String(productForm.supplier_id)) || null,
);
const supplierProductCascaderOptions = computed(() => {
  const groups = new Map<string, SupplierBatchProduct[]>();
  supplierProductOptions.value.forEach((item) => {
    const groupName = String(item.remote_group_name || item.type_label || '默认分组');
    const list = groups.get(groupName) || [];
    list.push(item);
    groups.set(groupName, list);
  });

  return Array.from(groups.entries()).map(([groupName, items], index) => ({
    label: groupName,
    value: `supplier-product-group-${index}`,
    children: items.map((item) => ({
      label: supplierProductOptionLabel(item),
      value: item.id,
    })),
  }));
});

// --- Category tree helpers ---
function categoryIdKey(row: ProductCategoryRecord) {
  return productGroupOptionKey(row);
}

function categoryDisplayName(row: ProductCategoryRecord) {
  return String(row.name || row.label || `分类 #${row.id}`).trim();
}

function isCategoryVisible(row: ProductCategoryRecord) {
  const value = row.is_visible;
  return !(value === false || value === 0 || value === '0');
}

function countValue(value: unknown) {
  const count = Number(value);
  return Number.isFinite(count) && count > 0 ? count : 0;
}

function categoryActiveProductCount(row: ProductCategoryRecord) {
  return countValue(row.products_count ?? row.product_count);
}

function categoryTotalProductCount(row: ProductCategoryRecord) {
  const total = countValue(row.products_with_trashed_count);
  return total > 0 ? total : categoryActiveProductCount(row);
}

function categoryCountLabel(row: ProductCategoryRecord, childCount: number) {
  if (childCount > 0) return `${childCount} 个子分类`;

  const activeCount = categoryActiveProductCount(row);
  const totalCount = categoryTotalProductCount(row);
  const deletedCount = Math.max(totalCount - activeCount, 0);

  if (deletedCount > 0) return `${totalCount} 个商品（含 ${deletedCount} 个已删除）`;

  return `${activeCount} 个商品`;
}

function productVisibilityLabel(row: ProductRecord) {
  if (row.is_deleted) return '已删除';
  return isProductVisible(row) ? '显示中' : '已隐藏';
}

function isProductVisible(row: ProductRecord) {
  return Number(row.status) === 1;
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

    const parentId = item.parent_id === undefined || item.parent_id === null || item.parent_id === '' ? '' : String(item.parent_id);
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
      const matched = [node.item.name, node.item.label].some((value) => String(value || '').toLowerCase().includes(keyword));
      if (matched) return { ...node, children: node.children };
      if (children.length) return { ...node, children };
      return null;
    })
    .filter((node): node is CategoryTreeNode => Boolean(node));
}

function flattenCategoryTreeRows(nodes: CategoryTreeNode[], level = 0): Array<{ node: ProductCategoryRecord; level: number; childCount: number }> {
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

  function visit(node: CategoryTreeNode, level: number) {
    const key = categoryIdKey(node.item);
    validKeys.add(key);
    if (level === 0 && node.children.length) defaultKeys.add(key);
    node.children.forEach((child) => visit(child, level + 1));
  }

  nodes.forEach((node) => visit(node, 0));

  const preserved = Array.from(categoryExpandedKeys.value).filter((key) => validKeys.has(key));
  categoryExpandedKeys.value = new Set(preserved.length ? preserved : Array.from(defaultKeys));
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

function syncDefaultCatalogCategory(nodes = categoryTree.value) {
  if (!nodes.length) {
    catalogFilters.product_group_key = '';
    return;
  }

  const selectedKey = String(catalogFilters.product_group_key || '');
  if (selectedKey) {
    const selectedNode = findCategoryTreeNode(nodes, selectedKey);
    if (selectedNode) {
      catalogFilters.product_group_key = categoryIdKey(firstDisplayCategoryNode(selectedNode));
      return;
    }
  }

  catalogFilters.product_group_key = categoryIdKey(firstDisplayCategoryNode(nodes[0]));
}

function isCategoryExpanded(row: ProductCategoryRecord) {
  return categoryExpandedKeys.value.has(categoryIdKey(row));
}

function isCategoryRowActive(row: ProductCategoryRecord) {
  const selectedKey = String(catalogFilters.product_group_key || '');
  if (!selectedKey) return false;
  // 仅高亮精确选中的行，父节点不再显示选中背景，避免父子背景连成一片
  return categoryIdKey(row) === selectedKey;
}

function isCategoryRowParentOfSelected(row: ProductCategoryRecord) {
  const selectedKey = String(catalogFilters.product_group_key || '');
  if (!selectedKey) return false;
  if (categoryIdKey(row) === selectedKey) return false;
  const node = findCategoryTreeNode(categoryTree.value, categoryIdKey(row));
  return Boolean(node?.children.length && categoryIdKey(firstDisplayCategoryNode(node)) === selectedKey);
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

function textValue(value: unknown) {
  return String(value ?? '').trim();
}

function productSpecDisplayName(row: ProductRecord) {
  return (
    [
      row.custom_display_name,
      row.product_spec_display,
      row.cpu_memory_display,
      row.product_display_name,
      row.display_name,
      row.combined_display_name,
      row.name,
    ]
      .map(textValue)
      .find(Boolean) || '-'
  );
}

function productSubtitle(row: ProductRecord) {
  const specName = productSpecDisplayName(row);
  return (
    [row.name, row.product_type_label, row.product_type, row.effective_product_group_full_name]
      .map(textValue)
      .find((item) => item && item !== specName) || '-'
  );
}

function normalizeCategoryScopeValue(value: unknown) {
  return value === undefined || value === null || value === '' ? '' : String(value);
}

function categoryScopeKey(row: ProductCategoryRecord) {
  return [
    normalizeCategoryScopeValue(row.product_type || catalogFilters.product_type),
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
  return [
    { content: '上移', value: 'up', disabled: !canMoveCategory(row, 'up') },
    { content: '下移', value: 'down', disabled: !canMoveCategory(row, 'down') },
    { content: '编辑', value: 'edit', divider: true },
    { content: '删除', value: 'delete', theme: 'error' },
  ];
}

function handleCategoryMenuClick(row: ProductCategoryRecord, option: DropdownOption) {
  const action = String(option.value || '');
  if (action === 'up' || action === 'down') {
    void handleMoveCategory(row, action);
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
  const selectedParent = categoryOptions.value.find((item) => String(item.id) === String(categoryForm.parent_id));
  if (selectedParent && String(selectedParent.product_type || '') !== String(value || '')) {
    categoryForm.parent_id = '';
  }
}

function resolveFirstProductGroupId(productType: string) {
  const selectedType = categoryProductTypeOptions.value.find((item) => String(item.value) === String(productType));
  return selectedType?.first_product_group_id || null;
}

function resolveSelectedParentCategory() {
  const parentId = String(categoryForm.parent_id || '');
  if (!parentId) return null;
  return categoryOptions.value.find((item) => String(item.id) === parentId) || null;
}

// --- Data loading ---
async function loadProductSummary() {
  summaryLoading.value = true;
  try {
    Object.assign(productSummary, await productApi.summary());
  } finally {
    summaryLoading.value = false;
  }
}

async function loadProductTypes() {
  typeLoading.value = true;
  try {
    const response = await productApi.types();
    productTypes.value = Array.isArray(response) ? response : response.list || [];
    if (!catalogFilters.product_type && productTypes.value[0]?.value) {
      catalogFilters.product_type = productTypes.value[0].value;
    }
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载一级分类失败'));
  } finally {
    typeLoading.value = false;
  }
}

async function refreshTypeCatalog() {
  await loadProductTypes();
  await Promise.all([loadCategories(), loadProducts()]);
}

function resetTypeForm() {
  editingTypeValue.value = '';
  typeForm.label = '';
  typeForm.icon = '';
}

function openTypeManagerDialog() {
  resetTypeForm();
  typeManagerDialogVisible.value = true;
}

function editType(type: ProductTypeRecord) {
  editingTypeValue.value = type.value;
  typeForm.label = type.label;
  typeForm.icon = type.icon || '';
}

async function submitType() {
  const label = typeForm.label.trim();
  if (!label) {
    MessagePlugin.warning('请输入一级分类名称');
    return;
  }

  typeSubmitting.value = true;
  try {
    if (editingTypeValue.value) {
      await productApi.updateType(editingTypeValue.value, { label, icon: typeForm.icon || '' });
      MessagePlugin.success('一级分类已更新');
    } else {
      await productApi.createType({ label, icon: typeForm.icon || '' });
      MessagePlugin.success('一级分类已创建');
    }
    resetTypeForm();
    await refreshTypeCatalog();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存一级分类失败'));
  } finally {
    typeSubmitting.value = false;
  }
}

async function toggleTypeHidden(type: ProductTypeRecord) {
  typeSubmitting.value = true;
  try {
    await productApi.updateType(type.value, {
      label: type.label,
      icon: type.icon || '',
      is_hidden: !type.is_hidden,
    });
    MessagePlugin.success(type.is_hidden ? '一级分类已显示' : '一级分类已隐藏');
    await refreshTypeCatalog();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新一级分类失败'));
  } finally {
    typeSubmitting.value = false;
  }
}

async function moveType(value: string, direction: -1 | 1) {
  const index = productTypes.value.findIndex((item) => item.value === value);
  const targetIndex = index + direction;
  if (index < 0 || targetIndex < 0 || targetIndex >= productTypes.value.length) return;
  const nextTypes = [...productTypes.value];
  const [current] = nextTypes.splice(index, 1);
  nextTypes.splice(targetIndex, 0, current);

  typeSubmitting.value = true;
  try {
    await productApi.reorderTypes({ values: nextTypes.map((item) => item.value) });
    productTypes.value = nextTypes;
    MessagePlugin.success('一级分类排序已更新');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新一级分类排序失败'));
    await loadProductTypes();
  } finally {
    typeSubmitting.value = false;
  }
}

function deleteType(type: ProductTypeRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除一级分类',
    body: `确认删除「${type.label || type.value}」吗？`,
    theme: 'warning',
    confirmBtn: '确认删除',
    cancelBtn: '取消',
    async onConfirm() {
      typeSubmitting.value = true;
      try {
        await productApi.deleteType(type.value);
        MessagePlugin.success('一级分类已删除');
        if (false && catalogFilters.lifecycle_status === 'all') {
          MessagePlugin.info('当前筛选为“全部商品”，已删除商品仍会显示在列表中');
        }
        dialog.hide();
        if (catalogFilters.product_type === type.value) {
          catalogFilters.product_type = '';
          catalogFilters.product_group_key = '';
        }
        resetTypeForm();
        await refreshTypeCatalog();
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除一级分类失败'));
      } finally {
        typeSubmitting.value = false;
      }
    },
  });
}

async function loadCategories() {
  categoryLoading.value = true;
  try {
    const response = await productApi.categories({ product_type: catalogFilters.product_type });
    categoryOptions.value = flattenCategories(response.tree || response.list || [], 0, null, catalogFilters.product_type);
    const tree = buildCategoryTree(categoryOptions.value);
    syncCategoryExpandedKeys(tree);
    syncDefaultCatalogCategory(tree);
  } catch (error) {
    categoryOptions.value = [];
    categoryExpandedKeys.value = new Set();
    MessagePlugin.error(errorMessage(error, '加载商品分类失败'));
  } finally {
    categoryLoading.value = false;
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
    await Promise.all([loadCategories(), loadProducts()]);
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
  productLoading.value = true;
  try {
    const selectedGroup = findProductGroupByKey(categoryOptions.value, catalogFilters.product_group_key);
    if (shouldAutoRevealDeletedProducts(selectedGroup)) {
      catalogFilters.lifecycle_status = 'all';
      MessagePlugin.info('当前分类仅包含已删除商品，已自动切换到“全部商品”');
    }
    const response = await productApi.list({
      keyword: catalogFilters.keyword,
      product_type: catalogFilters.product_type,
      status: catalogFilters.status,
      lifecycle_status: catalogFilters.lifecycle_status,
      ...productGroupPayload(selectedGroup),
      page: productPage.value,
      page_size: productPageSize.value,
    });
    products.value = Array.isArray(response.list) ? response.list : [];
    selectedProductKeys.value = [];
    productTotal.value = Number(response.total || 0);
    productPage.value = Number(response.page || productPage.value);
    productPageSize.value = Number(response.page_size || productPageSize.value);
  } catch (error) {
    products.value = [];
    selectedProductKeys.value = [];
    productTotal.value = 0;
    MessagePlugin.error(errorMessage(error, '加载商品列表失败'));
  } finally {
    productLoading.value = false;
  }
}

async function loadProviderTypes() {
  try {
    const response = await supplierApi.providerTypes();
    providerTypes.value = normalizeProviderTypeOptions(response);
  } catch {
    providerTypes.value = [];
  }
}

// --- Event handlers ---
function handleProductTypeChange(value: string) {
  catalogFilters.product_type = value;
  catalogFilters.product_group_key = '';
  categoryExpandedKeys.value = new Set();
  productPage.value = 1;
  void loadCategories().then(loadProducts);
}

function handleMobileProductTypeChange(value: string) {
  handleProductTypeChange(value);
}

function handleCategorySelect(row: ProductCategoryRecord) {
  const node = findCategoryTreeNode(categoryTree.value, categoryIdKey(row));
  const target = node ? firstDisplayCategoryNode(node) : row;
  if (node?.children.length) {
    toggleCategoryExpanded(row);
  }
  catalogFilters.product_group_key = categoryIdKey(target);
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

function resetCatalogFilters() {
  catalogFilters.keyword = '';
  catalogFilters.product_group_key = '';
  catalogFilters.status = '';
  catalogFilters.lifecycle_status = 'active';
  syncDefaultCatalogCategory();
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
  return current.mode === target.mode && current.value === target.value && Number(current.length) === Number(target.length);
}

function openProvisionHostnameDialog() {
  const targetRows = selectedProductRows();
  if (!targetRows.length) {
    MessagePlugin.warning('请先选择商品');
    return;
  }

  const currentRule = normalizeProvisionHostnameRule(targetRows[0]?.provision_hostname);
  provisionHostnameTargetKeys.value = targetRows.map((row) => row.id);
  provisionHostnameHasMixedRules.value = targetRows.some((row) => !isSameProvisionHostnameRule(row.provision_hostname, currentRule));
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
      responseRecord.target_effective_product_group_full_name
        || responseRecord.target_effective_product_group_name
        || resolveProductGroupLabel(batchCategoryForm.target_product_group_key),
    );

    if (updatedCount > 0) {
      MessagePlugin.success(`已将 ${updatedCount} 个商品移入「${targetLabel}」`);
    } else {
      MessagePlugin.success(`所选商品已在「${targetLabel}」下`);
    }

    batchCategoryDialogVisible.value = false;
    batchCategoryTargetKeys.value = [];
    await Promise.all([loadCategories(), loadProducts()]);
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
    await Promise.all([loadProductSummary(), loadCategories(), loadProducts()]);
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

// --- Product dialog ---
async function openProductDialog(row?: ProductRecord) {
  const [detail] = await Promise.all([row?.id ? loadProductDetail(row) : null, ensureProductSupplierOptions()]);
  const source = detail || row;
  editingProduct.value = source || null;
  activeProductDrawerSection.value = 'basic';
  Object.assign(productForm, {
    display_name: resolveProductFormDisplayName(source),
    custom_display_name: source?.custom_display_name || '',
    product_spec_display: source?.product_spec_display || source?.cpu_memory_display || '',
    selected_product_group_key: source ? productGroupOptionKey(source) : catalogFilters.product_group_key,
    monthly_price: productPricingValue(source, 'monthly', source?.monthly_price),
    quarterly_price: productPricingValue(source, 'quarterly'),
    semiannually_price: productPricingValue(source, 'semiannually'),
    annually_price: productPricingValue(source, 'annually'),
    auto_setup: Number(source?.auto_setup ?? 1),
    status: Number(source?.status ?? 1),
    supplier_id: source?.supplier_id || '',
    supplier_product_id: source?.supplier_product_id || '',
    config_options: normalizeConfigOptions(source?.config_options),
  });
  supplierProductOptions.value = [];
  if (productForm.supplier_id) {
    void loadProductSupplierProducts(productForm.supplier_id);
  }
  productDialogVisible.value = true;
}

function productPricingValue(source: ProductRecord | null | undefined, cycle: string, fallback?: unknown) {
  const pricing = toPlainRecord(source?.pricing);
  const value = pricing[cycle] ?? fallback;
  if (value === null || value === undefined || value === '') return 0;
  return Number(value) || 0;
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

function closeProductDrawer() {
  productDialogVisible.value = false;
}

function setProductDrawerSection(key: string) {
  activeProductDrawerSection.value = key;
}

async function loadProductDetail(row: ProductRecord) {
  try {
    return await productApi.detail(row.id);
  } catch {
    return row;
  }
}

function resolveProductFormDisplayName(source?: ProductRecord | null) {
  return String(
    source?.custom_display_name
      || source?.product_spec_display
      || source?.cpu_memory_display
      || source?.product_display_name
      || source?.display_name
      || source?.name
      || '',
  ).trim();
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
  productSubmitting.value = true;
  try {
    const payload = {
      display_name: productForm.display_name,
      name: productForm.display_name,
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
      product_type: catalogFilters.product_type,
      supplier_id: productForm.supplier_id || undefined,
      supplier_product_id: productForm.supplier_product_id || undefined,
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

// --- Config option helpers ---
function normalizeConfigOptions(value: unknown): ProductConfigOptionRecord[] {
  const items = Array.isArray(value) ? value : [];
  return items.map((itemValue, index) => {
    const item = toPlainRecord(itemValue);
    const field = String(item.field || item.spec_key || `option_${index + 1}`).trim();
    const name = String(item.name || item.option_name || field).trim();
    return {
      ...item,
      uid: String(item.uid || `${field}-${index}`),
      field,
      name,
      option_name: name,
      option_mode: String(item.option_mode || (item.option_type === 'quantity' ? 'range' : 'select')),
      parameter: String(item.parameter || ''),
      sub: Array.isArray(item.sub) ? item.sub as Array<Record<string, unknown>> : [],
      sub_items: Array.isArray(item.sub_items)
        ? item.sub_items as Array<Record<string, unknown>>
        : (Array.isArray(item.sub) ? item.sub as Array<Record<string, unknown>> : []),
      required: Boolean(item.required ?? true),
      hidden: Boolean(item.hidden ?? false),
      sort_order: Number(item.sort_order || index + 1),
    };
  });
}

function serializeConfigOptions(options: ProductConfigOptionRecord[]) {
  return options.map((item, index) => ({
    ...item,
    name: String(item.name || item.option_name || item.field || '').trim(),
    option_name: String(item.option_name || item.name || item.field || '').trim(),
    field: String(item.field || '').trim(),
    option_mode: String(item.option_mode || 'select'),
    parameter: String(item.parameter || '').trim(),
    required: Boolean(item.required),
    hidden: Boolean(item.hidden),
    sort_order: Number(item.sort_order || index + 1),
  }));
}

function resetConfigOptionForm() {
  Object.assign(configOptionForm, {
    name: '',
    field: '',
    option_mode: 'select',
    parameter: '',
    sub_items_text: '',
    description: '',
    suffix_text: '',
    advanced: true,
    required: true,
    hidden: false,
    sort_order: productForm.config_options.length + 1,
  });
  configOptionSubItemRows.value = [createConfigSubItemRow({}, 0)];
}

function formatConfigSubItems(items: Array<Record<string, unknown>> = []) {
  return items.map((item) => `${item.value || item.option_name_first || item.label || item.option_name || ''}|${item.label || item.option_name || item.value || ''}`).join(',');
}

function createConfigSubItemRow(item: Record<string, unknown> = {}, index = 0): ConfigOptionSubItemFormRow {
  const pricing = toPlainRecord(item.pricing);
  const monthlyPrice = item.monthly_price ?? item.monthly ?? item.month ?? pricing.monthly ?? pricing.month ?? '';
  return {
    uid: String(item.uid || `config-subitem-${Date.now()}-${index}-${Math.random().toString(36).slice(2)}`),
    name: String(item.label || item.option_name || item.name || '').trim(),
    value: String(item.value || item.option_name_first || '').trim(),
    monthly_price: monthlyPrice === '' || monthlyPrice === undefined || monthlyPrice === null ? '0.00' : String(monthlyPrice),
    sort_order: Number(item.sort_order || index + 1),
  };
}

function configSubItemRecordsFromParameter(rawValue: string): Array<Record<string, unknown>> {
  return parseConfigSubItems(rawValue);
}

function addConfigSubItemRow() {
  configOptionSubItemRows.value.push(createConfigSubItemRow({}, configOptionSubItemRows.value.length));
}

function removeConfigSubItemRow(index: number) {
  if (configOptionSubItemRows.value.length <= 1) return;
  configOptionSubItemRows.value.splice(index, 1);
}

function handleConfigOptionModeChange(value: string | number) {
  configOptionForm.option_mode = String(value || 'select');
  if (configOptionForm.option_mode === 'select' && configOptionSubItemRows.value.length === 0) {
    configOptionSubItemRows.value = [createConfigSubItemRow({}, 0)];
  }
}

function openConfigOptionDialog(row?: ProductConfigOptionRecord, index = -1) {
  configOptionEditingIndex.value = index;
  if (row) {
    Object.assign(configOptionForm, {
      name: row.name || row.option_name || '',
      field: row.field || '',
      option_mode: row.option_mode || 'select',
      parameter: row.parameter || '',
      sub_items_text: formatConfigSubItems(row.sub_items || row.sub || []),
      description: String(row.description || row.option_description || '').trim(),
      suffix_text: String(row.suffix_text || row.suffix || '').trim(),
      advanced: Boolean(row.advanced ?? true),
      required: Boolean(row.required ?? true),
      hidden: Boolean(row.hidden ?? false),
      sort_order: Number(row.sort_order || index + 1),
    });
    const sourceSubItems = Array.isArray(row.sub_items) && row.sub_items.length
      ? row.sub_items
      : (Array.isArray(row.sub) ? row.sub : []);
    const rowSubItems = sourceSubItems.length
      ? sourceSubItems.map((item) => toPlainRecord(item))
      : configSubItemRecordsFromParameter(String(row.parameter || ''));
    configOptionSubItemRows.value = rowSubItems.length
      ? rowSubItems.map((item, itemIndex) => createConfigSubItemRow(item, itemIndex))
      : [createConfigSubItemRow({}, 0)];
  } else {
    resetConfigOptionForm();
  }
  configOptionDialogVisible.value = true;
}

function splitConfigSubItemText(rawValue: string) {
  const tokens = rawValue
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
  const items: string[] = [];
  let buffer: string[] = [];

  tokens.forEach((token) => {
    buffer.push(token);
    if (token.includes('|')) {
      items.push(buffer.join(','));
      buffer = [];
    }
  });

  if (buffer.length) {
    if (items.length === 0) return tokens;
    items.push(buffer.join(','));
  }

  return items;
}

function parseConfigSubItems(rawValue: string) {
  return splitConfigSubItemText(rawValue)
    .map((item, index) => {
      const separatorIndex = item.indexOf('|');
      const rawValue = separatorIndex >= 0 ? item.slice(0, separatorIndex).trim() : item.trim();
      const rawLabel = separatorIndex >= 0 ? item.slice(separatorIndex + 1).trim() : '';
      const value = rawValue || rawLabel || '';
      const label = rawLabel || rawValue || '';
      return {
        id: value || index,
        value: value || label,
        label: label || value,
        option_name: label || value,
        option_name_first: value || label,
        pricing: {},
        sort_order: index + 1,
        hidden: 0,
      };
    });
}

function buildConfigSubItemsFromRows() {
  const rows = configOptionSubItemRows.value
    .map((row, index) => ({
      ...row,
      name: row.name.trim(),
      value: row.value.trim(),
      sort_order: Number(row.sort_order || index + 1),
      monthly_price: String(row.monthly_price ?? '').trim(),
    }))
    .filter((row) => row.name || row.value);

  const invalidRow = rows.find((row) => !row.name || !row.value);
  if (invalidRow) {
    throw new Error('请完整填写子项名称和传参值');
  }

  return rows.map((row, index) => {
    const monthlyPrice = Number(row.monthly_price || 0);
    const monthlyAmount = Number.isFinite(monthlyPrice) ? monthlyPrice.toFixed(2) : '0.00';
    return {
      id: row.value || index,
      value: row.value,
      label: row.name,
      option_name: row.name,
      option_name_first: row.value,
      monthly: monthlyAmount,
      monthly_price: monthlyAmount,
      pricing: {
        monthly: monthlyAmount,
      },
      sort_order: row.sort_order || index + 1,
      hidden: 0,
    };
  });
}

function submitConfigOption() {
  const name = configOptionForm.name.trim();
  const field = configOptionForm.field.trim();
  if (!name || !field) {
    MessagePlugin.warning('请填写配置名称和字段名');
    return;
  }
  let subItems: ReturnType<typeof parseConfigSubItems> = [];
  try {
    subItems = configOptionForm.option_mode === 'select' ? buildConfigSubItemsFromRows() : [];
  } catch (error) {
    MessagePlugin.warning(errorMessage(error, '请检查子项配置'));
    return;
  }
  const parameter = subItems.length
    ? subItems.map((item) => `${item.value}|${item.label}`).join(',')
    : configOptionForm.parameter.trim();
  if (configOptionForm.option_mode === 'select' && !parameter) {
    MessagePlugin.warning('请填写参数值或子项');
    return;
  }

  configOptionSubmitting.value = true;
  const payload: ProductConfigOptionRecord = {
    uid: `${field}-${Date.now()}`,
    source: 'manual',
    field,
    name,
    option_name: name,
    option_mode: configOptionForm.option_mode,
    option_type: configOptionForm.option_mode === 'range' ? 'quantity' : 'select',
    parameter,
    sub: subItems,
    sub_items: subItems,
    range_pricing: [],
    description: configOptionForm.description.trim(),
    suffix_text: configOptionForm.suffix_text.trim(),
    advanced: Boolean(configOptionForm.advanced),
    required: Boolean(configOptionForm.required),
    hidden: Boolean(configOptionForm.hidden),
    sort_order: Number(configOptionForm.sort_order || productForm.config_options.length + 1),
  };
  if (configOptionEditingIndex.value >= 0) {
    productForm.config_options.splice(configOptionEditingIndex.value, 1, payload);
  } else {
    productForm.config_options.push(payload);
  }
  configOptionDialogVisible.value = false;
  configOptionSubmitting.value = false;
}

function removeConfigOption(index: number) {
  productForm.config_options.splice(index, 1);
}

async function pullProductConfigTemplate() {
  const supplierId = productForm.supplier_id;
  const supplierProductId = productForm.supplier_product_id;
  if (!supplierId || !supplierProductId) {
    MessagePlugin.warning('请先绑定提供商和提供商商品 ID');
    return;
  }

  configTemplateLoading.value = true;
  try {
    const response = await supplierApi.productConfigTemplate(supplierId, supplierProductId);
    const options = Array.isArray(response.config_options) ? response.config_options : [];
    productForm.config_options = normalizeConfigOptions(options);
    MessagePlugin.success('配置项模板已拉取');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '拉取配置项模板失败'));
  } finally {
    configTemplateLoading.value = false;
  }
}

async function handleToggleProduct(row: ProductRecord) {
  productActionLoading.value = row.id;
  try {
    await productApi.toggleStatus(row.id);
    MessagePlugin.success('状态已更新');
    await loadProducts();
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '更新状态失败'));
  } finally {
    productActionLoading.value = null;
  }
}

function handleDeleteProduct(row: ProductRecord) {
  const dialog = DialogPlugin.confirm({
    header: '删除商品',
    body: `确认删除「${row.display_name || row.name || row.id}」吗？`,
    theme: 'warning',
    confirmBtn: '确认删除',
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = row.id;
      try {
        await productApi.delete(row.id);
        MessagePlugin.success('商品已删除');
        dialog.hide();
        if (catalogFilters.lifecycle_status === 'all') {
          MessagePlugin.info('当前筛选为“全部商品”，已删除商品仍会显示在列表中');
        }
        await Promise.all([loadProductSummary(), loadProducts()]);
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
    confirmBtn: '确认恢复',
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = `restore:${row.id}`;
      try {
        await productApi.restore(row.id);
        MessagePlugin.success('商品已恢复');
        dialog.hide();
        await Promise.all([loadProductSummary(), loadProducts()]);
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
    confirmBtn: '确认彻底删除',
    cancelBtn: '取消',
    async onConfirm() {
      productActionLoading.value = `force:${row.id}`;
      try {
        await productApi.forceDelete(row.id);
        MessagePlugin.success('商品已彻底删除');
        dialog.hide();
        await Promise.all([loadProductSummary(), loadProducts()]);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '彻底删除商品失败'));
      } finally {
        productActionLoading.value = null;
      }
    },
  });
}

function openCategoryDialog(row?: ProductCategoryRecord) {
  editingCategory.value = row || null;
  const level = row ? productGroupLevel(row) : 2;
  Object.assign(categoryForm, {
    name: row?.name || row?.label || '',
    product_type: String(row?.product_type || row?.first_product_group_code || catalogFilters.product_type || categoryProductTypeOptions.value[0]?.value || ''),
    parent_id: level === 3 ? row?.second_product_group_id || '' : '',
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
    const productType = categoryForm.product_type || catalogFilters.product_type || '';
    const firstProductGroupId = resolveFirstProductGroupId(productType);
    const selectedParent = resolveSelectedParentCategory();
    const targetLevel = editingCategory.value ? productGroupLevel(editingCategory.value) : (selectedParent ? 3 : 2);
    const payload: Record<string, unknown> = {
      name: categoryForm.name,
      service_type_code: productType || undefined,
      effective_product_group_level: targetLevel,
      first_product_group_id: targetLevel === 2 ? firstProductGroupId || undefined : undefined,
      first_product_group_code: targetLevel === 1 ? productType || undefined : undefined,
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
    await loadCategories();
    await loadProducts();
    await loadProductSummary();
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
    confirmBtn: '确认删除',
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
        await Promise.all([loadCategories(), loadProducts()]);
      } catch (error) {
        MessagePlugin.error(errorMessage(error, '删除分类失败'));
      } finally {
        categorySubmitting.value = false;
      }
    },
  });
}

// --- Supplier helpers (used within catalog for product editing) ---
async function ensureProductSupplierOptions() {
  if (productSupplierOptions.value.length) return;

  productSupplierLoading.value = true;
  try {
    const response = await supplierApi.list({ status: 1, page: 1, page_size: 100 });
    productSupplierOptions.value = Array.isArray(response.list) ? response.list : [];
  } catch (error) {
    productSupplierOptions.value = [];
    MessagePlugin.error(errorMessage(error, '加载提供商选项失败'));
  } finally {
    productSupplierLoading.value = false;
  }
}

function supplierOptionLabel(row: SupplierRecord) {
  const typeLabel = supplierInterfaceTypeLabel(row);
  return typeLabel ? `${row.name || row.id} / ${typeLabel}` : String(row.name || row.id || '-');
}

function supplierInterfaceTypeLabel(row: SupplierRecord) {
  const rawType = String(row.interface_type || '').trim();
  if (rawType && providerTypeFallbackLabels[rawType]) return providerTypeFallbackLabels[rawType];
  return row.interface_type_label || providerTypeLabel(rawType, providerTypeOptions.value);
}

function supplierProductOptionLabel(row: SupplierBatchProduct) {
  const typeLabel = row.type_label || row.remote_group_name || '';
  return typeLabel ? `${row.name || row.id} · ${typeLabel}` : String(row.name || row.id || '-');
}

function canSupplierBatchConnect(row: SupplierRecord) {
  return Boolean((row.has_api_url || row.api_url) && row.api_username && (row.has_api_key || row.api_key));
}

function handleProductSupplierChange(value: string | number) {
  productForm.supplier_id = value || '';
  productForm.supplier_product_id = '';
  supplierProductOptions.value = [];
  if (value) {
    void loadProductSupplierProducts(value, true);
  }
}

async function loadProductSupplierProducts(supplierId: string | number, notify = false) {
  if (!supplierId) return;

  const supplier = productSupplierOptions.value.find((item) => String(item.id) === String(supplierId));
  if (supplier && !canSupplierBatchConnect(supplier)) {
    supplierProductOptions.value = [];
    MessagePlugin.warning('接口配置不完整，暂时无法同步上游商品');
    return;
  }

  supplierProductLoading.value = true;
  try {
    const response = await supplierApi.products(supplierId, { silent: true });
    supplierProductOptions.value = buildSupplierBatchProducts(response);
    if (productForm.supplier_product_id) {
      const hasCurrent = supplierProductOptions.value.some((item) => String(item.id) === String(productForm.supplier_product_id));
      if (!hasCurrent) {
        supplierProductOptions.value.unshift({
          id: Number(productForm.supplier_product_id),
          name: `已绑定商品 #${productForm.supplier_product_id}`,
          type_label: '当前绑定',
          remote_group_name: '',
          is_connected: true,
          connected_display_name: '',
        });
      }
    }
    if (notify) MessagePlugin.success('上游商品已同步');
  } catch (error) {
    supplierProductOptions.value = [];
    MessagePlugin.error(errorMessage(error, '同步上游商品失败'));
  } finally {
    supplierProductLoading.value = false;
  }
}

function normalizeSupplierBatchProduct(itemValue: unknown): SupplierBatchProduct {
  const item = toPlainRecord(itemValue);
  return {
    ...item,
    id: Number(item.id || item.product_id || 0),
    name: String(item.name || item.product_name || '').trim(),
    type_label: String(item.type_label || item.type_name || item.type || item.billingcycle || '').trim(),
    remote_group_name: String(item.remote_group_name || item.group_name || item.second_group_name || item._group_label || '').trim(),
    is_connected: Boolean(item.is_connected),
    connected_display_name: String(item.connected_display_name || '').trim(),
  };
}

function buildSupplierBatchProducts(payloadValue: unknown) {
  const payload = toPlainRecord(payloadValue);
  const directProducts = Array.isArray(payload.products) ? payload.products : Array.isArray(payload) ? payload : [];
  if (directProducts.length) {
    return directProducts.map(normalizeSupplierBatchProduct).filter((item) => item.id > 0);
  }

  const groups = Array.isArray(payload.groups) ? payload.groups : [];
  return groups.flatMap((groupValue) => {
    const group = toPlainRecord(groupValue);
    const groupLabel = String(group.label || group.name || '').trim();
    const items = Array.isArray(group.items) ? group.items : [];
    return items.map((item) => normalizeSupplierBatchProduct({ ...toPlainRecord(item), _group_label: groupLabel }));
  }).filter((item) => item.id > 0);
}

function formatSplitAction(action: string) {
  return action === 'update' ? '更新' : '新建';
}

// --- Init ---
function loadCatalog() {
  void Promise.all([loadProductSummary(), loadProductTypes().then(loadCategories).then(loadProducts)]);
}

onMounted(() => {
  void loadProviderTypes();
  loadCatalog();
});
</script>

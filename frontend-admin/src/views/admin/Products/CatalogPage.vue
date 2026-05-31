<template>
  <div class="page-container products-page admin-page">
    <section class="catalog-kind-panel">
      <div class="catalog-kind-head">
        <div class="catalog-kind-meta">
          <strong>商品目录</strong>
          <p>一级菜单、分类、子分类与商品集中维护。</p>
        </div>
        <div class="catalog-kind-actions">
          <el-button text :loading="typeLoading" @click="refreshTypeCatalog">刷新种类</el-button>
          <el-button type="primary" @click="openTypeManagerDialog()">管理种类</el-button>
        </div>
      </div>

      <div class="catalog-kind-chips">
        <button
          v-for="item in productTypeOptions"
          :key="item.value"
          type="button"
          class="catalog-kind-chip"
          :class="typeChipClass(item)"
          @click="handleProductTypeChange(item.value)"
        >
          <span v-if="resolveTypeIconComponent(item.icon)" class="catalog-kind-chip__icon" aria-hidden="true">
            <el-icon><component :is="resolveTypeIconComponent(item.icon)" /></el-icon>
          </span>
          <span>{{ item.label }}</span>
          <em v-if="item.is_hidden" class="catalog-kind-chip-flag">隐藏</em>
          <small>{{ item.usage_count || 0 }}</small>
        </button>

      </div>
    </section>

    <section class="catalog-layout">
      <div v-if="mobileCategorySidebarVisible" class="mobile-sidebar-backdrop" @click="mobileCategorySidebarVisible = false"></div>
      <el-card shadow="never" class="group-panel" :class="{ 'is-mobile-open': mobileCategorySidebarVisible }">
        <template #header>
          <div class="group-panel-header">
            <div class="group-panel-header-title">
              <strong>商品分类</strong>
              <span class="group-panel-header-count" :title="categoryOverviewText">
                {{ currentTypeRootCategoryCount }} / {{ currentTypeChildCategoryCount }}
              </span>
            </div>
            <div class="group-panel-header-actions">
              <button
                type="button"
                class="group-panel-icon-button"
                :class="{ 'is-loading': categoryLoading }"
                :disabled="categoryLoading"
                title="刷新"
                @click="loadCategories"
              >
                <el-icon :size="14"><Refresh /></el-icon>
              </button>
              <el-button type="primary" size="small" :icon="Plus" @click="openCategoryDialog()">
                新增
              </el-button>
            </div>
          </div>
        </template>

        <div class="group-sidebar">
          <div class="group-sidebar-toolbar">
            <el-input
              v-model="categoryKeyword"
              class="group-sidebar-search"
              clearable
              placeholder="搜索分类或子菜单"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>

            <div v-if="categoryDragHint" class="drag-feedback-strip drag-feedback-strip--tree">
              {{ categoryDragHint }}
            </div>
          </div>

          <div class="group-tree-shell">
            <div class="group-tree-scroll" v-if="categoryTree.length">
            <el-tree
              ref="categoryTreeRef"
              class="group-tree"
              node-key="tree_key"
              highlight-current
              :indent="12"
              :data="categoryTreeNodes"
              :props="categoryTreeProps"
              :current-node-key="activeCategoryTreeKey"
              :expand-on-click-node="false"
              @node-click="handleCategoryTreeSelect"
              @node-expand="handleCategoryNodeExpand"
              @node-collapse="handleCategoryNodeCollapse"
            >
              <template #default="{ data }">
                <div
                  class="group-tree-node"
                  :class="{
                    'is-hidden': Number(data.is_visible) !== 1,
                  }"
                >
                  <button
                    type="button"
                    class="group-tree-node-drag-handle"
                    :disabled="!canDragCategoryTree"
                    draggable="true"
                    title="拖动排序"
                    @click.stop
                    @dragstart="handleCategoryTreeDragStart(data, $event)"
                    @dragend="handleCategoryTreeDragEnd"
                  >
                    <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                      <circle cx="3" cy="2.5" r="1.5"/>
                      <circle cx="7" cy="2.5" r="1.5"/>
                      <circle cx="3" cy="8" r="1.5"/>
                      <circle cx="7" cy="8" r="1.5"/>
                      <circle cx="3" cy="13.5" r="1.5"/>
                      <circle cx="7" cy="13.5" r="1.5"/>
                    </svg>
                  </button>
                  <div
                    class="group-tree-node-main"
                    :class="categoryTreeNodeMainClass(data)"
                    @dragover.prevent="handleCategoryTreeNodeDragOver(data, $event)"
                    @drop.prevent="handleCategoryTreeNodeDrop(data, $event)"
                  >
                    <span class="group-tree-node-label" :title="data.name">{{ data.name }}</span>
                    <span v-if="Number(data.is_visible) !== 1" class="group-tree-node-state">
                      隐藏
                    </span>
                    <span v-if="categoryTreeNodeNote(data)" class="group-tree-node-note">
                      {{ categoryTreeNodeNote(data) }}
                    </span>
                  </div>

                  <div class="group-tree-node-actions" @click.stop>
                    <el-dropdown trigger="click" @command="(cmd) => handleCategoryAction(cmd, data)">
                      <button type="button" class="group-tree-node-more">
                        <el-icon :size="16"><MoreFilled /></el-icon>
                      </button>
                      <template #dropdown>
                        <el-dropdown-menu>
                          <el-dropdown-item command="edit">编辑分类</el-dropdown-item>
                          <el-dropdown-item v-if="Number(data.level) === 1" command="add-child">
                            <el-icon :size="14"><Plus /></el-icon>新增子分类
                          </el-dropdown-item>
                          <el-dropdown-item command="toggle-visible">
                            {{ Number(data.is_visible) === 1 ? '隐藏分类' : '显示分类' }}
                          </el-dropdown-item>
                          <el-dropdown-item command="delete" divided style="color: var(--el-color-danger)">
                            删除分类
                          </el-dropdown-item>
                        </el-dropdown-menu>
                      </template>
                    </el-dropdown>
                  </div>
                </div>
              </template>
            </el-tree>
            </div>

            <el-empty
              v-else
              :description="filters.product_type ? '当前种类下还没有分类，可先新增分类。' : '请先选择或创建一级菜单种类。'"
            />

            <div v-if="categoryTree.length && !categoryTreeNodes.length" class="group-tree-empty">
              <strong>没有匹配的分类</strong>
              <p>试试更短的关键词，或者清除搜索后查看完整目录。</p>
              <el-button size="small" @click="categoryKeyword = ''">清除搜索</el-button>
            </div>
          </div>
        </div>
      </el-card>

      <el-card shadow="never" class="product-panel">
        <template #header>
          <div class="panel-header">
            <div class="panel-header-meta">
              <strong>配置列表</strong>
              <span>{{ productPanelSubtitle }}</span>
            </div>
            <div class="product-panel-actions">
              <el-button class="mobile-sidebar-toggle" type="primary" plain @click="mobileCategorySidebarVisible = true">
                <el-icon><Menu /></el-icon>
                <span v-if="!isMobile">菜单</span>
              </el-button>
              <el-button v-if="!isMobile" @click="openSplitProductDialog">拆分商品</el-button>
              <el-button :loading="summaryLoading || typeLoading || categoryLoading || productLoading" @click="loadData">
                <template v-if="isMobile"><el-icon><Refresh /></el-icon></template>
                <template v-else>刷新</template>
              </el-button>
              <el-button v-if="selectedProductRows.length > 1" @click="openBatchCategoryDialog()">
                批量改分类<span v-if="selectedProductRows.length">({{ selectedProductRows.length }})</span>
              </el-button>
              <el-button :disabled="!selectedProductRows.length" @click="openProvisionHostnameDialog()">
                <template v-if="isMobile">批量开通</template>
                <template v-else>批量开通主机名</template>
                <span v-if="selectedProductRows.length">({{ selectedProductRows.length }})</span>
              </el-button>
              <el-button type="primary" @click="openProductDialog()">
                <template v-if="isMobile">+ 新增</template>
                <template v-else>新增商品</template>
              </el-button>
            </div>
          </div>
        </template>

        <div class="product-filters">
          <div class="search-bar search-grid">
            <el-input
              v-model="filters.keyword"
              class="search-field search-field-keyword"
              placeholder="搜索配置名称 / 描述 / 开通模块"
              clearable
              @keyup.enter="handleSearch"
            >
              <template #prefix>
                <el-icon><Search /></el-icon>
              </template>
            </el-input>
            <el-select v-model="filters.status" class="search-field" clearable placeholder="商品状态">
              <el-option
                v-for="item in statusOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
              />
            </el-select>

          </div>

          <div v-if="selectedCategoryLabel || activeFilterTags.length" class="toolbar-foot">
            <div v-if="selectedCategoryLabel" class="selected-group">
              <span>当前分类：{{ selectedCategoryLabel }}</span>
              <button type="button" class="inline-clear-button" @click="clearCategoryFilter">清除</button>
            </div>

            <div v-if="activeFilterTags.length" class="active-filters">
              <span class="active-filters-label">筛选</span>
              <el-tag
                v-for="tag in activeFilterTags"
                :key="tag.key"
                closable
                size="small"
                @close="clearFilter(tag.key)"
              >
                {{ tag.label }}
              </el-tag>
            </div>
          </div>
        </div>

        <div class="product-content">
          <div v-if="isRootCategorySelected" class="group-selection-placeholder">
            <strong>{{ selectedCategoryLabel }}</strong>
            <p>一级分类仅用于承载二级分类，不直接展示商品。请从左侧选择具体二级分类后查看配置列表。</p>
            <div class="table-empty-actions">
              <el-button size="small" @click="clearCategoryFilter">查看全部</el-button>
              <el-button type="primary" size="small" @click="openChildCategoryDialog(selectedCategoryNode)">新增二级分类</el-button>
            </div>
          </div>

          <template v-else>
            <div class="product-table-shell">
              <el-table
                ref="productTableRef"
                :data="products"
                v-loading="productLoading"
                size="small"
                row-key="id"
                class="product-table"
                @selection-change="handleProductSelectionChange"
              >
                <template #empty>
                  <div class="table-empty">
                    <strong>{{ tableEmptyTitle }}</strong>
                    <p>{{ tableEmptyDescription }}</p>
                    <div class="table-empty-actions">
                      <el-button type="primary" size="small" @click="openProductDialog()">新增商品</el-button>
                      <el-button v-if="selectedCategoryLabel" size="small" @click="clearCategoryFilter">查看全部</el-button>
                    </div>
                  </div>
                </template>
                <el-table-column type="selection" width="44" align="center" class-name="col-selection" />
                <el-table-column label="" width="52" align="center" class-name="col-drag">
                  <template #default="{ row }">
                    <button
                      type="button"
                      class="drag-handle"
                      :class="{
                        'is-dragging': Number(draggingProductId) === Number(row.id),
                      }"
                      draggable="true"
                      @dragstart="handleProductDragStart(row, $event)"
                      @dragover.prevent="handleProductRowDragOver(row, $event)"
                      @drop.prevent="handleProductRowDrop(row, $event)"
                      @dragend="handleProductDragEnd"
                      title="拖动排序"
                    >
                      <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="3" cy="2.5" r="1.5"/>
                        <circle cx="7" cy="2.5" r="1.5"/>
                        <circle cx="3" cy="8" r="1.5"/>
                        <circle cx="7" cy="8" r="1.5"/>
                        <circle cx="3" cy="13.5" r="1.5"/>
                        <circle cx="7" cy="13.5" r="1.5"/>
                      </svg>
                    </button>
                  </template>
                </el-table-column>
                <el-table-column prop="id" label="ID" width="56" class-name="col-id" />
                <el-table-column label="配置信息" min-width="258">
                  <template #default="{ row }">
                    <div
                      class="product-name-cell"
                      :class="productDropZoneClass(row)"
                      @dragover.prevent="handleProductRowDragOver(row, $event)"
                      @drop.prevent="handleProductRowDrop(row, $event)"
                    >
                      <div class="product-name-head">
                        <strong>{{ resolveProductModelLabel(row) }}</strong>
                        <span v-if="row.remark && !isMobile" class="product-remark-inline" :title="row.remark">
                          {{ row.remark }}
                        </span>
                      </div>
                      <div v-if="!isMobile" class="product-meta-line">
                        <span class="product-group-pill">{{ row.category_full_name || row.group_full_name || '未分类' }}</span>
                        <span v-if="row.provision_module" class="product-module">{{ interfaceTypeLabel(row.provision_module) }}</span>
                        <span
                          v-if="row.provision_hostname_mode && row.provision_hostname_mode !== 'system'"
                          class="product-hostname-rule"
                        >
                          {{ row.provision_hostname_summary }}
                        </span>
                      </div>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="价格 / 资源" min-width="158" class-name="col-price">
                  <template #default="{ row }">
                    <div
                      class="overview-cell"
                      :class="productDropZoneClass(row)"
                      @dragover.prevent="handleProductRowDragOver(row, $event)"
                      @drop.prevent="handleProductRowDrop(row, $event)"
                    >
                      <div class="overview-price-line">
                        <strong>¥{{ row.primary_price?.amount || '0.00' }}</strong>
                        <small v-if="!isMobile">{{ billingCycleLabel(row.primary_cycle) ? '/' + billingCycleLabel(row.primary_cycle) : '' }}</small>
                      </div>
                      <div v-if="!isMobile" class="overview-meta-line">
                        <small>库存 {{ row.stock === -1 ? '不限' : row.stock }}</small>
                        <small>{{ row.services_count }} 服务</small>
                        <small>{{ row.orders_count }} 账单</small>
                      </div>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="交付 / 状态" min-width="146" class-name="col-status">
                  <template #default="{ row }">
                    <div
                      class="status-cell"
                      :class="productDropZoneClass(row)"
                      @dragover.prevent="handleProductRowDragOver(row, $event)"
                      @drop.prevent="handleProductRowDrop(row, $event)"
                    >
                      <div class="status-cell-tags">
                        <el-tag v-if="!isMobile" size="small" effect="plain" :type="typeTagType(row.type)">{{ row.type_label }}</el-tag>
                        <el-tag v-if="!isMobile" size="small" effect="plain" :type="row.auto_setup === 1 ? 'success' : 'info'">
                          {{ row.auto_setup === 1 ? '自动开通' : '手动开通' }}
                        </el-tag>
                        <el-tag size="small" effect="plain" :type="row.status === 1 ? 'success' : 'info'">
                          {{ row.status === 1 ? '上架中' : '已下架' }}
                        </el-tag>
                      </div>
                    </div>
                  </template>
                </el-table-column>
                <el-table-column label="操作" :width="isMobile ? 60 : 152" align="right">
                  <template #default="{ row }">
                    <div
                      v-if="!isMobile"
                      class="action-toolbar"
                      :class="productDropZoneClass(row)"
                      @dragover.prevent="handleProductRowDragOver(row, $event)"
                      @drop.prevent="handleProductRowDrop(row, $event)"
                    >
                      <el-button size="small" type="primary" plain @click="openProductDialog(row)">
                        编辑
                      </el-button>
                      <el-dropdown trigger="click" @command="(command) => handleProductAction(command, row)">
                        <el-button size="small">
                          更多
                          <el-icon><ArrowDown /></el-icon>
                        </el-button>
                        <template #dropdown>
                          <el-dropdown-menu>
                            <el-dropdown-item command="owners">查看拥有者</el-dropdown-item>
                            <el-dropdown-item command="provision-hostname">开通主机名</el-dropdown-item>
                            <el-dropdown-item :command="row.status === 1 ? 'disable' : 'enable'" divided>
                              {{ row.status === 1 ? '下架商品' : '上架商品' }}
                            </el-dropdown-item>
                            <el-dropdown-item command="delete" divided>删除商品</el-dropdown-item>
                          </el-dropdown-menu>
                        </template>
                      </el-dropdown>
                    </div>
                    <el-dropdown v-else trigger="click" @command="(cmd) => handleCatalogProductAction(cmd, row)">
                      <button type="button" class="mobile-action-btn" aria-label="操作">
                        <el-icon :size="16"><MoreFilled /></el-icon>
                      </button>
                      <template #dropdown>
                        <el-dropdown-menu>
                          <el-dropdown-item command="edit">编辑</el-dropdown-item>
                          <el-dropdown-item command="owners">查看拥有者</el-dropdown-item>
                          <el-dropdown-item command="provision-hostname">开通主机名</el-dropdown-item>
                          <el-dropdown-item :command="row.status === 1 ? 'disable' : 'enable'" divided>
                            {{ row.status === 1 ? '下架商品' : '上架商品' }}
                          </el-dropdown-item>
                          <el-dropdown-item command="delete" divided>删除商品</el-dropdown-item>
                        </el-dropdown-menu>
                      </template>
                    </el-dropdown>
                  </template>
                </el-table-column>
              </el-table>

              <div class="table-footer">
                <p class="footer-tip">
                  共 {{ total }} 个商品，前台商品页会同步读取这里的上架目录。
                </p>
                <el-pagination
                  v-model:current-page="page"
                  v-model:page-size="pageSize"
                  :total="total"
                  :page-sizes="[10, 20, 50, 100]"
                  layout="total, sizes, prev, pager, next"
                  @size-change="loadProducts"
                  @current-change="loadProducts"
                />
              </div>
            </div>
          </template>
        </div>
      </el-card>
    </section>

    <el-dialog
      v-model="typeManagerDialogVisible"
      title="管理种类"
      width="620px"
      class="catalog-dialog"
      destroy-on-close
    >
      <div class="dialog-intro">
        <strong>{{ editingTypeValue ? '编辑一级菜单种类' : '新增一级菜单种类' }}</strong>
        <p>种类会显示在商品目录顶部，用来承载分类和子分类，例如云服务器、NAT/云电脑。</p>
      </div>

      <div class="type-manager-form">
        <div class="type-manager-form__main">
          <el-input
            v-model="typeForm.label"
            maxlength="30"
            :placeholder="editingTypeValue ? '请输入新的种类名称' : '请输入种类名称，例如 NAT/云电脑'"
            @keyup.enter="handleSubmitType"
          />
        </div>
        <div class="type-manager-form__actions">
          <el-popover
            v-model:visible="typeIconPopoverVisible"
            placement="bottom-start"
            :width="380"
            trigger="click"
            popper-class="type-icon-popover"
          >
            <template #reference>
              <button type="button" class="type-icon-trigger">
                <el-icon><Grid /></el-icon>
              </button>
            </template>

            <div class="type-icon-popover__panel">
              <div class="type-icon-popover__head">
                <strong>选择商品种类图标</strong>
                <span>选择后会同步显示在顶部目录与前台入口</span>
              </div>
              <div class="type-icon-picker">
                <button
                  v-for="iconItem in productTypeIconOptions"
                  :key="iconItem.value"
                  type="button"
                  class="type-icon-picker__item"
                  :class="{ 'is-active': typeForm.icon === iconItem.value }"
                  :title="iconItem.label"
                  @click="selectTypeIcon(iconItem.value)"
                >
                  <el-icon><component :is="iconItem.component" /></el-icon>
                  <span>{{ iconItem.label }}</span>
                </button>
              </div>
              <div class="type-icon-popover__foot">
                <button
                  v-if="typeForm.icon"
                  type="button"
                  class="type-icon-clear"
                  @click="clearTypeIcon"
                >
                  清空图标
                </button>
              </div>
            </div>
          </el-popover>
          <el-button type="primary" :loading="typeSubmitting" @click="handleSubmitType">
            {{ editingTypeValue ? '保存修改' : '新增种类' }}
          </el-button>
          <el-button v-if="editingTypeValue" @click="resetTypeForm">取消编辑</el-button>
        </div>
      </div>

      <div class="type-manager-toolbar">
        <span class="type-manager-tip">拖动左侧手柄可调整一级菜单顺序，排序会立即生效并同步顶部目录。</span>
      </div>

      <div v-if="typeDragHint" class="drag-feedback-strip type-manager-drag-feedback">
        {{ typeDragHint }}
      </div>

      <div class="type-manager-list">
        <div
          v-for="item in productTypeOptions"
          :key="item.value"
          class="type-manager-item"
          :class="typeManagerItemClass(item)"
          @dragover.prevent="handleTypeManagerDragOver(item, $event)"
          @drop.prevent="handleTypeManagerDrop(item, $event)"
        >
          <button
            type="button"
            class="type-manager-drag-handle"
            :disabled="typeLoading || typeSubmitting"
            draggable="true"
            title="拖动排序"
            @dragstart="handleTypeDragStart(item, $event)"
            @dragend="handleTypeDragEnd"
          >
            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <circle cx="3" cy="2.5" r="1.5"/>
              <circle cx="7" cy="2.5" r="1.5"/>
              <circle cx="3" cy="8" r="1.5"/>
              <circle cx="7" cy="8" r="1.5"/>
              <circle cx="3" cy="13.5" r="1.5"/>
              <circle cx="7" cy="13.5" r="1.5"/>
            </svg>
          </button>
          <div class="type-manager-item-main">
            <div class="type-manager-item-head">
              <div class="type-manager-item-title">
                <span v-if="resolveTypeIconComponent(item.icon)" class="type-manager-item-title__icon" aria-hidden="true">
                  <el-icon><component :is="resolveTypeIconComponent(item.icon)" /></el-icon>
                </span>
                <strong>{{ item.label }}</strong>
              </div>
              <span class="type-manager-item-state" :class="{ 'is-hidden': item.is_hidden }">
                {{ item.is_hidden ? '前台已隐藏' : '前台显示中' }}
              </span>
            </div>
            <span>{{ item.usage_count || 0 }} 个商品 / {{ item.group_count || 0 }} 个分类</span>
            <small v-if="item.icon" class="type-manager-item-icon-name">图标：{{ item.icon }}</small>
          </div>
          <div class="type-manager-item-actions">
            <el-button link type="primary" @click="editType(item)">编辑</el-button>
            <el-button
              link
              :type="item.is_hidden ? 'success' : 'warning'"
              :disabled="typeSubmitting"
              @click="handleToggleTypeHidden(item)"
            >
              {{ item.is_hidden ? '显示' : '隐藏' }}
            </el-button>
            <el-popconfirm
              title="确认删除该种类？"
              @confirm="handleDeleteType(item)"
            >
              <template #reference>
                <el-button link type="danger">删除</el-button>
              </template>
            </el-popconfirm>
          </div>
        </div>
      </div>
    </el-dialog>

    <el-dialog
      v-model="categoryDialogVisible"
      :title="editingCategory ? '编辑商品分类' : '新增商品分类'"
      width="720px"
      class="catalog-dialog"
      destroy-on-close
    >
      <div class="dialog-intro">
        <strong>{{ editingCategory ? '更新分类资料' : '创建新的商品分类' }}</strong>
        <p>先选所属一级菜单，再维护分类和子菜单。子菜单自动继承所属一级菜单。</p>
      </div>

      <el-form ref="categoryFormRef" :model="categoryForm" :rules="categoryRules" label-position="top">
        <section class="dialog-section">
          <div class="dialog-section-header">
            <strong>分类结构</strong>
            <span>一级菜单用于区分业务大类，分类下可继续维护子分类。</span>
          </div>
          <div class="dialog-grid">
            <el-form-item label="所属一级菜单" prop="product_type">
              <el-select
                v-model="categoryForm.product_type"
                placeholder="请选择一级菜单"
                :disabled="Boolean(categoryForm.parent_id)"
              >
                <el-option
                  v-for="item in productTypeOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="上级分类" prop="parent_id">
              <el-select v-model="categoryForm.parent_id" clearable placeholder="不选择则为当前一级菜单下的分类">
                <el-option
                  v-for="item in availableParentCategories"
                  :key="item.id"
                  :label="item.label"
                  :value="item.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="分类名称" prop="name">
              <el-input v-model="categoryForm.name" maxlength="100" placeholder="例如：香港 / 美国 / 宁波高宽" />
            </el-form-item>
            <el-form-item label="排序值" prop="sort_order">
              <el-input-number v-model="categoryForm.sort_order" :min="0" :max="999999" style="width: 100%;" />
            </el-form-item>
            <el-form-item label="展示状态" prop="is_visible">
              <el-switch
                v-model="categoryForm.is_visible"
                :active-value="1"
                :inactive-value="0"
                active-text="前台展示"
                inactive-text="前台隐藏"
              />
            </el-form-item>
          </div>
        </section>

        <section class="dialog-section">
          <div class="dialog-section-header">
            <strong>前台展示</strong>
            <span>这些字段决定前台产品页的产品介绍，唯一标识会按分类名称自动生成。</span>
          </div>
          <div class="dialog-grid">
            <el-form-item label="产品介绍" prop="slogan" class="dialog-span-2">
              <el-input v-model="categoryForm.slogan" maxlength="255" placeholder="用于前台产品页的介绍文案" />
            </el-form-item>
          </div>
        </section>
      </el-form>

      <template #footer>
        <el-button @click="categoryDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="categorySubmitting" @click="handleSubmitCategory">保存分类</el-button>
      </template>
    </el-dialog>

    <el-drawer
      v-model="productDialogVisible"
      :title="editingProduct ? '编辑商品' : '新增商品'"
      direction="rtl"
      size="920px"
      class="product-drawer"
      destroy-on-close
    >
      <div class="product-drawer-shell">
        <div class="product-drawer-tabs" role="tablist" aria-label="商品录入分区">
          <button
            v-for="tab in productDrawerTabs"
            :key="tab.key"
            type="button"
            class="product-drawer-tab"
            :class="{ active: productDrawerTab === tab.key }"
            @click="activateProductDrawerTab(tab.key)"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="product-dialog-layout">
          <div class="product-dialog-main" v-loading="productDialogLoading">
            <el-form
              ref="productFormRef"
              :model="productForm"
              :rules="productRules"
              label-position="top"
              size="small"
              class="product-form"
            >
              <section v-show="productDrawerTab === 'details'" class="dialog-section">
                <div class="dialog-section-header">
                  <strong>详情</strong>
                  <span>填写分类、名称和类型。</span>
                </div>
                <div class="dialog-grid">
                  <el-form-item label="商品分类" prop="category_id">
                    <el-select
                      v-model="productForm.category_id"
                      placeholder="请选择二级商品分类"
                      no-data-text="请先创建二级分类"
                    >
                      <el-option
                        v-for="category in assignableCategoryOptions"
                        :key="category.id"
                        :label="category.label"
                        :value="category.id"
                    />
                  </el-select>
                </el-form-item>
                  <el-form-item label="商品类型" prop="product_type">
                    <el-select v-model="productForm.product_type" placeholder="请选择商品类型">
                      <el-option
                        v-for="item in productTypeOptions"
                        :key="item.value"
                        :label="item.label"
                      :value="item.value"
                    />
                  </el-select>
                </el-form-item>
                  <el-form-item label="备注" class="dialog-span-2">
                    <el-input
                      v-model="productForm.remark"
                      type="textarea"
                      :rows="3"
                      maxlength="255"
                      show-word-limit
                      placeholder="请输入商品备注，方便后台管理识别"
                    />
                  </el-form-item>
                </div>
              </section>

              <section v-show="productDrawerTab === 'pricing'" class="dialog-section">
                <div class="dialog-section-header">
                  <strong>定价</strong>
                  <span>支持分别填写月付、季付、半年付和年付，也支持一键补齐。</span>
                </div>
                <div class="pricing-stack">
                  <div
                    v-for="cycle in visibleBillingCycleOptions"
                    :key="cycle.value"
                    class="pricing-field"
                  >
                    <div class="pricing-field-head">
                      <div class="pricing-field-label">
                        <strong>{{ cycle.label }}</strong>
                        <button
                          v-if="cycle.value === 'monthly'"
                          type="button"
                          class="pricing-field-trigger"
                          title="按月付补齐其他周期"
                          @click="fillPricingFromMonthly"
                        >
                          补齐
                        </button>
                      </div>
                      <span class="pricing-field-status">
                        {{ productForm.pricing[cycle.value] > 0 ? '已设置' : '未启用' }}
                      </span>
                    </div>
                    <el-input-number
                      v-model="productForm.pricing[cycle.value]"
                      :min="0"
                      :precision="2"
                      :step="10"
                      controls-position="right"
                      class="pricing-field-input"
                      style="width: 100%;"
                    />
                  </div>
                </div>
                <div class="field-help">可点击月付右侧“补齐”，按倍数生成季付、半年付和年付；也可以分别手动自定义。</div>

                <div class="pricing-setup-field">
                  <div class="pricing-field-head">
                    <div class="pricing-field-label">
                      <strong>开通费</strong>
                    </div>
                  </div>
                  <el-input-number v-model="productForm.setup_fee" :min="0" :precision="2" :step="10" controls-position="right" style="width: 100%;" />
                  <div class="field-help">按需填写一次性开通费用，留空或 `0` 表示不收取。</div>
                </div>
              </section>

              <section v-show="productDrawerTab === 'automation'" class="dialog-section">
                <div class="dialog-section-header">
                  <strong>自动开通</strong>
                  <span>配置开通模块、交付方式和上架状态。</span>
                </div>
                <div class="dialog-grid">
                  <el-form-item label="供应商" class="dialog-span-2">
                    <el-select
                      v-model="productForm.supplier_id"
                      class="supplier-field"
                      filterable
                      clearable
                      :loading="supplierLoading"
                      placeholder="请选择供应商"
                      @change="handleSupplierChange"
                    >
                      <el-option
                        v-for="supplier in suppliers"
                        :key="supplier.id"
                        :label="formatSupplierOptionLabel(supplier)"
                        :value="supplier.id"
                      >
                        <div class="supplier-option">
                          <span class="supplier-option-name">{{ supplier.name }}</span>
                          <small class="supplier-option-type">{{ interfaceTypeLabel(supplier.interface_type) }}</small>
                        </div>
                      </el-option>
                    </el-select>
                  </el-form-item>

                  <el-form-item label="供应商商品" class="dialog-span-2">
                    <div class="supplier-product-row">
                      <el-cascader
                        v-model="productForm.supplier_product_id"
                        class="supplier-product-cascader"
                        :options="supplierProductCascaderOptions"
                        :props="supplierProductCascaderProps"
                        filterable
                        clearable
                        :show-all-levels="false"
                        :teleported="false"
                        :loading="supplierProductsLoading"
                        :disabled="!productForm.supplier_id || !selectedSupplierCanSync"
                        :placeholder="selectedSupplierCanSync ? '先选分类，再选商品' : '供应商接口未配置完整，无法同步商品'"
                        @change="handleSupplierProductChange"
                      >
                        <template #default="{ node, data }">
                          <div class="supplier-product-node">
                            <span class="supplier-product-name">{{ data.label }}</span>
                            <small v-if="node.isLeaf && data.type_label" class="supplier-product-type">
                              {{ data.type_label }}
                            </small>
                          </div>
                        </template>
                      </el-cascader>

                      <el-button
                        type="primary"
                        :loading="supplierProductsSyncing"
                        :disabled="!productForm.supplier_id || !selectedSupplierCanSync"
                        @click="syncSupplierProducts"
                      >
                        同步数据
                      </el-button>
                    </div>
                  </el-form-item>

                  <el-form-item label="开通模块" prop="provision_module">
                    <el-input
                      v-model="productForm.provision_module"
                      maxlength="50"
                      disabled
                      :placeholder="selectedSupplier ? '已根据供应商自动带出' : '选择供应商后自动带出模块'"
                    />
                    <div class="field-help">
                      {{ selectedSupplier ? '当前模块已按供应商接口类型自动匹配。' : '选择供应商后会自动带出对应模块，未选择时可留空。' }}
                    </div>
                  </el-form-item>
                  <div
                    v-if="selectedSupplier && !selectedSupplierCanSync"
                    class="field-help dialog-span-2 supplier-sync-warning"
                  >
                    当前供应商接口配置不完整，暂时无法拉取上游商品列表和配置模板。
                  </div>
                  <el-form-item label="自动开通" prop="auto_setup">
                    <el-switch
                      v-model="productForm.auto_setup"
                      :active-value="1"
                      :inactive-value="0"
                      active-text="自动"
                      inactive-text="手动"
                    />
                  </el-form-item>
                  <el-form-item label="上架状态" prop="status">
                    <el-switch
                      v-model="productForm.status"
                      :active-value="1"
                      :inactive-value="0"
                      active-text="上架"
                      inactive-text="下架"
                    />
                  </el-form-item>
                </div>
              </section>

              <section v-show="productDrawerTab === 'config'" class="dialog-section">
                <div class="dialog-section-header">
                  <strong>商品配置</strong>
                  <span>维护库存、排序和扩展配置项。</span>
                </div>
                <div class="dialog-grid">
                  <el-form-item label="库存" prop="stock">
                    <el-input-number v-model="productForm.stock" :min="-1" :max="999999" controls-position="right" style="width: 100%;" />
                    <div class="field-help">`-1` 表示不限库存，其它值按剩余库存显示。</div>
                  </el-form-item>
                  <el-form-item label="排序值" prop="sort_order">
                    <el-input-number v-model="productForm.sort_order" :min="0" :max="999999" controls-position="right" style="width: 100%;" />
                  </el-form-item>
                  <div class="dialog-span-2 config-editor">
                    <div class="config-editor-toolbar">
                      <div class="config-editor-meta">
                        <strong>默认配置项</strong>
                        <span>{{ productForm.config_options.length }} 项配置，拉取后保存商品即可入库。</span>
                      </div>
                      <div class="config-editor-actions">
                        <el-button plain :disabled="!canPullConfigOptions" @click="pullConfigOptionsFromSupplierProduct()">
                          从接口拉取
                        </el-button>
                        <el-button type="primary" plain @click="openConfigOptionDialog()">新增配置项</el-button>
                      </div>
                    </div>

                    <div class="config-table-shell">
                      <el-table
                        :data="productForm.config_options"
                        :row-key="(row) => row.uid"
                        size="small"
                        class="config-table"
                        empty-text="暂无配置项"
                      >
                        <template #empty>
                          <div class="config-table-empty">
                            <strong>还没有默认配置项</strong>
                            <p>选择供应商商品后可以直接从上游接口拉取，也可以按上游参数表手动新增。</p>
                            <div class="config-table-empty-actions">
                              <el-button plain size="small" :disabled="!canPullConfigOptions" @click="pullConfigOptionsFromSupplierProduct()">
                                从接口拉取
                              </el-button>
                              <el-button type="primary" plain size="small" @click="openConfigOptionDialog()">新增配置项</el-button>
                            </div>
                          </div>
                        </template>

                        <el-table-column label="配置项名称" min-width="180">
                          <template #default="{ row }">
                            <div class="config-name-cell">
                              <strong>{{ row.field }}|{{ row.name }}</strong>
                              <span v-if="row.parameter" class="config-name-note">参数已填写</span>
                            </div>
                          </template>
                        </el-table-column>

                        <el-table-column label="配置项类型" width="130">
                          <template #default="{ row }">
                            <el-tag size="small" :type="row.option_mode === 'range' ? 'warning' : 'primary'">
                              {{ row.option_mode === 'range' ? '范围型' : '单选型' }}
                            </el-tag>
                          </template>
                        </el-table-column>

                        <el-table-column label="配置项参数" min-width="220">
                          <template #default="{ row }">
                            <div class="config-parameter-cell">
                              <template v-if="row.option_mode === 'range'">
                                <span class="config-range-info">
                                  {{ row.qty_minimum }}~{{ row.qty_maximum }} / 步进{{ row.qty_step ?? 1 }}
                                  <small v-if="row.sub?.length">· {{ row.sub.length }} 个区间</small>
                                </span>
                              </template>
                              <template v-else>
                                <span v-if="row.sub?.length" class="config-range-info">
                                  {{ row.sub.length }} 个子项
                                </span>
                                <code v-else-if="row.parameter" class="config-parameter-text">{{ row.parameter }}</code>
                                <span v-else class="config-parameter-empty">未填写</span>
                              </template>
                            </div>
                          </template>
                        </el-table-column>

                        <el-table-column label="参数说明" min-width="240">
                          <template #default="{ row }">
                            <p class="config-description-text">{{ row.description || '-' }}</p>
                          </template>
                        </el-table-column>

                        <el-table-column label="必选" width="84" align="center">
                          <template #default="{ row }">
                            <el-tag :type="row.required ? 'danger' : 'info'" size="small">
                              {{ row.required ? '必选' : '可选' }}
                            </el-tag>
                          </template>
                        </el-table-column>

                        <el-table-column label="默认值" min-width="160">
                          <template #default="{ row }">
                            <span class="config-default-text">{{ row.default_value || '-' }}</span>
                          </template>
                        </el-table-column>

                        <el-table-column label="排序" width="104" align="center">
                          <template #default="{ row }">
                            <el-input-number
                              v-model="row.sort_order"
                              :min="0"
                              :max="999999"
                              controls-position="right"
                              class="config-order-input"
                            />
                          </template>
                        </el-table-column>

                        <el-table-column label="隐藏" width="96" align="center">
                          <template #default="{ row }">
                            <el-checkbox v-model="row.hidden" />
                          </template>
                        </el-table-column>

                        <el-table-column label="允许升降级" width="118" align="center">
                          <template #default="{ row }">
                            <el-checkbox v-model="row.allow_upgrade" />
                          </template>
                        </el-table-column>

                        <el-table-column label="应用优惠码" width="118" align="center">
                          <template #default="{ row }">
                            <el-checkbox v-model="row.allow_promo_code" />
                          </template>
                        </el-table-column>

                        <el-table-column label="操作" :width="isMobile ? 60 : 132" align="right" fixed="right">
                          <template #default="{ row, $index }">
                            <div v-if="!isMobile" class="config-table-actions">
                              <el-button link type="primary" @click="openConfigOptionDialog(row, $index)">编辑</el-button>
                              <el-popconfirm title="确认删除该配置项？" @confirm="removeConfigOption($index)">
                                <template #reference>
                                  <el-button link type="danger">删除</el-button>
                                </template>
                              </el-popconfirm>
                            </div>
                            <el-dropdown v-else trigger="click" @command="(cmd) => handleConfigOptionAction(cmd, row, $index)">
                              <button type="button" class="mobile-action-btn" aria-label="操作">
                                <el-icon :size="16"><MoreFilled /></el-icon>
                              </button>
                              <template #dropdown>
                                <el-dropdown-menu>
                                  <el-dropdown-item command="edit">编辑</el-dropdown-item>
                                  <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                                </el-dropdown-menu>
                              </template>
                            </el-dropdown>
                          </template>
                        </el-table-column>
                      </el-table>
                    </div>

                    <div class="field-help">选中供应商商品后，可从上游接口拉取默认配置项；当前接口未返回的参数仍可按上游参数格式手动补充。</div>
                  </div>
                </div>
              </section>
            </el-form>
          </div>
        </div>
      </div>

      <template #footer>
        <el-button @click="productDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="productSubmitting" @click="handleSubmitProduct">保存商品</el-button>
      </template>
    </el-drawer>

    <el-dialog
      v-model="splitProductDialogVisible"
      title="拆分商品"
      width="620px"
      class="catalog-dialog"
      destroy-on-close
    >
      <div class="dialog-intro">
        <strong>按 CPU 与内存子选项生成独立商品</strong>
        <p>系统会读取已选商品的 CPU、内存配置子项，基础规格更新原商品，其余规格生成独立商品，并把对应配置固化为上游开通默认值。</p>
      </div>

      <div class="hostname-batch-meta">
        <span>已选商品 {{ splitProductTargetRows.length }} 个</span>
        <span v-if="splitProductPreviewCount">预计处理 {{ splitProductPreviewCount }} 个规格</span>
        <span v-if="splitProductSkippedCount">跳过 {{ splitProductSkippedCount }} 个</span>
        <span>原基础规格不会重复创建，同名拆分商品会更新。</span>
      </div>

      <div v-loading="splitProductPreviewLoading" class="split-product-preview">
        <template v-if="splitProductPreviewRows.length">
          <div
            v-for="row in splitProductPreviewRows"
            :key="row.key"
            class="split-product-item"
          >
            <span>{{ row.display_name || (row.product_id ? `未配置规格 #${row.product_id}` : '未配置规格') }}</span>
            <small>{{ row.source_display_name || (row.source_product_id ? `未配置规格 #${row.source_product_id}` : '未配置规格') }}</small>
          </div>
        </template>
        <el-empty
          v-else
          :image-size="72"
          description="未找到可拆分的 CPU 或内存子选项"
        />
      </div>

      <el-alert
        type="warning"
        :closable="false"
        show-icon
      >
        <template #title>基础规格沿用原商品；处理后的商品会保留对应 CPU、内存子项，并固化为默认开通配置，不会再出现其他 CPU 或内存规格。</template>
      </el-alert>

      <template #footer>
        <el-button @click="splitProductDialogVisible = false">取消</el-button>
        <el-button
          type="primary"
          :loading="splitProductSubmitting"
          :disabled="splitProductPreviewLoading || !splitProductPreviewRows.length"
          @click="handleSubmitSplitProducts"
        >
          确认拆分
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="batchCategoryDialogVisible"
      :title="batchCategoryDialogTitle"
      width="620px"
      class="catalog-dialog"
      destroy-on-close
    >
      <div class="dialog-intro">
        <strong>批量更改商品分类</strong>
        <p>会把已选商品统一移动到新的最终分类下，并自动追加到目标分类末尾。</p>
      </div>

      <div class="hostname-batch-meta">
        <span>已选商品 {{ batchCategoryTargetRows.length }} 个</span>
        <span v-if="batchCategorySourceSummary">{{ batchCategorySourceSummary }}</span>
      </div>

      <el-form label-position="top" size="small" class="hostname-rule-form">
        <el-form-item label="目标分类">
          <el-select
            v-model="batchCategoryForm.category_id"
            filterable
            placeholder="请选择要移动到的分类"
            no-data-text="请先创建二级分类"
          >
            <el-option
              v-for="category in assignableCategoryOptions"
              :key="category.id"
              :label="category.label"
              :value="category.id"
            />
          </el-select>
          <div class="field-help">只能移动到最终可售分类，一级分类不会出现在这里。</div>
        </el-form-item>

        <el-alert
          type="info"
          :closable="false"
          show-icon
        >
          <template #title>{{ batchCategoryPreviewText }}</template>
        </el-alert>
      </el-form>

      <template #footer>
        <el-button @click="batchCategoryDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="batchCategorySubmitting" @click="handleSubmitBatchCategory">
          保存分类
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="provisionHostnameDialogVisible"
      :title="provisionHostnameDialogTitle"
      width="620px"
      class="catalog-dialog"
      destroy-on-close
    >
      <div class="dialog-intro">
        <strong>设置商品开通主机名</strong>
        <p>该配置会在自动开通时优先用于上游 `host` 参数。默认跟随上游商品自身规则，只有手动设置后才覆盖。</p>
      </div>

      <div class="hostname-batch-meta">
        <span>已选商品 {{ provisionHostnameTargetRows.length }} 个</span>
        <span v-if="provisionHostnameTargetRows.length === 1">
          {{ provisionHostnameTargetRows[0]?.name || '-' }}
        </span>
        <span v-else-if="provisionHostnameHasMixedRules">当前包含多种主机名规则</span>
      </div>

      <el-alert
        v-if="provisionHostnameHasMixedRules"
        type="warning"
        :closable="false"
        show-icon
        class="hostname-batch-warning"
      >
        <template #title>当前选中的商品存在不同的开通主机名规则，保存后会统一覆盖为当前设置。</template>
      </el-alert>

      <el-form label-position="top" size="small" class="hostname-rule-form">
        <el-form-item label="开通主机名策略">
          <el-radio-group v-model="provisionHostnameForm.mode">
            <el-radio-button label="system">跟随上游</el-radio-button>
            <el-radio-button label="fixed">固定主机名</el-radio-button>
            <el-radio-button label="prefix">指定前缀</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item
          v-if="provisionHostnameForm.mode === 'fixed'"
          label="固定主机名"
        >
          <el-input
            v-model="provisionHostnameForm.value"
            maxlength="200"
            placeholder="例如 hk-vps-core"
          />
          <div class="field-help">每次开通都向上游提交同一个主机名，适合上游允许固定值且不校验重复的场景。</div>
        </el-form-item>

        <template v-if="provisionHostnameForm.mode === 'prefix'">
          <el-form-item label="主机名前缀">
            <el-input
              v-model="provisionHostnameForm.value"
              maxlength="20"
              placeholder="例如 hk / sg / us"
            />
            <div class="field-help">只能输入字母，系统会在前缀后自动补随机后缀；若上游限制了前缀或长度，最终会按上游约束修正。</div>
          </el-form-item>
          <el-form-item label="主机名总长度">
            <el-input-number
              v-model="provisionHostnameForm.length"
              :min="4"
              :max="63"
              controls-position="right"
              style="width: 100%;"
            />
          </el-form-item>
        </template>

        <el-alert
          type="info"
          :closable="false"
          show-icon
        >
          <template #title>{{ provisionHostnamePreviewText }}</template>
        </el-alert>
      </el-form>

      <template #footer>
        <el-button @click="provisionHostnameDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="provisionHostnameSubmitting" @click="handleSubmitProvisionHostname">
          保存规则
        </el-button>
      </template>
    </el-dialog>

    <el-dialog
      v-model="configOptionDialogVisible"
      :title="editingConfigOptionIndex >= 0 ? '编辑配置项' : '新增配置项'"
      width="680px"
      append-to-body
      class="config-option-dialog"
      @closed="handleConfigOptionDialogClosed"
    >
      <el-form
        ref="configOptionFormRef"
        :model="configOptionForm"
        :rules="configOptionRules"
        label-position="top"
        status-icon
      >
      <div class="config-dialog-grid">
        <!-- 行1：名称 + 类型 + 高级设置 -->
        <el-form-item label="* 配置项名称" prop="name">
          <el-input v-model="configOptionForm.name" maxlength="60" placeholder="例如：CPU、内存、带宽" />
        </el-form-item>
        <el-form-item label="* 配置项类型" prop="option_mode" class="config-type-cell">
          <el-select v-model="configOptionForm.option_mode" style="width: 100%;" @change="onOptionModeChange">
            <el-option label="单选（固定选项）" value="select" />
            <el-option label="内存单选" value="select" v-if="false" />
            <el-option label="范围（数值滑动）" value="range" />
          </el-select>
        </el-form-item>
        <el-form-item label="高级设置" class="config-advanced-cell">
          <el-checkbox v-model="configOptionForm.show_advanced" />
        </el-form-item>

        <!-- 行2：配置标识 + 选说明 + 尾部文字（高级） -->
        <el-form-item label="配置标识" prop="field">
          <el-input v-model="configOptionForm.field" maxlength="60" placeholder="例如：cpu、memory、bw" />
        </el-form-item>
        <el-form-item label="选项说明" v-if="configOptionForm.show_advanced">
          <el-input v-model="configOptionForm.description" maxlength="200" placeholder="请输入备注" />
        </el-form-item>
        <el-form-item label="选项尾部文字" v-if="configOptionForm.show_advanced">
          <el-input v-model="configOptionForm.suffix_text" maxlength="20" placeholder="请输入选项尾部文字" />
        </el-form-item>

        <!-- 范围型：最小值/最大值/步进值 -->
        <template v-if="configOptionForm.option_mode === 'range'">
          <el-form-item label="最小值" prop="qty_minimum">
            <el-input-number v-model="configOptionForm.qty_minimum" :min="0" controls-position="right" style="width:100%;" />
          </el-form-item>
          <el-form-item label="最大值" prop="qty_maximum">
            <el-input-number v-model="configOptionForm.qty_maximum" :min="0" controls-position="right" style="width:100%;" />
          </el-form-item>
          <el-form-item label="步进值" prop="qty_step">
            <el-input-number v-model="configOptionForm.qty_step" :min="1" controls-position="right" style="width:100%;" />
          </el-form-item>
        </template>

        <!-- 范围型：价格区间列表 -->
        <div v-if="configOptionForm.option_mode === 'range'" class="config-dialog-span-3 sub-table-wrap">
          <el-table
            :data="configOptionForm.range_pricing"
            :row-key="getConfigRangePricingRowKey"
            size="small"
            border
            class="sub-table-grid"
            empty-text="暂无价格区间"
          >
            <el-table-column label="区间最小值" min-width="132">
              <template #default="{ row }">
                <el-input-number v-model="row.qty_minimum" :min="0" controls-position="right" style="width: 100%;" />
              </template>
            </el-table-column>
            <el-table-column label="区间最大值" min-width="132">
              <template #default="{ row }">
                <el-input-number v-model="row.qty_maximum" :min="0" controls-position="right" style="width: 100%;" />
              </template>
            </el-table-column>
            <el-table-column
              v-for="cyc in visibleConfigPricingCycles"
              :key="cyc.value"
              :label="cyc.label"
              min-width="132"
            >
              <template #default="{ row }">
                <el-input
                  v-model="row.pricing[cyc.value]"
                  placeholder="0.00"
                  @change="syncConfigPricingFieldsFromMonthly(row.pricing)"
                />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="72" align="center" fixed="right">
              <template #default="{ $index }">
                <div class="sub-table-action">
                  <el-button link type="danger" @click="removeRangePricingRow($index)">
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
          <div class="sub-table-add-row">
            <span class="sub-table-add-hint">添加价格区间</span>
            <el-button type="primary" plain size="small" @click="addRangePricingRow">+ 添加区间</el-button>
          </div>
        </div>

        <!-- 单选型：子项列表 -->
        <div v-if="configOptionForm.option_mode === 'select'" class="config-dialog-span-3 sub-table-wrap">
          <el-table
            :data="configOptionForm.sub_items"
            :row-key="getConfigSubItemRowKey"
            size="small"
            border
            class="sub-table-grid"
            empty-text="暂无子项"
          >
            <el-table-column label="子项名称" min-width="180">
              <template #default="{ row }">
                <el-input v-model="row.label" placeholder="子项名称" />
              </template>
            </el-table-column>
            <el-table-column label="传参值" min-width="140">
              <template #default="{ row }">
                <el-input v-model="row.value" placeholder="传参值" />
              </template>
            </el-table-column>
            <el-table-column
              v-for="cyc in visibleConfigPricingCycles"
              :key="cyc.value"
              :label="`${cyc.label}价格`"
              min-width="132"
            >
              <template #default="{ row }">
                <el-input
                  v-model="row.pricing[cyc.value]"
                  placeholder="0.00"
                  @change="syncConfigPricingFieldsFromMonthly(row.pricing)"
                />
              </template>
            </el-table-column>
            <el-table-column label="排序" width="110" align="center">
              <template #default="{ row }">
                <el-input-number v-model="row.sort_order" :min="0" controls-position="right" style="width: 100%;" />
              </template>
            </el-table-column>
            <el-table-column label="隐藏" width="80" align="center">
              <template #default="{ row }">
                <div class="sub-table-check">
                  <el-checkbox v-model="row.hidden" />
                </div>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="72" align="center" fixed="right">
              <template #default="{ $index }">
                <div class="sub-table-action">
                  <el-button link type="danger" @click="removeSubItem($index)">
                    <el-icon><Delete /></el-icon>
                  </el-button>
                </div>
              </template>
            </el-table-column>
          </el-table>
          <div class="sub-table-add-row">
            <el-input v-model="newSubItemLabel" placeholder="输入子项名称" style="width:180px;" />
            <el-input-number v-model="newSubItemSort" :min="0" controls-position="right" style="width:90px;" />
            <el-checkbox v-model="newSubItemHidden" />
            <el-button type="primary" plain size="small" @click="addSubItem">确认添加</el-button>
          </div>
        </div>

        <!-- 底部开关 -->
        <div class="config-dialog-switches config-dialog-span-3">
          <el-checkbox v-model="configOptionForm.required">必选</el-checkbox>
          <el-checkbox v-model="configOptionForm.hidden">隐藏</el-checkbox>
          <el-checkbox v-model="configOptionForm.allow_upgrade">允许升降级</el-checkbox>
          <el-checkbox v-model="configOptionForm.allow_promo_code">应用优惠码</el-checkbox>
        </div>
      </div>
      </el-form>

      <template #footer>
        <el-button @click="configOptionDialogVisible = false">关闭</el-button>
        <el-button type="primary" @click="saveConfigOption">保存更改</el-button>
      </template>
    </el-dialog>

    <!-- 商品拥有者抽屉 -->
    <el-drawer
      v-model="ownersDrawerVisible"
      title="商品拥有者"
      direction="rtl"
      size="760px"
      destroy-on-close
      class="owners-drawer"
    >
      <div class="owners-drawer-shell">
        <div class="owners-drawer-header">
          <div class="owners-product-info">
            <strong>{{ ownersProduct?.name }}</strong>
            <span>{{ ownersProduct?.category_full_name || ownersProduct?.group_full_name || '未分类' }}</span>
          </div>
          <div v-if="ownersSummary" class="owners-summary-row">
            <div class="owners-summary-item">
              <span class="owners-summary-val">{{ ownersSummary.owners_total }}</span>
              <span class="owners-summary-key">拥有者</span>
            </div>
            <div class="owners-summary-item">
              <span class="owners-summary-val">{{ ownersSummary.services_total }}</span>
              <span class="owners-summary-key">服务总数</span>
            </div>
            <div class="owners-summary-item">
              <span class="owners-summary-val">{{ ownersSummary.active_services_total }}</span>
              <span class="owners-summary-key">活跃服务</span>
            </div>
          </div>
        </div>

        <div class="owners-search-bar">
          <el-input
            v-model="ownersKeyword"
            placeholder="搜索用户名 / 邮箱 / 手机号"
            clearable
            @keyup.enter="loadOwners(1)"
            @clear="loadOwners(1)"
          >
            <template #prefix><el-icon><Search /></el-icon></template>
          </el-input>
          <el-button type="primary" @click="loadOwners(1)">搜索</el-button>
        </div>

        <el-table
          :data="ownersList"
          v-loading="ownersLoading"
          stripe
          size="small"
          class="owners-table"
        >
          <el-table-column label="用户" min-width="160">
            <template #default="{ row }">
              <div class="owner-user-cell">
                <strong>{{ row.display_name || row.nickname || '—' }}</strong>
                <span>{{ row.email }}</span>
                <span v-if="row.phone">{{ row.phone }}</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="80" align="center">
            <template #default="{ row }">
              <el-tag size="small" :type="row.status === 1 ? 'success' : 'danger'" effect="plain">
                {{ row.status_label }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="服务数" width="80" align="center">
            <template #default="{ row }">
              <span>{{ row.product_services_count }}</span>
            </template>
          </el-table-column>
          <el-table-column label="活跃" width="70" align="center">
            <template #default="{ row }">
              <span>{{ row.active_product_services_count }}</span>
            </template>
          </el-table-column>
          <el-table-column label="最近开通" min-width="130">
            <template #default="{ row }">
              <span class="owners-date">{{ row.latest_service_created_at || '—' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="最近到期" min-width="130">
            <template #default="{ row }">
              <span class="owners-date">{{ row.latest_service_expires_at || '—' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="72" align="center" fixed="right">
            <template #default="{ row }">
              <el-button
                link
                type="primary"
                size="small"
                @click="router.push({ name: 'AdminUserDetail', params: { id: row.id } })"
              >详情</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div class="owners-footer">
          <el-pagination
            v-model:current-page="ownersPage"
            v-model:page-size="ownersPageSize"
            :total="ownersTotal"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            @size-change="loadOwners(1)"
            @current-change="loadOwners"
          />
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ArrowDown,
  Box,
  Briefcase,
  ChromeFilled,
  Coin,
  Connection,
  Cpu,
  DataBoard,
  DataLine,
  Delete,
  DocumentCopy,
  Files,
  Folder,
  FolderChecked,
  FolderOpened,
  Grid,
  Link,
  Memo,
  Menu,
  Monitor,
  MostlyCloudy,
  MoreFilled,
  OfficeBuilding,
  Orange,
  Platform,
  Plus,
  Refresh,
  Search,
  SetUp,
  Suitcase,
  Tickets,
} from '@element-plus/icons-vue'
import productApi from '@/api/product'
import supplierApi from '@/api/supplier'
import { useResponsive } from '@/composables/useResponsive'

const { isMobile } = useResponsive()
import {
  statusOptions,
  visibleBillingCycleOptions,
  visibleConfigPricingCycles,
} from './catalogOptions'
import {
  billingCycleLabel,
  buildAssignableCategoryOptions,
  buildConfigPricingPayload,
  buildDerivedNumericPricingFromMonthly,
  buildCategoryTreeNode,
  createConfigOptionRecordFromSource,
  createDefaultConfigOptionForm as buildDefaultConfigOptionForm,
  createDefaultCategoryForm as buildDefaultCategoryForm,
  createDefaultPricing,
  createDefaultProductForm as buildDefaultProductForm,
  createEmptySubItemPricing,
  filterCategoryTree,
  formatSupplierOptionLabel,
  interfaceTypeLabel,
  nextConfigOptionUid,
  normalizeConfigOptions,
  normalizeConfigPricingFromSource,
  normalizeProviderSource,
  parseSupplierAmount,
  resolveConfigOptionMode,
  resolveHostingPanelOptionSpec,
  resolveMonthlyAmountFromPricing,
  sanitizePricing,
  serializeConfigOptions as serializeConfigOptionList,
  syncConfigPricingFieldsFromMonthly,
} from './catalogUtils'

const router = useRouter()

const summaryLoading = ref(false)
const typeLoading = ref(false)
const categoryLoading = ref(false)
const productLoading = ref(false)
const productDialogLoading = ref(false)
const supplierLoading = ref(false)
const supplierProductsLoading = ref(false)
const supplierProductsSyncing = ref(false)
const typeSubmitting = ref(false)
const categorySubmitting = ref(false)
const productSubmitting = ref(false)
const configOptionDialogVisible = ref(false)

const summary = reactive({
  groups_total: 0,
  root_groups_total: 0,
  sub_groups_total: 0,
  products_total: 0,
  products_active: 0,
  products_low_stock: 0,
})

const filters = reactive({
  keyword: '',
  category_id: '',
  product_type: '',
  status: '',
})

const productTypes = ref([])
const categoryTree = ref([])
const categoryOptions = ref([])
const products = ref([])
const suppliers = ref([])
const supplierProductGroups = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const categoryKeyword = ref('')

const categoryDialogVisible = ref(false)
const productDialogVisible = ref(false)
const mobileCategorySidebarVisible = ref(false)
const typeManagerDialogVisible = ref(false)
const editingCategory = ref(null)
const editingProduct = ref(null)
const editingTypeValue = ref('')
const typeIconPopoverVisible = ref(false)
const categoryTreeRef = ref()
const categoryFormRef = ref()
const productFormRef = ref()
const configOptionFormRef = ref()
const productTableRef = ref()
const expandedCategoryKeys = ref([])
const activeTreeNodeId = ref(0)
const draggingTypeValue = ref('')
const typeDropTargetValue = ref('')
const typeDropPosition = ref('')
const draggingCategoryId = ref(0)
const categoryDropTargetId = ref(0)
const categoryDropPosition = ref('')
const draggingProductId = ref(0)
const productDropTargetId = ref(0)
const productDropPosition = ref('')
const productDropCategoryId = ref(0)
const selectedProductRows = ref([])
const splitProductDialogVisible = ref(false)
const splitProductPreviewLoading = ref(false)
const splitProductSubmitting = ref(false)
const splitProductTargetRows = ref([])
const splitProductPreview = ref(null)
const batchCategoryDialogVisible = ref(false)
const batchCategorySubmitting = ref(false)
const batchCategoryTargetRows = ref([])
const provisionHostnameDialogVisible = ref(false)
const provisionHostnameSubmitting = ref(false)
const provisionHostnameTargetRows = ref([])
const provisionHostnameHasMixedRules = ref(false)

const typeForm = reactive({
  label: '',
  icon: '',
})

const productTypeIconMap = {
  Platform,
  Monitor,
  Connection,
  Cpu,
  OfficeBuilding,
  DataBoard,
  FolderChecked,
  DocumentCopy,
  MostlyCloudy,
  Folder,
  Orange,
  Briefcase,
  Box,
  Grid,
  Coin,
  Files,
  Link,
  DataLine,
  ChromeFilled,
  SetUp,
  FolderOpened,
  Suitcase,
  Tickets,
  Memo,
}

const productTypeIconOptions = [
  { value: 'Platform', label: '云服务器', component: Platform },
  { value: 'Cpu', label: '裸金属', component: Cpu },
  { value: 'Connection', label: 'NAT / 网络', component: Connection },
  { value: 'OfficeBuilding', label: '物理机', component: OfficeBuilding },
  { value: 'MostlyCloudy', label: 'CDN', component: MostlyCloudy },
  { value: 'ChromeFilled', label: '虚拟主机', component: ChromeFilled },
  { value: 'Link', label: '域名', component: Link },
  { value: 'DataBoard', label: '数据库', component: DataBoard },
  { value: 'FolderChecked', label: '云硬盘', component: FolderChecked },
  { value: 'DocumentCopy', label: '对象存储', component: DocumentCopy },
  { value: 'DataLine', label: '网络专线', component: DataLine },
  { value: 'Monitor', label: '云桌面', component: Monitor },
  { value: 'FolderOpened', label: '资源目录', component: FolderOpened },
  { value: 'Files', label: '文件服务', component: Files },
  { value: 'Coin', label: '计费产品', component: Coin },
  { value: 'Orange', label: '中间件', component: Orange },
  { value: 'Box', label: '通用产品', component: Box },
  { value: 'Grid', label: '其他分类', component: Grid },
  { value: 'Briefcase', label: '企业应用', component: Briefcase },
  { value: 'SetUp', label: '系统工具', component: SetUp },
  { value: 'Folder', label: '备份归档', component: Folder },
  { value: 'Suitcase', label: '业务方案', component: Suitcase },
  { value: 'Tickets', label: '票券服务', component: Tickets },
  { value: 'Memo', label: '轻量服务', component: Memo },
]

const provisionHostnameForm = reactive({
  mode: 'system',
  value: '',
  length: 12,
})

const batchCategoryForm = reactive({
  category_id: null,
})

const newSubItemLabel = ref('')
const newSubItemSort = ref(0)
const newSubItemHidden = ref(false)
let configOptionEditorRowKeySeed = 0

function nextConfigOptionEditorRowKey(prefix = 'config-row') {
  configOptionEditorRowKeySeed += 1
  return `${prefix}-${configOptionEditorRowKeySeed}`
}

function createConfigSubItemRecord(overrides = {}) {
  return {
    _row_key: nextConfigOptionEditorRowKey('config-sub'),
    label: '',
    value: '',
    pricing: createEmptySubItemPricing(),
    sort_order: 0,
    hidden: false,
    raw_id: '',
    ...overrides,
  }
}

function createConfigRangePricingRecord(overrides = {}) {
  return {
    _row_key: nextConfigOptionEditorRowKey('config-range'),
    qty_minimum: 0,
    qty_maximum: 0,
    pricing: createEmptySubItemPricing(),
    ...overrides,
  }
}

function getConfigSubItemRowKey(row) {
  return row._row_key
}

function getConfigRangePricingRowKey(row) {
  return row._row_key
}

function addSubItem() {
  const label = newSubItemLabel.value.trim()
  if (!label) { ElMessage.warning('请输入子项名称'); return }
  configOptionForm.sub_items.push(createConfigSubItemRecord({
    label,
    value: label,
    sort_order: newSubItemSort.value,
    hidden: newSubItemHidden.value,
  }))
  newSubItemLabel.value = ''
  newSubItemSort.value = 0
  newSubItemHidden.value = false
}

function removeSubItem(index) {
  configOptionForm.sub_items.splice(index, 1)
}

function addRangePricingRow() {
  configOptionForm.range_pricing.push(createConfigRangePricingRecord())
}

function removeRangePricingRow(index) {
  configOptionForm.range_pricing.splice(index, 1)
}

function onOptionModeChange(mode) {
  if (mode === 'range') {
    configOptionForm.sub_items = []
    if (!configOptionForm.range_pricing.length) {
      addRangePricingRow()
    }
    return
  }

  configOptionForm.range_pricing = []
}

function handleConfigOptionDialogClosed() {
  configOptionFormRef.value?.clearValidate?.()
}

const productDrawerTabs = [
  { key: 'details', label: '详情' },
  { key: 'pricing', label: '定价' },
  { key: 'automation', label: '自动开通' },
  { key: 'config', label: '商品配置' },
]

const categoryForm = reactive(createDefaultCategoryForm())
const productForm = reactive(createDefaultProductForm())
const configOptionForm = reactive(createDefaultConfigOptionForm())
const productDrawerTab = ref('details')
const editingConfigOptionIndex = ref(-1)

const categoryRules = {
  product_type: [{
    validator: (_rule, value, callback) => {
      if (categoryForm.parent_id || String(value || '').trim()) {
        callback()
        return
      }

      callback(new Error('请选择所属一级菜单'))
    },
    trigger: 'change',
  }],
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
}

const productRules = {
  category_id: [{ required: true, message: '请选择商品分类或子菜单', trigger: 'change' }],
  product_type: [{ required: true, message: '请选择商品类型', trigger: 'change' }],
}

const configOptionRules = {
  name: [{ required: true, message: '请输入配置项名称', trigger: 'blur' }],
  option_mode: [{ required: true, message: '请选择配置项类型', trigger: 'change' }],
  field: [
    { required: true, message: '请输入配置项标识', trigger: 'blur' },
    {
      validator: (_rule, value, callback) => {
        const normalized = String(value || '').trim()
        if (!normalized) {
          callback()
          return
        }

        if (!/^[A-Za-z0-9_]+$/.test(normalized)) {
          callback(new Error('配置项标识只允许字母、数字和下划线'))
          return
        }

        callback()
      },
      trigger: 'blur',
    },
  ],
  qty_minimum: [
    {
      validator: (_rule, value, callback) => {
        if (configOptionForm.option_mode !== 'range') {
          callback()
          return
        }

        if (Number(value) < 0) {
          callback(new Error('最小值不能小于 0'))
          return
        }

        callback()
      },
      trigger: 'change',
    },
  ],
  qty_maximum: [
    {
      validator: (_rule, value, callback) => {
        if (configOptionForm.option_mode !== 'range') {
          callback()
          return
        }

        const min = Number(configOptionForm.qty_minimum ?? 0)
        const max = Number(value ?? 0)
        if (max < min) {
          callback(new Error('最大值不能小于最小值'))
          return
        }

        callback()
      },
      trigger: 'change',
    },
  ],
  qty_step: [
    {
      validator: (_rule, value, callback) => {
        if (configOptionForm.option_mode !== 'range') {
          callback()
          return
        }

        if (Number(value) < 1) {
          callback(new Error('步进值至少为 1'))
          return
        }

        callback()
      },
      trigger: 'change',
    },
  ],
}

const productTypeOptions = computed(() => productTypes.value.map((item) => ({
  label: item.label,
  value: item.value,
  icon: item.icon || '',
  is_hidden: Number(item.is_hidden || 0) === 1,
  usage_count: Number(item.usage_count || 0),
  group_count: Number(item.group_count || 0),
})))

const activeCategoryId = computed(() => {
  const value = Number(filters.category_id)
  return Number.isFinite(value) && value > 0 ? value : 0
})

const activeCategoryTreeKey = computed(() => activeTreeNodeId.value ? `category-${activeTreeNodeId.value}` : null)
const activeProductType = computed(() => (
  productTypeOptions.value.find((item) => item.value === filters.product_type) || null
))
const activeProductTypeLabel = computed(() => activeProductType.value?.label || '未选择种类')
const currentTypeRootCategoryCount = computed(() => categoryTree.value.length)
const currentTypeChildCategoryCount = computed(() => categoryTree.value.reduce(
  (totalCount, item) => totalCount + Number(item.children_count ?? item.children?.length ?? 0),
  0,
))
const productPanelSubtitle = computed(() => (
  activeProductType.value
    ? `${activeProductTypeLabel.value} · ${activeProductType.value.usage_count || 0} 个商品 / ${activeProductType.value.group_count || 0} 个分类`
    : '价格、库存、开通方式与服务量一屏查看'
))
const typeDragHint = computed(() => {
  if (!draggingTypeValue.value || !typeDropTargetValue.value || !typeDropPosition.value) {
    return ''
  }

  const target = productTypeOptions.value.find((item) => item.value === typeDropTargetValue.value)
  if (!target) {
    return ''
  }

  return `将一级菜单插入到「${target.label}」${typeDropPosition.value === 'before' ? '前面' : '后面'}`
})
const categoryDragHint = computed(() => {
  if (!draggingCategoryId.value || !categoryDropTargetId.value || !categoryDropPosition.value) {
    return ''
  }

  const target = categoryNodeMap.value.get(categoryDropTargetId.value)
  if (!target) {
    return ''
  }

  if (categoryDropPosition.value === 'inner') {
    return `将分类移入「${target.name}」作为子级`
  }

  return `将分类插入到「${target.name}」${categoryDropPosition.value === 'before' ? '前面' : '后面'}`
})

const selectedCategoryLabel = computed(() => {
  const matched = categoryOptions.value.find((item) => item.id === activeCategoryId.value)
  return matched?.label || ''
})

const categoryNodeMap = computed(() => {
  const map = new Map()

  const walk = (nodes) => {
    nodes.forEach((node) => {
      map.set(Number(node.id), node)

      if (Array.isArray(node.children) && node.children.length) {
        walk(node.children)
      }
    })
  }

  walk(categoryTree.value.map((category) => buildCategoryTreeNode(category)))
  return map
})

const selectedCategoryNode = computed(() => categoryNodeMap.value.get(activeCategoryId.value) || null)
const selectedCategoryLevel = computed(() => Number(selectedCategoryNode.value?.level || 0))
const isRootCategorySelected = computed(() => activeCategoryId.value > 0 && selectedCategoryLevel.value === 1)
const selectedSupplier = computed(() => (
  suppliers.value.find((item) => item.id === productForm.supplier_id) || null
))

function canQuerySupplierCatalog(supplier) {
  if (!supplier) {
    return false
  }

  const hasApiUrl = supplier.has_api_url ?? Boolean(String(supplier.api_url || '').trim())
  const hasApiKey = supplier.has_api_key ?? Boolean(String(supplier.api_key || '').trim())

  return Boolean(
    hasApiUrl
    && String(supplier.api_username || '').trim()
    && hasApiKey
  )
}

const selectedSupplierCanSync = computed(() => canQuerySupplierCatalog(selectedSupplier.value))
const supplierProductItems = computed(() => (
  supplierProductGroups.value.flatMap((group) => group.items || [])
))
const selectedSupplierProduct = computed(() => (
  supplierProductItems.value.find((item) => item.id === Number(productForm.supplier_product_id || 0)) || null
))
const canPullConfigOptions = computed(() => (
  Boolean(productForm.supplier_id && productForm.supplier_product_id && selectedSupplierCanSync.value)
))
const supplierProductCascaderProps = {
  value: 'value',
  label: 'label',
  children: 'children',
  emitPath: false,
  expandTrigger: 'click',
}
const supplierProductCascaderOptions = computed(() => {
  const groups = supplierProductGroups.value.slice()

  if (
    productForm.supplier_product_id
    && !groups.some((group) => group.items?.some((item) => item.id === productForm.supplier_product_id))
  ) {
    groups.unshift({
      key: 'saved-product',
      label: '已绑定配置',
      items: [{
        id: productForm.supplier_product_id,
        name: `上游产品 #${productForm.supplier_product_id}`,
        type_label: '已保存',
      }],
    })
  }

  return groups
    .filter((group) => Array.isArray(group.items) && group.items.length)
    .map((group) => ({
      value: group.key,
      label: group.label,
      children: group.items.map((item) => ({
        value: Number(item.id),
        label: item.name,
        type_label: item.type_label || '',
        leaf: true,
      })),
    }))
})
const categoryOverviewText = computed(() => `${currentTypeRootCategoryCount.value} 个一级分类 / ${currentTypeChildCategoryCount.value} 个子分类`)
const normalizedCategoryKeyword = computed(() => categoryKeyword.value.trim().toLowerCase())
const canDragCategoryTree = computed(() => !normalizedCategoryKeyword.value && !categoryLoading.value)
const batchCategoryDialogTitle = computed(() => (
  batchCategoryTargetRows.value.length > 1 ? '批量更改商品分类' : '更改商品分类'
))
const batchCategorySourceSummary = computed(() => {
  const categoryNames = Array.from(new Set(
    batchCategoryTargetRows.value
      .map((row) => String(row.category_full_name || row.group_full_name || '').trim())
      .filter(Boolean)
  ))

  if (!categoryNames.length) {
    return ''
  }

  if (categoryNames.length === 1) {
    return `当前分类：${categoryNames[0]}`
  }

  return `涉及 ${categoryNames.length} 个原分类`
})
const batchCategoryPreviewText = computed(() => {
  const targetCategoryId = resolveSelectedCategoryId(batchCategoryForm.category_id)
  if (!targetCategoryId) {
    return '请选择目标分类后保存。'
  }

  const matchedCategory = assignableCategoryOptions.value.find((item) => Number(item.id) === Number(batchCategoryForm.category_id))
  const targetLabel = matchedCategory?.label || '目标分类'
  return `保存后会把已选商品统一移入「${targetLabel}」，并追加到该分类配置列表末尾。`
})
const provisionHostnameDialogTitle = computed(() => (
  provisionHostnameTargetRows.value.length > 1 ? '批量设置商品开通主机名' : '设置商品开通主机名'
))
const provisionHostnamePreviewText = computed(() => {
  if (provisionHostnameForm.mode === 'fixed') {
    return provisionHostnameForm.value.trim()
      ? `开通时将固定提交主机名：${provisionHostnameForm.value.trim()}。若上游不允许重复主机名，可能会被拒绝。`
      : '请填写固定主机名。'
  }

  if (provisionHostnameForm.mode === 'prefix') {
    const prefix = provisionHostnameForm.value.trim() || 'prefix'
    return `开通时将按“${prefix} + 随机后缀”生成主机名，总长度 ${Number(provisionHostnameForm.length || 12)}；若上游限制了前缀或长度，会自动按上游约束修正。`
  }

  return '默认优先按上游商品规则生成；若上游未返回规则，则回退到系统默认规则或账单快照。'
})
const splitProductPreviewItems = computed(() => (
  Array.isArray(splitProductPreview.value?.items) ? splitProductPreview.value.items : []
))
const splitProductPreviewRows = computed(() => splitProductPreviewItems.value.flatMap((item) => {
  const variants = Array.isArray(item?.variants) ? item.variants : []
  return variants.map((variant) => ({
    key: `${item.source_product_id || 'source'}-${variant.variant_key || variant.display_name}`,
    product_id: Number(variant.product_id || variant.id || 0),
    display_name: variant.display_name || '-',
    source_product_id: Number(item.source_product_id || item.product_id || 0),
    source_display_name: variant.source_display_name || item.source_display_name || '-',
    action: variant.action || 'create',
  }))
}))
const splitProductPreviewCount = computed(() => Number(splitProductPreview.value?.preview_count || 0))
const splitProductSkippedCount = computed(() => Number(splitProductPreview.value?.skipped_count || 0))

const tableEmptyTitle = computed(() => {
  if (selectedCategoryLabel.value) {
    return '当前分类暂无商品'
  }

  if (!filters.product_type) {
    return '请先选择一级菜单'
  }

  return '暂无商品数据'
})

const tableEmptyDescription = computed(() => (
  selectedCategoryLabel.value
    ? '可以先新增商品，或者切换左侧分类查看其他目录。'
    : filters.product_type
      ? '当前一级菜单下还没有商品，可以先新增商品或补充分类。'
      : '请先创建一级菜单种类，再维护分类和商品目录。'
))

const categoryTreeProps = {
  label: 'name',
  children: 'children',
}

const filteredRawCategoryTree = computed(() => filterCategoryTree(categoryTree.value, normalizedCategoryKeyword.value))
const categoryTreeNodes = computed(() => filteredRawCategoryTree.value.map((category) => buildCategoryTreeNode(category)))
const assignableCategoryOptions = computed(() => buildAssignableCategoryOptions(categoryTree.value))

const activeFilterTags = computed(() => {
  const tags = []

  if (filters.keyword.trim()) {
    tags.push({ key: 'keyword', label: `关键词：${filters.keyword.trim()}` })
  }

  if (filters.status !== '' && filters.status !== null) {
    const matchedStatus = statusOptions.find((item) => item.value === filters.status)
    tags.push({ key: 'status', label: `状态：${matchedStatus?.label || filters.status}` })
  }

  return tags
})
const availableParentCategories = computed(() => (
  categoryTree.value
    .filter((item) => item.id !== editingCategory.value?.id)
    .map((item) => ({
      id: item.id,
      category_id: item.category_id ?? item.id,
      label: item.name,
      product_type: item.product_type,
    }))
))

watch([categoryTreeNodes, activeCategoryTreeKey, expandedCategoryKeys], async () => {
  await nextTick()
  syncCategoryTreeExpansion()
  categoryTreeRef.value?.setCurrentKey(activeCategoryTreeKey.value)
}, { immediate: true })

watch([normalizedCategoryKeyword, filteredRawCategoryTree], ([keyword, filteredGroups]) => {
  if (keyword) {
    expandedCategoryKeys.value = filteredGroups.map((category) => `category-${category.id}`)
    return
  }

  if (selectedCategoryLevel.value === 2 && selectedCategoryNode.value?.parent_id) {
    expandedCategoryKeys.value = [`category-${selectedCategoryNode.value.parent_id}`]
    return
  }

  expandedCategoryKeys.value = []
}, { immediate: true })

watch(activeCategoryId, (groupId) => {
  if (!groupId) {
    return
  }

  activeTreeNodeId.value = groupId
  const currentNode = categoryNodeMap.value.get(groupId)
  if (currentNode && Number(currentNode.level) > 1 && currentNode.parent_id) {
    expandedCategoryKeys.value = [`category-${currentNode.parent_id}`]
  }
}, { immediate: true })

watch(() => categoryForm.parent_id, (parentId) => {
  if (!parentId) {
    if (!categoryForm.product_type) {
      categoryForm.product_type = filters.product_type || productTypes.value[0]?.value || ''
    }
    return
  }

  const matchedParent = availableParentCategories.value.find((item) => Number(item.id) === Number(parentId))
  if (matchedParent?.product_type) {
    categoryForm.product_type = matchedParent.product_type
  }
  categoryForm.parent_id = matchedParent?.id ?? null
})

watch(typeManagerDialogVisible, (visible) => {
  if (!visible) {
    resetTypeDragState()
    resetTypeForm()
  }
})

function resolveDefaultProductType(fallback = '') {
  return filters.product_type || productTypes.value[0]?.value || fallback
}

function createDefaultCategoryForm() {
  return buildDefaultCategoryForm(resolveDefaultProductType(''))
}

function createDefaultProductForm() {
  return buildDefaultProductForm(resolveDefaultProductType('other'))
}

function createDefaultConfigOptionForm() {
  return buildDefaultConfigOptionForm()
}

function serializeConfigOptions() {
  return serializeConfigOptionList(productForm.config_options)
}

async function pullConfigOptionsFromSupplierProduct(options = {}) {
  const supplierId = Number(productForm.supplier_id || 0)
  const supplierProductId = Number(productForm.supplier_product_id || 0)

  if (supplierId <= 0) {
    if (!options.silent) {
      ElMessage.warning('请先选择供应商')
    }
    return false
  }

  if (!selectedSupplierCanSync.value) {
    if (!options.silent) {
      ElMessage.warning('当前供应商接口配置不完整，无法拉取商品配置模板')
    }
    return false
  }

  if (supplierProductId <= 0) {
    if (!options.silent) {
      ElMessage.warning('请先选择供应商商品')
    }
    return false
  }

  try {
    const res = await supplierApi.productConfigTemplate(supplierId, supplierProductId, { silent: true })
    const configOptions = normalizeConfigOptions(res.data.config_options || [])

    productForm.config_options = configOptions

    if (!options.silent && configOptions.length > 0) {
      const autoFilledFields = Array.isArray(res.data.auto_filled_fields) ? res.data.auto_filled_fields : []
      const autoFilledText = autoFilledFields.length ? `，已自动带出 ${autoFilledFields.join('、')}` : ''
      ElMessage.success(`已从接口拉取 ${configOptions.length} 项配置${autoFilledText}，保存商品后生效`)
    }

    if (!options.silent && configOptions.length === 0) {
      ElMessage.warning('接口未返回可保存的商品配置项')
    }

    return true
  } catch (error) {
    if (!options.silent) {
      ElMessage.error(error?.message || '商品配置拉取失败')
    }

    return false
  }
}

function handleConfigOptionAction(command, row, index) {
  if (command === 'edit') {
    openConfigOptionDialog(row, index)
  } else if (command === 'delete') {
    removeConfigOption(index)
  }
}

function openConfigOptionDialog(option = null, index = -1) {
  editingConfigOptionIndex.value = index
  const defaults = createDefaultConfigOptionForm()

  if (!option) {
    Object.assign(configOptionForm, defaults, { sort_order: productForm.config_options.length + 1 })
    newSubItemLabel.value = ''
    newSubItemSort.value = 0
    newSubItemHidden.value = false
    configOptionDialogVisible.value = true
    return
  }

  // 反填现有配置项
  const base = createConfigOptionRecordFromSource(option, index)
  const raw = option

  // 判断模式
  const mode = resolveConfigOptionMode(raw)

  // 反填单选子项
  const subItems = []
  // 优先用 sub_items（上次保存的 UI 格式）
  if (Array.isArray(raw.sub_items) && raw.sub_items.length > 0) {
    for (const s of raw.sub_items) {
      const p = normalizeConfigPricingFromSource(s.pricing || {})
      subItems.push(createConfigSubItemRecord({
        label: String(s.label ?? ''),
        value: String(s.value ?? s.label ?? ''),
        pricing: p,
        sort_order: Number(s.sort_order ?? 0),
        hidden: Boolean(s.hidden),
        raw_id: s.raw_id ?? s.id ?? '',
      }))
    }
  } else if (Array.isArray(raw.sub) && raw.sub.length > 0 && mode !== 'range') {
    // 从后端 sub 格式反填（含价格）
    for (const s of raw.sub) {
      const p = normalizeConfigPricingFromSource(s.pricing || {})
      subItems.push(createConfigSubItemRecord({
        label: String(s.option_name ?? s.version ?? s.label ?? s.option_name_first ?? s.id ?? ''),
        value: String(s.option_name_first ?? s.value ?? s.id ?? s.option_name ?? ''),
        pricing: p,
        sort_order: Number(s.sort_order ?? 0),
        hidden: Boolean(s.hidden),
        raw_id: s.id ?? '',
      }))
    }
  } else if (typeof raw.parameter === 'string' && raw.parameter.trim() && mode !== 'range') {
    // 从旧的 parameter 字符串解析（如 "2|2核,4|4核"），价格留空待填
    const paramStr = raw.parameter.trim()
    const segments = paramStr.split(',').map(s => s.trim()).filter(Boolean)
    // 判断是否为 "value|label" 格式
    for (const seg of segments) {
      const pipeIdx = seg.indexOf('|')
      if (pipeIdx > 0) {
        const val = seg.slice(0, pipeIdx).trim()
        const lbl = seg.slice(pipeIdx + 1).trim()
        subItems.push(createConfigSubItemRecord({
          label: lbl || val,
          value: val,
        }))
      } else {
        subItems.push(createConfigSubItemRecord({
          label: seg,
          value: seg,
        }))
      }
    }
  }

  // 反填范围区间
  const rangePricing = []
  if (Array.isArray(raw.range_pricing) && raw.range_pricing.length > 0) {
    // 优先用 range_pricing（上次保存的 UI 格式）
    for (const r of raw.range_pricing) {
      const p = normalizeConfigPricingFromSource(r.pricing || {})
      rangePricing.push(createConfigRangePricingRecord({
        qty_minimum: Number(r.qty_minimum ?? 0),
        qty_maximum: Number(r.qty_maximum ?? 0),
        pricing: p,
      }))
    }
  } else if (Array.isArray(raw.sub) && raw.sub.length > 0 && mode === 'range') {
    // 兼容旧 sub 格式（range 类型的 sub 实际是区间）
    for (const s of raw.sub) {
      const p = normalizeConfigPricingFromSource(s.pricing || {})
      rangePricing.push(createConfigRangePricingRecord({
        qty_minimum: Number(s.qty_minimum ?? 0),
        qty_maximum: Number(s.qty_maximum ?? 0),
        pricing: p,
      }))
    }
  }

  Object.assign(configOptionForm, defaults, base, {
    option_mode: mode,
    show_advanced: Boolean(raw.show_advanced || raw.description || raw.suffix_text),
    suffix_text: String(raw.suffix_text ?? ''),
    sub_items: subItems,
    qty_minimum: Number(raw.qty_minimum ?? 0),
    qty_maximum: Number(raw.qty_maximum ?? 100),
    qty_step: Number(raw.qty_step ?? 1),
    range_pricing: rangePricing,
  })

  newSubItemLabel.value = ''
  newSubItemSort.value = 0
  newSubItemHidden.value = false
  configOptionDialogVisible.value = true
}

async function openSplitProductDialog() {
  const targetRows = selectedProductRows.value

  if (!targetRows.length) {
    ElMessage.warning('请先选择需要拆分的商品')
    return
  }

  splitProductTargetRows.value = targetRows
  splitProductPreview.value = null
  splitProductDialogVisible.value = true
  splitProductPreviewLoading.value = true
  try {
    const res = await productApi.splitPreview({
      product_ids: targetRows.map((row) => Number(row.id)),
    })
    splitProductPreview.value = res?.data || null
  } finally {
    splitProductPreviewLoading.value = false
  }
}

async function handleSubmitSplitProducts() {
  if (!splitProductTargetRows.value.length) {
    return
  }

  splitProductSubmitting.value = true
  try {
    const res = await productApi.splitProducts({
      product_ids: splitProductTargetRows.value.map((row) => Number(row.id)),
    })
    const data = res?.data || {}
    const createdCount = Number(data.created_count || 0)
    const updatedCount = Number(data.updated_count || 0)
    const skippedCount = Number(data.skipped_count || 0)
    const changedCount = createdCount + updatedCount

    if (changedCount > 0) {
      ElMessage.success(`已生成 ${createdCount} 个商品，更新 ${updatedCount} 个，跳过 ${skippedCount} 个`)
    } else {
      ElMessage.warning(skippedCount > 0 ? `未生成新商品，跳过 ${skippedCount} 个` : '未找到可拆分商品')
    }

    splitProductDialogVisible.value = false
    await Promise.all([loadSummary(), loadProducts()])
  } finally {
    splitProductSubmitting.value = false
  }
}

async function saveConfigOption() {
  try {
    await configOptionFormRef.value?.validate?.()
  } catch {
    return
  }

  const field = String(configOptionForm.field || '').trim()
  const name = String(configOptionForm.name || '').trim()

  if (!field) { ElMessage.error('请输入配置项标识'); return }
  if (!name) { ElMessage.error('请输入配置项名称'); return }

  const mode = configOptionForm.option_mode
  const isRange = mode === 'range'

  // 验证
  if (isRange && configOptionForm.range_pricing.length === 0) {
    ElMessage.error('范围型配置项至少需要一个价格区间'); return
  }
  if (!isRange && configOptionForm.sub_items.length === 0) {
    // 允许无子项（如 os/area 等只传参数不计费）但 parameter 必须有值
    if (!String(configOptionForm.parameter || '').trim()) {
      ElMessage.error('单选型配置项请添加至少一个子项，或填写参数字段'); return
    }
  }

  const normalizedField = field.toLowerCase()
  const hasDuplicate = productForm.config_options.some((item, index) => (
    index !== editingConfigOptionIndex.value && String(item.field || '').trim().toLowerCase() === normalizedField
  ))
  if (hasDuplicate) { ElMessage.error('配置项标识重复，同一个配置项只需维护一次'); return }

  // 构建 sub 数组（后端 OrderService 读取此格式）
  let sub = []
  if (isRange) {
    sub = configOptionForm.range_pricing.map((r, ri) => {
      const pricing = buildConfigPricingPayload(r.pricing)
      return {
        id: ri,
        qty_minimum: Number(r.qty_minimum ?? 0),
        qty_maximum: Number(r.qty_maximum ?? 0),
        pricing,
        hidden: 0,
      }
    })
  } else {
    sub = configOptionForm.sub_items.map((s, si) => {
      const pricing = buildConfigPricingPayload(s.pricing)
      return {
        id: String(s.raw_id || s.value || s.label || si),
        option_name: String(s.label || ''),
        option_name_first: String(s.value || s.label || ''),
        version: String(s.label || ''),
        pricing,
        sort_order: Number(s.sort_order ?? si),
        hidden: s.hidden ? 1 : 0,
      }
    })
  }

  // 构建 parameter（兼容旧格式展示，单选型从 sub_items 自动生成）
  let parameter = String(configOptionForm.parameter || '').trim()
  if (!isRange && configOptionForm.sub_items.length > 0) {
    parameter = configOptionForm.sub_items.map(s => `${s.value}|${s.label}`).join(',')
  }

  const payload = {
    uid: configOptionForm.uid || nextConfigOptionUid(),
    source: normalizeProviderSource(configOptionForm.source),
    spec_key: configOptionForm.spec_key || resolveHostingPanelOptionSpec(field)?.field || '',
    field,
    name,
    option_mode: mode,
    parameter,
    description: String(configOptionForm.description || '').trim(),
    suffix_text: String(configOptionForm.suffix_text || '').trim(),
    required: Boolean(configOptionForm.required),
    default_value: String(configOptionForm.default_value || '').trim(),
    sort_order: Math.max(Number(configOptionForm.sort_order || 0), 0),
    hidden: Boolean(configOptionForm.hidden),
    allow_upgrade: Boolean(configOptionForm.allow_upgrade),
    allow_promo_code: Boolean(configOptionForm.allow_promo_code),
    sub,
    // 范围型额外字段
    ...(isRange ? {
      qty_minimum: Number(configOptionForm.qty_minimum ?? 0),
      qty_maximum: Number(configOptionForm.qty_maximum ?? 100),
      qty_step: Number(configOptionForm.qty_step ?? 1),
    } : {}),
    // 单选型保存 sub_items 以便再次编辑时反填 UI
    ...(!isRange ? { sub_items: [...configOptionForm.sub_items] } : {}),
    range_pricing: isRange ? [...configOptionForm.range_pricing] : [],
    extra: { ...(configOptionForm.extra || {}) },
  }

  if (editingConfigOptionIndex.value >= 0) {
    productForm.config_options.splice(editingConfigOptionIndex.value, 1, payload)
  } else {
    productForm.config_options.push(payload)
  }

  configOptionDialogVisible.value = false
}

function removeConfigOption(index) {
  productForm.config_options.splice(index, 1)
}

function resolveSupplierProvisionModule(supplierId = productForm.supplier_id) {
  const normalizedSupplierId = Number(supplierId || 0)
  if (normalizedSupplierId <= 0) {
    return ''
  }

  return suppliers.value.find((item) => item.id === normalizedSupplierId)?.interface_type || ''
}

function syncProvisionModuleWithSupplier(supplierId = productForm.supplier_id) {
  productForm.provision_module = resolveSupplierProvisionModule(supplierId)
}

function applySupplierProductPricing(supplierProduct) {
  if (!supplierProduct || typeof supplierProduct !== 'object') {
    return false
  }

  const monthlyPrice = parseSupplierAmount(supplierProduct.monthly_price ?? supplierProduct.product_price)
  const setupFee = parseSupplierAmount(supplierProduct.setup_fee)
  let synced = false

  if (monthlyPrice !== null && monthlyPrice > 0) {
    Object.assign(productForm.pricing, buildDerivedNumericPricingFromMonthly(monthlyPrice))
    synced = true
  }

  if (setupFee !== null && setupFee >= 0) {
    productForm.setup_fee = setupFee
    synced = true
  }

  return synced
}

async function syncSelectedSupplierProductData(options = {}) {
  const matchedProduct = selectedSupplierProduct.value
  if (!matchedProduct) {
    return false
  }

  const pricingSynced = applySupplierProductPricing(matchedProduct)
  const configSynced = await pullConfigOptionsFromSupplierProduct({ silent: true })

  if (!configSynced && options.warnOnConfigFailure) {
    ElMessage.warning('当前商品未能自动拉取配置项，可稍后点击“从接口拉取”重试')
  }

  if (!options.silent) {
    const syncedParts = []

    if (pricingSynced) {
      syncedParts.push('价格')
    }

    if (configSynced) {
      syncedParts.push('配置')
    }

    if (syncedParts.length > 0) {
      ElMessage.success(`已自动同步上游${syncedParts.join('和')}`)
    }
  }

  return pricingSynced || configSynced
}

function fillPricingFromMonthly(options = {}) {
  const monthlyPrice = parseSupplierAmount(productForm.pricing.monthly)
  if (monthlyPrice === null || (!options.allowZero && monthlyPrice <= 0)) {
    Object.assign(productForm.pricing, createDefaultPricing())

    if (!options.silent) {
      ElMessage.warning('请先填写月付价格')
    }
    return
  }

  Object.assign(productForm.pricing, buildDerivedNumericPricingFromMonthly(monthlyPrice))

  if (!options.silent) {
    ElMessage.success('已按月付补齐其他周期价格')
  }
}

function categoryTreeNodeNote(node) {
  if (Number(node.level) === 1) {
    return `${node.children_count || 0} 个子分类`
  }

  return `${node.products_count || 0} 个商品`
}

function typeTagType(type) {
  if (type === 'vps') return 'primary'
  if (type === 'dedicated') return 'warning'
  if (type === 'hosting') return 'success'
  if (type === 'domain') return ''
  return 'info'
}

function formatCpuMemoryDisplay(value) {
  const source = String(value || '').trim()
  if (!source) {
    return ''
  }

  const normalized = source.toLowerCase()
  const cpuMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(?:v?cpu|核|c)/i)
  const memoryMatch = normalized.match(/(\d+(?:\.\d+)?)\s*(tib|tb|t|gib|gb|g|mib|mb|m)/i)

  if (!cpuMatch || !memoryMatch) {
    return ''
  }

  const cpuValue = normalizeModelDisplayNumber(cpuMatch[1])
  const memoryValue = normalizeModelDisplayNumber(memoryMatch[1])
  const memoryUnit = normalizeModelMemoryUnit(memoryMatch[2])

  if (!cpuValue || !memoryValue || !memoryUnit) {
    return ''
  }

  return `${cpuValue}vcpu${memoryValue}${memoryUnit}`
}

function normalizeModelDisplayNumber(value) {
  const source = String(value || '').trim()
  if (!source) {
    return ''
  }

  const parsed = Number(source)
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return ''
  }

  return Number.isInteger(parsed) ? String(parsed) : String(parsed)
}

function normalizeModelMemoryUnit(value) {
  const source = String(value || '').trim().toLowerCase()
  if (!source) {
    return ''
  }

  if (source.startsWith('t')) {
    return 'tib'
  }

  if (source.startsWith('g')) {
    return 'gib'
  }

  if (source.startsWith('m')) {
    return 'mib'
  }

  return ''
}

function resolveProductModelLabel(product) {
  const modelLabel = formatCpuMemoryDisplay(product?.cpu_memory_display)
  if (modelLabel) {
    return modelLabel
  }

  const combinedModelLabel = formatCpuMemoryDisplay(product?.combined_display_name)
  if (combinedModelLabel) {
    return combinedModelLabel
  }

  return product?.display_name || `未配置规格 #${product?.id || '-'}`
}

async function loadSummary() {
  summaryLoading.value = true
  try {
    const res = await productApi.summary()
    Object.assign(summary, res.data || {})
  } finally {
    summaryLoading.value = false
  }
}

async function loadProductTypes() {
  typeLoading.value = true
  try {
    const res = await productApi.types()
    productTypes.value = res.data.list || []

    if (!productTypes.value.length) {
      filters.product_type = ''
      return
    }

    if (!productTypes.value.some((item) => item.value === filters.product_type)) {
      filters.product_type = productTypes.value[0].value
    }
  } finally {
    typeLoading.value = false
  }
}

async function loadCategories(options = {}) {
  if (!filters.product_type) {
    categoryTree.value = []
    categoryOptions.value = []
    return
  }

  categoryLoading.value = true
  try {
    const res = await productApi.categories({ product_type: filters.product_type })
    categoryTree.value = res.data.tree || []
    categoryOptions.value = res.data.options || []

    if (activeCategoryId.value && !categoryOptions.value.some((item) => item.id === activeCategoryId.value)) {
      clearCategoryFilter({ preserveTreeFocus: false })
    }

    const focusedExists = categoryTree.value.some((category) => (
      Number(category.id) === Number(activeTreeNodeId.value)
      || (Array.isArray(category.children) && category.children.some((child) => Number(child.id) === Number(activeTreeNodeId.value)))
    ))
    if (!focusedExists) {
      activeTreeNodeId.value = 0
    }

    // 初次加载（无当前选中）自动选中第一个可售子分类
    if (options.autoSelectFirst && !activeCategoryId.value && categoryTree.value.length) {
      // 从树中找第一个叶子节点（level>1 或无子分类的根节点）
      let firstLeaf = null
      for (const root of categoryTree.value) {
        if (Array.isArray(root.children) && root.children.length) {
          firstLeaf = root.children[0]
          break
        } else if (!root.children_count || Number(root.children_count) === 0) {
          firstLeaf = root
          break
        }
      }
      if (firstLeaf) {
        activeTreeNodeId.value = Number(firstLeaf.id)
        filters.category_id = firstLeaf.category_id || firstLeaf.id || ''
        page.value = 1
        if (firstLeaf.parent_id) {
          expandedCategoryKeys.value = [`category-${firstLeaf.parent_id}`]
        }
      }
    }
  } finally {
    categoryLoading.value = false
  }
}

async function loadSupplierOptions() {
  supplierLoading.value = true
  try {
    const res = await supplierApi.list({
      status: 1,
      page: 1,
      page_size: 100,
    })

    suppliers.value = (res.data.list || []).map((item) => ({
      id: Number(item.id),
      name: item.name,
      interface_type: item.interface_type,
      api_url: item.api_url,
      has_api_url: Boolean(item.has_api_url),
      api_username: item.api_username || '',
      api_key: item.api_key || '',
      has_api_key: Boolean(item.has_api_key),
    }))

    if (productForm.supplier_id && !suppliers.value.some((item) => item.id === productForm.supplier_id)) {
      productForm.supplier_id = null
      productForm.supplier_product_id = null
      productForm.provision_module = ''
      supplierProductGroups.value = []
    } else if (productForm.supplier_id) {
      syncProvisionModuleWithSupplier(productForm.supplier_id)
    }
  } finally {
    supplierLoading.value = false
  }
}

async function loadSupplierProducts(supplierId, options = {}) {
  const normalizedSupplierId = Number(supplierId || 0)
  if (normalizedSupplierId <= 0) {
    supplierProductGroups.value = []
    return
  }

  const supplier = suppliers.value.find((item) => Number(item.id) === normalizedSupplierId) || null
  if (!canQuerySupplierCatalog(supplier)) {
    supplierProductGroups.value = []

    if (!options.silent) {
      ElMessage.warning('当前供应商接口配置不完整，无法同步上游商品')
    }
    return
  }

  const loadingRef = options.syncing ? supplierProductsSyncing : supplierProductsLoading
  loadingRef.value = true

  try {
    const res = await supplierApi.products(normalizedSupplierId, { silent: true })
    supplierProductGroups.value = (res.data.groups || []).map((group) => ({
      key: group.key,
      label: group.label,
      items: (group.items || []).map((item) => ({
        id: Number(item.id),
        name: item.name,
        type: item.type || '',
        type_label: item.type_label || item.type || '',
        description: item.description || '',
        group_label: item.group_label || group.label,
        billingcycle: item.billingcycle || '',
        product_price: item.product_price || null,
        monthly_price: item.monthly_price || null,
        setup_fee: item.setup_fee ?? null,
      })),
    }))

    if (options.message) {
      ElMessage.success('供应商商品已同步')
    }
  } catch {
    supplierProductGroups.value = []

    if (!options.silent) {
      ElMessage.error('供应商商品同步失败')
    }
  } finally {
    loadingRef.value = false
  }
}

async function loadProducts() {
  if (!filters.product_type) {
    products.value = []
    total.value = 0
    return
  }

  productLoading.value = true
  try {
    const res = await productApi.list({
      ...filters,
      product_type: filters.product_type || undefined,
      category_id: filters.category_id || undefined,
      page: page.value,
      page_size: pageSize.value,
    })
    products.value = res.data.list || []
    total.value = res.data.total || 0
    selectedProductRows.value = []
    await nextTick()
    productTableRef.value?.clearSelection?.()
  } finally {
    productLoading.value = false
  }
}

async function loadData() {
  await Promise.all([loadSummary(), loadProductTypes()])
  await loadCategories({ autoSelectFirst: true })
  await loadProducts()
}

function handleSearch() {
  page.value = 1
  loadProducts()
}

function resetFilters() {
  filters.keyword = ''
  filters.category_id = ''
  filters.status = ''
  activeTreeNodeId.value = 0
  page.value = 1
  loadProducts()
}

function selectCategory(category) {
  activeTreeNodeId.value = Number(category?.id || 0)
  filters.category_id = category?.category_id || category?.id || ''
  page.value = 1
  mobileCategorySidebarVisible.value = false
  loadProducts()
}

function clearCategoryFilter(options = {}) {
  filters.category_id = ''
  if (!options.preserveTreeFocus) {
    activeTreeNodeId.value = 0
  }
  page.value = 1
  mobileCategorySidebarVisible.value = false
  loadProducts()
}

function handleCategoryTreeSelect(data) {
  activeTreeNodeId.value = Number(data.id || 0)

  if (Number(data.level) === 1) {
    toggleRootCategory(data)
    return
  }

  if (data.parent_id) {
    expandedCategoryKeys.value = [`category-${data.parent_id}`]
  }

  selectCategory(data)
}

function handleCategoryNodeExpand(data) {
  if (Number(data.level) !== 1) {
    return
  }

  expandRootCategory(data)
}

function handleCategoryNodeCollapse(data) {
  if (Number(data.level) !== 1) {
    return
  }

  collapseRootCategory(data)
}

function toggleRootCategory(category) {
  const categoryKey = `category-${category.id}`
  const isExpanded = expandedCategoryKeys.value.includes(categoryKey)

  if (isExpanded) {
    collapseRootCategory(category)
    return
  }

  expandRootCategory(category)
}

function expandRootCategory(category) {
  const categoryKey = `category-${category.id}`
  const hasSelectedChild = Number(selectedCategoryNode.value?.parent_id) === Number(category.id)

  if (expandedCategoryKeys.value.length === 1 && expandedCategoryKeys.value[0] === categoryKey) {
    return
  }

  expandedCategoryKeys.value = [categoryKey]

  if (selectedCategoryNode.value && !hasSelectedChild) {
    clearCategoryFilter({ preserveTreeFocus: true })
  }
}

function collapseRootCategory(category) {
  const hasSelectedChild = Number(selectedCategoryNode.value?.parent_id) === Number(category.id)

  if (expandedCategoryKeys.value.length === 0) {
    return
  }

  expandedCategoryKeys.value = []

  if (hasSelectedChild) {
    clearCategoryFilter({ preserveTreeFocus: true })
  }
}

function clearFilter(key) {
  filters[key] = ''
  if (key === 'category_id') {
    activeTreeNodeId.value = 0
    filters.category_id = ''
  }
  page.value = 1
  loadProducts()
}

function resetProductDragState() {
  draggingProductId.value = 0
  productDropTargetId.value = 0
  productDropPosition.value = ''
  productDropCategoryId.value = 0
}

function resetTypeDragState() {
  draggingTypeValue.value = ''
  typeDropTargetValue.value = ''
  typeDropPosition.value = ''
}

function resetCategoryDragState() {
  draggingCategoryId.value = 0
  categoryDropTargetId.value = 0
  categoryDropPosition.value = ''
}

function canAssignProductToCategory(category) {
  if (!category) {
    return false
  }

  return Number(category.level || 0) > 1 || Number(category.children_count || 0) === 0
}

function handleTypeDragStart(item, event) {
  draggingTypeValue.value = item.value
  typeDropTargetValue.value = ''
  typeDropPosition.value = ''
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', item.value)
}

function handleTypeDragOver(item, event) {
  if (!draggingTypeValue.value || draggingTypeValue.value === item.value) {
    return
  }

  const rect = event.currentTarget.getBoundingClientRect()
  const position = event.clientX - rect.left < rect.width / 2 ? 'before' : 'after'
  typeDropTargetValue.value = item.value
  typeDropPosition.value = position
  event.dataTransfer.dropEffect = 'move'
}

function handleTypeManagerDragOver(item, event) {
  if (!draggingTypeValue.value || draggingTypeValue.value === item.value) {
    return
  }

  const rect = event.currentTarget.getBoundingClientRect()
  const position = event.clientY - rect.top < rect.height / 2 ? 'before' : 'after'
  typeDropTargetValue.value = item.value
  typeDropPosition.value = position
  event.dataTransfer.dropEffect = 'move'
}

async function handleTypeDrop(item) {
  if (!draggingTypeValue.value || draggingTypeValue.value === item.value) {
    return
  }

  const currentValues = productTypeOptions.value.map((entry) => entry.value)
  const draggingIndex = currentValues.indexOf(draggingTypeValue.value)
  const targetIndex = currentValues.indexOf(item.value)

  if (draggingIndex === -1 || targetIndex === -1) {
    resetTypeDragState()
    return
  }

  const reorderedValues = currentValues.filter((value) => value !== draggingTypeValue.value)
  const draggingValue = draggingTypeValue.value
  const adjustedTargetIndex = reorderedValues.indexOf(item.value)
  const insertIndex = typeDropPosition.value === 'after' ? adjustedTargetIndex + 1 : adjustedTargetIndex
  reorderedValues.splice(insertIndex, 0, draggingValue)

  typeLoading.value = true
  try {
    await productApi.reorderTypes({ values: reorderedValues })
    const currentType = filters.product_type
    await loadProductTypes()
    filters.product_type = currentType
    ElMessage.success('一级菜单排序已更新')
  } finally {
    resetTypeDragState()
    typeLoading.value = false
  }
}

async function handleTypeManagerDrop(item, event) {
  if (!draggingTypeValue.value || draggingTypeValue.value === item.value) {
    return
  }

  const rect = event.currentTarget.getBoundingClientRect()
  typeDropPosition.value = event.clientY - rect.top < rect.height / 2 ? 'before' : 'after'
  await handleTypeDrop(item)
}

function handleTypeDragEnd() {
  resetTypeDragState()
}

function typeChipClass(item) {
  const isCurrentTarget = typeDropTargetValue.value === item.value

  return {
    active: filters.product_type === item.value,
    'is-hidden': Boolean(item.is_hidden),
    'is-dragging': draggingTypeValue.value === item.value,
    'is-drop-before': isCurrentTarget && typeDropPosition.value === 'before',
    'is-drop-after': isCurrentTarget && typeDropPosition.value === 'after',
  }
}

function typeManagerItemClass(item) {
  const isCurrentTarget = typeDropTargetValue.value === item.value

  return {
    'is-dragging': draggingTypeValue.value === item.value,
    'is-drop-before': isCurrentTarget && typeDropPosition.value === 'before',
    'is-drop-after': isCurrentTarget && typeDropPosition.value === 'after',
  }
}

function resolveVerticalDropPosition(event) {
  const target = event.target.closest('.el-tree-node__content, .product-drop-zone, .drag-handle')
  if (!target) {
    return 'after'
  }

  const rect = target.getBoundingClientRect()
  const ratio = (event.clientY - rect.top) / Math.max(rect.height, 1)

  return ratio <= 0.5 ? 'before' : 'after'
}

function canDropCategoryTreeNode(draggingData, dropData, position) {
  if (!canDragCategoryTree.value) {
    return false
  }

  const draggingId = Number(draggingData?.id || 0)
  const dropId = Number(dropData?.id || 0)
  if (!draggingId || !dropId || draggingId === dropId) {
    return false
  }

  const draggingLevel = Number(draggingData?.level || 0)
  const dropLevel = Number(dropData?.level || 0)

  if (draggingLevel === 1) {
    return dropLevel === 1 && ['before', 'after'].includes(position)
  }

  if (draggingLevel === 2) {
    if (dropLevel === 1) {
      return position === 'inner'
    }

    return dropLevel === 2 && ['before', 'after'].includes(position)
  }

  return false
}

function resolveCategoryTreeDropPosition(draggingData, dropData, event) {
  const draggingLevel = Number(draggingData?.level || 0)
  const dropLevel = Number(dropData?.level || 0)

  if (!draggingLevel || !dropLevel) {
    return ''
  }

  if (draggingLevel === 1 && dropLevel === 1) {
    return resolveVerticalDropPosition(event)
  }

  if (draggingLevel === 2 && dropLevel === 1) {
    return 'inner'
  }

  if (draggingLevel === 2 && dropLevel === 2) {
    return resolveVerticalDropPosition(event)
  }

  return ''
}

function handleCategoryTreeDragStart(data, event) {
  if (!canDragCategoryTree.value) {
    return
  }

  draggingCategoryId.value = Number(data?.id || 0)
  categoryDropTargetId.value = 0
  categoryDropPosition.value = ''

  if (draggingCategoryId.value && event?.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(draggingCategoryId.value))
  }
}

function handleCategoryTreeItemDragOver(dropData, event) {
  if (!canDragCategoryTree.value) {
    return
  }

  const draggingData = categoryNodeMap.value.get(draggingCategoryId.value) || null
  if (!draggingData || !dropData) {
    return
  }

  const position = resolveCategoryTreeDropPosition(draggingData, dropData, event)

  if (!position || !canDropCategoryTreeNode(draggingData, dropData, position)) {
    categoryDropTargetId.value = 0
    categoryDropPosition.value = ''
    return
  }

  draggingCategoryId.value = Number(draggingData.id || 0)
  categoryDropTargetId.value = Number(dropData.id || 0)
  categoryDropPosition.value = position
  event.dataTransfer.dropEffect = 'move'
}

async function moveCategoryByDrag(dropData, dropType) {
  const draggingData = categoryNodeMap.value.get(draggingCategoryId.value) || null

  if (!draggingData || !dropData) {
    return
  }

  const payload = {
    category_id: Number(draggingData.category_id || draggingData.id),
    target_parent_id: null,
    target_product_type: filters.product_type || null,
    reference_category_id: null,
    position: 'append',
  }

  if (Number(draggingData.level) === 1) {
    payload.reference_category_id = Number(dropData.category_id || dropData.id)
    payload.position = dropType === 'before' ? 'before' : 'after'
  } else if (dropType === 'inner') {
    payload.target_parent_id = Number(dropData.category_id || dropData.id)
    payload.position = 'append'
  } else {
    payload.target_parent_id = Number(dropData.parent_id || 0) || null
    payload.reference_category_id = Number(dropData.category_id || dropData.id)
    payload.position = dropType === 'before' ? 'before' : 'after'
  }

  categoryLoading.value = true
  try {
    await productApi.reorderCategory(payload)
    ElMessage.success('分类位置已更新')
    await Promise.all([loadCategories(), loadProducts()])
  } finally {
    categoryLoading.value = false
    resetCategoryDragState()
  }
}

function handleCategoryTreeNodeDragOver(data, event) {
  if (draggingCategoryId.value) {
    handleCategoryTreeItemDragOver(data, event)
    return
  }

  handleProductTreeDragOver(data, event)
}

async function handleCategoryTreeNodeDrop(data, event) {
  if (draggingCategoryId.value) {
    const dropType = categoryDropTargetId.value === Number(data?.id || 0)
      ? categoryDropPosition.value || resolveCategoryTreeDropPosition(
          categoryNodeMap.value.get(draggingCategoryId.value) || null,
          data,
          event
        )
      : ''

    if (!dropType) {
      resetCategoryDragState()
      return
    }

    await moveCategoryByDrag(data, dropType)
    return
  }

  await handleProductTreeDrop(data)
}

function handleCategoryTreeDragEnd() {
  resetCategoryDragState()
}

function resolveProductDropPosition(event) {
  const rect = event.currentTarget.getBoundingClientRect()

  return event.clientY - rect.top < rect.height / 2 ? 'before' : 'after'
}

function handleProductDragStart(row, event) {
  draggingProductId.value = Number(row.id)
  productDropTargetId.value = 0
  productDropPosition.value = ''
  productDropCategoryId.value = 0
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(row.id))
}

function handleProductRowDragOver(row, event) {
  if (!draggingProductId.value || Number(row.id) === Number(draggingProductId.value)) {
    return
  }

  productDropTargetId.value = Number(row.id)
  productDropPosition.value = resolveVerticalDropPosition(event)
  productDropCategoryId.value = 0
  event.dataTransfer.dropEffect = 'move'
}

function productDropZoneClass(row) {
  const isCurrentTarget = Number(productDropTargetId.value) === Number(row?.id)
  const isSrcRow = Number(draggingProductId.value) === Number(row?.id)

  return {
    'product-drop-zone': true,
    'is-drop-before': isCurrentTarget && productDropPosition.value === 'before',
    'is-drop-after': isCurrentTarget && productDropPosition.value === 'after',
    'is-dragging-src': isSrcRow && draggingProductId.value !== 0,
  }
}

async function moveProductByDrag(payload) {
  productLoading.value = true
  try {
    await productApi.reorderProduct(payload)
    ElMessage.success('商品位置已更新')
    await Promise.all([loadCategories(), loadProducts()])
  } finally {
    productLoading.value = false
    resetProductDragState()
  }
}

async function handleProductRowDrop(row, event) {
  if (!draggingProductId.value || Number(row.id) === Number(draggingProductId.value)) {
    resetProductDragState()
    return
  }

  const position = productDropPosition.value || resolveProductDropPosition(event)
  await moveProductByDrag({
    product_id: Number(draggingProductId.value),
    target_category_id: Number(row.category_id || 0) || undefined,
    reference_product_id: Number(row.id),
    position,
  })
}

function handleProductTreeDragOver(category, event) {
  if (!draggingProductId.value || !canAssignProductToCategory(category)) {
    return
  }

  productDropTargetId.value = 0
  productDropPosition.value = ''
  productDropCategoryId.value = Number(category.id)
  event.dataTransfer.dropEffect = 'move'
}

async function handleProductTreeDrop(category) {
  if (!draggingProductId.value) {
    return
  }

  if (!canAssignProductToCategory(category)) {
    ElMessage.warning('请将商品拖到最终可售菜单下')
    resetProductDragState()
    return
  }

  await moveProductByDrag({
    product_id: Number(draggingProductId.value),
    target_category_id: Number(category.category_id || 0) || undefined,
    position: 'append',
  })
}

function handleProductDragEnd() {
  resetProductDragState()
}

function categoryTreeNodeMainClass(data) {
  const isGroupCurrentTarget = Number(categoryDropTargetId.value) === Number(data.id)
  const isProductCurrentTarget = Number(productDropCategoryId.value) === Number(data.id) && canAssignProductToCategory(data)

  return {
    'is-group-drop-inner': isGroupCurrentTarget && categoryDropPosition.value === 'inner',
    'is-group-drop-before': isGroupCurrentTarget && categoryDropPosition.value === 'before',
    'is-group-drop-after': isGroupCurrentTarget && categoryDropPosition.value === 'after',
    'is-product-drop-target': isProductCurrentTarget,
  }
}

async function handleProductTypeChange(value) {
  if (!value || value === filters.product_type) {
    return
  }

  filters.product_type = value
  filters.keyword = ''
  filters.status = ''
  filters.category_id = ''
  activeTreeNodeId.value = 0
  categoryKeyword.value = ''
  expandedCategoryKeys.value = []
  page.value = 1
  await loadCategories({ autoSelectFirst: true })
  await loadProducts()
}

async function refreshTypeCatalog() {
  await loadProductTypes()
  await Promise.all([loadCategories(), loadProducts()])
}

function resetTypeForm() {
  editingTypeValue.value = ''
  typeForm.label = ''
  typeForm.icon = ''
  typeIconPopoverVisible.value = false
}

function openTypeManagerDialog(mode = '') {
  resetTypeDragState()
  typeManagerDialogVisible.value = true

  if (mode === 'create' || !editingTypeValue.value) {
    resetTypeForm()
  }
}

function editType(type) {
  editingTypeValue.value = type.value
  typeForm.label = type.label
  typeForm.icon = type.icon || ''
  typeManagerDialogVisible.value = true
}

async function handleSubmitType() {
  const label = String(typeForm.label || '').trim()
  if (!label) {
    ElMessage.warning('请输入种类名称')
    return
  }

  typeSubmitting.value = true
  try {
    if (editingTypeValue.value) {
      await productApi.updateType(editingTypeValue.value, { label, icon: typeForm.icon || '' })
      ElMessage.success('种类已更新')
    } else {
      await productApi.createType({ label, icon: typeForm.icon || '' })
      ElMessage.success('种类已创建')
    }

    resetTypeForm()
    await refreshTypeCatalog()
  } finally {
    typeSubmitting.value = false
  }
}

async function handleToggleTypeHidden(type) {
  typeSubmitting.value = true
  try {
    const nextHidden = !type.is_hidden
    await productApi.updateType(type.value, {
      label: type.label,
      icon: type.icon || '',
      is_hidden: nextHidden,
    })
    ElMessage.success(nextHidden ? '种类已隐藏' : '种类已显示')
    await refreshTypeCatalog()
  } finally {
    typeSubmitting.value = false
  }
}

async function handleDeleteType(type) {
  await productApi.deleteType(type.value)
  ElMessage.success('种类已删除')

  if (filters.product_type === type.value) {
    filters.product_type = ''
    filters.category_id = ''
    activeTreeNodeId.value = 0
  }

  resetTypeForm()
  await refreshTypeCatalog()
}

function resolveTypeIconComponent(iconName) {
  const normalizedIconName = String(iconName || '').trim()
  if (!normalizedIconName) {
    return null
  }

  return productTypeIconMap[normalizedIconName] || null
}

function selectTypeIcon(iconName) {
  typeForm.icon = iconName
  typeIconPopoverVisible.value = false
}

function clearTypeIcon() {
  typeForm.icon = ''
  typeIconPopoverVisible.value = false
}

async function handleSupplierChange(value) {
  const normalizedSupplierId = Number(value || 0)
  productForm.supplier_id = normalizedSupplierId > 0 ? normalizedSupplierId : null
  productForm.supplier_product_id = null
  productForm.config_options = []
  syncProvisionModuleWithSupplier(productForm.supplier_id)
  supplierProductGroups.value = []

  if (productForm.supplier_id && selectedSupplierCanSync.value) {
    await loadSupplierProducts(productForm.supplier_id, { silent: true })
  }
}

async function handleSupplierProductChange(value) {
  const normalizedProductId = Number(value || 0)
  productForm.supplier_product_id = normalizedProductId > 0 ? normalizedProductId : null

  if (!productForm.supplier_product_id) {
    productForm.config_options = []
    return
  }
  await syncSelectedSupplierProductData({ silent: true, warnOnConfigFailure: true })
}

async function syncSupplierProducts() {
  if (!productForm.supplier_id) {
    ElMessage.warning('请先选择供应商')
    return
  }

  if (!selectedSupplierCanSync.value) {
    ElMessage.warning('当前供应商接口配置不完整，无法同步上游商品')
    return
  }

  await loadSupplierProducts(productForm.supplier_id, { syncing: true, silent: true })

  if (productForm.supplier_product_id) {
    await syncSelectedSupplierProductData({ silent: true })
  }

  ElMessage.success('供应商商品已同步')
}

function openCategoryDialog(category = null) {
  editingCategory.value = category
  Object.assign(categoryForm, createDefaultCategoryForm(), {
    product_type: category?.product_type ?? (filters.product_type || productTypes.value[0]?.value || ''),
    parent_id: category?.parent_id ?? null,
    name: category?.name ?? '',
    slogan: category?.slogan ?? '',
    sort_order: Number(category?.sort_order ?? 0),
    is_visible: Number(category?.is_visible ?? 1),
  })
  categoryDialogVisible.value = true
}

function openChildCategoryDialog(parentCategory = null) {
  editingCategory.value = null
  Object.assign(categoryForm, createDefaultCategoryForm(), {
    product_type: parentCategory?.product_type ?? (filters.product_type || productTypes.value[0]?.value || ''),
    parent_id: parentCategory?.id ?? null,
  })
  categoryDialogVisible.value = true
}

function applyProductForm(product = null) {
  Object.assign(productForm, createDefaultProductForm(), {
    category_id: product?.category_id ?? null,
    product_type: product?.product_type ?? (filters.product_type || productTypes.value[0]?.value || 'other'),
    remark: String(product?.remark ?? ''),
    pricing: sanitizePricing(product?.pricing),
    setup_fee: Number(product?.setup_fee ?? 0),
    stock: Number(product?.stock ?? -1),
    status: Number(product?.status ?? 1),
    sort_order: Number(product?.sort_order ?? 0),
    provision_module: product?.provision_module ?? '',
    auto_setup: Number(product?.auto_setup ?? 0),
    supplier_id: product?.supplier_id ?? null,
    supplier_product_id: product?.supplier_product_id ?? null,
    config_options: normalizeConfigOptions(product?.config_options),
    purchase_requires: {
      provision_hostname: {
        mode: product?.purchase_requires?.provision_hostname?.mode || 'system',
        value: product?.purchase_requires?.provision_hostname?.value || '',
        length: Number(product?.purchase_requires?.provision_hostname?.length || 12),
      },
    },
  })

  syncProvisionModuleWithSupplier(product?.supplier_id ?? null)
}

function resolveSelectedCategoryId(groupPublicId) {
  const normalizedId = Number(groupPublicId || 0)
  if (!normalizedId) {
    return null
  }

  const matched = assignableCategoryOptions.value.find((item) => Number(item.id) === normalizedId)
  if (matched?.category_id) {
    return Number(matched.category_id)
  }

  const fallback = categoryOptions.value.find((item) => Number(item.id) === normalizedId)
  if (fallback?.category_id) {
    return Number(fallback.category_id)
  }

  return null
}

function syncCategoryTreeExpansion() {
  if (!categoryTreeRef.value?.getNode) {
    return
  }

  const expandedSet = new Set(expandedCategoryKeys.value)

  categoryTree.value.forEach((category) => {
    const treeNode = categoryTreeRef.value.getNode(`category-${category.id}`)
    if (!treeNode) {
      return
    }

    if (expandedSet.has(`category-${category.id}`)) {
      treeNode.expand?.()
    } else {
      treeNode.collapse?.()
    }
  })
}

async function ensureSupplierOptionsLoaded() {
  if (suppliers.value.length > 0) return
  await loadSupplierOptions()
}

async function activateProductDrawerTab(tabKey) {
  productDrawerTab.value = tabKey

  if (tabKey !== 'automation' && tabKey !== 'config') {
    return
  }

  try {
    await ensureSupplierOptionsLoaded()

    if (
      tabKey === 'automation'
      && productForm.supplier_id
      && selectedSupplierCanSync.value
      && supplierProductGroups.value.length === 0
    ) {
      await loadSupplierProducts(productForm.supplier_id, { silent: true })
    }
  } catch (error) {
    ElMessage.error(error?.message || '供应商数据加载失败')
  }
}

async function openProductDialog(product = null) {
  editingProduct.value = product
  productDrawerTab.value = 'details'
  supplierProductGroups.value = []
  editingConfigOptionIndex.value = -1
  configOptionDialogVisible.value = false
  productDialogVisible.value = true
  productDialogLoading.value = false
  applyProductForm(null)

  if (!product?.id) {
    editingProduct.value = null
    return
  }

  productDialogLoading.value = true

  try {
    const res = await productApi.detail(product.id)
    const detailProduct = res.data || null

    if (!detailProduct) {
      throw new Error('商品详情为空')
    }

    editingProduct.value = detailProduct
    applyProductForm(detailProduct)
  } catch {
    productDialogVisible.value = false
  } finally {
    productDialogLoading.value = false
  }
}

function normalizePricing(pricing) {
  const filteredPricing = sanitizePricing(pricing)
  const monthlyAmount = resolveMonthlyAmountFromPricing(filteredPricing)
  if (monthlyAmount === null || monthlyAmount <= 0) {
    return {}
  }

  return buildDerivedNumericPricingFromMonthly(monthlyAmount)
}

async function handleSubmitCategory() {
  try {
    await categoryFormRef.value?.validate()
  } catch {
    return
  }

  categorySubmitting.value = true
  try {
    const payload = {
      product_type: categoryForm.product_type || null,
      parent_id: categoryForm.parent_id || null,
      name: categoryForm.name,
      slogan: categoryForm.slogan,
      sort_order: categoryForm.sort_order,
      is_visible: categoryForm.is_visible,
    }

    if (editingCategory.value) {
      await productApi.updateCategory(editingCategory.value.id, payload)
      ElMessage.success('分类已更新')
    } else {
      await productApi.createCategory(payload)
      ElMessage.success('分类已创建')
    }

    categoryDialogVisible.value = false
    await Promise.all([loadSummary(), loadCategories(), loadProducts()])
  } finally {
    categorySubmitting.value = false
  }
}

async function handleDeleteCategory(category) {
  await productApi.deleteCategory(category.id)
  ElMessage.success('分类已删除')
  await Promise.all([loadSummary(), loadCategories(), loadProducts()])
}

async function handleToggleCategoryVisible(category) {
  const nextVisible = Number(category.is_visible) === 1 ? 0 : 1
  await productApi.updateCategory(category.id, {
    product_type: category.product_type || null,
    parent_id: category.parent_id || null,
    name: category.name,
    slogan: category.slogan || '',
    sort_order: category.sort_order ?? 0,
    is_visible: nextVisible,
  })
  ElMessage.success(nextVisible ? '分类已显示' : '分类已隐藏')
  await loadCategories()
}

async function handleCategoryAction(command, category) {
  if (command === 'edit') {
    openCategoryDialog(category)
    return
  }

  if (command === 'add-child') {
    openChildCategoryDialog(category)
    return
  }

  if (command === 'toggle-visible') {
    await handleToggleCategoryVisible(category)
    return
  }

  if (command === 'delete') {
    try {
      await ElMessageBox.confirm(
        Number(category.level) === 1 ? '确认删除该分类及其所有子分类？' : '确认删除该子分类？',
        '删除确认',
        { confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning' }
      )
      await handleDeleteCategory(category)
    } catch {
      // cancelled
    }
  }
}

async function handleSubmitProduct() {
  try {
    await productFormRef.value?.validate()
  } catch {
    return
  }

  const pricing = normalizePricing(productForm.pricing)
  if (Object.keys(pricing).length === 0) {
    ElMessage.error('请填写大于 0 的月付价格')
    return
  }

  let configOptions = []
  try {
    configOptions = serializeConfigOptions()
  } catch (error) {
    ElMessage.error(error.message)
    return
  }

  productSubmitting.value = true
  try {
    const resolvedCategoryId = resolveSelectedCategoryId(productForm.category_id)
    const payload = {
      category_id: resolvedCategoryId,
      product_type: productForm.product_type,
      remark: String(productForm.remark || '').trim(),
      pricing,
      setup_fee: productForm.setup_fee,
      config_options: configOptions,
      purchase_requires: productForm.purchase_requires,
      stock: productForm.stock,
      status: productForm.status,
      sort_order: productForm.sort_order,
      provision_module: productForm.provision_module,
      auto_setup: productForm.auto_setup,
      supplier_id: productForm.supplier_id,
      supplier_product_id: productForm.supplier_product_id,
    }

    if (editingProduct.value) {
      await productApi.update(editingProduct.value.id, payload)
      ElMessage.success('商品已更新')
    } else {
      await productApi.create(payload)
      ElMessage.success('商品已创建')
    }

    productDialogVisible.value = false
    await Promise.all([loadSummary(), loadProducts()])
  } finally {
    productSubmitting.value = false
  }
}

// 商品拥有者
const ownersDrawerVisible = ref(false)
const ownersLoading = ref(false)
const ownersProduct = ref(null)
const ownersList = ref([])
const ownersSummary = ref(null)
const ownersTotal = ref(0)
const ownersPage = ref(1)
const ownersPageSize = ref(20)
const ownersKeyword = ref('')

async function openOwnersDrawer(product) {
  ownersProduct.value = product
  ownersKeyword.value = ''
  ownersPage.value = 1
  ownersList.value = []
  ownersSummary.value = null
  ownersDrawerVisible.value = true
  await loadOwners(1)
}

async function loadOwners(page) {
  if (!ownersProduct.value) return
  if (page) ownersPage.value = page
  ownersLoading.value = true
  try {
    const res = await productApi.owners(ownersProduct.value.id, {
      page: ownersPage.value,
      page_size: ownersPageSize.value,
      keyword: ownersKeyword.value || undefined,
    })
    ownersList.value = res.data.list || []
    ownersSummary.value = res.data.summary || null
    ownersTotal.value = res.data.total || 0
  } finally {
    ownersLoading.value = false
  }
}

function handleProductSelectionChange(rows) {
  selectedProductRows.value = Array.isArray(rows) ? rows : []
}

function openBatchCategoryDialog(rows = null) {
  const targetRows = Array.isArray(rows) && rows.length ? rows : selectedProductRows.value

  if (!targetRows.length) {
    ElMessage.warning('请先选择商品')
    return
  }

  batchCategoryTargetRows.value = targetRows
  batchCategoryForm.category_id = null
  batchCategoryDialogVisible.value = true
}

async function handleSubmitBatchCategory() {
  if (!batchCategoryTargetRows.value.length) {
    return
  }

  const targetCategoryId = resolveSelectedCategoryId(batchCategoryForm.category_id)
  if (!targetCategoryId) {
    ElMessage.warning('请选择目标分类')
    return
  }

  batchCategorySubmitting.value = true
  try {
    const res = await productApi.batchUpdateCategory({
      product_ids: batchCategoryTargetRows.value.map((row) => Number(row.id)),
      target_category_id: targetCategoryId,
    })

    const updatedCount = Number(res?.data?.updated_count ?? batchCategoryTargetRows.value.length)
    const targetLabel = res?.data?.target_category_full_name || res?.data?.target_category_name || '目标分类'

    if (updatedCount > 0) {
      ElMessage.success(`已将 ${updatedCount} 个商品移入「${targetLabel}」`)
    } else {
      ElMessage.success(`所选商品已在「${targetLabel}」下`)
    }

    batchCategoryDialogVisible.value = false
    await Promise.all([loadCategories(), loadProducts()])
  } finally {
    batchCategorySubmitting.value = false
  }
}

function normalizeProvisionHostnameRule(rule = {}) {
  const mode = ['system', 'fixed', 'prefix'].includes(rule?.mode) ? rule.mode : 'system'
  const value = String(rule?.value || '').trim()
  const rawLength = Number(rule?.length || 12)
  const length = Number.isFinite(rawLength) ? Math.min(63, Math.max(4, rawLength)) : 12

  return {
    mode,
    value,
    length,
  }
}

function isSameProvisionHostnameRule(left = {}, right = {}) {
  const current = normalizeProvisionHostnameRule(left)
  const target = normalizeProvisionHostnameRule(right)

  return current.mode === target.mode
    && current.value === target.value
    && Number(current.length) === Number(target.length)
}

function openProvisionHostnameDialog(product = null) {
  const rows = product ? [product] : selectedProductRows.value

  if (!rows.length) {
    ElMessage.warning('请先选择商品')
    return
  }

  provisionHostnameTargetRows.value = rows
  const currentRule = normalizeProvisionHostnameRule(rows[0]?.provision_hostname || {})
  provisionHostnameHasMixedRules.value = rows.some((row) => !isSameProvisionHostnameRule(row?.provision_hostname || {}, currentRule))
  provisionHostnameForm.mode = currentRule.mode
  provisionHostnameForm.value = currentRule.value
  provisionHostnameForm.length = currentRule.length
  provisionHostnameDialogVisible.value = true
}

async function handleSubmitProvisionHostname() {
  if (!provisionHostnameTargetRows.value.length) {
    return
  }

  if (provisionHostnameForm.mode !== 'system' && !provisionHostnameForm.value.trim()) {
    ElMessage.warning(provisionHostnameForm.mode === 'fixed' ? '请输入固定主机名' : '请输入主机名前缀')
    return
  }

  provisionHostnameSubmitting.value = true
  try {
    await productApi.batchUpdateProvisionHostname({
      product_ids: provisionHostnameTargetRows.value.map((row) => Number(row.id)),
      provision_hostname: {
        mode: provisionHostnameForm.mode,
        value: provisionHostnameForm.value.trim(),
        length: Number(provisionHostnameForm.length || 12),
      },
    })
    ElMessage.success('商品开通主机名规则已更新')
    provisionHostnameDialogVisible.value = false
    await loadProducts()
  } finally {
    provisionHostnameSubmitting.value = false
  }
}

function handleCatalogProductAction(command, row) {
  if (command === 'edit') {
    openProductDialog(row)
  } else {
    handleProductAction(command, row)
  }
}

async function handleProductAction(command, product) {
  if (command === 'provision-hostname') {
    openProvisionHostnameDialog(product)
    return
  }

  if (command === 'owners') {
    await openOwnersDrawer(product)
    return
  }

  if (command === 'enable' || command === 'disable') {
    await handleToggleProductStatus(product)
    return
  }

  if (command === 'delete') {
    await handleDeleteProduct(product)
  }
}

async function handleToggleProductStatus(product) {
  await productApi.toggleStatus(product.id)
  ElMessage.success(`商品已${product.status === 1 ? '下架' : '上架'}`)
  await Promise.all([loadSummary(), loadProducts()])
}

async function handleDeleteProduct(product) {
  await productApi.delete(product.id)
  ElMessage.success('商品已删除')

  if (page.value > 1 && products.value.length <= 1) {
    page.value -= 1
  }

  await Promise.all([loadSummary(), loadProducts()])
}

onMounted(loadData)
</script>

<style scoped lang="scss">
.products-page {
  position: relative;
  min-height: 100%;
  gap: 14px;
}

.catalog-kind-panel {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 14px 18px;
  border: 1px solid $border-color;
  border-radius: 12px;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.catalog-kind-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.catalog-kind-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.catalog-kind-meta strong {
  color: $text-color-primary;
  font-size: 16px;
}

.catalog-kind-meta p {
  margin: 0;
  color: $text-color-secondary;
  font-size: 12px;
}

.catalog-kind-actions,
.catalog-kind-chips {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.catalog-kind-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  min-height: 34px;
  padding: 0 14px;
  border: 1px solid $border-color;
  border-radius: 8px;
  background: $bg-color-card;
  color: $text-color-primary;
  cursor: pointer;
  user-select: none;
  transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.catalog-kind-chip__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  color: inherit;
  font-size: 14px;
}

.catalog-kind-chip span {
  font-size: 13px;
  font-weight: 600;
}

.catalog-kind-chip-flag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba($color-warning, 0.12);
  color: $color-warning;
  font-size: 11px;
  font-style: normal;
  font-weight: 600;
}

.catalog-kind-chip small {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 999px;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 12px;
}

.catalog-kind-chip:hover,
.catalog-kind-chip.active {
  border-color: $color-primary-border;
  background: $color-primary-soft;
  color: $color-primary;
}

.catalog-kind-chip.active small {
  background: rgba($color-primary, 0.12);
  color: $color-primary;
}

.catalog-kind-chip.is-hidden {
  border-style: dashed;
  color: $text-color-secondary;
}

.catalog-kind-chip.is-hidden:not(.active) {
  background: rgba($text-color-placeholder, 0.06);
}

.catalog-kind-chip--ghost {
  border-style: dashed;
}

.drag-feedback-strip {
  display: inline-flex;
  align-items: center;
  min-height: 34px;
  padding: 0 12px;
  border: 1px dashed $color-primary-border;
  border-radius: 10px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
}

.drag-feedback-strip--tree,
.drag-feedback-strip {
  width: 100%;
}

.group-panel,
.product-panel {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  min-width: 0;
  overflow: hidden;
  border-radius: 12px;
  border: 1px solid $border-color;
  background: $bg-color-card;
  box-shadow: $shadow-sm;
}

.group-panel :deep(.el-card__header),
.product-panel :deep(.el-card__header) {
  border-bottom: 0;
}

.group-panel :deep(.el-card__header) {
  padding: 12px 14px 0;
}

.product-panel :deep(.el-card__header) {
  padding: 14px 16px 0;
}

.group-panel :deep(.el-card__body) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
  overflow: hidden;
  padding: 10px 12px 12px;
}

.product-panel :deep(.el-card__body) {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
  overflow: hidden;
  padding: 14px 16px 16px;
}

.catalog-layout {
  display: grid;
  grid-template-columns: 300px minmax(0, 1fr);
  gap: 14px;
  align-items: start;
  width: 100%;
  min-width: 0;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.panel-header-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.panel-header-meta strong {
  color: $text-color-primary;
  font-size: 15px;
  font-weight: 600;
}

.panel-header-meta span {
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.group-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 28px;
}

.group-panel-header-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;

  strong {
    color: $text-color-primary;
    font-size: 15px;
    font-weight: 600;
    white-space: nowrap;
  }
}

.group-panel-header-count {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.group-panel-header-actions,
.product-panel-actions {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  flex-shrink: 0;
}

.group-panel-header-actions :deep(.el-button--small) {
  padding: 6px 10px;
  min-height: 28px;
  font-size: 12px;
}

.group-panel-icon-button {
  appearance: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: $text-color-placeholder;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover:not(:disabled) {
    border-color: $border-color;
    background: $bg-color-hover;
    color: $color-primary;
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.6;
  }

  &.is-loading .el-icon {
    animation: group-panel-icon-spin 0.9s linear infinite;
  }
}

@keyframes group-panel-icon-spin {
  to { transform: rotate(360deg); }
}

.group-sidebar {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 10px;
  flex: 1;
  min-height: 0;
  min-width: 0;
  overflow: hidden;
}

.group-sidebar-toolbar {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.group-sidebar-search {
  width: 100%;
}

.group-tree-shell {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
}

.group-tree-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  overflow-x: hidden;
  padding-right: 4px;
}

.group-tree {
  background: transparent;
}

.group-tree :deep(.el-tree-node) {
  overflow: hidden;
}

.group-tree :deep(.el-tree-node__content) {
  overflow: hidden;
  height: auto;
  min-height: 40px;
  margin-bottom: 2px;
  padding: 2px 4px 2px 2px;
  border-radius: 8px;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.group-tree :deep(.el-tree-node__content:hover) {
  background: $bg-color-hover;
}

.group-tree :deep(.el-tree-node.is-current > .el-tree-node__content) {
  background: $color-primary-soft;
  box-shadow: inset 0 0 0 1px $color-primary-border;
}

.group-tree-node {
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

.group-tree-node-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 22px;
  border: none;
  border-radius: 6px;
  background: transparent;
  color: $text-color-placeholder;
  cursor: grab;
  flex-shrink: 0;
  opacity: 0;
  transition: opacity 0.15s ease, background-color 0.2s ease, color 0.2s ease;
}

.group-tree :deep(.el-tree-node__content:hover) .group-tree-node-drag-handle,
.group-tree :deep(.el-tree-node.is-current > .el-tree-node__content) .group-tree-node-drag-handle {
  opacity: 1;
}

.group-tree-node-drag-handle:hover:not(:disabled) {
  background: rgba($color-primary, 0.08);
  color: $color-primary;
}

.group-tree-node-drag-handle:disabled {
  cursor: not-allowed;
  opacity: 0;
}

.group-tree-node-main {
  display: flex;
  flex: 1;
  align-items: center;
  gap: 8px;
  min-width: 0;
  min-height: 32px;
  padding: 4px 8px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
}

.group-tree-node-main.is-group-drop-inner {
  border-color: $color-primary-border;
  background: rgba($color-primary, 0.1);
}

.group-tree-node-main.is-group-drop-before {
  box-shadow: inset 0 2px 0 $color-primary;
}

.group-tree-node-main.is-group-drop-after {
  box-shadow: inset 0 -2px 0 $color-primary;
}

.group-tree-node-main.is-product-drop-target {
  border-style: dashed;
  border-color: rgba($color-primary, 0.45);
  background: rgba($color-primary, 0.08);
}

.group-tree-node-label {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.5;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.group-tree-node-note {
  flex-shrink: 0;
  color: $text-color-placeholder;
  font-size: 11px;
  line-height: 1.5;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  transition: opacity 0.15s ease;
}

.group-tree :deep(.el-tree-node__content:hover) .group-tree-node-note,
.group-tree :deep(.el-tree-node.is-current > .el-tree-node__content) .group-tree-node-note {
  opacity: 0;
  pointer-events: none;
}

.group-tree-node-state {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  height: 18px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba($color-warning, 0.12);
  color: $color-warning;
  font-size: 10px;
  font-weight: 600;
}

.group-tree-node.is-hidden .group-tree-node-main {
  background: rgba($text-color-placeholder, 0.05);
}

.group-tree-node.is-hidden .group-tree-node-label {
  color: $text-color-secondary;
}

.group-tree-node-actions {
  position: absolute;
  top: 50%;
  right: 4px;
  transform: translateY(-50%);
  display: inline-flex;
  align-items: center;
  gap: 4px;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.2s ease;
  z-index: 1;
}

.group-tree :deep(.el-tree-node__content:hover) .group-tree-node-actions,
.group-tree :deep(.el-tree-node.is-current > .el-tree-node__content) .group-tree-node-actions {
  opacity: 1;
  pointer-events: auto;
}

.group-tree-node-actions :deep(.el-button) {
  margin-left: 0;
}

.group-tree-node-more {
  appearance: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 1px solid transparent;
  border-radius: 8px;
  background: transparent;
  color: $text-color-placeholder;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    border-color: $border-color;
    background: $bg-color-hover;
    color: $text-color-primary;
  }

  &:active {
    background: $bg-color-soft;
  }
}

.group-tree-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 20px 12px 8px;
  text-align: center;
}

.group-tree-empty strong {
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.group-tree-empty p {
  margin: 0;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.product-filters {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.selected-group,
.active-filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.selected-group span,
.active-filters-label {
  color: $text-color-secondary;
  font-size: 12px;
}

.inline-clear-button {
  padding: 0;
  border: none;
  background: transparent;
  color: $color-primary;
  font-size: 12px;
  cursor: pointer;
}

.inline-clear-button:hover {
  color: $color-primary-hover;
}

.products-page :deep(.el-form-item__label) {
  color: $text-color-secondary !important;
}

.products-page :deep(.el-input__wrapper),
.products-page :deep(.el-select__wrapper),
.products-page :deep(.el-textarea__inner),
.products-page :deep(.el-input-number .el-input__wrapper) {
  border-radius: 10px;
}

.products-page :deep(.el-switch__label) {
  color: $text-color-secondary;
}

.products-page :deep(.el-pagination) {
  --el-pagination-button-bg-color: #{$bg-color-card};
  --el-pagination-button-disabled-bg-color: #{$bg-color-soft};
  --el-pagination-hover-color: #{$color-primary};
}

.toolbar-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.search-grid {
  display: grid;
  grid-template-columns: minmax(260px, 1.8fr) minmax(150px, 0.9fr) auto;
  align-items: center;
  gap: 10px;
}

.search-field {
  width: 100%;
}

.search-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.toolbar-foot {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  min-width: 0;
  margin-top: 8px;
  padding-top: 12px;
  border-top: 1px solid $divider-color;
  flex-wrap: wrap;
}

.footer-tip {
  font-size: 12px;
  color: $text-color-secondary;
  line-height: 1.5;
}

.table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: clamp(240px, 34vh, 360px);
  padding: 20px 0;
  color: $text-color-secondary;
}

.table-empty strong {
  font-size: 15px;
  color: $text-color-primary;
}

.table-empty p {
  color: $text-color-secondary;
  line-height: 1.6;
  font-size: 12px;
}

.table-empty-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
}

.action-toolbar {
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: nowrap;
  white-space: nowrap;
}

.action-toolbar :deep(.el-button) {
  margin-left: 0;
  min-height: auto;
}

.mobile-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border: 1px solid #e5eaf3;
  border-radius: 8px;
  background: #fff;
  color: #5b6b82;
  cursor: pointer;
  transition: all 0.15s ease;

  &:active {
    background: #e8f1ff;
    border-color: #c9dbff;
    color: #165dff;
  }
}

.catalog-dialog :deep(.el-dialog__body) {
  padding-top: 12px;
}

.product-drawer :deep(.el-drawer) {
  width: min(920px, calc(100vw - 16px)) !important;
}

.product-drawer :deep(.el-drawer__header) {
  margin-bottom: 0;
  padding: 16px 18px 12px;
  border-bottom: 1px solid $divider-color;
}

.product-drawer :deep(.el-drawer__title) {
  font-size: 15px;
  font-weight: 700;
}

.product-drawer :deep(.el-drawer__body) {
  padding: 10px 14px 16px;
  overflow: auto;
}

.product-drawer :deep(.el-drawer__footer) {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 10px 14px 14px;
  border-top: 1px solid $divider-color;
}

.catalog-dialog :deep(.el-dialog__header) {
  padding: 18px 20px 0;
}

.catalog-dialog :deep(.el-dialog__footer) {
  padding: 0 20px 20px;
}

.dialog-intro {
  margin-bottom: 16px;
  padding: 14px 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
}

.dialog-intro strong {
  display: block;
  color: $text-color-primary;
  font-size: 15px;
}

.dialog-intro p {
  margin-top: 6px;
  color: $text-color-secondary;
  line-height: 1.6;
  font-size: 13px;
}

.dialog-section {
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.02);
}

.dialog-section + .dialog-section {
  margin-top: 14px;
}

.product-drawer .dialog-section {
  padding: 10px;
}

.product-drawer .dialog-section + .dialog-section {
  margin-top: 8px;
}

.dialog-section-header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: baseline;
  margin-bottom: 14px;
  flex-wrap: wrap;
}

.product-drawer .dialog-section-header {
  gap: 8px;
  margin-bottom: 8px;
  align-items: flex-start;
}

.dialog-section-header strong {
  color: $text-color-primary;
  font-size: 15px;
}

.dialog-section-header span {
  font-size: 12px;
  color: $text-color-placeholder;
}

.product-drawer-shell {
  min-height: 100%;
}

.product-drawer-tabs {
  position: sticky;
  top: -10px;
  z-index: 3;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin: -10px -14px 10px;
  padding: 10px 14px 8px;
  border-bottom: 1px solid $divider-color;
  background: rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(12px);
}

.product-drawer-tab {
  appearance: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 28px;
  padding: 0 10px;
  border: 1px solid $border-color;
  border-radius: 999px;
  background: $bg-color-card;
  color: $text-color-secondary;
  font-size: 11px;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  transition: all 0.2s ease;
}

.product-drawer-tab:hover {
  border-color: $border-color-strong;
  background: $bg-color-hover;
  color: $text-color-primary;
}

.product-drawer-tab.active {
  border-color: $color-primary-border;
  background: $color-primary-soft;
  color: $color-primary;
  box-shadow: inset 0 0 0 1px rgba($color-primary, 0.12);
}

.product-dialog-layout {
  display: block;
}

.product-dialog-main {
  min-width: 0;
}

.product-form :deep(.el-form-item) {
  margin-bottom: 8px;
}

.product-form :deep(.el-form-item__label) {
  padding-bottom: 4px;
  font-size: 12px;
  line-height: 1.4;
}

.product-form :deep(.el-switch__label) {
  font-size: 12px;
}

.product-form :deep(.el-textarea__inner) {
  min-height: 88px !important;
}

.dialog-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 2px 12px;
}

.dialog-span-2 {
  grid-column: span 2;
}

.price-section {
  margin: 8px 0 18px;
  padding: 16px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
}

.price-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px 12px;
}

.pricing-stack {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.pricing-field,
.pricing-setup-field {
  max-width: 100%;
}

.pricing-field-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
  min-width: 0;
}

.pricing-field-label {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
  min-width: 0;
}

.pricing-field-label strong {
  color: $text-color-primary;
  font-size: 13px;
}

.pricing-field-status {
  color: $text-color-placeholder;
  font-size: 11px;
}

.pricing-field-trigger {
  appearance: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  min-width: 44px;
  height: 24px;
  padding: 0 10px;
  border: 1px solid $color-primary-border;
  border-radius: 999px;
  background: $color-primary-soft;
  color: $color-primary;
  font-size: 12px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s ease;
}

.pricing-field-trigger:hover {
  background: rgba($color-primary, 0.16);
  color: $color-primary-hover;
}

.pricing-field-input {
  min-width: 0;
}

.pricing-setup-field {
  max-width: 220px;
  margin-top: 14px;
}

.supplier-product-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: center;
}

.supplier-field,
.supplier-product-cascader {
  width: 100%;
}

.supplier-option {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.supplier-option-name {
  min-width: 0;
  color: $text-color-primary;
}

.supplier-option-type {
  flex-shrink: 0;
  color: $text-color-secondary;
  font-size: 12px;
}

.supplier-product-node {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  width: 100%;
}

.supplier-product-name {
  min-width: 0;
  color: $text-color-primary;
}

.supplier-product-type {
  flex-shrink: 0;
  color: $text-color-secondary;
  font-size: 12px;
}

.config-editor {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-top: 4px;
}

.config-editor-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.config-editor-actions {
  display: inline-flex;
  gap: 8px;
  flex-wrap: wrap;
}

.config-editor-meta {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.config-editor-meta strong {
  color: $text-color-primary;
  font-size: 13px;
}

.config-editor-meta span {
  color: $text-color-placeholder;
  font-size: 12px;
}

.config-table-shell {
  overflow: auto;
  border: 1px solid $divider-color;
  border-radius: 12px;
}

.config-table :deep(.el-table__header-wrapper th) {
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-weight: 600;
}

.config-table :deep(.el-table__cell) {
  border-bottom-color: $divider-color;
}

.config-table-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 22px 12px;
  color: $text-color-secondary;
}

.config-table-empty strong {
  color: $text-color-primary;
  font-size: 14px;
}

.config-table-empty p {
  margin: 0;
  font-size: 12px;
  line-height: 1.6;
}

.config-table-empty-actions {
  display: inline-flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: center;
}

.config-name-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.config-name-cell strong {
  color: $text-color-primary;
  font-size: 12px;
  line-height: 1.5;
  word-break: break-all;
}

.config-name-note {
  color: $text-color-placeholder;
  font-size: 11px;
}

.config-parameter-cell {
  min-width: 0;
}

.config-parameter-text {
  display: -webkit-box;
  overflow: hidden;
  color: $text-color-primary;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-line;
  word-break: break-all;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  line-clamp: 3;
}

.config-parameter-empty,
.config-default-text {
  color: $text-color-secondary;
  font-size: 12px;
}

.config-description-text {
  margin: 0;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.config-order-input {
  width: 92px;
}

.config-table-actions {
  display: inline-flex;
  justify-content: flex-end;
  align-items: center;
  gap: 6px;
  width: 100%;
}

.config-dialog-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 2px 12px;
}

.config-dialog-span-2 {
  grid-column: span 2;
}

.config-dialog-span-3 {
  grid-column: span 3;
}

.config-type-cell {
  grid-column: span 1;
}

.config-advanced-cell {
  display: flex;
  align-items: center;
}

.config-dialog-switches {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 16px;
  padding-top: 4px;
}

/* 子项/区间定价表格 */
.sub-table-wrap {
  margin: 4px 0 8px;
  border: 1px solid var(--el-border-color-light, #e4e7ed);
  border-radius: 12px;
  overflow: hidden;
}

.sub-table-grid :deep(.el-table__header-wrapper th) {
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-weight: 600;
}

.sub-table-grid :deep(.el-table__cell) {
  border-bottom-color: $divider-color;
  vertical-align: top;
}

.sub-table-grid :deep(.cell) {
  width: 100%;
}

.sub-table-grid :deep(.el-input-number),
.sub-table-grid :deep(.el-input-number .el-input__wrapper) {
  width: 100%;
}

.sub-table-action,
.sub-table-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.sub-table-add-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-top: 1px dashed var(--el-border-color-light, #e4e7ed);
  background: var(--el-fill-color-extra-light, #fafafa);
}

.sub-table-add-hint {
  font-size: 12px;
  color: var(--el-text-color-placeholder, #c0c4cc);
}

.config-range-info {
  font-size: 12px;
  color: var(--el-text-color-secondary, #909399);
}

.config-spec-option {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
}

.config-spec-option-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.config-spec-option-main span {
  color: $text-color-primary;
}

.config-spec-option-main small {
  color: $text-color-secondary;
  line-height: 1.5;
  white-space: normal;
}

.config-spec-option strong {
  flex-shrink: 0;
  color: $color-primary;
  font-size: 12px;
}

.field-help {
  margin-top: 4px;
  color: $text-color-placeholder;
  font-size: 11px;
  line-height: 1.5;
}

.hostname-batch-meta {
  display: flex;
  align-items: center;
  gap: 8px 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
  color: $text-color-secondary;
  font-size: 12px;
}

.hostname-batch-warning {
  margin-bottom: 16px;
}

.hostname-rule-form {
  display: grid;
  gap: 8px;
}

.split-product-preview {
  display: grid;
  gap: 8px;
  min-height: 96px;
  max-height: 240px;
  margin-bottom: 14px;
  overflow: auto;
}

.split-product-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid $divider-color;
  border-radius: 8px;
  background: $bg-color-soft;
}

.split-product-item span {
  min-width: 0;
  overflow: hidden;
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.split-product-item small {
  flex: 0 0 auto;
  color: $text-color-placeholder;
  font-size: 12px;
}

.mobile-sidebar-toggle {
  display: none;
}

@media (max-width: 1280px) {
  .search-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .catalog-layout {
    grid-template-columns: 1fr;
  }

  .group-panel {
    position: static;
  }
}

@media (max-width: 900px) {
  .mobile-sidebar-toggle {
    display: inline-flex;
  }

  .mobile-sidebar-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    z-index: 1999;
  }

  .group-panel {
    position: fixed !important;
    top: 0;
    bottom: 0;
    left: -320px;
    width: 280px;
    height: 100dvh;
    max-height: 100dvh;
    z-index: 2000;
    margin: 0;
    border-radius: 0;
    border: none;
    box-shadow: 4px 0 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  }

  .group-panel :deep(.el-card__body) {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }

  .group-panel.is-mobile-open {
    transform: translateX(320px);
  }

  .group-sidebar {
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
  }

  .group-tree-shell {
    min-height: 0;
  }

  .panel-header {
    flex-wrap: wrap;
  }

  .product-toolbar,
  .toolbar-foot {
    flex-direction: column;
    align-items: flex-start;
  }

  .product-panel-actions {
    width: 100%;
  }

  .search-grid {
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }

  .dialog-grid,
  .config-dialog-grid,
  .price-grid,
  .price-grid-compact,
  .product-dialog-layout {
    grid-template-columns: 1fr;
  }

  .search-field-keyword {
    grid-column: 1 / -1;
  }

  // 选择器和按钮并排
  .search-field:not(.search-field-keyword) {
    grid-column: 1;
  }

  .search-actions {
    grid-column: 2;
    justify-content: flex-end;
    gap: 6px;

    :deep(.el-button) {
      padding: 5px 14px;
      font-size: 12px;
    }
  }

  .dialog-span-2,
  .config-dialog-span-2,
  .config-dialog-span-3 {
    grid-column: span 1;
  }

  .product-drawer :deep(.el-drawer) {
    width: min(100vw, calc(100vw - 8px)) !important;
  }

  .pricing-field-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .pricing-stack {
    grid-template-columns: 1fr;
  }

  .pricing-setup-field {
    max-width: 100%;
  }

  .type-manager-form {
    flex-direction: column;
    align-items: stretch;
  }

  .type-manager-form__main {
    grid-template-columns: 1fr;
  }

  .type-manager-form__actions {
    justify-content: flex-start;
  }

  .type-icon-field__row {
    justify-content: space-between;
  }

  .type-icon-picker {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .supplier-product-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 640px) {
  .catalog-kind-panel {
    padding: 10px 12px;
    gap: 8px;
  }

  .catalog-kind-head {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }

  .catalog-kind-actions {
    gap: 6px;
    width: 100%;

    :deep(.el-button) {
      flex: 1;
      padding: 5px 12px;
      font-size: 12px;
    }
  }

  .catalog-kind-meta strong {
    font-size: 13px;
  }

  .catalog-kind-meta p {
    display: none;
  }

  .catalog-kind-chips {
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 2px;
    gap: 6px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    touch-action: pan-x;
    &::-webkit-scrollbar {
      height: 3px;
    }
    &::-webkit-scrollbar-thumb {
      background: #d0d5dd;
      border-radius: 3px;
    }
  }

  .catalog-kind-chip {
    min-height: 28px;
    padding: 0 8px;
    gap: 4px;
    border-radius: 6px;
    font-size: 12px;
  }

  .catalog-kind-chip__icon {
    width: 14px;
    height: 14px;
    font-size: 12px;
  }

  .catalog-kind-chip span {
    font-size: 12px;
  }

  .catalog-kind-chip small {
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    font-size: 10px;
  }

  .type-icon-picker {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .type-manager-form__actions {
    flex-wrap: wrap;
  }

  .type-icon-trigger {
    width: 38px;
    height: 38px;
  }

  // 种类列表项紧凑
  .type-manager-list {
    gap: 4px;
  }

  .type-manager-item {
    padding: 8px 10px;
    gap: 8px;
  }

  .type-manager-item-main > span {
    display: none;
  }

  .type-manager-item-actions {
    gap: 2px;

    :deep(.el-button) {
      padding: 4px 6px;
      font-size: 11px;
    }
  }

  .dialog-intro {
    margin-bottom: 12px;

    strong { font-size: 14px; }
    p { font-size: 12px; }
  }

  .catalog-dialog :deep(.el-dialog__body) {
    padding: 14px 14px 20px;
  }

  .type-icon-field__row {
    gap: 10px;
  }

  .page-container.products-page {
    padding: 10px;
    gap: 10px;
  }

  .products-page :deep(.el-card__body) {
    padding: 10px 12px 12px;
  }

  .products-page :deep(.el-card__header) {
    padding: 10px 12px 0;
  }

  .panel-header {
    gap: 8px;
  }

  .panel-header-meta strong {
    font-size: 14px;
  }

  .panel-header-meta span {
    font-size: 11px;
  }

  .product-panel-actions {
    gap: 4px;
    flex-wrap: wrap;

    :deep(.el-button) {
      padding: 5px 10px;
      font-size: 12px;
      min-height: 30px;
    }

    :deep(.el-button--primary) {
      padding: 5px 14px;
    }

    :deep(.el-button .el-icon) {
      margin-right: 0;
    }
  }

  .product-panel-actions :deep(.el-button) {
    padding: 6px 10px;
    font-size: 12px;
  }

  .product-filters {
    gap: 6px;
  }

  .toolbar-foot {
    margin-top: 4px;
    padding-top: 6px;
    gap: 4px;
  }

  .group-tree-node-actions {
    display: none;
  }

  .group-tree :deep(.el-tree-node.is-current > .el-tree-node__content) .group-tree-node-actions {
    display: inline-flex;
    opacity: 1;
    pointer-events: auto;
  }

  .group-overview-button {
    flex-direction: column;
    align-items: flex-start;
  }

  .toolbar-actions {
    width: 100%;
    flex-direction: column;
  }

  .search-actions {
    width: 100%;
  }

  .search-actions :deep(.el-button) {
    flex: 1;
  }

  .toolbar-actions :deep(.el-button) {
    flex: 1;
  }

  // 表格列宽统一
  .product-table :deep(.el-table__header),
  .product-table :deep(.el-table__body) {
    table-layout: fixed !important;
    width: 100% !important;
  }

  // 可见列宽分配：配置40% 价格25% 状态25% 操作10%
  .product-table :deep(.el-table__header th.el-table_1_column_4),
  .product-table :deep(.el-table__body td.el-table_1_column_4) {
    width: 42% !important;
  }
  .product-table :deep(.el-table__header th.el-table_1_column_5),
  .product-table :deep(.el-table__body td.el-table_1_column_5) {
    width: 24% !important;
  }
  .product-table :deep(.el-table__header th.el-table_1_column_6),
  .product-table :deep(.el-table__body td.el-table_1_column_6) {
    width: 22% !important;
  }
  .product-table :deep(.el-table__header th.el-table_1_column_7),
  .product-table :deep(.el-table__body td.el-table_1_column_7) {
    width: 12% !important;
  }

  .product-table :deep(.el-table__header-wrapper),
  .product-table :deep(.el-table__body-wrapper) {
    width: 100%;
  }

  .product-table :deep(.el-scrollbar__view) {
    display: block !important;
    width: 100% !important;
  }

  .product-table :deep(colgroup col) {
    width: auto !important;
  }

  .product-table :deep(.col-selection),
  .product-table :deep(.col-drag),
  .product-table :deep(.col-id) {
    display: none;
  }

  // 单元格紧凑
  .product-table :deep(td.el-table__cell) {
    padding: 8px 6px;
  }

  .product-table :deep(.el-table__cell .cell) {
    padding: 0;
  }

  .product-name-head strong {
    white-space: normal;
    word-break: break-word;
    font-size: 13px;
  }

  .product-name-cell {
    gap: 0;
    padding: 0;
  }

  .product-meta-line {
    gap: 4px;
  }

  .product-group-pill {
    max-width: 120px;
  }

  // ---- 价格列紧凑 ----
  .overview-cell {
    gap: 0;
    padding: 0;
  }

  .overview-price-line {
    gap: 4px;

    strong {
      font-size: 13px;
    }

    small {
      font-size: 10px;
    }
  }

  .overview-meta-line {
    gap: 4px;
    flex-direction: column;
    align-items: flex-start;

    small {
      font-size: 10px;
    }
  }

  // ---- 状态列紧凑 ----
  .status-cell {
    padding: 0;
  }

  .status-cell-tags {
    gap: 3px;

    :deep(.el-tag--small) {
      padding: 0 6px;
      font-size: 11px;
      height: 22px;
      line-height: 22px;
    }
  }

  .product-content {
    margin-top: 10px;
  }

  // ---- 操作列紧凑 ----
  .action-toolbar {
    gap: 4px;
  }

  .action-toolbar :deep(.el-button--small) {
    padding: 4px 8px;
    font-size: 12px;
  }

  // ---- 分页器精简 ----
  .table-footer {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
  }

  .table-footer :deep(.el-pagination) {
    flex-wrap: wrap;
    justify-content: flex-start;
  }

  .table-footer :deep(.el-pagination .el-pagination__sizes) {
    display: none;
  }

  .toolbar-foot {
    margin-top: 6px;
    padding-top: 8px;
  }
}

// ===== 商品列表表格 =====
.product-content {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
  margin-top: 14px;
}

.product-table-shell {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  min-width: 0;
}

.product-table {
  --el-table-border-color: #{$divider-color};
  --el-table-header-bg-color: #{$bg-color-soft};
  --el-table-row-hover-bg-color: #{$bg-color-hover};

  :deep(.el-table__header-wrapper th) {
    background: $bg-color-soft;
    color: $text-color-secondary;
    font-weight: 600;
    font-size: 12px;
  }

  :deep(.el-table__cell) {
    border-bottom-color: $divider-color;
    padding: 8px 0;
  }

  :deep(.el-table__row:hover > td) {
    background: $bg-color-hover !important;
  }
}

.table-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid $divider-color;
}

// ===== 拖拽手柄与 drop zone =====
.drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: $text-color-placeholder;
  cursor: grab;
  transition: background-color 0.2s ease, color 0.2s ease;

  &:hover {
    background: rgba($color-primary, 0.08);
    color: $color-primary;
  }

  &.is-dragging {
    opacity: 0.5;
    cursor: grabbing;
  }
}

.product-drop-zone {
  transition: box-shadow 0.15s ease;

  &.is-drop-before {
    box-shadow: inset 0 2px 0 $color-primary;
  }

  &.is-drop-after {
    box-shadow: inset 0 -2px 0 $color-primary;
  }

  &.is-dragging-src {
    opacity: 0.5;
  }
}

// ===== 商品信息单元格 =====
.product-name-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 4px 0;
}

.product-name-head {
  display: flex;
  align-items: baseline;
  gap: 8px;
  min-width: 0;
  flex-wrap: wrap;

  strong {
    overflow: hidden;
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.5;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.product-remark-inline {
  display: inline-flex;
  align-items: center;
  max-width: 180px;
  min-height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgba($color-warning, 0.12);
  color: color.mix($color-warning, #8a5a00, 62%);
  font-size: 11px;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-updated-at {
  flex-shrink: 0;
  color: $text-color-placeholder;
  font-size: 11px;
  white-space: nowrap;
}

.product-meta-line {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
  min-width: 0;
}

.product-group-pill {
  display: inline-flex;
  align-items: center;
  max-width: 180px;
  min-height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: $bg-color-soft;
  color: $text-color-secondary;
  font-size: 11px;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.product-module {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgba($color-primary, 0.08);
  color: $color-primary;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.product-hostname-rule {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgba($color-warning, 0.1);
  color: $color-warning;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

// ===== 价格/资源单元格 =====
.overview-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  padding: 4px 0;
}

.overview-price-line {
  display: flex;
  align-items: baseline;
  gap: 6px;

  strong {
    color: $text-color-primary;
    font-size: 14px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
  }

  small {
    color: $text-color-placeholder;
    font-size: 11px;
  }
}

.overview-meta-line {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;

  small {
    color: $text-color-secondary;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
  }
}

// ===== 交付/状态单元格 =====
.status-cell {
  display: flex;
  flex-direction: column;
  min-width: 0;
  padding: 4px 0;
}

.status-cell-tags {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

// ===== 分类选中占位 =====
.group-selection-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 40px 20px;
  text-align: center;

  strong {
    color: $text-color-primary;
    font-size: 15px;
  }

  p {
    margin: 0;
    color: $text-color-secondary;
    font-size: 13px;
    line-height: 1.6;
  }
}

// ===== 供应商同步警告 =====
.supplier-sync-warning {
  color: $color-warning;
  font-size: 12px;
  line-height: 1.6;
}

// ===== 种类管理弹窗 =====
.type-manager-form {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.type-manager-form__main {
  display: grid;
  grid-template-columns: minmax(220px, 1fr);
  flex: 1;
  min-width: 0;
}

.type-manager-form__actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.type-icon-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border: 1px solid $border-color;
  border-radius: 10px;
  background: $bg-color-card;
  color: $text-color-secondary;
  cursor: pointer;
  transition: border-color 0.18s ease, box-shadow 0.18s ease;

  &:hover {
    border-color: $color-primary-border;
    box-shadow: 0 0 0 3px rgba($color-primary, 0.08);
    color: $color-primary;
  }

  .el-icon {
    font-size: 16px;
  }
}

.type-icon-popover__panel {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.type-icon-popover__head {
  display: flex;
  flex-direction: column;
  gap: 4px;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
  }

  span {
    color: $text-color-secondary;
    font-size: 12px;
    line-height: 1.5;
  }
}

.type-icon-picker {
  display: grid;
  grid-template-columns: repeat(4, minmax(72px, 1fr));
  gap: 8px;
}

.type-icon-picker__item {
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 72px;
  padding: 8px 6px;
  border: 1px solid $border-color;
  border-radius: 10px;
  background: $bg-color-card;
  color: $text-color-secondary;
  cursor: pointer;
  transition: all 0.18s ease;

  .el-icon {
    font-size: 18px;
    color: $text-color-primary;
  }

  span {
    font-size: 11px;
    line-height: 1.2;
    text-align: center;
  }

  &:hover {
    border-color: $color-primary-border;
    color: $color-primary;
    transform: translateY(-1px);
  }

  &.is-active {
    border-color: $color-primary-border;
    background: $color-primary-soft;
    color: $color-primary;
    box-shadow: inset 0 0 0 1px rgba($color-primary, 0.08);
  }

  &.is-active .el-icon {
    color: $color-primary;
  }
}

.type-icon-clear {
  align-self: flex-start;
  padding: 0;
  border: 0;
  background: transparent;
  color: $text-color-secondary;
  font-size: 12px;
  cursor: pointer;

  &:hover {
    color: $color-primary;
  }
}

.type-icon-popover__foot {
  display: flex;
  justify-content: flex-end;
}

.type-manager-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.type-manager-tip {
  color: $text-color-placeholder;
  font-size: 12px;
}

.type-manager-drag-feedback {
  margin-bottom: 10px;
}

.type-manager-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.type-manager-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid $border-color;
  border-radius: 10px;
  background: $bg-color-card;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    border-color: $border-color-strong;
  }

  &.is-dragging {
    opacity: 0.5;
  }

  &.is-drop-before {
    box-shadow: inset 0 2px 0 $color-primary;
  }

  &.is-drop-after {
    box-shadow: inset 0 -2px 0 $color-primary;
  }
}

.type-manager-drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  flex-shrink: 0;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: $text-color-placeholder;
  cursor: grab;
  transition: background-color 0.2s ease, color 0.2s ease;

  &:hover:not(:disabled) {
    background: rgba($color-primary, 0.08);
    color: $color-primary;
  }

  &:disabled {
    cursor: not-allowed;
    opacity: 0.5;
  }
}

.type-manager-item-main {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
  min-width: 0;
}

.type-manager-item-head {
  display: flex;
  align-items: center;
  gap: 8px;

  strong {
    color: $text-color-primary;
    font-size: 13px;
    font-weight: 600;
  }
}

.type-manager-item-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.type-manager-item-title__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
  background: rgba($color-primary, 0.08);
  color: $color-primary;
  flex-shrink: 0;
}

.type-manager-item-state {
  display: inline-flex;
  align-items: center;
  min-height: 20px;
  padding: 0 8px;
  border-radius: 999px;
  background: rgba($color-success, 0.1);
  color: $color-success;
  font-size: 11px;
  font-weight: 600;

  &.is-hidden {
    background: rgba($color-warning, 0.1);
    color: $color-warning;
  }
}

.type-manager-item-main > span {
  color: $text-color-placeholder;
  font-size: 12px;
}

.type-manager-item-icon-name {
  color: $text-color-secondary;
  font-size: 11px;
}

.type-manager-item-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  flex-shrink: 0;
}

.type-manager-item-actions :deep(.el-button) {
  margin-left: 0;
}

// ===== 商品拥有者抽屉 =====
.owners-drawer :deep(.el-drawer) {
  background: #ffffff;
}

.owners-drawer-shell {
  display: flex;
  flex-direction: column;
  gap: 16px;
  height: 100%;
}

.owners-drawer-header {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-bottom: 16px;
  border-bottom: 1px solid #e5e6eb;
}

.owners-product-info {
  display: flex;
  align-items: baseline;
  gap: 8px;

  strong {
    font-size: 15px;
    font-weight: 600;
    color: #1d2129;
  }

  span {
    font-size: 12px;
    color: #86909c;
    background: #f2f3f5;
    padding: 1px 6px;
    border-radius: 3px;
  }
}

.owners-summary-row {
  display: flex;
  gap: 0;
  background: #f7f8fa;
  border-radius: 6px;
  border: 1px solid #e5e6eb;
  overflow: hidden;
}

.owners-summary-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 3px;
  padding: 12px 0;
  border-right: 1px solid #e5e6eb;

  &:last-child {
    border-right: none;
  }

  .owners-summary-val {
    font-size: 22px;
    font-weight: 700;
    color: #0052d9;
    line-height: 1;
    font-variant-numeric: tabular-nums;
  }

  .owners-summary-key {
    font-size: 11px;
    color: #86909c;
  }
}

.owners-search-bar {
  display: flex;
  gap: 8px;
  align-items: center;
}

.owners-table {
  flex: 1;

  :deep(.el-table__header-wrapper th) {
    background: #f7f8fa;
    color: #4e5969;
    font-weight: 500;
  }

  :deep(.el-table__row:hover > td) {
    background: #f2f3f5 !important;
  }
}

.owner-user-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 2px 0;

  strong {
    font-size: 13px;
    font-weight: 500;
    color: #1d2129;
  }

  span {
    font-size: 11px;
    color: #86909c;
  }
}

.owners-date {
  font-size: 12px;
  color: #4e5969;
  font-variant-numeric: tabular-nums;
}

.owners-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
  border-top: 1px solid #e5e6eb;
}
</style>

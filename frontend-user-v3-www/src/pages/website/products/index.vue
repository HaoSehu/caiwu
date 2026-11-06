<template>
  <!-- data-mobile-view：手机端主容器形态标记（form）。商品页在手机上以纵向表单
       一列排列配置项，桌面端不渲染该属性。 -->
  <div
    class="shop-wrap"
    :class="{ 'shop-wrap--no-product': !selectedProduct }"
    :data-mobile-view="isPhone ? 'form' : undefined"
  >
    <!-- 左侧产品目录侧边栏 -->
    <aside
      v-if="!isMobile"
      class="shop-sidebar"
      :class="{ collapsed: sidebarCollapsed }"
    >
      <div class="sidebar-head">
        <span class="sidebar-head-text" v-show="!sidebarCollapsed"
          >产品目录</span
        >
        <button
          class="sidebar-toggle"
          :aria-label="sidebarCollapsed ? '展开产品目录' : '收起产品目录'"
          :aria-expanded="!sidebarCollapsed"
          @click="sidebarCollapsed = !sidebarCollapsed"
        >
          <MenuUnfoldIcon v-if="sidebarCollapsed" />
          <MenuFoldIcon v-else />
        </button>
      </div>
      <button
        type="button"
        v-for="type in productTypes"
        :key="type.value"
        class="sidebar-item"
        :class="{ active: activeTypeValue === type.value }"
        :aria-pressed="activeTypeValue === type.value"
        @click="switchType(type.value)"
        :title="sidebarCollapsed ? type.label : ''"
      >
        <span class="item-abbr">{{
          type.abbr || type.label.slice(0, 2).toUpperCase()
        }}</span>
        <template v-if="!sidebarCollapsed">
          <span class="item-body">
            <span class="item-name">{{ type.label }}</span>
            <span class="item-sub">{{
              type.product_count > 0
                ? `${type.product_count} 个商品`
                : "暂无商品"
            }}</span>
          </span>
          <span class="item-count">{{ type.product_count || 0 }}</span>
        </template>
      </button>
    </aside>

    <!-- 右侧主区域 -->
    <div class="shop-body">
      <!-- 中间配置区 -->
      <div class="shop-main">
        <!-- 页面标题视觉隐藏：产品页是目录型界面，视觉上的层级由左侧「产品目录」
             与配置区标题承担，再叠一个大标题会挤掉首屏配置区。
             h1 仍保留在无障碍树里，读屏与 SEO 有明确页面主题。 -->
        <h1 class="sr-only">产品与服务 - 配置选型与报价</h1>
        <div v-if="isMobile" class="mobile-picker-row mobile-picker-row--duo">
          <div class="mobile-picker-col">
            <h2 class="config-block-title">地区</h2>
            <button
              type="button"
              class="mobile-picker-trigger"
              @click="openMobileRegionDrawer"
            >
              <span
                >{{ activeGroupName || "请选择地区"
                }}{{ activeChildName ? ` · ${activeChildName}` : "" }}</span
              >
              <svg
                viewBox="0 0 12 12"
                fill="none"
                width="12"
                height="12"
                aria-hidden="true"
              >
                <path
                  d="M3 4.5l3 3 3-3"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </div>
          <div v-if="visibleProducts.length" class="mobile-picker-col">
            <h2 class="config-block-title">产品规格选择</h2>
            <button
              type="button"
              class="mobile-picker-trigger"
              @click="openMobileSpecPicker"
            >
              <span>{{ mobileSpecPickerLabel || "请选择产品规格" }}</span>
              <svg
                viewBox="0 0 12 12"
                fill="none"
                width="12"
                height="12"
                aria-hidden="true"
              >
                <path
                  d="M3 4.5l3 3 3-3"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </div>
        </div>

        <!-- 产品分类（二级，一级 Tab） -->
        <div class="filter-block" v-if="rootGroups.length && !isMobile">
          <span class="filter-label">地区</span>
          <t-tabs
            class="catalog-tabs"
            :value="activeGroupId"
            :list="rootGroupTabs"
            @change="handleDesktopGroupChange"
          />
        </div>

        <!-- 二级分类（三级，二级 Tab） -->
        <div class="filter-block" v-if="childGroups.length && !isMobile">
          <span class="filter-label">可用区</span>
          <t-tabs
            class="catalog-tabs catalog-tabs--child"
            :value="activeChildId"
            :list="childGroupTabs"
            @change="handleDesktopChildChange"
          />
        </div>

        <div
          v-if="selectedProduct && productIntroText && !isMobile"
          class="product-intro-card"
        >
          <h2 class="config-block-title">商品介绍</h2>
          <p>{{ productIntroText }}</p>
        </div>

        <!-- 商品配置区 -->
        <t-loading
          v-if="selectedProduct"
          :loading="configLoading"
          size="small"
        >
          <div class="config-area">
            <!-- 操作系统 -->
            <div v-if="osGroups.length && isMobile" class="mobile-picker-row">
              <h2 class="config-block-title">操作系统</h2>
              <button
                type="button"
                class="mobile-picker-trigger"
                @click="openMobileOsDrawer"
              >
                <span
                  >{{ currentOsGroup?.label || "请选择系统"
                  }}{{
                    currentOsVersionLabel ? ` · ${currentOsVersionLabel}` : ""
                  }}</span
                >
                <svg
                  viewBox="0 0 12 12"
                  fill="none"
                  width="12"
                  height="12"
                  aria-hidden="true"
                >
                  <path
                    d="M3 4.5l3 3 3-3"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>
            <div
              class="config-block"
              v-if="osConfig && !isMobile && !machineConfigs.length"
            >
              <h2 class="config-block-title">系统安装</h2>
              <div class="os-section">
                <div v-if="osGroups.length" class="os-cards">
                  <button
                    v-for="os in osGroups"
                    :key="os.id"
                    type="button"
                    class="os-card"
                    :class="{ active: configForm.os_group === os.id }"
                    @click="selectOsGroup(os)"
                  >
                    <div class="os-card-head">
                      <img
                        v-if="os.icon"
                        :src="os.icon"
                        :alt="os.label"
                        class="os-logo-img"
                        width="28"
                        height="28"
                        loading="lazy"
                        decoding="async"
                      />
                      <span v-else class="os-logo">{{
                        os.label.slice(0, 2)
                      }}</span>
                      <span class="os-label-name">{{ os.label }}</span>
                    </div>
                    <div class="os-card-ver">
                      <span v-if="configForm.os_group === os.id">{{
                        currentOsVersionLabel
                      }}</span>
                      <span v-else class="os-placeholder">选择版本</span>
                    </div>
                    <span
                      class="os-check-mark"
                      v-if="configForm.os_group === os.id"
                      >✓</span
                    >
                  </button>
                </div>
                <div v-else class="spec-fixed">
                  当前商品未提供可切换的系统版本
                </div>
                <div class="os-ver-row" v-if="currentOsGroup?.versions?.length">
                  <span class="os-ver-label">版本</span>
                  <div class="os-ver-btns">
                    <button
                      v-for="ver in currentOsGroup.versions"
                      :key="ver.id"
                      type="button"
                      class="ver-btn"
                      :class="{ active: configForm.os === ver.id }"
                      @click="configForm.os = ver.id"
                    >
                      {{ ver.label }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- 机型配置 -->
            <div
              v-if="(machineConfigs.length || hasMobileProductGroups) && isMobile"
            >
              <div
                v-if="hasMobileProductGroups"
                class="mobile-picker-row mobile-picker-row--duo"
              >
                <div class="mobile-picker-col">
                  <h2 class="config-block-title">CPU</h2>
                  <button
                    type="button"
                    class="mobile-picker-trigger"
                    @click="openMobileGroupedCpuDrawer"
                  >
                    <span>{{ selectedMobileCpuLabel || "请选择 CPU" }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
                <div class="mobile-picker-col">
                  <h2 class="config-block-title">内存</h2>
                  <button
                    type="button"
                    class="mobile-picker-trigger"
                    @click="openMobileGroupedMemoryDrawer"
                  >
                    <span>{{ selectedMobileMemoryLabel || "请选择内存" }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <div
                v-else-if="cpuConfig || memConfig"
                class="mobile-picker-row mobile-picker-row--duo"
              >
                <div v-if="cpuConfig" class="mobile-picker-col">
                  <h2 class="config-block-title">{{ cpuConfig.label }}</h2>
                  <button
                    type="button"
                    class="mobile-picker-trigger"
                    @click="openMobileCpuDrawer"
                  >
                    <span>{{
                      cpuConfig.options.find(
                        (o) => o.id === configForm[cpuConfig.key],
                      )?.label || "请选择"
                    }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
                <div v-if="memConfig" class="mobile-picker-col">
                  <h2 class="config-block-title">{{ memConfig.label }}</h2>
                  <button
                    type="button"
                    class="mobile-picker-trigger"
                    @click="openMobileMemDrawer"
                  >
                    <span>{{
                      memConfig.options.find(
                        (o) => o.id === configForm[memConfig.key],
                      )?.label || "请选择"
                    }}</span>
                    <svg
                      viewBox="0 0 12 12"
                      fill="none"
                      width="12"
                      height="12"
                      aria-hidden="true"
                    >
                      <path
                        d="M3 4.5l3 3 3-3"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
              <div v-if="otherMachineConfigs.length" class="config-block">
                <div class="spec-list">
                  <template v-for="cfg in otherMachineConfigs" :key="cfg.key">
                    <MobileRangePicker
                      v-if="isRangeConfig(cfg)"
                      v-model="configForm[cfg.key + '_num']"
                      :label="cfg.label"
                      :min="cfg.min ?? 1"
                      :max="cfg.max ?? 9999"
                    />
                    <button
                      v-else-if="cfg.options.length"
                      type="button"
                      class="mobile-config-select-row"
                      @click="openMobileSingleConfigDrawer(cfg)"
                    >
                      <span class="mobile-config-select-label">{{
                        cfg.label
                      }}</span>
                      <span class="mobile-config-select-value">
                        {{ selectedOptionLabel(cfg) || "请选择" }}
                        <svg
                          viewBox="0 0 12 12"
                          fill="none"
                          width="12"
                          height="12"
                          aria-hidden="true"
                        >
                          <path
                            d="M4.5 3l3 3-3 3"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                          />
                        </svg>
                      </span>
                    </button>
                    <div v-else class="spec-row">
                      <span class="spec-label">{{ cfg.label }}</span>
                      <MobileRangePicker
                        v-if="cfg.isNumber"
                        v-model="configForm[cfg.key + '_num']"
                        :label="cfg.label"
                        :min="cfg.min ?? 1"
                        :max="cfg.max ?? 9999"
                      />
                      <div class="opt-row" v-else-if="cfg.options.length > 1">
                        <button
                          v-for="opt in cfg.options"
                          :key="opt.id"
                          type="button"
                          class="opt-btn"
                          :class="{ active: configForm[cfg.key] === opt.id }"
                          @click="configForm[cfg.key] = opt.id"
                        >
                          {{ opt.label }}
                        </button>
                      </div>
                      <span v-else class="spec-fixed">{{
                        cfg.options[0]?.label
                      }}</span>
                    </div>
                  </template>
                </div>
              </div>
            </div>
            <div class="config-block" v-if="machineConfigs.length && !isMobile">
              <h2 class="config-block-title">机型配置</h2>
              <div
                v-if="desktopMachineSpecRows.length"
                class="desktop-machine-spec-panel"
              >
                <div class="desktop-machine-table-scroll">
                  <!-- 表格语义要成对：只有 role="row"/"columnheader" 而没有 role="table"
                       容器时，读屏不会把它当表格，列标题也不会关联到单元格 -->
                  <div
                    class="desktop-machine-table"
                    role="table"
                    aria-label="机型规格"
                  >
                    <div class="desktop-machine-table-head" role="row">
                    <span
                      class="machine-spec-cell machine-spec-cell--name"
                      role="columnheader"
                      >实例规格</span
                    >
                    <span
                      class="machine-spec-cell machine-spec-cell--sortable"
                      role="columnheader"
                      :aria-sort="
                        machineSpecSort.key === 'cpu'
                          ? machineSpecSort.direction === 'asc'
                            ? 'ascending'
                            : 'descending'
                          : 'none'
                      "
                    >
                      <span>vCPU</span>
                      <span
                        class="machine-spec-sort-controls"
                        aria-label="vCPU 排序"
                      >
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'cpu' &&
                              machineSpecSort.direction === 'asc',
                          }"
                          aria-label="vCPU 从小到大排序"
                          @click="setMachineSpecSort('cpu', 'asc')"
                        >
                          <CaretUpIcon />
                        </button>
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'cpu' &&
                              machineSpecSort.direction === 'desc',
                          }"
                          aria-label="vCPU 从大到小排序"
                          @click="setMachineSpecSort('cpu', 'desc')"
                        >
                          <CaretDownIcon />
                        </button>
                      </span>
                    </span>
                    <span
                      class="machine-spec-cell machine-spec-cell--sortable"
                      role="columnheader"
                      :aria-sort="
                        machineSpecSort.key === 'memory'
                          ? machineSpecSort.direction === 'asc'
                            ? 'ascending'
                            : 'descending'
                          : 'none'
                      "
                    >
                      <span>内存</span>
                      <span
                        class="machine-spec-sort-controls"
                        aria-label="内存排序"
                      >
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'memory' &&
                              machineSpecSort.direction === 'asc',
                          }"
                          aria-label="内存从小到大排序"
                          @click="setMachineSpecSort('memory', 'asc')"
                        >
                          <CaretUpIcon />
                        </button>
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'memory' &&
                              machineSpecSort.direction === 'desc',
                          }"
                          aria-label="内存从大到小排序"
                          @click="setMachineSpecSort('memory', 'desc')"
                        >
                          <CaretDownIcon />
                        </button>
                      </span>
                    </span>
                    <span
                      class="machine-spec-cell machine-spec-cell--processor"
                      role="columnheader"
                      >处理器</span
                    >
                    <span class="machine-spec-cell" role="columnheader"
                      >主频/睿频</span
                    >
                    <span
                      class="machine-spec-cell machine-spec-cell--price machine-spec-cell--sortable"
                      role="columnheader"
                      :aria-sort="
                        machineSpecSort.key === 'price'
                          ? machineSpecSort.direction === 'asc'
                            ? 'ascending'
                            : 'descending'
                          : 'none'
                      "
                    >
                      <span>基础价格</span>
                      <span
                        class="machine-spec-sort-controls"
                        aria-label="基础价格排序"
                      >
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'price' &&
                              machineSpecSort.direction === 'asc',
                          }"
                          aria-label="基础价格从小到大排序"
                          @click="setMachineSpecSort('price', 'asc')"
                        >
                          <CaretUpIcon />
                        </button>
                        <button
                          type="button"
                          class="machine-spec-sort-btn"
                          :class="{
                            active:
                              machineSpecSort.key === 'price' &&
                              machineSpecSort.direction === 'desc',
                          }"
                          aria-label="基础价格从大到小排序"
                          @click="setMachineSpecSort('price', 'desc')"
                        >
                          <CaretDownIcon />
                        </button>
                      </span>
                    </span>
                  </div>
                  <button
                    v-for="row in desktopMachineSpecRows"
                    :key="row.id"
                    type="button"
                    class="desktop-machine-table-row"
                    role="row"
                    :class="{ active: row.active }"
                    :aria-selected="row.active"
                    :aria-describedby="
                      row.note ? `spec-note-${row.id}` : undefined
                    "
                    @click="selectDesktopMachineSpec(row)"
                  >
                    <span
                      class="machine-spec-cell machine-spec-cell--name"
                      role="cell"
                    >
                      <span class="machine-spec-radio" aria-hidden="true"></span>
                      <span class="machine-spec-name-wrap">
                        <span class="machine-spec-name">{{ row.name }}</span>
                        <t-tooltip
                          v-if="row.note"
                          placement="top-left"
                          :delay="120"
                          theme="light"
                          :overlay-inner-style="specNoteTooltipStyle"
                        >
                          <template #content>
                            <div class="spec-note-content">{{ row.note }}</div>
                          </template>
                          <span
                            class="spec-note-trigger"
                            aria-hidden="true"
                            @click.stop
                            >?</span
                          >
                        </t-tooltip>
                      </span>
                    </span>
                    <span class="machine-spec-cell" role="cell">{{
                      row.cpuText
                    }}</span>
                    <span class="machine-spec-cell" role="cell">{{
                      row.memoryText
                    }}</span>
                    <span
                      class="machine-spec-cell machine-spec-cell--processor"
                      role="cell"
                      >{{ row.processorLabel }}</span
                    >
                    <span class="machine-spec-cell" role="cell">{{
                      formatFrequencyPair(row.baseFrequency, row.turboFrequency)
                    }}</span>
                    <span
                      class="machine-spec-cell machine-spec-cell--price"
                      role="cell"
                      >{{ row.basePriceText }}</span
                    >
                  </button>
                  <div class="sr-only">
                    <template
                      v-for="row in desktopMachineSpecRows"
                      :key="`note-${row.id}`"
                    >
                      <span v-if="row.note" :id="`spec-note-${row.id}`"
                        >{{ row.name }}：{{ row.note }}</span
                      >
                    </template>
                  </div>
                  </div>
                </div>
                <div v-if="osConfig" class="desktop-machine-os-panel">
                  <div class="desktop-machine-subtitle">系统安装</div>
                  <div class="os-section">
                    <div v-if="osGroups.length" class="os-cards">
                      <button
                        v-for="os in osGroups"
                        :key="os.id"
                        type="button"
                        class="os-card"
                        :class="{ active: configForm.os_group === os.id }"
                        @click="selectOsGroup(os)"
                      >
                        <div class="os-card-head">
                          <img
                            v-if="os.icon"
                            :src="os.icon"
                            :alt="os.label"
                            class="os-logo-img"
                            width="28"
                            height="28"
                            loading="lazy"
                            decoding="async"
                          />
                          <span v-else class="os-logo">{{
                            os.label.slice(0, 2)
                          }}</span>
                          <span class="os-label-name">{{ os.label }}</span>
                        </div>
                        <div class="os-card-ver">
                          <span v-if="configForm.os_group === os.id">{{
                            currentOsVersionLabel
                          }}</span>
                          <span v-else class="os-placeholder">选择版本</span>
                        </div>
                        <span
                          class="os-check-mark"
                          v-if="configForm.os_group === os.id"
                          >✓</span
                        >
                      </button>
                    </div>
                    <div v-else class="spec-fixed">
                      当前商品未提供可切换的系统版本
                    </div>
                    <div
                      class="os-ver-row"
                      v-if="currentOsGroup?.versions?.length"
                    >
                      <span class="os-ver-label">版本</span>
                      <div class="os-ver-btns">
                        <button
                          v-for="ver in currentOsGroup.versions"
                          :key="ver.id"
                          type="button"
                          class="ver-btn"
                          :class="{ active: configForm.os === ver.id }"
                          @click="configForm.os = ver.id"
                        >
                          {{ ver.label }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
                <div
                  v-if="desktopMachineExtraConfigs.length"
                  class="desktop-machine-extra-list"
                >
                  <template
                    v-for="cfg in desktopMachineExtraConfigs"
                    :key="cfg.key"
                  >
                    <MobileRangePicker
                      v-if="cfg.isNumber"
                      v-model="configForm[cfg.key + '_num']"
                      :label="cfg.label"
                      :min="cfg.min ?? 1"
                      :max="cfg.max ?? 9999"
                    />
                    <div v-else class="spec-row spec-row--desktop-extra">
                      <span class="spec-label">{{ cfg.label }}</span>
                      <div class="opt-row" v-if="cfg.options.length > 1">
                        <button
                          v-for="opt in cfg.options"
                          :key="opt.id"
                          type="button"
                          class="opt-btn"
                          :class="{ active: configForm[cfg.key] === opt.id }"
                          @click="configForm[cfg.key] = opt.id"
                        >
                          {{ opt.label }}
                        </button>
                      </div>
                      <span v-else class="spec-fixed">{{
                        cfg.options[0]?.label
                      }}</span>
                    </div>
                  </template>
                </div>
              </div>
              <div v-else class="spec-list">
                <template v-for="cfg in machineConfigs" :key="cfg.key">
                  <MobileRangePicker
                    v-if="cfg.isNumber"
                    v-model="configForm[cfg.key + '_num']"
                    :label="cfg.label"
                    :min="cfg.min ?? 1"
                    :max="cfg.max ?? 9999"
                  />
                  <div v-else class="spec-row">
                    <span class="spec-label">{{ cfg.label }}</span>
                    <div class="opt-row" v-if="cfg.options.length > 1">
                      <button
                        v-for="opt in cfg.options"
                        :key="opt.id"
                        type="button"
                        class="opt-btn"
                        :class="{ active: configForm[cfg.key] === opt.id }"
                        @click="configForm[cfg.key] = opt.id"
                      >
                        {{ opt.label }}
                      </button>
                    </div>
                    <span v-else class="spec-fixed">{{
                      cfg.options[0]?.label
                    }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- 网络配置 -->
            <div class="config-block" v-if="networkConfigs.length">
              <h2 class="config-block-title">网络配置</h2>
              <div class="spec-list">
                <template v-for="cfg in networkConfigs" :key="cfg.key">
                  <MobileRangePicker
                    v-if="isMobile && isRangeConfig(cfg)"
                    v-model="configForm[cfg.key + '_num']"
                    :label="cfg.label"
                    :min="cfg.min ?? 1"
                    :max="cfg.max ?? 9999"
                  />
                  <button
                    v-else-if="isMobile && cfg.options.length"
                    type="button"
                    class="mobile-config-select-row"
                    @click="openMobileSingleConfigDrawer(cfg)"
                  >
                    <span class="mobile-config-select-label">{{
                      cfg.label
                    }}</span>
                    <span class="mobile-config-select-value">
                      {{ selectedOptionLabel(cfg) || "请选择" }}
                      <svg
                        viewBox="0 0 12 12"
                        fill="none"
                        width="12"
                        height="12"
                        aria-hidden="true"
                      >
                        <path
                          d="M4.5 3l3 3-3 3"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <MobileRangePicker
                    v-else-if="cfg.isNumber"
                    v-model="configForm[cfg.key + '_num']"
                    :label="cfg.label"
                    :min="cfg.min ?? 1"
                    :max="cfg.max ?? 9999"
                  />
                  <div v-else class="spec-row">
                    <span class="spec-label">{{ cfg.label }}</span>
                    <div class="opt-row" v-if="cfg.options.length > 1">
                      <button
                        v-for="opt in cfg.options"
                        :key="opt.id"
                        type="button"
                        class="opt-btn"
                        :class="{ active: configForm[cfg.key] === opt.id }"
                        @click="configForm[cfg.key] = opt.id"
                      >
                        {{ opt.label }}
                      </button>
                    </div>
                    <span v-else class="spec-fixed">{{
                      cfg.options[0]?.label
                    }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- 其他配置（兜底） -->
            <div class="config-block" v-if="otherConfigs.length">
              <h2 class="config-block-title">其他配置</h2>
              <div class="spec-list">
                <template v-for="cfg in otherConfigs" :key="cfg.key">
                  <MobileRangePicker
                    v-if="isMobile && isRangeConfig(cfg)"
                    v-model="configForm[cfg.key + '_num']"
                    :label="cfg.label"
                    :min="cfg.min ?? 1"
                    :max="cfg.max ?? 9999"
                  />
                  <button
                    v-else-if="isMobile && cfg.options.length"
                    type="button"
                    class="mobile-config-select-row"
                    @click="openMobileSingleConfigDrawer(cfg)"
                  >
                    <span class="mobile-config-select-label">{{
                      cfg.label
                    }}</span>
                    <span class="mobile-config-select-value">
                      {{ selectedOptionLabel(cfg) || "请选择" }}
                      <svg
                        viewBox="0 0 12 12"
                        fill="none"
                        width="12"
                        height="12"
                        aria-hidden="true"
                      >
                        <path
                          d="M4.5 3l3 3-3 3"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </span>
                  </button>
                  <MobileRangePicker
                    v-else-if="cfg.isNumber"
                    v-model="configForm[cfg.key + '_num']"
                    :label="cfg.label"
                    :min="cfg.min ?? 1"
                    :max="cfg.max ?? 9999"
                  />
                  <div v-else class="spec-row">
                    <span class="spec-label">{{ cfg.label }}</span>
                    <div class="opt-row" v-if="cfg.options.length > 1">
                      <button
                        v-for="opt in cfg.options"
                        :key="opt.id"
                        type="button"
                        class="opt-btn"
                        :class="{ active: configForm[cfg.key] === opt.id }"
                        @click="configForm[cfg.key] = opt.id"
                      >
                        {{ opt.label }}
                      </button>
                    </div>
                    <!-- 单选项必须显示固定值：没有 spec-fixed 兜底时整行只剩标签，
                         用户看不到当前生效的值（网络配置块一直保留了同样的兜底）。 -->
                    <span v-else class="spec-fixed">{{
                      cfg.options[0]?.label
                    }}</span>
                  </div>
                </template>
              </div>
            </div>

            <!-- 基础设置 -->
            <div class="config-block">
              <h2 class="config-block-title">基础设置</h2>
              <div class="spec-list">
                <!-- 计费周期 -->
                <div class="spec-row">
                  <span class="spec-label">计费周期</span>
                  <div class="opt-row">
                    <button
                      v-for="item in pricingEntries"
                      :key="item.cycle"
                      type="button"
                      class="opt-btn cycle-btn"
                      :class="{ active: selectedCycle === item.cycle }"
                      @click="selectedCycle = item.cycle"
                    >
                      <span class="cycle-name">{{ item.label }}</span>
                      <span class="cycle-amt">¥{{ item.amount }}</span>
                    </button>
                  </div>
                </div>

                <!-- 单笔订单仅支持创建一台服务实例，避免多台计价但仅开通一台。 -->
                <div class="spec-row">
                  <span class="spec-label">购买数量</span>
                  <span>1 台（多台请分次下单）</span>
                </div>
              </div>
            </div>
          </div>
        </t-loading>

        <!-- 首屏加载态：此前只有全屏遮罩，去掉后 main 区在加载期间是纯空白，
             慢网下用户看不到任何「正在加载」的反馈。 -->
        <div
          v-if="pageLoading"
          class="catalog-loading"
          role="status"
          aria-live="polite"
        >
          <span class="catalog-loading__spinner" aria-hidden="true"></span>
          <span class="catalog-loading__text">产品数据加载中…</span>
        </div>

        <div
          v-else-if="pageError"
          class="catalog-error"
          role="alert"
        >
          <p class="catalog-error__text">产品数据加载失败，请检查网络连接</p>
          <!-- 本块是 v-else-if="pageError"，pageLoading 为真时上方加载态已接管，
               所以这里不可能处于加载中，不写 :disabled 与三元文案以免误导。 -->
          <button
            type="button"
            class="catalog-error__retry"
            @click="retryInit"
          >
            重新加载
          </button>
        </div>

        <t-empty
          v-else-if="
            !configLoading && !selectedProduct && visibleProducts.length
          "
          role="status"
          aria-live="polite"
          class="catalog-empty"
          title="请选择商品后查看配置与报价"
          style="padding: 60px 0"
        />

        <t-empty
          v-else-if="
            !configLoading && !selectedProduct && !visibleProducts.length
          "
          role="status"
          aria-live="polite"
          class="catalog-empty"
          title="当前分类暂无商品，可切换其他分类查看"
          style="padding: 60px 0"
        />

        <!-- 商品详情加载中：此前 pageLoading 已结束、selectedProduct 还没回来时，
             上面两个空态都被 !configLoading 挡掉，主区是一整片空白，用户无法
             判断是在加载还是坏了。补一个与目录加载同款的状态位，配置区到达后自然消失。 -->
        <div
          v-else-if="configLoading && !selectedProduct"
          class="catalog-loading"
          role="status"
          aria-live="polite"
        >
          <span class="catalog-loading__spinner" aria-hidden="true"></span>
          <span class="catalog-loading__text">商品配置加载中…</span>
        </div>
      </div>

      <!-- 右侧费用摘要 -->
      <aside class="shop-cost" v-if="selectedProduct">
        <div class="shop-cost-scroll">
        <div class="cost-header">
          <span class="cost-title">配置费用</span>
          <span class="stock-badge" :class="stockClass">{{ stockLabel }}</span>
        </div>

        <div class="stock-info" v-if="selectedProduct">
          <div
            v-if="resolvedStock !== null && resolvedStock !== -1"
            class="stock-main"
          >
            当前库存&nbsp;&nbsp;
            <strong>剩余 {{ resolvedStock }} 台</strong>
          </div>
          <div v-else-if="productStockLoading" class="stock-main">
            当前库存&nbsp;&nbsp;<strong>同步中...</strong>
          </div>
          <div v-else-if="productStockError" class="stock-main">
            当前库存&nbsp;&nbsp;<strong>同步失败</strong>
          </div>
          <div class="stock-hint">{{ stockHint }}</div>
        </div>

        <t-alert
          v-if="purchaseRequirementList.length"
          theme="warning"
          role="alert"
          class="purchase-requirements-alert"
        >
          <template #title
            >购买要求：{{ purchaseRequirementSummary }}</template
          >
        </t-alert>

        <div class="cost-divider"></div>

        <div
          class="cost-detail"
          :class="{ 'cost-detail--loading': quoteLoading }"
        >
          <div class="cost-item">
            <span>产品</span
            ><span>{{
              selectedProductSummaryName || selectedProductDisplayName
            }}</span>
          </div>
          <div class="cost-item" v-for="item in summaryItems" :key="item.key">
            <span>{{ item.label }}</span
            ><span>{{ item.value }}</span>
          </div>
          <div class="cost-item" v-if="selectedCycleLabel">
            <span>周期</span><span>{{ selectedCycleLabel }}</span>
          </div>
        </div>

        <div class="cost-divider"></div>

        <div
          class="cost-breakdown"
          :class="{ 'cost-breakdown--loading': quoteLoading }"
        >
          <div class="cost-item">
            <span>基础价格</span><span>¥{{ baseAmount }}</span>
          </div>
          <div class="cost-item" v-if="toMoneyNumber(setupFee) > 0">
            <span>开通费</span><span>¥{{ setupFee }}</span>
          </div>
          <div
            class="cost-item cost-item--extra"
            v-for="item in quoteItems"
            :key="item.field"
          >
            <span>+ {{ item.label }}</span
            ><span>¥{{ item.amount }}</span>
          </div>
          <div
            class="cost-item cost-item--discount"
            v-if="memberDiscountAmount > 0"
          >
            <span
              >会员折扣{{
                memberDiscount.level_name
                  ? `（${memberDiscount.level_name}）`
                  : ""
              }}</span
            ><span>-¥{{ memberDiscount.amount }}</span>
          </div>
          <div class="cost-item cost-item--discount" v-if="appliedCoupon">
            <span>优惠券 {{ appliedCoupon.code }}</span
            ><span>-¥{{ appliedCoupon.discount_amount }}</span>
          </div>
        </div>

        <div class="cost-divider"></div>

        <div class="coupon-panel">
          <div class="coupon-panel-head">
            <span class="coupon-panel-title">优惠券</span>
            <button
              v-if="appliedCoupon"
              type="button"
              class="coupon-clear-btn"
              @click="confirmClearCoupon"
            >
              移除
            </button>
          </div>
          <div class="coupon-panel-form">
            <t-select
              :value="selectedCouponId || undefined"
              clearable
              placeholder="请选择优惠券"
              :options="couponSelectOptions"
              @change="handleCouponChange"
            />
          </div>
          <div v-if="appliedCoupon" class="coupon-panel-tip">
            {{ appliedCoupon.name }}，{{
              appliedCoupon.discount_label
            }}，本次已减免 ¥{{ appliedCoupon.discount_amount }}
          </div>
          <div
            v-else-if="!availableCoupons.length"
            class="coupon-panel-tip coupon-panel-tip--muted"
          >
            {{
              selectedProduct
                ? "当前暂无可用优惠券，登录后如有优惠券会自动展示在这里。"
                : "请选择商品后查看可用优惠券。"
            }}
          </div>
        </div>
        </div>

        <div class="shop-cost-foot">
        <div class="cost-divider"></div>

        <div
          class="cost-total"
          :class="{ 'cost-total--loading': quoteLoading }"
        >
          <span class="cost-total-label">合计费用</span>
          <div class="cost-price-wrap">
            <template v-if="quoteError && !quoteLoading">
              <span class="cost-quote-error">价格计算失败</span>
              <button
                type="button"
                class="cost-quote-retry"
                @click="retryQuote"
              >
                重试
              </button>
            </template>
            <template v-else>
              <span class="cost-currency">¥</span>
              <span
                v-if="quoteLoading"
                class="cost-amount cost-amount--loading"
                >计算中</span
              >
              <span v-else class="cost-amount">{{ totalPrice }}</span>
              <span class="cost-cycle">/{{ selectedCycleLabel || "月付" }}</span>
            </template>
          </div>
        </div>

        <button
          class="buy-btn"
          :disabled="!canSubmit || submitting || quoteLoading"
          :class="{ loading: submitting, 'is-sold-out': soldOut }"
          @click="handleSubmit"
        >
          <span>{{
            soldOut ? "已售罄" : submitting ? "提交中..." : "立即购买"
          }}</span>
        </button>
        </div>
      </aside>
    </div>

    <div
      v-if="selectedProduct"
      ref="allocationFooterEl"
      class="allocation-footer"
      :class="{ 'allocation-footer--anchored': allocationFooterAnchored }"
    >
      <div ref="allocationFooterInnerEl" class="allocation-footer-inner">
        <div class="allocation-footer-main">
          <!-- 只读信息（价格 + 库存/周期）纵向成组，动作（优惠券）单独放右侧胶囊 -->
          <div class="allocation-footer-info">
            <div class="allocation-footer-summary">
              <span class="allocation-footer-label">费用合计：</span>
              <div class="allocation-footer-price">
                <template v-if="quoteError && !quoteLoading">
                  <span class="allocation-footer-quote-error">价格计算失败</span>
                  <button
                    type="button"
                    class="allocation-footer-quote-retry"
                    @click="retryQuote"
                  >
                    重试
                  </button>
                </template>
                <template v-else>
                  <span class="allocation-footer-symbol">¥</span>
                  <span v-if="quoteLoading" class="allocation-footer-num"
                    >…</span
                  >
                  <span v-else class="allocation-footer-num">{{
                    totalPrice
                  }}</span>
                </template>
              </div>
              <span class="allocation-footer-discount-text">
                {{
                  hasDiscount ? `已优惠 ¥${totalDiscountAmount}` : "无折扣"
                }}
              </span>
            </div>
            <div
              v-if="stockLabel || selectedCycleLabel"
              class="allocation-footer-meta"
            >
              <span v-if="stockLabel">{{ stockLabel }}</span>
              <span v-if="selectedCycleLabel">{{ selectedCycleLabel }}</span>
            </div>
          </div>
          <button
            type="button"
            class="allocation-footer-coupon-btn"
            @click="mobileCouponDrawer = true"
          >
            <TicketIcon class="allocation-footer-coupon-icon" />
            {{
              appliedCoupon
                ? `优惠券 -¥${appliedCoupon.discount_amount}`
                : "使用优惠券"
            }}
          </button>
        </div>
        <div class="allocation-footer-actions">
          <!-- 辅助入口进入文档流并与购买按钮同行，避免绝对定位压住按钮热区 -->
          <div class="allocation-footer-links">
            <button
              v-if="productIntroText"
              type="button"
              class="allocation-footer-detail-btn"
              @click="mobileIntroDrawer = true"
            >
              <FileIcon class="allocation-footer-detail-icon" />
              商品介绍
            </button>
            <button
              type="button"
              class="allocation-footer-detail-btn"
              @click="mobileCostDrawer = true"
            >
              <FileIcon class="allocation-footer-detail-icon" />
              费用明细
            </button>
          </div>
          <button
            class="allocation-footer-action"
            :disabled="!canSubmit || submitting || quoteLoading"
            :class="{ loading: submitting, 'is-sold-out': soldOut }"
            @click="handleSubmit"
          >
            <span>{{
              soldOut ? "已售罄" : submitting ? "提交中..." : "立即购买"
            }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 移动专属抽屉必须用 isMobile 门控：defineAsyncComponent 只把 chunk 从路由
         chunk 里切出去，父组件一渲染该 vnode 就会触发 loader（实测桌面端仍会下载
         MobileSheet / MobileRegionPicker / MobileOsPicker 约 12.5KB gzip）。 -->
    <MobileSheet
      v-if="isMobile"
      v-model="mobileIntroDrawer"
      size="40%"
      title="商品介绍"
      confirm-text="关闭"
      @confirm="mobileIntroDrawer = false"
    >
      <div class="mobile-intro-sheet-body">
        <p>{{ productIntroText }}</p>
      </div>
    </MobileSheet>

    <MobileSheet
      v-if="isMobile"
      v-model="mobileCostDrawer"
      size="78%"
      title="费用明细"
      confirm-text="关闭"
      @confirm="mobileCostDrawer = false"
    >
      <div class="mobile-cost-sheet-body">
        <t-alert
          v-if="purchaseRequirementList.length"
          theme="warning"
          role="alert"
          class="purchase-requirements-alert purchase-requirements-alert--mobile"
        >
          <template #title
            >购买要求：{{ purchaseRequirementSummary }}</template
          >
        </t-alert>

        <div class="mobile-cost-table-card">
          <div class="mobile-cost-table-head">
            <span>费用明细：</span>
            <strong>共{{ mobileCostRows.length }}项</strong>
          </div>
          <div class="mobile-cost-table">
            <div class="mobile-cost-table-header">
              <span>配置名称</span>
              <span>配置详情</span>
              <span>{{ hasDiscount ? "折后价已优惠" : "折后价无折扣" }}</span>
            </div>
            <div
              class="mobile-cost-table-row"
              v-for="row in mobileCostRows"
              :key="row.key"
            >
              <span class="mobile-cost-table-name">{{ row.label }}</span>
              <span class="mobile-cost-table-detail">{{ row.detail }}</span>
              <span class="mobile-cost-table-amount">¥{{ row.amount }}</span>
            </div>
            <div class="mobile-cost-table-total">
              <span>总价：</span>
              <strong>¥{{ totalPrice }}</strong>
            </div>
          </div>
        </div>
      </div>
    </MobileSheet>

    <MobileSheet
      v-if="isMobile"
      v-model="mobileCouponDrawer"
      size="42%"
      title="选择优惠券"
      confirm-text="关闭"
      @confirm="mobileCouponDrawer = false"
    >
      <div class="mobile-coupon-sheet-body">
        <div v-if="appliedCoupon" class="mobile-coupon-applied">
          <span class="mobile-coupon-applied__text">{{
            appliedCoupon.name }}，{{
              appliedCoupon.discount_label
            }}，本次已减免 ¥{{ appliedCoupon.discount_amount }}</span>
          <button
            type="button"
            class="coupon-clear-btn"
            @click="removeMobileCoupon"
          >
            移除
          </button>
        </div>
        <button
          v-for="item in availableCoupons"
          :key="item.id"
          type="button"
          class="mobile-coupon-option"
          :class="{ 'is-active': selectedCouponId === item.id }"
          :aria-pressed="selectedCouponId === item.id"
          @click="applyMobileCoupon(item.id)"
        >
          <span class="mobile-coupon-option__label">{{
            item.name }} · {{ item.discount_label }}</span>
          <span class="mobile-coupon-option__mark" aria-hidden="true">✓</span>
        </button>
        <div
          v-if="!availableCoupons.length"
          class="coupon-panel-tip coupon-panel-tip--muted"
        >
          {{
            selectedProduct
              ? "当前暂无可用优惠券，登录后如有优惠券会自动展示在这里。"
              : "请选择商品后查看可用优惠券。"
          }}
        </div>
      </div>
    </MobileSheet>

    <MobileRegionPicker
      v-if="isMobile"
      :visible="mobileRegionDrawer"
      :regions="rootGroups"
      :zone-map="tempChildGroups"
      :active-group-id="activeGroupId"
      :active-zone-id="activeChildId"
      @close="mobileRegionDrawer = false"
      @change="handleRegionChange"
      @confirm="confirmRegionSelection"
    />

    <MobileOsPicker
      v-if="isMobile"
      :visible="mobileOsDrawer"
      :os-groups="osGroups"
      :active-os-group-id="configForm.os_group || ''"
      :active-os-version-id="configForm.os || ''"
      @close="mobileOsDrawer = false"
      @confirm="confirmOsSelection"
    />

    <MobileOptionPicker
      v-if="isMobile && cpuConfig"
      :visible="mobileCpuDrawer"
      :title="cpuConfig.label"
      :options="cpuConfig.options"
      :active-id="configForm[cpuConfig.key] || ''"
      @close="mobileCpuDrawer = false"
      @confirm="confirmCpuSelection"
    />

    <MobileOptionPicker
      v-if="isMobile && memConfig"
      :visible="mobileMemDrawer"
      :title="memConfig.label"
      :options="memConfig.options"
      :active-id="configForm[memConfig.key] || ''"
      @close="mobileMemDrawer = false"
      @confirm="confirmMemSelection"
    />

    <MobileOptionPicker
      v-if="isMobile && hasMobileProductGroups"
      :visible="mobileSpecFamilyDrawer"
      title="产品规格"
      :options="mobileSpecFamilyOptions"
      :active-id="selectedMobileSpecFamily?.key || ''"
      @close="mobileSpecFamilyDrawer = false"
      @confirm="confirmMobileSpecFamilySelection"
    />

    <MobileOptionPicker
      v-if="isMobile && hasMobileProductGroups"
      :visible="mobileGroupedCpuDrawer"
      title="CPU"
      :options="mobileGroupedCpuOptions"
      :active-id="selectedMobileProductGroup?.key || ''"
      @close="mobileGroupedCpuDrawer = false"
      @confirm="confirmMobileGroupedCpuSelection"
    />

    <MobileOptionPicker
      v-if="isMobile && hasMobileProductGroups"
      :visible="mobileGroupedMemoryDrawer"
      title="内存"
      :options="mobileGroupedMemoryOptions"
      :active-id="String(selectedProductId || '')"
      @close="mobileGroupedMemoryDrawer = false"
      @confirm="confirmMobileGroupedMemorySelection"
    />

    <MobileOptionPicker
      v-if="isMobile && mobileSingleConfig"
      :visible="mobileSingleConfigDrawer"
      :title="mobileSingleConfig.label"
      :options="mobileSingleConfig.options"
      :active-id="configForm[mobileSingleConfig.key] || ''"
      @close="closeMobileSingleConfigDrawer"
      @confirm="confirmMobileSingleConfigSelection"
    />


    <MobileSheet
      v-if="isMobile"
      v-model="mobileProductDrawer"
      size="40%"
      title="请选择产品规格"
      cancel-text="取消"
      confirm-text="确定"
      @confirm="confirmMobileProductSelection"
    >
      <div class="mobile-product-sheet-list">
        <template v-if="hasMobileProductGroups">
          <button
            v-for="group in mobileProductGroups"
            :key="group.key"
            type="button"
            class="mobile-product-sheet-item"
            :class="{ active: mobilePendingProductGroupKey === group.key }"
            :aria-pressed="mobilePendingProductGroupKey === group.key"
            :aria-describedby="group.note ? `mob-note-${group.key}` : undefined"
            @click="mobilePendingProductGroupKey = group.key"
          >
            <span class="mobile-product-sheet-name-wrap">
              <span class="mobile-product-sheet-name">{{ group.label }}</span>
              <t-popup
                v-if="group.note"
                placement="top-left"
                trigger="click"
                :overlay-inner-style="specNotePopupStyle"
              >
                <span
                  class="spec-note-trigger"
                  aria-hidden="true"
                  @click.stop
                  >?</span
                >
                <template #content>
                  <div class="spec-note-content">{{ group.note }}</div>
                </template>
              </t-popup>
            </span>
            <span class="mobile-product-sheet-meta">{{ group.cpuText }}</span>
          </button>
        </template>
        <template v-else>
          <button
            v-for="p in visibleProducts"
            :key="p.id"
            type="button"
            class="mobile-product-sheet-item"
            :class="{ active: mobilePendingProductId === p.id }"
            :aria-pressed="mobilePendingProductId === p.id"
            :aria-describedby="
              normalizeInstanceSpecNote(p.instance_spec_note)
                ? `mob-pnote-${p.id}`
                : undefined
            "
            @click="mobilePendingProductId = p.id"
          >
            <span class="mobile-product-sheet-name-wrap">
              <span class="mobile-product-sheet-name">{{
                resolveProductDisplayName(p)
              }}</span>
              <t-popup
                v-if="normalizeInstanceSpecNote(p.instance_spec_note)"
                placement="top-left"
                trigger="click"
                :overlay-inner-style="specNotePopupStyle"
              >
                <span
                  class="spec-note-trigger"
                  aria-hidden="true"
                  @click.stop
                  >?</span
                >
                <template #content>
                  <div class="spec-note-content">
                    {{ normalizeInstanceSpecNote(p.instance_spec_note) }}
                  </div>
                </template>
              </t-popup>
            </span>
            <span
              v-if="formatProductListPrice(p)"
              class="mobile-product-sheet-price"
              >{{ formatProductListPrice(p) }}</span
            >
          </button>
        </template>
        <div class="sr-only">
          <template
            v-for="group in mobileProductGroups"
            :key="`mob-note-${group.key}`"
          >
            <span v-if="group.note" :id="`mob-note-${group.key}`"
              >{{ group.label }}：{{ group.note }}</span
            >
          </template>
          <template v-for="p in visibleProducts" :key="`mob-pnote-${p.id}`">
            <span
              v-if="normalizeInstanceSpecNote(p.instance_spec_note)"
              :id="`mob-pnote-${p.id}`"
              >{{ resolveProductDisplayName(p) }}：{{
                normalizeInstanceSpecNote(p.instance_spec_note)
              }}</span
            >
          </template>
        </div>
      </div>
    </MobileSheet>
  </div>
</template>

<script setup>
import {
  computed,
  defineAsyncComponent,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import {
  CaretDownIcon,
  CaretUpIcon,
  FileIcon,
  MenuFoldIcon,
  MenuUnfoldIcon,
  TicketIcon,
} from "tdesign-icons-vue-next";
import { MessagePlugin } from "tdesign-vue-next/es/message/plugin";
import { useWebsiteProductsPage } from "@/domains/products/useWebsiteProductsPage";
import { usePhoneBreakpoint } from "@/composables/useMobileBreakpoint";
import MobileRangePicker from "@/components/MobileRangePicker.vue";
// 抽屉类移动组件只在移动端真正打开时才需要，桌面访问者永远用不到；
// 静态引入会让它们随 products 路由 chunk 一起下发（实测移动端专属部分 gzip 约 16KB）。
// MobileRangePicker 保持静态：它是移动端配置区的行内控件，首次渲染就要到位。
const MobileRegionPicker = defineAsyncComponent(
  () => import("@/components/MobileRegionPicker.vue"),
);
const MobileOsPicker = defineAsyncComponent(
  () => import("@/components/MobileOsPicker.vue"),
);
const MobileOptionPicker = defineAsyncComponent(
  () => import("@/components/MobileOptionPicker.vue"),
);
const MobileSheet = defineAsyncComponent(
  () => import("@/components/MobileSheet.vue"),
);
import {
  resolveProductDisplayName,
  toMoneyNumber,
} from "@/utils/websiteProductConfig";
import {
  buildInstanceSpecName,
  buildMachineSpecDisplayName,
  isCpuConfigKey,
  isMemoryConfigKey,
  normalizeMemorySpecText,
  parseMachineSpecFromText,
  resolveMachineSpecSelection,
} from "@/domains/products/machineSpecResolver";
import { formatMoneyAmount, formatMoneyText } from "@caiwu/shared/finance";

const {
  pageLoading,
  pageError,
  retryInit,
  configLoading,
  submitting,
  sidebarCollapsed,
  isMobile,
  mobileProductDrawer,
  mobileRegionDrawer,
  tempChildGroups,
  mobilePendingProductId,
  productTypes,
  rootGroups,
  childGroups,
  activeTypeValue,
  activeGroupId,
  activeChildId,
  selectedProductId,
  selectedProduct,
  configForm,
  selectedCycle,
  quoteLoading,
  quoteError,
  retryQuote,
  productStockLoading,
  productStockError,
  selectedProductDisplayName,
  activeGroupName,
  activeChildName,
  osConfig,
  osGroups,
  currentOsGroup,
  currentOsVersionLabel,
  machineConfigs,
  networkConfigs,
  otherConfigs,
  pricingEntries,
  baseAmount,
  setupFee,
  quoteItems,
  appliedCoupon,
  memberDiscount,
  memberDiscountAmount,
  availableCoupons,
  totalPrice,
  selectedCycleLabel,
  summaryItems,
  resolvedStock,
  stockClass,
  stockLabel,
  stockHint,
  selectedCouponId,
  canSubmit,
  purchaseRequirementList,
  purchaseRequirementSummary,
  handleCouponChange,
  confirmClearCoupon,
  visibleProducts,
  selectOsGroup,
  handleSubmit,
  openMobileRegionDrawer,
  handleRegionChange,
  confirmRegionSelection,
  openMobileProductDrawer,
  confirmMobileProductSelection: confirmRawMobileProductSelection,
  switchType,
  switchGroup,
  switchChild,
  selectProduct,
} = useWebsiteProductsPage();

// 手机形态标记：与 $breakpoint-mobile-max 同口径。商品页的布局换轨（侧栏隐藏、
// picker 行出现）仍按 992 判定；这里只用于 data-mobile-view 契约标记。
const { isMobile: isPhone } = usePhoneBreakpoint();

const mobileOsDrawer = ref(false);
const mobileIntroDrawer = ref(false);
const mobileCostDrawer = ref(false);
const mobileCouponDrawer = ref(false);
const allocationFooterEl = ref(null);
const allocationFooterInnerEl = ref(null);
const allocationFooterAnchored = ref(false);
let allocationFooterObserver = null;
let allocationFooterResizeObserver = null;

// 移动端优惠券改用底部抽屉内的选项卡片（替代桌面下拉弹层），
// 与地区/CPU/内存等选择器保持同一交互范式；点选即应用并收起抽屉
function applyMobileCoupon(id) {
  handleCouponChange(id);
  mobileCouponDrawer.value = false;
}

async function removeMobileCoupon() {
  // 二次确认通过后才收起抽屉：取消时保留原优惠券与抽屉，用户可继续切换
  if (await confirmClearCoupon()) {
    mobileCouponDrawer.value = false;
  }
}

// 会员折扣 + 优惠券合计让利，用于浮动合计条与费用明细的折扣文案
const totalDiscountAmount = computed(() => {
  const member = memberDiscountAmount.value;
  const coupon = Number(appliedCoupon.value?.discount_amount || 0);
  return (member + coupon).toFixed(2);
});
const hasDiscount = computed(() => Number(totalDiscountAmount.value) > 0);

// TDesign Tabs 用 list 驱动一级/二级目录，避免额外渲染空的 tab panel
const rootGroupTabs = computed(() =>
  rootGroups.value.map((group) => ({ value: group.id, label: group.name })),
);
const childGroupTabs = computed(() =>
  childGroups.value.map((group) => ({ value: group.id, label: group.name })),
);
// 说明气泡最大宽度：弹层被 Teleport 到 body，scoped 里的类选择器命中不到，
// 用 overlayInnerStyle 直接下发（等价于原 EP 的 popper-class max-width 意图）
const specNoteTooltipStyle = { maxWidth: "min(320px, calc(100vw - 32px))" };
// 移动端规格备注气泡（t-popup）：与桌面 t-tooltip 同口径，固定 260px 在 320px
// 窄屏上会超出可视区；min() 在宽屏下仍取 260px，版式不变。
const specNotePopupStyle = {
  width: "min(260px, calc(100vw - 32px))",
};
// 优惠券下拉：TDesign TSelect 的 options 需显式 label/value
const couponSelectOptions = computed(() =>
  availableCoupons.value.map((item) => ({
    value: item.id,
    label: `${item.name} · ${item.discount_label}`,
  })),
);

function cleanupAllocationFooterObserver() {
  allocationFooterObserver?.disconnect();
  allocationFooterObserver = null;
  allocationFooterResizeObserver?.disconnect();
  allocationFooterResizeObserver = null;
}

function setupAllocationFooterObserver() {
  cleanupAllocationFooterObserver();
  allocationFooterAnchored.value = false;

  if (
    !isMobile.value ||
    !selectedProduct.value ||
    !allocationFooterEl.value ||
    !("IntersectionObserver" in window)
  ) {
    return;
  }

  allocationFooterObserver = new IntersectionObserver(
    ([entry]) => {
      allocationFooterAnchored.value = Boolean(entry?.isIntersecting);
    },
    {
      root: null,
      threshold: 0,
    },
  );
  allocationFooterObserver.observe(allocationFooterEl.value);

  if ("ResizeObserver" in window && allocationFooterInnerEl.value) {
    allocationFooterResizeObserver = new ResizeObserver(([entry]) => {
      // 必须取 border-box 高度：fixed 结算栏的上下 padding（含 safe-area）同样遮挡页面，
      // contentRect 会漏掉这部分，滚到页面底部时最后一段内容会被结算栏压住
      const borderBoxSize = entry?.borderBoxSize;
      const borderBoxHeight = Array.isArray(borderBoxSize)
        ? borderBoxSize[0]?.blockSize
        : borderBoxSize?.blockSize;
      const height = Math.ceil(
        borderBoxHeight ||
          allocationFooterInnerEl.value?.getBoundingClientRect().height ||
          0,
      );
      if (height > 0) {
        // 变量写到 .shop-wrap（allocation-footer 的父级）：fixed 结算栏高度变化时，
        // 同级 .shop-wrap 的 padding-bottom 避让与 .shop-main 内部留白都能联动读取
        allocationFooterEl.value?.parentElement?.style.setProperty(
          "--allocation-footer-height",
          `${height}px`,
        );
      }
    });
    allocationFooterResizeObserver.observe(allocationFooterInnerEl.value);
  }
}

onMounted(() => {
  nextTick(setupAllocationFooterObserver);
});

onBeforeUnmount(() => {
  cleanupAllocationFooterObserver();
});

watch([isMobile, selectedProduct], () => {
  nextTick(setupAllocationFooterObserver);
});

// 跨断点切换为桌面形态时，收起 view 层的移动抽屉，避免 DOM 被 v-if 隐藏后状态残留。
// 抽屉外层现在统一带 v-if="isMobile"，切到桌面会整块卸载，若不在这里复位 ref，
// 切回移动端时组件重新挂载会凭残留的 true 立刻弹出上次的抽屉。
watch(isMobile, (mobile) => {
  if (!mobile) {
    mobileOsDrawer.value = false;
    mobileIntroDrawer.value = false;
    mobileCostDrawer.value = false;
    mobileCouponDrawer.value = false;
    mobileRegionDrawer.value = false;
    mobileProductDrawer.value = false;
    mobileCpuDrawer.value = false;
    mobileMemDrawer.value = false;
    mobileSpecFamilyDrawer.value = false;
    mobileGroupedCpuDrawer.value = false;
    mobileGroupedMemoryDrawer.value = false;
    mobileSingleConfigDrawer.value = false;
  }
});

function formatProductListPrice(product) {
  const pricingEntries = Array.isArray(product?.pricing_entries)
    ? product.pricing_entries
    : [];
  const monthlyEntry = pricingEntries.find(
    (item) => item?.cycle === "monthly" && Number(item?.amount || 0) > 0,
  );
  const fallbackEntry =
    monthlyEntry ||
    pricingEntries.find((item) => Number(item?.amount || 0) > 0);

  if (fallbackEntry) {
    return `${formatMoneyText(fallbackEntry.amount)}/${fallbackEntry.label || "月"}`;
  }

  const pricing =
    product?.pricing && typeof product.pricing === "object"
      ? product.pricing
      : {};
  const monthlyAmount = Number(pricing.monthly || 0);
  if (monthlyAmount > 0) {
    return `${formatMoneyText(monthlyAmount)}/月`;
  }

  if (Number(product?.primary_price || 0) > 0) {
    return `${formatMoneyText(product.primary_price)}/${product.primary_cycle === "monthly" ? "月" : "起"}`;
  }

  return "";
}

function normalizeInstanceSpecNote(value) {
  return String(value || "").trim();
}

function formatMobileSpecNumber(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) {
    return String(value || "").trim();
  }

  return Number.isInteger(number) ? String(number) : String(number);
}

function normalizeMobileModelBaseName(value, cpuText, memoryText) {
  let text = String(value || "").trim();
  const cpuSlug = buildMobileCpuSlug(cpuText);
  const memorySlug = buildMobileMemorySlug(memoryText);
  const cpuNumber = parseMachineSpecNumber(cpuText);
  const memoryNumber = parseMachineSpecNumber(memoryText);
  const cpuAliases = Number.isFinite(cpuNumber)
    ? [
        `${formatMobileSpecNumber(cpuNumber)}vcpu`,
        `${formatMobileSpecNumber(cpuNumber)}cpu`,
        `${formatMobileSpecNumber(cpuNumber)}核`,
      ]
    : [];
  const memoryAliases = Number.isFinite(memoryNumber)
    ? [
        `${formatMobileSpecNumber(memoryNumber)}gib`,
        `${formatMobileSpecNumber(memoryNumber)}gb`,
        `${formatMobileSpecNumber(memoryNumber)}g`,
      ]
    : [];
  const cleanupSegments = [
    memorySlug,
    ...memoryAliases,
    normalizeMemorySpecText(memoryText),
    memoryText,
    cpuSlug,
    ...cpuAliases,
    cpuText,
  ]
    .filter(Boolean)
    .sort((left, right) => String(right).length - String(left).length);

  cleanupSegments.forEach((segment) => {
    const escaped = String(segment).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    text = text
      .replace(new RegExp(`[-_\\s]*${escaped}\\s*$`, "i"), "")
      .replace(
        new RegExp(`[-_\\s]*${escaped.replace(/\\s\\+/g, "\\s*")}\\s*$`, "i"),
        "",
      );
  });

  // 兜底：清理上面未捕获的 vCPU / 内存规格片段
  // （例如 cpuText / memoryText 未单独提供时）。
  text = text.replace(/[-_\s]+\d+vcpu\s*$/i, "");
  text = text.replace(/[-_\s]+\d+g(?:ib?)?\s*$/i, "");

  return text.replace(/[-_\s]+$/g, "").trim();
}

function buildMobileCpuSlug(value) {
  const number = parseMachineSpecNumber(value);
  return Number.isFinite(number) ? `${formatMobileSpecNumber(number)}vcpu` : "";
}

function buildMobileMemorySlug(value) {
  const normalized = normalizeMemorySpecText(value);
  const number = parseMachineSpecNumber(normalized || value);
  return Number.isFinite(number) ? `${formatMobileSpecNumber(number)}g` : "";
}

function resolveMobileProductSpec(product) {
  const detailProduct =
    selectedProduct.value?.id === product.id ? selectedProduct.value : null;
  const sourceProduct = mergeProductPresentationSource(product, detailProduct);
  const displayName = resolveProductDisplayName(sourceProduct);
  const specSource = String(
    sourceProduct.instance_spec_text ||
      sourceProduct.instance_spec_alias ||
      displayName ||
      "",
  ).trim();
  const cpuMemoryDisplay = String(
    sourceProduct.cpu_memory_display || "",
  ).trim();
  const configSpec = resolveMachineSpecSelection(sourceProduct.config_options, {
    cpu: sourceProduct.purchase_requires?.upstream_default_config?.cpu,
    memory: sourceProduct.purchase_requires?.upstream_default_config?.memory,
  });
  const spec = parseMachineSpecFromText(
    [
      cpuMemoryDisplay,
      specSource,
      displayName,
      configSpec.cpuRaw,
      configSpec.memoryRaw,
    ]
      .filter(Boolean)
      .join(" "),
  );
  const cpuText =
    String(sourceProduct.cpu_display || "").trim() ||
    configSpec.cpuText ||
    spec.cpuText ||
    "";
  const memoryText =
    normalizeMemorySpecText(sourceProduct.memory_display) ||
    configSpec.memoryText ||
    spec.memoryText ||
    "";
  const cpuValue = parseMachineSpecNumber(cpuText);
  const memoryValue = parseMachineSpecNumber(memoryText);
  const explicitSpecText = String(
    sourceProduct.instance_spec_text || sourceProduct.instance_spec_alias || "",
  ).trim();
  const baseName = normalizeMobileModelBaseName(
    explicitSpecText ||
      buildMachineSpecDisplayName({
        combinedDisplayName: sourceProduct.combined_display_name,
        displayName,
        cpuText,
        memoryText,
      }) ||
      displayName,
    cpuText,
    memoryText,
  );
  const cpuSlug = buildMobileCpuSlug(cpuText);
  const memorySlug = buildMobileMemorySlug(memoryText);

  return {
    product,
    productId: product.id,
    displayName,
    baseName,
    cpuText,
    memoryText,
    cpuValue,
    memoryValue,
    cpuSlug,
    memorySlug,
    note: normalizeInstanceSpecNote(sourceProduct.instance_spec_note),
    priceText: formatProductListPrice(product),
  };
}

function sortMobileSpecRows(left, right) {
  const leftValid = Number.isFinite(left.memoryValue);
  const rightValid = Number.isFinite(right.memoryValue);

  if (leftValid && rightValid && left.memoryValue !== right.memoryValue) {
    return left.memoryValue - right.memoryValue;
  }

  if (leftValid !== rightValid) {
    return leftValid ? -1 : 1;
  }

  return left.originalIndex - right.originalIndex;
}

const mobileCostRows = computed(() => {
  const rows = [];
  // baseAmount/setupFee 可能是千分位展示文本，经 toMoneyNumber 回读避免 NaN
  const productBaseAmount =
    toMoneyNumber(baseAmount.value) + toMoneyNumber(setupFee.value);

  if (selectedProduct.value) {
    rows.push({
      key: "product",
      label: "配置名称",
      detail:
        selectedProductSummaryName.value ||
        resolveProductDisplayName(selectedProduct.value),
      amount: formatMoneyAmount(productBaseAmount),
    });
  }

  summaryItems.value.forEach((item) => {
    const matchedQuote = quoteItems.value.find(
      (quoteItem) => quoteItem.label === item.label,
    );
    rows.push({
      key: item.key,
      label: item.label,
      detail: item.value,
      amount: formatMoneyAmount(matchedQuote?.amount || 0),
    });
  });

  return rows;
});

const selectedProductGroup = computed(
  () => selectedProduct.value?.group || null,
);
const productIntroText = computed(() =>
  String(selectedProductGroup.value?.slogan || "").trim(),
);
const soldOut = computed(
  () =>
    resolvedStock.value !== null &&
    !productStockLoading.value &&
    !productStockError.value &&
    resolvedStock.value === 0,
);

const FREQUENCY_UNIT = "GHz";

function isCpuConfig(cfg) {
  return isCpuConfigKey(cfg.key, cfg.label);
}

function isMemConfig(cfg) {
  return isMemoryConfigKey(cfg.key, cfg.label);
}

function stripFrequencyUnit(value) {
  const text = String(value || "").trim();

  if (!text) {
    return "";
  }

  return text.replace(/\s*GHz$/i, "");
}

function formatFrequencyValue(value) {
  const text = stripFrequencyUnit(value);

  if (!text) {
    return "";
  }

  return /^[+-]?\d+(?:\.\d+)?$/.test(text) ? `${text}${FREQUENCY_UNIT}` : text;
}

function formatFrequencyPair(baseFrequency, turboFrequency) {
  const base = formatFrequencyValue(baseFrequency);
  const turbo = formatFrequencyValue(turboFrequency);

  if (base && turbo) {
    return `${base}/${turbo}`;
  }

  if (base) {
    return base;
  }

  if (turbo) {
    return turbo;
  }

  return "-";
}

const cpuConfigs = computed(() => machineConfigs.value.filter(isCpuConfig));
const memConfigs = computed(() => machineConfigs.value.filter(isMemConfig));
const cpuConfig = computed(() => cpuConfigs.value[0] || null);
const memConfig = computed(() => memConfigs.value[0] || null);
const otherMachineConfigs = computed(() =>
  machineConfigs.value.filter((cfg) => !isCpuConfig(cfg) && !isMemConfig(cfg)),
);
const desktopMachineExtraConfigs = computed(() => otherMachineConfigs.value);
const machineSpecSort = ref({
  key: "",
  direction: "",
});
const mobilePendingProductGroupKey = ref("");
const PRODUCT_DISPLAY_FIELDS = [
  "combined_display_name",
  "product_display_name",
  "display_name",
  "instance_spec_text",
  "instance_spec_alias",
  "cpu_memory_display",
  "cpu_display",
  "memory_display",
];

function parseMachineSpecNumber(value) {
  const text = String(value || "").trim();
  const match = text.match(/\d+(?:\.\d+)?/);
  return match ? Number(match[0]) : Number.NaN;
}

function resolveProductPriceNumber(product) {
  const pricingEntries = Array.isArray(product?.pricing_entries)
    ? product.pricing_entries
    : [];
  const monthlyEntry = pricingEntries.find(
    (item) => item?.cycle === "monthly" && Number(item?.amount || 0) > 0,
  );
  const fallbackEntry =
    monthlyEntry ||
    pricingEntries.find((item) => Number(item?.amount || 0) > 0);
  if (fallbackEntry) {
    return Number(fallbackEntry.amount || 0);
  }

  const pricing =
    product?.pricing && typeof product.pricing === "object"
      ? product.pricing
      : {};
  const monthlyAmount = Number(pricing.monthly || 0);
  if (monthlyAmount > 0) {
    return monthlyAmount;
  }

  const primaryPrice = Number(product?.primary_price || 0);
  return primaryPrice > 0 ? primaryPrice : Number.NaN;
}

function mergeProductPresentationSource(product, detailProduct = null) {
  if (!detailProduct) {
    return product || {};
  }

  const sourceProduct = { ...product, ...detailProduct };

  PRODUCT_DISPLAY_FIELDS.forEach((field) => {
    const listValue = String(product?.[field] || "").trim();
    if (listValue) {
      sourceProduct[field] = product[field];
    }
  });

  return sourceProduct;
}

function resolveMachineSpecSortValue(row) {
  if (machineSpecSort.value.key === "cpu") {
    return row.cpuValue;
  }

  if (machineSpecSort.value.key === "memory") {
    return row.memoryValue;
  }

  if (machineSpecSort.value.key === "price") {
    return row.basePriceValue;
  }

  return Number.NaN;
}

function setMachineSpecSort(key, direction) {
  machineSpecSort.value = { key, direction };
}

// 机器规格解析缓存：按 product.id 缓存解析结果，命中条件为 product 与选中 detail 的引用未变，
// 避免每次选中切换对所有 SKU 重复正则解析（仅重算选中商品那一行）
const desktopSpecRowCache = new Map();
const mobileSpecCache = new Map();

function resolveDesktopSpecRowBase(product) {
  const cacheKey = Number(product?.id || 0);
  if (!cacheKey) return null;

  const detailProduct =
    selectedProduct.value?.id === product.id ? selectedProduct.value : null;
  const detailRef = detailProduct || null;
  const cached = desktopSpecRowCache.get(cacheKey);
  if (
    cached &&
    cached.productRef === product &&
    cached.detailRef === detailRef
  ) {
    return cached.base;
  }

  const sourceProduct = mergeProductPresentationSource(product, detailProduct);
  const displayName = resolveProductDisplayName(sourceProduct);
  const specSource = String(
    sourceProduct.instance_spec_text ||
      sourceProduct.instance_spec_alias ||
      displayName ||
      "",
  ).trim();
  const cpuMemoryDisplay = String(
    sourceProduct.cpu_memory_display || "",
  ).trim();
  const configSpec = resolveMachineSpecSelection(sourceProduct.config_options, {
    cpu: sourceProduct.purchase_requires?.upstream_default_config?.cpu,
    memory: sourceProduct.purchase_requires?.upstream_default_config?.memory,
  });
  const spec = parseMachineSpecFromText(
    [
      cpuMemoryDisplay,
      specSource,
      displayName || "",
      configSpec.cpuRaw,
      configSpec.memoryRaw,
    ]
      .filter(Boolean)
      .join(" "),
  );
  const cpuText = String(sourceProduct.cpu_display || "").trim();
  const memoryText = normalizeMemorySpecText(sourceProduct.memory_display);
  const resolvedCpuText = cpuText || configSpec.cpuText || spec.cpuText || "";
  const resolvedMemoryText =
    memoryText || configSpec.memoryText || spec.memoryText || "";
  const rowName = buildMachineSpecDisplayName({
    combinedDisplayName: sourceProduct.combined_display_name,
    displayName,
    cpuText: resolvedCpuText,
    memoryText: resolvedMemoryText,
  });

  const base = {
    productId: product.id,
    name:
      rowName ||
      displayName ||
      buildInstanceSpecName(specSource, spec, product.id),
    note: normalizeInstanceSpecNote(sourceProduct.instance_spec_note),
    family: spec.family,
    cpuText: resolvedCpuText || "-",
    memoryText: resolvedMemoryText || "-",
    processor: spec.processor,
    processorLabel: sourceProduct.cpu_model_name || spec.processor,
    baseFrequency: sourceProduct.cpu_base_frequency || "",
    turboFrequency: sourceProduct.cpu_turbo_frequency || "",
    basePriceText: formatProductListPrice(product) || "-",
    cpuValue: parseMachineSpecNumber(resolvedCpuText),
    memoryValue: parseMachineSpecNumber(resolvedMemoryText),
    basePriceValue: resolveProductPriceNumber(product),
  };
  desktopSpecRowCache.set(cacheKey, {
    productRef: product,
    detailRef,
    base,
  });
  return base;
}

function resolveMobileSpecCached(product) {
  const cacheKey = Number(product?.id || 0);
  if (!cacheKey) return null;

  const detailProduct =
    selectedProduct.value?.id === product.id ? selectedProduct.value : null;
  const detailRef = detailProduct || null;
  const cached = mobileSpecCache.get(cacheKey);
  if (
    cached &&
    cached.productRef === product &&
    cached.detailRef === detailRef
  ) {
    return cached.value;
  }

  const value = resolveMobileProductSpec(product);
  mobileSpecCache.set(cacheKey, {
    productRef: product,
    detailRef,
    value,
  });
  return value;
}

const desktopMachineSpecRows = computed(() => {
  if (!visibleProducts.value.length) {
    return [];
  }

  const rows = visibleProducts.value
    .map((product, index) => {
      const base = resolveDesktopSpecRowBase(product);
      if (!base) {
        return null;
      }

      return {
        ...base,
        id: `product-${product.id}`,
        active: selectedProductId.value === product.id,
        // family 的上下文回退（当前分组名）不缓存，取实时值
        family:
          base.family ||
          activeChildName.value ||
          activeGroupName.value ||
          "通用型",
        originalIndex: index,
      };
    })
    .filter(Boolean);

  if (!machineSpecSort.value.key || !machineSpecSort.value.direction) {
    return rows;
  }

  return rows.slice().sort((left, right) => {
    const leftValue = resolveMachineSpecSortValue(left);
    const rightValue = resolveMachineSpecSortValue(right);
    const leftValid = Number.isFinite(leftValue);
    const rightValid = Number.isFinite(rightValue);

    if (!leftValid && !rightValid) {
      return left.originalIndex - right.originalIndex;
    }

    if (!leftValid) {
      return 1;
    }

    if (!rightValid) {
      return -1;
    }

    const factor = machineSpecSort.value.direction === "desc" ? -1 : 1;
    const result = (leftValue - rightValue) * factor;
    return result === 0 ? left.originalIndex - right.originalIndex : result;
  });
});

const mobileProductSpecRows = computed(() =>
  visibleProducts.value
    .map((product, index) => {
      const base = resolveMobileSpecCached(product);
      return base ? { ...base, originalIndex: index } : null;
    })
    .filter(Boolean),
);
const selectedProductSummaryName = computed(() => {
  const productId = Number(
    selectedProductId.value || selectedProduct.value?.id || 0,
  );
  const listProduct =
    visibleProducts.value.find(
      (product) => Number(product.id || 0) === productId,
    ) || null;
  const sourceProduct = mergeProductPresentationSource(
    listProduct || selectedProduct.value,
    selectedProduct.value,
  );
  const displayName = resolveProductDisplayName(sourceProduct);
  const specSource = String(
    sourceProduct.instance_spec_text ||
      sourceProduct.instance_spec_alias ||
      displayName ||
      "",
  ).trim();
  const cpuMemoryDisplay = String(
    sourceProduct.cpu_memory_display || "",
  ).trim();
  const configSpec = resolveMachineSpecSelection(sourceProduct.config_options, {
    cpu: sourceProduct.purchase_requires?.upstream_default_config?.cpu,
    memory: sourceProduct.purchase_requires?.upstream_default_config?.memory,
  });
  const spec = parseMachineSpecFromText(
    [
      cpuMemoryDisplay,
      specSource,
      displayName,
      configSpec.cpuRaw,
      configSpec.memoryRaw,
    ]
      .filter(Boolean)
      .join(" "),
  );
  const cpuText =
    String(sourceProduct.cpu_display || "").trim() ||
    configSpec.cpuText ||
    spec.cpuText ||
    "";
  const memoryText =
    normalizeMemorySpecText(sourceProduct.memory_display) ||
    configSpec.memoryText ||
    spec.memoryText ||
    "";

  return (
    buildMachineSpecDisplayName({
      combinedDisplayName: sourceProduct.combined_display_name,
      displayName,
      cpuText,
      memoryText,
    }) || displayName
  );
});
const mobileProductSpecFamilies = computed(() => {
  const families = new Map();

  mobileProductSpecRows.value.forEach((row) => {
    if (!row.baseName || !row.cpuSlug || !row.memorySlug) {
      return;
    }

    const key = row.baseName.toLowerCase();
    if (!families.has(key)) {
      families.set(key, {
        key,
        label: row.baseName,
        baseName: row.baseName,
        products: [],
        originalIndex: row.originalIndex,
      });
    }

    families.get(key).products.push(row);
  });

  return Array.from(families.values()).sort(
    (left, right) => left.originalIndex - right.originalIndex,
  );
});
const mobileProductGroups = computed(() => {
  const groups = new Map();

  mobileProductSpecRows.value.forEach((row) => {
    if (!row.baseName || !row.cpuSlug || !row.memorySlug) {
      return;
    }

    const key = `${row.baseName.toLowerCase()}|${row.cpuSlug}`;
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        label: row.baseName ? `${row.baseName}-${row.cpuSlug}` : row.cpuSlug,
        baseName: row.baseName,
        cpuText: row.cpuText,
        cpuValue: row.cpuValue,
        note: row.note,
        products: [],
        originalIndex: row.originalIndex,
      });
    }

    const group = groups.get(key);
    group.products.push(row);
    if (!group.note && row.note) {
      group.note = row.note;
    }
  });

  return Array.from(groups.values())
    .map((group) => ({
      ...group,
      products: group.products.slice().sort(sortMobileSpecRows),
    }))
    .sort((left, right) => {
      const leftValid = Number.isFinite(left.cpuValue);
      const rightValid = Number.isFinite(right.cpuValue);

      if (leftValid && rightValid && left.cpuValue !== right.cpuValue) {
        return left.cpuValue - right.cpuValue;
      }

      if (leftValid !== rightValid) {
        return leftValid ? -1 : 1;
      }

      return left.originalIndex - right.originalIndex;
    });
});
const hasMobileProductGroups = computed(
  () =>
    visibleProducts.value.length > 0 &&
    mobileProductGroups.value.length > 0 &&
    mobileProductSpecRows.value.every(
      (row) => row.baseName && row.cpuSlug && row.memorySlug,
    ),
);
const selectedMobileSpecFamily = computed(() => {
  if (!hasMobileProductGroups.value) {
    return null;
  }

  const selectedGroup = selectedMobileProductGroup.value;
  return (
    mobileProductSpecFamilies.value.find(
      (family) => family.baseName === selectedGroup?.baseName,
    ) ||
    mobileProductSpecFamilies.value[0] ||
    null
  );
});
const selectedMobileProductGroup = computed(() => {
  if (!hasMobileProductGroups.value) {
    return null;
  }

  const selectedId = Number(selectedProductId.value || 0);
  return (
    mobileProductGroups.value.find((group) =>
      group.products.some((row) => row.productId === selectedId),
    ) ||
    mobileProductGroups.value[0] ||
    null
  );
});
const selectedMobileProductModelLabel = computed(() => {
  if (hasMobileProductGroups.value) {
    return selectedMobileProductGroup.value?.label || "";
  }
  const selectedId = Number(selectedProductId.value || 0);
  const matched = mobileProductSpecRows.value.find(
    (row) => row.productId === selectedId,
  );
  return matched?.baseName || selectedProductDisplayName.value;
});
const selectedMobileCpuLabel = computed(
  () =>
    selectedMobileProductGroup.value?.cpuText ||
    selectedMobileProductGroup.value?.label ||
    "",
);
const mobileSpecPickerLabel = computed(() =>
  hasMobileProductGroups.value
    ? selectedMobileSpecFamily.value?.label || ""
    : selectedMobileProductModelLabel.value,
);
const selectedMobileMemoryLabel = computed(() => {
  const selectedId = Number(selectedProductId.value || 0);
  const matched = selectedMobileProductGroup.value?.products.find(
    (row) => row.productId === selectedId,
  );
  return matched?.memoryText || "";
});
const mobileGroupedCpuOptions = computed(() =>
  mobileProductGroups.value
    .filter(
      (group) =>
        !selectedMobileSpecFamily.value ||
        group.baseName === selectedMobileSpecFamily.value.baseName,
    )
    .map((group) => ({
      id: group.key,
      label: group.cpuText || group.label,
    })),
);
const mobileSpecFamilyOptions = computed(() =>
  mobileProductSpecFamilies.value.map((family) => ({
    id: family.key,
    label: family.label,
  })),
);
const mobileGroupedMemoryOptions = computed(
  () =>
    selectedMobileProductGroup.value?.products.map((row) => ({
      id: String(row.productId),
      label:
        row.memoryText ||
        row.memorySlug ||
        resolveProductDisplayName(row.product),
    })) || [],
);

const mobileCpuDrawer = ref(false);
const mobileMemDrawer = ref(false);
const mobileSpecFamilyDrawer = ref(false);
const mobileGroupedCpuDrawer = ref(false);
const mobileGroupedMemoryDrawer = ref(false);
const mobileSingleConfigDrawer = ref(false);
const mobileSingleConfig = ref(null);

async function openMobileProductGroupDrawer() {
  await openMobileProductDrawer();

  if (hasMobileProductGroups.value) {
    mobilePendingProductGroupKey.value =
      selectedMobileProductGroup.value?.key ||
      mobileProductGroups.value[0]?.key ||
      "";
  } else {
    mobilePendingProductGroupKey.value = "";
  }
}

function openMobileCpuDrawer() {
  if (!cpuConfig.value?.options?.length) {
    // 静默 return 会让用户点了毫无反应，误以为按钮坏了；明确告知当前无可选项。
    MessagePlugin.warning("当前机型没有可选的 CPU 规格");
    return;
  }
  mobileCpuDrawer.value = true;
}

function openMobileMemDrawer() {
  if (!memConfig.value?.options?.length) {
    MessagePlugin.warning("当前机型没有可选的内存规格");
    return;
  }
  mobileMemDrawer.value = true;
}

function openMobileGroupedCpuDrawer() {
  if (!mobileGroupedCpuOptions.value.length) {
    MessagePlugin.warning("当前商品没有可选的 CPU 规格");
    return;
  }
  mobileGroupedCpuDrawer.value = true;
}

function openMobileSpecPicker() {
  if (hasMobileProductGroups.value) {
    if (!mobileSpecFamilyOptions.value.length) {
      MessagePlugin.warning("当前商品没有可选的规格");
      return;
    }
    mobileSpecFamilyDrawer.value = true;
    return;
  }

  void openMobileProductGroupDrawer();
}

function openMobileGroupedMemoryDrawer() {
  if (!mobileGroupedMemoryOptions.value.length) {
    MessagePlugin.warning("当前商品没有可选的内存规格");
    return;
  }
  mobileGroupedMemoryDrawer.value = true;
}

function findPreferredMobileProductId(group) {
  if (!group?.products?.length) {
    return 0;
  }

  const selectedId = Number(selectedProductId.value || 0);
  const currentMemory =
    mobileProductSpecRows.value.find((row) => row.productId === selectedId)
      ?.memorySlug || "";
  const matchedMemory = currentMemory
    ? group.products.find((row) => row.memorySlug === currentMemory)
    : null;

  return matchedMemory?.productId || group.products[0]?.productId || 0;
}

function findPreferredMobileGroup(groups) {
  if (!groups?.length) {
    return null;
  }

  const selectedId = Number(selectedProductId.value || 0);
  const currentRow = mobileProductSpecRows.value.find(
    (row) => row.productId === selectedId,
  );
  const currentCpuSlug = currentRow?.cpuSlug || "";
  const matchedCpu = currentCpuSlug
    ? groups.find((group) =>
        group.products.some((row) => row.cpuSlug === currentCpuSlug),
      )
    : null;

  return matchedCpu || groups[0];
}

function confirmMobileGroupedMemorySelection(productId) {
  mobileGroupedMemoryDrawer.value = false;
  const nextProductId = Number(productId || 0);
  if (nextProductId > 0) {
    selectProduct(nextProductId, { syncRoute: true });
  }
}

function confirmMobileGroupedCpuSelection(groupKey) {
  mobileGroupedCpuDrawer.value = false;
  const targetGroup = mobileProductGroups.value.find(
    (group) => group.key === groupKey,
  );
  const nextProductId = findPreferredMobileProductId(targetGroup);
  if (nextProductId > 0) {
    selectProduct(nextProductId, { syncRoute: true });
  }
}

function confirmMobileSpecFamilySelection(familyKey) {
  mobileSpecFamilyDrawer.value = false;
  const targetFamily = mobileProductSpecFamilies.value.find(
    (family) => family.key === familyKey,
  );
  if (!targetFamily) {
    return;
  }

  const targetGroups = mobileProductGroups.value.filter(
    (group) => group.baseName === targetFamily.baseName,
  );
  const targetGroup = findPreferredMobileGroup(targetGroups);
  const nextProductId = findPreferredMobileProductId(targetGroup);
  if (nextProductId > 0) {
    selectProduct(nextProductId, { syncRoute: true });
  }
}

function confirmMobileProductSelection() {
  if (hasMobileProductGroups.value) {
    const targetGroup =
      mobileProductGroups.value.find(
        (group) => group.key === mobilePendingProductGroupKey.value,
      ) ||
      selectedMobileProductGroup.value ||
      mobileProductGroups.value[0];
    const nextProductId = findPreferredMobileProductId(targetGroup);
    if (nextProductId > 0) {
      selectProduct(nextProductId, { syncRoute: true });
    }

    mobileProductDrawer.value = false;
    return;
  }

  if (mobilePendingProductId.value) {
    confirmRawMobileProductSelection();
  } else {
    mobileProductDrawer.value = false;
  }
}

function confirmCpuSelection(optionId) {
  mobileCpuDrawer.value = false;
  if (optionId && cpuConfig.value) {
    configForm[cpuConfig.value.key] = optionId;
  }
}

function confirmMemSelection(optionId) {
  mobileMemDrawer.value = false;
  if (optionId && memConfig.value) {
    configForm[memConfig.value.key] = optionId;
  }
}

function selectDesktopMachineSpec(row) {
  if (row.productId) {
    selectProduct(row.productId, { syncRoute: true });
  }
}

function selectedOptionLabel(cfg) {
  if (!cfg?.options?.length) return "";
  const option = cfg.options.find((item) => item.id === configForm[cfg.key]);
  return option?.label || cfg.options[0]?.label || "";
}

function openMobileSingleConfigDrawer(cfg) {
  if (!cfg?.options?.length) {
    MessagePlugin.warning(`当前机型没有可选的${cfg?.label || "配置项"}`);
    return;
  }
  mobileSingleConfig.value = cfg;
  mobileSingleConfigDrawer.value = true;
}

function closeMobileSingleConfigDrawer() {
  mobileSingleConfigDrawer.value = false;
}

function confirmMobileSingleConfigSelection(optionId) {
  const cfg = mobileSingleConfig.value;
  mobileSingleConfigDrawer.value = false;
  if (optionId && cfg) {
    configForm[cfg.key] = optionId;
  }
}

function isRangeConfig(cfg) {
  return cfg.isNumber;
}

function openMobileOsDrawer() {
  if (!osGroups.value.length) {
    MessagePlugin.warning("当前商品没有可选的操作系统");
    return;
  }
  mobileOsDrawer.value = true;
}

function confirmOsSelection(groupId, versionId) {
  mobileOsDrawer.value = false;
  const group = osGroups.value.find((g) => g.id === groupId);
  if (group) {
    selectOsGroup(group);
    if (versionId) {
      configForm.os = versionId;
    }
  }
}

async function handleDesktopGroupChange(value) {
  const nextGroupId = Number(value || 0);
  if (!nextGroupId) {
    return;
  }

  try {
    await switchGroup(nextGroupId);
  } catch {
    // 品类目录请求失败会 throw；桌面下拉切换此前是 void 调用，失败后
    // 下拉已切到新值但列表还是旧数据，且控制台只有 unhandled rejection。
    MessagePlugin.warning("目录加载失败，请重新选择地区");
  }
}

function handleDesktopChildChange(value) {
  const nextChildId = Number(value || 0);
  if (!nextChildId) {
    return;
  }

  switchChild(nextChildId);
}
</script>

<style scoped lang="scss" src="./page.scss"></style>

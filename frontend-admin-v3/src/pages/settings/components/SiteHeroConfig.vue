<template>
  <t-card :bordered="false" :loading="loading" class="slides-config-card">
    <t-alert v-if="isDirty" theme="warning" message="当前存在未保存修改。保存后官网首页最长约 2 分钟同步。" />
    <t-alert v-if="!canManageSite" theme="info" message="当前账号只有站点查看权限，首页配置为只读，无法编辑与保存。" />

    <section class="slides-section">
      <div class="slides-section-head">
        <div>
          <h2>轮播项（{{ heroForm.slides.length }} / {{ maxSlides }}）</h2>
          <p>每个轮播项包含导航名称、主标题、描述和两个按钮。</p>
        </div>
        <t-space v-if="canManageSite">
          <t-button variant="outline" @click="resetSlidesToDefault">恢复默认</t-button>
          <t-button theme="primary" variant="outline" :disabled="heroForm.slides.length >= maxSlides" @click="addSlide">
            <template #icon><add-icon /></template>
            新增轮播
          </t-button>
        </t-space>
      </div>

      <article v-for="(slide, index) in heroForm.slides" :key="`slide-${index}`" class="slide-card">
        <div class="slide-card-head">
          <span class="slide-card-head__index">{{ index + 1 }}</span>
          <strong class="slide-card-head__name">{{ slide.rail_title || '未命名轮播' }}</strong>
          <div v-if="canManageSite" class="slide-card-head__actions">
            <t-button
              variant="text"
              size="small"
              :aria-label="`上移第 ${index + 1} 个轮播`"
              :disabled="index === 0"
              @click="moveSlide(index, -1)"
            >
              <template #icon><arrow-up-icon /></template>
            </t-button>
            <t-button
              variant="text"
              size="small"
              :aria-label="`下移第 ${index + 1} 个轮播`"
              :disabled="index === heroForm.slides.length - 1"
              @click="moveSlide(index, 1)"
            >
              <template #icon><arrow-down-icon /></template>
            </t-button>
            <t-button
              theme="danger"
              variant="text"
              size="small"
              :aria-label="`删除第 ${index + 1} 个轮播`"
              :disabled="heroForm.slides.length <= 1"
              @click="removeSlide(index)"
            >
              <template #icon><delete-icon /></template>
            </t-button>
          </div>
        </div>

        <div class="slide-body">
          <div class="slide-row">
            <label class="slide-label">标题</label>
            <t-input
              v-model="slide.rail_title"
              maxlength="20"
              aria-label="导航名称"
              placeholder="导航名"
              class="slide-field--sm"
            />
            <t-input
              v-model="slide.title"
              maxlength="80"
              aria-label="主标题"
              placeholder="主标题，例如：官网焕新 · 云上新体验"
              class="slide-field--lg"
            />
          </div>

          <div class="slide-row">
            <label class="slide-label">按钮</label>
            <div class="slide-btn-group">
              <span class="slide-btn-group__tag">主</span>
              <t-input
                v-model="slide.primary_text"
                maxlength="20"
                aria-label="主按钮文案"
                placeholder="按钮文案"
                class="slide-field--sm"
              />
              <t-input
                v-model="slide.primary_path"
                maxlength="255"
                aria-label="主按钮跳转路径"
                placeholder="跳转路径"
                class="slide-field--lg"
              />
            </div>
            <div class="slide-btn-group">
              <span class="slide-btn-group__tag">次</span>
              <t-input
                v-model="slide.secondary_text"
                maxlength="20"
                aria-label="次按钮文案"
                placeholder="按钮文案"
                class="slide-field--sm"
              />
              <t-input
                v-model="slide.secondary_path"
                maxlength="255"
                aria-label="次按钮跳转路径"
                placeholder="跳转路径"
                class="slide-field--lg"
              />
            </div>
          </div>

          <div class="slide-row">
            <label class="slide-label">描述</label>
            <t-textarea
              v-model="slide.desc"
              :autosize="{ minRows: 2, maxRows: 4 }"
              maxlength="300"
              aria-label="轮播描述"
              placeholder="轮播描述文案"
              class="slide-field--full"
            />
          </div>

          <div class="slide-row">
            <label class="slide-label">视频</label>
            <div
              class="slide-video-selector"
              role="button"
              tabindex="0"
              :aria-label="`选择第 ${index + 1} 个轮播的背景视频`"
              @click="openVideoDrawer(index)"
              @keydown.enter.prevent="openVideoDrawer(index)"
              @keydown.space.prevent="openVideoDrawer(index)"
            >
              <video-icon />
              <span v-if="slide.video" class="slide-video-selector__name">{{ videoDisplayName(slide.video) }}</span>
              <span v-else class="slide-video-selector__placeholder">点击选择背景视频</span>
              <chevron-right-icon />
            </div>
          </div>
        </div>
      </article>
    </section>

    <section class="slides-section">
      <div class="slides-section-head">
        <div>
          <h2>底部特色卡片（{{ heroForm.features.length }} / {{ maxFeatures }}）</h2>
          <p>用于官网首页轮播下方的横向卡片组。</p>
        </div>
        <t-space v-if="canManageSite">
          <t-button variant="outline" @click="resetFeaturesToDefault">恢复默认</t-button>
          <t-button
            theme="primary"
            variant="outline"
            :disabled="heroForm.features.length >= maxFeatures"
            @click="addFeature"
          >
            <template #icon><add-icon /></template>
            新增卡片
          </t-button>
        </t-space>
      </div>

      <div class="feature-list">
        <article v-for="(feature, index) in heroForm.features" :key="`feature-${index}`" class="feature-card">
          <div v-if="canManageSite" class="feature-card-sort">
            <t-button
              variant="text"
              :aria-label="`上移功能：${feature.title || `第 ${index + 1} 项`}`"
              :disabled="index === 0"
              @click="moveFeature(index, -1)"
            >
              <template #icon><arrow-up-icon /></template>
            </t-button>
            <t-button
              variant="text"
              :aria-label="`下移功能：${feature.title || `第 ${index + 1} 项`}`"
              :disabled="index === heroForm.features.length - 1"
              @click="moveFeature(index, 1)"
            >
              <template #icon><arrow-down-icon /></template>
            </t-button>
          </div>
          <t-input v-model="feature.kicker" maxlength="20" placeholder="标签 kicker" />
          <t-input v-model="feature.title" maxlength="50" placeholder="标题" />
          <t-textarea
            v-model="feature.desc"
            :autosize="{ minRows: 2, maxRows: 3 }"
            maxlength="120"
            placeholder="描述"
          />
          <t-input v-model="feature.path" maxlength="255" placeholder="跳转路径，可选" />
          <t-button
            v-if="canManageSite"
            theme="danger"
            variant="text"
            :disabled="heroForm.features.length <= 1"
            @click="removeFeature(index)"
          >
            <template #icon><delete-icon /></template>
            删除
          </t-button>
        </article>
      </div>
    </section>
  </t-card>

  <div class="settings-bottom-actions">
    <t-button theme="primary" :loading="saving" :disabled="!canManageSite" @click="saveHero">
      <template #icon><check-icon /></template>
      保存设置
    </t-button>
  </div>

  <t-drawer
    :visible="videoDrawerVisible"
    :header="videoDrawerTitle"
    size="min(560px, calc(100vw - 32px))"
    placement="right"
    :footer="null"
    @close="closeVideoDrawer"
  >
    <t-radio-group v-model="videoDrawerMode" variant="default-filled" class="video-drawer-tabs">
      <t-radio-button value="select">选择已有视频</t-radio-button>
      <t-radio-button value="url">输入视频 URL</t-radio-button>
    </t-radio-group>

    <div v-if="videoDrawerMode === 'select'" class="video-drawer-grid">
      <div
        v-for="opt in heroVideoOptions"
        :key="opt.value"
        class="video-drawer-card"
        :class="{ 'is-selected': videoDrawerCurrentSrc === opt.value }"
        role="button"
        tabindex="0"
        :aria-pressed="videoDrawerCurrentSrc === opt.value"
        @click="selectVideoFromDrawer(opt.value)"
        @keydown.enter.prevent="selectVideoFromDrawer(opt.value)"
        @keydown.space.prevent="selectVideoFromDrawer(opt.value)"
        @mouseenter="onVideoCardEnter($event)"
        @mouseleave="onVideoCardLeave($event)"
      >
        <video
          class="video-drawer-card__video"
          :src="resolveHeroVideoSrc(opt.value)"
          muted
          loop
          playsinline
          preload="metadata"
        ></video>
        <div class="video-drawer-card__overlay">
          <check-circle-filled-icon v-if="videoDrawerCurrentSrc === opt.value" class="video-drawer-card__check" />
          <span class="video-drawer-card__name">{{ opt.filename || opt.value.split('/').pop() }}</span>
          <span v-if="opt.size" class="video-drawer-card__size">{{ formatFileSize(opt.size) }}</span>
        </div>
      </div>
      <div v-if="!heroVideoOptions.length" class="video-drawer-empty">后端 uploads/hero-videos 目录暂无视频</div>
    </div>

    <div v-else class="video-drawer-url-mode">
      <p class="video-drawer-url-mode__hint">输入第三方视频 URL，支持 mp4/webm 格式。</p>
      <t-input
        v-model="videoUrlInput"
        placeholder="https://example.com/videos/bg.mp4"
        clearable
        @keyup.enter="confirmVideoUrl"
      />
      <div v-if="videoUrlPreview" class="video-drawer-url-mode__preview">
        <video :src="videoUrlPreview" muted loop playsinline preload="metadata" controls></video>
      </div>
      <t-space class="video-drawer-url-mode__actions">
        <t-button theme="primary" @click="confirmVideoUrl">
          <template #icon><check-icon /></template>
          确定
        </t-button>
      </t-space>
    </div>
  </t-drawer>
</template>
<script setup lang="ts">
import {
  AddIcon,
  ArrowDownIcon,
  ArrowUpIcon,
  CheckCircleFilledIcon,
  CheckIcon,
  ChevronRightIcon,
  DeleteIcon,
  VideoIcon,
} from 'tdesign-icons-vue-next';
import { DialogPlugin } from 'tdesign-vue-next/es/dialog/plugin';
import { MessagePlugin } from 'tdesign-vue-next/es/message/plugin';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

import type { HomeHeroFeature, HomeHeroPayload, HomeHeroSlide } from '@/api/admin';
import { adminApi } from '@/api/admin';
import { resolveApiAssetUrl } from '@/utils/apiAssetUrl';
import { toRecord } from '@/utils/format';
import { errorMessage } from '@/utils/userMessage';

// 首页装修（轮播 + 底部特色卡片）：原先内嵌在 settings/index.vue 的 v-else 分支里，
// 与通用设置共用 1600 行单文件。抽出后本组件只负责 site_hero 这一套模板与状态。
defineOptions({ name: 'AdminSiteHeroConfig' });

// reloadToken 变化即重新拉取：父级在切回 site_hero tab 时自增该值，
// 用 prop 而不是模板 ref 调用，避免组件实例类型耦合。
const props = defineProps<{ canManageSite: boolean; reloadToken: number }>();
const emit = defineEmits<{ (e: 'dirty-change', value: boolean): void }>();

const loading = ref(false);
const saving = ref(false);
const maxSlides = 5;
const maxFeatures = 5;
const heroDefaults = reactive<{ slides: HomeHeroSlide[]; features: HomeHeroFeature[] }>({ slides: [], features: [] });
const heroForm = reactive<{ slides: HomeHeroSlide[]; features: HomeHeroFeature[] }>({ slides: [], features: [] });
const heroVideoOptions = ref<Array<{ label: string; value: string; filename?: string; size?: number }>>([]);
const heroSnapshot = ref('');
const videoDrawerVisible = ref(false);
const videoDrawerSlideIndex = ref(-1);
const videoDrawerCurrentSrc = ref('');
const videoDrawerMode = ref<'select' | 'url'>('select');
const videoUrlInput = ref('');
const videoUrlPreview = ref('');

const videoDrawerTitle = computed(() => {
  if (videoDrawerSlideIndex.value < 0) return '选择背景视频';
  const slide = heroForm.slides[videoDrawerSlideIndex.value];
  return `背景视频 · 第 ${videoDrawerSlideIndex.value + 1} 项${slide?.rail_title ? `（${slide.rail_title}）` : ''}`;
});

async function loadHero() {
  loading.value = true;
  try {
    const data = await adminApi.siteHero.get();
    applyHeroPayload(data);
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '加载首页 Banner 失败'));
  } finally {
    loading.value = false;
  }
}

function applyHeroPayload(payload: HomeHeroPayload = {}) {
  heroDefaults.slides = cloneList(payload.defaults?.slides);
  heroDefaults.features = cloneList(payload.defaults?.features);
  heroVideoOptions.value = normalizeHeroVideoOptions(payload.options?.videos);
  heroForm.slides = cloneList(payload.slides).length ? cloneList(payload.slides) : cloneList(heroDefaults.slides);
  heroForm.features = cloneList(payload.features).length
    ? cloneList(payload.features)
    : cloneList(heroDefaults.features);
  heroSnapshot.value = JSON.stringify(heroForm);
}

async function saveHero() {
  if (!props.canManageSite) {
    MessagePlugin.warning('当前账号无站点管理权限');
    return;
  }

  if (!validateHero()) return;
  saving.value = true;
  try {
    const payload = {
      slides: heroForm.slides.map((item) => ({ ...item })),
      features: heroForm.features.map((item) => ({ ...item })),
    };
    const response = await adminApi.siteHero.save(payload);
    applyHeroPayload({
      ...response,
      slides: response.slides || payload.slides,
      features: response.features || payload.features,
    });
    MessagePlugin.success('首页 Banner 已保存');
  } catch (error) {
    MessagePlugin.error(errorMessage(error, '保存首页 Banner 失败'));
  } finally {
    saving.value = false;
  }
}

function validateHero() {
  for (const [index, slide] of heroForm.slides.entries()) {
    for (const [key, label] of [
      ['rail_title', '导航名称'],
      ['title', '主标题'],
      ['desc', '描述'],
      ['primary_text', '主按钮文案'],
      ['primary_path', '主按钮跳转'],
      ['secondary_text', '次按钮文案'],
      ['secondary_path', '次按钮跳转'],
    ] as Array<[keyof HomeHeroSlide, string]>) {
      if (!String(slide[key] ?? '').trim()) {
        MessagePlugin.warning(`第 ${index + 1} 个轮播项的${label}不能为空`);
        return false;
      }
    }
  }
  for (const [index, feature] of heroForm.features.entries()) {
    for (const [key, label] of [
      ['kicker', '标签'],
      ['title', '标题'],
      ['desc', '描述'],
    ] as Array<[keyof HomeHeroFeature, string]>) {
      if (!String(feature[key] ?? '').trim()) {
        MessagePlugin.warning(`第 ${index + 1} 张卡片的${label}不能为空`);
        return false;
      }
    }
  }
  return true;
}

function buildBlankSlide(): HomeHeroSlide {
  return {
    rail_title: '新轮播项',
    title: '',
    desc: '',
    primary_text: '立即体验',
    primary_path: '/products',
    secondary_text: '查看详情',
    secondary_path: '/about',
    video: heroVideoOptions.value[0]?.value || '',
  };
}

function buildBlankFeature(): HomeHeroFeature {
  return { kicker: '新卡片', title: '', desc: '', path: '' };
}

function addSlide() {
  if (heroForm.slides.length < maxSlides) heroForm.slides.push(buildBlankSlide());
}

function removeSlide(index: number) {
  if (heroForm.slides.length > 1) heroForm.slides.splice(index, 1);
}

function moveSlide(index: number, offset: number) {
  moveItem(heroForm.slides, index, offset);
}

function addFeature() {
  if (heroForm.features.length < maxFeatures) heroForm.features.push(buildBlankFeature());
}

function removeFeature(index: number) {
  if (heroForm.features.length > 1) heroForm.features.splice(index, 1);
}

function moveFeature(index: number, offset: number) {
  moveItem(heroForm.features, index, offset);
}

function resetSlidesToDefault() {
  const dialog = DialogPlugin.confirm({
    header: '恢复默认轮播',
    body: '恢复默认会覆盖当前轮播项，下次保存才会写入数据库。',
    confirmBtn: { content: '确定', theme: 'primary' },
    cancelBtn: '取消',
    onConfirm: () => {
      heroForm.slides = cloneList(heroDefaults.slides);
      dialog.destroy();
    },
  });
}

function resetFeaturesToDefault() {
  const dialog = DialogPlugin.confirm({
    header: '恢复默认卡片',
    body: '恢复默认会覆盖当前特色卡片，下次保存才会写入数据库。',
    confirmBtn: { content: '确定', theme: 'primary' },
    cancelBtn: '取消',
    onConfirm: () => {
      heroForm.features = cloneList(heroDefaults.features);
      dialog.destroy();
    },
  });
}

function normalizeHeroVideoOptions(list: unknown) {
  if (!Array.isArray(list)) return [];
  return list
    .map((item) => {
      const record = toRecord(item);
      const value = toManagedRelativePath(String(record.path || record.url || '').trim());
      if (!value) return null;
      const filename = String(record.filename || value.split('/').pop() || value);
      const size = Number(record.size || 0);
      return {
        label: size > 0 ? `${filename} · ${formatFileSize(size)}` : filename,
        value,
        filename,
        size,
      };
    })
    .filter(Boolean) as Array<{ label: string; value: string; filename?: string; size?: number }>;
}

// 后端可能仅返回完整 URL：托管资源还原为相对路径，与后端归一化的存储格式保持一致
function toManagedRelativePath(value: string) {
  const managed = value.match(/^(?:https?:\/\/[^/]+)?\/((?:uploads|media)\/.*)$/i);
  return managed ? `/${managed[1]}` : value;
}

function formatFileSize(size: number) {
  if (!Number.isFinite(size) || size <= 0) return '';
  if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(1)} MB`;
  if (size >= 1024) return `${Math.round(size / 1024)} KB`;
  return `${size} B`;
}

function resolveHeroVideoSrc(src: string) {
  return resolveApiAssetUrl(src, import.meta.env.VITE_API_BASE_URL);
}

function onVideoCardEnter(event: MouseEvent) {
  const el = event.currentTarget as HTMLElement;
  const video = el.querySelector('video') as HTMLVideoElement | null;
  if (video) {
    video.currentTime = 0;
    video.play().catch(() => {});
  }
}

function onVideoCardLeave(event: MouseEvent) {
  const el = event.currentTarget as HTMLElement;
  const video = el.querySelector('video') as HTMLVideoElement | null;
  if (video) {
    video.pause();
    video.currentTime = 0;
  }
}

function openVideoDrawer(slideIndex: number) {
  videoDrawerSlideIndex.value = slideIndex;
  const current = String(heroForm.slides[slideIndex]?.video || '');
  videoDrawerCurrentSrc.value = current;
  const isPredefined = heroVideoOptions.value.some((opt) => opt.value === current);
  if (current && !isPredefined) {
    videoDrawerMode.value = 'url';
    videoUrlInput.value = current;
    videoUrlPreview.value = current;
  } else {
    videoDrawerMode.value = 'select';
    videoUrlInput.value = '';
    videoUrlPreview.value = '';
  }
  videoDrawerVisible.value = true;
}

function closeVideoDrawer() {
  videoDrawerVisible.value = false;
  videoDrawerSlideIndex.value = -1;
  videoUrlInput.value = '';
  videoUrlPreview.value = '';
}

function selectVideoFromDrawer(value: string) {
  const idx = videoDrawerSlideIndex.value;
  if (idx >= 0 && idx < heroForm.slides.length) {
    heroForm.slides[idx].video = heroForm.slides[idx].video === value ? '' : value;
  }
  closeVideoDrawer();
}

function confirmVideoUrl() {
  const url = videoUrlInput.value.trim();
  if (!url) {
    MessagePlugin.warning('请输入视频 URL');
    return;
  }
  if (!/^https?:\/\/.+/.test(url)) {
    MessagePlugin.warning('请输入以 http:// 或 https:// 开头的有效 URL');
    return;
  }
  const idx = videoDrawerSlideIndex.value;
  if (idx >= 0 && idx < heroForm.slides.length) {
    heroForm.slides[idx].video = url;
  }
  MessagePlugin.success('已设置第三方视频 URL');
  closeVideoDrawer();
}

function videoDisplayName(src: string) {
  if (!src) return '';
  const opt = heroVideoOptions.value.find((item) => item.value === src);
  if (opt?.size) return `${opt.filename || src.split('/').pop()} · ${formatFileSize(opt.size)}`;
  if (opt) return opt.filename || src.split('/').pop() || src;
  // External URL — show shortened domain path
  try {
    const url = new URL(src);
    const path = url.pathname.split('/').filter(Boolean).pop() || url.hostname;
    return `外部 · ${path}`;
  } catch {
    return src.split('/').pop() || src;
  }
}

function moveItem<T>(list: T[], index: number, offset: number) {
  const target = index + offset;
  if (target < 0 || target >= list.length) return;
  const [item] = list.splice(index, 1);
  list.splice(target, 0, item);
}

function cloneList<T extends Record<string, unknown>>(list?: T[]) {
  return Array.isArray(list) ? list.map((item) => ({ ...item })) : [];
}

watch(videoUrlInput, (value) => {
  if (/^https?:\/\/.+/.test(value)) {
    videoUrlPreview.value = value;
  } else {
    videoUrlPreview.value = '';
  }
});

// 脏状态：加载中或尚未取到快照时不算未保存修改，避免首访误拦离开。
const isDirty = computed(
  () => !loading.value && heroSnapshot.value !== '' && JSON.stringify(heroForm) !== heroSnapshot.value,
);
watch(isDirty, (value) => emit('dirty-change', value), { immediate: true });

onMounted(() => {
  void loadHero();
});

watch(
  () => props.reloadToken,
  () => {
    // 父级 onMounted 也会自增一次 token，与本组件首次挂载的加载同一拍；此时自身的请求已在飞，
    // 跳过可避免首访对 siteHero.get() 连打两次。
    if (loading.value) return;
    void loadHero();
  },
);

// 子组件被销毁时把脏状态清回 false，否则父级的 pageDirty 会一直为真、拦住后续所有离开。
onBeforeUnmount(() => {
  emit('dirty-change', false);
});
</script>
<style lang="less" scoped>
// `.slides-section-head p` 原与父级 `.field-info p` 合并在 settings/index.less 的一条选择器里，
// hero 抽出后各归各家，父级样式表不再背首页装修的样式。
.slides-section-head p {
  margin: 0;
  color: var(--td-text-color-secondary);
  line-height: 1.7;
}

.slides-config-card {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-xl);
}

.slides-section {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.slides-section + .slides-section {
  margin-top: var(--td-comp-margin-xl);
}

.slides-section-head,
.slide-card-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--td-comp-margin-l);
  flex-wrap: wrap;
}

.slides-section-head h2 {
  margin: 0;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-size-4, 16px);
}

.slide-card,
.feature-card {
  padding: 14px 16px;
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-large);
  background: var(--td-bg-color-page);
}

.slide-card-head {
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
  margin-bottom: var(--td-comp-margin-m);
  padding-bottom: 10px;
  border-bottom: 1px solid var(--td-component-border);
}

.slide-card-head__index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  flex-shrink: 0;
  border-radius: var(--td-radius-circle);
  background: var(--td-brand-color-light);
  color: var(--td-brand-color);
  font-size: var(--td-font-size-size-1, 12px);
  font-weight: 600;
}

.slide-card-head__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-size-3, 14px);
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slide-card-head__actions {
  display: flex;
  flex-shrink: 0;
  gap: var(--td-comp-margin-xxs);
  margin-left: auto;
}

.slide-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.slide-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.slide-label {
  flex-shrink: 0;
  width: 36px;
  padding-top: 5px;
  color: var(--td-text-color-secondary);
  font-size: var(--td-font-size-size-1, 12px);
  font-weight: 500;
  text-align: right;
  user-select: none;
}

.slide-field--sm {
  width: 120px;
  flex-shrink: 0;
}

.slide-field--lg {
  flex: 1;
  min-width: 0;
}

.slide-field--full {
  flex: 1;
  min-width: 0;
}

.slide-btn-group {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.slide-btn-group__tag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  border-radius: var(--td-radius-default);
  background: var(--td-brand-color-1);
  color: var(--td-brand-color);
  font-size: var(--td-font-size-size-1, 12px);
  font-weight: 600;
}

.slide-video-selector {
  display: flex;
  align-items: center;
  gap: var(--td-comp-margin-s);
  padding: 6px 12px;
  border: 1px solid var(--td-component-border);
  border-radius: var(--td-radius-medium);
  background: var(--td-bg-color-page);
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
  flex: 1;
  min-width: 0;
}

.slide-video-selector:hover {
  border-color: var(--td-brand-color);
  box-shadow: var(--caiwu-ring-brand);
}

.slide-video-selector__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--td-text-color-primary);
  font-size: var(--td-font-size-size-3, 14px);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.slide-video-selector__placeholder {
  flex: 1;
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-size-3, 14px);
}

.video-drawer-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 14px;
}

.video-drawer-card {
  position: relative;
  border-radius: var(--td-radius-large);
  border: 2px solid var(--td-component-border);
  background: var(--caiwu-media-canvas);
  overflow: hidden;
  cursor: pointer;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    transform 0.18s ease;
}

.video-drawer-card:hover {
  border-color: var(--td-brand-color-light);
  box-shadow: var(--td-shadow-3);
  transform: translateY(-2px);
}

.video-drawer-card.is-selected {
  border-color: var(--td-brand-color);
  box-shadow: var(--caiwu-ring-brand-strong), var(--td-shadow-3);
}

.video-drawer-card__video {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  object-fit: cover;
}

.video-drawer-card__overlay {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  background: var(--caiwu-mask-overlay-strong);
  backdrop-filter: blur(4px);
  pointer-events: none;
}

.video-drawer-card__check {
  flex-shrink: 0;
  color: var(--td-brand-color);
  font-size: var(--td-font-size-size-4, 16px);
}

.video-drawer-card__name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  color: var(--td-font-white-1);
  font-size: var(--td-font-size-size-1, 12px);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.video-drawer-card__size {
  flex-shrink: 0;
  color: var(--caiwu-text-on-mask-secondary);
  font-size: var(--td-font-size-size-1, 12px);
}

.video-drawer-empty {
  grid-column: 1 / -1;
  padding: 48px 24px;
  text-align: center;
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-size-3, 14px);
}

.video-drawer-tabs {
  margin-bottom: var(--td-comp-margin-l);
  width: 100%;
}

.video-drawer-url-mode {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.video-drawer-url-mode__hint {
  margin: 0;
  color: var(--td-text-color-placeholder);
  font-size: var(--td-font-size-size-3, 14px);
  line-height: 1.5;
}

.video-drawer-url-mode__preview {
  border-radius: var(--td-radius-medium);
  overflow: hidden;
  background: var(--caiwu-media-canvas);
}

.video-drawer-url-mode__preview video {
  display: block;
  width: 100%;
  max-height: 280px;
  object-fit: contain;
}

.video-drawer-url-mode__actions {
  display: flex;
  justify-content: flex-end;
}

.feature-list {
  display: flex;
  flex-direction: column;
  gap: var(--td-comp-margin-m);
}

.feature-card {
  display: grid;
  grid-template-columns: 80px minmax(120px, 0.8fr) minmax(160px, 1fr) minmax(220px, 1.2fr) minmax(160px, 1fr) 80px;
  gap: 10px;
  align-items: start;
}

.feature-card-sort {
  display: flex;
  gap: var(--td-comp-margin-xs);
}

@media (max-width: 1200px) {
  .feature-card {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 1024px) {
  .slide-row {
    flex-wrap: wrap;
  }

  .slide-label {
    width: 100%;
    padding-top: 0;
    text-align: left;
    border-bottom: 1px solid var(--td-component-border);
    padding-bottom: 4px;
  }

  .slide-field--sm {
    width: 100%;
  }

  .slide-btn-group {
    flex-wrap: wrap;
  }
}

@media (max-width: 767.98px) {
  .feature-card {
    grid-template-columns: 1fr;
  }
}
</style>

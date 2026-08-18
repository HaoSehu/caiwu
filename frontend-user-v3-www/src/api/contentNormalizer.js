// 官网内容载荷归一化：业务逻辑全部来自 @caiwu/shared/content，
// 这里仅注入官网特有的「API 域资源 URL 解析」选项。
// 使用子路径导入，避免把依赖 markdown-it 的渲染器拖入全站首屏。
import {
  normalizeContentDetailPayload,
  normalizeContentListPayload,
  normalizeContentOverviewPayload,
  normalizeSiteHomePayload,
  withNormalizedData,
} from "@caiwu/shared/content/contentNormalizer";
import { resolveApiAssetUrl } from "@/utils/apiAssetUrl";

const API_BASE_URL = String(import.meta.env?.VITE_API_BASE_URL || "");

const assetOptions = {
  resolveAssetUrl: (value) => resolveApiAssetUrl(value, API_BASE_URL),
};

export const normalizeContentDetailPayloadWithAsset = (data) =>
  normalizeContentDetailPayload(data, assetOptions);

export const normalizeContentListPayloadWithAsset = (data) =>
  normalizeContentListPayload(data, assetOptions);

export const normalizeContentOverviewPayloadWithAsset = (data) =>
  normalizeContentOverviewPayload(data, assetOptions);

export const normalizeSiteHomePayloadWithAsset = (data) =>
  normalizeSiteHomePayload(data, assetOptions);

export { withNormalizedData };

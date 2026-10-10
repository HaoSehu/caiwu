import { updatePageMeta } from "@/utils/pageMeta";

/** 站点公网地址（用于拼接 canonical 与 JSON-LD 的绝对 URL）。 */
export function resolvePublicSiteUrl() {
  return String(
    import.meta.env.VITE_PUBLIC_SITE_URL || "https://www.coyjs.cn",
  ).replace(/\/+$/, "");
}

/** 拼接绝对 canonical：`{siteUrl}/{path}`，两侧多余斜杠已归一。 */
export function buildDetailCanonical(siteUrl, path) {
  const base = String(siteUrl || "").replace(/\/+$/, "");
  const normalized = `/${String(path || "").replace(/^\/+|\/+$/g, "")}`;
  return `${base}${normalized}`;
}

/**
 * 详情页 SEO meta 统一入口。
 *
 * 动态详情页（商品 / 公告 / 帮助 / 内容）在数据就绪后必须覆盖静态 meta，
 * 否则会沿用列表页标题，且 description / canonical 缺失。
 * 这里把原 ContentDetailPage 内联的 applyArticleMeta 抽出来供各详情页共用，
 * 统一处理：标题追加站名、canonical、OG 三件套与 JSON-LD（主体 + 面包屑）。
 *
 * 结构化数据主体用 `structuredType` 区分：内容/公告/帮助用 Article，商品用 Product。
 */
export function applyDetailPageMeta({
  title,
  description = "",
  canonical = "",
  siteName = "",
  listName = "",
  listUrl = "",
  structuredType = "Article",
  publishedAt,
  updatedAt,
} = {}) {
  if (typeof document === "undefined") return;

  const cleanTitle = String(title || "").trim();
  if (!cleanTitle) return;

  const cleanDescription = String(description || "").trim();
  const fullTitle =
    siteName && !cleanTitle.includes(siteName)
      ? `${cleanTitle} - ${siteName}`
      : cleanTitle;

  const structuredData = [
    {
      "@context": "https://schema.org",
      "@type": structuredType,
      headline: cleanTitle,
      name: cleanTitle,
      description: cleanDescription || undefined,
      url: canonical,
      datePublished: publishedAt,
      dateModified: updatedAt,
      publisher: {
        "@type": "Organization",
        name: siteName || "创欧云",
      },
    },
  ];

  if (listName && listUrl) {
    structuredData.push({
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        { "@type": "ListItem", position: 1, name: listName, item: listUrl },
        { "@type": "ListItem", position: 2, name: cleanTitle, item: canonical },
      ],
    });
  }

  updatePageMeta({
    title: fullTitle,
    description: cleanDescription,
    canonical,
    ogTitle: fullTitle,
    ogDescription: cleanDescription,
    ogUrl: canonical,
    structuredData,
  });
}

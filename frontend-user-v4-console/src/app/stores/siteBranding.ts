import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { applyDocumentTitle, deriveInitials, syncDocumentTitle, updateFavicon } from '@caiwu/shared/runtime';

import siteApi from '@/api/site';
import { DEFAULT_SUPPORT_CONTACTS } from '@/data/supportContacts';

const DEFAULT_SITE_NAME = import.meta.env.VITE_APP_TITLE || '创欧云';
const DEFAULT_SITE_LOGO = '/branding/logo.svg';
const DEFAULT_FAVICON = '/branding/logo1.svg';

function pick(raw: Record<string, unknown> | undefined, keys: string[], fallback: string) {
  for (const key of keys) {
    const value = raw?.[key];
    if (value !== undefined && value !== null && String(value).trim() !== '') {
      return String(value).trim();
    }
  }
  return fallback;
}

export const useSiteBrandingStore = defineStore('site-branding', () => {
  const sidebarCollapsed = ref(false);
  const siteName = ref(DEFAULT_SITE_NAME);
  const browserTitle = ref(DEFAULT_SITE_NAME);
  const siteLogo = ref(DEFAULT_SITE_LOGO);
  const siteFavicon = ref(DEFAULT_FAVICON);
  const serviceQqGroup = ref<string>(DEFAULT_SUPPORT_CONTACTS.qqGroup);
  const serviceEmail = ref<string>(DEFAULT_SUPPORT_CONTACTS.email);
  const serviceHours = ref<string>(DEFAULT_SUPPORT_CONTACTS.hours);
  const supportGroupTitle = ref<string>('');
  const supportGroupText = ref<string>('');
  const supportGroupQr = ref<string>(DEFAULT_SUPPORT_CONTACTS.groupQr);
  const supportGroupLink = ref<string>('');
  const termsUrl = ref<string>('');
  const privacyUrl = ref<string>('');
  const brandInitials = computed(() => deriveInitials(siteName.value));

  let fetchPromise: Promise<void> | null = null;

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
  }

  function applyPageTitle(pageTitle = '') {
    const baseTitle = browserTitle.value || siteName.value || DEFAULT_SITE_NAME;
    applyDocumentTitle(pageTitle, baseTitle, siteFavicon.value || DEFAULT_FAVICON, DEFAULT_FAVICON);
  }

  function hydrateSiteConfig(data: Record<string, unknown> = {}) {
    const previousBaseTitle = browserTitle.value || siteName.value || DEFAULT_SITE_NAME;
    siteName.value = pick(data, ['site_name'], siteName.value || DEFAULT_SITE_NAME);
    browserTitle.value = pick(data, ['browser_title'], siteName.value || DEFAULT_SITE_NAME);
    siteLogo.value = pick(data, ['site_logo'], siteLogo.value || DEFAULT_SITE_LOGO);
    siteFavicon.value = pick(data, ['site_favicon'], siteFavicon.value || DEFAULT_FAVICON);
    serviceQqGroup.value = pick(
      data,
      ['service_qq_group', 'serviceQqGroup', 'service_phone', 'servicePhone'],
      serviceQqGroup.value || DEFAULT_SUPPORT_CONTACTS.qqGroup,
    );
    serviceEmail.value = pick(data, ['service_email', 'serviceEmail'], serviceEmail.value || DEFAULT_SUPPORT_CONTACTS.email);
    serviceHours.value = pick(data, ['service_hours', 'serviceHours'], serviceHours.value || DEFAULT_SUPPORT_CONTACTS.hours);
    supportGroupTitle.value = pick(data, ['support_group_title', 'supportGroupTitle'], supportGroupTitle.value || '');
    supportGroupText.value = pick(data, ['support_group_text', 'supportGroupText'], supportGroupText.value || '');
    supportGroupQr.value = pick(data, ['support_group_qr', 'supportGroupQr'], supportGroupQr.value || DEFAULT_SUPPORT_CONTACTS.groupQr);
    supportGroupLink.value = pick(data, ['support_group_link', 'supportGroupLink'], supportGroupLink.value || '');
    termsUrl.value = pick(data, ['terms_url'], termsUrl.value || '');
    privacyUrl.value = pick(data, ['privacy_url'], privacyUrl.value || '');
    syncDocumentTitle(browserTitle.value || siteName.value || DEFAULT_SITE_NAME, previousBaseTitle, DEFAULT_SITE_NAME);
    updateFavicon(siteFavicon.value || DEFAULT_FAVICON, DEFAULT_FAVICON);
  }

  async function fetchSiteConfig() {
    if (fetchPromise) {
      return fetchPromise;
    }

    fetchPromise = siteApi.config({ silentError: true })
      .then((res: any) => {
        hydrateSiteConfig(res.data || {});
      })
      .catch(() => {
        siteLogo.value = siteLogo.value || DEFAULT_SITE_LOGO;
        siteFavicon.value = siteFavicon.value || DEFAULT_FAVICON;
      })
      .finally(() => {
        updateFavicon(siteFavicon.value || DEFAULT_FAVICON, DEFAULT_FAVICON);
        fetchPromise = null;
      });

    return fetchPromise;
  }

  return {
    sidebarCollapsed,
    siteName,
    browserTitle,
    siteLogo,
    siteFavicon,
    serviceQqGroup,
    serviceEmail,
    serviceHours,
    supportGroupTitle,
    supportGroupText,
    supportGroupQr,
    supportGroupLink,
    termsUrl,
    privacyUrl,
    brandInitials,
    toggleSidebar,
    applyPageTitle,
    hydrateSiteConfig,
    fetchSiteConfig,
  };
});

<template>
  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <div class="footer-logo">
            <img
              :src="logoSrc"
              :alt="appStore.siteName"
              class="footer-logo-image"
              loading="lazy"
              @error="handleFooterLogoError"
            />
            <span v-if="footerLogoLoadFailed" class="footer-logo-fallback">{{
              appStore.siteName
            }}</span>
          </div>
          <p class="footer-brand__desc">
            为企业与开发者提供稳定、安全、高性价比的云计算与 IDC 服务。
          </p>
          <ul class="footer-contact">
            <li v-for="item in supportContacts" :key="item.key">
              <span class="footer-contact__label">{{ item.label }}</span>
              <a
                v-if="item.key === 'qq-group' && appStore.supportGroupLink"
                :href="appStore.supportGroupLink"
                class="footer-contact__value footer-contact__link"
                target="_blank"
                rel="noopener noreferrer"
              >{{ item.value }}</a>
              <span v-else class="footer-contact__value">{{ item.value }}</span>
            </li>
          </ul>
        </div>

        <div class="footer-columns">
          <div class="footer-col">
            <h3>产品</h3>
            <router-link
              v-for="item in seoLandingFooterLinks"
              :key="item.to"
              :to="item.to"
            >
              {{ item.label }}
            </router-link>
          </div>

          <div class="footer-col">
            <h3>解决方案</h3>
            <router-link to="/products">电商行业</router-link>
            <router-link to="/products">游戏行业</router-link>
            <router-link to="/products">金融行业</router-link>
            <router-link to="/products">出海业务</router-link>
          </div>

          <div class="footer-col">
            <h3>支持</h3>
            <router-link to="/notices">站点公告</router-link>
            <router-link to="/help">帮助中心</router-link>
            <a :href="consoleUrl('/client/tickets')">工单系统</a>
            <router-link to="/about">关于我们</router-link>
            <router-link to="/terms">服务条款</router-link>
            <router-link to="/privacy">隐私政策</router-link>
          </div>

          <div class="footer-col">
            <h3>账户</h3>
            <a :href="consoleUrl('/client/register')">免费注册</a>
            <a :href="consoleUrl('/client/login')">账户登录</a>
            <a :href="consoleUrl('/client/dashboard')">进入控制台</a>
            <a :href="consoleUrl('/client/verification')">实名认证</a>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <p>
          &copy; {{ new Date().getFullYear() }} {{ appStore.siteName }}. All
          rights reserved.
        </p>
        <p
          v-if="appStore.valueAddedLicense || appStore.icpRecord"
          class="footer-bottom__meta"
        >
          <span v-if="appStore.valueAddedLicense"
            >增值电信业务经营许可证：{{ appStore.valueAddedLicense }}</span
          >
          <span v-if="appStore.icpRecord"
            ><a
              href="https://beian.miit.gov.cn/"
              target="_blank"
              rel="noopener nofollow"
              class="footer-icp-link"
              >ICP 备案号：{{ appStore.icpRecord }}</a
            ></span
          >
        </p>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, ref } from "vue";
import { useAppStore } from "@/app/stores/app";
import { buildSupportContacts } from "@/data/supportContacts";
import { seoLandingFooterLinks } from "@/data/seoLandingMeta";
import { buildConsoleUrl } from "@/utils/consoleUrl";

const appStore = useAppStore();

// 优先使用站点品牌配置的 logo（管理端可上传），失败时回退到文字标识
const logoSrc = computed(() => appStore.siteLogo);
const footerLogoLoadFailed = ref(false);

const supportContacts = computed(() =>
  buildSupportContacts({
    serviceQqGroup: appStore.serviceQqGroup,
    serviceEmail: appStore.serviceEmail,
    serviceHours: appStore.serviceHours,
  }),
);

function consoleUrl(path) {
  return buildConsoleUrl(path);
}

function handleFooterLogoError() {
  footerLogoLoadFailed.value = true;
}
</script>

<style scoped lang="scss">
// 页脚原本与页头共用 .container：抽成独立组件后 scoped 选择器不再跨组件生效，
// 因此这里复制一份同定义（页头那份仍留在 WebsiteLayout.vue）。
.container {
  width: min(1200px, calc(100% - 48px));
  margin: 0 auto;
}

.site-footer {
  margin-top: auto;
  padding: 56px 0 0;
  background: #f4f7fc;
  border-top: 1px solid $divider-color;
}

.footer-top {
  display: grid;
  grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
  gap: 64px;
  padding-bottom: 32px;
}

.footer-brand {
  min-width: 0;
}

.footer-brand__desc {
  margin-top: 16px;
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1.75;
}

.footer-logo {
  display: flex;
  align-items: center;
  min-height: 40px;
  min-width: 148px;
}

.footer-logo-image {
  display: block;
  width: 148px;
  height: 40px;
  max-width: 100%;
  object-fit: contain;
  object-position: left center;
}

.footer-logo-fallback {
  display: flex;
  align-items: center;
  height: 40px;
  padding: 0 10px;
  background: $color-primary;
  color: $text-color-inverse;
  font-size: 16px;
  font-weight: 600;
  border-radius: 8px;
  white-space: nowrap;
}

.footer-contact {
  display: grid;
  gap: 8px;
  margin: 24px 0 0;
  padding: 0;
  list-style: none;
}

.footer-contact li {
  display: flex;
  align-items: center;
  gap: 12px;
  color: $text-color-secondary;
  font-size: 12px;
  line-height: 1.6;
}

.footer-contact__label {
  flex-shrink: 0;
  color: $text-color-placeholder;
  font-size: 12px;
}

.footer-contact__value {
  color: $text-color-primary;
  font-size: 13px;
  font-weight: 500;
}

.footer-contact__link {
  text-decoration: none;

  &:hover {
    color: $color-primary;
  }
}

.footer-columns {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 32px;
}

.footer-col {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.footer-col h3 {
  margin: 0 0 8px;
  color: $text-color-primary;
  font-size: 14px;
  font-weight: 600;
}

.footer-col a {
  color: $text-color-secondary;
  font-size: 13px;
  line-height: 1;
  padding: 6px 0;
  text-decoration: none;
  transition: color 0.16s ease;
}

.footer-col a:hover {
  color: $color-primary;
}

.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px 24px;
  min-height: 56px;
  padding: 14px 0;
  border-top: 1px solid $divider-color;
  color: $text-color-placeholder;
  font-size: 12px;
}

.footer-bottom p {
  margin: 0;
}

.footer-bottom__meta {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  color: $text-color-placeholder;
}

.footer-icp-link {
  color: inherit;
  transition: color 0.16s ease;
}

.footer-icp-link:hover {
  color: $color-primary;
}

@media (max-width: $breakpoint-nav) {
  .footer-top {
    grid-template-columns: 1fr;
    gap: 32px;
  }

  .footer-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px 32px;
  }

  .footer-bottom {
    justify-content: flex-start;
  }
}

@media (max-width: 640px) {
  .container {
    width: calc(100% - 24px);
  }

  .footer-logo {
    min-width: 120px;
  }

  .footer-logo-image {
    width: 120px;
    height: 32px;
  }

  .footer-logo-fallback {
    height: 32px;
    font-size: 14px;
    padding: 0 8px;
  }

  .footer-top {
    gap: 24px;
    padding-bottom: 24px;
  }

  .footer-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px 16px;
  }

  .footer-col {
    min-width: 0;
  }

  .footer-bottom {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding-block: 18px;
  }

  .footer-bottom__meta {
    gap: 4px 14px;
  }
}

@media (max-width: 480px) {
  .footer-contact {
    gap: 6px;
  }

  .footer-contact li {
    gap: 8px;
  }

  .footer-columns {
    gap: 18px 12px;
  }

  .footer-col h3 {
    font-size: 13px;
  }

  .footer-col a {
    font-size: 12px;
  }
}
</style>

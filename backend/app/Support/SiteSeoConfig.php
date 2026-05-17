<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * 统一读取站点基础信息与 SEO 全站默认配置。
 *
 * 数据源：
 *   - settings 表 group_key = 'basic'：站点名称、Logo、favicon 等
 *   - settings 表 group_key = 'seo'：SEO 层面全站默认（描述、关键词、canonical base、og image 等）
 *
 * 前台 /api/site/config 与 /api/site/home 接口都会使用本类输出，
 * 保证后台保存后前台能同步生效。
 */
class SiteSeoConfig
{
    public const SETTING_GROUP_BASIC = 'basic';

    public const SETTING_GROUP_SEO = 'seo';

    public const DEFAULT_SITE_NAME = '创欧云';

    public const DEFAULT_SITE_LOGO = '/branding/logo.svg';

    public const DEFAULT_SITE_FAVICON = '/branding/logo1.svg';

    public const DEFAULT_OG_IMAGE = '/branding/logo.svg';

    public const DEFAULT_SITE_DESCRIPTION = '创欧云 — 稳定、安全、高性价比的云服务器与 IDC 服务平台，覆盖香港、美国与国内多地节点，提供云服务器、独立服务器、云电脑与工单支持。';

    public const DEFAULT_SITE_KEYWORDS = '云服务器,独立服务器,高防服务器,云电脑,香港服务器,美国服务器,BGP 多线,IDC 服务,创欧云';

    public const DEFAULT_SERVICE_QQ_GROUP = '待补充群号';

    public const DEFAULT_SUPPORT_GROUP_QR = '';

    public const DEFAULT_SUPPORT_GROUP_LINK = '';

    /**
     * 供前端站点配置接口使用的统一负载。
     *
     * @return array<string, string>
     */
    public static function payload(): array
    {
        $defaultSiteName = (string) config('idc.site_name', self::DEFAULT_SITE_NAME);

        $siteName = self::read(self::SETTING_GROUP_BASIC, 'site_name', $defaultSiteName);
        $browserTitle = self::read(self::SETTING_GROUP_BASIC, 'browser_title', $siteName);
        $siteLogo = self::read(self::SETTING_GROUP_BASIC, 'site_logo', self::DEFAULT_SITE_LOGO);
        $siteFavicon = self::read(self::SETTING_GROUP_BASIC, 'site_favicon', self::DEFAULT_SITE_FAVICON);
        $serviceQqGroup = self::readFirstAvailable(
            self::SETTING_GROUP_BASIC,
            ['service_qq_group', 'service_phone'],
            self::DEFAULT_SERVICE_QQ_GROUP
        );
        $supportGroupQr = self::read(self::SETTING_GROUP_BASIC, 'support_group_qr', self::DEFAULT_SUPPORT_GROUP_QR);
        $supportGroupLink = self::read(self::SETTING_GROUP_BASIC, 'support_group_link', self::DEFAULT_SUPPORT_GROUP_LINK);
        $termsUrl = self::read(self::SETTING_GROUP_BASIC, 'terms_url', '');
        $privacyUrl = self::read(self::SETTING_GROUP_BASIC, 'privacy_url', '');

        $siteDescription = self::read(self::SETTING_GROUP_SEO, 'site_description', self::DEFAULT_SITE_DESCRIPTION);
        $siteKeywords = self::read(self::SETTING_GROUP_SEO, 'site_keywords', self::DEFAULT_SITE_KEYWORDS);
        $canonicalBase = self::read(self::SETTING_GROUP_SEO, 'canonical_base', '');
        $ogImage = self::read(self::SETTING_GROUP_SEO, 'og_image', '');
        $twitterHandle = self::read(self::SETTING_GROUP_SEO, 'twitter_handle', '');
        $robots = self::read(self::SETTING_GROUP_SEO, 'robots_directive', 'index,follow');
        $verifyGoogle = self::read(self::SETTING_GROUP_SEO, 'verify_google', '');
        $verifyBaidu = self::read(self::SETTING_GROUP_SEO, 'verify_baidu', '');
        $verifyBing = self::read(self::SETTING_GROUP_SEO, 'verify_bing', '');
        $verify360 = self::read(self::SETTING_GROUP_SEO, 'verify_360', '');
        $verifySogou = self::read(self::SETTING_GROUP_SEO, 'verify_sogou', '');
        $indexnowKey = self::normalizeIndexNowKey(self::read(self::SETTING_GROUP_SEO, 'indexnow_key', ''));

        return [
            // 基础站点信息（兼容旧接口字段）
            'site_name' => $siteName !== '' ? $siteName : $defaultSiteName,
            'browser_title' => $browserTitle !== '' ? $browserTitle : ($siteName !== '' ? $siteName : $defaultSiteName),
            'site_logo' => $siteLogo !== '' ? $siteLogo : self::DEFAULT_SITE_LOGO,
            'site_favicon' => $siteFavicon !== '' ? $siteFavicon : self::DEFAULT_SITE_FAVICON,
            'service_qq_group' => $serviceQqGroup !== '' ? $serviceQqGroup : self::DEFAULT_SERVICE_QQ_GROUP,
            'service_phone' => $serviceQqGroup !== '' ? $serviceQqGroup : self::DEFAULT_SERVICE_QQ_GROUP,
            'support_group_qr' => $supportGroupQr,
            'support_group_link' => $supportGroupLink,
            'terms_url' => $termsUrl,
            'privacy_url' => $privacyUrl,

            // SEO 默认值
            'site_description' => $siteDescription !== '' ? $siteDescription : self::DEFAULT_SITE_DESCRIPTION,
            'site_keywords' => $siteKeywords !== '' ? $siteKeywords : self::DEFAULT_SITE_KEYWORDS,
            'canonical_base' => self::normalizeBaseUrl($canonicalBase),
            'og_image' => $ogImage !== '' ? $ogImage : self::DEFAULT_OG_IMAGE,
            'twitter_handle' => $twitterHandle,
            'robots_directive' => $robots !== '' ? $robots : 'index,follow',

            // 搜索引擎站长平台验证码（留空则不输出对应 meta）
            'verify_google' => $verifyGoogle,
            'verify_baidu' => $verifyBaidu,
            'verify_bing' => $verifyBing,
            'verify_360' => $verify360,
            'verify_sogou' => $verifySogou,

            // IndexNow 推送（留空则不推送）
            'indexnow_key' => $indexnowKey,
        ];
    }

    /**
     * 仅拿 SEO 分组的原始值，管理端表单读取时使用。
     * 约定的 key 列表见 payload() 实现。
     *
     * @return array<string, string>
     */
    public static function seoGroupValues(): array
    {
        return [
            'site_description' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'site_description', ''),
            'site_keywords' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'site_keywords', ''),
            'canonical_base' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'canonical_base', ''),
            'og_image' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'og_image', ''),
            'twitter_handle' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'twitter_handle', ''),
            'robots_directive' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'robots_directive', ''),
            'verify_google' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'verify_google', ''),
            'verify_baidu' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'verify_baidu', ''),
            'verify_bing' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'verify_bing', ''),
            'verify_360' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'verify_360', ''),
            'verify_sogou' => (string) Setting::getValue(self::SETTING_GROUP_SEO, 'verify_sogou', ''),
            'indexnow_key' => self::normalizeIndexNowKey((string) Setting::getValue(self::SETTING_GROUP_SEO, 'indexnow_key', '')),
        ];
    }

    /**
     * IndexNow 密钥合法格式：8–128 位 ASCII（字母、数字、横线）。
     * 如果数据库里塞了非法内容，直接当作空值处理，避免生成错误的验证文件。
     */
    public static function normalizeIndexNowKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('/^[A-Za-z0-9\-]{8,128}$/', $trimmed)) {
            return '';
        }

        return $trimmed;
    }

    private static function read(string $group, string $key, string $default): string
    {
        $value = trim((string) Setting::getValue($group, $key, $default));

        return $value !== '' ? $value : $default;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private static function readFirstAvailable(string $group, array $keys, string $default): string
    {
        foreach ($keys as $key) {
            $value = trim((string) Setting::getValue($group, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    private static function normalizeBaseUrl(string $value): string
    {
        $trimmed = rtrim(trim($value), '/');
        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            return 'https://'.$trimmed;
        }

        return $trimmed;
    }
}

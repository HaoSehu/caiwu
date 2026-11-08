<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补种缺失的邮件默认模板。
 *
 * 与 `2026_11_08_000001_seed_missing_sms_notification_templates` 同源问题：
 * 原始种子迁移 `2026_07_06_124000_seed_notification_templates_defaults` 已归档，
 * 但它创建的邮件模板（100001–100030）在全新数据库上从未生成，导致
 * `message_scene_templates` 里的 `email:<code>` 关联悬空、
 * 计费通知走 email 渠道时因缺模板被静默跳过。
 *
 * 内容取自开发库中 `is_custom = 0` 的系统默认行（非管理端自定义），
 * 按当前 `code` / `scene_code` 落库。已存在的行不覆盖，可安全重复执行。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $now = now();

        foreach ($this->templates() as $template) {
            $exists = DB::table('notification_templates')
                ->where('channel', 'email')
                ->where('code', $template['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'channel' => 'email',
                'code' => $template['code'],
                'name' => $template['name'],
                'description' => $template['description'],
                'audience' => $template['audience'],
                'scene_code' => $template['scene_code'] !== '' ? $template['scene_code'] : null,
                'subject' => $template['subject'],
                'content' => $template['content'],
                'variables_json' => $template['variables_json'],
                'provider_variables_json' => $template['provider_variables_json'],
                'provider_template_id' => null,
                'is_enabled' => true,
                'is_custom' => false,
                'sort_order' => $template['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        $codes = array_column($this->templates(), 'code');

        DB::table('notification_templates')
            ->where('channel', 'email')
            ->where('is_custom', false)
            ->whereIn('code', $codes)
            ->delete();
    }

    /**
     * @return list<array{code: string, name: string, description: string, audience: string, subject: string, content: string, variables_json: string, provider_variables_json: string, scene_code: string, sort_order: int}>
     */
    private function templates(): array
    {
        return [
            [
                'code' => '100001',
                'name' => '验证码邮件',
                'description' => '发送邮箱验证码时使用。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】验证码邮件',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100001 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100001 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100001 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100001 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100001 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100001 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100001 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100001 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100001 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100001 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100001 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100001 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100001 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100001 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100001 .detail-label,
.cw-email-template-100001 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100001 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100001 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100001 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100001 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100001 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100001 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100001 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100001 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100001 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100001 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100001 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100001 .email-brand,
  .cw-email-template-100001 .email-hero,
  .cw-email-template-100001 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100001 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100001 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100001 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100001 .detail-label,
  .cw-email-template-100001 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100001 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100001 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100001 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100001 { background: #181818; }
  .cw-email-template-100001 .email-shell { background: #181818; }
  .cw-email-template-100001 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100001 .email-hero,
  .cw-email-template-100001 .email-section { background: #202020; }
  .cw-email-template-100001 .email-eyebrow { color: #2886de; }
  .cw-email-template-100001 h1,
  .cw-email-template-100001 .section-title,
  .cw-email-template-100001 .action-title { color: #ffffff; }
  .cw-email-template-100001 .email-lead,
  .cw-email-template-100001 .detail-value { color: #d2d0ce; }
  .cw-email-template-100001 .action-copy { color: #a19f9d; }
  .cw-email-template-100001 .detail-label { color: #8a8886; }
  .cw-email-template-100001 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100001 .action-card { background: #292929; }
  .cw-email-template-100001 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100001 .footer-copy { color: #8a8886; }
  .cw-email-template-100001 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100001">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>验证码邮件</h1>
                      <p class="email-lead">请使用以下验证码完成验证。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">验证码</td><td class="detail-value">{{code}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">有效期</td><td class="detail-value">{{expire_minutes}} 分钟</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">如非本人操作，可以忽略此邮件；验证码不会影响您的账号状态。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "code", "expire_minutes"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'register_verification',
                'sort_order' => 1,
            ],
            [
                'code' => '100002',
                'name' => '注册成功',
                'description' => '客户注册成功后发送欢迎通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】注册成功',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100002 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100002 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100002 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100002 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100002 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100002 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100002 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100002 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100002 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100002 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100002 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100002 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100002 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100002 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100002 .detail-label,
.cw-email-template-100002 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100002 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100002 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100002 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100002 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100002 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100002 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100002 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100002 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100002 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100002 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100002 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100002 .email-brand,
  .cw-email-template-100002 .email-hero,
  .cw-email-template-100002 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100002 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100002 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100002 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100002 .detail-label,
  .cw-email-template-100002 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100002 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100002 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100002 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100002 { background: #181818; }
  .cw-email-template-100002 .email-shell { background: #181818; }
  .cw-email-template-100002 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100002 .email-hero,
  .cw-email-template-100002 .email-section { background: #202020; }
  .cw-email-template-100002 .email-eyebrow { color: #2886de; }
  .cw-email-template-100002 h1,
  .cw-email-template-100002 .section-title,
  .cw-email-template-100002 .action-title { color: #ffffff; }
  .cw-email-template-100002 .email-lead,
  .cw-email-template-100002 .detail-value { color: #d2d0ce; }
  .cw-email-template-100002 .action-copy { color: #a19f9d; }
  .cw-email-template-100002 .detail-label { color: #8a8886; }
  .cw-email-template-100002 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100002 .action-card { background: #292929; }
  .cw-email-template-100002 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100002 .footer-copy { color: #8a8886; }
  .cw-email-template-100002 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100002">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>注册成功</h1>
                      <p class="email-lead">{{display_name}}，您的账号已注册成功。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">登录邮箱</td><td class="detail-value">{{email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">注册时间</td><td class="detail-value">{{registered_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">建议尽快完善账号安全信息，便于后续接收服务和账单通知。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "email", "registered_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'registration_success',
                'sort_order' => 2,
            ],
            [
                'code' => '100003',
                'name' => '登录IP提醒',
                'description' => '客户登录或登录异常时发送 IP 安全提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】登录IP提醒',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100003 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100003 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100003 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100003 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100003 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100003 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100003 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100003 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100003 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100003 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100003 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100003 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100003 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100003 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100003 .detail-label,
.cw-email-template-100003 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100003 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100003 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100003 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100003 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100003 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100003 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100003 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100003 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100003 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100003 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100003 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100003 .email-brand,
  .cw-email-template-100003 .email-hero,
  .cw-email-template-100003 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100003 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100003 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100003 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100003 .detail-label,
  .cw-email-template-100003 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100003 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100003 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100003 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100003 { background: #181818; }
  .cw-email-template-100003 .email-shell { background: #181818; }
  .cw-email-template-100003 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100003 .email-hero,
  .cw-email-template-100003 .email-section { background: #202020; }
  .cw-email-template-100003 .email-eyebrow { color: #2886de; }
  .cw-email-template-100003 h1,
  .cw-email-template-100003 .section-title,
  .cw-email-template-100003 .action-title { color: #ffffff; }
  .cw-email-template-100003 .email-lead,
  .cw-email-template-100003 .detail-value { color: #d2d0ce; }
  .cw-email-template-100003 .action-copy { color: #a19f9d; }
  .cw-email-template-100003 .detail-label { color: #8a8886; }
  .cw-email-template-100003 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100003 .action-card { background: #292929; }
  .cw-email-template-100003 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100003 .footer-copy { color: #8a8886; }
  .cw-email-template-100003 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100003">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>登录IP提醒</h1>
                      <p class="email-lead">{{display_name}}，您的账号发生登录行为。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">登录账号</td><td class="detail-value">{{email}}{{account}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">登录时间</td><td class="detail-value">{{login_at}}{{attempt_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">当前 IP</td><td class="detail-value">{{ip}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">上次 IP</td><td class="detail-value">{{previous_ip}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">设备</td><td class="detail-value">{{device}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">如果这不是您本人操作，请立即修改密码并检查账号安全设置。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "email", "account", "login_at", "attempt_at", "ip", "previous_ip", "device"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'login_alert',
                'sort_order' => 3,
            ],
            [
                'code' => '100004',
                'name' => '绑定通知',
                'description' => '客户绑定或变更安全信息后发送提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】绑定通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100004 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100004 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100004 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100004 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100004 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100004 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100004 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100004 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100004 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100004 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100004 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100004 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100004 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100004 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100004 .detail-label,
.cw-email-template-100004 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100004 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100004 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100004 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100004 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100004 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100004 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100004 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100004 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100004 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100004 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100004 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100004 .email-brand,
  .cw-email-template-100004 .email-hero,
  .cw-email-template-100004 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100004 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100004 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100004 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100004 .detail-label,
  .cw-email-template-100004 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100004 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100004 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100004 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100004 { background: #181818; }
  .cw-email-template-100004 .email-shell { background: #181818; }
  .cw-email-template-100004 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100004 .email-hero,
  .cw-email-template-100004 .email-section { background: #202020; }
  .cw-email-template-100004 .email-eyebrow { color: #2886de; }
  .cw-email-template-100004 h1,
  .cw-email-template-100004 .section-title,
  .cw-email-template-100004 .action-title { color: #ffffff; }
  .cw-email-template-100004 .email-lead,
  .cw-email-template-100004 .detail-value { color: #d2d0ce; }
  .cw-email-template-100004 .action-copy { color: #a19f9d; }
  .cw-email-template-100004 .detail-label { color: #8a8886; }
  .cw-email-template-100004 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100004 .action-card { background: #292929; }
  .cw-email-template-100004 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100004 .footer-copy { color: #8a8886; }
  .cw-email-template-100004 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100004">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>绑定通知</h1>
                      <p class="email-lead">{{display_name}}，您的账号安全信息已更新。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">绑定类型</td><td class="detail-value">{{bind_type}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">绑定账号</td><td class="detail-value">{{bind_account}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">原手机号</td><td class="detail-value">{{old_phone}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">新手机号</td><td class="detail-value">{{new_phone}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">原邮箱</td><td class="detail-value">{{old_email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">新邮箱</td><td class="detail-value">{{new_email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">变更时间</td><td class="detail-value">{{bound_at}}{{changed_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">IP 地址</td><td class="detail-value">{{ip}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">设备</td><td class="detail-value">{{device}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">如非本人操作，请尽快联系管理员处理。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "bind_type", "bind_account", "old_phone", "new_phone", "old_email", "new_email", "bound_at", "changed_at", "ip", "device"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'security_changed',
                'sort_order' => 4,
            ],
            [
                'code' => '100005',
                'name' => '产品开通通知',
                'description' => '产品或服务开通成功后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】产品开通通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100005 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100005 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100005 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100005 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100005 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100005 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100005 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100005 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100005 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100005 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100005 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100005 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100005 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100005 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100005 .detail-label,
.cw-email-template-100005 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100005 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100005 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100005 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100005 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100005 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100005 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100005 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100005 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100005 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100005 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100005 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100005 .email-brand,
  .cw-email-template-100005 .email-hero,
  .cw-email-template-100005 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100005 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100005 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100005 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100005 .detail-label,
  .cw-email-template-100005 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100005 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100005 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100005 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100005 { background: #181818; }
  .cw-email-template-100005 .email-shell { background: #181818; }
  .cw-email-template-100005 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100005 .email-hero,
  .cw-email-template-100005 .email-section { background: #202020; }
  .cw-email-template-100005 .email-eyebrow { color: #2886de; }
  .cw-email-template-100005 h1,
  .cw-email-template-100005 .section-title,
  .cw-email-template-100005 .action-title { color: #ffffff; }
  .cw-email-template-100005 .email-lead,
  .cw-email-template-100005 .detail-value { color: #d2d0ce; }
  .cw-email-template-100005 .action-copy { color: #a19f9d; }
  .cw-email-template-100005 .detail-label { color: #8a8886; }
  .cw-email-template-100005 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100005 .action-card { background: #292929; }
  .cw-email-template-100005 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100005 .footer-copy { color: #8a8886; }
  .cw-email-template-100005 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100005">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>产品开通通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品已开通。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">主机名</td><td class="detail-value">{{hostname}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">主 IP</td><td class="detail-value">{{product_main_ip}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">用户名</td><td class="detail-value">{{product_username}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">操作系统</td><td class="detail-value">{{operating_system}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">开通时间</td><td class="detail-value">{{activated_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">请登录控制台核对产品信息，并按需完成初始化配置。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "product_name", "service_name", "hostname", "product_main_ip", "product_username", "operating_system", "activated_at", "expires_at", "billing_cycle_label"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_activated',
                'sort_order' => 5,
            ],
            [
                'code' => '100006',
                'name' => '产品停用通知',
                'description' => '产品或服务被停用时发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】产品停用通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100006 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100006 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100006 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100006 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100006 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100006 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100006 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100006 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100006 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100006 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100006 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100006 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100006 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100006 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100006 .detail-label,
.cw-email-template-100006 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100006 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100006 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100006 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100006 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100006 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100006 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100006 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100006 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100006 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100006 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100006 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100006 .email-brand,
  .cw-email-template-100006 .email-hero,
  .cw-email-template-100006 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100006 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100006 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100006 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100006 .detail-label,
  .cw-email-template-100006 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100006 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100006 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100006 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100006 { background: #181818; }
  .cw-email-template-100006 .email-shell { background: #181818; }
  .cw-email-template-100006 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100006 .email-hero,
  .cw-email-template-100006 .email-section { background: #202020; }
  .cw-email-template-100006 .email-eyebrow { color: #2886de; }
  .cw-email-template-100006 h1,
  .cw-email-template-100006 .section-title,
  .cw-email-template-100006 .action-title { color: #ffffff; }
  .cw-email-template-100006 .email-lead,
  .cw-email-template-100006 .detail-value { color: #d2d0ce; }
  .cw-email-template-100006 .action-copy { color: #a19f9d; }
  .cw-email-template-100006 .detail-label { color: #8a8886; }
  .cw-email-template-100006 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100006 .action-card { background: #292929; }
  .cw-email-template-100006 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100006 .footer-copy { color: #8a8886; }
  .cw-email-template-100006 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100006">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>产品停用通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品已被停用。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">停用原因</td><td class="detail-value">{{reason}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">停用时间</td><td class="detail-value">{{suspended_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">请按停用原因完成处理，必要时联系技术支持协助恢复。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "product_name", "service_name", "reason", "expires_at", "suspended_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_suspended',
                'sort_order' => 6,
            ],
            [
                'code' => '100007',
                'name' => '产品恢复通知',
                'description' => '产品或服务恢复后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】产品恢复通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100007 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100007 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100007 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100007 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100007 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100007 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100007 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100007 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100007 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100007 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100007 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100007 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100007 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100007 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100007 .detail-label,
.cw-email-template-100007 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100007 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100007 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100007 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100007 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100007 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100007 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100007 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100007 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100007 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100007 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100007 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100007 .email-brand,
  .cw-email-template-100007 .email-hero,
  .cw-email-template-100007 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100007 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100007 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100007 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100007 .detail-label,
  .cw-email-template-100007 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100007 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100007 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100007 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100007 { background: #181818; }
  .cw-email-template-100007 .email-shell { background: #181818; }
  .cw-email-template-100007 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100007 .email-hero,
  .cw-email-template-100007 .email-section { background: #202020; }
  .cw-email-template-100007 .email-eyebrow { color: #2886de; }
  .cw-email-template-100007 h1,
  .cw-email-template-100007 .section-title,
  .cw-email-template-100007 .action-title { color: #ffffff; }
  .cw-email-template-100007 .email-lead,
  .cw-email-template-100007 .detail-value { color: #d2d0ce; }
  .cw-email-template-100007 .action-copy { color: #a19f9d; }
  .cw-email-template-100007 .detail-label { color: #8a8886; }
  .cw-email-template-100007 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100007 .action-card { background: #292929; }
  .cw-email-template-100007 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100007 .footer-copy { color: #8a8886; }
  .cw-email-template-100007 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100007">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>产品恢复通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品已恢复。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">恢复时间</td><td class="detail-value">{{resumed_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">新的到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">您可以继续使用该产品；建议确认业务状态是否已恢复正常。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "product_name", "service_name", "resumed_at", "expires_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_resumed',
                'sort_order' => 7,
            ],
            [
                'code' => '100008',
                'name' => '产品删除通知',
                'description' => '产品或服务删除后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】产品删除通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100008 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100008 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100008 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100008 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100008 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100008 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100008 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100008 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100008 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100008 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100008 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100008 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100008 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100008 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100008 .detail-label,
.cw-email-template-100008 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100008 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100008 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100008 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100008 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100008 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100008 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100008 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100008 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100008 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100008 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100008 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100008 .email-brand,
  .cw-email-template-100008 .email-hero,
  .cw-email-template-100008 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100008 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100008 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100008 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100008 .detail-label,
  .cw-email-template-100008 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100008 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100008 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100008 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100008 { background: #181818; }
  .cw-email-template-100008 .email-shell { background: #181818; }
  .cw-email-template-100008 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100008 .email-hero,
  .cw-email-template-100008 .email-section { background: #202020; }
  .cw-email-template-100008 .email-eyebrow { color: #2886de; }
  .cw-email-template-100008 h1,
  .cw-email-template-100008 .section-title,
  .cw-email-template-100008 .action-title { color: #ffffff; }
  .cw-email-template-100008 .email-lead,
  .cw-email-template-100008 .detail-value { color: #d2d0ce; }
  .cw-email-template-100008 .action-copy { color: #a19f9d; }
  .cw-email-template-100008 .detail-label { color: #8a8886; }
  .cw-email-template-100008 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100008 .action-card { background: #292929; }
  .cw-email-template-100008 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100008 .footer-copy { color: #8a8886; }
  .cw-email-template-100008 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100008">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>产品删除通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品已删除。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">删除时间</td><td class="detail-value">{{terminated_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">说明</td><td class="detail-value">{{notice_message}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">删除操作完成后资源可能无法恢复，如有疑问请及时联系支持。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "product_name", "service_name", "terminated_at", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_terminated',
                'sort_order' => 8,
            ],
            [
                'code' => '100010',
                'name' => '新订单待支付',
                'description' => '客户创建新订单或账单后发送待支付通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】新订单待支付 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100010 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100010 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100010 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100010 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100010 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100010 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100010 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100010 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100010 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100010 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100010 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100010 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100010 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100010 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100010 .detail-label,
.cw-email-template-100010 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100010 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100010 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100010 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100010 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100010 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100010 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100010 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100010 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100010 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100010 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100010 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100010 .email-brand,
  .cw-email-template-100010 .email-hero,
  .cw-email-template-100010 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100010 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100010 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100010 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100010 .detail-label,
  .cw-email-template-100010 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100010 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100010 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100010 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100010 { background: #181818; }
  .cw-email-template-100010 .email-shell { background: #181818; }
  .cw-email-template-100010 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100010 .email-hero,
  .cw-email-template-100010 .email-section { background: #202020; }
  .cw-email-template-100010 .email-eyebrow { color: #2886de; }
  .cw-email-template-100010 h1,
  .cw-email-template-100010 .section-title,
  .cw-email-template-100010 .action-title { color: #ffffff; }
  .cw-email-template-100010 .email-lead,
  .cw-email-template-100010 .detail-value { color: #d2d0ce; }
  .cw-email-template-100010 .action-copy { color: #a19f9d; }
  .cw-email-template-100010 .detail-label { color: #8a8886; }
  .cw-email-template-100010 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100010 .action-card { background: #292929; }
  .cw-email-template-100010 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100010 .footer-copy { color: #8a8886; }
  .cw-email-template-100010 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100010">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>新订单待支付</h1>
                      <p class="email-lead">{{display_name}}，您有一笔新订单待支付。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">应付金额</td><td class="detail-value">¥{{amount}}{{order_amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期日期</td><td class="detail-value">{{due_date}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">请在订单有效期内完成付款，付款后系统会继续处理开通流程。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "order_no", "invoice_no", "product_name", "billing_cycle_label", "amount", "order_amount", "due_date"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'order_created',
                'sort_order' => 10,
            ],
            [
                'code' => '100011',
                'name' => '第1次支付提醒',
                'description' => '订单或账单第一次支付提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】第1次支付提醒 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100011 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100011 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100011 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100011 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100011 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100011 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100011 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100011 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100011 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100011 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100011 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100011 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100011 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100011 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100011 .detail-label,
.cw-email-template-100011 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100011 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100011 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100011 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100011 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100011 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100011 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100011 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100011 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100011 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100011 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100011 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100011 .email-brand,
  .cw-email-template-100011 .email-hero,
  .cw-email-template-100011 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100011 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100011 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100011 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100011 .detail-label,
  .cw-email-template-100011 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100011 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100011 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100011 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100011 { background: #181818; }
  .cw-email-template-100011 .email-shell { background: #181818; }
  .cw-email-template-100011 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100011 .email-hero,
  .cw-email-template-100011 .email-section { background: #202020; }
  .cw-email-template-100011 .email-eyebrow { color: #2886de; }
  .cw-email-template-100011 h1,
  .cw-email-template-100011 .section-title,
  .cw-email-template-100011 .action-title { color: #ffffff; }
  .cw-email-template-100011 .email-lead,
  .cw-email-template-100011 .detail-value { color: #d2d0ce; }
  .cw-email-template-100011 .action-copy { color: #a19f9d; }
  .cw-email-template-100011 .detail-label { color: #8a8886; }
  .cw-email-template-100011 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100011 .action-card { background: #292929; }
  .cw-email-template-100011 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100011 .footer-copy { color: #8a8886; }
  .cw-email-template-100011 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100011">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>第1次支付提醒</h1>
                      <p class="email-lead">{{display_name}}，您的账单仍未支付。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">待付金额</td><td class="detail-value">¥{{amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期日期</td><td class="detail-value">{{due_date}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "invoice_no", "order_no", "product_name", "amount", "due_date", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'invoice_payment_reminder',
                'sort_order' => 11,
            ],
            [
                'code' => '100012',
                'name' => '第2次支付提醒',
                'description' => '订单或账单第二次支付提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】第2次支付提醒 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100012 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100012 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100012 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100012 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100012 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100012 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100012 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100012 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100012 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100012 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100012 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100012 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100012 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100012 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100012 .detail-label,
.cw-email-template-100012 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100012 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100012 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100012 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100012 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100012 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100012 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100012 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100012 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100012 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100012 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100012 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100012 .email-brand,
  .cw-email-template-100012 .email-hero,
  .cw-email-template-100012 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100012 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100012 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100012 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100012 .detail-label,
  .cw-email-template-100012 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100012 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100012 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100012 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100012 { background: #181818; }
  .cw-email-template-100012 .email-shell { background: #181818; }
  .cw-email-template-100012 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100012 .email-hero,
  .cw-email-template-100012 .email-section { background: #202020; }
  .cw-email-template-100012 .email-eyebrow { color: #2886de; }
  .cw-email-template-100012 h1,
  .cw-email-template-100012 .section-title,
  .cw-email-template-100012 .action-title { color: #ffffff; }
  .cw-email-template-100012 .email-lead,
  .cw-email-template-100012 .detail-value { color: #d2d0ce; }
  .cw-email-template-100012 .action-copy { color: #a19f9d; }
  .cw-email-template-100012 .detail-label { color: #8a8886; }
  .cw-email-template-100012 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100012 .action-card { background: #292929; }
  .cw-email-template-100012 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100012 .footer-copy { color: #8a8886; }
  .cw-email-template-100012 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100012">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>第2次支付提醒</h1>
                      <p class="email-lead">{{display_name}}，您的账单即将逾期。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">待付金额</td><td class="detail-value">¥{{amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期日期</td><td class="detail-value">{{due_date}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "invoice_no", "order_no", "product_name", "amount", "due_date", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'invoice_payment_reminder',
                'sort_order' => 12,
            ],
            [
                'code' => '100013',
                'name' => '第3次支付提醒',
                'description' => '订单或账单第三次支付提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】第3次支付提醒 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100013 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100013 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100013 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100013 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100013 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100013 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100013 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100013 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100013 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100013 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100013 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100013 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100013 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100013 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100013 .detail-label,
.cw-email-template-100013 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100013 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100013 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100013 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100013 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100013 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100013 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100013 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100013 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100013 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100013 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100013 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100013 .email-brand,
  .cw-email-template-100013 .email-hero,
  .cw-email-template-100013 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100013 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100013 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100013 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100013 .detail-label,
  .cw-email-template-100013 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100013 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100013 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100013 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100013 { background: #181818; }
  .cw-email-template-100013 .email-shell { background: #181818; }
  .cw-email-template-100013 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100013 .email-hero,
  .cw-email-template-100013 .email-section { background: #202020; }
  .cw-email-template-100013 .email-eyebrow { color: #2886de; }
  .cw-email-template-100013 h1,
  .cw-email-template-100013 .section-title,
  .cw-email-template-100013 .action-title { color: #ffffff; }
  .cw-email-template-100013 .email-lead,
  .cw-email-template-100013 .detail-value { color: #d2d0ce; }
  .cw-email-template-100013 .action-copy { color: #a19f9d; }
  .cw-email-template-100013 .detail-label { color: #8a8886; }
  .cw-email-template-100013 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100013 .action-card { background: #292929; }
  .cw-email-template-100013 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100013 .footer-copy { color: #8a8886; }
  .cw-email-template-100013 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100013">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>第3次支付提醒</h1>
                      <p class="email-lead">{{display_name}}，您的账单已逾期，请尽快处理。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">逾期金额</td><td class="detail-value">¥{{amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期日期</td><td class="detail-value">{{due_date}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "invoice_no", "order_no", "product_name", "amount", "due_date", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'invoice_payment_reminder',
                'sort_order' => 13,
            ],
            [
                'code' => '100014',
                'name' => '自动续费预告通知',
                'description' => '自动续费执行前发送提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】自动续费预告通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100014 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100014 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100014 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100014 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100014 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100014 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100014 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100014 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100014 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100014 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100014 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100014 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100014 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100014 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100014 .detail-label,
.cw-email-template-100014 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100014 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100014 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100014 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100014 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100014 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100014 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100014 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100014 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100014 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100014 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100014 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100014 .email-brand,
  .cw-email-template-100014 .email-hero,
  .cw-email-template-100014 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100014 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100014 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100014 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100014 .detail-label,
  .cw-email-template-100014 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100014 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100014 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100014 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100014 { background: #181818; }
  .cw-email-template-100014 .email-shell { background: #181818; }
  .cw-email-template-100014 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100014 .email-hero,
  .cw-email-template-100014 .email-section { background: #202020; }
  .cw-email-template-100014 .email-eyebrow { color: #2886de; }
  .cw-email-template-100014 h1,
  .cw-email-template-100014 .section-title,
  .cw-email-template-100014 .action-title { color: #ffffff; }
  .cw-email-template-100014 .email-lead,
  .cw-email-template-100014 .detail-value { color: #d2d0ce; }
  .cw-email-template-100014 .action-copy { color: #a19f9d; }
  .cw-email-template-100014 .detail-label { color: #8a8886; }
  .cw-email-template-100014 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100014 .action-card { background: #292929; }
  .cw-email-template-100014 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100014 .footer-copy { color: #8a8886; }
  .cw-email-template-100014 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100014">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>自动续费预告通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品即将自动续费。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{service_name}}{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">自动续费日期</td><td class="detail-value">{{auto_renew_date}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{urgency_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "service_name", "product_name", "expires_at", "billing_cycle_label", "auto_renew_date", "urgency_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'auto_renew_upcoming',
                'sort_order' => 14,
            ],
            [
                'code' => '100015',
                'name' => '自动续费通知',
                'description' => '自动续费订单生成或扣款时发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】自动续费通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100015 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100015 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100015 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100015 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100015 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100015 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100015 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100015 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100015 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100015 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100015 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100015 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100015 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100015 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100015 .detail-label,
.cw-email-template-100015 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100015 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100015 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100015 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100015 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100015 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100015 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100015 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100015 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100015 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100015 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100015 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100015 .email-brand,
  .cw-email-template-100015 .email-hero,
  .cw-email-template-100015 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100015 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100015 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100015 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100015 .detail-label,
  .cw-email-template-100015 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100015 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100015 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100015 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100015 { background: #181818; }
  .cw-email-template-100015 .email-shell { background: #181818; }
  .cw-email-template-100015 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100015 .email-hero,
  .cw-email-template-100015 .email-section { background: #202020; }
  .cw-email-template-100015 .email-eyebrow { color: #2886de; }
  .cw-email-template-100015 h1,
  .cw-email-template-100015 .section-title,
  .cw-email-template-100015 .action-title { color: #ffffff; }
  .cw-email-template-100015 .email-lead,
  .cw-email-template-100015 .detail-value { color: #d2d0ce; }
  .cw-email-template-100015 .action-copy { color: #a19f9d; }
  .cw-email-template-100015 .detail-label { color: #8a8886; }
  .cw-email-template-100015 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100015 .action-card { background: #292929; }
  .cw-email-template-100015 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100015 .footer-copy { color: #8a8886; }
  .cw-email-template-100015 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100015">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>自动续费通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品自动续费流程已触发。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{service_name}}{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">续费金额</td><td class="detail-value">¥{{amount}}{{order_amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">续费时间</td><td class="detail-value">{{renewed_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "service_name", "product_name", "order_no", "invoice_no", "amount", "order_amount", "renewed_at", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'auto_renew_notice',
                'sort_order' => 15,
            ],
            [
                'code' => '100016',
                'name' => '付款成功通知',
                'description' => '客户付款成功或管理员手动入账后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】付款成功通知 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100016 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100016 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100016 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100016 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100016 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100016 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100016 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100016 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100016 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100016 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100016 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100016 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100016 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100016 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100016 .detail-label,
.cw-email-template-100016 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100016 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100016 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100016 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100016 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100016 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100016 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100016 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100016 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100016 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100016 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100016 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100016 .email-brand,
  .cw-email-template-100016 .email-hero,
  .cw-email-template-100016 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100016 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100016 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100016 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100016 .detail-label,
  .cw-email-template-100016 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100016 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100016 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100016 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100016 { background: #181818; }
  .cw-email-template-100016 .email-shell { background: #181818; }
  .cw-email-template-100016 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100016 .email-hero,
  .cw-email-template-100016 .email-section { background: #202020; }
  .cw-email-template-100016 .email-eyebrow { color: #2886de; }
  .cw-email-template-100016 h1,
  .cw-email-template-100016 .section-title,
  .cw-email-template-100016 .action-title { color: #ffffff; }
  .cw-email-template-100016 .email-lead,
  .cw-email-template-100016 .detail-value { color: #d2d0ce; }
  .cw-email-template-100016 .action-copy { color: #a19f9d; }
  .cw-email-template-100016 .detail-label { color: #8a8886; }
  .cw-email-template-100016 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100016 .action-card { background: #292929; }
  .cw-email-template-100016 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100016 .footer-copy { color: #8a8886; }
  .cw-email-template-100016 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100016">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>付款成功通知</h1>
                      <p class="email-lead">{{display_name}}，您的付款已确认。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付金额</td><td class="detail-value">¥{{paid_amount}}{{amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付方式</td><td class="detail-value">{{payment_method}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">交易号</td><td class="detail-value">{{trade_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付时间</td><td class="detail-value">{{paid_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}{{remark}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "invoice_no", "order_no", "product_name", "paid_amount", "amount", "payment_method", "trade_no", "paid_at", "notice_message", "remark"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'order_paid',
                'sort_order' => 16,
            ],
            [
                'code' => '100017',
                'name' => '账单退款通知',
                'description' => '账单或订单退款后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】账单退款通知 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100017 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100017 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100017 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100017 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100017 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100017 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100017 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100017 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100017 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100017 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100017 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100017 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100017 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100017 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100017 .detail-label,
.cw-email-template-100017 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100017 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100017 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100017 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100017 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100017 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100017 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100017 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100017 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100017 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100017 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100017 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100017 .email-brand,
  .cw-email-template-100017 .email-hero,
  .cw-email-template-100017 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100017 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100017 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100017 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100017 .detail-label,
  .cw-email-template-100017 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100017 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100017 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100017 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100017 { background: #181818; }
  .cw-email-template-100017 .email-shell { background: #181818; }
  .cw-email-template-100017 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100017 .email-hero,
  .cw-email-template-100017 .email-section { background: #202020; }
  .cw-email-template-100017 .email-eyebrow { color: #2886de; }
  .cw-email-template-100017 h1,
  .cw-email-template-100017 .section-title,
  .cw-email-template-100017 .action-title { color: #ffffff; }
  .cw-email-template-100017 .email-lead,
  .cw-email-template-100017 .detail-value { color: #d2d0ce; }
  .cw-email-template-100017 .action-copy { color: #a19f9d; }
  .cw-email-template-100017 .detail-label { color: #8a8886; }
  .cw-email-template-100017 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100017 .action-card { background: #292929; }
  .cw-email-template-100017 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100017 .footer-copy { color: #8a8886; }
  .cw-email-template-100017 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100017">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>账单退款通知</h1>
                      <p class="email-lead">{{display_name}}，您的账单已退款。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">退款金额</td><td class="detail-value">¥{{refund_amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">退款方式</td><td class="detail-value">{{refund_method_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">退款时间</td><td class="detail-value">{{refunded_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">说明</td><td class="detail-value">{{refund_reason}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">退款到账时间以支付渠道实际处理为准，请以后续渠道通知为准。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "invoice_no", "order_no", "product_name", "refund_amount", "refund_method_label", "refunded_at", "refund_reason"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'order_refunded',
                'sort_order' => 17,
            ],
            [
                'code' => '100018',
                'name' => '第1次续费提醒',
                'description' => '产品到期前第一次续费提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】第1次续费提醒',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100018 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100018 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100018 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100018 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100018 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100018 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100018 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100018 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100018 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100018 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100018 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100018 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100018 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100018 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100018 .detail-label,
.cw-email-template-100018 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100018 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100018 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100018 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100018 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100018 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100018 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100018 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100018 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100018 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100018 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100018 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100018 .email-brand,
  .cw-email-template-100018 .email-hero,
  .cw-email-template-100018 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100018 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100018 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100018 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100018 .detail-label,
  .cw-email-template-100018 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100018 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100018 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100018 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100018 { background: #181818; }
  .cw-email-template-100018 .email-shell { background: #181818; }
  .cw-email-template-100018 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100018 .email-hero,
  .cw-email-template-100018 .email-section { background: #202020; }
  .cw-email-template-100018 .email-eyebrow { color: #2886de; }
  .cw-email-template-100018 h1,
  .cw-email-template-100018 .section-title,
  .cw-email-template-100018 .action-title { color: #ffffff; }
  .cw-email-template-100018 .email-lead,
  .cw-email-template-100018 .detail-value { color: #d2d0ce; }
  .cw-email-template-100018 .action-copy { color: #a19f9d; }
  .cw-email-template-100018 .detail-label { color: #8a8886; }
  .cw-email-template-100018 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100018 .action-card { background: #292929; }
  .cw-email-template-100018 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100018 .footer-copy { color: #8a8886; }
  .cw-email-template-100018 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100018">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>第1次续费提醒</h1>
                      <p class="email-lead">{{display_name}}，您的产品即将到期。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{service_name}}{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">剩余时间</td><td class="detail-value">{{days_left}} 天</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{urgency_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "service_name", "product_name", "days_left", "expires_at", "billing_cycle_label", "urgency_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_due',
                'sort_order' => 18,
            ],
            [
                'code' => '100019',
                'name' => '第2次续费提醒',
                'description' => '产品到期前第二次续费提醒。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】第2次续费提醒',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100019 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100019 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100019 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100019 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100019 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100019 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100019 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100019 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100019 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100019 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100019 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100019 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100019 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100019 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100019 .detail-label,
.cw-email-template-100019 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100019 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100019 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100019 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100019 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100019 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100019 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100019 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100019 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100019 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100019 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100019 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100019 .email-brand,
  .cw-email-template-100019 .email-hero,
  .cw-email-template-100019 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100019 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100019 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100019 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100019 .detail-label,
  .cw-email-template-100019 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100019 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100019 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100019 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100019 { background: #181818; }
  .cw-email-template-100019 .email-shell { background: #181818; }
  .cw-email-template-100019 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100019 .email-hero,
  .cw-email-template-100019 .email-section { background: #202020; }
  .cw-email-template-100019 .email-eyebrow { color: #2886de; }
  .cw-email-template-100019 h1,
  .cw-email-template-100019 .section-title,
  .cw-email-template-100019 .action-title { color: #ffffff; }
  .cw-email-template-100019 .email-lead,
  .cw-email-template-100019 .detail-value { color: #d2d0ce; }
  .cw-email-template-100019 .action-copy { color: #a19f9d; }
  .cw-email-template-100019 .detail-label { color: #8a8886; }
  .cw-email-template-100019 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100019 .action-card { background: #292929; }
  .cw-email-template-100019 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100019 .footer-copy { color: #8a8886; }
  .cw-email-template-100019 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100019">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>第2次续费提醒</h1>
                      <p class="email-lead">{{display_name}}，您的产品到期时间临近。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{service_name}}{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">剩余时间</td><td class="detail-value">{{days_left}} 天</td></tr>
                  <tr class="detail-row"><td class="detail-label">到期时间</td><td class="detail-value">{{expires_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{urgency_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "service_name", "product_name", "days_left", "expires_at", "billing_cycle_label", "urgency_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_due',
                'sort_order' => 19,
            ],
            [
                'code' => '100021',
                'name' => '管理员新订单通知',
                'description' => '客户创建新订单后通知管理员。',
                'audience' => 'admin',
                'subject' => '【{{site_name}}】管理员新订单通知 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100021 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100021 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100021 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100021 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100021 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100021 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100021 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100021 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100021 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100021 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100021 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100021 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100021 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100021 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100021 .detail-label,
.cw-email-template-100021 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100021 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100021 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100021 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100021 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100021 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100021 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100021 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100021 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100021 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100021 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100021 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100021 .email-brand,
  .cw-email-template-100021 .email-hero,
  .cw-email-template-100021 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100021 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100021 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100021 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100021 .detail-label,
  .cw-email-template-100021 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100021 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100021 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100021 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100021 { background: #181818; }
  .cw-email-template-100021 .email-shell { background: #181818; }
  .cw-email-template-100021 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100021 .email-hero,
  .cw-email-template-100021 .email-section { background: #202020; }
  .cw-email-template-100021 .email-eyebrow { color: #2886de; }
  .cw-email-template-100021 h1,
  .cw-email-template-100021 .section-title,
  .cw-email-template-100021 .action-title { color: #ffffff; }
  .cw-email-template-100021 .email-lead,
  .cw-email-template-100021 .detail-value { color: #d2d0ce; }
  .cw-email-template-100021 .action-copy { color: #a19f9d; }
  .cw-email-template-100021 .detail-label { color: #8a8886; }
  .cw-email-template-100021 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100021 .action-card { background: #292929; }
  .cw-email-template-100021 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100021 .footer-copy { color: #8a8886; }
  .cw-email-template-100021 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100021">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>管理员新订单通知</h1>
                      <p class="email-lead">{{recipient_name}}，有客户创建了新订单。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">客户</td><td class="detail-value">{{user_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户邮箱</td><td class="detail-value">{{user_email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单类型</td><td class="detail-value">{{order_type_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单金额</td><td class="detail-value">¥{{order_amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单状态</td><td class="detail-value">{{order_status_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">创建时间</td><td class="detail-value">{{created_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">请在后台确认订单状态，必要时跟进支付或开通流程。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "recipient_name", "user_name", "user_email", "order_no", "invoice_no", "order_type_label", "product_name", "billing_cycle_label", "order_amount", "order_status_label", "created_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'admin_order_created',
                'sort_order' => 21,
            ],
            [
                'code' => '100022',
                'name' => '管理员订单支付通知',
                'description' => '客户订单支付完成后通知管理员。',
                'audience' => 'admin',
                'subject' => '【{{site_name}}】管理员订单支付通知 #{{invoice_no}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100022 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100022 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100022 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100022 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100022 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100022 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100022 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100022 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100022 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100022 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100022 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100022 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100022 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100022 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100022 .detail-label,
.cw-email-template-100022 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100022 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100022 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100022 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100022 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100022 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100022 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100022 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100022 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100022 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100022 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100022 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100022 .email-brand,
  .cw-email-template-100022 .email-hero,
  .cw-email-template-100022 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100022 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100022 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100022 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100022 .detail-label,
  .cw-email-template-100022 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100022 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100022 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100022 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100022 { background: #181818; }
  .cw-email-template-100022 .email-shell { background: #181818; }
  .cw-email-template-100022 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100022 .email-hero,
  .cw-email-template-100022 .email-section { background: #202020; }
  .cw-email-template-100022 .email-eyebrow { color: #2886de; }
  .cw-email-template-100022 h1,
  .cw-email-template-100022 .section-title,
  .cw-email-template-100022 .action-title { color: #ffffff; }
  .cw-email-template-100022 .email-lead,
  .cw-email-template-100022 .detail-value { color: #d2d0ce; }
  .cw-email-template-100022 .action-copy { color: #a19f9d; }
  .cw-email-template-100022 .detail-label { color: #8a8886; }
  .cw-email-template-100022 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100022 .action-card { background: #292929; }
  .cw-email-template-100022 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100022 .footer-copy { color: #8a8886; }
  .cw-email-template-100022 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100022">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>管理员订单支付通知</h1>
                      <p class="email-lead">{{recipient_name}}，有客户完成了订单支付。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">客户</td><td class="detail-value">{{user_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户邮箱</td><td class="detail-value">{{user_email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">订单号</td><td class="detail-value">{{order_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">账单号</td><td class="detail-value">{{invoice_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">付款周期</td><td class="detail-value">{{billing_cycle_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付金额</td><td class="detail-value">¥{{paid_amount}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付方式</td><td class="detail-value">{{payment_method}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">交易号</td><td class="detail-value">{{trade_no}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">支付时间</td><td class="detail-value">{{paid_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">请核对支付记录，并关注后续开通或交付结果。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "recipient_name", "user_name", "user_email", "order_no", "invoice_no", "product_name", "billing_cycle_label", "paid_amount", "payment_method", "trade_no", "paid_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'admin_order_paid',
                'sort_order' => 22,
            ],
            [
                'code' => '100023',
                'name' => '工单开通通知',
                'description' => '客户工单创建成功后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】工单开通通知 #{{ticket_id}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100023 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100023 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100023 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100023 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100023 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100023 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100023 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100023 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100023 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100023 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100023 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100023 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100023 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100023 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100023 .detail-label,
.cw-email-template-100023 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100023 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100023 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100023 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100023 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100023 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100023 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100023 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100023 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100023 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100023 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100023 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100023 .email-brand,
  .cw-email-template-100023 .email-hero,
  .cw-email-template-100023 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100023 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100023 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100023 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100023 .detail-label,
  .cw-email-template-100023 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100023 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100023 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100023 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100023 { background: #181818; }
  .cw-email-template-100023 .email-shell { background: #181818; }
  .cw-email-template-100023 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100023 .email-hero,
  .cw-email-template-100023 .email-section { background: #202020; }
  .cw-email-template-100023 .email-eyebrow { color: #2886de; }
  .cw-email-template-100023 h1,
  .cw-email-template-100023 .section-title,
  .cw-email-template-100023 .action-title { color: #ffffff; }
  .cw-email-template-100023 .email-lead,
  .cw-email-template-100023 .detail-value { color: #d2d0ce; }
  .cw-email-template-100023 .action-copy { color: #a19f9d; }
  .cw-email-template-100023 .detail-label { color: #8a8886; }
  .cw-email-template-100023 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100023 .action-card { background: #292929; }
  .cw-email-template-100023 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100023 .footer-copy { color: #8a8886; }
  .cw-email-template-100023 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100023">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>工单开通通知</h1>
                      <p class="email-lead">{{display_name}}，您的工单已创建。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">工单编号</td><td class="detail-value">#{{ticket_id}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">工单标题</td><td class="detail-value">{{ticket_subject}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">部门</td><td class="detail-value">{{department}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">优先级</td><td class="detail-value">{{priority}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">创建时间</td><td class="detail-value">{{ticket_created_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{message_preview}} {{login_tip}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "ticket_id", "ticket_subject", "department", "priority", "ticket_created_at", "message_preview", "login_tip"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'ticket_opened',
                'sort_order' => 23,
            ],
            [
                'code' => '100024',
                'name' => '工单新回复通知',
                'description' => '管理员回复客户工单后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】工单新回复通知 #{{ticket_id}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100024 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100024 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100024 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100024 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100024 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100024 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100024 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100024 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100024 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100024 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100024 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100024 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100024 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100024 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100024 .detail-label,
.cw-email-template-100024 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100024 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100024 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100024 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100024 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100024 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100024 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100024 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100024 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100024 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100024 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100024 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100024 .email-brand,
  .cw-email-template-100024 .email-hero,
  .cw-email-template-100024 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100024 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100024 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100024 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100024 .detail-label,
  .cw-email-template-100024 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100024 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100024 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100024 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100024 { background: #181818; }
  .cw-email-template-100024 .email-shell { background: #181818; }
  .cw-email-template-100024 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100024 .email-hero,
  .cw-email-template-100024 .email-section { background: #202020; }
  .cw-email-template-100024 .email-eyebrow { color: #2886de; }
  .cw-email-template-100024 h1,
  .cw-email-template-100024 .section-title,
  .cw-email-template-100024 .action-title { color: #ffffff; }
  .cw-email-template-100024 .email-lead,
  .cw-email-template-100024 .detail-value { color: #d2d0ce; }
  .cw-email-template-100024 .action-copy { color: #a19f9d; }
  .cw-email-template-100024 .detail-label { color: #8a8886; }
  .cw-email-template-100024 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100024 .action-card { background: #292929; }
  .cw-email-template-100024 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100024 .footer-copy { color: #8a8886; }
  .cw-email-template-100024 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100024">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>工单新回复通知</h1>
                      <p class="email-lead">{{display_name}}，您的工单有新回复。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">工单编号</td><td class="detail-value">#{{ticket_id}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">工单标题</td><td class="detail-value">{{ticket_subject}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">状态</td><td class="detail-value">{{status}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">回复人</td><td class="detail-value">{{staff_name}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{message_preview}} {{login_tip}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "ticket_id", "ticket_subject", "status", "staff_name", "message_preview", "tickets_url", "login_tip"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'ticket_replied',
                'sort_order' => 24,
            ],
            [
                'code' => '100025',
                'name' => '工单自动关闭通知',
                'description' => '工单自动关闭后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】工单自动关闭通知 #{{ticket_id}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100025 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100025 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100025 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100025 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100025 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100025 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100025 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100025 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100025 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100025 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100025 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100025 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100025 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100025 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100025 .detail-label,
.cw-email-template-100025 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100025 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100025 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100025 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100025 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100025 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100025 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100025 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100025 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100025 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100025 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100025 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100025 .email-brand,
  .cw-email-template-100025 .email-hero,
  .cw-email-template-100025 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100025 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100025 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100025 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100025 .detail-label,
  .cw-email-template-100025 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100025 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100025 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100025 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100025 { background: #181818; }
  .cw-email-template-100025 .email-shell { background: #181818; }
  .cw-email-template-100025 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100025 .email-hero,
  .cw-email-template-100025 .email-section { background: #202020; }
  .cw-email-template-100025 .email-eyebrow { color: #2886de; }
  .cw-email-template-100025 h1,
  .cw-email-template-100025 .section-title,
  .cw-email-template-100025 .action-title { color: #ffffff; }
  .cw-email-template-100025 .email-lead,
  .cw-email-template-100025 .detail-value { color: #d2d0ce; }
  .cw-email-template-100025 .action-copy { color: #a19f9d; }
  .cw-email-template-100025 .detail-label { color: #8a8886; }
  .cw-email-template-100025 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100025 .action-card { background: #292929; }
  .cw-email-template-100025 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100025 .footer-copy { color: #8a8886; }
  .cw-email-template-100025 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100025">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>工单自动关闭通知</h1>
                      <p class="email-lead">{{display_name}}，您的工单已自动关闭。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">工单编号</td><td class="detail-value">#{{ticket_id}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">工单标题</td><td class="detail-value">{{ticket_subject}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">关闭时间</td><td class="detail-value">{{ticket_closed_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">关闭原因</td><td class="detail-value">{{close_reason}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">如果问题仍未解决，可以重新提交工单并说明最新情况。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "ticket_id", "ticket_subject", "ticket_closed_at", "close_reason"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'ticket_auto_closed',
                'sort_order' => 25,
            ],
            [
                'code' => '100026',
                'name' => '管理员新工单提示',
                'description' => '客户提交新工单后通知管理员。',
                'audience' => 'admin',
                'subject' => '【{{site_name}}】管理员新工单提示 #{{ticket_id}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100026 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100026 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100026 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100026 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100026 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100026 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100026 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100026 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100026 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100026 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100026 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100026 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100026 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100026 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100026 .detail-label,
.cw-email-template-100026 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100026 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100026 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100026 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100026 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100026 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100026 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100026 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100026 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100026 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100026 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100026 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100026 .email-brand,
  .cw-email-template-100026 .email-hero,
  .cw-email-template-100026 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100026 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100026 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100026 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100026 .detail-label,
  .cw-email-template-100026 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100026 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100026 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100026 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100026 { background: #181818; }
  .cw-email-template-100026 .email-shell { background: #181818; }
  .cw-email-template-100026 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100026 .email-hero,
  .cw-email-template-100026 .email-section { background: #202020; }
  .cw-email-template-100026 .email-eyebrow { color: #2886de; }
  .cw-email-template-100026 h1,
  .cw-email-template-100026 .section-title,
  .cw-email-template-100026 .action-title { color: #ffffff; }
  .cw-email-template-100026 .email-lead,
  .cw-email-template-100026 .detail-value { color: #d2d0ce; }
  .cw-email-template-100026 .action-copy { color: #a19f9d; }
  .cw-email-template-100026 .detail-label { color: #8a8886; }
  .cw-email-template-100026 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100026 .action-card { background: #292929; }
  .cw-email-template-100026 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100026 .footer-copy { color: #8a8886; }
  .cw-email-template-100026 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100026">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>管理员新工单提示</h1>
                      <p class="email-lead">{{recipient_name}}，有客户提交了新工单。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">工单编号</td><td class="detail-value">#{{ticket_id}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">标题</td><td class="detail-value">{{ticket_subject}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">部门</td><td class="detail-value">{{department}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">优先级</td><td class="detail-value">{{priority}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">状态</td><td class="detail-value">{{status}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户</td><td class="detail-value">{{client_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户邮箱</td><td class="detail-value">{{client_email}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{message_preview}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "recipient_name", "ticket_id", "ticket_subject", "department", "priority", "status", "client_name", "client_email", "message_preview"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'admin_ticket_created',
                'sort_order' => 26,
            ],
            [
                'code' => '100027',
                'name' => '管理员工单回复通知',
                'description' => '客户回复工单后通知管理员。',
                'audience' => 'admin',
                'subject' => '【{{site_name}}】管理员工单回复通知 #{{ticket_id}}',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100027 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100027 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100027 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100027 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100027 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100027 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100027 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100027 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100027 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100027 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100027 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100027 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100027 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100027 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100027 .detail-label,
.cw-email-template-100027 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100027 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100027 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100027 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100027 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100027 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100027 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100027 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100027 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100027 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100027 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100027 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100027 .email-brand,
  .cw-email-template-100027 .email-hero,
  .cw-email-template-100027 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100027 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100027 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100027 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100027 .detail-label,
  .cw-email-template-100027 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100027 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100027 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100027 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100027 { background: #181818; }
  .cw-email-template-100027 .email-shell { background: #181818; }
  .cw-email-template-100027 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100027 .email-hero,
  .cw-email-template-100027 .email-section { background: #202020; }
  .cw-email-template-100027 .email-eyebrow { color: #2886de; }
  .cw-email-template-100027 h1,
  .cw-email-template-100027 .section-title,
  .cw-email-template-100027 .action-title { color: #ffffff; }
  .cw-email-template-100027 .email-lead,
  .cw-email-template-100027 .detail-value { color: #d2d0ce; }
  .cw-email-template-100027 .action-copy { color: #a19f9d; }
  .cw-email-template-100027 .detail-label { color: #8a8886; }
  .cw-email-template-100027 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100027 .action-card { background: #292929; }
  .cw-email-template-100027 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100027 .footer-copy { color: #8a8886; }
  .cw-email-template-100027 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100027">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>管理员工单回复通知</h1>
                      <p class="email-lead">{{recipient_name}}，客户补充了新的工单回复。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">工单编号</td><td class="detail-value">#{{ticket_id}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">标题</td><td class="detail-value">{{ticket_subject}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">部门</td><td class="detail-value">{{department}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">优先级</td><td class="detail-value">{{priority}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">状态</td><td class="detail-value">{{status}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户</td><td class="detail-value">{{client_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">客户邮箱</td><td class="detail-value">{{client_email}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{message_preview}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "recipient_name", "ticket_id", "ticket_subject", "department", "priority", "status", "client_name", "client_email", "message_preview"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'admin_ticket_replied',
                'sort_order' => 27,
            ],
            [
                'code' => '100028',
                'name' => '管理员登录提示',
                'description' => '管理员登录后台后发送安全提醒。',
                'audience' => 'admin',
                'subject' => '【{{site_name}}】管理员登录提示',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100028 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100028 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100028 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100028 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100028 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100028 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100028 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100028 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100028 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100028 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100028 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100028 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100028 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100028 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100028 .detail-label,
.cw-email-template-100028 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100028 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100028 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100028 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100028 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100028 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100028 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100028 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100028 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100028 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100028 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100028 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100028 .email-brand,
  .cw-email-template-100028 .email-hero,
  .cw-email-template-100028 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100028 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100028 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100028 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100028 .detail-label,
  .cw-email-template-100028 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100028 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100028 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100028 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100028 { background: #181818; }
  .cw-email-template-100028 .email-shell { background: #181818; }
  .cw-email-template-100028 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100028 .email-hero,
  .cw-email-template-100028 .email-section { background: #202020; }
  .cw-email-template-100028 .email-eyebrow { color: #2886de; }
  .cw-email-template-100028 h1,
  .cw-email-template-100028 .section-title,
  .cw-email-template-100028 .action-title { color: #ffffff; }
  .cw-email-template-100028 .email-lead,
  .cw-email-template-100028 .detail-value { color: #d2d0ce; }
  .cw-email-template-100028 .action-copy { color: #a19f9d; }
  .cw-email-template-100028 .detail-label { color: #8a8886; }
  .cw-email-template-100028 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100028 .action-card { background: #292929; }
  .cw-email-template-100028 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100028 .footer-copy { color: #8a8886; }
  .cw-email-template-100028 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100028">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>管理员登录提示</h1>
                      <p class="email-lead">{{recipient_name}}，您的管理员账号发生登录行为。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">登录账号</td><td class="detail-value">{{account}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">登录时间</td><td class="detail-value">{{login_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">IP 地址</td><td class="detail-value">{{ip}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">设备</td><td class="detail-value">{{device}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">如果不是您本人登录，请立即修改密码并检查后台账号权限。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "recipient_name", "account", "login_at", "ip", "device"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'admin_login_alert',
                'sort_order' => 28,
            ],
            [
                'code' => '100029',
                'name' => '产品解除停用失败通知',
                'description' => '产品解除停用失败后发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】产品解除停用失败通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100029 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100029 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100029 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100029 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100029 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100029 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100029 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100029 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100029 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100029 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100029 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100029 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100029 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100029 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100029 .detail-label,
.cw-email-template-100029 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100029 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100029 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100029 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100029 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100029 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100029 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100029 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100029 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100029 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100029 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100029 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100029 .email-brand,
  .cw-email-template-100029 .email-hero,
  .cw-email-template-100029 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100029 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100029 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100029 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100029 .detail-label,
  .cw-email-template-100029 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100029 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100029 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100029 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100029 { background: #181818; }
  .cw-email-template-100029 .email-shell { background: #181818; }
  .cw-email-template-100029 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100029 .email-hero,
  .cw-email-template-100029 .email-section { background: #202020; }
  .cw-email-template-100029 .email-eyebrow { color: #2886de; }
  .cw-email-template-100029 h1,
  .cw-email-template-100029 .section-title,
  .cw-email-template-100029 .action-title { color: #ffffff; }
  .cw-email-template-100029 .email-lead,
  .cw-email-template-100029 .detail-value { color: #d2d0ce; }
  .cw-email-template-100029 .action-copy { color: #a19f9d; }
  .cw-email-template-100029 .detail-label { color: #8a8886; }
  .cw-email-template-100029 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100029 .action-card { background: #292929; }
  .cw-email-template-100029 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100029 .footer-copy { color: #8a8886; }
  .cw-email-template-100029 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100029">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>产品解除停用失败通知</h1>
                      <p class="email-lead">{{display_name}}，您的产品解除停用失败。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">产品名称</td><td class="detail-value">{{product_name}}{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">失败原因</td><td class="detail-value">{{failure_reason}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">处理时间</td><td class="detail-value">{{failed_at}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">系统已记录本次失败原因，请联系支持继续处理恢复事项。</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "product_name", "service_name", "failure_reason", "failed_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_suspend_failed',
                'sort_order' => 29,
            ],
            [
                'code' => '100030',
                'name' => '实例归属变更通知',
                'description' => '管理员转移实例归属后通知原持有者与接收方。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】实例归属{{direction_label}}通知',
                'content' => '<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
body {
  margin: 0;
  padding: 0;
  background: #f4f5f7;
  color: #1b1b1b;
  font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "PingFang SC", "Microsoft YaHei", "Hiragino Sans GB", "Helvetica Neue", Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.cw-email-template-100030 {
  width: 100%;
  background: #f4f5f7;
}
.cw-email-template-100030 .email-shell {
  width: 100%;
  background: #f4f5f7;
  padding: 40px 16px;
}
.cw-email-template-100030 .email-card {
  max-width: 600px;
  background: #ffffff;
  border-radius: 4px;
  border-collapse: separate;
  border-spacing: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 0 1px rgba(0, 0, 0, 0.08);
}
.cw-email-template-100030 .email-brand {
  padding: 32px 40px 16px;
  white-space: nowrap;
}
.cw-email-template-100030 .email-logo {
  max-width: 148px;
  max-height: 36px;
  display: block;
  border: 0;
}
.cw-email-template-100030 .email-hero {
  padding: 8px 40px 16px;
}
.cw-email-template-100030 .hero-copy {
  vertical-align: top;
}
.cw-email-template-100030 .email-eyebrow {
  color: #0067b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
}
.cw-email-template-100030 h1 {
  margin: 6px 0 0;
  color: #1b1b1b;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.2px;
}
.cw-email-template-100030 .email-lead {
  margin: 12px 0 0;
  color: #444444;
  font-size: 14px;
  line-height: 1.6;
}
.cw-email-template-100030 .email-section {
  padding: 12px 40px 0;
}
.cw-email-template-100030 .section-title {
  margin-bottom: 12px;
  color: #1b1b1b;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.2px;
}
.cw-email-template-100030 .detail-table {
  width: 100%;
  background: #ffffff;
  border-collapse: collapse;
}
.cw-email-template-100030 .detail-row {
  border-bottom: 1px solid #edebe9;
}
.cw-email-template-100030 .detail-label,
.cw-email-template-100030 .detail-value {
  padding: 12px 0;
  font-size: 13px;
  line-height: 1.5;
  vertical-align: top;
}
.cw-email-template-100030 .detail-label {
  width: 32%;
  color: #605e5c;
  font-weight: 400;
}
.cw-email-template-100030 .detail-value {
  color: #1b1b1b;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
}
.cw-email-template-100030 .email-action {
  margin-bottom: 24px;
}
.cw-email-template-100030 .action-card {
  background: #f8f9fa;
  padding: 12px 14px;
  border-radius: 4px;
}
.cw-email-template-100030 .action-title {
  color: #1b1b1b;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.cw-email-template-100030 .action-copy {
  margin-top: 4px;
  color: #605e5c;
  font-size: 12px;
  line-height: 1.6;
}
.cw-email-template-100030 .email-footer {
  padding: 28px 40px 36px;
  border-top: 1px solid #edebe9;
  margin-top: 24px;
}
.cw-email-template-100030 .footer-copy {
  margin: 0;
  color: #797775;
  font-size: 11px;
  line-height: 1.6;
}
.cw-email-template-100030 .footer-brand {
  margin: 4px 0 0;
  color: #a19f9d;
  font-size: 11px;
  font-weight: 500;
}
@media screen and (max-width: 640px) {
  .cw-email-template-100030 .email-shell {
    padding: 0 !important;
  }
  .cw-email-template-100030 .email-card {
    border-radius: 0 !important;
    box-shadow: none !important;
  }
  .cw-email-template-100030 .email-brand,
  .cw-email-template-100030 .email-hero,
  .cw-email-template-100030 .email-section {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  .cw-email-template-100030 .email-footer {
    padding: 24px 20px 32px !important;
  }
  .cw-email-template-100030 h1 {
    font-size: 19px !important;
  }
  .cw-email-template-100030 .detail-row {
    display: block;
    padding: 8px 0;
  }
  .cw-email-template-100030 .detail-label,
  .cw-email-template-100030 .detail-value {
    display: block;
    width: 100% !important;
    padding: 1px 0 !important;
    text-align: left !important;
  }
  .cw-email-template-100030 .detail-label {
    font-size: 12px;
    color: #797775;
  }
  .cw-email-template-100030 .detail-value {
    font-size: 13px;
    color: #1b1b1b;
  }
  .cw-email-template-100030 .email-brand {
    white-space: normal;
  }
}
@media (prefers-color-scheme: dark) {
  .cw-email-template-100030 { background: #181818; }
  .cw-email-template-100030 .email-shell { background: #181818; }
  .cw-email-template-100030 .email-card { background: #202020; box-shadow: none; }
  .cw-email-template-100030 .email-hero,
  .cw-email-template-100030 .email-section { background: #202020; }
  .cw-email-template-100030 .email-eyebrow { color: #2886de; }
  .cw-email-template-100030 h1,
  .cw-email-template-100030 .section-title,
  .cw-email-template-100030 .action-title { color: #ffffff; }
  .cw-email-template-100030 .email-lead,
  .cw-email-template-100030 .detail-value { color: #d2d0ce; }
  .cw-email-template-100030 .action-copy { color: #a19f9d; }
  .cw-email-template-100030 .detail-label { color: #8a8886; }
  .cw-email-template-100030 .detail-row { border-color: #2d2d2d; }
  .cw-email-template-100030 .action-card { background: #292929; }
  .cw-email-template-100030 .email-footer { border-color: #2d2d2d; }
  .cw-email-template-100030 .footer-copy { color: #8a8886; }
  .cw-email-template-100030 .footer-brand { color: #605e5c; }
}
  </style>
</head>
<body>
  <div class="cw-email-template cw-email-template-100030">
    <table role="presentation" class="email-shell" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td align="center">
          <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
          <table role="presentation" class="email-card" cellpadding="0" cellspacing="0" width="100%">
            <tr>
              <td class="email-brand">
                {{#site_logo}}<img class="email-logo" src="{{site_logo}}" alt="{{site_name}}" width="148" height="36">{{/site_logo}}
              </td>
            </tr>
            <tr>
              <td class="email-hero">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td class="hero-copy">
                      <div class="email-eyebrow">系统通知</div>
                      <h1>实例归属变更通知</h1>
                      <p class="email-lead">{{display_name}}，您名下的实例归属发生变更（{{direction_label}}）。</p>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-section">
                <div class="section-title">关键信息</div>
                <table role="presentation" class="detail-table" cellpadding="0" cellspacing="0" width="100%">
                  <tr class="detail-row"><td class="detail-label">实例名称</td><td class="detail-value">{{service_name}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">变更方向</td><td class="detail-value">{{direction_label}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">对方账号</td><td class="detail-value">{{counterparty_email}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">变更时间</td><td class="detail-value">{{transferred_at}}</td></tr>
                  <tr class="detail-row"><td class="detail-label">管理员备注</td><td class="detail-value">{{remark}}</td></tr>
                </table>
              </td>
            </tr>
            <tr>
              <td class="email-footer">
                <div class="email-action">
                  <div class="action-card">
                    <div class="action-title">处理建议</div>
                    <div class="action-copy">{{notice_message}}</div>
                  </div>
                </div>
                <p class="footer-copy">此为系统自动发送邮件，请勿直接回复。</p>
                <p class="footer-brand">{{site_name}}</p>
              </td>
            </tr>
          </table>
          <!--[if mso]></td></tr></table><![endif]-->
        </td>
      </tr>
    </table>
  </div>
</body>
</html>',
                'variables_json' => '["site_name", "site_logo", "display_name", "service_name", "direction_label", "counterparty_email", "remark", "transferred_at", "notice_message"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_transferred',
                'sort_order' => 30,
            ],
            [
                'code' => '100044',
                'name' => '实名认证通过',
                'description' => '实名认证审核通过后通知客户。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】实名认证通过',
                'content' => '<p>{{display_name}}，您提交的实名认证审核已通过，可以正常使用全部服务。</p>',
                'variables_json' => '["display_name"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'real_name_approved',
                'sort_order' => 42,
            ],
            [
                'code' => '100046',
                'name' => '服务已到期通知',
                'description' => '服务到期后、暂停前发送通知。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】服务已到期通知',
                'content' => '<p>{{display_name}}，您的服务 {{service_name}} 已于 {{expires_at}} 到期。</p><p>请尽快续费以恢复使用，逾期未续费将被暂停。</p>',
                'variables_json' => '["display_name", "service_name", "product_name", "expires_at"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'service_expired',
                'sort_order' => 44,
            ],
            [
                'code' => '100048',
                'name' => '账号绑定成功',
                'description' => '账号绑定成功邮件模板。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】账号绑定成功',
                'content' => '<p>{{display_name}}，您的账号已成功绑定。</p><p>如非本人操作，请立即修改密码并联系管理员。</p>',
                'variables_json' => '["display_name"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'account_bound',
                'sort_order' => 40,
            ],
            [
                'code' => '100063',
                'name' => '工单待处理提醒通知',
                'description' => '工单待处理提醒邮件模板。',
                'audience' => 'user',
                'subject' => '【{{site_name}}】工单待处理提醒 #{{ticket_id}}',
                'content' => '<p>{{display_name}}，您的工单「{{ticket_subject}}」客服已回复。</p><p>若在 {{hours_left}} 小时内无后续回复，工单将自动关闭，请及时查看。</p>',
                'variables_json' => '["display_name", "ticket_id", "ticket_subject", "hours_left"]',
                'provider_variables_json' => '[]',
                'scene_code' => 'ticket_pending_reminder',
                'sort_order' => 73,
            ],
        ];
    }
};

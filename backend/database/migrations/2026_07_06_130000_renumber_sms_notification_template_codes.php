<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach ($this->codeMap() as $legacyCode => $numericCode) {
            $this->renumberTemplate($legacyCode, $numericCode);
            $this->renameSettingKey('sms_template_content_'.$legacyCode, 'sms_template_content_'.$numericCode);
            $this->renameSettingKey('sms_template_provider_template_id_'.$legacyCode, 'sms_template_provider_template_id_'.$numericCode);
        }

        if (Schema::hasTable('settings')) {
            foreach ($this->codeMap() as $legacyCode => $numericCode) {
                DB::table('settings')
                    ->where('group_key', 'notification')
                    ->where('item_key', 'sms_template_code')
                    ->where('item_value', $legacyCode)
                    ->update(['item_value' => $numericCode]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        foreach (array_reverse($this->codeMap()) as $legacyCode => $numericCode) {
            $this->renumberTemplate($numericCode, $legacyCode);
            $this->renameSettingKey('sms_template_content_'.$numericCode, 'sms_template_content_'.$legacyCode);
            $this->renameSettingKey('sms_template_provider_template_id_'.$numericCode, 'sms_template_provider_template_id_'.$legacyCode);
        }
    }

    private function renumberTemplate(string $fromCode, string $toCode): void
    {
        $source = DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('code', $fromCode)
            ->first();

        if ($source === null) {
            return;
        }

        $target = DB::table('notification_templates')
            ->where('channel', 'sms')
            ->where('code', $toCode)
            ->first();

        if ($target === null) {
            DB::table('notification_templates')
                ->where('id', $source->id)
                ->update([
                    'code' => $toCode,
                    'updated_at' => now(),
                ]);

            return;
        }

        if ((bool) ($source->is_custom ?? false) && ! (bool) ($target->is_custom ?? false)) {
            DB::table('notification_templates')
                ->where('id', $target->id)
                ->update([
                    'subject' => $source->subject,
                    'content' => $source->content,
                    'provider_template_id' => $source->provider_template_id,
                    'is_custom' => true,
                    'updated_at' => now(),
                ]);
        }

        DB::table('notification_templates')
            ->where('id', $source->id)
            ->delete();
    }

    private function renameSettingKey(string $fromKey, string $toKey): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $source = DB::table('settings')
            ->where('group_key', 'notification')
            ->where('item_key', $fromKey)
            ->first();

        if ($source === null) {
            return;
        }

        $targetExists = DB::table('settings')
            ->where('group_key', 'notification')
            ->where('item_key', $toKey)
            ->exists();

        if (! $targetExists) {
            DB::table('settings')
                ->where('id', $source->id)
                ->update(['item_key' => $toKey]);

            return;
        }

        DB::table('settings')
            ->where('id', $source->id)
            ->delete();
    }

    /**
     * @return array<string, string>
     */
    private function codeMap(): array
    {
        return [
            'send_code' => '100001',
            'login_sms_remind' => '100002',
            'second_renew_product_reminder' => '100003',
            'unpay_invoice' => '100004',
            'invoice_overdue_pay' => '100005',
            'host_suspend' => '100006',
            'resume_use' => '100007',
            'credit_limit_invoice_notice' => '100008',
            'invoice_pay' => '100009',
            'submit_ticket' => '100010',
            'ticket_reply' => '100012',
            'new_order_notice' => '100013',
            'invoice_payment_confirmation' => '100014',
            'renew_product_reminder' => '100020',
            'order_refund' => '100021',
            'third_invoice_payment_reminder' => '100022',
            'second_invoice_payment_reminder' => '100023',
            'first_invoice_payment_reminder' => '100024',
            'default_product_welcome' => '100025',
            'service_termination_notification' => '100026',
            'uncertify_reminder' => '100027',
            'support_ticket_opened' => '100028',
            'email_bond_notice' => '100029',
            'registration_success' => '100030',
            'service_unsuspension_notification' => '100031',
            'realname_pass_remind' => '100032',
            'binding_remind' => '100033',
        ];
    }
};

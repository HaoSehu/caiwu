// ─── Dashboard ────────────────────────────────────────────────
export interface DashboardStats {
  today?: {
    income?: number | string;
    new_invoices?: number | string;
    new_users?: number | string;
  };
  month?: {
    income?: number | string;
    new_invoices?: number | string;
    new_users?: number | string;
  };
  counts?: {
    active_services?: number | string;
    total_invoices?: number | string;
    open_tickets?: number | string;
    total_users?: number | string;
  };
}

export interface RecentInvoice {
  id: number | string;
  invoice_no?: string;
  amount?: number | string;
  status?: number | string;
  created_at?: string;
}

export interface MonthlyRevenue {
  month_label?: string;
  revenue_by_product?: Array<{
    label?: string;
    product_name?: string;
    name?: string;
    income?: number | string;
    amount?: number | string;
    value?: number | string;
  }>;
  daily_revenue?: Array<{
    date?: string;
    day?: string;
    income?: number | string;
    amount?: number | string;
  }>;
}

// ─── Invoice ──────────────────────────────────────────────────
export interface InvoiceListParams {
  keyword?: string;
  type?: string;
  status?: string | number;
  date_range?: string[] | null;
  page?: number;
  page_size?: number;
}

export interface InvoiceRecord {
  id?: number | string;
  invoice_no?: string;
  order_no?: string;
  type?: string;
  type_label?: string;
  amount?: number | string;
  paid_amount?: number | string;
  status?: number | string;
  raw_status?: number | string;
  status_label?: string;
  created_at?: string;
  paid_at?: string | null;
  due_date?: string | null;
  user?: Record<string, unknown>;
  order?: Record<string, unknown> | null;
  product?: Record<string, unknown> | null;
  product_display_name?: string;
  product_spec_display?: string;
  combined_display_name?: string;
  summary?: Record<string, unknown>;
  payment_summary?: Record<string, unknown>;
  scene?: Record<string, unknown>;
  payments?: Record<string, unknown>[];
  items?: Record<string, unknown>[];
  logs?: Record<string, unknown>[];
  [key: string]: unknown;
}

export interface InvoiceDetailResponse extends InvoiceRecord {
  invoice?: InvoiceRecord;
  payments?: Record<string, unknown>[];
  items?: Record<string, unknown>[];
  logs?: Record<string, unknown>[];
}

// ─── Order ────────────────────────────────────────────────────
export interface OrderListParams {
  keyword?: string;
  type?: string;
  kind?: string;
  status?: string | number;
  date_range?: string[] | null;
  page?: number;
  page_size?: number;
}

export interface OrderRecord {
  id: number | string;
  order_no?: string;
  user_id?: number | string;
  user?: Record<string, unknown>;
  product_name?: string;
  product_full_path?: string;
  service?: Record<string, unknown> | null;
  type?: string;
  type_label?: string;
  addon_kind_label?: string;
  addon_target_label?: string;
  addon_mode?: string;
  amount?: number | string;
  quantity?: number | string;
  status?: number | string;
  status_label?: string;
  invoice?: Record<string, unknown> | null;
  created_at?: string;
  [key: string]: unknown;
}

// ─── Finance Menu ─────────────────────────────────────────────
export interface RechargeListParams {
  keyword?: string;
  status?: string | number;
  date_range?: string[] | null;
  page?: number;
  page_size?: number;
}

export interface RechargeRecord {
  id: number | string;
  payment_no?: string;
  invoice_no?: string;
  invoice_id?: number | string | null;
  gateway?: string;
  gateway_label?: string;
  trade_no?: string;
  user?: Record<string, unknown>;
  payment?: Record<string, unknown> | null;
  invoice?: Record<string, unknown> | null;
  order?: Record<string, unknown> | null;
  amount?: number | string;
  paid_amount?: number | string;
  status?: number | string;
  status_label?: string;
  created_at?: string;
  paid_at?: string | null;
  [key: string]: unknown;
}

export interface NewCustomerSummaryParams {
  start_date: string;
  end_date: string;
}

export interface NewCustomerDailyRecord {
  date: string;
  new_customers?: number | string;
  new_orders?: number | string;
  completed_orders?: number | string;
  new_tickets?: number | string;
  ticket_replies?: number | string;
  cancel_requests?: number | string;
  [key: string]: unknown;
}

export interface NewCustomerDailySummary {
  summary?: Record<string, number | string>;
  list?: NewCustomerDailyRecord[];
}

// ─── Settings ─────────────────────────────────────────────────
export interface SettingItem {
  key: string;
  value: string | number | boolean | null;
}

export interface ScheduleOverview {
  tasks?: Record<string, unknown>[];
  recent_logs?: Record<string, unknown>[];
}

// ─── Logs ─────────────────────────────────────────────────────
export interface LogListParams {
  page?: number;
  per_page?: number;
  keyword?: string;
  start_date?: string;
  end_date?: string;
  level?: string;
  task_key?: string;
  method?: string;
  module?: string;
  user_type?: string;
  status?: string | number;
  phone?: string;
  email?: string;
}

export interface LaravelPagination<T extends Record<string, unknown> = Record<string, unknown>> {
  data?: T[];
  total?: number;
  current_page?: number;
  per_page?: number;
  summary?: Record<string, unknown>;
}

export interface LogCleanupPayload {
  type: string;
  keep_days: number;
  confirm_text: string;
}

// ─── Content ──────────────────────────────────────────────────
export interface ContentListParams {
  content_type?: string;
  keyword?: string;
  category_id?: number | string;
  status?: number | string;
  is_pinned?: number | string;
  page?: number;
  page_size?: number;
}

export interface ContentCategoryRecord {
  id: number | string;
  name?: string;
  slug?: string | null;
  description?: string | null;
  status?: number | string;
  sort_order?: number | string;
  articles_count?: number | string;
  [key: string]: unknown;
}

export interface ContentCategoryPayload {
  content_type: string;
  name: string;
  slug: string | null;
  description: string | null;
  status: number;
  sort_order: number;
}

export interface ContentArticleRecord {
  id: number | string;
  title?: string;
  category_id?: number | string | null;
  category_name?: string;
  content_category?: ContentCategoryRecord | null;
  slug?: string | null;
  summary?: string | null;
  excerpt?: string | null;
  content?: string;
  keywords?: string | null;
  status?: number | string;
  status_label?: string;
  is_pinned?: number | string;
  is_recommended?: number | string;
  cover_image?: string | null;
  sort_order?: number | string;
  publish_at?: string | null;
  view_count?: number | string;
  operator?: string | null;
  remark?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  [key: string]: unknown;
}

export interface ContentArticlePayload {
  content_type: string;
  category_id: number;
  title: string;
  slug: string | null;
  summary: string | null;
  content: string;
  keywords: string | null;
  status: number;
  is_pinned: number;
  is_recommended: number;
  cover_image: string | null;
  sort_order: number;
  publish_at: string | null;
  operator: string | null;
  remark: string | null;
  require_reread?: boolean;
}

// ─── Media ────────────────────────────────────────────────────
export interface MediaFileRecord {
  id?: number | string;
  filename?: string;
  url?: string;
  path?: string;
  width?: number;
  height?: number;
  [key: string]: unknown;
}

// ─── Site Hero ────────────────────────────────────────────────
export interface HomeHeroSlide {
  rail_title?: string;
  title?: string;
  desc?: string;
  primary_text?: string;
  primary_path?: string;
  secondary_text?: string;
  secondary_path?: string;
  [key: string]: unknown;
}

export interface HomeHeroFeature {
  kicker?: string;
  title?: string;
  desc?: string;
  path?: string;
  [key: string]: unknown;
}

export interface HomeHeroPayload {
  slides?: HomeHeroSlide[];
  features?: HomeHeroFeature[];
  defaults?: {
    slides?: HomeHeroSlide[];
    features?: HomeHeroFeature[];
  };
  [key: string]: unknown;
}

// ─── Spec Catalog ─────────────────────────────────────────────
export interface ProductBindingRecord {
  product_id?: number;
  display_name?: string;
  product_display_name?: string;
  custom_display_name?: string;
  cpu_memory_display?: string;
  cpu_memory_slug_display?: string;
  product_spec_display?: string;
  combined_display_name?: string;
  category_full_name?: string;
  primary_price?: {
    cycle?: string;
    amount?: string;
  };
  status?: number;
}

export interface InstanceSpecRecord {
  id: string | number;
  value?: string;
  text?: string;
  name?: string;
  alias?: string;
  note?: string;
  status?: string;
  sort_order?: number;
  bindings?: ProductBindingRecord[];
  binding_ids?: number[];
  [key: string]: unknown;
}

export interface CpuModelRecord {
  id: string | number;
  value?: string;
  name?: string;
  base_frequency?: string;
  turbo_frequency?: string;
  sort_order?: number;
  bindings?: ProductBindingRecord[];
  binding_ids?: number[];
  [key: string]: unknown;
}

export interface CpuModelGroupRecord {
  id: string | number;
  value?: string;
  name?: string;
  sort_order?: number;
  model_count?: number;
  models?: CpuModelRecord[];
  [key: string]: unknown;
}

// ─── Coupon ───────────────────────────────────────────────────
export interface CouponListParams {
  keyword?: string;
  status?: string | number;
  discount_type?: string;
  discount_scope?: string;
  distribution_type?: string;
  page?: number;
  page_size?: number;
}

export interface CouponRecord {
  id: number | string;
  name?: string;
  description?: string | null;
  remark?: string | null;
  distribution_type?: string;
  distribution_type_label?: string;
  coupon_campaign_name?: string | null;
  discount_scope?: string;
  discount_scope_label?: string;
  discount_type?: string;
  discount_type_label?: string;
  discount_value?: number | string;
  discount_value_raw?: number | string;
  discount_label?: string;
  min_amount?: number | string;
  min_amount_raw?: number | string;
  max_discount_amount?: number | string | null;
  max_discount_amount_raw?: number | string | null;
  billing_cycles?: string[];
  billing_cycle_text?: string;
  product_ids?: number[];
  product_scope_text?: string;
  first_order_only?: boolean | number;
  user_ids?: number[];
  used_count?: number | string;
  total_usage_limit?: number | string | null;
  per_user_limit?: number | string | null;
  remaining_stock?: number | string | null;
  status?: number | string;
  display_status?: string;
  display_status_label?: string;
  display_status_reason?: string;
  validity_text?: string;
  starts_at?: string | null;
  expires_at?: string | null;
  sort_order?: number | string;
  can_delete?: boolean;
  updated_at?: string;
  [key: string]: unknown;
}

export interface CouponPayload {
  name: string;
  description: string | null;
  distribution_type: string;
  discount_scope: string;
  discount_type: string;
  discount_value: number;
  min_amount: number;
  max_discount_amount: number | null;
  billing_cycles: string[];
  product_ids: number[];
  first_order_only: boolean;
  user_ids: number[];
  total_usage_limit: number | null;
  per_user_limit: number | null;
  status: number;
  sort_order: number;
  starts_at: string | null;
  expires_at: string | null;
  remark: string | null;
}

export interface CouponCampaignListParams {
  keyword?: string;
  status?: string | number;
  page?: number;
  page_size?: number;
}

export interface CouponCampaignRecord {
  id: number | string;
  name?: string;
  description?: string | null;
  remark?: string | null;
  weekdays?: number[];
  trigger_time?: string;
  schedule_text?: string;
  next_run_at?: string | null;
  issue_quantity?: number | string;
  valid_duration_hours?: number | string | null;
  discount_type?: string;
  discount_type_label?: string;
  discount_scope?: string;
  discount_scope_label?: string;
  discount_value?: number | string;
  discount_value_raw?: number | string;
  discount_label?: string;
  min_amount?: number | string;
  min_amount_raw?: number | string;
  max_discount_amount?: number | string | null;
  max_discount_amount_raw?: number | string | null;
  billing_cycles?: string[];
  billing_cycle_text?: string;
  product_ids?: number[];
  product_scope_text?: string;
  first_order_only?: boolean | number;
  per_user_limit?: number | string | null;
  status?: number | string;
  display_status?: string;
  display_status_label?: string;
  last_dispatched_at?: string | null;
  last_coupon_name?: string | null;
  last_coupon_code?: string | null;
  generated_coupon_count?: number | string;
  sort_order?: number | string;
  updated_at?: string;
  [key: string]: unknown;
}

export interface CouponCampaignPayload {
  name: string;
  description: string | null;
  weekdays: number[];
  trigger_time: string | null;
  issue_quantity: number;
  valid_duration_hours: number | null;
  discount_type: string;
  discount_scope: string;
  discount_value: number;
  min_amount: number;
  max_discount_amount: number | null;
  billing_cycles: string[];
  product_ids: number[];
  first_order_only: boolean;
  per_user_limit: number | null;
  status: number;
  sort_order: number;
  remark: string | null;
}

// ─── Referral ─────────────────────────────────────────────────
export interface ReferralListParams {
  keyword?: string;
  status?: string | number;
  page?: number;
  page_size?: number;
}

export interface ReferralOverview {
  summary?: Record<string, unknown>;
  top_referrers?: Array<Record<string, unknown>>;
}

export interface ReferralRewardRecord {
  id: number | string;
  referrer?: Record<string, unknown> | null;
  referred_user?: Record<string, unknown> | null;
  order?: Record<string, unknown> | null;
  product?: Record<string, unknown> | null;
  order_amount?: number | string;
  reward_rate?: number | string;
  reward_amount?: number | string;
  status?: number | string;
  rewarded_at?: string | null;
  available_at?: string | null;
  released_at?: string | null;
  remark?: string | null;
  [key: string]: unknown;
}

export interface ReferralWithdrawalRecord {
  id: number | string;
  user?: Record<string, unknown> | null;
  amount?: number | string;
  method?: string;
  account_name?: string | null;
  account_no?: string | null;
  status?: number | string;
  operator?: string | null;
  remark?: string | null;
  created_at?: string | null;
  processed_at?: string | null;
  [key: string]: unknown;
}

export interface ReferralWithdrawalPayload {
  remark?: string;
}

// ─── Ticket ───────────────────────────────────────────────────
export interface TicketListParams {
  keyword?: string;
  status?: string | number;
  priority?: string | number;
  department?: string;
  page?: number;
  page_size?: number;
}

export interface TicketRecord {
  id: number | string;
  subject?: string;
  status?: number | string;
  priority?: number | string;
  department?: string;
  content?: string;
  attachments?: TicketAttachment[];
  attachment_urls?: Array<string | TicketAttachment>;
  user_id?: number | string;
  user?: Record<string, unknown>;
  assignee?: Record<string, unknown>;
  updated_at?: string;
  created_at?: string;
}

export interface TicketAttachment {
  id?: number | string;
  name?: string;
  path?: string;
  url?: string | null;
  deleted?: boolean;
  type?: string;
  [key: string]: unknown;
}

export interface TicketReply {
  id: number | string;
  ticket_id?: number | string;
  user_id?: number | string;
  content?: string;
  is_staff?: boolean | number;
  sender_name?: string;
  attachments?: TicketAttachment[];
  attachment_urls?: Array<string | TicketAttachment>;
  recalled?: boolean;
  recalled_at?: string | null;
  quote?: {
    id?: number | string;
    sender_name?: string;
    content?: string;
    recalled?: boolean;
  } | null;
  created_at?: string;
}

export interface TicketDetail extends TicketRecord {
  content?: string;
  close_reason?: string | null;
  close_reason_label?: string | null;
  service_id?: number | string | null;
  assignee_id?: number | string | null;
  service?: {
    id?: number | string;
    name?: string;
    display_name?: string;
    product_name?: string;
    expires_at?: string;
    connection?: {
      dedicated_ip?: string;
      internal_ip?: string;
      username?: string;
      password?: string;
      has_password?: boolean;
      port?: number | string;
    };
    specs?: Array<{ label?: string; name?: string; value?: unknown; text?: unknown }>;
    [key: string]: unknown;
  } | null;
  replies?: TicketReply[];
}

export interface TicketAdminUser {
  id: number | string;
  username?: string;
  nickname?: string;
  email?: string;
}

// ─── Verification ─────────────────────────────────────────────
export interface VerificationListParams {
  keyword?: string;
  verification_status?: number | string;
  is_verified?: number | string;
  page?: number;
  page_size?: number;
}

export interface VerificationRecord {
  id: number | string;
  display_name?: string;
  email?: string;
  phone?: string;
  real_name?: string;
  id_card?: string;
  id_card_masked?: string;
  verification_status?: number | string;
  verification_message?: string;
  verification_certify_id?: string;
  verification_method_label?: string;
  verification_type_label?: string;
  document_type_label?: string;
  identity_region_label?: string;
  created_at?: string;
  updated_at?: string;
  verified_at?: string;
  submitted_at?: string;
}

export interface VerificationSettingsPayload {
  verification_api?: string;
  verification_key?: string;
  verification_biz_code?: string;
  free_attempts?: number;
  retry_fee?: number;
  [key: string]: unknown;
}

// ─── Member Level ─────────────────────────────────────────────
export interface MemberLevelRecord {
  id: number | string;
  name?: string;
  code?: string | null;
  sales_amount_min?: number | string;
  sales_amount_max?: number | string | null;
  reward_rate?: number | string;
  status?: number | string;
  sort_order?: number | string;
  remark?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  [key: string]: unknown;
}

export interface MemberLevelPayload {
  name: string;
  code: string | null;
  sales_amount_min: number;
  sales_amount_max: number | null;
  reward_rate: number;
  status: number;
  sort_order: number;
  remark: string | null;
}

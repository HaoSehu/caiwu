<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function out($msg) { fwrite(STDOUT, $msg . "\n"); }

// Q1: 今年消费了多少
out("=== Q1: 消费记录 ===");
$cnt26 = DB::table('account_transactions')->where('user_id', 1)->where('created_at', '>=', '2026-01-01')->count();
$out26 = DB::table('account_transactions')->where('user_id', 1)->where('created_at', '>=', '2026-01-01')->where('change_amount', '<', 0)->sum('change_amount');
out("2026: count=$cnt26, outflow=$out26");
$cnt25 = DB::table('account_transactions')->where('user_id', 1)->whereBetween('created_at', ['2025-01-01', '2025-12-31'])->count();
$out25 = DB::table('account_transactions')->where('user_id', 1)->whereBetween('created_at', ['2025-01-01', '2025-12-31'])->where('change_amount', '<', 0)->sum('change_amount');
out("2025: count=$cnt25, outflow=$out25");

// Q2: 已购买哪些服务
out("=== Q2: 服务实例 ===");
$services = DB::table('services')->where('user_id', 1)->select('id', 'name', 'status', 'expires_at')->get();
out("Services count: " . $services->count());
foreach ($services as $s) out("  Service #{$s->id}: {$s->name} status={$s->status} expires={$s->expires_at}");

// Q4: 未支付订单
out("=== Q4: 未支付订单 ===");
$pending = DB::table('orders')->where('user_id', 1)->where('status', 0)->count();
out("Pending orders: $pending");

// Q5: 已支付订单
out("=== Q5: 已支付订单 ===");
$paid = DB::table('orders')->where('user_id', 1)->where('status', 1)->count();
out("Paid orders: $paid");
$total = DB::table('orders')->where('user_id', 1)->count();
out("Total orders: $total");

// Q7: 用户信息
out("=== Q7: 用户信息 ===");
$user = DB::table('users')->where('id', 1)->select('id', 'nickname', 'email', 'phone', 'verification_status', 'real_name', 'is_verified')->first();
out("User: nickname={$user->nickname} email={$user->email} phone={$user->phone} verify_status={$user->verification_status} real_name={$user->real_name} is_verified={$user->is_verified}");

// Q8: 优惠券
out("=== Q8: 优惠券 ===");
$coupons = DB::table('user_coupons')->where('user_id', 1)->select('id', 'coupon_id', 'status', 'used_at')->get();
out("User coupons: " . $coupons->count());
foreach ($coupons as $c) out("  Coupon #{$c->coupon_id} status={$c->status} used_at={$c->used_at}");

// Q9: 交易记录
out("=== Q9: 交易记录 ===");
$ledger = DB::table('account_transactions')->where('user_id', 1)->orderByDesc('id')->limit(5)->select('id', 'event_type', 'change_amount', 'balance_after', 'remark', 'created_at')->get();
out("Recent transactions: " . $ledger->count());
foreach ($ledger as $l) out("  #{$l->id} type={$l->event_type} change={$l->change_amount} after={$l->balance_after} remark={$l->remark} at={$l->created_at}");

// Q10: 哪些机器/配置
out("=== Q10: 机器/配置 ===");
$svcs = DB::table('services as s')
    ->leftJoin('products as p', 's.product_id', '=', 'p.id')
    ->where('s.user_id', 1)
    ->select('s.id', 's.name', 's.status', 'p.custom_display_name as product_name')
    ->get();
out("Machines: " . $svcs->count());
foreach ($svcs as $s) out("  #{$s->id} name={$s->name} product={$s->product_name} status={$s->status}");

// Q11: id=2 用户的机器
out("=== Q11: User 2 machines ===");
$svcs2 = DB::table('services')->where('user_id', 2)->select('id', 'name', 'status')->get();
out("User 2 services: " . $svcs2->count());
foreach ($svcs2 as $s) out("  #{$s->id} name={$s->name} status={$s->status}");

// Q12: 工单数
out("=== Q12: 工单 ===");
$tickets = DB::table('tickets')->where('user_id', 1)->count();
out("User 1 tickets: $tickets");

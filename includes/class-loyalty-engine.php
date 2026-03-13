<?php

if (!defined('ABSPATH')) exit;

/**
 * TGS_Loyalty_Engine — Xử lý tích điểm, đổi điểm, tính hạng.
 *
 * Gọi TGS_Loyalty_DB trực tiếp cho ví & balance (đã gộp wallet).
 */

// =========================================================================
// GLOBAL HELPER FUNCTIONS
// =========================================================================

function tgs_loyalty_calculate_earn_points($context)
{
    return TGS_Loyalty_Engine::calculate_earn_points($context);
}

function tgs_loyalty_earn_points($wp_user_id, $order_total, $context = [])
{
    return TGS_Loyalty_Engine::earn_points($wp_user_id, $order_total, $context);
}

function tgs_loyalty_redeem_points($wp_user_id, $points, $context = [])
{
    return TGS_Loyalty_Engine::redeem_points($wp_user_id, $points, $context);
}

function tgs_loyalty_get_member_info($wp_user_id)
{
    return TGS_Loyalty_Engine::get_member_info($wp_user_id);
}

function tgs_loyalty_get_rewards_for_customer($customer_id, $context = [])
{
    return TGS_Loyalty_Engine::get_rewards_for_customer($customer_id, $context);
}

function tgs_loyalty_issue_reward_vouchers($wp_user_id, $selected_rewards, $context = [])
{
    return TGS_Loyalty_Engine::issue_reward_vouchers($wp_user_id, $selected_rewards, $context);
}

function tgs_loyalty_apply_issued_voucher($coupon_code, $context = [])
{
    return TGS_Loyalty_Engine::apply_issued_voucher($coupon_code, $context);
}

function tgs_loyalty_consume_issued_vouchers($promotions, $context = [])
{
    return TGS_Loyalty_Engine::consume_issued_vouchers($promotions, $context);
}

// =========================================================================
// ENGINE CLASS
// =========================================================================

class TGS_Loyalty_Engine
{
    const OPTION_ISSUED_VOUCHERS = 'tgs_loyalty_issued_vouchers';

    /**
     * Tính điểm nhận được từ đơn hàng (preview, chưa cộng thật).
     *
     * @param array $context {
     *     @type int   store_id      Blog ID
     *     @type float order_total   Tổng đơn hàng
     *     @type int   wp_user_id    WordPress user ID (khóa chung)
     *     @type array cart_items    [{sku, qty, price}]
     * }
     */
    public static function calculate_earn_points($context)
    {
        $store_id    = intval($context['store_id'] ?? get_current_blog_id());
        $order_total = floatval($context['order_total'] ?? 0);
        $wp_user_id  = intval($context['wp_user_id'] ?? 0);
        $cart_items  = $context['cart_items'] ?? [];
        $settings    = TGS_Loyalty_DB::get_settings();

        $policies = self::get_active_policies($store_id);
        if (empty($policies)) {
            return ['total_points' => 0, 'details' => []];
        }

        // Hệ số nhân tier
        $multiplier = 1.0;
        if (!empty($settings['enable_tier_multiplier']) && $wp_user_id) {
            $tier_key = get_user_meta($wp_user_id, 'loyalty_tier', true) ?: TGS_Loyalty_DB::get_default_tier_key();
            $tiers = TGS_Loyalty_DB::get_tier_definitions();
            if (isset($tiers[$tier_key])) {
                $multiplier = floatval($tiers[$tier_key]['multiplier'] ?? 1.0);
            }
        }

        $total_points = 0;
        $details      = [];

        foreach ($policies as $policy) {
            $result = self::evaluate_policy($policy, $order_total, $cart_items, $multiplier);
            if ($result && $result['points'] > 0) {
                $total_points += $result['points'];
                $details[] = [
                    'policy_id'    => $policy->loyalty_policy_id,
                    'policy_code'  => $policy->loyalty_policy_code,
                    'policy_title' => $policy->loyalty_policy_title,
                    'policy_type'  => $policy->loyalty_policy_type,
                    'points'       => $result['points'],
                    'description'  => $result['description'] ?? '',
                ];
            }
        }

        return [
            'total_points' => $total_points,
            'multiplier'   => $multiplier,
            'details'      => $details,
        ];
    }

    /**
     * Cộng điểm thật — delegate sang wallet API + ghi loyalty log.
     */
    public static function earn_points($wp_user_id, $order_total, $context = [])
    {
        $wp_user_id = intval($wp_user_id);
        if ($wp_user_id <= 0 || $order_total <= 0) {
            return ['success' => false, 'message' => 'Thông tin không hợp lệ.'];
        }

        $context['wp_user_id'] = $wp_user_id;
        $context['order_total'] = $order_total;
        if (empty($context['store_id'])) {
            $context['store_id'] = get_current_blog_id();
        }

        $calc = self::calculate_earn_points($context);
        $points = $calc['total_points'];
        if ($points <= 0) {
            return ['success' => false, 'message' => 'Không có chính sách tích điểm phù hợp.'];
        }

        // Lấy balance trước khi cộng
        $balance_before = self::get_user_balance($wp_user_id);

        // Cộng vào wallet
        $wallet_result = self::wallet_add_balance($wp_user_id, $points, implode('; ', array_map(function ($d) {
            return $d['policy_title'] . ': +' . $d['points'];
        }, $calc['details'])), [
            'reference_id'   => sanitize_text_field($context['reference_id'] ?? ''),
            'reference_type' => 'loyalty_earn',
        ]);

        if (is_wp_error($wallet_result)) {
            return ['success' => false, 'message' => $wallet_result->get_error_message()];
        }

        $balance_after = self::get_user_balance($wp_user_id);

        // Cập nhật tier
        $total_earned = self::get_user_total_earned($wp_user_id);
        $new_tier = TGS_Loyalty_DB::determine_tier($total_earned);
        update_user_meta($wp_user_id, 'loyalty_tier', $new_tier);

        // Ghi loyalty log
        $desc_parts = [];
        foreach ($calc['details'] as $d) {
            $desc_parts[] = $d['policy_title'] . ': +' . $d['points'] . ' điểm';
        }

        $log_id = TGS_Loyalty_DB::add_log([
            'wp_user_id'    => $wp_user_id,
            'log_type'      => TGS_Loyalty_DB::LOG_EARN,
            'points'        => $points,
            'points_before' => $balance_before,
            'points_after'  => $balance_after,
            'description'   => implode('; ', $desc_parts),
            'reference_id'  => sanitize_text_field($context['reference_id'] ?? ''),
            'reference_type' => sanitize_text_field($context['reference_type'] ?? 'order'),
            'policy_id'     => $calc['details'][0]['policy_id'] ?? null,
            'log_meta'      => wp_json_encode($calc),
            'created_by'    => get_current_user_id(),
            'blog_id'       => get_current_blog_id(),
        ]);

        do_action('tgs_loyalty_points_earned', $wp_user_id, $points, $balance_after, $log_id, $calc);

        return [
            'success'       => true,
            'points_earned' => $points,
            'points_before' => $balance_before,
            'points_after'  => $balance_after,
            'tier'          => $new_tier,
            'log_id'        => $log_id,
            'details'       => $calc['details'],
        ];
    }

    /**
     * Đổi / trừ điểm — delegate sang wallet API.
     */
    public static function redeem_points($wp_user_id, $points, $context = [])
    {
        $wp_user_id = intval($wp_user_id);
        $points     = floatval($points);
        if ($wp_user_id <= 0 || $points <= 0) {
            return ['success' => false, 'message' => 'Thông tin không hợp lệ.'];
        }

        $settings = TGS_Loyalty_DB::get_settings();
        $min_redeem_points = floatval($settings['min_redeem_points'] ?? 0);
        if ($min_redeem_points > 0 && $points < $min_redeem_points) {
            return ['success' => false, 'message' => 'Số điểm đổi tối thiểu là ' . number_format($min_redeem_points) . '.'];
        }

        $balance_before = self::get_user_balance($wp_user_id);
        if ($balance_before < $points) {
            return ['success' => false, 'message' => 'Không đủ điểm. Hiện có: ' . number_format($balance_before)];
        }

        // Trừ wallet
        $wallet_result = self::wallet_deduct_balance(
            $wp_user_id,
            $points,
            sanitize_text_field($context['description'] ?? 'Đổi điểm'),
            [
                'reference_id'   => sanitize_text_field($context['reference_id'] ?? ''),
                'reference_type' => 'loyalty_redeem',
            ]
        );

        if (is_wp_error($wallet_result)) {
            return ['success' => false, 'message' => $wallet_result->get_error_message()];
        }

        $balance_after = self::get_user_balance($wp_user_id);

        // Ghi loyalty log
        $log_id = TGS_Loyalty_DB::add_log([
            'wp_user_id'     => $wp_user_id,
            'log_type'       => TGS_Loyalty_DB::LOG_REDEEM,
            'points'         => $points,
            'points_before'  => $balance_before,
            'points_after'   => $balance_after,
            'description'    => sanitize_text_field($context['description'] ?? 'Đổi điểm'),
            'reference_id'   => sanitize_text_field($context['reference_id'] ?? ''),
            'reference_type' => sanitize_text_field($context['reference_type'] ?? 'redeem'),
            'created_by'     => get_current_user_id(),
            'blog_id'        => get_current_blog_id(),
        ]);

        do_action('tgs_loyalty_points_redeemed', $wp_user_id, $points, $balance_after, $log_id);

        return [
            'success'         => true,
            'points_redeemed' => $points,
            'points_before'   => $balance_before,
            'points_after'    => $balance_after,
            'log_id'          => $log_id,
        ];
    }

    /**
     * Lấy thông tin member: balance từ wallet, tier từ usermeta.
     */
    public static function get_member_info($wp_user_id)
    {
        $wp_user_id = intval($wp_user_id);
        if ($wp_user_id <= 0) return null;

        $user = get_userdata($wp_user_id);
        if (!$user) return null;

        $balance      = self::get_user_balance($wp_user_id);
        $total_earned = self::get_user_total_earned($wp_user_id);
        $tier_key     = get_user_meta($wp_user_id, 'loyalty_tier', true) ?: TGS_Loyalty_DB::get_default_tier_key();

        $tiers = TGS_Loyalty_DB::get_tier_definitions();
        $tier  = $tiers[$tier_key] ?? reset($tiers);

        // Tìm next tier
        $next_tier      = null;
        $points_to_next = 0;
        $found_current  = false;
        foreach ($tiers as $key => $t) {
            if ($found_current) {
                $next_tier      = $t;
                $points_to_next = $t['min_points'] - $total_earned;
                break;
            }
            if ($key === $tier_key) {
                $found_current = true;
            }
        }

        return [
            'wp_user_id'     => $wp_user_id,
            'display_name'   => $user->display_name,
            'user_email'     => $user->user_email,
            'current_points' => $balance,
            'total_earned'   => $total_earned,
            'tier'           => $tier,
            'tier_key'       => $tier_key,
            'tier_badge'     => TGS_Loyalty_DB::tier_badge($tier_key),
            'next_tier'      => $next_tier,
            'points_to_next' => max(0, $points_to_next),
            'multiplier'     => floatval($tier['multiplier'] ?? 1.0),
        ];
    }

    public static function get_rewards_for_customer($customer_id, $context = [])
    {
        $customer_id = intval($customer_id);
        $default = [
            'available_rewards' => [],
            'customer_points'   => 0,
        ];

        if ($customer_id <= 0) {
            return $default;
        }

        $wp_user_id = self::get_wp_user_id_from_customer($customer_id);
        if ($wp_user_id <= 0) {
            return $default;
        }

        TGS_Loyalty_DB::ensure_wallet($wp_user_id);

        $member_info = self::get_member_info($wp_user_id);
        $current_points = floatval($member_info['current_points'] ?? 0);
        $tier_key = sanitize_key($member_info['tier_key'] ?? TGS_Loyalty_DB::get_default_tier_key());
        $store_id = intval($context['store_id'] ?? get_current_blog_id());
        $order_total = floatval($context['order_total'] ?? 0);
        $policies = self::get_active_policies($store_id, true, [5, 6]);
        $available_rewards = [];
        $now = current_time('timestamp');

        foreach ($policies as $policy) {
            $policy_type = intval($policy->loyalty_policy_type);
            $cfg = TGS_Loyalty_DB::parse_rules($policy);
            if ($cfg['min_order_amount'] > 0 && $order_total > 0 && $order_total < $cfg['min_order_amount']) {
                continue;
            }

            $reward_rows = $cfg['rules']['rewards'] ?? [];
            if (!is_array($reward_rows)) {
                continue;
            }

            foreach ($reward_rows as $reward) {
                if (!is_array($reward) || intval($reward['status'] ?? 1) !== 1) {
                    continue;
                }

                $start_at = !empty($reward['start_at']) ? strtotime((string) $reward['start_at']) : 0;
                $end_at = !empty($reward['end_at']) ? strtotime((string) $reward['end_at']) : 0;
                if (($start_at && $now < $start_at) || ($end_at && $now > $end_at)) {
                    continue;
                }

                $min_tier = sanitize_key($reward['min_tier'] ?? TGS_Loyalty_DB::get_default_tier_key());
                if (!self::is_tier_at_least($tier_key, $min_tier)) {
                    continue;
                }

                $effective_points = self::resolve_reward_points($reward, $tier_key);
                if ($effective_points <= 0) {
                    continue;
                }

                $sku = sanitize_text_field($reward['sku'] ?? '');
                $reward_code = sanitize_key($reward['reward_code'] ?? ($sku ?: ('reward_' . $policy->loyalty_policy_id . '_' . count($available_rewards))));
                $reward_name = sanitize_text_field($reward['name'] ?? '');
                $product_id = intval($reward['product_id'] ?? 0);
                if ($reward_name === '' && $product_id > 0) {
                    $reward_name = get_the_title($product_id) ?: 'Quà đổi điểm';
                }
                if ($reward_name === '') {
                    $reward_name = $policy_type === 6 ? 'Voucher đổi điểm' : 'Quà đổi điểm';
                }

                $reward_payload = [
                    'index'                  => 'lp_' . intval($policy->loyalty_policy_id) . '_' . $reward_code,
                    'promoId'                => 'loyalty_reward_' . intval($policy->loyalty_policy_id),
                    'policy_id'              => intval($policy->loyalty_policy_id),
                    'program_id'             => intval($policy->loyalty_policy_id),
                    'program_code'           => sanitize_text_field($policy->loyalty_policy_code),
                    'program_title'          => sanitize_text_field($policy->loyalty_policy_title),
                    'reward_code'            => $reward_code,
                    'reward_type'            => $policy_type === 6 ? 'voucher' : 'product',
                    'product_id'             => $product_id,
                    'productId'              => $product_id,
                    'sku'                    => $sku,
                    'product_name'           => $reward_name,
                    'quantity'               => max(1, intval($reward['quantity'] ?? 1)),
                    'points'                 => $effective_points,
                    'base_points'            => max(0, intval($reward['points_required'] ?? 0)),
                    'customer_points'        => $current_points,
                    'remaining_points_after' => max(0, $current_points - $effective_points),
                    'can_redeem'             => $current_points >= $effective_points,
                    'canRedeem'              => $current_points >= $effective_points,
                    'is_tracking'            => $policy_type === 6 ? 0 : self::resolve_reward_tracking_flag($reward),
                    'tier_key'               => $tier_key,
                    'min_tier'               => $min_tier,
                ];

                if ($policy_type === 6) {
                    $reward_payload['voucher_prefix'] = sanitize_text_field($reward['voucher_prefix'] ?? strtoupper($policy->loyalty_policy_code) . '-');
                    $reward_payload['discount_type'] = sanitize_key($reward['discount_type'] ?? 'fixed');
                    $reward_payload['discount_value'] = floatval($reward['discount_value'] ?? 0);
                    $reward_payload['max_discount'] = floatval($reward['max_discount'] ?? 0);
                    $reward_payload['min_order_amount'] = floatval($reward['min_order_amount'] ?? 0);
                    $reward_payload['expires_in_days'] = max(0, intval($reward['expires_in_days'] ?? 0));
                    $reward_payload['voucher_quantity'] = max(1, intval($reward['quantity'] ?? 1));
                    $reward_payload['quantity'] = max(1, intval($reward['quantity'] ?? 1));
                }

                $available_rewards[] = $reward_payload;
            }
        }

        return [
            'available_rewards' => array_values($available_rewards),
            'customer_points'   => $current_points,
            'customer'          => [
                'wp_user_id'     => $wp_user_id,
                'tier_key'       => $tier_key,
                'tier_name'      => sanitize_text_field($member_info['tier']['name'] ?? $tier_key),
                'multiplier'     => floatval($member_info['multiplier'] ?? 1),
                'points_to_next' => floatval($member_info['points_to_next'] ?? 0),
            ],
        ];
    }

    public static function issue_reward_vouchers($wp_user_id, $selected_rewards, $context = [])
    {
        $wp_user_id = intval($wp_user_id);
        $selected_rewards = is_array($selected_rewards) ? $selected_rewards : [];
        if ($wp_user_id <= 0 || empty($selected_rewards)) {
            return [];
        }

        $registry = self::get_voucher_registry();
        $issued = [];
        $now = current_time('timestamp');
        $store_id = intval($context['store_id'] ?? get_current_blog_id());

        foreach ($selected_rewards as $reward) {
            if (!is_array($reward) || sanitize_key($reward['reward_type'] ?? '') !== 'voucher') {
                continue;
            }

            $quantity = max(1, intval($reward['voucher_quantity'] ?? $reward['quantity'] ?? 1));
            $discount_type = sanitize_key($reward['discount_type'] ?? 'fixed');
            $discount_value = floatval($reward['discount_value'] ?? 0);
            if ($discount_value <= 0) {
                continue;
            }

            $expires_in_days = max(0, intval($reward['expires_in_days'] ?? 0));
            $expires_at = $expires_in_days > 0 ? strtotime('+' . $expires_in_days . ' days', $now) : 0;
            $prefix = sanitize_text_field($reward['voucher_prefix'] ?? strtoupper(sanitize_key($reward['reward_code'] ?? 'LP')) . '-');

            for ($index = 0; $index < $quantity; $index++) {
                $code = self::generate_voucher_code($prefix, $registry);
                $voucher = [
                    'code' => $code,
                    'reward_code' => sanitize_key($reward['reward_code'] ?? ''),
                    'reward_name' => sanitize_text_field($reward['product_name'] ?? $reward['name'] ?? 'Voucher đổi điểm'),
                    'policy_id' => intval($reward['policy_id'] ?? 0),
                    'program_code' => sanitize_text_field($reward['program_code'] ?? ''),
                    'wp_user_id' => $wp_user_id,
                    'customer_id' => intval($context['customer_id'] ?? 0),
                    'store_id' => $store_id,
                    'points_cost' => floatval($reward['points'] ?? 0),
                    'discount_type' => $discount_type,
                    'discount_value' => $discount_value,
                    'max_discount' => floatval($reward['max_discount'] ?? 0),
                    'min_order_amount' => floatval($reward['min_order_amount'] ?? 0),
                    'issued_at' => $now,
                    'expires_at' => $expires_at,
                    'status' => 'active',
                    'used_count' => 0,
                    'sale_ledger_id' => intval($context['sale_ledger_id'] ?? 0),
                    'sale_code' => sanitize_text_field($context['sale_code'] ?? ''),
                ];

                $registry[$code] = $voucher;
                $issued[] = $voucher;
            }
        }

        if (!empty($issued)) {
            self::save_voucher_registry($registry);
        }

        return $issued;
    }

    public static function apply_issued_voucher($coupon_code, $context = [])
    {
        $coupon_code = strtoupper(sanitize_text_field($coupon_code));
        if ($coupon_code === '') {
            return new WP_Error('empty_code', 'Mã voucher trống');
        }

        $registry = self::get_voucher_registry();
        $voucher = $registry[$coupon_code] ?? null;
        if (empty($voucher) || !is_array($voucher)) {
            return new WP_Error('not_found', 'Voucher đổi điểm không tồn tại hoặc đã hết hạn');
        }

        if (($voucher['status'] ?? '') !== 'active') {
            return new WP_Error('inactive', 'Voucher đổi điểm không còn hiệu lực');
        }

        $now = current_time('timestamp');
        $expires_at = intval($voucher['expires_at'] ?? 0);
        if ($expires_at > 0 && $now > $expires_at) {
            return new WP_Error('expired', 'Voucher đổi điểm đã hết hạn');
        }

        $cart_total = floatval($context['cart_total'] ?? 0);
        $min_order_amount = floatval($voucher['min_order_amount'] ?? 0);
        if ($min_order_amount > 0 && $cart_total < $min_order_amount) {
            return new WP_Error('min_order', 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher');
        }

        $customer_id = intval($context['customer_id'] ?? 0);
        if ($customer_id > 0) {
            $wp_user_id = self::get_wp_user_id_from_customer($customer_id);
            if ($wp_user_id > 0 && intval($voucher['wp_user_id'] ?? 0) > 0 && intval($voucher['wp_user_id']) !== $wp_user_id) {
                return new WP_Error('owner_mismatch', 'Voucher này thuộc về khách hàng khác');
            }
        }

        $discount_type = sanitize_key($voucher['discount_type'] ?? 'fixed');
        $discount_value = floatval($voucher['discount_value'] ?? 0);
        $max_discount = floatval($voucher['max_discount'] ?? 0);
        if ($discount_value <= 0) {
            return new WP_Error('invalid_discount', 'Voucher không có giá trị giảm hợp lệ');
        }

        $discount_amount = $discount_type === 'percent'
            ? ($cart_total * ($discount_value / 100))
            : $discount_value;
        if ($discount_type === 'percent' && $max_discount > 0) {
            $discount_amount = min($discount_amount, $max_discount);
        }
        $discount_amount = max(0, min($discount_amount, $cart_total));

        if ($discount_amount <= 0) {
            return new WP_Error('not_applicable', 'Voucher chưa đủ điều kiện áp dụng');
        }

        return [
            'id' => 'loyalty_voucher_' . sanitize_key($coupon_code),
            'name' => sanitize_text_field($voucher['reward_name'] ?? ('Voucher ' . $coupon_code)),
            'type' => 'voucher',
            'code' => $coupon_code,
            'priority' => 0,
            'appliedViaCoupon' => true,
            'calculated_result' => [
                'discount_amount' => $discount_amount,
                'discount_type' => $discount_type,
                'discount_value' => $discount_value,
                'max_discount' => $max_discount,
                'min_order_amount' => $min_order_amount,
                'loyalty_voucher' => true,
                'voucher_code' => $coupon_code,
            ],
        ];
    }

    public static function consume_issued_vouchers($promotions, $context = [])
    {
        $promotions = is_array($promotions) ? $promotions : [];
        if (empty($promotions)) {
            return [];
        }

        $registry = self::get_voucher_registry();
        $consumed = [];

        foreach ($promotions as $promotion) {
            if (!is_array($promotion)) {
                continue;
            }

            $result = is_array($promotion['calculated_result'] ?? null) ? $promotion['calculated_result'] : [];
            if (empty($result['loyalty_voucher']) || empty($result['voucher_code'])) {
                continue;
            }

            $code = strtoupper(sanitize_text_field($result['voucher_code']));
            if (empty($registry[$code]) || !is_array($registry[$code])) {
                continue;
            }

            $registry[$code]['status'] = 'used';
            $registry[$code]['used_count'] = intval($registry[$code]['used_count'] ?? 0) + 1;
            $registry[$code]['used_at'] = current_time('timestamp');
            $registry[$code]['used_sale_ledger_id'] = intval($context['sale_ledger_id'] ?? 0);
            $registry[$code]['used_sale_code'] = sanitize_text_field($context['sale_code'] ?? '');
            $consumed[] = $registry[$code];
        }

        if (!empty($consumed)) {
            self::save_voucher_registry($registry);
        }

        return $consumed;
    }

    /* ================================================================
     *  VÍ ĐIỂM — gọi TGS_Loyalty_DB trực tiếp (đã gộp wallet)
     * ================================================================ */

    private static function get_user_balance($wp_user_id)
    {
        return TGS_Loyalty_DB::get_balance($wp_user_id);
    }

    private static function get_user_total_earned($wp_user_id)
    {
        return TGS_Loyalty_DB::get_total_earned($wp_user_id);
    }

    private static function wallet_add_balance($wp_user_id, $amount, $description, $args = [])
    {
        TGS_Loyalty_DB::ensure_wallet($wp_user_id);
        return TGS_Loyalty_DB::add_balance($wp_user_id, $amount, $description, $args);
    }

    private static function wallet_deduct_balance($wp_user_id, $amount, $description, $args = [])
    {
        return TGS_Loyalty_DB::deduct_balance($wp_user_id, $amount, $description, $args);
    }

    /* ================================================================
     *  INTERNAL: QUERY & EVALUATE
     * ================================================================ */

    private static function get_active_policies($store_id, $auto_apply_only = true, $types = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . TGS_Loyalty_DB::TABLE_POLICY;
        $now   = time();

        $where = "is_deleted = 0
               AND loyalty_policy_status = 1
               AND (loyalty_policy_start_date = 0 OR loyalty_policy_start_date <= %d)
               AND (loyalty_policy_end_date = 0   OR loyalty_policy_end_date >= %d)";

        if ($auto_apply_only) {
            $where .= ' AND auto_apply = 1';
        }

        $types = array_values(array_filter(array_map('intval', (array) $types)));
        if (!empty($types)) {
            $where .= ' AND loyalty_policy_type IN (' . implode(',', $types) . ')';
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE {$where}
             ORDER BY loyalty_policy_priority ASC, loyalty_policy_id DESC",
            $now,
            $now
        ));

        if (empty($results)) return [];

        $filtered = [];
        foreach ($results as $p) {
            if (self::is_store_in_scope($p, $store_id)) {
                $filtered[] = $p;
            }
        }
        return $filtered;
    }

    private static function is_store_in_scope($policy, $store_id)
    {
        $blog_ids = json_decode($policy->apply_to_blog_ids ?? '[]', true);
        if (empty($blog_ids)) return true;
        return in_array(intval($store_id), array_map('intval', $blog_ids));
    }

    private static function get_wp_user_id_from_customer($customer_id)
    {
        global $wpdb;

        $person_table = $wpdb->prefix . 'local_ledger_person';
        if ($wpdb->get_var("SHOW TABLES LIKE '$person_table'") !== $person_table) {
            return 0;
        }

        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT user_wp_id FROM {$person_table}
             WHERE local_ledger_person_id = %d
               AND (is_deleted = 0 OR is_deleted IS NULL)
             LIMIT 1",
            $customer_id
        )));
    }

    private static function is_tier_at_least($current_tier, $required_tier)
    {
        $tiers = array_keys(TGS_Loyalty_DB::get_tier_definitions());
        $current_index = array_search(sanitize_key($current_tier), $tiers, true);
        $required_index = array_search(sanitize_key($required_tier), $tiers, true);

        if ($required_index === false) {
            return true;
        }

        if ($current_index === false) {
            return false;
        }

        return $current_index >= $required_index;
    }

    private static function resolve_reward_points($reward, $tier_key)
    {
        $base_points = max(0, intval($reward['points_required'] ?? 0));
        $tier_costs = $reward['tier_costs'] ?? [];
        if (is_array($tier_costs)) {
            $tier_key = sanitize_key($tier_key);
            if (isset($tier_costs[$tier_key])) {
                return max(0, intval($tier_costs[$tier_key]));
            }
        }

        return $base_points;
    }

    private static function resolve_reward_tracking_flag($reward)
    {
        if (isset($reward['is_tracking'])) {
            return !empty($reward['is_tracking']) ? 1 : 0;
        }

        return 0;
    }

    private static function get_voucher_registry()
    {
        $registry = get_option(self::OPTION_ISSUED_VOUCHERS, []);
        return is_array($registry) ? $registry : [];
    }

    private static function save_voucher_registry($registry)
    {
        $registry = is_array($registry) ? $registry : [];
        update_option(self::OPTION_ISSUED_VOUCHERS, $registry, false);
    }

    private static function generate_voucher_code($prefix, $registry)
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', (string) $prefix));
        if ($prefix === '') {
            $prefix = 'LP-';
        }

        do {
            $code = $prefix . strtoupper(wp_generate_password(8, false, false));
        } while (isset($registry[$code]));

        return $code;
    }

    /**
     * Đánh giá 1 policy — đọc config từ loyalty_rules JSON.
     */
    private static function evaluate_policy($policy, $order_total, $cart_items, $multiplier)
    {
        $type = intval($policy->loyalty_policy_type);
        $cfg  = TGS_Loyalty_DB::parse_rules($policy);

        // Kiểm tra đơn tối thiểu
        if ($cfg['min_order_amount'] > 0 && $order_total < $cfg['min_order_amount']) {
            return null;
        }

        $points = 0;
        $desc   = '';

        switch ($type) {
            case 1: // earn_per_amount
                $earn_rate = floatval($cfg['earn_rate']);
                $earn_unit = floatval($cfg['earn_unit']);
                if ($earn_unit <= 0) break;
                $points = floor($order_total / $earn_unit) * $earn_rate;
                $desc   = number_format($earn_rate) . ' điểm / ' . number_format($earn_unit) . '₫';
                break;

            case 2: // earn_per_product
                $rules = $cfg['rules'];
                if (!empty($rules) && is_array($rules)) {
                    foreach ($rules as $rule) {
                        $sku = $rule['sku'] ?? '';
                        $pts = floatval($rule['points'] ?? 0);
                        if (!$sku || $pts <= 0) continue;
                        foreach ($cart_items as $item) {
                            $item_sku = $item['sku'] ?? ($item['product_sku'] ?? '');
                            if ($item_sku === $sku) {
                                $points += $pts * intval($item['qty'] ?? 1);
                            }
                        }
                    }
                }
                $desc = 'Điểm theo sản phẩm';
                break;

            case 3: // multiplier
                $bonus_multiplier = floatval($cfg['rules']['multiplier'] ?? 2);
                $earn_rate = floatval($cfg['earn_rate']);
                $earn_unit = floatval($cfg['earn_unit']);
                if ($earn_unit <= 0) break;
                $base_points = floor($order_total / $earn_unit) * $earn_rate;
                $points = $base_points * ($bonus_multiplier - 1);
                $desc   = 'Nhân x' . $bonus_multiplier . ' điểm';
                break;

            case 4: // bonus_event
                $rules = $cfg['rules'];
                if (!empty($rules) && is_array($rules)) {
                    foreach ($rules as $rule) {
                        $min_amount = floatval($rule['min_amount'] ?? 0);
                        $bonus_pts  = floatval($rule['bonus_points'] ?? 0);
                        if ($order_total >= $min_amount && $bonus_pts > 0) {
                            $points = $bonus_pts;
                            $desc   = 'Bonus ' . number_format($bonus_pts) . ' điểm khi đạt ' . number_format($min_amount) . '₫';
                        }
                    }
                }
                break;
        }

        // Áp dụng tier multiplier
        if ($points > 0 && $multiplier > 1.0 && $type !== 3) {
            $points = round($points * $multiplier, 2);
        }

        // Cap max points
        $max = intval($cfg['max_points_per_order']);
        if ($max > 0 && $points > $max) {
            $points = $max;
        }

        return $points > 0 ? ['points' => $points, 'description' => $desc] : null;
    }
}

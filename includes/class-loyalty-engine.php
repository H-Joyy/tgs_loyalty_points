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

// =========================================================================
// ENGINE CLASS
// =========================================================================

class TGS_Loyalty_Engine
{
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

    private static function get_active_policies($store_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . TGS_Loyalty_DB::TABLE_POLICY;
        $now   = time();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE is_deleted = 0
               AND loyalty_policy_status = 1
               AND auto_apply = 1
               AND (loyalty_policy_start_date = 0 OR loyalty_policy_start_date <= %d)
               AND (loyalty_policy_end_date = 0   OR loyalty_policy_end_date >= %d)
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

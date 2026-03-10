<?php

if (!defined('ABSPATH')) exit;

/**
 * TGS_Loyalty_Ajax — AJAX handlers cho CRUD chính sách & thành viên.
 *
 * Thay đổi chính so với bản cũ:
 *   - policy_save(): 7 cột cũ gom vào loyalty_rules JSON
 *   - member_*(): dùng wp_user_id thay customer_id, delegate wallet API
 */
class TGS_Loyalty_Ajax
{
    public static function init()
    {
        // ── Policy CRUD ──
        add_action('wp_ajax_tgs_loyalty_policy_list',   [__CLASS__, 'policy_list']);
        add_action('wp_ajax_tgs_loyalty_policy_get',    [__CLASS__, 'policy_get']);
        add_action('wp_ajax_tgs_loyalty_policy_save',   [__CLASS__, 'policy_save']);
        add_action('wp_ajax_tgs_loyalty_policy_delete', [__CLASS__, 'policy_delete']);
        add_action('wp_ajax_tgs_loyalty_policy_clone',  [__CLASS__, 'policy_clone']);
        add_action('wp_ajax_tgs_loyalty_policy_stats',  [__CLASS__, 'policy_stats']);

        // ── Member ──
        add_action('wp_ajax_tgs_loyalty_member_list',   [__CLASS__, 'member_list']);
        add_action('wp_ajax_tgs_loyalty_member_adjust', [__CLASS__, 'member_adjust']);
        add_action('wp_ajax_tgs_loyalty_member_logs',   [__CLASS__, 'member_logs']);
        add_action('wp_ajax_tgs_loyalty_member_stats',  [__CLASS__, 'member_stats']);

        // ── Scope data ──
        add_action('wp_ajax_tgs_loyalty_get_scope_data', [__CLASS__, 'get_scope_data']);
    }

    /* ================================================================
     *  POLICY
     * ================================================================ */

    public static function policy_list()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $result = TGS_Loyalty_DB::get_policies([
            'page'     => max(1, intval($_POST['page'] ?? 1)),
            'per_page' => intval($_POST['per_page'] ?? 20),
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'status'   => $_POST['status'] ?? '',
            'type'     => $_POST['type'] ?? '',
        ]);

        wp_send_json_success($result);
    }

    public static function policy_get()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Thiếu ID.']);

        $policy = TGS_Loyalty_DB::get_policy($id);
        if (!$policy) wp_send_json_error(['message' => 'Không tìm thấy chính sách.']);

        // Giải nén loyalty_rules để JS dùng trực tiếp
        $policy->parsed_rules = TGS_Loyalty_DB::parse_rules($policy);

        wp_send_json_success($policy);
    }

    /**
     * Lưu policy — tất cả config (earn_rate, earn_unit, redeem_rate, …) gom vào loyalty_rules JSON.
     */
    public static function policy_save()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);

        // JS gửi loyalty_rules chứa type-specific rules (SKU array, multiplier, bonus)
        // Luôn pack đầy đủ config vào loyalty_rules JSON
        $type_rules = json_decode(wp_unslash($_POST['loyalty_rules'] ?? '[]'), true) ?: [];
        $loyalty_rules = wp_json_encode([
            'earn_rate'            => floatval($_POST['earn_rate'] ?? 1),
            'earn_unit'            => floatval($_POST['earn_unit'] ?? 1000),
            'redeem_rate'          => floatval($_POST['redeem_rate'] ?? 1),
            'min_order_amount'     => floatval($_POST['min_order_amount'] ?? 0),
            'max_points_per_order' => intval($_POST['max_points_per_order'] ?? 0),
            'point_expiry_days'    => intval($_POST['point_expiry_days'] ?? 0),
            'rules'                => $type_rules,
        ]);

        $data = [
            'loyalty_policy_code'       => sanitize_text_field($_POST['loyalty_policy_code'] ?? ''),
            'loyalty_policy_title'      => sanitize_text_field($_POST['loyalty_policy_title'] ?? ''),
            'loyalty_policy_type'       => intval($_POST['loyalty_policy_type'] ?? 1),
            'loyalty_rules'             => $loyalty_rules,
            'apply_to_blog_ids'         => wp_unslash($_POST['apply_to_blog_ids'] ?? '[]'),
            'apply_to_org_level'        => wp_unslash($_POST['apply_to_org_level'] ?? '{}'),
            'auto_apply'                => intval($_POST['auto_apply'] ?? 1),
            'loyalty_policy_priority'   => intval($_POST['loyalty_policy_priority'] ?? 0),
            'loyalty_policy_start_date' => intval($_POST['loyalty_policy_start_date'] ?? 0),
            'loyalty_policy_end_date'   => intval($_POST['loyalty_policy_end_date'] ?? 0),
            'loyalty_policy_status'     => intval($_POST['loyalty_policy_status'] ?? 0),
            'user_id'                   => get_current_user_id(),
            'source_blog_id'            => get_current_blog_id(),
        ];

        if (empty($data['loyalty_policy_code'])) {
            wp_send_json_error(['message' => 'Mã chính sách không được trống.']);
        }
        if (empty($data['loyalty_policy_title'])) {
            wp_send_json_error(['message' => 'Tên chính sách không được trống.']);
        }

        $saved_id = TGS_Loyalty_DB::save_policy($data, $id);

        if (!$saved_id) {
            wp_send_json_error(['message' => 'Lỗi lưu chính sách.']);
        }

        wp_send_json_success([
            'message'           => $id ? 'Đã cập nhật chính sách.' : 'Đã tạo chính sách mới.',
            'loyalty_policy_id' => $saved_id,
        ]);
    }

    public static function policy_delete()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Thiếu ID.']);

        TGS_Loyalty_DB::delete_policy($id);
        wp_send_json_success(['message' => 'Đã xóa chính sách.']);
    }

    public static function policy_clone()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Thiếu ID.']);

        $new_id = TGS_Loyalty_DB::clone_policy($id);
        if (!$new_id) wp_send_json_error(['message' => 'Không thể sao chép.']);

        wp_send_json_success([
            'message'           => 'Đã sao chép chính sách.',
            'loyalty_policy_id' => $new_id,
        ]);
    }

    public static function policy_stats()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');
        wp_send_json_success(TGS_Loyalty_DB::get_policy_stats());
    }

    /* ================================================================
     *  MEMBER — dùng wp_user_id, delegate wallet API
     * ================================================================ */

    public static function member_list()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $result = TGS_Loyalty_DB::get_members([
            'page'     => max(1, intval($_POST['page'] ?? 1)),
            'per_page' => intval($_POST['per_page'] ?? 20),
            'search'   => sanitize_text_field($_POST['search'] ?? ''),
            'tier'     => sanitize_text_field($_POST['tier'] ?? ''),
        ]);

        foreach ($result['items'] as &$m) {
            $m->tier_badge = TGS_Loyalty_DB::tier_badge($m->member_tier);
        }

        wp_send_json_success($result);
    }

    /**
     * Cộng / trừ điểm thủ công — delegate sang Engine (wallet bridge).
     */
    public static function member_adjust()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $wp_user_id  = intval($_POST['wp_user_id'] ?? 0);
        $points      = floatval($_POST['points'] ?? 0);
        $action      = sanitize_text_field($_POST['adjust_action'] ?? 'add');
        $description = sanitize_text_field($_POST['description'] ?? '');

        if (!$wp_user_id || $points <= 0) {
            wp_send_json_error(['message' => 'Thông tin không hợp lệ.']);
        }

        if ($action === 'deduct') {
            $result = tgs_loyalty_redeem_points($wp_user_id, $points, [
                'description'    => $description ?: 'Trừ điểm thủ công',
                'reference_type' => 'manual',
            ]);
        } else {
            // Cộng điểm thủ công — dùng trực tiếp wallet + log (không qua policy)
            $info = tgs_loyalty_get_member_info($wp_user_id);
            $balance_before = $info ? $info['current_points'] : 0;

            // Wallet bridge
            if (function_exists('user_wallet_add_balance')) {
                $wallet_result = user_wallet_add_balance($wp_user_id, $points, $description ?: 'Cộng điểm thủ công', [
                    'reference_type' => 'loyalty_manual',
                ]);
                if (is_wp_error($wallet_result)) {
                    wp_send_json_error(['message' => $wallet_result->get_error_message()]);
                }
            } else {
                $bal = floatval(get_user_meta($wp_user_id, 'loyalty_balance', true));
                update_user_meta($wp_user_id, 'loyalty_balance', $bal + $points);
            }

            $info_after = tgs_loyalty_get_member_info($wp_user_id);
            $balance_after = $info_after ? $info_after['current_points'] : $balance_before + $points;

            // Cập nhật tier
            $total_earned = $info_after ? $info_after['total_earned'] : $balance_after;
            $new_tier = TGS_Loyalty_DB::determine_tier($total_earned);
            update_user_meta($wp_user_id, 'loyalty_tier', $new_tier);

            TGS_Loyalty_DB::add_log([
                'wp_user_id'     => $wp_user_id,
                'log_type'       => TGS_Loyalty_DB::LOG_ADJUST,
                'points'         => $points,
                'points_before'  => $balance_before,
                'points_after'   => $balance_after,
                'description'    => $description ?: 'Cộng điểm thủ công',
                'reference_type' => 'manual',
                'created_by'     => get_current_user_id(),
                'blog_id'        => get_current_blog_id(),
            ]);

            $result = [
                'success'       => true,
                'points_before' => $balance_before,
                'points_after'  => $balance_after,
            ];
        }

        if (empty($result['success'])) {
            wp_send_json_error(['message' => $result['message'] ?? 'Lỗi xử lý.']);
        }

        wp_send_json_success([
            'message'       => $action === 'deduct' ? 'Đã trừ điểm.' : 'Đã cộng điểm.',
            'points_before' => $result['points_before'],
            'points_after'  => $result['points_after'],
        ]);
    }

    public static function member_logs()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $result = TGS_Loyalty_DB::get_logs([
            'wp_user_id' => intval($_POST['wp_user_id'] ?? 0),
            'log_type'   => sanitize_text_field($_POST['log_type'] ?? ''),
            'page'       => max(1, intval($_POST['page'] ?? 1)),
            'per_page'   => intval($_POST['per_page'] ?? 20),
        ]);

        wp_send_json_success($result);
    }

    public static function member_stats()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');
        wp_send_json_success(TGS_Loyalty_DB::get_member_stats());
    }

    /* ================================================================
     *  SCOPE DATA
     * ================================================================ */

    public static function get_scope_data()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        if (class_exists('TGS_Selling_Policy_Ajax') && method_exists('TGS_Selling_Policy_Ajax', 'get_scope_data')) {
            $_POST['nonce'] = wp_create_nonce('tgs_selling_nonce');
            TGS_Selling_Policy_Ajax::get_scope_data();
            return;
        }

        if (is_multisite()) {
            $sites = get_sites(['number' => 200]);
            $websites = [];
            foreach ($sites as $s) {
                $details = get_blog_details($s->blog_id);
                $websites[] = [
                    'blog_id' => intval($s->blog_id),
                    'name'    => $details->blogname ?? 'Site #' . $s->blog_id,
                    'domain'  => $s->domain . $s->path,
                ];
            }
            wp_send_json_success(['websites' => $websites, 'tree' => null]);
        } else {
            wp_send_json_success(['websites' => [], 'tree' => null]);
        }
    }
}

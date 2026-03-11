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

        // ── Settings ──
        add_action('wp_ajax_tgs_loyalty_settings_get',  [__CLASS__, 'settings_get']);
        add_action('wp_ajax_tgs_loyalty_settings_save', [__CLASS__, 'settings_save']);

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
        if (!$policy) wp_send_json_error(['message' => 'Không tìm thấy chương trình.']);

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
            wp_send_json_error(['message' => 'Mã chương trình không được trống.']);
        }
        if (empty($data['loyalty_policy_title'])) {
            wp_send_json_error(['message' => 'Tên chương trình không được trống.']);
        }

        $saved_id = TGS_Loyalty_DB::save_policy($data, $id);

        if (!$saved_id) {
            global $wpdb;
            $db_error = $wpdb->last_error;
            wp_send_json_error(['message' => 'Không thể lưu.' . ($db_error ? ' DB: ' . $db_error : '')]);
        }

        wp_send_json_success([
            'message'           => $id ? 'Đã cập nhật.' : 'Đã tạo chương trình mới.',
            'loyalty_policy_id' => $saved_id,
        ]);
    }

    public static function policy_delete()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Thiếu ID.']);

        TGS_Loyalty_DB::delete_policy($id);
        wp_send_json_success(['message' => 'Đã xóa.']);
    }

    public static function policy_clone()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $id = intval($_POST['loyalty_policy_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Thiếu ID.']);

        $new_id = TGS_Loyalty_DB::clone_policy($id);
        if (!$new_id) wp_send_json_error(['message' => 'Không thể sao chép.']);

        wp_send_json_success([
            'message'           => 'Đã sao chép.',
            'loyalty_policy_id' => $new_id,
        ]);
    }

    public static function policy_stats()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');
        wp_send_json_success(TGS_Loyalty_DB::get_policy_stats());
    }

    public static function settings_get()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');
        wp_send_json_success(TGS_Loyalty_DB::get_settings());
    }

    public static function settings_save()
    {
        check_ajax_referer('tgs_loyalty_nonce', 'nonce');

        $tiers = json_decode(wp_unslash($_POST['tiers'] ?? '[]'), true);
        if (!is_array($tiers)) {
            $tiers = [];
        }

        $result = TGS_Loyalty_DB::save_settings([
            'enable_pos_earn'     => intval($_POST['enable_pos_earn'] ?? 0),
            'enable_pos_redeem'   => intval($_POST['enable_pos_redeem'] ?? 0),
            'allow_manual_adjust' => intval($_POST['allow_manual_adjust'] ?? 0),
            'point_label'         => sanitize_text_field($_POST['point_label'] ?? 'điểm'),
            'min_redeem_points'   => floatval($_POST['min_redeem_points'] ?? 0),
            'tiers'               => $tiers,
        ]);

        wp_send_json_success([
            'message'  => 'Đã lưu cài đặt loyalty.',
            'settings' => $result['settings'],
        ]);
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

        $settings = TGS_Loyalty_DB::get_settings();
        if (empty($settings['allow_manual_adjust'])) {
            wp_send_json_error(['message' => 'Chức năng điều chỉnh thủ công đang bị tắt trong cài đặt loyalty.']);
        }

        if ($action === 'deduct') {
            $result = tgs_loyalty_redeem_points($wp_user_id, $points, [
                'description'    => $description ?: 'Trừ điểm thủ công',
                'reference_type' => 'manual',
            ]);
        } else {
            // Cộng điểm thủ công — dùng trực tiếp TGS_Loyalty_DB
            $info = tgs_loyalty_get_member_info($wp_user_id);
            $balance_before = $info ? $info['current_points'] : 0;

            TGS_Loyalty_DB::ensure_wallet($wp_user_id);
            $wallet_result = TGS_Loyalty_DB::add_balance($wp_user_id, $points, $description ?: 'Cộng điểm bằng tay', [
                'reference_type' => 'loyalty_manual',
            ]);
            if (is_wp_error($wallet_result)) {
                wp_send_json_error(['message' => $wallet_result->get_error_message()]);
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
                'description'    => $description ?: 'Cộng điểm bằng tay',
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

        if (!class_exists('TGS_Org_Chart_Data')) {
            wp_send_json_error('Plugin TGS Multisite Hierarchy chưa được kích hoạt.');
            return;
        }

        $org_data = TGS_Org_Chart_Data::get_all_data();
        $tree = self::build_scope_tree($org_data);

        $websites = [];
        if (is_multisite()) {
            $sites = get_sites([
                'number'  => 9999,
                'orderby' => 'blog_id',
                'order'   => 'ASC',
            ]);

            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                $websites[(int) $site->blog_id] = [
                    'blog_id' => (int) $site->blog_id,
                    'name'    => get_bloginfo('name'),
                    'domain'  => $site->domain,
                    'path'    => $site->path,
                ];
                restore_current_blog();
            }
        }

        wp_send_json_success([
            'tree'     => $tree,
            'websites' => $websites,
        ]);
    }

    private static function build_scope_tree($data)
    {
        $node_shops = isset($data['node_shops']) ? $data['node_shops'] : [];
        if (isset($data['province_shops'])) {
            foreach ($data['province_shops'] as $province_id => $shop_ids) {
                if (!isset($node_shops[$province_id])) {
                    $node_shops[$province_id] = $shop_ids;
                }
            }
        }

        $company = isset($data['company']) ? $data['company'] : null;
        if (!$company) {
            return null;
        }

        $company_id = $company['id'];
        $tree = [
            'id'       => $company_id,
            'name'     => $company['name'],
            'code'     => $company['code'] ?? '',
            'level'    => 1,
            'shop_ids' => isset($node_shops[$company_id]) ? array_map('intval', $node_shops[$company_id]) : [],
            'children' => [],
        ];

        $regions = $data['regions'] ?? [];
        $branches = $data['branches'] ?? [];
        $provinces = $data['provinces'] ?? [];

        uasort($regions, fn($left, $right) => ($left['order'] ?? 0) - ($right['order'] ?? 0));

        foreach ($regions as $region_id => $region) {
            $region_node = [
                'id'       => $region_id,
                'name'     => $region['name'],
                'code'     => $region['code'] ?? '',
                'level'    => 2,
                'shop_ids' => isset($node_shops[$region_id]) ? array_map('intval', $node_shops[$region_id]) : [],
                'children' => [],
            ];

            $region_branches = array_filter($branches, fn($branch) => $branch['region_id'] === $region_id);
            uasort($region_branches, fn($left, $right) => ($left['order'] ?? 0) - ($right['order'] ?? 0));

            foreach ($region_branches as $branch_id => $branch) {
                $branch_node = [
                    'id'       => $branch_id,
                    'name'     => $branch['name'],
                    'code'     => $branch['code'] ?? '',
                    'level'    => 3,
                    'shop_ids' => isset($node_shops[$branch_id]) ? array_map('intval', $node_shops[$branch_id]) : [],
                    'children' => [],
                ];

                $branch_provinces = array_filter($provinces, fn($province) => $province['branch_id'] === $branch_id);
                uasort($branch_provinces, fn($left, $right) => ($left['order'] ?? 0) - ($right['order'] ?? 0));

                foreach ($branch_provinces as $province_id => $province) {
                    $branch_node['children'][] = [
                        'id'       => $province_id,
                        'name'     => $province['name'],
                        'code'     => $province['code'] ?? '',
                        'level'    => 4,
                        'shop_ids' => isset($node_shops[$province_id]) ? array_map('intval', $node_shops[$province_id]) : [],
                        'children' => [],
                    ];
                }

                $region_node['children'][] = $branch_node;
            }

            $tree['children'][] = $region_node;
        }

        return $tree;
    }
}

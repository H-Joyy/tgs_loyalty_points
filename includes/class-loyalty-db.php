<?php

if (!defined('ABSPATH')) exit;

/**
 * TGS_Loyalty_DB — Quản lý DB cho hệ thống tích điểm & ví điểm (gộp wallet).
 *
 * Bảng:
 *   - global_loyalty_policy : Chương trình tích điểm
 *   - global_loyalty_log    : Lịch sử điểm
 *   - wallet                : Ví điểm khách hàng (global)
 *   - wallet_log            : Lịch sử giao dịch ví (global)
 */
class TGS_Loyalty_DB
{
    const OPTION_SETTINGS = 'tgs_loyalty_settings';
    const OPTION_TIERS    = 'tgs_loyalty_tiers';

    const STATUS_DRAFT   = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_EXPIRED = 2;

    const LOG_EARN   = 'earn';
    const LOG_REDEEM = 'redeem';
    const LOG_ADJUST = 'adjust';
    const LOG_EXPIRE = 'expire';
    const LOG_REFUND = 'refund';

    const TABLE_POLICY = 'global_loyalty_policy';
    const TABLE_LOG    = 'global_loyalty_log';

    /* ================================================================
     *  TẠO BẢNG — Đã chuyển sang TGS_Shop_Database::create_global_tables()
     *  Plugin tgs_shop_management tạo tất cả bảng khi kích hoạt.
     *  Giữ method stub để tương thích ngược (nếu có nơi nào gọi).
     * ================================================================ */

    public static function create_tables()
    {
        // Bảng được tạo bởi tgs_shop_management — không làm gì ở đây.
    }

    /* ================================================================
     *  VÍ ĐIỂM
     * ================================================================ */

    public static function ensure_wallet($user_id)
    {
        if (self::wallet_exists($user_id)) return true;
        return self::create_wallet($user_id);
    }

    public static function wallet_exists($user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'wallet';
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE user_id = %d", $user_id));
    }

    public static function create_wallet($user_id, $args = [])
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'wallet';

        if (!get_user_by('id', $user_id)) {
            return new \WP_Error('invalid_user', 'Người dùng không tồn tại.');
        }
        if (self::wallet_exists($user_id)) {
            return new \WP_Error('wallet_exists', 'Ví đã tồn tại.');
        }

        $wallet_key = 'WLT-' . strtoupper(substr(md5(uniqid($user_id, true)), 0, 8));
        $now = current_time('mysql');

        $wpdb->insert($table, [
            'user_id'           => $user_id,
            'balance'           => floatval($args['balance'] ?? 0),
            'wallet_key'        => $wallet_key,
            'status'            => 'active',
            'wallet_type'       => 'standard',
            'total_earned'      => 0,
            'total_spent'       => 0,
            'transaction_count' => 0,
            'wallet_meta'       => wp_json_encode($args['wallet_meta'] ?? []),
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $wallet_id = $wpdb->insert_id;
        if (!$wallet_id) return new \WP_Error('create_failed', 'Không thể tạo ví.');

        do_action('user_wallet_created', $wallet_id, $user_id);
        return $wallet_id;
    }

    public static function get_wallet_by_user($user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'wallet';
        $wallet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d", $user_id));
        if ($wallet && !empty($wallet->wallet_meta)) {
            $wallet->wallet_meta = json_decode($wallet->wallet_meta, true);
        }
        return $wallet;
    }

    public static function get_balance($user_id)
    {
        $wallet = self::get_wallet_by_user($user_id);
        return $wallet ? floatval($wallet->balance) : 0;
    }

    public static function get_total_earned($user_id)
    {
        $wallet = self::get_wallet_by_user($user_id);
        return $wallet ? floatval($wallet->total_earned) : 0;
    }

    public static function add_balance($user_id, $amount, $description = '', $args = [])
    {
        global $wpdb;
        $amount = floatval($amount);
        if ($amount <= 0) return new \WP_Error('invalid_amount', 'Số điểm phải lớn hơn 0.');

        $table  = $wpdb->base_prefix . 'wallet';
        $wallet = self::get_wallet_by_user($user_id);
        if (!$wallet) return new \WP_Error('wallet_not_found', 'Không tìm thấy ví.');
        if ($wallet->status !== 'active') return new \WP_Error('wallet_inactive', 'Ví không hoạt động.');

        $wpdb->query('START TRANSACTION');
        try {
            $before = floatval($wallet->balance);
            $after  = $before + $amount;

            $ok = $wpdb->update($table, [
                'balance'             => $after,
                'total_earned'        => floatval($wallet->total_earned) + $amount,
                'transaction_count'   => intval($wallet->transaction_count) + 1,
                'last_transaction_at' => current_time('mysql'),
                'updated_at'          => current_time('mysql'),
            ], ['id' => $wallet->id]);

            if ($ok === false) throw new \Exception('Không thể cập nhật ví.');

            $log_id = self::add_wallet_log([
                'wallet_id'        => $wallet->id,
                'user_id'          => $user_id,
                'transaction_type' => 'credit',
                'amount'           => $amount,
                'reference_id'     => $args['reference_id'] ?? null,
                'reference_type'   => $args['reference_type'] ?? null,
                'wallet_log_meta'  => ['description' => $description, 'balance_before' => $before, 'balance_after' => $after],
            ]);

            if (!$log_id) throw new \Exception('Không thể ghi lịch sử.');

            $wpdb->query('COMMIT');
            do_action('user_wallet_balance_added', $user_id, $amount, $after, $log_id);

            return ['success' => true, 'balance_before' => $before, 'balance_after' => $after, 'amount' => $amount, 'log_id' => $log_id];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('transaction_failed', $e->getMessage());
        }
    }

    public static function deduct_balance($user_id, $amount, $description = '', $args = [])
    {
        global $wpdb;
        $amount = floatval($amount);
        if ($amount <= 0) return new \WP_Error('invalid_amount', 'Số điểm phải lớn hơn 0.');

        $table  = $wpdb->base_prefix . 'wallet';
        $wallet = self::get_wallet_by_user($user_id);
        if (!$wallet) return new \WP_Error('wallet_not_found', 'Không tìm thấy ví.');
        if ($wallet->status !== 'active') return new \WP_Error('wallet_inactive', 'Ví không hoạt động.');
        if ($wallet->balance < $amount) return new \WP_Error('insufficient_balance', 'Số điểm không đủ.');

        $wpdb->query('START TRANSACTION');
        try {
            $before = floatval($wallet->balance);
            $after  = $before - $amount;

            $ok = $wpdb->update($table, [
                'balance'             => $after,
                'total_spent'         => floatval($wallet->total_spent) + $amount,
                'transaction_count'   => intval($wallet->transaction_count) + 1,
                'last_transaction_at' => current_time('mysql'),
                'updated_at'          => current_time('mysql'),
            ], ['id' => $wallet->id]);

            if ($ok === false) throw new \Exception('Không thể cập nhật ví.');

            $log_id = self::add_wallet_log([
                'wallet_id'        => $wallet->id,
                'user_id'          => $user_id,
                'transaction_type' => 'debit',
                'amount'           => $amount,
                'reference_id'     => $args['reference_id'] ?? null,
                'reference_type'   => $args['reference_type'] ?? null,
                'wallet_log_meta'  => ['description' => $description, 'balance_before' => $before, 'balance_after' => $after],
            ]);

            if (!$log_id) throw new \Exception('Không thể ghi lịch sử.');

            $wpdb->query('COMMIT');
            do_action('user_wallet_balance_deducted', $user_id, $amount, $after, $log_id);

            return ['success' => true, 'balance_before' => $before, 'balance_after' => $after, 'amount' => $amount, 'log_id' => $log_id];
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return new \WP_Error('transaction_failed', $e->getMessage());
        }
    }

    public static function add_wallet_log($data)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'wallet_log';
        $wpdb->insert($table, [
            'wallet_id'        => intval($data['wallet_id']),
            'user_id'          => intval($data['user_id']),
            'transaction_type' => sanitize_text_field($data['transaction_type']),
            'amount'           => floatval($data['amount']),
            'wallet_log_meta'  => isset($data['wallet_log_meta']) ? wp_json_encode($data['wallet_log_meta']) : null,
            'reference_id'     => isset($data['reference_id']) ? sanitize_text_field($data['reference_id']) : null,
            'reference_type'   => isset($data['reference_type']) ? sanitize_text_field($data['reference_type']) : null,
            'ip_address'       => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'created_by'       => $data['created_by'] ?? get_current_user_id(),
            'created_at'       => current_time('mysql'),
        ]);
        return $wpdb->insert_id ?: false;
    }

    /* ================================================================
     *  CHƯƠNG TRÌNH TÍCH ĐIỂM — CRUD
     * ================================================================ */

    public static function get_policies($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;

        $defaults = [
            'page' => 1,
            'per_page' => 20,
            'search' => '',
            'status' => '',
            'type' => '',
            'orderby' => 'loyalty_policy_priority ASC, loyalty_policy_id DESC'
        ];
        $args = wp_parse_args($args, $defaults);

        $where = 'is_deleted = 0';
        if ($args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(" AND (loyalty_policy_code LIKE %s OR loyalty_policy_title LIKE %s)", $like, $like);
        }
        if ($args['status'] !== '') {
            $where .= $wpdb->prepare(" AND loyalty_policy_status = %d", intval($args['status']));
        }
        if ($args['type'] !== '') {
            $where .= $wpdb->prepare(" AND loyalty_policy_type = %d", intval($args['type']));
        }

        $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $offset = ($args['page'] - 1) * $args['per_page'];
        $items  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$args['orderby']} LIMIT %d OFFSET %d",
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items' => $items ?: [],
            'total' => intval($total),
            'page' => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1
        ];
    }

    public static function get_policy($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . self::TABLE_POLICY . " WHERE loyalty_policy_id = %d AND is_deleted = 0",
            $id
        ));
    }

    public static function save_policy($data, $id = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;

        if ($id > 0) {
            $data['updated_at'] = current_time('mysql');
            $wpdb->update($table, $data, ['loyalty_policy_id' => $id]);
            return $id;
        }
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function delete_policy($id)
    {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . self::TABLE_POLICY,
            ['is_deleted' => 1, 'updated_at' => current_time('mysql')],
            ['loyalty_policy_id' => $id]
        );
    }

    public static function clone_policy($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;
        $original = self::get_policy($id);
        if (!$original) return false;

        $new = (array) $original;
        unset($new['loyalty_policy_id']);
        $new['loyalty_policy_code']   = $original->loyalty_policy_code . '-COPY-' . time();
        $new['loyalty_policy_title']  = $original->loyalty_policy_title . ' (bản sao)';
        $new['loyalty_policy_status'] = self::STATUS_DRAFT;
        $new['created_at'] = current_time('mysql');
        $new['updated_at'] = current_time('mysql');
        $wpdb->insert($table, $new);
        return $wpdb->insert_id;
    }

    public static function get_policy_stats()
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;
        $now   = time();

        return [
            'total'   => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0")),
            'active'  => intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0 AND loyalty_policy_status = 1
                 AND (loyalty_policy_start_date = 0 OR loyalty_policy_start_date <= %d)
                 AND (loyalty_policy_end_date = 0 OR loyalty_policy_end_date >= %d)",
                $now,
                $now
            ))),
            'draft'   => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0 AND loyalty_policy_status = 0")),
            'by_type' => $wpdb->get_results(
                "SELECT loyalty_policy_type AS type, COUNT(*) AS cnt FROM {$table} WHERE is_deleted = 0 GROUP BY loyalty_policy_type ORDER BY loyalty_policy_type"
            ),
        ];
    }

    public static function parse_rules($policy)
    {
        $defaults = [
            'earn_rate' => 1,
            'earn_unit' => 1000,
            'redeem_rate' => 1,
            'min_order_amount' => 0,
            'max_points_per_order' => 0,
            'point_expiry_days' => 0,
            'product_skus' => [],
            'rules' => []
        ];
        $rules = json_decode($policy->loyalty_rules ?? '{}', true);
        return wp_parse_args(is_array($rules) ? $rules : [], $defaults);
    }

    /* ================================================================
     *  LỊCH SỬ ĐIỂM
     * ================================================================ */

    public static function add_log($data)
    {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($wpdb->prefix . self::TABLE_LOG, $data);
        return $wpdb->insert_id;
    }

    public static function get_logs($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOG;
        $defaults = ['page' => 1, 'per_page' => 20, 'wp_user_id' => 0, 'log_type' => '', 'orderby' => 'created_at DESC'];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        if ($args['wp_user_id']) $where .= $wpdb->prepare(" AND wp_user_id = %d", intval($args['wp_user_id']));
        if ($args['log_type'])   $where .= $wpdb->prepare(" AND log_type = %s", sanitize_text_field($args['log_type']));

        $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $offset = ($args['page'] - 1) * $args['per_page'];
        $items  = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$args['orderby']} LIMIT %d OFFSET %d",
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items' => $items ?: [],
            'total' => intval($total),
            'page' => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1
        ];
    }

    /* ================================================================
     *  THỐNG KÊ KHÁCH HÀNG
     * ================================================================ */

    public static function get_member_stats()
    {
        global $wpdb;
        $wt = $wpdb->base_prefix . 'wallet';
        $dk = self::get_default_tier_key();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wt}'") !== $wt) {
            return ['total' => 0, 'by_tier' => [], 'total_points' => 0];
        }

        return [
            'total'        => intval($wpdb->get_var("SELECT COUNT(*) FROM {$wt} WHERE status = 'active'")),
            'by_tier'      => $wpdb->get_results(
                "SELECT COALESCE(um.meta_value, '{$dk}') AS tier, COUNT(*) AS cnt
                 FROM {$wt} w LEFT JOIN {$wpdb->usermeta} um ON um.user_id = w.user_id AND um.meta_key = 'loyalty_tier'
                 WHERE w.status = 'active' GROUP BY tier ORDER BY cnt DESC"
            ) ?: [],
            'total_points' => floatval($wpdb->get_var("SELECT COALESCE(SUM(balance), 0) FROM {$wt} WHERE status = 'active'")),
        ];
    }

    public static function get_members($args = [])
    {
        global $wpdb;
        $wt = $wpdb->base_prefix . 'wallet';
        $dk = self::get_default_tier_key();

        $defaults = ['page' => 1, 'per_page' => 20, 'search' => '', 'tier' => ''];
        $args = wp_parse_args($args, $defaults);

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wt}'") !== $wt) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }

        $where = "w.status = 'active'";
        if ($args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(
                " AND (u.display_name LIKE %s OR u.user_email LIKE %s OR COALESCE(um_phone.meta_value,'') LIKE %s)",
                $like,
                $like,
                $like
            );
        }
        if ($args['tier']) {
            $where .= $wpdb->prepare(" AND COALESCE(um_tier.meta_value, %s) = %s", $dk, sanitize_text_field($args['tier']));
        }

        $join = "JOIN {$wpdb->users} u ON u.ID = w.user_id
                 LEFT JOIN {$wpdb->usermeta} um_tier ON um_tier.user_id = w.user_id AND um_tier.meta_key = 'loyalty_tier'
                 LEFT JOIN {$wpdb->usermeta} um_phone ON um_phone.user_id = w.user_id AND um_phone.meta_key = 'billing_phone'";

        $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$wt} w {$join} WHERE {$where}");
        $offset = ($args['page'] - 1) * $args['per_page'];

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT w.id AS wallet_id, w.user_id AS wp_user_id,
                    u.display_name, u.user_email,
                    w.balance AS current_points,
                    w.total_earned, w.total_spent AS total_redeemed,
                    COALESCE(um_tier.meta_value, %s) AS member_tier,
                    COALESCE(um_phone.meta_value, '') AS customer_phone
             FROM {$wt} w {$join}
             WHERE {$where} ORDER BY w.balance DESC LIMIT %d OFFSET %d",
            $dk,
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items' => $items ?: [],
            'total' => intval($total),
            'page' => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1
        ];
    }

    /* ================================================================
     *  HẠNG KHÁCH HÀNG — Bảng màu tối giản
     *  Xám xanh (trung tính) → Xanh dương → Vàng ấm → Tím primary
     * ================================================================ */

    public static function get_tier_definitions()
    {
        $defaults = [
            'member' => ['name' => 'Thành viên', 'min_points' => 0,     'color' => '#90A4AE', 'multiplier' => 1.0],
            'silver' => ['name' => 'Bạc',        'min_points' => 1000,  'color' => '#5C6BC0', 'multiplier' => 1.2],
            'gold'   => ['name' => 'Vàng',       'min_points' => 5000,  'color' => '#F9A825', 'multiplier' => 1.5],
            'vip'    => ['name' => 'VIP',         'min_points' => 20000, 'color' => '#696cff', 'multiplier' => 2.0],
        ];
        return self::normalize_tiers(get_option(self::OPTION_TIERS, $defaults));
    }

    public static function get_settings_defaults()
    {
        return [
            'enable_pos_earn'     => 1,
            'enable_pos_redeem'   => 1,
            'allow_manual_adjust' => 1,
            'enable_tier_multiplier' => 0,
            'point_label'         => 'điểm',
            'min_redeem_points'   => 0,
        ];
    }

    public static function get_settings()
    {
        $s = get_option(self::OPTION_SETTINGS, []);
        if (!is_array($s)) $s = [];
        $s = wp_parse_args($s, self::get_settings_defaults());
        $s['tiers'] = self::get_tier_definitions();
        return $s;
    }

    public static function save_settings($data)
    {
        $settings = [
            'enable_pos_earn'     => !empty($data['enable_pos_earn']) ? 1 : 0,
            'enable_pos_redeem'   => !empty($data['enable_pos_redeem']) ? 1 : 0,
            'allow_manual_adjust' => !empty($data['allow_manual_adjust']) ? 1 : 0,
            'enable_tier_multiplier' => !empty($data['enable_tier_multiplier']) ? 1 : 0,
            'point_label'         => sanitize_text_field($data['point_label'] ?? 'điểm'),
            'min_redeem_points'   => max(0, floatval($data['min_redeem_points'] ?? 0)),
        ];
        $tiers = self::normalize_tiers($data['tiers'] ?? []);

        update_option(self::OPTION_SETTINGS, $settings, false);
        update_option(self::OPTION_TIERS, $tiers, false);

        return ['settings' => self::get_settings(), 'tiers' => $tiers];
    }

    private static function normalize_tiers($tiers)
    {
        if (!is_array($tiers)) $tiers = [];
        $normalized = [];

        foreach ($tiers as $key => $tier) {
            if (!is_array($tier)) continue;
            $tk = sanitize_key($tier['key'] ?? $key ?: ($tier['name'] ?? 'tier'));
            if ($tk === '') $tk = 'tier_' . (count($normalized) + 1);
            $base = $tk;
            $s = 2;
            while (isset($normalized[$tk])) {
                $tk = $base . '_' . $s++;
            }

            $normalized[$tk] = [
                'name'       => sanitize_text_field($tier['name'] ?? ucfirst($tk)),
                'min_points' => max(0, floatval($tier['min_points'] ?? 0)),
                'color'      => sanitize_hex_color($tier['color'] ?? '#90A4AE') ?: '#90A4AE',
                'multiplier' => max(0, floatval($tier['multiplier'] ?? 1)),
            ];
        }

        if (empty($normalized)) {
            $normalized['member'] = ['name' => 'Thành viên', 'min_points' => 0, 'color' => '#90A4AE', 'multiplier' => 1.0];
        }

        uasort($normalized, fn($a, $b) => floatval($a['min_points']) <=> floatval($b['min_points']));
        return $normalized;
    }

    public static function get_default_tier_key()
    {
        $tiers = self::get_tier_definitions();
        return sanitize_key(array_key_first($tiers) ?: 'member');
    }

    public static function determine_tier($total_earned)
    {
        $tiers   = self::get_tier_definitions();
        $current = self::get_default_tier_key();
        foreach ($tiers as $key => $tier) {
            if ($total_earned >= $tier['min_points']) $current = $key;
        }
        return $current;
    }

    public static function tier_badge($tier_key)
    {
        $tiers = self::get_tier_definitions();
        $tier  = $tiers[$tier_key] ?? reset($tiers);
        $color = esc_attr($tier['color']);
        $name  = esc_html($tier['name']);
        return "<span style=\"display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;color:#fff;background:{$color};\">{$name}</span>";
    }
}

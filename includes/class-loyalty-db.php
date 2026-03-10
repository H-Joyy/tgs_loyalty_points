<?php

if (!defined('ABSPATH')) exit;

/**
 * TGS_Loyalty_DB — Quản lý bảng và truy vấn DB cho hệ thống tích điểm.
 *
 * Kiến trúc liên kết user thống nhất:
 *   - wp_user_id (WordPress user ID) là khóa chung giữa tất cả plugin
 *   - Flow: POS → local_ledger_person.user_wp_id → wp_users.ID → wp_wallet.user_id → loyalty_log.wp_user_id
 *
 * Bảng:
 *   - wp_global_loyalty_policy : Chính sách tích điểm (GLOBAL, quy tắc JSON gọn)
 *   - wp_global_loyalty_log    : Lịch sử giao dịch điểm (GLOBAL)
 *   - Không có bảng member riêng — dùng wp_wallet + wp_usermeta để lưu balance / tier
 */
class TGS_Loyalty_DB
{
    /* ──────────────── POLICY STATUS ──────────────── */
    const STATUS_DRAFT   = 0;
    const STATUS_ACTIVE  = 1;
    const STATUS_EXPIRED = 2;

    /* ──────────────── LOG TYPES ──────────────── */
    const LOG_EARN   = 'earn';
    const LOG_REDEEM = 'redeem';
    const LOG_ADJUST = 'adjust';
    const LOG_EXPIRE = 'expire';
    const LOG_REFUND = 'refund';

    /* ──── Tên bảng ──── */
    const TABLE_POLICY = 'global_loyalty_policy';
    const TABLE_LOG    = 'global_loyalty_log';

    /* ================================================================
     *  TẠO BẢNG
     * ================================================================ */

    public static function create_tables()
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $policy_table = $wpdb->prefix . self::TABLE_POLICY;
        $log_table    = $wpdb->prefix . self::TABLE_LOG;

        $sqls = [];

        // ── 1. Bảng chính sách tích điểm (gọn: cấu hình nằm trong loyalty_rules JSON) ──
        $sqls[] = "CREATE TABLE IF NOT EXISTS {$policy_table} (
            loyalty_policy_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            loyalty_policy_code       VARCHAR(50) NOT NULL,
            loyalty_policy_title      VARCHAR(255) NOT NULL,
            loyalty_policy_type       TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=earn_per_amount, 2=earn_per_product, 3=multiplier, 4=bonus_event',
            loyalty_rules             JSON NULL COMMENT 'Toàn bộ cấu hình: earn_rate, earn_unit, redeem_rate, min_order, max_points, expiry_days, product_skus, rules[]',
            apply_to_blog_ids         JSON NULL COMMENT 'Shop áp dụng ([] = tất cả)',
            apply_to_org_level        JSON NULL COMMENT 'Org chart scope',
            auto_apply                TINYINT(1) NOT NULL DEFAULT 1,
            loyalty_policy_priority   SMALLINT NOT NULL DEFAULT 0,
            loyalty_policy_start_date BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loyalty_policy_end_date   BIGINT UNSIGNED NOT NULL DEFAULT 0,
            loyalty_policy_status     TINYINT NOT NULL DEFAULT 0 COMMENT '0=draft, 1=active, 2=expired',
            is_deleted                TINYINT(1) NOT NULL DEFAULT 0,
            user_id                   BIGINT UNSIGNED NULL COMMENT 'WP user tạo policy',
            source_blog_id            BIGINT UNSIGNED NULL,
            created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_code (loyalty_policy_code),
            INDEX idx_status (loyalty_policy_status),
            INDEX idx_type (loyalty_policy_type),
            INDEX idx_priority (loyalty_policy_priority)
        ) {$charset};";

        // ── 2. Bảng log giao dịch điểm (wp_user_id = FK rõ ràng vào wp_users.ID) ──
        $sqls[] = "CREATE TABLE IF NOT EXISTS {$log_table} (
            loyalty_log_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            wp_user_id      BIGINT UNSIGNED NOT NULL COMMENT 'FK → wp_users.ID',
            log_type        VARCHAR(20) NOT NULL COMMENT 'earn, redeem, adjust, expire, refund',
            points          DECIMAL(15,2) NOT NULL,
            points_before   DECIMAL(15,2) NOT NULL DEFAULT 0,
            points_after    DECIMAL(15,2) NOT NULL DEFAULT 0,
            description     TEXT NULL,
            reference_id    VARCHAR(100) NULL COMMENT 'order_id, ticket_id, local_ledger_id,...',
            reference_type  VARCHAR(50) NULL COMMENT 'order, ticket, manual,...',
            policy_id       BIGINT UNSIGNED NULL COMMENT 'FK → loyalty_policy_id',
            log_meta        JSON NULL,
            created_by      BIGINT UNSIGNED NULL COMMENT 'WP user thực hiện',
            blog_id         BIGINT UNSIGNED NULL COMMENT 'Blog xảy ra giao dịch',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (wp_user_id),
            INDEX idx_type (log_type),
            INDEX idx_ref (reference_id, reference_type),
            INDEX idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }
    }

    /* ================================================================
     *  POLICY CRUD
     * ================================================================ */

    public static function get_policies($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;

        $defaults = [
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
            'status'   => '',
            'type'     => '',
            'orderby'  => 'loyalty_policy_priority ASC, loyalty_policy_id DESC',
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

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $offset = ($args['page'] - 1) * $args['per_page'];

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$args['orderby']} LIMIT %d OFFSET %d",
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items'       => $items ?: [],
            'total'       => intval($total),
            'page'        => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1,
        ];
    }

    public static function get_policy($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE loyalty_policy_id = %d AND is_deleted = 0", $id));
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
        $table = $wpdb->prefix . self::TABLE_POLICY;
        return $wpdb->update($table, ['is_deleted' => 1, 'updated_at' => current_time('mysql')], ['loyalty_policy_id' => $id]);
    }

    public static function clone_policy($id)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;

        $original = self::get_policy($id);
        if (!$original) return false;

        $new_data = (array) $original;
        unset($new_data['loyalty_policy_id']);
        $new_data['loyalty_policy_code']   = $original->loyalty_policy_code . '-COPY-' . time();
        $new_data['loyalty_policy_title']  = $original->loyalty_policy_title . ' (bản sao)';
        $new_data['loyalty_policy_status'] = self::STATUS_DRAFT;
        $new_data['created_at'] = current_time('mysql');
        $new_data['updated_at'] = current_time('mysql');

        $wpdb->insert($table, $new_data);
        return $wpdb->insert_id;
    }

    public static function get_policy_stats()
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_POLICY;
        $now = time();

        $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0");
        $active = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0 AND loyalty_policy_status = 1
             AND (loyalty_policy_start_date = 0 OR loyalty_policy_start_date <= %d)
             AND (loyalty_policy_end_date = 0 OR loyalty_policy_end_date >= %d)",
            $now,
            $now
        ));
        $draft  = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0 AND loyalty_policy_status = 0");
        $auto   = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_deleted = 0 AND auto_apply = 1");

        $by_type = $wpdb->get_results(
            "SELECT loyalty_policy_type AS type, COUNT(*) AS cnt FROM {$table} WHERE is_deleted = 0 GROUP BY loyalty_policy_type ORDER BY loyalty_policy_type"
        );

        return [
            'total'   => intval($total),
            'active'  => intval($active),
            'draft'   => intval($draft),
            'auto'    => intval($auto),
            'by_type' => $by_type,
        ];
    }

    /* ================================================================
     *  POLICY RULE HELPERS — đọc cấu hình từ loyalty_rules JSON
     * ================================================================ */

    /**
     * Parse loyalty_rules JSON thành array, merge với default values.
     */
    public static function parse_rules($policy)
    {
        $defaults = [
            'earn_rate'          => 1,
            'earn_unit'          => 1000,
            'redeem_rate'        => 1,
            'min_order_amount'   => 0,
            'max_points_per_order' => 0,
            'point_expiry_days'  => 0,
            'product_skus'       => [],
            'rules'              => [],
        ];
        $rules = json_decode($policy->loyalty_rules ?? '{}', true);
        if (!is_array($rules)) $rules = [];
        return wp_parse_args($rules, $defaults);
    }

    /* ================================================================
     *  POINT LOG
     * ================================================================ */

    public static function add_log($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOG;
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function get_logs($args = [])
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_LOG;

        $defaults = [
            'page'       => 1,
            'per_page'   => 20,
            'wp_user_id' => 0,
            'log_type'   => '',
            'orderby'    => 'created_at DESC',
        ];
        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        if ($args['wp_user_id']) {
            $where .= $wpdb->prepare(" AND wp_user_id = %d", intval($args['wp_user_id']));
        }
        if ($args['log_type']) {
            $where .= $wpdb->prepare(" AND log_type = %s", sanitize_text_field($args['log_type']));
        }

        $total  = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $offset = ($args['page'] - 1) * $args['per_page'];

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY {$args['orderby']} LIMIT %d OFFSET %d",
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items'       => $items ?: [],
            'total'       => intval($total),
            'page'        => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1,
        ];
    }

    /**
     * Thống kê member từ bảng wp_wallet (delegate sang wallet plugin).
     * Trả tổng số wallet + phân bố tier + tổng điểm.
     */
    public static function get_member_stats()
    {
        global $wpdb;
        $wallet_table = $wpdb->base_prefix . 'wallet';
        $default_tier = self::get_default_tier_key();

        // Kiểm tra bảng wallet tồn tại
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wallet_table}'") !== $wallet_table) {
            return ['total' => 0, 'by_tier' => [], 'total_points' => 0];
        }

        $total     = $wpdb->get_var("SELECT COUNT(*) FROM {$wallet_table} WHERE status = 'active'");
        $total_pts = $wpdb->get_var("SELECT COALESCE(SUM(balance), 0) FROM {$wallet_table} WHERE status = 'active'");

        // Tier phân bố từ usermeta
        $by_tier = $wpdb->get_results(
            "SELECT COALESCE(um.meta_value, '{$default_tier}') AS tier, COUNT(*) AS cnt
             FROM {$wallet_table} w
             LEFT JOIN {$wpdb->usermeta} um ON um.user_id = w.user_id AND um.meta_key = 'loyalty_tier'
             WHERE w.status = 'active'
             GROUP BY tier
             ORDER BY cnt DESC"
        );

        return [
            'total'        => intval($total),
            'by_tier'      => $by_tier ?: [],
            'total_points' => floatval($total_pts),
        ];
    }

    /* ================================================================
     *  MEMBER HELPERS — delegate sang wallet plugin
     * ================================================================ */

    /**
     * Lấy danh sách members (wallet + user info + tier).
     */
    public static function get_members($args = [])
    {
        global $wpdb;
        $wallet_table = $wpdb->base_prefix . 'wallet';
        $default_tier = self::get_default_tier_key();

        $defaults = [
            'page'     => 1,
            'per_page' => 20,
            'search'   => '',
            'tier'     => '',
        ];
        $args = wp_parse_args($args, $defaults);

        // Kiểm tra bảng wallet
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wallet_table}'") !== $wallet_table) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1];
        }

        $where = "w.status = 'active'";

        if ($args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= $wpdb->prepare(
                " AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                $like,
                $like
            );
        }
        if ($args['tier']) {
            $where .= $wpdb->prepare(
                " AND COALESCE(um_tier.meta_value, '{$default_tier}') = %s",
                sanitize_text_field($args['tier'])
            );
        }

        $total = $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wallet_table} w
             JOIN {$wpdb->users} u ON u.ID = w.user_id
             LEFT JOIN {$wpdb->usermeta} um_tier ON um_tier.user_id = w.user_id AND um_tier.meta_key = 'loyalty_tier'
             WHERE {$where}"
        );

        $offset = ($args['page'] - 1) * $args['per_page'];

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT w.id AS wallet_id, w.user_id AS wp_user_id,
                    u.display_name, u.user_email,
                    w.balance AS current_points,
                    w.total_earned, w.total_spent AS total_redeemed,
                    COALESCE(um_tier.meta_value, '{$default_tier}') AS member_tier,
                    COALESCE(um_phone.meta_value, '') AS customer_phone
             FROM {$wallet_table} w
             JOIN {$wpdb->users} u ON u.ID = w.user_id
             LEFT JOIN {$wpdb->usermeta} um_tier ON um_tier.user_id = w.user_id AND um_tier.meta_key = 'loyalty_tier'
             LEFT JOIN {$wpdb->usermeta} um_phone ON um_phone.user_id = w.user_id AND um_phone.meta_key = 'billing_phone'
             WHERE {$where}
             ORDER BY w.balance DESC
             LIMIT %d OFFSET %d",
            intval($args['per_page']),
            intval($offset)
        ));

        return [
            'items'       => $items ?: [],
            'total'       => intval($total),
            'page'        => intval($args['page']),
            'total_pages' => $args['per_page'] > 0 ? ceil($total / $args['per_page']) : 1,
        ];
    }

    /* ================================================================
     *  TIER — delegate sang wallet plugin nếu có, fallback nội bộ
     * ================================================================ */

    /**
     * Lấy tier definitions. Ưu tiên wallet plugin, fallback option nội bộ.
     */
    public static function get_tier_definitions()
    {
        // Ưu tiên wallet plugin tier system
        if (function_exists('user_wallet_get_available_tiers')) {
            $wallet_tiers = user_wallet_get_available_tiers();
            if (!empty($wallet_tiers)) {
                $result = [];
                foreach ($wallet_tiers as $t) {
                    $key = sanitize_key($t['id'] ?? $t['name'] ?? 'standard');
                    $result[$key] = [
                        'name'       => $t['name'] ?? $key,
                        'min_points' => floatval($t['min_points'] ?? 0),
                        'color'      => $t['color'] ?? '#999999',
                        'multiplier' => floatval($t['discount_percent'] ?? 0) > 0
                            ? 1 + (floatval($t['discount_percent']) / 100)
                            : 1.0,
                    ];
                }
                return $result;
            }
        }

        // Fallback
        $defaults = [
            'bronze'   => ['name' => 'Đồng',     'min_points' => 0,     'color' => '#CD7F32', 'multiplier' => 1.0],
            'silver'   => ['name' => 'Bạc',      'min_points' => 1000,  'color' => '#8B9DC3', 'multiplier' => 1.2],
            'gold'     => ['name' => 'Vàng',     'min_points' => 5000,  'color' => '#F4C430', 'multiplier' => 1.5],
            'platinum' => ['name' => 'Bạch Kim', 'min_points' => 20000, 'color' => '#667EEA', 'multiplier' => 2.0],
        ];
        return get_option('tgs_loyalty_tiers', $defaults);
    }

    /**
     * Lấy tier mặc định đầu tiên theo cấu hình hiện tại.
     */
    public static function get_default_tier_key()
    {
        $tiers = self::get_tier_definitions();
        return sanitize_key(array_key_first($tiers) ?: 'bronze');
    }

    /**
     * Xác định tier dựa trên tổng điểm tích lũy.
     */
    public static function determine_tier($total_earned)
    {
        // Ưu tiên wallet plugin
        if (function_exists('user_wallet_get_tier')) {
            // Không thể gọi trực tiếp vì cần user_id, method này dùng total_earned
        }

        $tiers   = self::get_tier_definitions();
        $current = self::get_default_tier_key();

        foreach ($tiers as $key => $tier) {
            if ($total_earned >= $tier['min_points']) {
                $current = $key;
            }
        }
        return $current;
    }

    /**
     * Badge HTML cho tier.
     */
    public static function tier_badge($tier_key)
    {
        $tiers = self::get_tier_definitions();
        $tier  = $tiers[$tier_key] ?? reset($tiers);
        $color = esc_attr($tier['color']);
        $name  = esc_html($tier['name']);
        return "<span style=\"display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;color:#fff;background:{$color};\">{$name}</span>";
    }
}

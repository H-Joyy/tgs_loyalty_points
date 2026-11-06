<?php
/**
 * API cho POS React: xuất CHƯƠNG TRÌNH ĐIỂM của một shop (cài đặt, hạng, chính sách tích điểm, danh mục đổi quà).
 *
 * POS mới tính điểm bằng TypeScript (@tgs/pos-core/loyalty, port từ class-loyalty-engine.php) ở máy bán và module
 * `pos`. SỐ ĐIỂM của khách không còn ở ví WP (wp_wallet) mà ở sổ điểm của phần mềm kế toán (Q5, docs 19). Màn khai
 * báo chính sách / hạng / cài đặt vẫn ở plugin này; API chỉ đọc. Khuôn trả về = `LoyaltyProgram` trong
 * tgs_pos_react/openapi/pos.yaml.
 *
 *   GET /wp-json/tgs-loyalty/v1/pos-program?blog_id=12
 *
 * Quyền đọc: server POS ký HMAC-SHA256 (X-TGS-Timestamp, X-TGS-Signature = hmac(timestamp, TGS_POS_SSO_SECRET),
 * lệch ≤ 5 phút), hoặc người dùng WP đã đăng nhập.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class TGS_Loyalty_React_Api
{
    const NS = 'tgs-loyalty/v1';
    const MAX_SKEW = 300;

    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register']);
    }

    public static function register()
    {
        register_rest_route(self::NS, '/pos-program', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_program'],
            'permission_callback' => [__CLASS__, 'can_read'],
            'args' => ['blog_id' => ['type' => 'integer', 'required' => false]],
        ]);
    }

    public static function can_read($request)
    {
        $secret = defined('TGS_POS_SSO_SECRET') ? (string) TGS_POS_SSO_SECRET : '';
        $ts = (string) $request->get_header('x-tgs-timestamp');
        $sig = (string) $request->get_header('x-tgs-signature');
        if ($secret !== '' && $ts !== '' && $sig !== '' && abs(time() - intval($ts)) <= self::MAX_SKEW) {
            return hash_equals(hash_hmac('sha256', $ts, $secret), $sig);
        }
        return is_user_logged_in();
    }

    public static function get_program($request)
    {
        $blog_id = intval($request->get_param('blog_id')) ?: get_current_blog_id();
        // Bảng chính sách điểm là bảng THEO SITE ({prefix}global_loyalty_policy); cài đặt / hạng là option của site.
        $switched = is_multisite() && $blog_id !== get_current_blog_id();
        if ($switched) {
            switch_to_blog($blog_id);
        }
        try {
            $program = self::build_program($blog_id);
        } finally {
            if ($switched) {
                restore_current_blog();
            }
        }
        return rest_ensure_response($program);
    }

    private static function build_program($blog_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . TGS_Loyalty_DB::TABLE_POLICY;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE is_deleted = 0 AND loyalty_policy_status = 1
             ORDER BY loyalty_policy_priority ASC, loyalty_policy_id DESC"
        );

        $now = time();
        $policies = [];
        foreach ((array) $rows as $row) {
            $policy = self::map_policy($row);
            // Đã hết hạn thì không gửi; chưa tới ngày vẫn gửi (máy cache theo phiên bản).
            if ($policy['endDate'] > 0 && $policy['endDate'] < $now) {
                continue;
            }
            if (!empty($policy['applyToBlogIds']) && !in_array(intval($blog_id), $policy['applyToBlogIds'], true)) {
                continue;
            }
            $policies[] = $policy;
        }

        $settings = TGS_Loyalty_DB::get_settings();
        $tiers = [];
        foreach (TGS_Loyalty_DB::get_tier_definitions() as $key => $tier) {
            $tiers[] = [
                'key' => (string) $key,
                'name' => (string) $tier['name'],
                'minPoints' => floatval($tier['min_points']),
                'multiplier' => floatval($tier['multiplier']),
                'color' => (string) ($tier['color'] ?? ''),
            ];
        }
        $out_settings = [
            'enablePosEarn' => !empty($settings['enable_pos_earn']),
            'enablePosRedeem' => !empty($settings['enable_pos_redeem']),
            'enableTierMultiplier' => !empty($settings['enable_tier_multiplier']),
            'minRedeemPoints' => floatval($settings['min_redeem_points'] ?? 0),
            'pointLabel' => (string) ($settings['point_label'] ?? 'điểm'),
        ];

        return [
            'version' => 'lp-' . substr(md5(wp_json_encode([$blog_id, $policies, $tiers, $out_settings])), 0, 16),
            'settings' => $out_settings,
            'tiers' => $tiers,
            'policies' => $policies,
        ];
    }

    /** Một dòng global_loyalty_policy → khuôn LoyaltyProgram.policies[] (loyalty_rules đã giải mã, giữ nguyên dạng). */
    public static function map_policy($row)
    {
        $rules = json_decode((string) ($row->loyalty_rules ?? ''), true);
        $blogs = json_decode((string) ($row->apply_to_blog_ids ?? ''), true);
        // + 0 chứ không intval: PHP 32 bit chặn intval ở năm 2038.
        $ts = static function ($v) {
            return is_numeric($v) ? $v + 0 : 0;
        };
        return [
            'id' => intval($row->loyalty_policy_id),
            'code' => (string) ($row->loyalty_policy_code ?? ''),
            'title' => (string) ($row->loyalty_policy_title ?? ''),
            'type' => intval($row->loyalty_policy_type ?? 0),
            'priority' => intval($row->loyalty_policy_priority ?? 0),
            'status' => intval($row->loyalty_policy_status ?? 0),
            'isDeleted' => intval($row->is_deleted ?? 0) === 1,
            'autoApply' => intval($row->auto_apply ?? 0) === 1,
            'startDate' => $ts($row->loyalty_policy_start_date ?? 0),
            'endDate' => $ts($row->loyalty_policy_end_date ?? 0),
            'applyToBlogIds' => array_values(array_map('intval', is_array($blogs) ? $blogs : [])),
            // Rỗng phải ra {} (không phải []) để đúng kiểu object.
            'rules' => is_array($rules) && !empty($rules) ? $rules : new stdClass(),
        ];
    }
}

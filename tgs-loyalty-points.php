<?php

/**
 * Plugin Name: TGS Loyalty Points
 * Description: Chính sách tích điểm khách hàng — Tích điểm khi mua hàng, đổi điểm, quản lý hạng thành viên.
 * Version: 1.0.0
 * Author: TGS Team
 * Text Domain: tgs-loyalty-points
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) exit;

define('TGS_LOYALTY_VERSION', '1.0.0');
define('TGS_LOYALTY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TGS_LOYALTY_PLUGIN_URL', plugin_dir_url(__FILE__));

class TGS_Loyalty_Points_Plugin
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        require_once TGS_LOYALTY_PLUGIN_DIR . 'includes/class-loyalty-db.php';
        require_once TGS_LOYALTY_PLUGIN_DIR . 'includes/class-loyalty-engine.php';
        require_once TGS_LOYALTY_PLUGIN_DIR . 'includes/class-loyalty-ajax.php';
        $this->init_hooks();
    }

    private function init_hooks()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_filter('tgs_shop_dashboard_routes', [$this, 'add_routes']);
        add_action('tgs_shop_sidebar_menu', [$this, 'add_sidebar']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        TGS_Loyalty_Ajax::init();
    }

    /**
     * Tạo bảng DB khi kích hoạt plugin.
     */
    public function activate()
    {
        TGS_Loyalty_DB::create_tables();
    }

    /**
     * Đăng ký routes vào dashboard tgs_shop_management.
     */
    public function add_routes($routes)
    {
        $dir = TGS_LOYALTY_PLUGIN_DIR . 'admin-views/';
        $routes['loyalty-dashboard']    = ['Dashboard tích điểm', $dir . 'dashboard.php'];
        $routes['loyalty-policies']     = ['Chính sách tích điểm', $dir . 'list.php'];
        $routes['loyalty-policy-add']   = ['Thêm chính sách tích điểm', $dir . 'add.php'];
        $routes['loyalty-policy-detail'] = ['Chi tiết chính sách tích điểm', $dir . 'detail.php'];
        $routes['loyalty-members']      = ['Thành viên tích điểm', $dir . 'members.php'];
        return $routes;
    }

    /**
     * Thêm menu sidebar nằm trong nhóm "Bán hàng".
     */
    public function add_sidebar($current_view)
    {
        $views = [
            'loyalty-dashboard',
            'loyalty-policies',
            'loyalty-policy-add',
            'loyalty-policy-detail',
            'loyalty-members',
        ];
        $is_active = in_array($current_view, $views);
?>
        <li class="menu-item <?php echo $is_active ? 'active open' : ''; ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-gift"></i>
                <div>Tích điểm</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item <?php echo $current_view === 'loyalty-dashboard' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-dashboard'); ?>" class="menu-link">
                        <i class="bx bx-bar-chart-alt-2 me-1"></i>
                        <div>Dashboard</div>
                    </a>
                </li>
                <li class="menu-item <?php echo in_array($current_view, ['loyalty-policies', 'loyalty-policy-add', 'loyalty-policy-detail']) ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-policies'); ?>" class="menu-link">
                        <i class="bx bx-star me-1"></i>
                        <div>Chính sách tích điểm</div>
                    </a>
                </li>
                <li class="menu-item <?php echo $current_view === 'loyalty-members' ? 'active' : ''; ?>">
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-members'); ?>" class="menu-link">
                        <i class="bx bx-group me-1"></i>
                        <div>Thành viên</div>
                    </a>
                </li>
            </ul>
        </li>
<?php
    }

    /**
     * Enqueue CSS/JS cho các view loyalty-*.
     */
    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'tgs-shop-management') === false) return;
        $v = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : '';
        if (strpos($v, 'loyalty-') !== 0) return;

        wp_enqueue_style('tgs-loyalty-css', TGS_LOYALTY_PLUGIN_URL . 'assets/css/loyalty.css', [], TGS_LOYALTY_VERSION);
        wp_enqueue_script('tgs-loyalty-js', TGS_LOYALTY_PLUGIN_URL . 'assets/js/loyalty.js', ['jquery'], TGS_LOYALTY_VERSION, true);
        wp_localize_script('tgs-loyalty-js', 'tgsLoyalty', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('tgs_loyalty_nonce'),
            'blogId'  => get_current_blog_id(),
        ]);

        // Scope Selector (shared from tgs_selling_policy)
        if (defined('TGS_SELLING_PLUGIN_URL')) {
            wp_enqueue_style('tgs-scope-selector-css', TGS_SELLING_PLUGIN_URL . 'assets/css/scope-selector.css', [], TGS_LOYALTY_VERSION);
            wp_enqueue_script('tgs-scope-selector-js', TGS_SELLING_PLUGIN_URL . 'assets/js/scope-selector.js', ['jquery', 'tgs-loyalty-js'], TGS_LOYALTY_VERSION, true);
        }
    }
}

/**
 * Khởi tạo plugin sau khi tgs_shop_management đã load.
 */
function tgs_loyalty_points_init()
{
    if (!class_exists('TGS_Shop_Management')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>TGS Loyalty Points</strong> cần plugin <strong>TGS Shop Management</strong> được kích hoạt.</p></div>';
        });
        return;
    }
    TGS_Loyalty_Points_Plugin::get_instance();
}
add_action('plugins_loaded', 'tgs_loyalty_points_init', 20);

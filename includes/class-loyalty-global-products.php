<?php

if (!defined('ABSPATH')) exit;

/**
 * Cầu nối sản phẩm global cho loyalty.
 *
 * Quy ước: chính sách tích điểm lưu SKU, không lưu sản phẩm local.
 * Các key local_product_* trả về cho UI chỉ là alias từ global product source.
 */
class TGS_Loyalty_Global_Products
{
    private static function ensure_global_constants()
    {
        global $wpdb;

        if (!defined('TGS_TABLE_GLOBAL_PRODUCT_NAME')) {
            define('TGS_TABLE_GLOBAL_PRODUCT_NAME', $wpdb->base_prefix . 'global_product_name');
        }
    }

    private static function ensure_source()
    {
        self::ensure_global_constants();

        if (class_exists('TGS_Global_Product_Source')) {
            return true;
        }

        $plugin_root = defined('WP_PLUGIN_DIR')
            ? WP_PLUGIN_DIR
            : dirname(TGS_LOYALTY_PLUGIN_DIR);

        $candidates = [
            trailingslashit($plugin_root) . 'tgs_shop_management/functions/class-tgs-global-product-source.php',
            trailingslashit(dirname(TGS_LOYALTY_PLUGIN_DIR)) . 'tgs_shop_management/functions/class-tgs-global-product-source.php',
        ];

        foreach ($candidates as $file) {
            if (is_readable($file)) {
                require_once $file;
                break;
            }
        }

        return class_exists('TGS_Global_Product_Source');
    }

    public static function search_products($search = '', $blog_id = 0, $limit = 50)
    {
        if (!self::ensure_source()) {
            return [];
        }

        $limit = max(1, min(100, (int) $limit));
        $args = [
            'search' => sanitize_text_field((string) $search),
            'per_page' => $limit,
            'parent_only' => false,
            'require_sku' => true,
            'status_filter' => 'active',
            'with_local_aliases' => true,
            'order_by' => 'global_product_name',
            'order_dir' => 'ASC',
        ];

        if ((int) $blog_id > 0) {
            $args['blog_id'] = (int) $blog_id;
        }

        $result = TGS_Global_Product_Source::query_products($args);
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];

        return array_map([__CLASS__, 'to_legacy_payload'], $items);
    }

    private static function to_legacy_payload(array $product)
    {
        $sku = (string) ($product['global_product_sku'] ?? $product['local_product_sku'] ?? '');
        $name = (string) ($product['global_product_name'] ?? $product['local_product_name'] ?? '');
        $price = (float) ($product['global_product_price'] ?? $product['local_product_price'] ?? 0);
        $price_after_tax = (float) ($product['global_product_price_after_tax'] ?? $product['local_product_price_after_tax'] ?? 0);

        return [
            'global_product_name_id' => (int) ($product['global_product_name_id'] ?? 0),
            'global_product_sku' => $sku,
            'global_product_name' => $name,
            'global_product_price' => $price,
            'global_product_price_after_tax' => $price_after_tax,
            'global_product_unit' => (string) ($product['global_product_unit'] ?? ''),

            // Alias cho JS hiện tại trong màn thêm/sửa chính sách.
            'local_product_name_id' => (int) ($product['global_product_name_id'] ?? 0),
            'local_product_sku' => $sku,
            'local_product_name' => $name,
            'local_product_price' => $price,
            'local_product_price_after_tax' => $price_after_tax,
            'local_product_unit' => (string) ($product['global_product_unit'] ?? ''),
        ];
    }
}

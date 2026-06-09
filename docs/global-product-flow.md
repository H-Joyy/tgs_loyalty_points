# TGS Loyalty Points - luồng sản phẩm global

Tài liệu này ghi lại cách plugin `tgs_loyalty_points` dùng sản phẩm sau khi hệ thống chuyển sang catalog global.

## Nguyên tắc

- Không query hoặc join bảng `wp_local_product_name` / `{blog_prefix}_local_product_name`.
- Chính sách loyalty loại "theo sản phẩm" lưu theo SKU, không lưu ID sản phẩm local.
- SKU trong rule được hiểu là `global_product_sku`.
- Catalog sản phẩm lấy từ `wp_global_product_name` thông qua `TGS_Global_Product_Source`.
- Các key `local_product_*` trả về cho màn thêm/sửa chính sách chỉ là alias từ global để giữ UI cũ không vỡ.

Doc API gốc nằm ở:

```text
wp-content/plugins/tgs_shop_management/docs/global-product-api.md
```

## Helper trung tâm

File:

```text
includes/class-loyalty-global-products.php
```

Class:

```php
TGS_Loyalty_Global_Products
```

Hàm chính:

- `search_products($search, $blog_id, $limit)`: tìm sản phẩm global theo tên/SKU/barcode.

Response vẫn có alias cho JS cũ:

- `local_product_sku` = `global_product_sku`
- `local_product_name` = `global_product_name`
- `local_product_price` = `global_product_price`
- `local_product_price_after_tax` = `global_product_price_after_tax`
- `local_product_name_id` = `global_product_name_id`

## Autocomplete SKU trong chính sách

AJAX:

```text
tgs_loyalty_get_products
```

Luồng mới:

1. Nhận `search` và `blog_id` từ màn thêm/sửa chính sách.
2. Gọi `TGS_Loyalty_Global_Products::search_products()`.
3. Helper gọi `TGS_Global_Product_Source::query_products()`.
4. Trả về danh sách sản phẩm có SKU để UI chọn.

Không còn union các bảng `{blog_prefix}_local_product_name` trên multisite.

## Engine tính điểm

`TGS_Loyalty_Engine` vẫn tính điểm theo SKU trong cart:

- Cart item đọc `sku` hoặc `product_sku`.
- Rule loại 2 đọc `rule.sku`.
- Khi SKU khớp, cộng điểm theo `points_per_unit`.

Vì vậy khi POS hoặc plugin khác truyền cart vào loyalty, SKU phải là SKU global.

## Quy ước khi phát triển tiếp

- Nếu cần tìm sản phẩm trong màn loyalty: dùng `TGS_Loyalty_Global_Products`.
- Nếu cần chi tiết sản phẩm khác ngoài autocomplete: gọi `TGS_Global_Product_Source`.
- Nếu cần tồn kho để đổi quà sau này: dùng API/source global trong `tgs_shop_management`, ưu tiên `actual_stock`.
- Không thêm query mới vào `local_product_name`.
- Nếu thấy `local_product_*` trong UI loyalty: hiểu là alias từ global.

## Checklist review code

- Không có `TGS_TABLE_LOCAL_PRODUCT_NAME` trong PHP loyalty.
- Không có `JOIN local_product_name`.
- Không có `FROM local_product_name`.
- AJAX product search đi qua `TGS_Loyalty_Global_Products`.
- Rule sản phẩm lưu SKU global.

# Luồng Tích Điểm (Loyalty Points) — Tài liệu kỹ thuật

> Cập nhật: 2026-03-19

---

## 1. Database Schema

### Bảng `{base_prefix}wallet` — Ví điểm toàn hệ thống (global)
| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | int PK | |
| `user_id` | int | WP user ID |
| `balance` | decimal | Số điểm hiện tại |
| `total_earned` | decimal | Tổng điểm đã tích lũy (dùng xác định tier) |
| `total_spent` | decimal | Tổng điểm đã đổi |
| `transaction_count` | int | Số lần giao dịch |
| `wallet_type` | varchar | `loyalty` |
| `status` | varchar | `active` / `frozen` |
| `last_transaction_at` | datetime | |
| `wallet_meta` | JSON | Metadata tùy chỉnh |

### Bảng `{base_prefix}wallet_log` — Lịch sử giao dịch điểm (global)
| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | int PK | |
| `wallet_id` | int | FK → wallet.id |
| `user_id` | int | WP user ID |
| `transaction_type` | varchar | `credit` (tích) / `debit` (đổi) |
| `amount` | decimal | Số điểm giao dịch |
| `reference_id` | varchar | ID đơn hàng / sale_ledger_id |
| `reference_type` | varchar | `loyalty_earn` / `sale_order` / `manual_adjust` |
| `wallet_log_meta` | JSON | `{description, balance_before, balance_after}` |
| `ip_address` | varchar | |
| `created_by` | int | WP user ID người thực hiện |
| `created_at` | datetime | |

### Bảng `{prefix}global_loyalty_policy` — Chính sách tích điểm (per-site)
| Cột | Kiểu | Mô tả |
|---|---|---|
| `loyalty_policy_id` | int PK | |
| `loyalty_policy_code` | varchar | Mã chính sách |
| `loyalty_policy_title` | varchar | Tên chính sách |
| `loyalty_policy_type` | int | 1=earn_per_amount · 2=earn_per_product · 3=multiplier · 4=bonus_event · 5=product_reward · 6=voucher_reward |
| `loyalty_rules` | JSON | Xem chi tiết bên dưới |
| `apply_to_blog_ids` | JSON | `[]` = áp dụng mọi site |
| `loyalty_policy_status` | int | 1=hoạt động |
| `auto_apply` | int | 1=tự động |
| `loyalty_policy_priority` | int | Số nhỏ = ưu tiên cao |
| `loyalty_policy_start_date` | int | Unix timestamp |
| `loyalty_policy_end_date` | int | Unix timestamp |
| `is_deleted` | int | Soft delete |

**Cấu trúc `loyalty_rules` JSON theo loại:**
```json
// Type 1 — earn_per_amount
{ "earn_rate": 1, "earn_unit": 1000, "max_points_per_order": 500, "min_order_amount": 0 }

// Type 2 — earn_per_product
{ "rules": [{ "sku": "SKU001", "points_per_unit": 5 }] }

// Type 3 — multiplier (bonus layer, không thay thế type 1)
{ "earn_rate": 1, "earn_unit": 1000, "bonus_multiplier": 2.0 }

// Type 4 — bonus_event
{ "bonus_points": 100, "min_order_amount": 500000 }

// Type 5 — product_reward (catalog đổi quà)
{ "points_required": 200, "product_id": 42, "product_name": "Bình giữ nhiệt", "quantity": 1, "is_tracking": 0 }

// Type 6 — voucher_reward (catalog đổi voucher)
{ "points_required": 500, "voucher_prefix": "TGS-VIP-", "discount_type": "percent", "discount_value": 10, "max_discount": 50000 }
```

### Bảng `{prefix}global_loyalty_log` — Lịch sử điểm theo site (per-site)
| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | int PK | |
| `log_type` | varchar | `earn` / `redeem` / `adjust` / `expire` / `refund` |
| `wp_user_id` | int | WP user ID |
| `points` | decimal | Số điểm giao dịch |
| `points_before` | decimal | Số dư trước |
| `points_after` | decimal | Số dư sau |
| `description` | varchar | Mô tả |
| `reference_id` | varchar | ID tham chiếu |
| `reference_type` | varchar | `sale_order` / `manual` / ... |
| `policy_id` | int | FK → global_loyalty_policy |
| `log_meta` | JSON | Chi tiết bổ sung |
| `created_by` | int | WP user ID |
| `blog_id` | int | Site ID |
| `created_at` | datetime | |

### Bảng `{prefix}local_ledger_person` — Khách hàng TGS (per-site)
Cột quan trọng: **`user_wp_id`** — cầu nối giữa khách TGS và ví WP.
- NULL → **khách hàng chưa có tài khoản WP → không tích/trừ điểm được**.

---

## 2. Luồng TÍCH ĐIỂM (Earn)

```
[POS - pos-order.js]
  saveOrderToServer()
    POST action=tgs_pos_save_order
      total            = getTotalWithTax()   ← QUAN TRỌNG: tổng SAU giảm giá
      tgs_person_id    = selectedCustomer.tgs_person_id
      items            = JSON cart items
      points_used      = 0 (nếu không đổi quà)
      selected_rewards = []
         ↓
[PHP - class-tgs-pos-ajax-order.php]
  TGS_POS_Ajax_Order::save_order()
    1. Tạo đơn hàng → $sale_ledger_id
    2. Query local_ledger_person WHERE id = $tgs_person_id → lấy user_wp_id
       ├─ user_wp_id IS NULL → loyalty_warnings[]: "Khách chưa liên kết tài khoản"
       └─ user_wp_id tồn tại → tiếp tục
    3. TGS_Loyalty_DB::ensure_wallet($user_wp_id)
         └─ INSERT IGNORE INTO {base_prefix}wallet (user_id, balance=0, ...)
    4. tgs_loyalty_earn_points($user_wp_id, $total, $context)
         ↓
[PHP - class-loyalty-engine.php]
  TGS_Loyalty_Engine::earn_points()
    → calculate_earn_points($context)
        → get_active_policies($store_id)
             SELECT FROM {prefix}global_loyalty_policy
             WHERE status=1 AND auto_apply=1
               AND (start_date=0 OR start_date <= NOW())
               AND (end_date=0   OR end_date   >= NOW())
               AND apply_to_blog_ids chứa store_id hoặc rỗng
        → evaluate_policy() cho từng chính sách:
             Type 1: floor(order_total / earn_unit) * earn_rate * tier_multiplier
             Type 2: Σ (points_per_sku * qty) cho SKU khớp trong cart
             Type 3: floor(total / earn_unit) * earn_rate * (bonus_multiplier − 1)
             Type 4: bonus_points nếu order_total ≥ min_amount
             Áp cap max_points_per_order nếu có
        → Tổng điểm = Σ tất cả chính sách áp dụng
    → TGS_Loyalty_DB::add_balance($user_wp_id, $points, ...)
         START TRANSACTION
           UPDATE {base_prefix}wallet
             SET balance += $points,
                 total_earned += $points,
                 transaction_count += 1,
                 last_transaction_at = NOW()
             WHERE user_id = $user_wp_id
           INSERT INTO {base_prefix}wallet_log
             (transaction_type='credit', amount=$points, reference_type='loyalty_earn', ...)
         COMMIT
    → determine_tier($total_earned)
         UPDATE wp_usermeta SET loyalty_tier = 'gold' WHERE user_id = ...
    → INSERT INTO {prefix}global_loyalty_log (log_type='earn', ...)
    → do_action('tgs_loyalty_points_earned', ...)
    → return { success: true, points_earned, points_before, points_after, tier }
         ↓
[PHP - save_order() tiếp]
    5. merge_order_meta($sale_ledger_id, { points_earned, loyalty_points_after, ... })
         UPDATE {prefix}local_ledger_meta SET ... (JSON blob)
    6. wp_send_json_success({ ..., points_earned, loyalty_points_after, loyalty_tier })
         ↓
[POS - pos-order.js]
    7. Hiển thị màn hình biên nhận với điểm vừa tích
```

---

## 3. Luồng PREVIEW ĐIỂM (Earn Preview — trước khi bấm thanh toán)

```
[POS - pos-promotion.js]
  checkPromotions()  [tự động gọi khi cart thay đổi]
    POST action=tgs_pos_check_promotions
      cart_total   = getTotalWithTax()  ← SAU giảm giá (đã fix bug)
      cart_items   = JSON
      customer_id  = tgs_person_id
         ↓
[PHP - class-tgs-pos-ajax-order.php]
  TGS_POS_Ajax_Order::check_promotions()
    → Với mỗi policy type 7 (loyalty_points trong selling_policy):
         tgs_loyalty_calculate_earn_points($user_wp_id, $cart_total, $context)
    → Trả về promotion { type: 'loyalty_points', calculated_result: { points_earned: X } }
    → type='loyalty_points' bị LOẠI KHỎI calculateTotalDiscount() → chỉ hiển thị thông tin
         ↓
[POS - pos-layout-tailwind.php]
    Hiển thị "Sẽ tích X điểm" trên thanh giỏ hàng
```

---

## 4. Luồng ĐỔI ĐIỂM / ĐỔI QUÀ (Redeem)

```
[POS - pos-promotion.js]
  openRewardsModal()
    POST action=tgs_pos_check_promotions
      include_rewards = true
      cart_total      = getTotalWithTax()  ← SAU giảm giá (đã fix bug)
      customer_id     = tgs_person_id
         ↓
[PHP - class-tgs-pos-ajax-order.php]
  → get_rewards_for_customer($customer_id, $context)
       → tgs_loyalty_get_rewards_for_customer(...)
            → map tgs_person_id → user_wp_id
            → SELECT balance FROM {base_prefix}wallet WHERE user_id = $user_wp_id
            → Query {prefix}global_loyalty_policy WHERE type IN (5, 6)
            → Với mỗi phần thưởng: can_redeem = (balance >= points_required)
       → return { available_rewards[], customer_points, customer{tier, ...} }
         ↓
[POS]
  Hiển thị modal: danh sách quà, điểm cần, "Đủ điểm / Không đủ"
  Cashier chọn quà → toggleRewardSelection() → calculatePointsToDeduct()
  confirmRewardSelection() → gắn vào appliedPromotions, lưu selectedRewards[]

[POS - pos-order.js]
  saveOrderToServer()
    POST ...
      points_used      = pointsToDeduct (tổng điểm cần trừ)
      selected_rewards = JSON selectedRewards[]
         ↓
[PHP - save_order()]
  ĐỔI ĐIỂM TRƯỚC khi tích điểm:
    tgs_loyalty_redeem_points($user_wp_id, $points_used, [...])
      TGS_Loyalty_DB::deduct_balance($user_wp_id, $points)
        START TRANSACTION
          UPDATE wallet SET balance -= $points, total_spent += $points
          INSERT wallet_log (transaction_type='debit')
        COMMIT
      INSERT global_loyalty_log (log_type='redeem')
      return { success, points_redeemed, points_before, points_after }
    
    Nếu có reward type=6 (voucher):
      tgs_loyalty_issue_reward_vouchers($user_wp_id, $selected_rewards, [...])
        → Tạo mã voucher với prefix
        → update_option('tgs_loyalty_issued_vouchers', $registry)
           ⚠️ Lưu toàn bộ registry vào wp_options — không thread-safe ở quy mô lớn
        → return issued_vouchers[]
    
    Nếu có reward type=5 (product):
      → Sản phẩm tặng đã được thêm vào products_data với price=0 TRƯỚC khi tạo đơn
        → Kho tự trừ khi tạo sale
```

---

## 5. Cách POS đọc Số dư Điểm Khách hàng (3 path)

### Path 1 — Khi tìm kiếm khách hàng
```
JS: posCustomerMethods.searchCustomer()
  POST action=tgs_pos_search_customer
         ↓
PHP: TGS_POS_Ajax_Order::search_customer()
  → SELECT local_ledger_person.*, wallet.balance AS loyalty_points
       FROM local_ledger_person
       LEFT JOIN {base_prefix}wallet ON wallet.user_id = local_ledger_person.user_wp_id
  → Trả về loyalty_points trong mỗi kết quả
JS: lưu vào selectedCustomer.loyalty_points  ← ĐÂY LÀ SỐ DƯ TẠI THỜI ĐIỂM TÌM KIẾM
```

### Path 2 — Khi chọn khách hàng (real-time refresh)
```
JS: posPromotionMethods.fetchCustomerPoints()
  POST action=tgs_pos_get_customer_points
    customer_id = tgs_person_id
         ↓
PHP: TGS_POS_Ajax_Order::get_customer_points()
  SELECT user_wp_id FROM local_ledger_person WHERE id = $customer_id
  SELECT balance FROM {base_prefix}wallet WHERE user_id = $user_wp_id
  return { points: balance }
         ↓
JS: this.customerPoints = parseInt(data.data.points)
    Hiển thị lên màn hình POS
```

### Path 3 — Khi mở Rewards Modal
```
check_promotions với include_rewards=true
  → rewards_data.customer_points = balance tươi từ wallet
JS: this.customerPoints = parseInt(data.data.rewards_data.customer_points)
```

---

## 6. Tiers (Hạng thành viên)

Dựa trên `wallet.total_earned` (tổng điểm tích lũy từ trước đến nay, không bao giờ giảm):

| Hạng | `loyalty_tier` key | Điều kiện (total_earned) | Hệ số nhân |
|---|---|---|---|
| Thành viên | `member` | 0+ | ×1.0 |
| Bạc | `silver` | 1.000+ | ×1.2 |
| Vàng | `gold` | 5.000+ | ×1.5 |
| VIP | `vip` | 20.000+ | ×2.0 |

Cài đặt trong WP option `tgs_loyalty_tiers` (có thể thay đổi qua admin).
Tier được cập nhật sau mỗi lần tích điểm tại `wp_usermeta.loyalty_tier`.

---

## 7. Tham chiếu AJAX Actions

| AJAX Action | PHP Handler | Gọi từ | Mục đích |
|---|---|---|---|
| `tgs_pos_save_order` | `TGS_POS_Ajax_Order::save_order()` | `pos-order.js::saveOrderToServer()` | Tạo đơn + tích điểm + trừ điểm |
| `tgs_pos_check_promotions` | `TGS_POS_Ajax_Order::check_promotions()` | `pos-promotion.js::checkPromotions()` | Preview điểm sẽ tích + danh sách khuyến mãi |
| `tgs_pos_check_promotions` (include_rewards=true) | `TGS_POS_Ajax_Order::check_promotions()` | `pos-promotion.js::openRewardsModal()` | Catalog phần thưởng + số dư điểm |
| `tgs_pos_apply_coupon` | `TGS_POS_Ajax_Order::apply_coupon()` | `pos-promotion.js::applyCoupon()` | Áp voucher loyalty hoặc mã giảm giá |
| `tgs_pos_get_customer_points` | `TGS_POS_Ajax_Order::get_customer_points()` | `pos-promotion.js::fetchCustomerPoints()` | Đọc số dư điểm real-time |
| `tgs_pos_search_customer` | `TGS_POS_Ajax_Order::search_customer()` | `pos-customer.js` | Tìm khách + kèm loyalty_points |
| `tgs_pos_save_customer` | `TGS_POS_Ajax_Order::save_customer()` | `pos-customer.js` | Tạo khách + link WP user |
| `tgs_pos_get_customer_orders` | `TGS_POS_Ajax_Order::get_customer_orders()` | `pos-customer.js` | Lịch sử đơn + số dư điểm |
| `wp_ajax_tgs_loyalty_*` | `TGS_Loyalty_Ajax::*` | Admin UI (loyalty.js) | CRUD chính sách, quản lý thành viên, điều chỉnh điểm |

---

## 8. Bugs đã sửa & Bugs còn tồn tại

### ✅ Đã sửa

| # | Vị trí | Mô tả |
|---|---|---|
| BUG#1 | `pos-promotion.js` lines 24 & 490 | Earn preview dùng `getTotalPrice()` (trước giảm giá) thay vì `getTotalWithTax()` (sau giảm giá) → hiển thị điểm sai với khách |
| BUG#2 | `class-tgs-pos-ajax-order.php` lines 108 & 723 | Fallback `TGS_PERSON_TYPE_CUSTOMER = 1` trong `save_order` và `save_customer` nhưng hằng số thực tế = `2` → khách được lưu type=1, tìm kiếm theo type=2, không thấy |
| BUG#3 | `class-tgs-pos-ajax-order.php` loyalty block | `user_wp_id` = NULL bị bỏ qua hoàn toàn, không có cảnh báo về frontend → cashier không biết điểm không được ghi |

### ⚠️ Còn tồn tại (chưa sửa)

| # | Mức độ | Vị trí | Mô tả |
|---|---|---|---|
| BUG#4 | 🟡 | `class-loyalty-engine.php` `save_voucher_registry()` | Voucher registry lưu toàn bộ vào `wp_options` — race condition khi 2 giao dịch đồng thời, không thread-safe |
| BUG#5 | 🟡 | `class-tgs-pos-ajax-customer.php` | Đăng ký trùng 4 AJAX action với Order class — nếu file này được load sẽ gây xung đột |
| BUG#6 | 🟢 | `pos-cart.js` | Hàm `getTotalWithTax()` đặt tên nhầm — thực ra là "total after discount", không có tính thuế thêm |

---

## 9. Identity Bridge — Điểm quan trọng cần nhớ

```
WP User (wp_users)
    │  user_id
    ├──→ {base_prefix}wallet.user_id       (điểm toàn hệ thống)
    └──→ local_ledger_person.user_wp_id    (khách hàng tại cửa hàng)
                │
                └──→ local_ledger_person_id  ← đây là tgs_person_id mà POS dùng

Nếu local_ledger_person.user_wp_id IS NULL:
  → Khách hàng KHÔNG tích được điểm
  → Khách hàng KHÔNG đổi được quà
  → TGS tự động tạo WP user khi save_customer() nếu TGS_WP_User_Helper hoạt động
```

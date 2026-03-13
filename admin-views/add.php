<?php if (!defined('ABSPATH')) exit; ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Thêm chương trình
    </h4>

    <div class="row">
        <!-- ── CỘT TRÁI: FORM CHÍNH ── -->
        <div class="col-md-8">
            <!-- Thông tin chung -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin chung</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã chương trình <span class="text-primary">*</span></label>
                            <input type="text" id="policy-code" class="form-control" placeholder="VD: TD-2026-001">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Loại <span class="text-primary">*</span></label>
                            <select id="policy-type" class="form-select">
                                <option value="1">1 — Tích theo số tiền</option>
                                <option value="2">2 — Tích theo sản phẩm</option>
                                <option value="3">3 — Nhân hệ số điểm</option>
                                <option value="4">4 — Bonus sự kiện</option>
                                <option value="5">5 — Đổi quà bằng điểm</option>
                                <option value="6">6 — Đổi voucher bằng điểm</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Trạng thái</label>
                            <select id="policy-status" class="form-select">
                                <option value="0">Nháp</option>
                                <option value="1">Hoạt động</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tên chương trình <span class="text-primary">*</span></label>
                            <input type="text" id="policy-title" class="form-control" placeholder="VD: Tích 1 điểm mỗi 1.000₫">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="date" id="policy-start" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="date" id="policy-end" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ưu tiên</label>
                            <input type="number" id="policy-priority" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="policy-auto" checked>
                                <label class="form-check-label" for="policy-auto">Tự động áp dụng</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cấu hình tích điểm -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Cấu hình tích điểm</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Số điểm tích</label>
                            <input type="number" id="earn-rate" class="form-control" value="1" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Mỗi (VND)</label>
                            <input type="number" id="earn-unit" class="form-control" value="1000" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Giá trị đổi (₫/điểm)</label>
                            <input type="number" id="redeem-rate" class="form-control" value="1" min="0" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Điểm hết hạn (ngày)</label>
                            <input type="number" id="point-expiry" class="form-control" value="0" min="0" placeholder="0 = không">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Đơn hàng tối thiểu (₫)</label>
                            <input type="number" id="min-order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Điểm tối đa / đơn</label>
                            <input type="number" id="max-points" class="form-control" value="0" min="0" placeholder="0 = không giới hạn">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quy tắc chi tiết (dynamic theo type) -->
            <div class="card mb-4" id="rules-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quy tắc chi tiết</h5>
                    <button class="btn btn-sm btn-outline-primary" id="btn-add-rule"><i class="bx bx-plus me-1"></i> Thêm dòng</button>
                </div>
                <div class="card-body" id="rules-panel">
                    <!-- Rendered by JS -->
                </div>
            </div>
        </div>

        <!-- ── CỘT PHẢI: SCOPE + ACTIONS ── -->
        <div class="col-md-4">
            <!-- Scope -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Phạm vi áp dụng</h5>
                </div>
                <div class="card-body" id="scope-container">
                    <p class="text-muted small">Để trống = áp dụng tất cả shop.</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-body d-grid gap-2">
                    <button class="btn btn-primary" id="btn-save">
                        <i class="bx bx-save me-1"></i> Lưu chương trình
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-policies'); ?>"
                        class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i> Quay lại danh sách
                    </a>
                </div>
            </div>

            <!-- Ghi chú -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ghi chú</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small text-muted mb-0">
                        <li class="mb-2"><strong>Loại 1 — Theo số tiền:</strong> X điểm mỗi Y VND. Cấu hình ở "Cấu hình tích điểm".</li>
                        <li class="mb-2"><strong>Loại 2 — Theo sản phẩm:</strong> Thêm dòng quy tắc với SKU + điểm cố định.</li>
                        <li class="mb-2"><strong>Loại 3 — Nhân hệ số:</strong> Nhân X lần điểm cơ bản (VD: x2, x3) trong thời gian event.</li>
                        <li class="mb-2"><strong>Loại 4 — Bonus:</strong> Tặng điểm bonus khi đơn hàng đạt mức nhất định.</li>
                        <li><strong>Loại 5 — Đổi quà:</strong> Khai báo quà, số điểm cần đổi và tier tối thiểu.</li>
                        <li><strong>Loại 6 — Đổi voucher:</strong> Khai báo voucher được cấp sau khi khách redeem điểm tại POS.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $reward_tiers = class_exists('TGS_Loyalty_DB') ? TGS_Loyalty_DB::get_tier_definitions() : ['member' => ['name' => 'Thành viên']]; ?>

<script>
    jQuery(function($) {
        const $type = $('#policy-type');
        const $panel = $('#rules-panel');
        const $rulesCard = $('#rules-card');
        const rewardTiers = <?php echo wp_json_encode($reward_tiers); ?>;

        function buildRewardTierOptions(selected) {
            return Object.entries(rewardTiers).map(function(entry) {
                const key = entry[0];
                const tier = entry[1] || {};
                return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + (tier.name || key) + '</option>';
            }).join('');
        }

        // ── Render rules panel theo loại ──
        function renderRulesPanel() {
            const type = parseInt($type.val());
            $panel.empty();

            if (type === 1) {
                // Loại 1: Không cần rules phụ — dùng earn_rate / earn_unit
                $rulesCard.hide();
                return;
            }

            $rulesCard.show();

            if (type === 2) {
                // Loại 2: SKU + điểm
                $panel.append('<div id="rules-rows"></div>');
                addProductRule();
            } else if (type === 3) {
                // Loại 3: Hệ số nhân
                $panel.html(
                    '<div class="row g-3">' +
                    '<div class="col-md-6"><label class="form-label">Hệ số nhân</label>' +
                    '<input type="number" id="rule-multiplier" class="form-control" value="2" min="1" step="0.1"></div>' +
                    '<div class="col-md-6"><label class="form-label">Mô tả</label>' +
                    '<input type="text" id="rule-multiplier-desc" class="form-control" placeholder="VD: x2 điểm Tết Nguyên Đán"></div>' +
                    '</div>'
                );
            } else if (type === 4) {
                // Loại 4: Bonus theo mức
                $panel.append('<div id="rules-rows"></div>');
                addBonusRule();
            } else if (type === 5) {
                $panel.append('<div id="rules-rows"></div>');
                addRewardRule();
            } else if (type === 6) {
                $panel.append('<div id="rules-rows"></div>');
                addVoucherRule();
            }
        }

        function addProductRule() {
            const idx = $('#rules-rows .rule-row').length;
            $('#rules-rows').append(
                '<div class="row g-2 mb-2 rule-row align-items-end">' +
                '<div class="col-md-5"><label class="form-label">SKU</label>' +
                '<input type="text" class="form-control rule-sku" placeholder="Tìm SKU hoặc tên SP..." autocomplete="off"></div>' +
                '<div class="col-md-4"><label class="form-label">Điểm / SP</label>' +
                '<input type="number" class="form-control rule-points" value="10" min="0"></div>' +
                '<div class="col-md-3"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div>' +
                '</div>'
            );
        }

        function addBonusRule() {
            $('#rules-rows').append(
                '<div class="row g-2 mb-2 rule-row align-items-end">' +
                '<div class="col-md-5"><label class="form-label">Đơn tối thiểu (₫)</label>' +
                '<input type="number" class="form-control rule-min-amount" value="500000" min="0"></div>' +
                '<div class="col-md-4"><label class="form-label">Điểm bonus</label>' +
                '<input type="number" class="form-control rule-bonus-points" value="100" min="0"></div>' +
                '<div class="col-md-3"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div>' +
                '</div>'
            );
        }

        function addRewardRule(reward) {
            reward = reward || {};
            $('#rules-rows').append(
                '<div class="border rounded p-3 mb-3 rule-row reward-row">' +
                '<div class="row g-2 align-items-end">' +
                '<div class="col-md-3"><label class="form-label">Mã quà</label><input type="text" class="form-control rule-reward-code" value="' + (reward.reward_code || '') + '" placeholder="gift_mug"></div>' +
                '<div class="col-md-3"><label class="form-label">Product ID</label><input type="number" class="form-control rule-reward-product-id" value="' + Number(reward.product_id || 0) + '" min="0"></div>' +
                '<div class="col-md-3"><label class="form-label">SKU</label><input type="text" class="form-control rule-reward-sku" value="' + (reward.sku || '') + '" placeholder="Tìm SKU..." autocomplete="off"></div>' +
                '<div class="col-md-3"><label class="form-label">Tên quà</label><input type="text" class="form-control rule-reward-name" value="' + (reward.name || '') + '" placeholder="Ly sứ"></div>' +
                '<div class="col-md-2"><label class="form-label">Số lượng</label><input type="number" class="form-control rule-reward-qty" value="' + Number(reward.quantity || 1) + '" min="1"></div>' +
                '<div class="col-md-3"><label class="form-label">Điểm cần đổi</label><input type="number" class="form-control rule-reward-points" value="' + Number(reward.points_required || 0) + '" min="1"></div>' +
                '<div class="col-md-3"><label class="form-label">Tier tối thiểu</label><select class="form-select rule-reward-min-tier">' + buildRewardTierOptions(reward.min_tier || 'member') + '</select></div>' +
                '<div class="col-md-2"><label class="form-label">Tracking</label><select class="form-select rule-reward-tracking"><option value="0">Không</option><option value="1">Có</option></select></div>' +
                '<div class="col-md-2"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div>' +
                '</div>' +
                '</div>'
            );
            const $row = $('#rules-rows .reward-row').last();
            $row.find('.rule-reward-tracking').val(String(reward.is_tracking ? 1 : 0));
        }

        function addVoucherRule(reward) {
            reward = reward || {};
            $('#rules-rows').append(
                '<div class="border rounded p-3 mb-3 rule-row voucher-row">' +
                '<div class="row g-2 align-items-end">' +
                '<div class="col-md-3"><label class="form-label">Mã voucher</label><input type="text" class="form-control rule-voucher-code" value="' + (reward.reward_code || '') + '" placeholder="vip10"></div>' +
                '<div class="col-md-3"><label class="form-label">Tên voucher</label><input type="text" class="form-control rule-voucher-name" value="' + (reward.name || '') + '" placeholder="Voucher 10%"></div>' +
                '<div class="col-md-2"><label class="form-label">Số lượng</label><input type="number" class="form-control rule-voucher-qty" value="' + Number(reward.quantity || 1) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Điểm đổi</label><input type="number" class="form-control rule-voucher-points" value="' + Number(reward.points_required || 0) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Tier tối thiểu</label><select class="form-select rule-voucher-min-tier">' + buildRewardTierOptions(reward.min_tier || 'member') + '</select></div>' +
                '<div class="col-md-3"><label class="form-label">Tiền tố code</label><input type="text" class="form-control rule-voucher-prefix" value="' + (reward.voucher_prefix || '') + '" placeholder="LP-VIP-"></div>' +
                '<div class="col-md-3"><label class="form-label">Loại giảm</label><select class="form-select rule-voucher-discount-type"><option value="fixed">Giảm tiền</option><option value="percent">Giảm %</option></select></div>' +
                '<div class="col-md-2"><label class="form-label">Giá trị</label><input type="number" class="form-control rule-voucher-discount-value" value="' + Number(reward.discount_value || 0) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Giảm tối đa</label><input type="number" class="form-control rule-voucher-max-discount" value="' + Number(reward.max_discount || 0) + '" min="0"></div>' +
                '<div class="col-md-2"><label class="form-label">Đơn tối thiểu</label><input type="number" class="form-control rule-voucher-min-order" value="' + Number(reward.min_order_amount || 0) + '" min="0"></div>' +
                '<div class="col-md-2"><label class="form-label">Hạn dùng (ngày)</label><input type="number" class="form-control rule-voucher-expiry" value="' + Number(reward.expires_in_days || 0) + '" min="0"></div>' +
                '<div class="col-md-1"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div>' +
                '</div>' +
                '</div>'
            );
            const $row = $('#rules-rows .voucher-row').last();
            $row.find('.rule-voucher-discount-type').val(reward.discount_type || 'fixed');
        }

        $type.on('change', renderRulesPanel);
        renderRulesPanel();

        $('#btn-add-rule').on('click', function() {
            const type = parseInt($type.val());
            if (type === 2) addProductRule();
            else if (type === 4) addBonusRule();
            else if (type === 5) addRewardRule();
            else if (type === 6) addVoucherRule();
        });

        $(document).on('click', '.btn-remove-rule', function() {
            $(this).closest('.rule-row').remove();
        });

        // ── SKU AUTOCOMPLETE ──
        var $skuDropdown = $('<div class="list-group shadow position-fixed" style="z-index:9999;max-height:220px;overflow-y:auto;display:none"></div>').appendTo('body');
        var _skuTimer = null;
        var skuSelectors = '.rule-sku, .rule-reward-sku';

        $(document).on('input', skuSelectors, function() {
            var $inp = $(this);
            var q = $inp.val().trim();
            clearTimeout(_skuTimer);
            if (!q || q.length < 2) {
                $skuDropdown.hide();
                return;
            }
            _skuTimer = setTimeout(function() {
                $.post(tgsLoyalty.ajaxUrl, {
                    action: 'tgs_loyalty_get_products',
                    nonce: tgsLoyalty.nonce,
                    search: q
                }, function(r) {
                    if (!r.success || !r.data.length) {
                        $skuDropdown.hide();
                        return;
                    }
                    var html = '';
                    r.data.forEach(function(p) {
                        html += '<a href="#" class="list-group-item list-group-item-action py-1 px-2 sku-pick"' +
                            ' data-sku="' + p.local_product_sku + '" data-name="' + (p.local_product_name || '') + '">' +
                            '<code class="me-1">' + p.local_product_sku + '</code> ' + p.local_product_name +
                            ' <small class="text-muted">(' + Number(p.local_product_price_after_tax || 0).toLocaleString('vi-VN') + 'đ)</small></a>';
                    });
                    $skuDropdown.html(html);
                    var off = $inp.offset();
                    $skuDropdown.css({
                        top: off.top + $inp.outerHeight(),
                        left: off.left,
                        width: Math.max($inp.outerWidth(), 320)
                    }).show();
                    $skuDropdown.data('target', $inp);
                });
            }, 300);
        });

        $(document).on('click', '.sku-pick', function(e) {
            e.preventDefault();
            var $inp = $skuDropdown.data('target');
            if ($inp) {
                $inp.val($(this).data('sku'));
                $inp.attr('title', $(this).data('name'));
                // Auto-fill reward name if empty
                var $row = $inp.closest('.rule-row');
                var $nameField = $row.find('.rule-reward-name');
                if ($nameField.length && !$nameField.val().trim()) {
                    $nameField.val($(this).data('name'));
                }
            }
            $skuDropdown.hide();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.list-group, ' + skuSelectors).length) {
                $skuDropdown.hide();
            }
        });

        // ── Build rules JSON ──
        function buildRules() {
            const type = parseInt($type.val());

            if (type === 1) return '[]';

            if (type === 2) {
                const rules = [];
                $('#rules-rows .rule-row').each(function() {
                    const sku = $(this).find('.rule-sku').val().trim();
                    const pts = parseFloat($(this).find('.rule-points').val()) || 0;
                    if (sku && pts > 0) rules.push({
                        sku: sku,
                        points: pts
                    });
                });
                return JSON.stringify(rules);
            }

            if (type === 3) {
                return JSON.stringify({
                    multiplier: parseFloat($('#rule-multiplier').val()) || 2
                });
            }

            if (type === 4) {
                const rules = [];
                $('#rules-rows .rule-row').each(function() {
                    const min = parseFloat($(this).find('.rule-min-amount').val()) || 0;
                    const pts = parseFloat($(this).find('.rule-bonus-points').val()) || 0;
                    if (pts > 0) rules.push({
                        min_amount: min,
                        bonus_points: pts
                    });
                });
                return JSON.stringify(rules);
            }

            if (type === 5) {
                const rewards = [];
                $('#rules-rows .reward-row').each(function() {
                    const pointsRequired = parseInt($(this).find('.rule-reward-points').val(), 10) || 0;
                    const productId = parseInt($(this).find('.rule-reward-product-id').val(), 10) || 0;
                    const name = $(this).find('.rule-reward-name').val().trim();
                    if (pointsRequired <= 0 || (!productId && !name)) {
                        return;
                    }

                    rewards.push({
                        reward_code: $(this).find('.rule-reward-code').val().trim(),
                        product_id: productId,
                        sku: $(this).find('.rule-reward-sku').val().trim(),
                        name: name,
                        quantity: parseInt($(this).find('.rule-reward-qty').val(), 10) || 1,
                        points_required: pointsRequired,
                        min_tier: $(this).find('.rule-reward-min-tier').val() || 'member',
                        is_tracking: parseInt($(this).find('.rule-reward-tracking').val(), 10) || 0,
                        status: 1
                    });
                });
                return JSON.stringify({
                    rewards: rewards
                });
            }

            if (type === 6) {
                const rewards = [];
                $('#rules-rows .voucher-row').each(function() {
                    const pointsRequired = parseInt($(this).find('.rule-voucher-points').val(), 10) || 0;
                    const name = $(this).find('.rule-voucher-name').val().trim();
                    const discountValue = parseFloat($(this).find('.rule-voucher-discount-value').val()) || 0;
                    if (pointsRequired <= 0 || !name || discountValue <= 0) {
                        return;
                    }

                    rewards.push({
                        reward_code: $(this).find('.rule-voucher-code').val().trim(),
                        name: name,
                        quantity: parseInt($(this).find('.rule-voucher-qty').val(), 10) || 1,
                        points_required: pointsRequired,
                        min_tier: $(this).find('.rule-voucher-min-tier').val() || 'member',
                        voucher_prefix: $(this).find('.rule-voucher-prefix').val().trim(),
                        discount_type: $(this).find('.rule-voucher-discount-type').val() || 'fixed',
                        discount_value: discountValue,
                        max_discount: parseFloat($(this).find('.rule-voucher-max-discount').val()) || 0,
                        min_order_amount: parseFloat($(this).find('.rule-voucher-min-order').val()) || 0,
                        expires_in_days: parseInt($(this).find('.rule-voucher-expiry').val(), 10) || 0,
                        status: 1
                    });
                });
                return JSON.stringify({
                    rewards: rewards
                });
            }

            return '[]';
        }

        // ── Scope Selector ──
        let scopeBlogIds = [];
        let scopeOrgInfo = {};
        if (typeof TgsScopeSelector !== 'undefined') {
            TgsScopeSelector.init({
                container: '#scope-container',
                ajaxUrl: tgsLoyalty.ajaxUrl,
                nonce: tgsLoyalty.nonce,
                ajaxAction: 'tgs_loyalty_get_scope_data',
                onChange: function(blogIds, orgInfo) {
                    scopeBlogIds = blogIds || [];
                    scopeOrgInfo = orgInfo || {};
                }
            });
        }

        // ── SAVE ──
        $('#btn-save').on('click', function() {
            const code = $('#policy-code').val().trim();
            const title = $('#policy-title').val().trim();
            if (!code) return alert('Vui lòng nhập mã chương trình.');
            if (!title) return alert('Vui lòng nhập tên chương trình.');

            const startDate = $('#policy-start').val();
            const endDate = $('#policy-end').val();

            const payload = {
                action: 'tgs_loyalty_policy_save',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_code: code,
                loyalty_policy_title: title,
                loyalty_policy_type: parseInt($type.val()),
                loyalty_policy_status: parseInt($('#policy-status').val()),
                loyalty_policy_priority: parseInt($('#policy-priority').val()) || 0,
                loyalty_policy_start_date: startDate ? Math.floor(new Date(startDate).getTime() / 1000) : 0,
                loyalty_policy_end_date: endDate ? Math.floor(new Date(endDate + 'T23:59:59').getTime() / 1000) : 0,
                loyalty_rules: buildRules(),
                earn_rate: parseFloat($('#earn-rate').val()) || 1,
                earn_unit: parseFloat($('#earn-unit').val()) || 1000,
                redeem_rate: parseFloat($('#redeem-rate').val()) || 1,
                min_order_amount: parseFloat($('#min-order').val()) || 0,
                max_points_per_order: parseInt($('#max-points').val()) || 0,
                point_expiry_days: parseInt($('#point-expiry').val()) || 0,
                auto_apply: $('#policy-auto').is(':checked') ? 1 : 0,
                product_skus: '[]',
                apply_to_blog_ids: JSON.stringify(scopeBlogIds),
                apply_to_org_level: JSON.stringify(scopeOrgInfo),
            };

            const $btn = $(this).prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Đang lưu...');

            $.post(tgsLoyalty.ajaxUrl, payload, function(res) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Lưu chương trình');
                if (res.success) {
                    alert(res.data.message);
                    window.location.href = '<?php echo admin_url("admin.php?page=tgs-shop-management&view=loyalty-policy-detail"); ?>&id=' + res.data.loyalty_policy_id;
                } else {
                    alert(res.data?.message || 'Có lỗi xảy ra.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Lưu chương trình');
                alert('Lỗi kết nối.');
            });
        });
    });
</script>
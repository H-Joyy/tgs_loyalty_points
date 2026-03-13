<?php if (!defined('ABSPATH')) exit; ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Chi tiết chương trình
    </h4>

    <div class="row">
        <!-- ── CỘT TRÁI: FORM ── -->
        <div class="col-md-8">
            <!-- Thông tin chung -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin chung</h5>
                    <span class="badge bg-primary" id="policy-id-badge"></span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Mã chương trình <span class="text-primary">*</span></label>
                            <input type="text" id="policy-code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Loại</label>
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
                                <option value="2">Hết hạn</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tên chương trình <span class="text-primary">*</span></label>
                            <input type="text" id="policy-title" class="form-control">
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
                            <input type="number" id="point-expiry" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Đơn hàng tối thiểu (₫)</label>
                            <input type="number" id="min-order" class="form-control" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Điểm tối đa / đơn</label>
                            <input type="number" id="max-points" class="form-control" value="0" min="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quy tắc chi tiết -->
            <div class="card mb-4" id="rules-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quy tắc chi tiết</h5>
                    <button class="btn btn-sm btn-outline-primary" id="btn-add-rule"><i class="bx bx-plus me-1"></i> Thêm dòng</button>
                </div>
                <div class="card-body" id="rules-panel"></div>
            </div>

            <!-- Thông tin hệ thống -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Thông tin hệ thống</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2 small text-muted">
                        <div class="col-md-4">ID: <strong id="sys-id">—</strong></div>
                        <div class="col-md-4">Tạo bởi: <strong id="sys-user">—</strong></div>
                        <div class="col-md-4">Blog: <strong id="sys-blog">—</strong></div>
                        <div class="col-md-6">Ngày tạo: <strong id="sys-created">—</strong></div>
                        <div class="col-md-6">Cập nhật: <strong id="sys-updated">—</strong></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── CỘT PHẢI ── -->
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
                    <button class="btn btn-primary" id="btn-update">
                        <i class="bx bx-save me-1"></i> Cập nhật
                    </button>
                    <button class="btn btn-outline-primary" id="btn-clone">
                        <i class="bx bx-copy me-1"></i> Sao chép
                    </button>
                    <button class="btn btn-outline-primary" id="btn-delete">
                        <i class="bx bx-trash me-1"></i> Xóa
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-policies'); ?>"
                        class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back me-1"></i> Quay lại
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $reward_tiers = class_exists('TGS_Loyalty_DB') ? TGS_Loyalty_DB::get_tier_definitions() : ['member' => ['name' => 'Thành viên']]; ?>

<script>
    jQuery(function($) {
        const params = new URLSearchParams(window.location.search);
        const policyId = parseInt(params.get('id')) || 0;
        if (!policyId) {
            alert('Không tìm thấy ID chương trình.');
            return;
        }

        const $type = $('#policy-type');
        const $panel = $('#rules-panel');
        const rewardTiers = <?php echo wp_json_encode($reward_tiers); ?>;

        function buildRewardTierOptions(selected) {
            return Object.entries(rewardTiers).map(function(entry) {
                const key = entry[0];
                const tier = entry[1] || {};
                return '<option value="' + key + '"' + (key === selected ? ' selected' : '') + '>' + (tier.name || key) + '</option>';
            }).join('');
        }

        // ── Rules panel render ──
        function addProductRule(sku, pts) {
            $('#rules-rows').append(
                '<div class="row g-2 mb-2 rule-row align-items-end">' +
                '<div class="col-md-5"><label class="form-label">SKU</label>' +
                '<input type="text" class="form-control rule-sku" value="' + (sku || '') + '" placeholder="Tìm SKU hoặc tên SP..." autocomplete="off"></div>' +
                '<div class="col-md-4"><label class="form-label">Điểm / SP</label>' +
                '<input type="number" class="form-control rule-points" value="' + (pts || 10) + '" min="0"></div>' +
                '<div class="col-md-3"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div></div>'
            );
        }

        function addBonusRule(minAmt, bonusPts) {
            $('#rules-rows').append(
                '<div class="row g-2 mb-2 rule-row align-items-end">' +
                '<div class="col-md-5"><label class="form-label">Đơn tối thiểu (₫)</label>' +
                '<input type="number" class="form-control rule-min-amount" value="' + (minAmt || 500000) + '" min="0"></div>' +
                '<div class="col-md-4"><label class="form-label">Điểm bonus</label>' +
                '<input type="number" class="form-control rule-bonus-points" value="' + (bonusPts || 100) + '" min="0"></div>' +
                '<div class="col-md-3"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div></div>'
            );
        }

        function addRewardRule(reward) {
            reward = reward || {};
            $('#rules-rows').append(
                '<div class="border rounded p-3 mb-3 rule-row reward-row">' +
                '<div class="row g-2 align-items-end">' +
                '<div class="col-md-3"><label class="form-label">Mã quà</label><input type="text" class="form-control rule-reward-code" value="' + (reward.reward_code || '') + '"></div>' +
                '<div class="col-md-3"><label class="form-label">Product ID</label><input type="number" class="form-control rule-reward-product-id" value="' + Number(reward.product_id || 0) + '" min="0"></div>' +
                '<div class="col-md-3"><label class="form-label">SKU</label><input type="text" class="form-control rule-reward-sku" value="' + (reward.sku || '') + '" placeholder="Tìm SKU..." autocomplete="off"></div>' +
                '<div class="col-md-3"><label class="form-label">Tên quà</label><input type="text" class="form-control rule-reward-name" value="' + (reward.name || '') + '"></div>' +
                '<div class="col-md-2"><label class="form-label">Số lượng</label><input type="number" class="form-control rule-reward-qty" value="' + Number(reward.quantity || 1) + '" min="1"></div>' +
                '<div class="col-md-3"><label class="form-label">Điểm cần đổi</label><input type="number" class="form-control rule-reward-points" value="' + Number(reward.points_required || 0) + '" min="1"></div>' +
                '<div class="col-md-3"><label class="form-label">Tier tối thiểu</label><select class="form-select rule-reward-min-tier">' + buildRewardTierOptions(reward.min_tier || 'member') + '</select></div>' +
                '<div class="col-md-2"><label class="form-label">Tracking</label><select class="form-select rule-reward-tracking"><option value="0">Không</option><option value="1">Có</option></select></div>' +
                '<div class="col-md-2"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div></div>' +
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
                '<div class="col-md-3"><label class="form-label">Mã voucher</label><input type="text" class="form-control rule-voucher-code" value="' + (reward.reward_code || '') + '"></div>' +
                '<div class="col-md-3"><label class="form-label">Tên voucher</label><input type="text" class="form-control rule-voucher-name" value="' + (reward.name || '') + '"></div>' +
                '<div class="col-md-2"><label class="form-label">Số lượng</label><input type="number" class="form-control rule-voucher-qty" value="' + Number(reward.quantity || 1) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Điểm đổi</label><input type="number" class="form-control rule-voucher-points" value="' + Number(reward.points_required || 0) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Tier tối thiểu</label><select class="form-select rule-voucher-min-tier">' + buildRewardTierOptions(reward.min_tier || 'member') + '</select></div>' +
                '<div class="col-md-3"><label class="form-label">Tiền tố code</label><input type="text" class="form-control rule-voucher-prefix" value="' + (reward.voucher_prefix || '') + '"></div>' +
                '<div class="col-md-3"><label class="form-label">Loại giảm</label><select class="form-select rule-voucher-discount-type"><option value="fixed">Giảm tiền</option><option value="percent">Giảm %</option></select></div>' +
                '<div class="col-md-2"><label class="form-label">Giá trị</label><input type="number" class="form-control rule-voucher-discount-value" value="' + Number(reward.discount_value || 0) + '" min="1"></div>' +
                '<div class="col-md-2"><label class="form-label">Giảm tối đa</label><input type="number" class="form-control rule-voucher-max-discount" value="' + Number(reward.max_discount || 0) + '" min="0"></div>' +
                '<div class="col-md-2"><label class="form-label">Đơn tối thiểu</label><input type="number" class="form-control rule-voucher-min-order" value="' + Number(reward.min_order_amount || 0) + '" min="0"></div>' +
                '<div class="col-md-2"><label class="form-label">Hạn dùng (ngày)</label><input type="number" class="form-control rule-voucher-expiry" value="' + Number(reward.expires_in_days || 0) + '" min="0"></div>' +
                '<div class="col-md-1"><button class="btn btn-sm btn-outline-primary btn-remove-rule mt-4"><i class="bx bx-trash"></i></button></div></div>' +
                '</div>'
            );
            const $row = $('#rules-rows .voucher-row').last();
            $row.find('.rule-voucher-discount-type').val(reward.discount_type || 'fixed');
        }

        function renderRulesPanel(type, rules) {
            $panel.empty();
            type = parseInt(type);

            if (type === 1) {
                $('#rules-card').hide();
                return;
            }
            $('#rules-card').show();

            if (type === 2) {
                $panel.append('<div id="rules-rows"></div>');
                if (Array.isArray(rules) && rules.length) {
                    rules.forEach(r => addProductRule(r.sku, r.points));
                } else {
                    addProductRule();
                }
            } else if (type === 3) {
                const mult = (rules && rules.multiplier) ? rules.multiplier : 2;
                $panel.html(
                    '<div class="row g-3"><div class="col-md-6"><label class="form-label">Hệ số nhân</label>' +
                    '<input type="number" id="rule-multiplier" class="form-control" value="' + mult + '" min="1" step="0.1"></div></div>'
                );
            } else if (type === 4) {
                $panel.append('<div id="rules-rows"></div>');
                if (Array.isArray(rules) && rules.length) {
                    rules.forEach(r => addBonusRule(r.min_amount, r.bonus_points));
                } else {
                    addBonusRule();
                }
            } else if (type === 5) {
                $panel.append('<div id="rules-rows"></div>');
                const rewardRows = Array.isArray(rules?.rewards) ? rules.rewards : [];
                if (rewardRows.length) {
                    rewardRows.forEach(r => addRewardRule(r));
                } else {
                    addRewardRule();
                }
            } else if (type === 6) {
                $panel.append('<div id="rules-rows"></div>');
                const rewardRows = Array.isArray(rules?.rewards) ? rules.rewards : [];
                if (rewardRows.length) {
                    rewardRows.forEach(r => addVoucherRule(r));
                } else {
                    addVoucherRule();
                }
            }
        }

        $type.on('change', function() {
            renderRulesPanel($(this).val(), []);
        });

        $('#btn-add-rule').on('click', function() {
            const t = parseInt($type.val());
            if (t === 2) addProductRule();
            else if (t === 4) addBonusRule();
            else if (t === 5) addRewardRule();
            else if (t === 6) addVoucherRule();
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

        function buildRules() {
            const type = parseInt($type.val());
            if (type === 1) return '[]';
            if (type === 2) {
                const rules = [];
                $('#rules-rows .rule-row').each(function() {
                    const sku = $(this).find('.rule-sku').val().trim();
                    const pts = parseFloat($(this).find('.rule-points').val()) || 0;
                    if (sku && pts > 0) rules.push({
                        sku,
                        points: pts
                    });
                });
                return JSON.stringify(rules);
            }
            if (type === 3) return JSON.stringify({
                multiplier: parseFloat($('#rule-multiplier').val()) || 2
            });
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

        // ── Scope ──
        let scopeBlogIds = [];
        let scopeOrgInfo = {};

        // ── Load policy ──
        function loadPolicy() {
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_get',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: policyId
            }, function(res) {
                if (!res.success) {
                    alert(res.data?.message || 'Lỗi');
                    return;
                }
                const p = res.data;

                $('#policy-id-badge').text('#' + p.loyalty_policy_id);
                $('#policy-code').val(p.loyalty_policy_code);
                $('#policy-title').val(p.loyalty_policy_title);
                $type.val(p.loyalty_policy_type);
                $('#policy-status').val(p.loyalty_policy_status);
                $('#policy-priority').val(p.loyalty_policy_priority);
                $('#policy-auto').prop('checked', p.auto_apply == 1);

                // Dates
                if (p.loyalty_policy_start_date > 0) {
                    $('#policy-start').val(new Date(p.loyalty_policy_start_date * 1000).toISOString().split('T')[0]);
                }
                if (p.loyalty_policy_end_date > 0) {
                    $('#policy-end').val(new Date(p.loyalty_policy_end_date * 1000).toISOString().split('T')[0]);
                }

                // Earn config from parsed_rules (JSON-based)
                const r = p.parsed_rules || {};
                $('#earn-rate').val(r.earn_rate ?? 1);
                $('#earn-unit').val(r.earn_unit ?? 1000);
                $('#redeem-rate').val(r.redeem_rate ?? 1);
                $('#point-expiry').val(r.point_expiry_days ?? 0);
                $('#min-order').val(r.min_order_amount ?? 0);
                $('#max-points').val(r.max_points_per_order ?? 0);

                // Rules (type-specific)
                renderRulesPanel(p.loyalty_policy_type, r.rules || []);

                // System info
                $('#sys-id').text(p.loyalty_policy_id);
                $('#sys-user').text(p.user_id || '—');
                $('#sys-blog').text(p.source_blog_id || '—');
                $('#sys-created').text(p.created_at || '—');
                $('#sys-updated').text(p.updated_at || '—');

                // Scope
                scopeBlogIds = JSON.parse(p.apply_to_blog_ids || '[]');
                scopeOrgInfo = JSON.parse(p.apply_to_org_level || '{}');
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
                    setTimeout(function() {
                        TgsScopeSelector.setValue(scopeBlogIds, scopeOrgInfo);
                    }, 500);
                }
            });
        }

        // ── UPDATE ──
        $('#btn-update').on('click', function() {
            const code = $('#policy-code').val().trim();
            const title = $('#policy-title').val().trim();
            if (!code) return alert('Mã chương trình không được trống.');
            if (!title) return alert('Tên chương trình không được trống.');

            const startDate = $('#policy-start').val();
            const endDate = $('#policy-end').val();

            const payload = {
                action: 'tgs_loyalty_policy_save',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: policyId,
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
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Cập nhật');
                alert(res.data?.message || (res.success ? 'Đã cập nhật.' : 'Lỗi.'));
                if (res.success) loadPolicy();
            });
        });

        // ── CLONE ──
        $('#btn-clone').on('click', function() {
            if (!confirm('Sao chép chương trình này?')) return;
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_clone',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: policyId
            }, function(res) {
                if (res.success) {
                    alert(res.data.message);
                    window.location.href = '<?php echo admin_url("admin.php?page=tgs-shop-management&view=loyalty-policy-detail"); ?>&id=' + res.data.loyalty_policy_id;
                } else {
                    alert(res.data?.message || 'Lỗi.');
                }
            });
        });

        // ── DELETE ──
        $('#btn-delete').on('click', function() {
            if (!confirm('Xóa chương trình này?')) return;
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_delete',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: policyId
            }, function(res) {
                alert(res.data?.message || 'Đã xóa.');
                window.location.href = '<?php echo admin_url("admin.php?page=tgs-shop-management&view=loyalty-policies"); ?>';
            });
        });

        loadPolicy();
    });
</script>
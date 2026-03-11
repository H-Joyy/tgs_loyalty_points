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
                        <li><strong>Loại 4 — Bonus:</strong> Tặng điểm bonus khi đơn hàng đạt mức nhất định.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        const $type = $('#policy-type');
        const $panel = $('#rules-panel');
        const $rulesCard = $('#rules-card');

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
            }
        }

        function addProductRule() {
            const idx = $('#rules-rows .rule-row').length;
            $('#rules-rows').append(
                '<div class="row g-2 mb-2 rule-row align-items-end">' +
                '<div class="col-md-5"><label class="form-label">SKU</label>' +
                '<input type="text" class="form-control rule-sku" placeholder="Mã SKU"></div>' +
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

        $type.on('change', renderRulesPanel);
        renderRulesPanel();

        $('#btn-add-rule').on('click', function() {
            const type = parseInt($type.val());
            if (type === 2) addProductRule();
            else if (type === 4) addBonusRule();
        });

        $(document).on('click', '.btn-remove-rule', function() {
            $(this).closest('.rule-row').remove();
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
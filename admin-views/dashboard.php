<?php if (!defined('ABSPATH')) exit; ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Dashboard
    </h4>

    <!-- ── STAT CARDS ── -->
    <div class="row g-4 mb-4" id="loyalty-stats">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Tổng chương trình</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2" id="stat-total">0</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="bx bx-star bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Đang hoạt động</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2 text-primary" id="stat-active">0</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="bx bx-check-circle bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Tổng thành viên</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2" id="stat-members">0</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="bx bx-group bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Tổng điểm đang có</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2 text-primary" id="stat-total-points">0</h4>
                            </div>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="bx bx-coin-stack bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ── LOẠI CHÍNH SÁCH ── -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Phân loại chương trình</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="type-breakdown">
                            <thead>
                                <tr>
                                    <th>Loại</th>
                                    <th>Tên</th>
                                    <th class="text-center">Số lượng</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── PHÂN BỐ HẠNG THÀNH VIÊN ── -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Phân bố hạng thành viên</h5>
                </div>
                <div class="card-body" id="tier-distribution">
                    <p class="text-muted">Đang tải...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── HƯỚNG DẪN NHANH ── -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Hướng dẫn nhanh</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-primary mb-2">Loại 1</span>
                                <h6>Tích điểm theo số tiền</h6>
                                <small class="text-muted">VD: 1 điểm / 1.000₫. Mua 100.000₫ → 100 điểm.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-primary mb-2">Loại 2</span>
                                <h6>Tích điểm theo sản phẩm</h6>
                                <small class="text-muted">VD: Mua SP ABC → +50 điểm/SP.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-primary mb-2">Loại 3</span>
                                <h6>Nhân hệ số điểm</h6>
                                <small class="text-muted">VD: x2 điểm trong sự kiện, ngày lễ.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="border rounded p-3 h-100">
                                <span class="badge bg-primary mb-2">Loại 4</span>
                                <h6>Bonus sự kiện</h6>
                                <small class="text-muted">VD: Mua từ 500.000₫ → +200 điểm bonus.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        const TYPE_LABELS = {
            1: 'Tích theo số tiền',
            2: 'Tích theo sản phẩm',
            3: 'Nhân hệ số',
            4: 'Bonus sự kiện'
        };

        function loadDashboard() {
            // Policy stats
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_stats',
                nonce: tgsLoyalty.nonce
            }, function(res) {
                if (!res.success) return;
                const d = res.data;
                $('#stat-total').text(d.total);
                $('#stat-active').text(d.active);

                // Type breakdown
                const $tbody = $('#type-breakdown tbody').empty();
                if (d.by_type && d.by_type.length) {
                    d.by_type.forEach(function(t) {
                        $tbody.append(
                            '<tr><td>' + t.type + '</td><td>' + (TYPE_LABELS[t.type] || 'Khác') +
                            '</td><td class="text-center">' + t.cnt + '</td></tr>'
                        );
                    });
                } else {
                    $tbody.append('<tr><td colspan="3" class="text-muted text-center">Chưa có chương trình</td></tr>');
                }
            });

            // Member stats
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_member_stats',
                nonce: tgsLoyalty.nonce
            }, function(res) {
                if (!res.success) return;
                const d = res.data;
                $('#stat-members').text(d.total);
                $('#stat-total-points').text(Number(d.total_points).toLocaleString('vi-VN'));

                // Tier distribution
                const $container = $('#tier-distribution').empty();
                if (d.by_tier && d.by_tier.length) {
                    const TIER_DEFS = <?php echo wp_json_encode(TGS_Loyalty_DB::get_tier_definitions()); ?>;
                    d.by_tier.forEach(function(t) {
                        const tierDef = TIER_DEFS[t.tier] || {};
                        const color = tierDef.color || '#6c757d';
                        const primaryColor = '#696cff';
                        const name = tierDef.name || t.tier;
                        $container.append(
                            '<div class="d-flex align-items-center mb-3">' +
                            '<span class="badge me-3" style="background:' + color + ';color:#fff;min-width:80px;">' + name + '</span>' +
                            '<div class="flex-grow-1"><div class="progress" style="height:20px;">' +
                            '<div class="progress-bar" style="width:' + Math.max(5, (t.cnt / Math.max(1, d.total)) * 100) + '%;background:' + primaryColor + ';">' +
                            t.cnt + ' thành viên</div></div></div></div>'
                        );
                    });
                } else {
                    $container.html('<p class="text-muted">Chưa có thành viên.</p>');
                }
            });
        }

        loadDashboard();
    });
</script>
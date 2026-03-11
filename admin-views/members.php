<?php if (!defined('ABSPATH')) exit; ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Thành viên
    </h4>

    <!-- ── TOOLBAR ── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Tên hoặc SĐT...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hạng</label>
                    <select id="filter-tier" class="form-select form-select-sm">
                        <option value="">Tất cả</option>
                        <?php
                        $tiers = TGS_Loyalty_DB::get_tier_definitions();
                        foreach ($tiers as $key => $tier) {
                            echo '<option value="' . esc_attr($key) . '">' . esc_html($tier['name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm" id="btn-search">
                        <i class="bx bx-search me-1"></i> Lọc
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ── -->
    <div class="card">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên</th>
                        <th>SĐT</th>
                        <th>Hạng</th>
                        <th class="text-end">Điểm hiện tại</th>
                        <th class="text-end">Tổng tích</th>
                        <th class="text-end">Tổng đổi</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="member-tbody">
                    <tr>
                        <td colspan="8" class="text-center text-muted">Đang tải...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted" id="pag-info"></small>
            <div>
                <button class="btn btn-sm btn-outline-secondary" id="pag-prev" disabled>&laquo; Trước</button>
                <button class="btn btn-sm btn-outline-secondary" id="pag-next" disabled>Sau &raquo;</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Cộng / Trừ điểm -->
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adjustModalTitle">Điều chỉnh điểm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="adj-customer-id">
                <input type="hidden" id="adj-action">
                <div class="mb-3">
                    <label class="form-label">Số điểm</label>
                    <input type="number" id="adj-points" class="form-control" min="1" value="100">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <input type="text" id="adj-desc" class="form-control" placeholder="Lý do điều chỉnh...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="btn-adj-confirm">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Lịch sử điểm -->
<div class="modal fade" id="logsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch sử giao dịch điểm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Thời gian</th>
                                <th>Loại</th>
                                <th class="text-end">Điểm</th>
                                <th class="text-end">Trước</th>
                                <th class="text-end">Sau</th>
                                <th>Mô tả</th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        const LOG_TYPE_LABELS = {
            earn: 'Tích',
            redeem: 'Đổi',
            adjust: 'Điều chỉnh',
            expire: 'Hết hạn',
            refund: 'Hoàn'
        };
        const LOG_TYPE_COLORS = {
            earn: 'primary',
            redeem: 'primary',
            adjust: 'primary',
            expire: 'primary',
            refund: 'primary'
        };

        let currentPage = 1;

        function fmtNum(n) {
            return Number(n).toLocaleString('vi-VN');
        }

        function loadMembers(page) {
            currentPage = page || 1;
            const $tbody = $('#member-tbody');
            $tbody.html('<tr><td colspan="8" class="text-center text-muted">Đang tải...</td></tr>');

            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_member_list',
                nonce: tgsLoyalty.nonce,
                page: currentPage,
                search: $('#filter-search').val(),
                tier: $('#filter-tier').val(),
            }, function(res) {
                if (!res.success) return;
                const d = res.data;
                $tbody.empty();

                if (!d.items.length) {
                    $tbody.html('<tr><td colspan="8" class="text-center text-muted">Chưa có thành viên.</td></tr>');
                    return;
                }

                d.items.forEach(function(m) {
                    $tbody.append(
                        '<tr>' +
                        '<td>' + m.wp_user_id + '</td>' +
                        '<td>' + $('<span>').text(m.display_name || '—').html() + '</td>' +
                        '<td>' + $('<span>').text(m.customer_phone || '—').html() + '</td>' +
                        '<td>' + m.tier_badge + '</td>' +
                        '<td class="text-end fw-bold">' + fmtNum(m.current_points) + '</td>' +
                        '<td class="text-end text-primary">' + fmtNum(m.total_earned) + '</td>' +
                        '<td class="text-end text-primary">' + fmtNum(m.total_redeemed) + '</td>' +
                        '<td>' +
                        '<button class="btn btn-sm btn-outline-primary me-1 btn-add-pts" data-id="' + m.wp_user_id + '" data-name="' + $('<span>').text(m.display_name).html() + '" title="Cộng điểm"><i class="bx bx-plus"></i></button>' +
                        '<button class="btn btn-sm btn-outline-primary me-1 btn-deduct-pts" data-id="' + m.wp_user_id + '" data-name="' + $('<span>').text(m.display_name).html() + '" title="Trừ điểm"><i class="bx bx-minus"></i></button>' +
                        '<button class="btn btn-sm btn-outline-primary btn-logs" data-id="' + m.wp_user_id + '" title="Lịch sử"><i class="bx bx-history"></i></button>' +
                        '</td></tr>'
                    );
                });

                // Pagination
                const start = (d.page - 1) * 20 + 1;
                const end = Math.min(d.page * 20, d.total);
                $('#pag-info').text(d.total > 0 ? ('Hiển thị ' + start + '–' + end + ' / ' + d.total) : '');
                $('#pag-prev').prop('disabled', d.page <= 1);
                $('#pag-next').prop('disabled', d.page >= d.total_pages);
            });
        }

        // Events
        $('#btn-search').on('click', function() {
            loadMembers(1);
        });
        $('#filter-search').on('keypress', function(e) {
            if (e.which === 13) loadMembers(1);
        });
        $('#pag-prev').on('click', function() {
            loadMembers(currentPage - 1);
        });
        $('#pag-next').on('click', function() {
            loadMembers(currentPage + 1);
        });

        // Adjust modal
        $(document).on('click', '.btn-add-pts', function() {
            $('#adj-customer-id').val($(this).data('id'));
            $('#adj-action').val('add');
            $('#adjustModalTitle').text('Cộng điểm — ' + $(this).data('name'));
            $('#adj-points').val(100);
            $('#adj-desc').val('');
            new bootstrap.Modal('#adjustModal').show();
        });

        $(document).on('click', '.btn-deduct-pts', function() {
            $('#adj-customer-id').val($(this).data('id'));
            $('#adj-action').val('deduct');
            $('#adjustModalTitle').text('Trừ điểm — ' + $(this).data('name'));
            $('#adj-points').val(100);
            $('#adj-desc').val('');
            new bootstrap.Modal('#adjustModal').show();
        });

        $('#btn-adj-confirm').on('click', function() {
            const pts = parseFloat($('#adj-points').val());
            if (!pts || pts <= 0) return alert('Số điểm phải > 0.');

            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_member_adjust',
                nonce: tgsLoyalty.nonce,
                wp_user_id: $('#adj-customer-id').val(),
                adjust_action: $('#adj-action').val(),
                points: pts,
                description: $('#adj-desc').val(),
            }, function(res) {
                alert(res.data?.message || (res.success ? 'Thành công.' : 'Lỗi.'));
                bootstrap.Modal.getInstance('#adjustModal')?.hide();
                loadMembers(currentPage);
            });
        });

        // Logs modal
        $(document).on('click', '.btn-logs', function() {
            const custId = $(this).data('id');
            const $tbody = $('#logs-tbody').html('<tr><td colspan="6" class="text-center">Đang tải...</td></tr>');
            new bootstrap.Modal('#logsModal').show();

            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_member_logs',
                nonce: tgsLoyalty.nonce,
                wp_user_id: custId,
                per_page: 50,
            }, function(res) {
                $tbody.empty();
                if (!res.success || !res.data.items.length) {
                    $tbody.html('<tr><td colspan="6" class="text-center text-muted">Chưa có giao dịch.</td></tr>');
                    return;
                }
                res.data.items.forEach(function(l) {
                    const colorClass = 'text-primary';
                    const prefix = parseFloat(l.points) >= 0 ? '+' : '';
                    $tbody.append(
                        '<tr>' +
                        '<td><small>' + l.created_at + '</small></td>' +
                        '<td><span class="badge bg-' + (LOG_TYPE_COLORS[l.log_type] || 'secondary') + '">' + (LOG_TYPE_LABELS[l.log_type] || l.log_type) + '</span></td>' +
                        '<td class="text-end ' + colorClass + ' fw-bold">' + prefix + fmtNum(l.points) + '</td>' +
                        '<td class="text-end">' + fmtNum(l.points_before) + '</td>' +
                        '<td class="text-end">' + fmtNum(l.points_after) + '</td>' +
                        '<td><small>' + $('<span>').text(l.description || '').html() + '</small></td>' +
                        '</tr>'
                    );
                });
            });
        });

        loadMembers(1);
    });
</script>
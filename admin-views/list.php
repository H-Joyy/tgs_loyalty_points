<?php if (!defined('ABSPATH')) exit; ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Chính sách tích điểm
    </h4>

    <!-- ── TOOLBAR ── -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Mã hoặc tên...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Loại</label>
                    <select id="filter-type" class="form-select form-select-sm">
                        <option value="">Tất cả</option>
                        <option value="1">Theo số tiền</option>
                        <option value="2">Theo sản phẩm</option>
                        <option value="3">Nhân hệ số</option>
                        <option value="4">Bonus sự kiện</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Trạng thái</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">Tất cả</option>
                        <option value="1">Hoạt động</option>
                        <option value="0">Nháp</option>
                        <option value="2">Hết hạn</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm" id="btn-search">
                        <i class="bx bx-search me-1"></i> Lọc
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <a href="<?php echo admin_url('admin.php?page=tgs-shop-management&view=loyalty-policy-add'); ?>"
                        class="btn btn-success btn-sm">
                        <i class="bx bx-plus me-1"></i> Thêm chính sách
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ── -->
    <div class="card">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover" id="loyalty-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Mã</th>
                        <th>Tên chính sách</th>
                        <th>Loại</th>
                        <th>Tỷ lệ tích</th>
                        <th>Ưu tiên</th>
                        <th>Tự động</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody id="loyalty-tbody">
                    <tr>
                        <td colspan="9" class="text-center text-muted">Đang tải...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer d-flex justify-content-between align-items-center" id="loyalty-pagination">
            <small class="text-muted" id="pag-info"></small>
            <div>
                <button class="btn btn-sm btn-outline-secondary" id="pag-prev" disabled>&laquo; Trước</button>
                <button class="btn btn-sm btn-outline-secondary" id="pag-next" disabled>Sau &raquo;</button>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        const TYPE_LABELS = {
            1: 'Theo số tiền',
            2: 'Theo sản phẩm',
            3: 'Nhân hệ số',
            4: 'Bonus sự kiện'
        };
        const TYPE_COLORS = {
            1: 'primary',
            2: 'success',
            3: 'warning',
            4: 'info'
        };
        const STATUS_LABELS = {
            0: 'Nháp',
            1: 'Hoạt động',
            2: 'Hết hạn'
        };
        const STATUS_COLORS = {
            0: 'secondary',
            1: 'success',
            2: 'danger'
        };

        let currentPage = 1;

        function loadList(page) {
            currentPage = page || 1;
            const $tbody = $('#loyalty-tbody');
            $tbody.html('<tr><td colspan="9" class="text-center text-muted">Đang tải...</td></tr>');

            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_list',
                nonce: tgsLoyalty.nonce,
                page: currentPage,
                search: $('#filter-search').val(),
                type: $('#filter-type').val(),
                status: $('#filter-status').val(),
            }, function(res) {
                if (!res.success) return;
                const d = res.data;
                $tbody.empty();

                if (!d.items.length) {
                    $tbody.html('<tr><td colspan="9" class="text-center text-muted">Không có dữ liệu.</td></tr>');
                } else {
                    d.items.forEach(function(p) {
                        const typeBadge = '<span class="badge bg-' + (TYPE_COLORS[p.loyalty_policy_type] || 'secondary') + '">' + (TYPE_LABELS[p.loyalty_policy_type] || 'Khác') + '</span>';
                        const statusBadge = '<span class="badge bg-' + (STATUS_COLORS[p.loyalty_policy_status] || 'secondary') + '">' + (STATUS_LABELS[p.loyalty_policy_status] || '?') + '</span>';
                        // Parse earn info from loyalty_rules JSON
                        let earnInfo = '—';
                        try {
                            const rules = JSON.parse(p.loyalty_rules || '{}');
                            if (rules.earn_rate && rules.earn_unit) {
                                earnInfo = rules.earn_rate + ' đ / ' + Number(rules.earn_unit).toLocaleString('vi-VN') + '₫';
                            }
                        } catch (e) {}
                        const autoApply = p.auto_apply == 1 ? '<i class="bx bx-check text-success"></i>' : '<i class="bx bx-x text-muted"></i>';
                        const detailUrl = '<?php echo admin_url("admin.php?page=tgs-shop-management&view=loyalty-policy-detail"); ?>&id=' + p.loyalty_policy_id;

                        $tbody.append(
                            '<tr>' +
                            '<td>' + p.loyalty_policy_id + '</td>' +
                            '<td><code>' + $('<span>').text(p.loyalty_policy_code).html() + '</code></td>' +
                            '<td><a href="' + detailUrl + '">' + $('<span>').text(p.loyalty_policy_title).html() + '</a></td>' +
                            '<td>' + typeBadge + '</td>' +
                            '<td>' + earnInfo + '</td>' +
                            '<td>' + p.loyalty_policy_priority + '</td>' +
                            '<td class="text-center">' + autoApply + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td>' +
                            '<a href="' + detailUrl + '" class="btn btn-sm btn-outline-primary me-1" title="Chi tiết"><i class="bx bx-show"></i></a>' +
                            '<button class="btn btn-sm btn-outline-warning me-1 btn-clone" data-id="' + p.loyalty_policy_id + '" title="Sao chép"><i class="bx bx-copy"></i></button>' +
                            '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + p.loyalty_policy_id + '" title="Xóa"><i class="bx bx-trash"></i></button>' +
                            '</td></tr>'
                        );
                    });
                }

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
            loadList(1);
        });
        $('#filter-search').on('keypress', function(e) {
            if (e.which === 13) loadList(1);
        });
        $('#pag-prev').on('click', function() {
            loadList(currentPage - 1);
        });
        $('#pag-next').on('click', function() {
            loadList(currentPage + 1);
        });

        $(document).on('click', '.btn-clone', function() {
            if (!confirm('Sao chép chính sách này?')) return;
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_clone',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: $(this).data('id')
            }, function(res) {
                alert(res.data?.message || 'Đã sao chép.');
                loadList(currentPage);
            });
        });

        $(document).on('click', '.btn-delete', function() {
            if (!confirm('Xóa chính sách này?')) return;
            $.post(tgsLoyalty.ajaxUrl, {
                action: 'tgs_loyalty_policy_delete',
                nonce: tgsLoyalty.nonce,
                loyalty_policy_id: $(this).data('id')
            }, function(res) {
                alert(res.data?.message || 'Đã xóa.');
                loadList(currentPage);
            });
        });

        loadList(1);
    });
</script>
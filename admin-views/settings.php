<?php if (!defined('ABSPATH')) exit; ?>

<?php $settings = TGS_Loyalty_DB::get_settings(); ?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Tích điểm /</span> Cài đặt
    </h4>

    <div id="loyalty-settings-alert" class="alert d-none mb-4"></div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Cài đặt chung</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tên gọi điểm</label>
                        <input type="text" id="point-label" class="form-control" value="<?php echo esc_attr($settings['point_label']); ?>" placeholder="điểm">
                        <div class="form-text">Hiển thị trong POS và bảng danh sách.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Điểm tối thiểu để đổi</label>
                        <input type="number" id="min-redeem-points" class="form-control" min="0" step="1" value="<?php echo esc_attr($settings['min_redeem_points']); ?>">
                        <div class="form-text">Nhập 0 nếu không giới hạn.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enable-pos-earn" <?php checked(!empty($settings['enable_pos_earn'])); ?>>
                        <label class="form-check-label" for="enable-pos-earn">Tự động cộng điểm khi bán hàng</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="enable-pos-redeem" <?php checked(!empty($settings['enable_pos_redeem'])); ?>>
                        <label class="form-check-label" for="enable-pos-redeem">Cho phép dùng điểm để thanh toán</label>
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="allow-manual-adjust" <?php checked(!empty($settings['allow_manual_adjust'])); ?>>
                        <label class="form-check-label" for="allow-manual-adjust">Cho phép điều chỉnh điểm bằng tay</label>
                    </div>

                    <div class="form-check form-switch mt-3 mb-0">
                        <input class="form-check-input" type="checkbox" id="enable-tier-multiplier" <?php checked(!empty($settings['enable_tier_multiplier'])); ?>>
                        <label class="form-check-label" for="enable-tier-multiplier">Nhân điểm theo hạng thành viên</label>
                        <div class="form-text">Nếu tắt, chính sách 1 điểm / 1.000đ sẽ cộng đúng theo cấu hình, không tự nhân thêm theo hạng Bạc/Vàng/VIP.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Hạng thành viên</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-tier">
                        <i class="bx bx-plus me-1"></i> Thêm hạng
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Tên hạng</th>
                                    <th>Điểm tối thiểu</th>
                                    <th>Hệ số nhân</th>
                                    <th>Màu</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tiers-tbody"></tbody>
                        </table>
                    </div>
                    <div class="form-text">Hạng sẽ tự sắp xếp theo điểm tối thiểu từ thấp đến cao khi lưu.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div class="text-muted small">Thay đổi sẽ có hiệu lực ngay sau khi lưu.</div>
            <button type="button" class="btn btn-primary" id="btn-save-loyalty-settings">
                <i class="bx bx-save me-1"></i> Lưu cài đặt
            </button>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {
        const initialSettings = <?php echo wp_json_encode($settings); ?>;

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function renderTierRow(key, tier) {
            return '<tr class="tier-row" data-key="' + escapeHtml(key) + '">' +
                '<td><input type="text" class="form-control form-control-sm tier-name" value="' + escapeHtml(tier.name || '') + '" placeholder="VD: Thành viên"></td>' +
                '<td><input type="number" class="form-control form-control-sm tier-min-points" value="' + Number(tier.min_points || 0) + '" min="0" step="1"></td>' +
                '<td><input type="number" class="form-control form-control-sm tier-multiplier" value="' + Number(tier.multiplier || 1) + '" min="0" step="0.1"></td>' +
                '<td><input type="color" class="form-control form-control-color form-control-sm tier-color" value="' + escapeHtml(tier.color || '#90A4AE') + '"></td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary btn-remove-tier"><i class="bx bx-trash"></i></button></td>' +
                '</tr>';
        }

        function renderTiers(tiers) {
            const $tbody = $('#tiers-tbody').empty();
            const entries = Object.entries(tiers || {});
            if (!entries.length) {
                $tbody.append(renderTierRow('member', {
                    name: 'Thành viên',
                    min_points: 0,
                    multiplier: 1,
                    color: '#90A4AE'
                }));
                return;
            }
            entries.forEach(function(entry) {
                $tbody.append(renderTierRow(entry[0], entry[1] || {}));
            });
        }

        function collectTiers() {
            const tiers = [];
            $('#tiers-tbody .tier-row').each(function() {
                const name = $(this).find('.tier-name').val().trim();
                tiers.push({
                    key: $(this).data('key') || name.toLowerCase().replace(/[^a-z0-9]/g, '_') || 'tier',
                    name: name,
                    min_points: parseFloat($(this).find('.tier-min-points').val()) || 0,
                    multiplier: parseFloat($(this).find('.tier-multiplier').val()) || 1,
                    color: $(this).find('.tier-color').val() || '#90A4AE'
                });
            });
            return tiers;
        }

        function showAlert(type, message) {
            $('#loyalty-settings-alert')
                .removeClass('d-none alert-success alert-danger alert-warning alert-info')
                .addClass('alert-' + type)
                .text(message);
        }

        $('#btn-add-tier').on('click', function() {
            $('#tiers-tbody').append(renderTierRow('', {
                name: '',
                min_points: 0,
                multiplier: 1,
                color: '#90A4AE'
            }));
        });

        $(document).on('click', '.btn-remove-tier', function() {
            if ($('#tiers-tbody .tier-row').length <= 1) {
                showAlert('warning', 'Phải có ít nhất 1 hạng.');
                return;
            }
            $(this).closest('.tier-row').remove();
        });

        $('#btn-save-loyalty-settings').on('click', function() {
            const payload = {
                action: 'tgs_loyalty_settings_save',
                nonce: tgsLoyalty.nonce,
                enable_pos_earn: $('#enable-pos-earn').is(':checked') ? 1 : 0,
                enable_pos_redeem: $('#enable-pos-redeem').is(':checked') ? 1 : 0,
                allow_manual_adjust: $('#allow-manual-adjust').is(':checked') ? 1 : 0,
                enable_tier_multiplier: $('#enable-tier-multiplier').is(':checked') ? 1 : 0,
                point_label: $('#point-label').val().trim(),
                min_redeem_points: $('#min-redeem-points').val(),
                tiers: JSON.stringify(collectTiers())
            };

            const $btn = $(this).prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Đang lưu...');

            $.post(tgsLoyalty.ajaxUrl, payload, function(res) {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Lưu cài đặt');
                if (res.success) {
                    showAlert('success', res.data?.message || 'Đã lưu.');
                } else {
                    showAlert('danger', res.data?.message || 'Không thể lưu.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Lưu cài đặt');
                showAlert('danger', 'Mất kết nối.');
            });
        });

        renderTiers(initialSettings.tiers || {});
    });
</script>
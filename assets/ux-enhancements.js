function validateDauTonForm(form) {
    const tau = (form.ten_tau && form.ten_tau.value || '').trim();
    const ngay = (form.ngay && form.ngay.value || '').trim();
    const so = (form.so_luong && form.so_luong.value || '').trim();
    if (!tau) { showAlert('Vui lòng chọn tàu', 'warning'); return false; }
    if (!ngay) { showAlert('Vui lòng chọn ngày', 'warning'); return false; }
    if (so === '' || isNaN(so)) { showAlert('Số lượng phải là số hợp lệ', 'warning'); return false; }
    if (form.action && form.action.value === 'cap_them' && parseFloat(so) < 0) {
        showAlert('Số lượng cấp thêm phải không âm', 'warning'); return false;
    }
    return true;
}

function validateTransferForm(form) {
    const src = (form.tau_nguon && form.tau_nguon.value || '').trim();
    const dst = (form.tau_dich && form.tau_dich.value || '').trim();
    const day = (form.ngay_chuyen && form.ngay_chuyen.value || '').trim();
    const so = (form.so_lit && form.so_lit.value || '').trim();
    if (!src) { showAlert('Vui lòng chọn tàu nguồn', 'warning'); return false; }
    if (!dst) { showAlert('Vui lòng chọn tàu đích', 'warning'); return false; }
    if (src === dst) { showAlert('Tàu nguồn và tàu đích không được trùng', 'warning'); return false; }
    if (!day) { showAlert('Vui lòng chọn ngày chuyển', 'warning'); return false; }
    if (so === '' || isNaN(so) || parseFloat(so) <= 0) { showAlert('Số lít phải > 0', 'warning'); return false; }
    return true;
}

let modalEditTransferEl = null;
let editTransferForm = null;
let editTransferPairInput = null;
let editTransferSourceSelect = null;
let editTransferDestSelect = null;
let editTransferDateInput = null;
let editTransferLitersInput = null;
let editTransferReasonInput = null;
let modalEditTransferInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    // Toggle giữa cấp thêm và tinh chỉnh trên trang quan_ly_dau_ton
    const toggle = document.getElementById('toggleTinhChinh');
    if (toggle) {
        const actionHidden = document.getElementById('actionHidden');
        const labelNgay = document.getElementById('labelNgay');
        const labelSoLuong = document.getElementById('labelSoLuong');
        const inputSoLuong = document.getElementById('inputSoLuong');
        const hintSoLuong = document.getElementById('hintSoLuong');
        const btnSubmit = document.getElementById('btnSubmitLenh');
        const btnSubmitText = btnSubmit ? btnSubmit.querySelector('span') : null;
        const groupCayXang = document.getElementById('groupCayXang');
        const inputCayXang = document.querySelector('select[name="cay_xang"]');

        function applyState() {
            const isTinhChinh = toggle.checked;
            if (actionHidden) actionHidden.value = isTinhChinh ? 'tinh_chinh' : 'cap_them';
            if (labelNgay) labelNgay.textContent = isTinhChinh ? 'Ngày tinh chỉnh' : 'Ngày lấy dầu';
            if (labelSoLuong) labelSoLuong.textContent = isTinhChinh ? 'Số lượng tinh chỉnh (Lít)' : 'Số lượng lấy (Lít)';
            if (inputSoLuong) {
                inputSoLuong.min = isTinhChinh ? '' : '0';
                inputSoLuong.placeholder = isTinhChinh ? 'vd: -20 hoặc 20' : 'vd: 500';
            }
            if (hintSoLuong) hintSoLuong.textContent = isTinhChinh ? 'Có thể âm (giảm) hoặc dương (tăng)' : 'Nhập số không âm';
            if (btnSubmit) {
                btnSubmit.classList.toggle('btn-primary', isTinhChinh);
                btnSubmit.classList.toggle('btn-success', !isTinhChinh);
            }
            if (btnSubmitText) btnSubmitText.textContent = isTinhChinh ? 'Lưu tinh chỉnh' : 'Lưu lệnh lấy dầu';
            if (groupCayXang) groupCayXang.style.display = isTinhChinh ? 'none' : 'block';
            if (inputCayXang) inputCayXang.required = !isTinhChinh;
        }
        toggle.addEventListener('change', applyState);
        applyState();
    }

    // Xử lý chung cho các nút hành động trên dòng
    window.__TD2_TRANSFER_HANDLER = true;
    modalEditTransferEl = document.getElementById('modalEditTransfer');
    editTransferForm = document.getElementById('formEditTransfer');
    editTransferPairInput = document.getElementById('editTransferPairId');
    editTransferSourceSelect = document.getElementById('editTransferSource');
    editTransferDestSelect = document.getElementById('editTransferDest');
    editTransferDateInput = document.getElementById('editTransferDate');
    editTransferLitersInput = document.getElementById('editTransferLiters');
    editTransferReasonInput = document.getElementById('editTransferReason');
    modalEditTransferInstance = null;
    if (modalEditTransferEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        modalEditTransferInstance = new bootstrap.Modal(modalEditTransferEl);
    }

    document.body.addEventListener('click', function(event) {
        const deleteButton = event.target.closest('.btn-delete');
        const editButton = event.target.closest('.btn-edit-inline');
        const saveButton = event.target.closest('.btn-save-inline');
        const cancelButton = event.target.closest('.btn-cancel-inline');
        const deleteTransferButton = event.target.closest('[data-action="delete-transfer"]');
        const editTransferButton = event.target.closest('[data-action="edit-transfer"]');

        // Xử lý nút Sửa chuyển dầu
        if (editTransferButton) {
            event.preventDefault();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            } else {
                event.stopPropagation();
            }
            if (!modalEditTransferInstance || !editTransferForm) {
                showAlert('Không thể mở hộp thoại sửa chuyển dầu trên trình duyệt này.', 'danger');
                return;
            }
            const pairId = editTransferButton.dataset.pairId || '';
            const src = editTransferButton.dataset.src || '';
            const dst = editTransferButton.dataset.dst || '';
            const date = editTransferButton.dataset.date || '';
            const liters = editTransferButton.dataset.liters || '';
            const reason = editTransferButton.dataset.reason || '';

            editTransferPairInput.value = pairId;
            editTransferSourceSelect.value = src;
            editTransferDestSelect.value = dst;
            editTransferDateInput.value = date;
            editTransferLitersInput.value = liters;
            editTransferReasonInput.value = reason;

            modalEditTransferInstance.show();
            return;
        }

        // Xử lý nút Xóa Chuyển Dầu
        if (deleteTransferButton) {
            event.preventDefault();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            } else {
                event.stopPropagation();
            }
            const pairId = deleteTransferButton.dataset.pairId;
            if (!pairId) {
                showAlert('Không tìm thấy ID cặp chuyển dầu.', 'danger');
                return;
            }
            if (confirm('Bạn có chắc chắn muốn xóa lệnh chuyển dầu này không? Hành động này sẽ xóa cả hai bản ghi (xuất và nhập).')) {
                fetch('../api/delete_transfer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'transfer_pair_id=' + encodeURIComponent(pairId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert(data.error || 'Có lỗi xảy ra', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Delete transfer error:', error);
                    showAlert('Lỗi kết nối. Vui lòng thử lại.', 'danger');
                });
            }
        }

        // Xử lý nút Xóa (cho các lệnh đơn lẻ)
        if (deleteButton) {
            const entryId = deleteButton.dataset.id;
            if (!entryId) {
                showAlert('Không tìm thấy ID của mục nhập.', 'danger');
                return;
            }
            if (confirm('Bạn có chắc chắn muốn xóa lệnh cấp dầu này không?')) {
                fetch('../api/delete_dau_ton.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(entryId)
                })
                .then(async response => {
                    const raw = await response.text();
                    console.log('[delete_dau_ton] raw response:', raw);
                    let data;
                    try {
                        data = JSON.parse(raw);
                    } catch (parseErr) {
                        console.error('[delete_dau_ton] JSON parse error', parseErr);
                        throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');
                    }
                    if (!response.ok) {
                        throw new Error(data.message || data.error || 'Máy chủ trả về lỗi ' + response.status);
                    }
                    return data;
                })
                .then(data => {
                    window.lastDeleteDebug = data.debug;
                    console.log('[delete_dau_ton] success payload:', data);
                    console.log('[delete_dau_ton] debug:', window.lastDeleteDebug);
                    if (data.success) {
                        showAlert(data.message, 'success');
                        const targetUrl = window.location.pathname + window.location.search;
                        showAlert(data.message, 'success');
                        setTimeout(() => {
                            window.location.href = targetUrl || window.location.pathname;
                        }, 1500);
                    } else {
                        console.warn('[delete_dau_ton] failure payload:', data);
                        showAlert(data.message || 'Không thể xóa lệnh cấp dầu.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('[delete_dau_ton] request error:', error);
                    showAlert(error.message || 'Lỗi kết nối. Vui lòng thử lại.', 'danger');
                });
            }
        }

        // Xử lý nút Sửa (chuyển sang chế độ inline edit)
        if (editButton) {
            const row = editButton.closest('tr');
            if (row) {
                row.querySelector('.cay-xang-display').style.display = 'none';
                row.querySelector('.action-buttons-view').style.display = 'none';
                row.querySelector('.cay-xang-edit').style.display = 'flex';
            }
        }

        // Xử lý nút Hủy
        if (cancelButton) {
            const row = cancelButton.closest('tr');
            if (row) {
                row.querySelector('.cay-xang-display').style.display = 'block';
                row.querySelector('.action-buttons-view').style.display = 'flex';
                row.querySelector('.cay-xang-edit').style.display = 'none';
            }
        }

        // Xử lý nút Lưu
        if (saveButton) {
            event.preventDefault();
            if (event.stopImmediatePropagation) {
                event.stopImmediatePropagation();
            } else {
                event.stopPropagation();
            }
            const row = saveButton.closest('tr');
            const entryId = saveButton.dataset.id;
            const select = row.querySelector('.cay-xang-edit select');
            const newCayXang = select.value;

            if (saveButton.dataset.loading === '1') {
                return;
            }
            saveButton.dataset.loading = '1';
            const originalHtml = saveButton.innerHTML;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            saveButton.disabled = true;

            fetch('../api/update_cay_xang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(entryId)}&cay_xang=${encodeURIComponent(newCayXang)}`
            })
            .then(async response => {
                const raw = await response.text();
                let data;
                try {
                    data = JSON.parse(raw);
                } catch (jsonErr) {
                    console.error('Update cay xang error: invalid JSON response', raw);
                    throw new Error('Máy chủ trả về dữ liệu không hợp lệ.');
                }
                return data;
            })
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    const displaySpan = row.querySelector('.cay-xang-display');
                    displaySpan.textContent = newCayXang || 'Chưa xác định';
                    // Chuyển về chế độ xem
                    row.querySelector('.cay-xang-display').style.display = 'block';
                    row.querySelector('.cay-xang-edit').style.display = 'none';
                    row.querySelector('.action-buttons-view').style.display = 'flex';
                    const form = document.getElementById('formLenhDau');
                    if (form) {
                        form.reset();
                    }
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Update cay xang error:', error);
                showAlert(error.message || 'Lỗi kết nối. Vui lòng thử lại.', 'danger');
            })
            .finally(() => {
                saveButton.dataset.loading = '0';
                saveButton.innerHTML = originalHtml;
                saveButton.disabled = false;
            });
    }
    });

    // Modal chi tiết tiêu hao
    const modalChiTiet = document.getElementById('modalChiTiet');
    if (modalChiTiet) {
        modalChiTiet.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            modalChiTiet.querySelector('[data-field="ten_tau"]').textContent = button.getAttribute('data-ten-tau') || '';
            modalChiTiet.querySelector('[data-field="route"]').textContent = button.getAttribute('data-route') || '';
            modalChiTiet.querySelector('[data-field="km"]').textContent = button.getAttribute('data-km') || '0.0';
            modalChiTiet.querySelector('[data-field="km_co"]').textContent = button.getAttribute('data-km-co') || '0.0';
            modalChiTiet.querySelector('[data-field="km_khong"]').textContent = button.getAttribute('data-km-khong') || '0.0';
            modalChiTiet.querySelector('[data-field="weight"]').textContent = button.getAttribute('data-weight') || '0.00';
            modalChiTiet.querySelector('[data-field="ngay_di"]').textContent = button.getAttribute('data-ngay-di') || '';
            modalChiTiet.querySelector('[data-field="ngay_den"]').textContent = button.getAttribute('data-ngay-den') || '';
            modalChiTiet.querySelector('[data-field="ngay_do"]').textContent = button.getAttribute('data-ngay-do') || '';
            modalChiTiet.querySelector('[data-field="dau"]').textContent = button.getAttribute('data-dau') || '0.00';
                    });
                }
            });

    if (editTransferForm) {
        editTransferForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const pairId = editTransferPairInput.value.trim();
            const src = editTransferSourceSelect.value.trim();
            const dst = editTransferDestSelect.value.trim();
            const date = editTransferDateInput.value.trim();
            const liters = editTransferLitersInput.value.trim();
            const reason = editTransferReasonInput.value.trim();

            if (!pairId) {
                showAlert('Không xác định được cặp chuyển dầu để sửa.', 'danger');
                return;
            }
            if (!src || !dst) {
                showAlert('Vui lòng chọn tàu nguồn và tàu đích.', 'warning');
                return;
            }
            if (src === dst) {
                showAlert('Tàu nguồn và tàu đích không được trùng.', 'warning');
                return;
            }
            if (!date) {
                showAlert('Vui lòng nhập ngày chuyển.', 'warning');
                return;
            }
            if (!liters || isNaN(liters) || parseFloat(liters) <= 0) {
                showAlert('Số lít phải là số lớn hơn 0.', 'warning');
                return;
            }

            const body = new URLSearchParams({
                transfer_pair_id: pairId,
                new_source_ship: src,
                new_dest_ship: dst,
                new_date: date,
                new_liters: liters,
                reason: reason
            });

            fetch('../api/update_transfer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Đã cập nhật lệnh chuyển dầu.', 'success');
                    if (modalEditTransferInstance) {
                        modalEditTransferInstance.hide();
                    }
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.error || 'Không thể cập nhật lệnh chuyển dầu.', 'danger');
                }
            })
            .catch(error => {
                console.error('Update transfer error:', error);
                showAlert('Lỗi kết nối. Vui lòng thử lại.', 'danger');
            });
        });
    }
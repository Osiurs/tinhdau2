                </div> <!-- nested container -->
            </div> <!-- /col main -->
        </div> <!-- /row -->
    </div> <!-- /container-fluid layout -->

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <h5><i class="fas fa-ship me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p class="mb-0">Hệ thống tính toán nhiên liệu sử dụng cho tàu</p>
                    <small>Phiên bản <?php echo VERSION; ?></small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/vn.js"></script>
<?php $prefixFooter = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../' : ''; ?>
    <script src="<?php echo $prefixFooter; ?>assets/ux-enhancements.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Sidebar toggle persistence + improved UX
        (function() {
            let sidebarTooltips = [];

            const destroyTooltips = () => {
                sidebarTooltips.forEach(t => { try { t.dispose(); } catch (e) {} });
                sidebarTooltips = [];
            };

            const initCollapsedTooltips = () => {
                destroyTooltips();
                const sidebar = document.getElementById('desktopSidebar');
                if (!sidebar) return;
                if (!document.body.classList.contains('sidebar-collapsed')) return;
                const links = sidebar.querySelectorAll('.list-group-item');
                links.forEach(link => {
                    const textEl = link.querySelector('.item-text');
                    const titleText = textEl ? textEl.textContent.trim() : link.textContent.trim();
                    if (titleText) {
                        link.setAttribute('title', titleText);
                        link.setAttribute('data-bs-toggle', 'tooltip');
                        link.setAttribute('data-bs-placement', 'right');
                        try { sidebarTooltips.push(new bootstrap.Tooltip(link)); } catch (e) {}
                    }
                });
            };

            const updateToggleUI = (collapsed) => {
                const desktopBtn = document.getElementById('sidebarToggleDesktop');
                const insideBtn = document.getElementById('sidebarToggleInside');

                const updateBtn = (btn) => {
                    if (!btn) return;
                    const icon = btn.querySelector('i');
                    const label = btn.querySelector('span');
                    if (icon) {
                        icon.classList.remove('fa-angles-left', 'fa-angles-right');
                        icon.classList.add(collapsed ? 'fa-angles-right' : 'fa-angles-left');
                    }
                    if (label) {
                        label.textContent = collapsed ? ' Mở rộng' : ' Thu gọn';
                    }
                    btn.setAttribute('aria-label', collapsed ? 'Mở rộng sidebar' : 'Thu gọn sidebar');
                };

                updateBtn(desktopBtn);
                updateBtn(insideBtn);

                initCollapsedTooltips();
            };

            const applyState = (collapsed) => {
                if (collapsed) {
                    document.body.classList.add('sidebar-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                }
                updateToggleUI(collapsed);
            };
            const saveState = (collapsed) => {
                try { localStorage.setItem('sidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
            };
            const readState = () => {
                try { return localStorage.getItem('sidebarCollapsed') === '1'; } catch (e) { return false; }
            };

            document.addEventListener('DOMContentLoaded', function() {
                // Apply initial state on desktop
                const initial = readState();
                applyState(initial);

                const btns = [
                    document.getElementById('sidebarToggleDesktop'),
                    document.getElementById('sidebarToggleInside')
                ].filter(Boolean);

                btns.forEach(btn => btn.addEventListener('click', function() {
                    const collapsed = !document.body.classList.contains('sidebar-collapsed');
                    applyState(collapsed);
                    saveState(collapsed);
                }));
            });
        })();

        // Hàm hiển thị loading
        function showLoading() {
            document.querySelectorAll('.loading').forEach(el => el.style.display = 'inline-block');
        }
        
        // Hàm ẩn loading
        function hideLoading() {
            document.querySelectorAll('.loading').forEach(el => el.style.display = 'none');
        }
        
        // Hàm hiển thị thông báo
        function showAlert(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Tự động ẩn sau 5 giây
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // Cờ chặn auto-handler khi đang submit để tránh làm mất dữ liệu
        window.__submittingForm = false;
        document.addEventListener('DOMContentLoaded', function(){
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form){
                if (form.dataset.boundSubmitGuard) return;
                form.dataset.boundSubmitGuard = '1';
                form.addEventListener('submit', function(ev){
                    const actionInput = form.querySelector('input[name="action"]');
                    const currentAction = actionInput ? (actionInput.value || 'calculate') : 'calculate';
                    if (currentAction === 'save') {
                        window.__submittingForm = true;
                        return;
                    }
                    window.__submittingForm = true;
                    // Khoá các auto-handlers trong 3 giây để tránh các onchange async gây reset
                    setTimeout(function(){ window.__submittingForm = false; }, 3000);
                });
            });
        });

        // Hàm format số
        function formatNumber(num) {
            return new Intl.NumberFormat('vi-VN').format(num);
        }
        
        // Đảm bảo mỗi ô nhập điểm và dropdown kết quả đều có id duy nhất
        function ensureInputAndResultsIds(input, resultContainer) {
            try {
                if (input && !input.id) {
                    const unique = 'diem_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
                    input.id = unique;
                }
                if (input && resultContainer && !resultContainer.id) {
                    resultContainer.id = input.id + '_results';
                }
            } catch(_) { /* no-op */ }
        }
        
        // Hàm tìm kiếm điểm (hỗ trợ IME tiếng Việt + chuẩn hóa Unicode NFC)
        function searchDiem(input, resultContainer) {
            // Bỏ qua khi đang gõ bằng IME (composition)
            if (input && input.dataset && input.dataset.composing === '1') {
                return;
            }

            const runSearch = () => {
                // Gán id động cho các ô Điểm C/D/E... vì chúng không có sẵn id trong HTML
                ensureInputAndResultsIds(input, resultContainer);
                // Chuẩn hóa về NFC để tránh tách dấu
                const keyword = (input.value || '').normalize('NFC').trim();
                
                // Lấy điểm đầu đã chọn (nếu đang tìm điểm cuối)
                let diemDau = '';
                if (input.id === 'diem_ket_thuc') {
                    const diemBatDauInput = document.getElementById('diem_bat_dau');
                    if (diemBatDauInput) {
                        diemDau = diemBatDauInput.value.trim();
                    }
                }
                // Nếu đang ở ô điểm kết thúc nhưng chưa có điểm bắt đầu thì dừng
                if (input.id === 'diem_ket_thuc' && !diemDau) {
                    showAlert('Vui lòng chọn điểm bắt đầu trước khi chọn điểm kết thúc', 'warning');
                    return;
                }
                
                // Nếu không có từ khóa, không làm gì cả
                if (keyword.length === 0) {
                    resultContainer.innerHTML = '';
                    resultContainer.style.display = 'none';
                    return;
                }
                
                const url = `api/search_diem.php?keyword=${encodeURIComponent(keyword)}${diemDau ? '&diem_dau=' + encodeURIComponent(diemDau) : ''}`;
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            let html = '';
                            data.data.forEach(item => {
                                const diem = item.diem;
                                const khoangCach = item.khoang_cach;
                                let displayText = diem;
                                
                                // Nếu có khoảng cách, hiển thị thêm
                                if (khoangCach !== null) {
                                    displayText += ` (${khoangCach} km)`;
                                }
                                
                                html += `<div class="dropdown-item" onclick="selectDiem('${diem}', '${input.id}')">${displayText}</div>`;
                            });
                            resultContainer.innerHTML = html;
                            resultContainer.style.display = 'block';
                        } else {
                            resultContainer.innerHTML = '<div class="dropdown-item text-muted">Không tìm thấy điểm</div>';
                            resultContainer.style.display = 'block';
                        }
                    })
                    .catch(error => {
                        console.error('Lỗi tìm kiếm:', error);
                        resultContainer.innerHTML = '<div class="dropdown-item text-muted">Lỗi tìm kiếm</div>';
                        resultContainer.style.display = 'block';
                    });
            };

            // Debounce để tránh gọi liên tục khi đang gõ
            clearTimeout(input._searchTimer);
            input._searchTimer = setTimeout(runSearch, 250);
        }
        
        // Hàm hiển thị tất cả điểm
        function showAllDiem(resultContainer, diemDau = '') {
            // Suy ra input liền trước nếu dropdown chưa có id (trường hợp C/D/E được thêm động)
            try {
                const maybeInput = resultContainer && resultContainer.previousElementSibling && resultContainer.previousElementSibling.matches && resultContainer.previousElementSibling.matches('input.diem-input')
                    ? resultContainer.previousElementSibling
                    : null;
                if (maybeInput) {
                    ensureInputAndResultsIds(maybeInput, resultContainer);
                }
            } catch(_) { /* no-op */ }
            const url = `api/search_diem.php?keyword=&diem_dau=${encodeURIComponent(diemDau)}`;
            // Nếu mở dropdown điểm kết thúc nhưng chưa chọn điểm bắt đầu thì dừng
            if (resultContainer && resultContainer.id === 'diem_ket_thuc_results' && !diemDau) {
                showAlert('Vui lòng chọn điểm bắt đầu trước khi chọn điểm kết thúc', 'warning');
                return;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('API response:', data);
                    if (data.success && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(item => {
                            const diem = item.diem;
                            const khoangCach = item.khoang_cach;
                            let displayText = diem;
                            
                            // Nếu có khoảng cách, hiển thị thêm
                            if (khoangCach !== null) {
                                displayText += ` (${khoangCach} km)`;
                            }
                            
                            html += `<div class="dropdown-item" onclick="selectDiem('${diem}', '${resultContainer.id.replace('_results', '')}')">${displayText}</div>`;
                        });
                        resultContainer.innerHTML = html;
                        resultContainer.style.display = 'block';
                    } else {
                        resultContainer.innerHTML = '<div class="dropdown-item text-muted">Không có điểm nào</div>';
                        resultContainer.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Lỗi tìm kiếm:', error);
                });
        }
        
        // Hàm chọn điểm từ dropdown
        function selectDiem(diem, inputId) {
            const input = document.getElementById(inputId);
            const resultContainer = document.getElementById(inputId + '_results');
            
            // Set giá trị và ẩn dropdown
            if (input) { input.value = diem; }
            if (resultContainer) {
                resultContainer.style.display = 'none';
            }
            
            // Kiểm tra xem có phải input trong modal chỉnh sửa không
            const isEditModal = inputId === 'edit_diem_di' || inputId === 'edit_diem_den';
            
            if (input) {
                if (!isEditModal) {
                    // Trong form chính: khóa input và thay đổi placeholder
                    input.readOnly = true;
                    input.placeholder = 'Đã chọn: ' + diem;
                } else {
                    // Trong modal chỉnh sửa: không khóa, chỉ cập nhật giá trị
                    input.placeholder = 'Bắt đầu nhập để tìm kiếm...';
                    // Cập nhật data-original-value nếu chưa có (lần đầu chọn)
                    if (!input.dataset.originalValue) {
                        input.dataset.originalValue = diem;
                    }
                }
                window.__lastFocusedDiemMoiInput = input;
            }
            
            // Xóa bất kỳ class lỗi nào
            if (input) { input.classList.remove('is-invalid'); }
            
            if (typeof updateDiemMoiPlaceholders === 'function') {
                try { updateDiemMoiPlaceholders(); } catch(_){}
            }
            
            // Nếu chọn điểm bắt đầu, mở khóa điểm kết thúc (chỉ trong form chính)
            if (inputId === 'diem_bat_dau') {
                const diemKetThucInput = document.getElementById('diem_ket_thuc');
                if (diemKetThucInput) {
                    diemKetThucInput.readOnly = false;
                    diemKetThucInput.placeholder = 'Bắt đầu nhập để tìm kiếm...';
                    diemKetThucInput.value = ''; // Xóa giá trị cũ
                    diemKetThucInput.classList.remove('is-invalid');
                }
            }
            
            // Nếu chọn điểm kết thúc, kiểm tra xem có phải điểm đầu không
            if (inputId === 'diem_ket_thuc') {
                const diemBatDauInput = document.getElementById('diem_bat_dau');
                if (diemBatDauInput && diem === diemBatDauInput.value) {
                    showAlert('Điểm kết thúc không được giống điểm bắt đầu!', 'warning');
                    if (input) {
                        input.value = '';
                        input.readOnly = false;
                        input.placeholder = 'Bắt đầu nhập để tìm kiếm...';
                    }
                    return;
                }
            }
            
            // Kiểm tra tương tự cho modal chỉnh sửa
            if (inputId === 'edit_diem_den') {
                const editDiemDiInput = document.getElementById('edit_diem_di');
                if (editDiemDiInput && diem === editDiemDiInput.value) {
                    showAlert('Điểm đến không được giống điểm đi!', 'warning');
                    if (input) {
                        input.value = '';
                    }
                    return;
                }
            }

            // Re-check hiển thị/ẩn khoảng cách thủ công ngay sau khi chọn A/B (chỉ form chính)
            if (typeof checkAndShowManualDistance === 'function') {
                try { checkAndShowManualDistance(); } catch(_) {}
            }
            
            // Nếu trong modal chỉnh sửa, gọi checkRouteChange để cập nhật cảnh báo
            if (isEditModal && typeof checkRouteChange === 'function') {
                try { checkRouteChange(); } catch(_) {}
            }
        }
        
        // Ẩn dropdown khi click ra ngoài
        document.addEventListener('click', function(e) {
            if (!e.target.matches('.diem-input')) {
                document.querySelectorAll('.diem-results').forEach(el => {
                    el.style.display = 'none';
                });
            }
        });
        
        // Helper: reset khu vực nhật ký chuyến khi đổi tàu/chuyến để tránh hiển thị dữ liệu cũ
        function resetTripLogUI(soChuyen){
            try {
                const header = document.querySelector('#tripLogDynamic .card-header h6');
                if (header) header.innerHTML = `<i class="fas fa-list me-2"></i>Các đoạn của chuyến ${escapeHtml(String(soChuyen||''))} (sắp xếp theo thứ tự nhập)`;
            } catch(_){ }
            try {
                const cardBody = document.querySelector('#tripLogDynamic .card-body');
                if (cardBody) {
                    const oldAlert = cardBody.querySelector('.alert.alert-warning');
                    if (oldAlert) { try { oldAlert.remove(); } catch(_){ } }
                }
            } catch(_){ }
            try {
                const tbody = document.getElementById('trip_table_body');
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-muted">Chưa có dữ liệu cho chuyến này.</td></tr>`;
                } else {
                    const wrap = document.getElementById('tripLogDynamic');
                    if (wrap) wrap.innerHTML = '';
                }
            } catch(_){ }
        }

        // Function để xử lý thay đổi tàu - CẬP NHẬT CHO DROPDOWN MÃ CHUYẾN
        window.onTauChange = function() {
            console.log('=== onTauChange CALLED ===');
            const tenTau = document.getElementById('ten_tau').value;
            const soChuyenSelect = document.getElementById('so_chuyen');
            const chuyenMoiCheckbox = document.getElementById('chuyen_moi');
            
            console.log('=== onTauChange START ===');
            console.log('tenTau:', tenTau);
            // Invalidate các request chi tiết chuyến đang chờ để tránh render đè dữ liệu cũ
            window.__tripReqToken = (typeof window.__tripReqToken === 'number' ? window.__tripReqToken + 1 : 1);
            // Xóa nhanh UI hiện tại để không còn thấy dữ liệu của tàu trước
            try { resetTripLogUI(soChuyenSelect?.value || ''); } catch(_){ }
            
            if (window.__submittingForm) { return; }
            if (tenTau && soChuyenSelect) {
                // Gọi API để lấy danh sách chuyến của tàu (trở về endpoint cũ ổn định)
                console.log('Fetching trips for:', tenTau);
                const apiUrl = 'ajax/get_trips.php?ten_tau=' + encodeURIComponent(tenTau);
                console.log('API URL:', apiUrl);
                
                fetch(apiUrl)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('API response data:', data);
                        if (data && data.success) {
                            // Cập nhật URL để server có thể render sẵn nếu reload
                            try {
                                const params = new URLSearchParams(window.location.search);
                                params.set('ten_tau', tenTau);
                                const currentSo = soChuyenSelect.value || '';
                                if (currentSo) params.set('so_chuyen', currentSo); else params.delete('so_chuyen');
                                const newUrl = window.location.pathname + '?' + params.toString();
                                window.history.replaceState({}, '', newUrl);
                            } catch(_){}
                            // Xóa các option cũ
                            soChuyenSelect.innerHTML = '';
                            
                            // Thêm option mặc định
                            const defaultOption = document.createElement('option');
                            defaultOption.value = '';
                            defaultOption.textContent = '-- Chọn chuyến --';
                            soChuyenSelect.appendChild(defaultOption);
                            // Dọn sạch khu vực bảng ngay khi đổi tàu để tránh sót dữ liệu
                            try { resetTripLogUI(''); } catch(_){ }
                            
                            // Thêm các chuyến đã có
                            data.trips.forEach(trip => {
                                const option = document.createElement('option');
                                option.value = trip;
                                option.textContent = trip;
                                soChuyenSelect.appendChild(option);
                            });
                            
                            // Giữ nguyên mã chuyến đang có nếu tồn tại trong danh sách; nếu không, chọn chuyến cao nhất
                            const preSelected = (soChuyenSelect.getAttribute('data-preselected') || '').trim();
                            // console.log(`DEBUG onTauChange: data-preselected='${preSelected}', available trips:`, data.trips);
                            const exists = preSelected && data.trips.some(t => String(t) === preSelected);
                            // console.log(`DEBUG onTauChange: preSelected value '${preSelected}' exists in trips? ${exists}`);
                            if (exists) {
                                soChuyenSelect.value = preSelected;
                                try { window.onChuyenChange(); } catch(e) {}
                            } else if (data.trips.length > 0) {
                                // Chỉ tự động chọn trip cao nhất nếu không có giá trị hiện tại
                                // console.log('DEBUG: soChuyenSelect.value =', soChuyenSelect.value, 'data.trips[0] =', data.trips[0]);
                                if (!soChuyenSelect.value || soChuyenSelect.value === '') {
                                    // console.log('DEBUG: Auto-selecting trip', data.trips[0]);
                                    soChuyenSelect.value = data.trips[0];
                                    try { window.onChuyenChange(); } catch(e) {}
                                    // Cập nhật URL với giá trị mã chuyến mới được chọn
                                    try {
                                        const params = new URLSearchParams(window.location.search);
                                        params.set('ten_tau', tenTau);
                                        params.set('so_chuyen', soChuyenSelect.value);
                                        window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                                    } catch(_){}
                                } else {
                                    // Nếu có giá trị hiện tại nhưng không tồn tại trong danh sách, giữ nguyên
                                    // console.log('DEBUG: Keeping current value', soChuyenSelect.value);
                                    try { window.onChuyenChange(); } catch(e) {}
                                }
                            } else {
                                // Không có chuyến nào: chỉ set tháng hiện tại nếu chưa có giá trị
                                try {
                                    const thangSelect = document.getElementById('thang_bao_cao');
                                    if (thangSelect && (!thangSelect.value || thangSelect.value === '')) {
                                        const now = new Date();
                                        const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
                                        thangSelect.value = ym;
                                    }
                                } catch(_){}
                                // Và đặt mặc định mã chuyến đầu tiên = 1 để có thể lưu ngay
                                const firstTripOpt = document.createElement('option');
                                firstTripOpt.value = '1';
                                firstTripOpt.textContent = '1';
                                soChuyenSelect.appendChild(firstTripOpt);
                                soChuyenSelect.value = '1';
                                try { soChuyenSelect.dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}
                                try {
                                    const params = new URLSearchParams(window.location.search);
                                    params.set('ten_tau', tenTau);
                                    params.set('so_chuyen', '1');
                                    window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                                } catch(_){}
                            }
                            
                            // Lưu thông tin để tạo chuyến mới
                            soChuyenSelect.setAttribute('data-max-trip', data.max_trip);
                            soChuyenSelect.setAttribute('data-next-trip', data.next_trip);
                            
                            // Reset checkbox "Tạo chuyến mới"
                            if (chuyenMoiCheckbox) {
                                chuyenMoiCheckbox.checked = false;
                            }
                            
                            // Tự động cập nhật phân loại khi chọn tàu
                            const selectPhanLoai = document.getElementById('loc_phan_loai');
                            if (selectPhanLoai) {
                                const selectedOption = document.querySelector(`#ten_tau option[value="${tenTau}"]`);
                                if (selectedOption) {
                                    const phanLoai = selectedOption.getAttribute('data-pl') || 'cong_ty';
                                    selectPhanLoai.value = phanLoai;
                                    console.log('Updated phanLoai to:', phanLoai);
                                }
                            }
                        } else {
                            console.error('API returned success: false or no data:', data);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching trips:', error);
                    });
            } else {
                console.log('No tenTau selected, clearing soChuyenSelect');
                if (soChuyenSelect) {
                    soChuyenSelect.innerHTML = '<option value="">-- Vui lòng chọn tàu --</option>';
                }
                // Reset checkbox khi không chọn tàu
                if (chuyenMoiCheckbox) {
                    chuyenMoiCheckbox.checked = false;
                }
                // Xóa sạch bảng khi bỏ chọn tàu
                try { resetTripLogUI(''); } catch(_){ }
                // Xóa dữ liệu form
                clearFormData();
            }
            // Nếu đang ở chế độ Tạo chuyến mới sau khi tải danh sách chuyến, áp dụng lại giá trị nextTrip
            // CHỈ gọi khi thực sự cần thiết và không phải trong quá trình submit form
            const chuyenMoiCheckbox2 = document.getElementById('chuyen_moi');
            if (chuyenMoiCheckbox2 && chuyenMoiCheckbox2.checked && !window.__submittingForm) {
                try { window.onToggleChuyenMoi(); } catch(e){}
            }
            console.log('=== onTauChange END ===');
        };

        // Function để xử lý thay đổi chuyến
        window.onChuyenChange = function() {
            if (window.__submittingForm) { return; }
            const tenTau = document.getElementById('ten_tau').value;
            const soChuyen = document.getElementById('so_chuyen').value;
            
            if (tenTau && soChuyen) {
                // Gọi API để lấy chi tiết chuyến (với request token chống race-condition)
                const apiUrl = `ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${encodeURIComponent(soChuyen)}`;
                // Hiển thị trạng thái loading nhanh để tránh nhìn thấy dữ liệu cũ
                try {
                    const header = document.querySelector('#tripLogDynamic .card-header h6');
                    if (header) header.innerHTML = `<i class="fas fa-list me-2"></i>Các đoạn của chuyến ${escapeHtml(String(soChuyen||''))} (sắp xếp theo thứ tự nhập)`;
                    const tbody = document.getElementById('trip_table_body');
                    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-muted">Đang tải dữ liệu...</td></tr>`;
                } catch(_){ /* noop */ }

                window.__tripReqToken = (typeof window.__tripReqToken === 'number' ? window.__tripReqToken + 1 : 1);
                const reqToken = window.__tripReqToken;

                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (reqToken !== window.__tripReqToken) { return; }
                        if (data && data.success) {
                            // Nếu có dữ liệu, điền vào form (có thể từ đoạn cuối cùng)
                            let segment = data.last_segment;
                            
                            // Nếu chuyến hiện tại chưa có dữ liệu, lấy điểm cuối từ chuyến trước đó
                            if (!segment && parseInt(soChuyen) > 1) {
                                const prevTripUrl = `ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${parseInt(soChuyen) - 1}`;
                                fetch(prevTripUrl)
                                    .then(response => response.json())
                                    .then(prevData => {
                                        if (prevData && prevData.success && prevData.last_segment) {
                                            const prevSegment = prevData.last_segment;
                                            processDiemBatDau(prevSegment);
                                        }
                                    })
                                    .catch(_ => {});
                            }
                            
                            if (segment) {
                                processDiemBatDau(segment);
                            }
                            
                            // Helper function để xử lý pre-fill điểm bắt đầu
                            function processDiemBatDau(segment) {
                                const diemBatDauField = document.getElementById('diem_bat_dau');
                                if (!diemBatDauField) return;
                                
                                // Chỉ pre-fill nếu field đang trống (tránh ghi đè khi người dùng đã nhập)
                                if (diemBatDauField.value.trim() !== '') return;
                                
                                let nextStart = '';
                                if (segment.doi_lenh_tuyen) {
                                    try {
                                        const parsedRoute = JSON.parse(segment.doi_lenh_tuyen);
                                        if (Array.isArray(parsedRoute) && parsedRoute.length > 0) {
                                            const lastEntry = parsedRoute[parsedRoute.length - 1];
                                            if (lastEntry && typeof lastEntry.point === 'string') {
                                                nextStart = lastEntry.point.trim();
                                            }
                                        }
                                    } catch(_){ /* ignore JSON parse errors */ }
                                }
                                if (!nextStart && typeof segment.diem_den === 'string') {
                                    let candidate = segment.diem_den;
                                    if (candidate.includes('→')) {
                                        const parts = candidate.split('→').map(p => p.trim()).filter(Boolean);
                                        if (parts.length > 0) {
                                            candidate = parts[parts.length - 1];
                                        }
                                    } else if (candidate.includes('->')) {
                                        const parts = candidate.split('->').map(p => p.trim()).filter(Boolean);
                                        if (parts.length > 0) {
                                            candidate = parts[parts.length - 1];
                                        }
                                    }
                                    nextStart = candidate;
                                }
                                if (!nextStart && typeof segment.diem_du_kien === 'string' && segment.doi_lenh) {
                                    nextStart = segment.diem_du_kien.trim();
                                }
                                if (!nextStart && typeof segment.diem_den === 'string') {
                                    nextStart = segment.diem_den.trim();
                                }
                                if (nextStart) {
                                    // Loại bỏ lý do dạng "(Đổi lệnh)", "(Lãnh vật tư)", hoặc các lý do tương tự ở cuối chuỗi
                                    nextStart = nextStart.replace(/\s*\((đổi lệnh|lãnh vật tư)\)\s*$/i, '').trim();
                                    // Loại bỏ ký tự unicode full-width ngoặc nếu có trong ghi chú cũ
                                    nextStart = nextStart.replace(/\s*（[^）]*）\s*$/u, '').trim();
                                }
                                if (nextStart) {
                                    diemBatDauField.value = nextStart;
                                    try { diemBatDauField.readOnly = true; } catch(_){}
                                    diemBatDauField.placeholder = 'Đã chọn: ' + nextStart;
                                    // Xóa các cảnh báo "Không tìm thấy thông tin điểm" còn tồn tại trước đó
                                    document.querySelectorAll('.alert').forEach(el => {
                                        try {
                                            if (el.textContent && el.textContent.indexOf('Không tìm thấy thông tin điểm') !== -1) {
                                                el.remove();
                                            }
                                        } catch(_){}
                                    });
                                }
                            }
                            
                            // Xử lý các field khác từ last_segment (nếu có)
                            if (segment) {
                                if (segment.ngay_do_xong) {
                                    document.getElementById('ngay_di').value = formatDateVN(segment.ngay_do_xong);
                                }
                                if (segment.loai_hang) {
                                    document.getElementById('loai_hang').value = segment.loai_hang;
                                }
                            }
                            // Đồng bộ tháng báo cáo theo ngày GẦN NHẤT của dữ liệu chuyến (segments hoặc cap_them)
                            try {
                                const thangSelect = document.getElementById('thang_bao_cao');
                                if (thangSelect) {
                                    const getIsoFromSeg = (seg)=> (seg?.ngay_do_xong || seg?.ngay_den || seg?.ngay_di || '').trim();
                                    let candidateIso = '';
                                    // 1) ưu tiên last_segment nếu có
                                    if (data.last_segment) {
                                        candidateIso = getIsoFromSeg(data.last_segment);
                                    }
                                    // 2) nếu chưa có, lấy max date từ toàn bộ segments
                                    if (!candidateIso && Array.isArray(data.segments) && data.segments.length > 0) {
                                        let maxTime = -1, bestIso = '';
                                        data.segments.forEach(s => {
                                            const iso = getIsoFromSeg(s);
                                            if (iso) {
                                                const t = new Date(iso).getTime();
                                                if (!isNaN(t) && t > maxTime) { maxTime = t; bestIso = iso; }
                                            }
                                        });
                                        candidateIso = bestIso;
                                    }
                                    // 3) nếu vẫn chưa có, lấy max theo created_at của cap_them
                                    if (!candidateIso && Array.isArray(data.cap_them) && data.cap_them.length > 0) {
                                        let maxTime = -1, bestIso = '';
                                        data.cap_them.forEach(ct => {
                                            const iso = (ct?.created_at || '').slice(0,10);
                                            if (iso) {
                                                const t = new Date(iso).getTime();
                                                if (!isNaN(t) && t > maxTime) { maxTime = t; bestIso = iso; }
                                            }
                                        });
                                        candidateIso = bestIso;
                                    }
                                        if (candidateIso) {
                                            const d = new Date(candidateIso);
                                            if (!isNaN(d.getTime())) {
                                                const ym = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
                                                // Chỉ set nếu chưa có giá trị hoặc người dùng chưa chọn tháng cụ thể
                                                if (!thangSelect.value || thangSelect.value === '' || !thangSelect.dataset.userSelected) {
                                                    thangSelect.value = ym;
                                                }
                                            }
                                        }
                                }
                            } catch (e) { /* noop */ }
                            // Render nhật ký chi tiết phía dưới
                            try { renderTripLog(data, tenTau, soChuyen); } catch(e) { console.warn('renderTripLog error', e); }
                        } else {
                            // Chuyến mới hoặc không có dữ liệu, xóa form
                            clearFormData();
                            try { renderTripLog({segments:[], cap_them:[], success:true}, tenTau, soChuyen); } catch(_) {}
                        }
                    })
                    .catch(error => {
                        if (reqToken !== window.__tripReqToken) { return; }
                        console.error('Error fetching trip details:', error);
                        try { renderTripLog({segments:[], cap_them:[], success:true}, tenTau, soChuyen); } catch(_) {}
                    });
            }
        };

        // Function để xóa dữ liệu form
        function clearFormData() {
            const fieldsToReset = ['diem_bat_dau', 'diem_ket_thuc', 'khoi_luong', 'ngay_di', 'ngay_den', 'ngay_do_xong', 'loai_hang', 'ghi_chu'];
            fieldsToReset.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) field.value = '';
            });
            
            // Reset checkboxes
            const checkboxes = ['doi_lenh', 'cap_them'];
            checkboxes.forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox) checkbox.checked = false;
            });
        }

        // Function để format ngày theo định dạng VN
        function formatDateVN(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return dateStr;
            
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }

        // Function để xử lý checkbox "Tạo chuyến mới"
        window.onToggleChuyenMoi = function() {
            if (window.__submittingForm) { return; }
            const chuyenMoiCheckbox = document.getElementById('chuyen_moi');
            const soChuyenSelect = document.getElementById('so_chuyen');
            
            if (chuyenMoiCheckbox && soChuyenSelect) {
                if (chuyenMoiCheckbox.checked) {
                    // Xác định số chuyến mới
                    let nextTrip = parseInt(soChuyenSelect.getAttribute('data-next-trip') || '0');
                    if (isNaN(nextTrip) || nextTrip <= 0) {
                        let maxTrip = 0;
                        soChuyenSelect.querySelectorAll('option').forEach(option => {
                            const tripNum = parseInt(option.value);
                            if (!isNaN(tripNum) && tripNum > maxTrip) maxTrip = tripNum;
                        });
                        nextTrip = maxTrip + 1;
                    }
                    if (!nextTrip || isNaN(nextTrip)) nextTrip = 1;

                    // Khóa dropdown: hiển thị duy nhất mã mới nhưng KHÔNG disable để giữ màu hiển thị
                    soChuyenSelect.innerHTML = '';
                    const fixedOption = document.createElement('option');
                    fixedOption.value = String(nextTrip);
                    fixedOption.textContent = String(nextTrip);
                    soChuyenSelect.appendChild(fixedOption);
                    soChuyenSelect.value = String(nextTrip);
                    soChuyenSelect.selectedIndex = 0;
                    // Cập nhật header/bảng ngay để không còn dữ liệu cũ
                    try {
                        const header = document.querySelector('#tripLogDynamic .card-header h6');
                        if (header) header.innerHTML = `<i class=\"fas fa-list me-2\"></i>Các đoạn của chuyến ${escapeHtml(String(nextTrip))} (sắp xếp theo thứ tự nhập)`;
                        const tbody = document.getElementById('trip_table_body');
                        if (tbody) tbody.innerHTML = `<tr><td colspan=\"6\" class=\"text-muted\">Chưa có dữ liệu cho chuyến này.</td></tr>`;
                        const cardBody = document.querySelector('#tripLogDynamic .card-body');
                        const oldAlert = cardBody ? cardBody.querySelector('.alert.alert-warning') : null;
                        if (oldAlert) { try { oldAlert.parentNode && oldAlert.parentNode.removeChild(oldAlert); } catch(_){ } }
                    } catch(_){ }
                    // Gọi fetch để đồng bộ lại (có loading chống race)
                    try { soChuyenSelect.dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}
                    soChuyenSelect.setAttribute('data-locked', '1');
                    // Chặn tương tác và làm cho nó trông bị vô hiệu hóa
                    soChuyenSelect.style.pointerEvents = 'none';
                    soChuyenSelect.style.backgroundColor = '#e9ecef'; // Màu nền giống disabled
                    soChuyenSelect.title = 'Đang tạo chuyến mới: mã chuyến được cố định';

                    // Xóa dữ liệu form để bắt đầu chuyến mới
                    clearFormData();
                    // Đồng bộ tháng báo cáo về tháng hiện tại khi tạo chuyến mới (chỉ lần đầu bật)
                    try {
                        const thangSelect = document.getElementById('thang_bao_cao');
                        if (thangSelect && !thangSelect.dataset.initByNewTrip && (!thangSelect.value || thangSelect.value === '')) {
                            const now = new Date();
                            const ym = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0');
                            thangSelect.value = ym;
                            thangSelect.dataset.initByNewTrip = '1';
                        }
                    } catch(_){}
                    // Xóa/clear nhật ký vì đang tạo chuyến mới
                    try { renderTripLog({segments:[], cap_them:[], success:true}, document.getElementById('ten_tau')?.value||'', soChuyenSelect.value); } catch(_) {}
                } else {
                    // Mở khóa dropdown và tải lại danh sách chuyến
                    soChuyenSelect.removeAttribute('data-locked');
                    soChuyenSelect.style.pointerEvents = '';
                    soChuyenSelect.style.backgroundColor = ''; // Trả lại màu nền mặc định
                    soChuyenSelect.title = '';
                    const tenTau = document.getElementById('ten_tau').value;
                    if (tenTau) {
                        // Cố gắng gợi ý chọn lại base trip khi tải danh sách chuyến
                        let baseTrip = parseInt(soChuyenSelect.getAttribute('data-max-trip') || '0');
                        if (baseTrip && !isNaN(baseTrip)) {
                            soChuyenSelect.setAttribute('data-preselected', String(baseTrip));
                        }
                        onTauChange(); // Tải lại danh sách chuyến, sau đó chọn preselected
                    }
                }
            }
        };

        // Render nhật ký chi tiết của chuyến vào #tripLogDynamic
        function renderTripLog(data, tenTau, soChuyen){
            const wrap = document.getElementById('tripLogDynamic');
            if (!wrap) return;
            const hasSegments = Array.isArray(data?.segments) && data.segments.length>0;
            const hasCaps = Array.isArray(data?.cap_them) && data.cap_them.length>0;
            if (!hasSegments && !hasCaps) {
                // Nếu đã có khung bảng tĩnh trên server, chỉ cần thay nội dung tbody
                    const tbody = document.getElementById('trip_table_body');
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-muted">Chưa có dữ liệu cho chuyến này.</td></tr>`;
                    return;
                }
                wrap.innerHTML = '';
                return;
            }
            // Hợp nhất và sắp xếp như phía server đã render
            const combined = [];
            (data.segments||[]).forEach((seg, idx)=>{
                combined.push({type:'doan', id: Number(seg.___idx||0), seq: idx, data: seg});
            });
            (data.cap_them||[]).forEach((ct, i)=>{
                combined.push({type:'cap_them', id: Number(ct.___idx||0), seq: 1000+i, data: ct});
            });
            combined.sort((a,b)=> (a.id!==b.id ? a.id-b.id : a.seq-b.seq));
            const capThemCount = (data.cap_them||[]).length;
            const capThemTotal = (data.cap_them||[]).reduce((s,r)=> s + (Number(r.so_luong_cap_them_lit||0)||0), 0);
            let rows = '';
            let stt = 1;
            combined.forEach(row=>{
                if (row.type==='doan'){
                    const d=row.data;
                    const idx = Number(d.___idx||0);
                    rows += `<tr>`+
                        `<td><strong>${stt++}</strong></td>`+
                        `<td>${escapeHtml(d.diem_di||'')}</td>`+
                        `<td>${escapeHtml(d.diem_den||'')}</td>`+
                        `<td>${formatNumber(Number(d.khoi_luong_van_chuyen_t||0))} tấn</td>`+
                        `<td>${formatNumber(Number(d.dau_tinh_toan_lit||0))} lít</td>`+
                        `<td>${escapeHtml(formatDateVN(d.ngay_di||''))}</td>`+
                        `<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditSegmentModal(${idx})" title="Sửa đoạn này"><i class="fas fa-edit"></i> Sửa</button></td>`+
                    `</tr>`;
                } else {
                    const c=row.data;
                    const idx = Number(c.___idx||0);
                    rows += `<tr class="table-warning">`+
                        `<td><span class="badge bg-warning text-dark">Cấp thêm</span></td>`+
                        `<td colspan="2"><span class="text-muted">—</span></td>`+
                        `<td><span class="text-muted">—</span></td>`+
                        `<td><strong>${formatNumber(Number(c.so_luong_cap_them_lit||0))}</strong> lít`+
                        `${c.ly_do_cap_them? `<br><small class="text-muted">Lý do: ${escapeHtml(c.ly_do_cap_them)}</small>`:''}</td>`+
                        `<td><span class="text-muted">—</span></td>`+
                        `<td><button type="button" class="btn btn-sm btn-outline-primary" onclick="openEditSegmentModal(${idx})" title="Sửa cấp thêm này"><i class="fas fa-edit"></i> Sửa</button></td>`+
                    `</tr>`;
                }
            });
            // Nếu có sẵn khung bảng do server render, chỉ thay tbody để tránh nhấp nháy UI
            const existingTbody = document.getElementById('trip_table_body');
            if (existingTbody) {
                // 1) Cập nhật tiêu đề header để khớp số chuyến hiện tại
                try {
                    const header = document.querySelector('#tripLogDynamic .card-header h6');
                    if (header) {
                        header.innerHTML = `<i class="fas fa-list me-2"></i>Các đoạn của chuyến ${escapeHtml(String(soChuyen||''))} (sắp xếp theo thứ tự nhập)`;
                    }
                } catch(_){ /* noop */ }

                // 2) Cập nhật/loại bỏ cảnh báo cấp thêm theo dữ liệu mới
                try {
                    const cardBody = document.querySelector('#tripLogDynamic .card-body');
                    if (cardBody) {
                        const oldAlert = cardBody.querySelector('.alert.alert-warning');
                        if (oldAlert) { try { oldAlert.parentNode && oldAlert.parentNode.removeChild(oldAlert); } catch(e){} }
                        if (capThemCount > 0) {
                            const alertHtml = `<div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">\n                        <i class=\"fas fa-gas-pump me-2\"></i>\n                        <div>Đã có <strong>${capThemCount}</strong> lệnh cấp thêm trong chuyến này. Tổng: <strong>${formatNumber(capThemTotal)}</strong> lít.</div>\n                    </div>`;
                            cardBody.insertAdjacentHTML('afterbegin', alertHtml);
                        }
                    }
                } catch(_){ /* noop */ }

                // 3) Cuối cùng, cập nhật tbody
                existingTbody.innerHTML = rows || `<tr><td colspan="7" class="text-muted">Chưa có dữ liệu cho chuyến này.</td></tr>`;
                return;
            }

            wrap.innerHTML = `
                <div class="card border-info">
                  <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-list me-2"></i>Các đoạn của chuyến ${escapeHtml(String(soChuyen||''))} (sắp xếp theo thứ tự nhập)</h6>
                  </div>
                  <div class="card-body">
                    ${capThemCount>0 ? `<div class=\"alert alert-warning d-flex align-items-center\" role=\"alert\">\n                        <i class=\"fas fa-gas-pump me-2\"></i>\n                        <div>Đã có <strong>${capThemCount}</strong> lệnh cấp thêm trong chuyến này. Tổng: <strong>${formatNumber(capThemTotal)}</strong> lít.</div>\n                    </div>` : ''}
                    <div class="table-responsive">
                      <table class="table table-sm table-striped">
                        <thead>
                          <tr>
                            <th>STT</th><th>Điểm đi</th><th>Điểm đến</th><th>Khối lượng</th><th>Nhiên liệu</th><th>Ngày đi</th><th>Thao tác</th>
                          </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                      </table>
                    </div>
                    <div class="mt-2"><small class="text-muted"><i class="fas fa-plus-circle me-1"></i>Đoạn mới sẽ được thêm vào danh sách trên</small></div>
                  </div>
                </div>
            `;
        }
        function escapeHtml(s){ return (s==null? '': String(s)).replace(/[&<>"']/g, m=> ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m])); }

        // Ngăn thay đổi mã chuyến khi đang ở chế độ tạo chuyến mới
        (function bindSoChuyenGuard(){
            const sel = document.getElementById('so_chuyen');
            if (!sel || sel.dataset.boundGuard) return;
            sel.dataset.boundGuard = '1';
            sel.addEventListener('change', function(){
                if (sel.dataset.locked === '1') {
                    // Trả lại giá trị và cảnh báo nhẹ
                    const val = sel.querySelector('option') ? sel.querySelector('option').value : sel.value;
                    sel.value = val;
                    // Bỏ thông báo khi tạo chuyến mới theo yêu cầu
                }
            });
            // Ngăn mở dropdown khi đang khóa
            sel.addEventListener('mousedown', function(ev){
                if (sel.dataset.locked === '1') { ev.preventDefault(); sel.blur(); }
            });
            sel.addEventListener('keydown', function(ev){
                if (sel.dataset.locked === '1') { ev.preventDefault(); }
            });
        })();

        // Function để validate form
        function validateForm() {
            const formEl = document.querySelector('form[onsubmit*="validateForm"]');
            const submitAction = formEl ? (formEl.dataset.submitAction || 'calculate') : 'calculate';
            const tenTau = document.getElementById('ten_tau').value.trim();
            const soChuyenValue = document.getElementById('so_chuyen') ? document.getElementById('so_chuyen').value.trim() : '';
            const diemBatDau = document.getElementById('diem_bat_dau').value.trim();
            const diemKetThuc = document.getElementById('diem_ket_thuc').value.trim();
            const khoiLuong = document.getElementById('khoi_luong').value.trim();
            // Fix: cap_them là hidden input (không có .checked), nên check toggle checkbox hoặc value
            const toggleCapThem = document.getElementById('toggle_cap_them');
            const capThemChecked = toggleCapThem ? toggleCapThem.checked : false;
            const soLuongCapThem = document.getElementById('so_luong_cap_them') ? document.getElementById('so_luong_cap_them').value.trim() : '';
            const loaiCapThem = document.querySelector('input[name="loai_cap_them"]:checked') ? document.querySelector('input[name="loai_cap_them"]:checked').value : 'bom_nuoc';
            const diaDiemCapThem = document.getElementById('dia_diem_cap_them') ? document.getElementById('dia_diem_cap_them').value.trim() : '';
            const lyDoCapThemKhac = document.getElementById('ly_do_cap_them_khac') ? document.getElementById('ly_do_cap_them_khac').value.trim() : '';
            const doiLenhChecked = document.getElementById('doi_lenh') ? document.getElementById('doi_lenh').checked : false;
            const chuyenMoiChecked = document.getElementById('chuyen_moi') ? document.getElementById('chuyen_moi').checked : false;
            // Lấy danh sách các ô Điểm mới (C/D/E...) và tổng hợp giá trị đầu tiên có nội dung
            const diemMoiInputs = Array.from(document.querySelectorAll('input[name="diem_moi[]"]'));
            const diemMoi = diemMoiInputs.map(el => String(el.value||'').trim()).find(v => v) || '';
            const kcThucTe = document.getElementById('khoang_cach_thuc_te') ? document.getElementById('khoang_cach_thuc_te').value.trim() : '';
            const kcThuCong = document.getElementById('khoang_cach_thu_cong') ? document.getElementById('khoang_cach_thu_cong').value.trim() : '';
            const isShowingResult = !!document.getElementById('has_calc_session');
            
            if (!tenTau) {
                showAlert('Vui lòng chọn tàu', 'warning');
                return false;
            }

            if (submitAction === 'calculate' || !isShowingResult) {
                if (!capThemChecked && !diemBatDau) {
                    showAlert('Vui lòng chọn điểm bắt đầu từ danh sách gợi ý', 'warning');
                    return false;
                }
                
                if (!capThemChecked && !diemKetThuc) {
                    showAlert('Vui lòng chọn điểm kết thúc từ danh sách gợi ý', 'warning');
                    return false;
                }
                
                if (!capThemChecked && diemBatDau === diemKetThuc) {
                    showAlert('Điểm bắt đầu và điểm kết thúc không được giống nhau', 'warning');
                    return false;
                }
                
                // Kiểm tra xem điểm có được chọn từ list không
                const diemBatDauInput = document.getElementById('diem_bat_dau');
                const diemKetThucInput = document.getElementById('diem_ket_thuc');
                
                if (!capThemChecked && diemBatDauInput && diemBatDauInput.readOnly === false) {
                    showAlert('Vui lòng chọn điểm bắt đầu từ danh sách gợi ý', 'warning');
                    return false;
                }
                
                if (!capThemChecked && diemKetThucInput && diemKetThucInput.readOnly === false) {
                    showAlert('Vui lòng chọn điểm kết thúc từ danh sách gợi ý', 'warning');
                    return false;
                }
            }
            
            if (!capThemChecked && (khoiLuong === '' || isNaN(khoiLuong) || parseFloat(khoiLuong) < 0)) {
                showAlert('Vui lòng nhập khối lượng hợp lệ (≥ 0)', 'warning');
                return false;
            }

            if (submitAction === 'save') {
                if (!tenTau) {
                    showAlert('Vui lòng chọn tàu trước khi lưu kết quả', 'warning');
                    return false;
                }
                if (!chuyenMoiChecked && (soChuyenValue === '' || isNaN(parseInt(soChuyenValue, 10)))) {
                    showAlert('Vui lòng chọn mã chuyến hoặc bật "Tạo chuyến mới" trước khi lưu', 'warning');
                    return false;
                }
            }

            // Kiểm tra khoảng cách thủ công nếu cần (cho phép submit khi có giá trị hợp lệ)
            if (!capThemChecked && !doiLenhChecked) {
                const fieldWrap = document.getElementById('khoang_cach_thu_cong_fields');
                const val = parseFloat(kcThuCong);
                const manualActive = (fieldWrap && fieldWrap.style.display !== 'none') || (!isNaN(val) && val > 0);
                if (manualActive) {
                    if (kcThuCong === '' || isNaN(val) || val <= 0) {
                        showAlert('Vui lòng nhập khoảng cách thủ công hợp lệ (> 0)', 'warning');
                        return false;
                    }
                    // Khi dùng khoảng cách thủ công, bỏ qua bắt buộc chọn A/B từ dropdown (readOnly)
                }
            }

            // Đổi lệnh
            if (!capThemChecked && doiLenhChecked && (submitAction === 'calculate' || !isShowingResult)) {
                if (!diemMoi) {
                    showAlert('Vui lòng chọn điểm đến mới (C)', 'warning');
                    return false;
                }
                // Yêu cầu người dùng chọn từ dropdown (input phải ở trạng thái readOnly sau khi chọn)
                const firstDiemMoiInput = document.querySelector('input[name="diem_moi[]"]');
                if (firstDiemMoiInput && firstDiemMoiInput.readOnly === false) {
                    showAlert('Vui lòng chọn điểm đến mới từ danh sách gợi ý', 'warning');
                    return false;
                }
                if (kcThucTe === '' || isNaN(kcThucTe) || parseFloat(kcThucTe) <= 0) {
                    showAlert('Vui lòng nhập tổng khoảng cách thực tế hợp lệ (> 0)', 'warning');
                    return false;
                }
            }

            if (capThemChecked) {
                if (!tenTau) {
                    showAlert('Vui lòng chọn tàu để cấp thêm', 'warning');
                    return false;
                }
                // Kiểm tra theo loại cấp thêm
                if (loaiCapThem === 'khac') {
                    // Nếu chọn "Khác", cần nhập lý do tiêu hao
                    if (!lyDoCapThemKhac || lyDoCapThemKhac.length === 0) {
                        showAlert('Vui lòng nhập lý do tiêu hao', 'warning');
                        return false;
                    }
                } else {
                    // Nếu chọn "Ma nơ" hoặc "Qua cầu", cần nhập địa điểm (lý do sẽ tự động tạo)
                    if (!diaDiemCapThem || diaDiemCapThem.length === 0) {
                        showAlert('Vui lòng nhập địa điểm cấp thêm', 'warning');
                        return false;
                    }
                }
                if (soLuongCapThem === '' || isNaN(soLuongCapThem) || parseFloat(soLuongCapThem) <= 0) {
                    showAlert('Vui lòng nhập số lượng tiêu hao hợp lệ (> 0)', 'warning');
                    return false;
                }
            }
            
            return true;
        }
        
        // Hàm reset điểm
        function resetDiem(inputId) {
            const input = document.getElementById(inputId);
            const resultContainer = document.getElementById(inputId + '_results');
            
            // Reset input
            input.value = '';
            input.readOnly = false;
            input.placeholder = 'Bắt đầu nhập để tìm kiếm...';
            input.classList.remove('is-invalid');
            
            // Ẩn dropdown
            if (resultContainer) {
                resultContainer.style.display = 'none';
                resultContainer.innerHTML = '';
            }
            
            // Nếu reset điểm bắt đầu, cũng reset điểm kết thúc
            if (inputId === 'diem_bat_dau') {
                const diemKetThucInput = document.getElementById('diem_ket_thuc');
                const diemKetThucResults = document.getElementById('diem_ket_thuc_results');
                if (diemKetThucInput) {
                    diemKetThucInput.value = '';
                    diemKetThucInput.readOnly = true;
                    diemKetThucInput.placeholder = 'Chọn điểm bắt đầu trước...';
                    diemKetThucInput.classList.remove('is-invalid');
                }
                if (diemKetThucResults) {
                    diemKetThucResults.style.display = 'none';
                    diemKetThucResults.innerHTML = '';
                }
            }

            // Re-check hiển thị/ẩn khoảng cách thủ công sau khi reset A/B
            if (typeof checkAndShowManualDistance === 'function') {
                try { checkAndShowManualDistance(); } catch(_) {}
            }
        }
        
        // Khởi tạo tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const calcForm = document.querySelector('form[onsubmit*="validateForm"]');
            if (calcForm) {
                calcForm.dataset.submitAction = 'calculate';
                calcForm.querySelectorAll('button[name="action"]').forEach(btn => {
                    btn.addEventListener('click', function() {
                        calcForm.dataset.submitAction = btn.value || 'calculate';
                        if (calcForm.dataset.submitAction === 'save') {
                            window.__submittingForm = true;
                        }
                    });
                });
                calcForm.querySelectorAll('input[name="action"]').forEach(input => {
                    input.addEventListener('change', function() {
                        calcForm.dataset.submitAction = input.value || 'calculate';
                        if (calcForm.dataset.submitAction === 'save') {
                            window.__submittingForm = true;
                        }
                    });
                });
            }
            // Nếu đang hiển thị kết quả từ session (show=1), khóa handler trong thời gian ngắn để tránh reset
            (function guardWhenShowingCalc(){
                const hasSession = document.getElementById('has_calc_session');
                if (hasSession) {
                    window.__submittingForm = true;
                    setTimeout(function(){ window.__submittingForm = false; }, 3000);
                }
            })();
            // Fix #16: Đảm bảo mã chuyến không bị khóa không cần thiết khi page load
            (function initSoChuyenState(){
                const chuyenMoiCheckbox = document.getElementById('chuyen_moi');
                const soChuyenSelect = document.getElementById('so_chuyen');
                if (!soChuyenSelect) return;

                // Nếu checkbox "Tạo chuyến mới" được tick, gọi onToggleChuyenMoi để set đúng trạng thái
                if (chuyenMoiCheckbox && chuyenMoiCheckbox.checked) {
                    // Đảm bảo hàm onToggleChuyenMoi đã được định nghĩa
                    if (typeof window.onToggleChuyenMoi === 'function') {
                        setTimeout(function(){ window.onToggleChuyenMoi(); }, 100);
                    }
                } else {
                    // Đảm bảo mã chuyến KHÔNG bị khóa khi không tick "Tạo chuyến mới"
                    soChuyenSelect.removeAttribute('data-locked');
                    soChuyenSelect.style.pointerEvents = '';
                    soChuyenSelect.style.backgroundColor = '';
                    soChuyenSelect.title = '';
                }
            })();
            // Khi đang hiển thị kết quả (show=1), các ô điểm đã có giá trị cần được khóa readOnly
            // để vượt qua validateForm() yêu cầu "chọn từ danh sách gợi ý"
            (function lockPrefilledPointInputs(){
                const hasSession = document.getElementById('has_calc_session');
                if (!hasSession) return;
                const lockIfFilled = (id) => {
                    const el = document.getElementById(id);
                    if (el && String(el.value||'').trim()) {
                        try { el.readOnly = true; } catch(e) {}
                        try { el.placeholder = 'Đã chọn: ' + el.value; } catch(e) {}
                        el.classList.remove('is-invalid');
                    }
                };
                lockIfFilled('diem_bat_dau');
                lockIfFilled('diem_ket_thuc');
                // Trường đổi lệnh C (nếu đang bật và đã có giá trị)
                const doiLenh = document.getElementById('doi_lenh');
                if (doiLenh && doiLenh.checked) {
                    // Khoá tất cả các ô C/D/E đã có giá trị
                    document.querySelectorAll('input[name="diem_moi[]"]').forEach(el => {
                        if (String(el.value||'').trim()) {
                            try { el.readOnly = true; } catch(e) {}
                            el.classList.remove('is-invalid');
                        }
                    });
                }
            })();
            // Smooth scroll to results after form submission
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('show') === '1' || document.getElementById('ket-qua-tinh-toan')) {
                setTimeout(() => {
                    const resultsSection = document.getElementById('ket-qua-tinh-toan');
                    if (resultsSection) {
                        resultsSection.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start',
                            inline: 'nearest'
                        });
                    }
                }, 100);
            }

            // Đảm bảo chỉ có đúng 1 modal chọn tàu trong DOM (nếu trùng lặp do render)
            (function ensureSingleExtraShipsModal(){
                const modals = document.querySelectorAll('#extraShipsModal');
                if (modals.length > 1) {
                    for (let i = 1; i < modals.length; i++) {
                        try { modals[i].parentNode && modals[i].parentNode.removeChild(modals[i]); } catch(_){}
                    }
                }
                // Nếu modal nằm trong form, di chuyển ra cuối body để tránh reflow/submit ảnh hưởng
                const m = document.getElementById('extraShipsModal');
                if (m && m.closest('form')) {
                    try { document.body.appendChild(m); } catch(_){}
                }
                // Xóa backdrop thừa nếu có (giữ 1 cái tối đa khi hiển thị)
                const backs = document.querySelectorAll('.modal-backdrop');
                if (backs.length > 1) {
                    for (let i = 1; i < backs.length; i++) {
                        try { backs[i].parentNode && backs[i].parentNode.removeChild(backs[i]); } catch(_){}
                    }
                }
            })();
            // Bỏ auto-submit khi đổi dropdown Tàu ở trang Lịch sử (chỉ lọc khi người dùng bấm Lọc)
            const shipSelect = document.querySelector('form[action$="lich_su.php" i] select[name="ten_phuong_tien"], form select[name="ten_phuong_tien"]');
            if (shipSelect) {
                // Không tự submit nữa; chỉ đánh dấu đã thay đổi để người dùng bấm nút Lọc
                shipSelect.addEventListener('change', function(){ /* no auto-submit */ });
            }

            // Đảm bảo nút Lọc không bị dính tham số xuất báo cáo còn sót lại
            (function(){
                const form = document.querySelector('form[action$="lich_su.php" i]') || document.querySelector('form');
                if (!form) return;
                const filterBtn = form.querySelector('button[name="filter"][type="submit"]');
                if (!filterBtn || filterBtn.dataset.boundCleanExport) return;
                filterBtn.dataset.boundCleanExport = '1';
                const removeExportParams = ()=>{
                    try {
                        form.querySelectorAll('input[name="export"], input[name="xlsx"], input[name="from"], input[name="to"], input[name="extra_ships[]"], input[name^="notai_date"], input[name^="notai_amount"]').forEach(n=>n.remove());
                    } catch(_){ /* ignore */ }
                };
                // Xóa tham số ngay khi bấm Lọc và cả trước khi submit form (phòng trường hợp submit bằng phím tắt)
                filterBtn.addEventListener('click', removeExportParams);
                form.addEventListener('submit', function(){
                    // Chỉ áp dụng khi người dùng thực sự lọc (có nút filter trong form này)
                    removeExportParams();
                });
            })();

            // Xuất Excel: hỏi có muốn xuất thêm sheet chi tiết theo tàu không (themed overlay)
            const exportBtn = document.getElementById('exportExcelBtn');
            const overlay = document.getElementById('extraShipsOverlay');
            const btnClose = document.getElementById('extraShipsClose');
            const btnCancel = document.getElementById('extraShipsCancel');
            const btnConfirm = document.getElementById('extraShipsConfirm');
            const btnDefault = document.getElementById('extraShipsDefault');
            const toggleDetail = document.getElementById('toggleDetailArea');
            const detailArea = document.getElementById('detailArea');
            const searchInput = document.getElementById('extraShipsSearch');
            const listWrap = document.getElementById('extraShipsList');
            const btnSelectAll = document.getElementById('extraShipsSelectAll');
            const btnClear = document.getElementById('extraShipsClear');
            if (exportBtn && !exportBtn.dataset.boundExport) {
                exportBtn.dataset.boundExport = '1';
                exportBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    const form = exportBtn.closest('form');
                    if (!form) return;
                    if (overlay) { 
                        // Nếu overlay đang nằm trong card lọc, di chuyển ra body để tránh bị ẩn khi ẩn card
                        if (!overlay.dataset.movedToBody) {
                            try { document.body.appendChild(overlay); overlay.dataset.movedToBody = '1'; } catch (err) {}
                        }
                        overlay.style.display = 'block'; 
                        document.body.classList.add('no-scroll'); 
                        const listCard = document.getElementById('historyListCard');
                        const filterCard = document.getElementById('historyFilterCard');
                        if (listCard) listCard.style.display = 'none';
                        if (filterCard) filterCard.style.display = 'none';
                    }
                });
            }
            const hideOverlay = ()=>{ 
                if (overlay){ 
                    overlay.style.display='none'; 
                    document.body.classList.remove('no-scroll'); 
                    const listCard = document.getElementById('historyListCard');
                    const filterCard = document.getElementById('historyFilterCard');
                    if (listCard) listCard.style.display = '';
                    if (filterCard) filterCard.style.display = '';
                } 
            };
            btnClose && btnClose.addEventListener('click', hideOverlay);
            btnCancel && btnCancel.addEventListener('click', hideOverlay);
            if (toggleDetail && !toggleDetail.dataset.boundToggle) {
                toggleDetail.dataset.boundToggle = '1';
                toggleDetail.addEventListener('click', function(){
                    if (!detailArea) return;
                    const show = (detailArea.style.display !== 'block');
                    detailArea.style.display = show ? 'block' : 'none';
                    if (show && searchInput) { try { searchInput.focus(); } catch(e){} }
                    // Khi mở detailArea, khởi tạo wizard nếu chưa có
                    const wizard = document.getElementById('detailWizard');
                    if (wizard && window.flatpickr) {
                        try { flatpickr(wizard.querySelectorAll('.vn-date'), { dateFormat:'d/m/Y', allowInput:true, locale:'vn' }); } catch(e){}
                    }
                });
            }
            if (btnDefault && !btnDefault.dataset.boundDefault) {
                btnDefault.dataset.boundDefault = '1';
                btnDefault.addEventListener('click', function(){
                    const form = document.querySelector('form[action$="lich_su.php" i]') || document.querySelector('form');
                    if (!form) return;
                    form.querySelectorAll('input[name="extra_ships[]"]').forEach(n=>n.remove());
                    form.querySelectorAll('input[name="export"]').forEach(n=>n.remove());
                    form.querySelectorAll('input[name="filter"]').forEach(n=>n.remove());
                    form.querySelectorAll('input[name="xlsx"]').forEach(n=>n.remove());
                    const ex = document.createElement('input'); ex.type='hidden'; ex.name='export'; ex.value='excel'; form.appendChild(ex);
                    const xlsx = document.createElement('input'); xlsx.type='hidden'; xlsx.name='xlsx'; xlsx.value='1'; form.appendChild(xlsx);
                    form.submit();
                    hideOverlay();
                });
            }
            if (btnConfirm && !btnConfirm.dataset.boundConfirm) {
                btnConfirm.dataset.boundConfirm = '1';
                btnConfirm.addEventListener('click', function(){
                    const form = document.querySelector('form[action$="lich_su.php" i]') || document.querySelector('form');
                    if (!form) return;
                    // Xóa cũ
                    form.querySelectorAll('input[name="extra_ships[]"]').forEach(n=>n.remove());
                    // Thu thập chọn
                    const selected = Array.from(document.querySelectorAll('.extra-ship-item:checked')).map(chk=>chk.value);
                    selected.forEach(val=>{ const h=document.createElement('input'); h.type='hidden'; h.name='extra_ships[]'; h.value=val; form.appendChild(h); });

                    // Wizard mini-form trong overlay: hỏi tuần tự với Back/Skip/Next/Done
                    const wizard = document.getElementById('detailWizard');
                    const shipNameEl = document.getElementById('wizardShipName');
                    const progressEl = document.getElementById('wizardProgress');
                    const dateEl = document.getElementById('wizardNotaiDate');
                    const amtEl = document.getElementById('wizardNotaiAmount');
                    const btnBack = document.getElementById('wizardBack');
                    const btnSkip = document.getElementById('wizardSkip');
                    const btnNext = document.getElementById('wizardNext');
                    const btnDone = document.getElementById('wizardDone');
                    const btnApplyAll = document.getElementById('wizardApplyAll');

                    const ddmmyyyy = /^\d{1,2}\/\d{1,2}\/\d{4}$/;
                    const answers = {}; // { ship: {date, amount} }
                    let idx = 0;

                    const updateUI = ()=>{
                        if (!wizard) return;
                        wizard.style.display = 'block';
                        const total = selected.length;
                        const ship = selected[idx] || '';
                        if (shipNameEl) shipNameEl.textContent = ship;
                        if (progressEl) progressEl.textContent = `${idx+1}/${total}`;
                        // Prefill nếu đã có
                        const a = answers[ship] || {};
                        if (dateEl) dateEl.value = a.date || '';
                        if (amtEl) amtEl.value = a.amount || '';
                        if (btnBack) btnBack.disabled = idx === 0;
                        if (btnNext) btnNext.classList.toggle('d-none', idx >= total-1);
                        if (btnDone) btnDone.classList.toggle('d-none', idx < total-1);
                    };

                    const persistCurrent = ()=>{
                        const ship = selected[idx];
                        const dateVal = dateEl ? (dateEl.value||'').trim() : '';
                        const amtVal = amtEl ? (amtEl.value||'').trim() : '';
                        answers[ship] = { date: (ddmmyyyy.test(dateVal) ? dateVal : ''), amount: (amtVal!=='' && !isNaN(amtVal) ? amtVal : '') };
                    };

                    const goSubmit = ()=>{
                        // push answers vào form dưới dạng notai_date[ship], notai_amount[ship]
                        Object.keys(answers).forEach(ship=>{
                            const a = answers[ship];
                            if (a.date) { const h1=document.createElement('input'); h1.type='hidden'; h1.name=`notai_date[${ship}]`; h1.value=a.date; form.appendChild(h1); }
                            if (a.amount!=='') { const h2=document.createElement('input'); h2.type='hidden'; h2.name=`notai_amount[${ship}]`; h2.value=a.amount; form.appendChild(h2); }
                        });
                        // Lấy khoảng ngày ưu tiên từ overlay; nếu trống fallback về filter
                        const overlayFrom = document.getElementById('detailFromDate');
                        const overlayTo = document.getElementById('detailToDate');
                        let fromVal = overlayFrom ? (overlayFrom.value||'').trim() : '';
                        let toVal = overlayTo ? (overlayTo.value||'').trim() : '';
                        if (!toVal) {
                            const today = new Date();
                            const dd = String(today.getDate()).padStart(2,'0');
                            const mm = String(today.getMonth()+1).padStart(2,'0');
                            const yyyy = today.getFullYear();
                            toVal = `${dd}/${mm}/${yyyy}`;
                        }
                        if (!fromVal) {
                            const tu = form.querySelector('input[name="tu_ngay"]');
                            if (tu && (tu.value||'').trim()) fromVal = (tu.value||'').trim();
                        }
                        if (!fromVal) { fromVal = toVal; }
                        const hFrom=document.createElement('input'); hFrom.type='hidden'; hFrom.name='from'; hFrom.value=fromVal; form.appendChild(hFrom);
                        const hTo=document.createElement('input'); hTo.type='hidden'; hTo.name='to'; hTo.value=toVal; form.appendChild(hTo);
                        form.querySelectorAll('input[name="export"]').forEach(n=>n.remove());
                        form.querySelectorAll('input[name="filter"]').forEach(n=>n.remove());
                        form.querySelectorAll('input[name="xlsx"]').forEach(n=>n.remove());
                        const ex = document.createElement('input'); ex.type='hidden'; ex.name='export'; ex.value='excel'; form.appendChild(ex);
                        // Xuất CHI TIẾT ở định dạng XLSX với header/logo template
                        const xlsx = document.createElement('input'); xlsx.type='hidden'; xlsx.name='xlsx'; xlsx.value='1'; form.appendChild(xlsx);
                        form.submit();
                        hideOverlay();
                    };

                    // Bind buttons once per open
                    if (btnBack && !btnBack.dataset.bound) {
                        btnBack.dataset.bound = '1';
                        btnBack.addEventListener('click', ()=>{ persistCurrent(); if (idx>0) idx--; updateUI(); });
                    }
                    if (btnSkip && !btnSkip.dataset.bound) {
                        btnSkip.dataset.bound = '1';
                        btnSkip.addEventListener('click', ()=>{ answers[selected[idx]] = {date:'',amount:''}; if (idx < selected.length-1) { idx++; updateUI(); } else { goSubmit(); } });
                    }
                    if (btnNext && !btnNext.dataset.bound) {
                        btnNext.dataset.bound = '1';
                        btnNext.addEventListener('click', ()=>{ persistCurrent(); if (idx < selected.length-1) { idx++; updateUI(); } });
                    }
                    if (btnDone && !btnDone.dataset.bound) {
                        btnDone.dataset.bound = '1';
                        btnDone.addEventListener('click', ()=>{ persistCurrent(); goSubmit(); });
                    }
                    if (btnApplyAll && !btnApplyAll.dataset.bound) {
                        btnApplyAll.dataset.bound = '1';
                        btnApplyAll.addEventListener('click', ()=>{
                            // Lấy giá trị hiện tại và áp cho tất cả tàu còn lại
                            const dateVal = dateEl ? (dateEl.value||'').trim() : '';
                            const amtVal = amtEl ? (amtEl.value||'').trim() : '';
                            for (let i = idx; i < selected.length; i++) {
                                const ship = selected[i];
                                answers[ship] = {
                                    date: (ddmmyyyy.test(dateVal) ? dateVal : ''),
                                    amount: (amtVal!=='' && !isNaN(amtVal) ? amtVal : '')
                                };
                            }
                            goSubmit();
                        });
                    }

                    // Bắt đầu wizard nếu có chọn tàu; nếu không, xuất luôn mặc định
                    if (selected.length > 0 && wizard) { idx = 0; updateUI(); return; }
                    form.querySelectorAll('input[name="export"]').forEach(n=>n.remove());
                    form.querySelectorAll('input[name="filter"]').forEach(n=>n.remove());
                    form.querySelectorAll('input[name="xlsx"]').forEach(n=>n.remove());
                    const ex = document.createElement('input'); ex.type='hidden'; ex.name='export'; ex.value='excel'; form.appendChild(ex);
                    const xlsx = document.createElement('input'); xlsx.type='hidden'; xlsx.name='xlsx'; xlsx.value='1'; form.appendChild(xlsx);
                    form.submit();
                    hideOverlay();
                });
            }
            if (searchInput && listWrap && !searchInput.dataset.boundSearch) {
                searchInput.dataset.boundSearch = '1';
                const doFilter = ()=>{
                    const q = (searchInput.value || '').toLowerCase();
                    listWrap.querySelectorAll('.list-group-item').forEach(item=>{
                        const label = item.textContent.toLowerCase();
                        item.style.display = (!q || label.indexOf(q) !== -1) ? '' : 'none';
                    });
                };
                searchInput.addEventListener('input', doFilter);
            }
            if (btnSelectAll && !btnSelectAll.dataset.boundSelAll) {
                btnSelectAll.dataset.boundSelAll = '1';
                btnSelectAll.addEventListener('click', ()=>{
                    document.querySelectorAll('.extra-ship-item').forEach(chk=>{ chk.checked = true; });
                });
            }
            if (btnClear && !btnClear.dataset.boundClear) {
                btnClear.dataset.boundClear = '1';
                btnClear.addEventListener('click', ()=>{
                    document.querySelectorAll('.extra-ship-item').forEach(chk=>{ chk.checked = false; });
                });
            }
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Khởi tạo flatpickr cho các ô ngày theo định dạng dd/mm/yyyy
            if (window.flatpickr) {
                const opts = { dateFormat: 'd/m/Y', allowInput: true, locale: 'vn' };
                document.querySelectorAll('.vn-date').forEach(el => flatpickr(el, opts));
                document.querySelectorAll('input[name="tu_ngay"], input[name="den_ngay"], #detailFromDate, #detailToDate').forEach(el => flatpickr(el, opts));
                const toEl = document.getElementById('detailToDate');
                if (toEl && !toEl.value) {
                    const today = new Date();
                    const dd = String(today.getDate()).padStart(2,'0');
                    const mm = String(today.getMonth()+1).padStart(2,'0');
                    const yyyy = today.getFullYear();
                    toEl.value = `${dd}/${mm}/${yyyy}`;
                }
            }

    // Month picker (history page) – sync with tu_ngay/den_ngay
    (function initMonthPicker(){
      const hiddenMonth = document.getElementById('filter_month');
      const btn = document.getElementById('monthPickerBtn');
      if (!hiddenMonth || !btn) return;
      const label = document.getElementById('monthPickerLabel');
      const panel = document.getElementById('monthPickerPanel');
      const yearEl = document.getElementById('monthPickerYear');
      const prevBtn = document.getElementById('monthPickerPrevYear');
      const nextBtn = document.getElementById('monthPickerNextYear');
      const grid = document.getElementById('monthPickerGrid');
      const clearBtn = document.getElementById('monthPickerClear');
      const applyBtn = document.getElementById('monthPickerApply');
      const tuEl = document.querySelector('input[name="tu_ngay"]');
      const denEl = document.querySelector('input[name="den_ngay"]');

      const toLabel = (ym) => {
        if (!ym) return '--/----';
        const [y,m] = ym.split('-');
        return (m && y) ? (m.padStart(2,'0') + '/' + y) : '--/----';
      };

      let currentYear = new Date().getFullYear();
      const setYear = (y)=>{ currentYear = y; yearEl && (yearEl.textContent = String(y)); };
      setYear(new Date().getFullYear());

      // Initialize from hidden value
      label.textContent = toLabel(hiddenMonth.value);
      btn.addEventListener('click', ()=>{
        if (!panel) return; panel.style.display = (panel.style.display==='none' || panel.style.display==='') ? 'block' : 'none';
      });

      const selectMonthBtn = (m)=>{
        grid.querySelectorAll('.month-item').forEach(b=>{
          const isSel = Number(b.dataset.month) === m;
          b.classList.toggle('btn-primary', isSel);
          b.classList.toggle('btn-outline-primary', !isSel);
        });
      };

      let selectedMonth = null;
      if (hiddenMonth.value) {
        const parts = hiddenMonth.value.split('-');
        if (parts.length===2) { setYear(Number(parts[0])); selectedMonth = Number(parts[1]); selectMonthBtn(selectedMonth); }
      }

      prevBtn && prevBtn.addEventListener('click', ()=> setYear(currentYear - 1));
      nextBtn && nextBtn.addEventListener('click', ()=> setYear(currentYear + 1));
      grid && grid.addEventListener('click', (e)=>{
        const t = e.target.closest('.month-item');
        if (!t) return;
        selectedMonth = Number(t.dataset.month);
        selectMonthBtn(selectedMonth);
      });
      clearBtn && clearBtn.addEventListener('click', ()=>{
        selectedMonth = null; hiddenMonth.value=''; label.textContent='--/----';
        selectMonthBtn(-1);
        if (tuEl) tuEl.value=''; if (denEl) denEl.value='';
      });
      applyBtn && applyBtn.addEventListener('click', ()=>{
        if (!selectedMonth) return;
        const ym = String(currentYear) + '-' + String(selectedMonth).padStart(2,'0');
        hiddenMonth.value = ym;
        label.textContent = toLabel(ym);
        // set tu_ngay/den_ngay boundaries for the chosen month
        const first = new Date(currentYear, selectedMonth - 1, 1);
        const last = new Date(currentYear, selectedMonth, 0);
        const fmt = (d)=> ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();
        if (tuEl) tuEl.value = fmt(first);
        if (denEl) denEl.value = fmt(last);
        if (panel) panel.style.display = 'none';
        // Khi chọn tháng mới: reset chọn Tàu để tránh trạng thái "lọc theo tháng nhưng vẫn còn tàu cũ"
        const form = btn.closest('form') || document.querySelector('form[action$="lich_su.php" i]') || document.querySelector('form');
        if (form) {
          const shipSelect = form.querySelector('select[name="ten_phuong_tien"]');
          if (shipSelect) {
            shipSelect.value = '';
          }
          // Reset các dropdown phụ thuộc
          const depNames = ['so_chuyen','diem_di','diem_den','loai_hang'];
          depNames.forEach(n=>{
            const sel = form.querySelector(`select[name="${n}"]`);
            if (sel) sel.value = '';
          });
          // Tự submit để refresh danh sách theo tháng mới (không còn chọn tàu)
          // Xóa các tham số export để tránh xuất báo cáo không mong muốn
          form.querySelectorAll('input[name="export"]').forEach(n=>n.remove());
          form.querySelectorAll('input[name="extra_ships[]"]').forEach(n=>n.remove());
          form.submit();
        }

            // Bỏ quick-add loại hàng: quản lý giống cây xăng (qua trang quản trị)
      });
    })();

            // Bỏ khóa ngày sau khi submit: đảm bảo có thể chỉnh sửa Ngày đi/đến/dỡ xong
            // Tôn trọng chế độ Cấp thêm: không mở khóa Ngày đi khi đang cấp thêm
            (function unlockDateFields() {
                const capThem = document.getElementById('cap_them');
                const capThemOn = !!(capThem && capThem.checked);
                const ids = ['ngay_di','ngay_den','ngay_do_xong'];
                ids.forEach(id => {
                    // Nếu đang Cấp thêm, không mở khóa 'ngay_di' vì nó sẽ được tự động lấy từ chuyến trước
                    if (capThemOn && id === 'ngay_di') return;
                    const el = document.getElementById(id);
                    if (!el) return;
                    try { el.readOnly = false; } catch (e) {}
                    try { el.removeAttribute('disabled'); } catch (e) {}
                });
            })();

        // Toggle khu vực Cấp thêm
        const capThemCheckbox = document.getElementById('cap_them');
        const capThemFields = document.getElementById('cap_them_fields');
        const diaDiemCapThemInput = document.getElementById('dia_diem_cap_them');
        const soLuongCapThemInput = document.getElementById('so_luong_cap_them');
        const lyDoCapThemWrapper = document.getElementById('ly_do_cap_them_wrapper');
        const lyDoCapThemInput = document.getElementById('ly_do_cap_them_khac');
        const loaiCapThemRadios = Array.from(document.querySelectorAll('input[name="loai_cap_them"]'));

        const setFieldState = (el, required, disabled) => {
            if (!el) return;
            el.required = !!required;
            if (!required) {
                try { el.removeAttribute('required'); } catch(_) {}
            }
            el.disabled = !!disabled;
            if (disabled) {
                try { el.setAttribute('disabled', 'disabled'); } catch(_) {}
            } else {
                try { el.removeAttribute('disabled'); } catch(_) {}
            }
        };

        // ====== LOGIC CŨ - ĐÃ DISABLE VÌ DÙNG TOGGLE SWITCH MỚI ======
        // const updateLyDoCapThemState = () => {
        //     const capOn = capThemCheckbox ? capThemCheckbox.checked : false;
        //     const loaiKhacRadio = document.getElementById('loai_khac');
        //     const loaiKhacChecked = !!(loaiKhacRadio && loaiKhacRadio.checked);
        //     const shouldShow = capOn && loaiKhacChecked;
        //     if (lyDoCapThemWrapper) {
        //         lyDoCapThemWrapper.style.display = shouldShow ? '' : 'none';
        //     }
        //     // Không disable nữa - chỉ set required
        //     if (lyDoCapThemInput) {
        //         lyDoCapThemInput.required = shouldShow;
        //         // Luôn enable
        //         lyDoCapThemInput.disabled = false;
        //         lyDoCapThemInput.removeAttribute('disabled');
        //     }
        // };
        // ====== END LOGIC CŨ ======

        if (capThemCheckbox && capThemFields) {
            const toggleFields = () => {
                const capOn = capThemCheckbox.checked;
                capThemFields.style.display = capOn ? 'block' : 'none';
                
                // Xử lý trường ngày đi khi bật/tắt cấp thêm
                const ngayDiInput = document.getElementById('ngay_di');
                const ngayDiHelp = document.getElementById('ngay_di_help');
                const ngayCapGroup = document.getElementById('ngay_cap_them_group');
                const ngayCapInput = document.getElementById('ngay_cap_them');
                const ngayDiGroup = ngayDiInput ? (ngayDiInput.closest('.col-md-4') || ngayDiInput.closest('.mb-3')) : null;
                if (ngayDiGroup) {
                    ngayDiGroup.style.display = capOn ? 'none' : '';
                }
                if (ngayCapGroup) {
                    ngayCapGroup.style.display = capOn ? 'block' : 'none';
                }
                if (ngayCapInput) {
                    // Ngày cấp thêm không còn bắt buộc
                    ngayCapInput.required = false;
                    
                    // Cập nhật hiển thị dấu * và hint
                    const ngayCapRequired = document.querySelector('.ngay-cap-required');
                    const ngayCapHint = document.querySelector('.ngay-cap-hint');
                    if (ngayCapRequired) {
                        ngayCapRequired.style.display = 'none';
                    }
                    if (ngayCapHint && capOn) {
                        ngayCapHint.textContent = 'Chọn ngày cấp thêm thực tế (tùy chọn)';
                    }
                }
                if (ngayCapInput && ngayDiInput && capOn && !ngayCapInput.value) {
                    ngayCapInput.value = ngayDiInput.value;
                }
                if (ngayCapInput && ngayDiInput && capOn) {
                    ngayDiInput.value = ngayCapInput.value;
                }
                if (ngayCapInput && ngayDiInput && !capOn && ngayCapInput.value) {
                    ngayDiInput.value = ngayCapInput.value;
                }
                if (ngayCapInput && !capOn) {
                    ngayCapInput.required = false;
                }
                
                if (ngayDiInput && ngayDiHelp) {
                    if (capOn) {
                        ngayDiInput.readOnly = true;
                        ngayDiHelp.style.display = 'block';
                    } else {
                        ngayDiInput.readOnly = false;
                        ngayDiHelp.style.display = 'none';
                    }
                }

                // Ẩn/hiện các trường không cần thiết khi chọn Cấp thêm
                // Giữ lại 'so_chuyen' (mã chuyến) và 'ngay_di' (ngày cấp)
                const ids = ['diem_bat_dau','diem_ket_thuc','khoi_luong','ngay_den','ngay_do_xong','loai_hang'];
                ids.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    let group = null;
                    if (id.startsWith('ngay_')) {
                        group = el.closest('.col-md-4') || el.closest('.mb-3');
                    } else {
                        group = el.closest('.mb-3');
                    }
                    if (group) group.style.display = capOn ? 'none' : '';
                });

                // Tắt required/disabled cho các trường bị ẩn để tránh lỗi HTML5 validation
                // Không bắt buộc các trường ngày (ngày đến, ngày dỡ xong) theo yêu cầu "Bỏ khóa ngày"
                const idsRequired = ['diem_bat_dau','diem_ket_thuc','khoi_luong'];
                idsRequired.forEach(id => {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.required = !capOn;
                    if (capOn) {
                        el.setAttribute('disabled', 'disabled');
                    } else {
                        el.removeAttribute('disabled');
                    }
                });

                setFieldState(diaDiemCapThemInput, capOn, false); // KHÔNG disable
                setFieldState(soLuongCapThemInput, capOn, false); // KHÔNG disable

                // Force enable lại sau khi setFieldState (đảm bảo không bị logic khác can thiệp)
                if (diaDiemCapThemInput) {
                    diaDiemCapThemInput.disabled = false;
                    diaDiemCapThemInput.removeAttribute('disabled');
                }
                if (soLuongCapThemInput) {
                    soLuongCapThemInput.disabled = false;
                    soLuongCapThemInput.removeAttribute('disabled');
                }

                // updateLyDoCapThemState(); // DISABLED - dùng logic mới trong index.php

                // Xử lý riêng cho loại hàng: chỉ bắt buộc khi có khối lượng > 0
                const loaiHangEl = document.getElementById('loai_hang');
                const khoiLuongEl = document.getElementById('khoi_luong');
                if (loaiHangEl && khoiLuongEl) {
                    const getKhongHangValue = () => {
                        try {
                            const opts = Array.from(loaiHangEl.options || []);
                            const opt = opts.find(o => String(o.text || o.value || '').trim().toLowerCase() === 'không hàng');
                            return opt ? opt.value : null;
                        } catch(_) { return null; }
                    };
                    const khongHangValue = getKhongHangValue();

                    const updateLoaiHangRequiredAndValue = () => {
                        const khoiLuong = parseFloat(khoiLuongEl.value) || 0;
                        const shouldDisable = capThemCheckbox.checked;
                        loaiHangEl.required = !shouldDisable && khoiLuong > 0;
                        if (shouldDisable) {
                            loaiHangEl.setAttribute('disabled', 'disabled');
                        } else {
                            loaiHangEl.removeAttribute('disabled');
                        }

                        if (!shouldDisable) {
                            if (khoiLuong === 0 && khongHangValue !== null) {
                                loaiHangEl.value = khongHangValue;
                                loaiHangEl.classList.remove('is-invalid');
                            } else if (khoiLuong > 0 && khongHangValue !== null && loaiHangEl.value === khongHangValue) {
                                loaiHangEl.value = '';
                            }
                        }
                    };

                    khoiLuongEl.addEventListener('input', updateLoaiHangRequiredAndValue);
                    updateLoaiHangRequiredAndValue();
                }

                // ĐÃ BỎ: Không vô hiệu hóa số chuyến khi khối lượng = 0
                // Vì tàu có thể chạy không hàng (khối lượng = 0) và vẫn cần mã chuyến để lưu

                // Khi Cấp thêm: bắt buộc nhập mã chuyến và ngày cấp (ngày đi)
                const soChuyen = document.getElementById('so_chuyen');
                const ngayDi = document.getElementById('ngay_di');
                if (soChuyen) soChuyen.required = capOn;
                if (ngayDi) ngayDi.required = false;

                // Đổi nhãn ngày đi -> ngày cấp khi bật Cấp thêm
                const labelNgayDi = document.getElementById('label_ngay_di');
                if (labelNgayDi) {
                    const span = labelNgayDi.querySelector('.label-text');
                    if (span) span.textContent = capOn ? 'Ngày cấp' : 'Ngày đi';
                }
            };

            capThemCheckbox.addEventListener('change', () => {
                toggleFields();
                // updateLyDoCapThemState(); // DISABLED - dùng logic mới trong index.php
            });
            loaiCapThemRadios.forEach(radio => {
                // radio.addEventListener('change', updateLyDoCapThemState); // DISABLED - dùng logic mới trong index.php
            });
            toggleFields();
        }
        // updateLyDoCapThemState(); // DISABLED - dùng logic mới trong index.php

            // Toggle khu vực Đổi lệnh
            const doiLenhCheckbox = document.getElementById('doi_lenh');
            const doiLenhFields = document.getElementById('doi_lenh_fields');
            if (doiLenhCheckbox && doiLenhFields) {
                const toggleDoiLenh = () => {
                    const on = doiLenhCheckbox.checked;
                    doiLenhFields.style.display = on ? 'block' : 'none';
                    const diemMoi = document.querySelector('input[name="diem_moi[]"]');
                    const kcThucTe = document.getElementById('khoang_cach_thuc_te');
                    if (diemMoi) { diemMoi.required = on; diemMoi.disabled = !on; }
                    if (kcThucTe) { kcThucTe.required = on; kcThucTe.disabled = !on; }

                    // Kiểm tra và hiển thị/ẩn trường khoảng cách thủ công
                    if (typeof checkAndShowManualDistance === 'function') {
                        try { checkAndShowManualDistance(); } catch(_) {}
                    }
                };
                doiLenhCheckbox.addEventListener('change', toggleDoiLenh);
                toggleDoiLenh();
            }

            // Tự tính lại khi thay đổi Khối lượng - ĐÃ TẮT theo yêu cầu
            // Người dùng phải bấm nút "Tính Toán Nhiên Liệu" để xem kết quả
            /*
            (function autoRecalcOnWeightChange() {
                const input = document.getElementById('khoi_luong');
                if (!input) return;
                let timer = null;
                const shouldAutoCalc = () => {
                    const capThem = document.getElementById('cap_them');
                    if (capThem && capThem.checked) return false; // chế độ Cấp thêm không tính
                    const tenTau = (document.getElementById('ten_tau')?.value || '').trim();
                    const diemA = (document.getElementById('diem_bat_dau')?.value || '').trim();
                    const diemB = (document.getElementById('diem_ket_thuc')?.value || '').trim();
                    return !!(tenTau && diemA && diemB);
                };
                const triggerCalc = () => {
                    if (!shouldAutoCalc()) return;
                    const form = input.closest('form');
                    if (!form) return;
                    // Bypass native validation to avoid popup on required date fields during auto-recalc
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'action';
                    hidden.value = 'calculate';
                    form.appendChild(hidden);
                    form.setAttribute('novalidate', 'novalidate');
                    form.submit();
                };
                input.addEventListener('input', function() {
                    clearTimeout(timer);
                    timer = setTimeout(triggerCalc, 600); // debounce để không submit liên tục khi đang gõ
                });
                input.addEventListener('change', triggerCalc);
            })();
            */

            // Lọc danh sách tàu theo phân loại (công ty / thuê ngoài)
            window.filterTauByPhanLoai = function() {
                const filter = (document.getElementById('loc_phan_loai')?.value || '').trim();
                const selectTau = document.getElementById('ten_tau');
                if (!selectTau) return;
                const current = selectTau.value;
                let firstVisible = '';
                Array.from(selectTau.options).forEach(opt => {
                    if (!opt.value) return; // skip placeholder
                    const pl = opt.getAttribute('data-pl') || '';
                    const visible = !filter || filter === pl;
                    opt.style.display = visible ? '' : 'none';
                    if (visible && !firstVisible) firstVisible = opt.value;
                });
                // Nếu option hiện tại bị ẩn thì reset về rỗng
                const curOpt = Array.from(selectTau.options).find(o => o.value === current);
                if (curOpt && curOpt.style.display === 'none') {
                    selectTau.value = '';
                }
            };

            // Tự động cập nhật phân loại khi chọn tàu - MOVED TO MAIN onTauChange FUNCTION

            // Xử lý checkbox chuyến mới – ủy quyền về handler toàn cục để đảm bảo thêm option hiển thị mã mới
            const chuyenMoiCheckbox = document.getElementById('chuyen_moi');
            if (chuyenMoiCheckbox) {
                const delegateToggle = function() {
                    try { if (window.onToggleChuyenMoi) window.onToggleChuyenMoi(); } catch(e) { console.warn('onToggleChuyenMoi delegate error', e); }
                };
                chuyenMoiCheckbox.addEventListener('change', delegateToggle);
                chuyenMoiCheckbox.addEventListener('input', delegateToggle);
                // Áp dụng ngay theo trạng thái hiện tại khi trang tải xong
                // CHỈ gọi khi không phải trong quá trình submit form
                if (!window.__submittingForm) {
                    try { delegateToggle(); } catch(e) { /* ignore */ }
                }
            }

            // Đánh dấu khi người dùng thay đổi tháng báo cáo để tránh bị ghi đè
            const thangBaoCaoSelect = document.getElementById('thang_bao_cao');
            if (thangBaoCaoSelect) {
                thangBaoCaoSelect.addEventListener('change', function() {
                    this.dataset.userSelected = '1';
                });
                thangBaoCaoSelect.addEventListener('input', function() {
                    this.dataset.userSelected = '1';
                });
            }

            // Xử lý IME tiếng Việt: đánh dấu thời gian composition để chỉ tìm khi kết thúc
            document.querySelectorAll('.diem-input').forEach(function(el) {
                el.dataset.composing = '0';
                el.addEventListener('compositionstart', function() {
                    el.dataset.composing = '1';
                });
                el.addEventListener('compositionend', function() {
                    el.dataset.composing = '0';
                    const res = document.getElementById(el.id + '_results');
                    searchDiem(el, res);
                });
            });

            // Ngăn chặn submit form khi nhấn Enter trong các trường input (trừ nút submit)
            document.querySelectorAll('input[type="number"], input[type="text"], input[type="email"], textarea').forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        return false;
                    }
                });
            });

            // Debug: Log giá trị form trước khi submit
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    const soChuyen = document.getElementById('so_chuyen')?.value;
                    const chuyenMoi = document.getElementById('chuyen_moi')?.checked;
                    const tenTau = document.getElementById('ten_tau')?.value;
                    // console.log('DEBUG FORM SUBMIT: soChuyen =', soChuyen, 'chuyenMoi =', chuyenMoi, 'tenTau =', tenTau);
                });
            });

            // Xử lý dropdown cây xăng cấp thêm - ĐÃ BỎ vì không cần cây xăng

            // Chuẩn hóa input date nếu người dùng gõ dd/mm/yyyy
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    form.querySelectorAll('.vn-date').forEach(function(el) {
                        if (!el.value) return;
                        if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(el.value)) {
                            const [d,m,y] = el.value.split('/');
                            el.value = `${y.padStart(4,'0')}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`;
                        }
                    });
                });
            });

            // Xử lý hiển thị và tự động điền trường khoảng cách khi cần thiết
            function checkAndShowManualDistance() {
                const aEl = document.getElementById('diem_bat_dau');
                const bEl = document.getElementById('diem_ket_thuc');
                if (!aEl || !bEl) { return; }
                const diemBatDau = aEl.value.trim();
                const diemKetThuc = bEl.value.trim();
                const khoangCachThuCongFields = document.getElementById('khoang_cach_thu_cong_fields');
                const khoangCachThuCongInput = document.getElementById('khoang_cach_thu_cong');
                const khoangCachStatus = document.getElementById('khoang_cach_status');
                const btnUnlock = document.getElementById('btn_unlock_khoang_cach');

                // Kiểm tra nếu đang bật "Đổi lệnh", không cần hiển thị khoảng cách thủ công
                const doiLenhCheckbox = document.getElementById('doi_lenh');
                if (doiLenhCheckbox && doiLenhCheckbox.checked) {
                    if (khoangCachThuCongFields) {
                        khoangCachThuCongFields.style.display = 'none';
                    }
                    if (khoangCachThuCongInput) {
                        khoangCachThuCongInput.required = false;
                        khoangCachThuCongInput.value = '';
                        khoangCachThuCongInput.readOnly = false;
                    }
                    if (btnUnlock) {
                        btnUnlock.style.display = 'none';
                    }
                    return;
                }

                if (diemBatDau && diemKetThuc && khoangCachThuCongFields) {
                    // Hiển thị field ngay khi chọn đủ 2 điểm
                    khoangCachThuCongFields.style.display = 'block';

                    // Cập nhật trạng thái là đang kiểm tra
                    if (khoangCachStatus) {
                        khoangCachStatus.textContent = 'Đang kiểm tra tuyến đường...';
                        khoangCachStatus.parentElement.className = 'form-text text-info';
                    }

                    // Kiểm tra xem có tuyến đường trực tiếp không và lấy khoảng cách
                    const url = `api/search_diem.php?keyword=&diem_dau=${encodeURIComponent(diemBatDau)}`;

                    fetch(url)
                        .then(response => response.json())
                        .then(data => {
                            // Debug log để kiểm tra
                            console.log('=== DEBUG: Kiểm tra tuyến đường ===');
                            console.log('Điểm bắt đầu:', diemBatDau);
                            console.log('Điểm kết thúc:', diemKetThuc);
                            console.log('API response:', data);

                            // Chuẩn hóa so sánh - loại bỏ ghi chú trong ngoặc trước khi so sánh
                            // FIX: Tạo nhiều variants bằng cách xóa dần ngoặc từ cuối
                            const norm = function(s){
                                let str = String(s||'').normalize('NFC');
                                // Bỏ dấu tiếng Việt
                                const noAccent = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                                // Loại bỏ ký tự đặc biệt và chuẩn hóa khoảng trắng
                                const cleaned = noAccent.replace(/[^a-z0-9\s]/gi, ' ').replace(/\s+/g, ' ');
                                return cleaned.toLowerCase().trim();
                            };

                            // Tạo nhiều variants của tên điểm bằng cách xóa dần ngoặc từ cuối
                            // Ví dụ: "Cảng Long Bình (ĐN) (test)" → ["cang long binh dn test", "cang long binh dn", "cang long binh"]
                            const getVariants = function(s) {
                                const variants = [];
                                let current = String(s||'').normalize('NFC').trim();
                                variants.push(norm(current));

                                // Xóa từng cặp ngoặc từ cuối, tạo variant mỗi lần
                                while (/\s*[（(][^）)]*[）)]\s*$/.test(current)) {
                                    current = current.replace(/\s*[（(][^）)]*[）)]\s*$/, '').trim();
                                    if (current) {
                                        const normalized = norm(current);
                                        if (!variants.includes(normalized)) {
                                            variants.push(normalized);
                                        }
                                    }
                                }
                                return variants;
                            };

                            let foundRoute = null;
                            if (data && data.success && Array.isArray(data.data)) {
                                // Debug: log từng điểm để kiểm tra
                                console.log('Danh sách điểm từ API:');
                                data.data.forEach(function(item, index) {
                                    const normalized = norm(item.diem);
                                    console.log(`  [${index}] "${item.diem}" -> normalized: "${normalized}" (khoảng cách: ${item.khoang_cach})`);
                                });

                                // Tạo variants cho điểm kết thúc
                                const diemKetThucVariants = getVariants(diemKetThuc);
                                console.log('Điểm kết thúc variants:', diemKetThucVariants);

                                // Tìm route bằng cách so sánh variants
                                foundRoute = data.data.find(function (it) {
                                    const itemVariants = getVariants(it.diem);
                                    // Kiểm tra xem có variant nào match không
                                    return diemKetThucVariants.some(v => itemVariants.includes(v));
                                });
                                console.log('Kết quả tìm thấy:', foundRoute);
                            }

                            const applyDisplay = function(found, khoangCach){
                                if (found && khoangCach !== null && khoangCach !== undefined && khoangCach > 0) {
                                    // Có tuyến đường trực tiếp, TỰ ĐỘNG ĐIỀN khoảng cách
                                    if (khoangCachThuCongInput) {
                                        khoangCachThuCongInput.value = khoangCach;
                                        khoangCachThuCongInput.readOnly = true; // Khóa không cho sửa
                                        khoangCachThuCongInput.required = false; // Không bắt buộc vì đã có giá trị
                                        khoangCachThuCongInput.classList.add('bg-light'); // Highlight readonly
                                        khoangCachThuCongInput.dataset.autoFilled = 'true'; // Đánh dấu là tự động điền
                                    }
                                    if (btnUnlock) {
                                        btnUnlock.style.display = 'inline-block'; // Hiển thị nút Sửa
                                    }
                                    if (khoangCachStatus) {
                                        khoangCachStatus.textContent = `Tìm thấy tuyến đường: ${khoangCach} km (tự động từ dữ liệu)`;
                                        khoangCachStatus.parentElement.className = 'form-text text-success';
                                    }
                                } else {
                                    // Không có tuyến đường trực tiếp, YÊU CẦU người dùng nhập
                                    if (khoangCachThuCongInput) {
                                        // Giữ nguyên giá trị đã nhập (nếu có) hoặc xóa nếu chưa nhập
                                        if (!khoangCachThuCongInput.value || khoangCachThuCongInput.readOnly) {
                                            khoangCachThuCongInput.value = '';
                                        }
                                        khoangCachThuCongInput.readOnly = false; // Mở khóa để nhập
                                        khoangCachThuCongInput.required = true; // Bắt buộc nhập
                                        khoangCachThuCongInput.classList.remove('bg-light');
                                        delete khoangCachThuCongInput.dataset.autoFilled;
                                    }
                                    if (btnUnlock) {
                                        btnUnlock.style.display = 'none'; // Ẩn nút Sửa
                                    }
                                    if (khoangCachStatus) {
                                        khoangCachStatus.textContent = 'Không tìm thấy tuyến đường trực tiếp. Vui lòng nhập khoảng cách thực tế.';
                                        khoangCachStatus.parentElement.className = 'form-text text-warning';
                                    }
                                }
                            };

                            if (foundRoute && foundRoute.khoang_cach !== null) {
                                applyDisplay(true, foundRoute.khoang_cach);
                                return;
                            }

                            // Fallback: gọi lại với keyword = điểm kết thúc
                            const url2 = `api/search_diem.php?keyword=${encodeURIComponent(diemKetThuc)}&diem_dau=${encodeURIComponent(diemBatDau)}`;
                            fetch(url2)
                                .then(r => r.json())
                                .then(d2 => {
                                    const list2 = (d2 && d2.success && Array.isArray(d2.data)) ? d2.data : [];
                                    // Sử dụng variants để so sánh (giống như trên)
                                    const diemKetThucVariants = getVariants(diemKetThuc);
                                    const found2 = list2.find(function (it) {
                                        const itemVariants = getVariants(it.diem);
                                        return diemKetThucVariants.some(v => itemVariants.includes(v));
                                    });
                                    if (found2 && found2.khoang_cach !== null) {
                                        applyDisplay(true, found2.khoang_cach);
                                    } else {
                                        applyDisplay(false, null);
                                    }
                                })
                                .catch(() => { applyDisplay(false, null); });
                        })
                        .catch(error => {
                            console.error('Lỗi kiểm tra tuyến đường:', error);
                            // Nếu có lỗi, yêu cầu nhập thủ công
                            if (khoangCachThuCongInput) {
                                khoangCachThuCongInput.readOnly = false;
                                khoangCachThuCongInput.required = true;
                                khoangCachThuCongInput.classList.remove('bg-light');
                                delete khoangCachThuCongInput.dataset.autoFilled;
                            }
                            if (btnUnlock) {
                                btnUnlock.style.display = 'none';
                            }
                            if (khoangCachStatus) {
                                khoangCachStatus.textContent = 'Có lỗi khi kiểm tra. Vui lòng nhập khoảng cách thủ công.';
                                khoangCachStatus.parentElement.className = 'form-text text-danger';
                            }
                        });
                } else {
                    // Chưa chọn đủ điểm, ẩn trường khoảng cách
                    if (khoangCachThuCongFields) {
                        khoangCachThuCongFields.style.display = 'none';
                    }
                    if (khoangCachThuCongInput) {
                        khoangCachThuCongInput.value = '';
                        khoangCachThuCongInput.required = false;
                        khoangCachThuCongInput.readOnly = false;
                        khoangCachThuCongInput.classList.remove('bg-light');
                        delete khoangCachThuCongInput.dataset.autoFilled;
                    }
                    if (btnUnlock) {
                        btnUnlock.style.display = 'none';
                    }
                }
            }

            // Hàm mở khóa field khoảng cách để cho phép chỉnh sửa
            function unlockKhoangCach() {
                const khoangCachThuCongInput = document.getElementById('khoang_cach_thu_cong');
                const khoangCachStatus = document.getElementById('khoang_cach_status');
                const btnUnlock = document.getElementById('btn_unlock_khoang_cach');

                if (khoangCachThuCongInput) {
                    khoangCachThuCongInput.readOnly = false;
                    khoangCachThuCongInput.required = true;
                    khoangCachThuCongInput.classList.remove('bg-light');
                    khoangCachThuCongInput.focus();
                    khoangCachThuCongInput.select(); // Select all để dễ sửa
                }

                if (btnUnlock) {
                    btnUnlock.style.display = 'none'; // Ẩn nút Sửa sau khi mở khóa
                }

                if (khoangCachStatus) {
                    khoangCachStatus.textContent = 'Đã mở khóa. Bạn có thể chỉnh sửa khoảng cách.';
                    khoangCachStatus.parentElement.className = 'form-text text-info';
                }
            }

            // Gọi hàm kiểm tra khi thay đổi điểm bắt đầu hoặc kết thúc
            const diemBatDauInput = document.getElementById('diem_bat_dau');
            const diemKetThucInput = document.getElementById('diem_ket_thuc');
            
            if (diemBatDauInput) {
                diemBatDauInput.addEventListener('change', checkAndShowManualDistance);
            }
            if (diemKetThucInput) {
                diemKetThucInput.addEventListener('change', checkAndShowManualDistance);
            }

            // Đã bỏ kiểm tra khoảng cách thủ công khi trang tải

            // Không auto-render nữa để khôi phục hành vi cũ
            
            // Khi trang vừa tải: nếu đã có sẵn tàu và mã chuyến được chọn, tải chi tiết để hiển thị bảng ngay
            (function initTripLogOnLoad(){

                const tenTauEl = document.getElementById('ten_tau');
                const soChuyenEl = document.getElementById('so_chuyen');
                if (!tenTauEl || !soChuyenEl) return;
                const tenTau = (tenTauEl.value || '').trim();
                const soChuyen = (soChuyenEl.value || '').trim();
                if (!tenTau) return;
                // Ghi nhớ mã chuyến đang có (nếu có) để onTauChange giữ nguyên
                if (soChuyen) { 
                    soChuyenEl.setAttribute('data-preselected', soChuyen);

                }
                // CHỈ gọi onTauChange khi không phải trong quá trình submit form
                if (!window.__submittingForm) {
                    try { window.onTauChange(); } catch (_) {}
                }
                // Đồng thời, gọi trực tiếp lấy chi tiết theo mã hiện có (nếu đã có) để hiển thị nhanh ngay lần đầu
                if (soChuyen) {
                    try { window.onChuyenChange(); } catch (e) { console.warn('initTripLogOnLoad direct fetch error', e); }
                }
            })();

            // Xử lý chuyển đổi mã chuyến
            window.openTripChangeModal = function() {
                const tenTau = document.getElementById('ten_tau').value;
                const soChuyen = document.getElementById('so_chuyen').value;

                // console.log('openTripChangeModal called:', {tenTau, soChuyen}); // Debug

                if (!tenTau || tenTau.trim() === '') {
                    showAlert('Vui lòng chọn tàu trước khi chuyển chuyến', 'warning');
                    return;
                }
                
                // Cập nhật thông tin chuyến hiện tại
                document.getElementById('currentShipName').textContent = tenTau;
                document.getElementById('currentTripNumber').textContent = soChuyen || '-';
                
                // Đếm số đoạn hiện tại
                const tripTableBody = document.getElementById('trip_table_body');
                let segmentCount = 0;
                if (tripTableBody) {
                    const rows = tripTableBody.querySelectorAll('tr');
                    segmentCount = rows.length;
                    // Trừ đi dòng "Chưa có dữ liệu" nếu có
                    if (rows.length === 1 && rows[0].textContent.includes('Chưa có dữ liệu')) {
                        segmentCount = 0;
                    }
                }
                document.getElementById('currentTripSegments').textContent = segmentCount;
                
                // Tải danh sách chuyến
                loadTripList(tenTau, soChuyen);
                
                // Hiển thị modal
                const modal = new bootstrap.Modal(document.getElementById('tripChangeModal'));
                modal.show();
            };
            
            function loadTripList(tenTau, currentTrip) {
                const select = document.getElementById('newTripSelect');
                select.innerHTML = '<option value="">-- Đang tải danh sách --</option>';
                
                fetch(`ajax/get_trips.php?ten_tau=${encodeURIComponent(tenTau)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            select.innerHTML = '';
                            
                            // Thêm option mặc định
                            const defaultOption = document.createElement('option');
                            defaultOption.value = '';
                            defaultOption.textContent = '-- Chọn chuyến --';
                            select.appendChild(defaultOption);
                            
                            // Thêm các chuyến
                            data.trips.forEach(trip => {
                                const option = document.createElement('option');
                                option.value = trip;
                                option.textContent = `Chuyến ${trip}`;
                                if (trip == currentTrip) {
                                    option.textContent += ' (hiện tại)';
                                    option.disabled = true;
                                }
                                select.appendChild(option);
                            });
                            
                            // Xử lý khi chọn chuyến
                            select.addEventListener('change', function() {
                                const selectedTrip = this.value;
                                const btnConfirm = document.getElementById('btnConfirmChange');
                                
                                if (selectedTrip && selectedTrip !== currentTrip) {
                                    btnConfirm.disabled = true; // Sẽ bật khi chọn đoạn
                                    loadTripDetails(tenTau, selectedTrip);
                                } else {
                                    btnConfirm.disabled = true;
                                    document.getElementById('selectedTripInfo').style.display = 'none';
                                }
                            });
                        } else {
                            select.innerHTML = '<option value="">-- Lỗi tải danh sách --</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Lỗi tải danh sách chuyến:', error);
                        select.innerHTML = '<option value="">-- Lỗi tải danh sách --</option>';
                    });
            }
            
            function loadTripDetails(tenTau, soChuyen) {
                const detailsDiv = document.getElementById('selectedTripDetails');
                detailsDiv.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
                document.getElementById('selectedTripInfo').style.display = 'block';
                
                // Lấy đoạn của chuyến hiện tại (chuyến nguồn)
                const currentTrip = document.getElementById('so_chuyen').value;
                
                fetch(`ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${encodeURIComponent(currentTrip)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let html = '';
                            
                            // Thông tin chuyến đích
                            html += '<div class="alert alert-info">';
                            html += '<h6><i class="fas fa-arrow-right me-2"></i>Chuyến đích: ' + soChuyen + '</h6>';
                            html += '<div class="row">';
                            html += '<div class="col-md-6">';
                            html += '<p><strong>Số đoạn hiện có:</strong> ' + (data.segments ? data.segments.length : 0) + '</p>';
                            html += '</div>';
                            html += '<div class="col-md-6">';
                            html += '<p><strong>Số lần cấp thêm:</strong> ' + (data.cap_them ? data.cap_them.length : 0) + '</p>';
                            html += '</div>';
                            html += '</div>';
                            html += '</div>';
                            
                            // Lấy đoạn của chuyến nguồn để chọn di chuyển
                            return fetch(`ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${encodeURIComponent(currentTrip)}`)
                                .then(response => response.json())
                                .then(sourceData => {
                                    if (sourceData.success && sourceData.segments && sourceData.segments.length > 0) {
                                        html += '<h6>Chọn đoạn từ chuyến ' + currentTrip + ' để di chuyển sang chuyến ' + soChuyen + ':</h6>';
                                        html += '<div class="list-group">';
                                        
                                        sourceData.segments.forEach((segment, index) => {
                                            html += '<div class="list-group-item list-group-item-action segment-item" data-segment-index="' + index + '" onclick="selectSegment(' + index + ')">';
                                            html += '<div class="d-flex w-100 justify-content-between">';
                                            html += '<h6 class="mb-1">Đoạn ' + (index + 1) + '</h6>';
                                            html += '<small class="text-primary"><i class="fas fa-arrow-right"></i> Di chuyển</small>';
                                            html += '</div>';
                                            html += '<p class="mb-1"><strong>Tuyến:</strong> ' + escapeHtml(segment.diem_di || '') + ' → ' + escapeHtml(segment.diem_den || '') + '</p>';
                                            html += '<div class="row">';
                                            html += '<div class="col-md-4"><small><strong>Khối lượng:</strong> ' + formatNumber(segment.khoi_luong_van_chuyen_t || 0) + ' tấn</small></div>';
                                            html += '<div class="col-md-4"><small><strong>Nhiên liệu:</strong> ' + formatNumber(segment.dau_tinh_toan_lit || 0) + ' lít</small></div>';
                                            html += '<div class="col-md-4"><small><strong>Ngày đi:</strong> ' + formatDateVN(segment.ngay_di || '') + '</small></div>';
                                            html += '</div>';
                                            if (segment.ngay_do_xong) {
                                                html += '<small><strong>Ngày dỡ xong:</strong> ' + formatDateVN(segment.ngay_do_xong) + '</small>';
                                            }
                                            html += '</div>';
                                        });
                                        
                                        html += '</div>';
                                    } else {
                                        html += '<div class="alert alert-warning">Chuyến nguồn chưa có đoạn nào để di chuyển.</div>';
                                    }
                                    
                                    detailsDiv.innerHTML = html;
                                });
                        } else {
                            detailsDiv.innerHTML = '<div class="text-muted">Không có thông tin chi tiết</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Lỗi tải chi tiết chuyến:', error);
                        detailsDiv.innerHTML = '<div class="text-danger">Lỗi tải thông tin</div>';
                    });
            }
            
            // Biến lưu trữ đoạn được chọn
            let selectedSegmentIndex = null;
            let selectedSegmentData = null;
            
            window.selectSegment = function(index) {
                // Xóa highlight cũ
                document.querySelectorAll('.segment-item').forEach(item => {
                    item.classList.remove('active');
                });
                
                // Highlight đoạn được chọn
                const selectedItem = document.querySelector(`[data-segment-index="${index}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('active');
                    selectedSegmentIndex = index;
                    
                    // Lưu dữ liệu đoạn được chọn
                    const tenTau = document.getElementById('ten_tau').value;
                    const soChuyen = document.getElementById('so_chuyen').value;
                    
                    // Lấy dữ liệu đoạn từ API
                    fetch(`ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${encodeURIComponent(soChuyen)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.segments && data.segments[index]) {
                                selectedSegmentData = data.segments[index];
                                console.log('Selected segment:', selectedSegmentData);
                                
                                // Bật nút xác nhận khi đã chọn đoạn
                                const btnConfirm = document.getElementById('btnConfirmChange');
                                if (btnConfirm) {
                                    btnConfirm.disabled = false;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Lỗi lấy dữ liệu đoạn:', error);
                        });
                }
            };
            
            window.changeTrip = function() {
                const tenTau = document.getElementById('ten_tau').value;
                const newTrip = document.getElementById('newTripSelect').value;
                const currentTrip = document.getElementById('so_chuyen').value;
                
                console.log('changeTrip called:', {tenTau, newTrip, currentTrip, selectedSegmentIndex});
                
                if (!tenTau || !newTrip) {
                    showAlert('Vui lòng chọn chuyến hợp lệ', 'warning');
                    return;
                }
                
                if (selectedSegmentIndex === null) {
                    showAlert('Vui lòng chọn đoạn để di chuyển', 'warning');
                    return;
                }
                
                if (currentTrip === newTrip) {
                    showAlert('Không thể di chuyển đoạn trong cùng một chuyến', 'warning');
                    return;
                }
                
                // Di chuyển đoạn trực tiếp (không cần xác nhận)
                
                // Gọi API để di chuyển đoạn
                const formData = new FormData();
                formData.append('ten_tau', tenTau);
                formData.append('from_trip', currentTrip);
                formData.append('to_trip', newTrip);
                formData.append('segment_index', selectedSegmentIndex);
                
                fetch('api/move_segment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(`Đã di chuyển đoạn thành công từ chuyến ${currentTrip} sang chuyến ${newTrip}`, 'success');
                        
                        // Cập nhật dropdown mã chuyến về chuyến đích
                        const soChuyenSelect = document.getElementById('so_chuyen');
                        soChuyenSelect.value = newTrip;
                        
                        // Cập nhật URL
                        try {
                            const params = new URLSearchParams(window.location.search);
                            params.set('ten_tau', tenTau);
                            params.set('so_chuyen', newTrip);
                            window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
                        } catch(e) {}
                        
                        // Tải lại dữ liệu chuyến đích
                        try {
                            window.onChuyenChange();
                        } catch(e) {
                            console.error('Lỗi khi tải lại dữ liệu:', e);
                        }
                        
                        // Đóng modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('tripChangeModal'));
                        if (modal) {
                            modal.hide();
                        }
                        
                        // Reset biến chọn đoạn
                        selectedSegmentIndex = null;
                        selectedSegmentData = null;
                    } else {
                        showAlert('Lỗi khi di chuyển đoạn: ' + (data.error || 'Không xác định'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Lỗi khi di chuyển đoạn:', error);
                    showAlert('Lỗi khi di chuyển đoạn: ' + error.message, 'danger');
                });
            };
            
            // Bật nút chuyển chuyến khi có tàu được chọn
            document.addEventListener('DOMContentLoaded', function() {
                const tenTauSelect = document.getElementById('ten_tau');
                const btnChangeTrip = document.getElementById('btn_change_trip');
                
                if (tenTauSelect && btnChangeTrip) {
                    const updateButtonState = () => {
                        const hasShip = tenTauSelect.value && tenTauSelect.value.trim() !== '';
                        btnChangeTrip.disabled = !hasShip;
                        // console.log('Update button state:', hasShip, tenTauSelect.value); // Debug
                    };
                    
                    tenTauSelect.addEventListener('change', updateButtonState);
                    updateButtonState(); // Cập nhật trạng thái ban đầu
                    
                    // Cũng cập nhật khi tàu được chọn từ URL parameters
                    setTimeout(updateButtonState, 100);
                }
            });
        });
    </script>

    <script>
        // Handlers for editing/deleting transfer orders from history table
            document.addEventListener('DOMContentLoaded', function(){
                if (window.__TD2_TRANSFER_HANDLER) {
                    return;
                }
                document.body.addEventListener('click', async function(e){
                var btnDel = e.target.closest('[data-action="delete-transfer"]');
                if (btnDel) {
                    e.preventDefault();
                    if (!confirm('Xóa lệnh chuyển dầu này?')) return;
                    try {
                        var fd = new FormData();
                        var pairId = btnDel.getAttribute('data-pair-id');
                        if (pairId) {
                            fd.append('transfer_pair_id', pairId);
                        } else {
                            fd.append('source_ship', btnDel.getAttribute('data-src'));
                            fd.append('dest_ship', btnDel.getAttribute('data-dst'));
                            fd.append('date', btnDel.getAttribute('data-date'));
                            fd.append('liters', btnDel.getAttribute('data-liters'));
                        }
                        const res = await fetch('api/delete_transfer.php', { method: 'POST', body: fd });
                        const j = await res.json();
                        if (!j.success) throw new Error(j.error || 'Lỗi không xác định');
                        location.reload();
                    } catch(err) {
                        alert('Không thể xóa: ' + err.message);
                    }
                    return;
                }

                var btnEdit = e.target.closest('[data-action="edit-transfer"]');
                if (btnEdit) {
                    e.preventDefault();
                    // Simple prompt-based editor to avoid large modal changes
                    try {
                        var src = btnEdit.getAttribute('data-src');
                        var dst = btnEdit.getAttribute('data-dst');
                        var date = btnEdit.getAttribute('data-date');
                        var liters = btnEdit.getAttribute('data-liters');
                        var pairId = btnEdit.getAttribute('data-pair-id');

                        var newDate = prompt('Ngày (dd/mm/yyyy):', date) || date;
                        var newLiters = prompt('Số lít:', liters) || liters;
                        var newSrc = prompt('Tàu nguồn:', src) || src;
                        var newDst = prompt('Tàu đích:', dst) || dst;
                        var reason = prompt('Lý do (tuỳ chọn):', 'Chuyển dầu') || 'Chuyển dầu';

                        var fd = new FormData();
                        if (pairId) {
                            fd.append('transfer_pair_id', pairId);
                        } else {
                            fd.append('old_source_ship', src);
                            fd.append('old_dest_ship', dst);
                            fd.append('old_date', date);
                            fd.append('old_liters', liters);
                        }
                        fd.append('new_source_ship', newSrc);
                        fd.append('new_dest_ship', newDst);
                        fd.append('new_date', newDate);
                        fd.append('new_liters', newLiters);
                        fd.append('reason', reason);

                        const res = await fetch('api/update_transfer.php', { method: 'POST', body: fd });
                        const j = await res.json();
                        if (!j.success) throw new Error(j.error || 'Lỗi không xác định');
                        location.reload();
                    } catch(err) {
                        alert('Không thể cập nhật: ' + err.message);
                    }
                }
            });
        });
    </script>

    <!-- Back to Top styles and behavior -->
    <style>
        .back-to-top {
            position: fixed; right: 18px; bottom: 26px; z-index: 1050;
            width: 42px; height: 42px; border-radius: 50%;
            border: 1px solid var(--border-color);
            background: rgba(255,255,255,0.9); color: var(--primary-color);
            display: grid; place-items: center; box-shadow: 0 8px 20px rgba(0,0,0,.15);
            opacity: 0; pointer-events: none; transition: opacity .2s ease, background-color .2s ease, color .2s ease;
        }
        .back-to-top:hover { opacity: 1; background: var(--primary-color); color: #fff; }
        .back-to-top.show { opacity: .35; pointer-events: auto; }
    </style>
    <script>
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                let btn = document.getElementById('backToTopBtn');
                if (!btn) {
                    btn = document.createElement('button');
                    btn.id = 'backToTopBtn';
                    btn.className = 'back-to-top';
                    btn.setAttribute('aria-label', 'Lên đầu trang');
                    btn.setAttribute('title', 'Lên đầu trang');
                    btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                    document.body.appendChild(btn);
                }
                const threshold = 200;
                const onScroll = () => { btn.classList.toggle('show', window.scrollY > threshold); };
                document.addEventListener('scroll', onScroll, { passive: true });
                onScroll();
                btn.addEventListener('click', function(){
                    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    if (reduce) { window.scrollTo(0, 0); return; }
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            });
        })();
    </script>

    <script>
        // Chức năng chỉnh sửa đoạn
        window.openEditSegmentModal = function(idx) {
            if (!idx || idx <= 0) {
                showAlert('Không tìm thấy đoạn cần sửa', 'warning');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('editSegmentModal'));
            document.getElementById('edit_segment_idx').value = idx;
            document.getElementById('edit_segment_content').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Đang tải dữ liệu...</div>';
            modal.show();
            
            // Lấy dữ liệu đoạn hiện tại
            const tenTau = document.getElementById('ten_tau').value;
            const soChuyen = document.getElementById('so_chuyen').value;
            
            fetch(`ajax/get_trip_details.php?ten_tau=${encodeURIComponent(tenTau)}&so_chuyen=${encodeURIComponent(soChuyen)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Tìm đoạn có ___idx = idx
                        let segment = null;
                        let isCapThem = false;
                        
                        // Tìm trong segments
                        if (data.segments) {
                            segment = data.segments.find(s => (s.___idx || s.___idx) == idx);
                        }
                        
                        // Nếu không tìm thấy, tìm trong cap_them
                        if (!segment && data.cap_them) {
                            segment = data.cap_them.find(ct => (ct.___idx || ct.___idx) == idx);
                            isCapThem = true;
                        }
                        
                        if (!segment) {
                            document.getElementById('edit_segment_content').innerHTML = '<div class="alert alert-danger">Không tìm thấy dữ liệu đoạn</div>';
                            return;
                        }
                        
                        // Render form chỉnh sửa
                        let html = '';
                        if (isCapThem) {
                            html = `
                                <div class="mb-3">
                                    <label class="form-label">Lý do cấp thêm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="edit_ly_do_cap_them" name="ly_do_cap_them" 
                                           value="${escapeHtml(segment.ly_do_cap_them || '')}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Số lượng (Lít) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" id="edit_so_luong_cap_them" name="so_luong_cap_them_lit" 
                                           value="${segment.so_luong_cap_them_lit || 0}" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ngày cấp thêm</label>
                                    <input type="text" class="form-control vn-date" id="edit_ngay_di" name="ngay_di" 
                                           placeholder="dd/mm/yyyy" value="${formatDateVN(segment.ngay_di || '')}">
                                </div>
                            `;
                        } else {
                            // Lấy thông tin hiện tại để so sánh
                            const currentDiemDi = segment.diem_di || '';
                            const currentDiemDen = segment.diem_den || '';
                            const currentKhoiLuong = segment.khoi_luong_van_chuyen_t || 0;
                            const currentNhiênLieu = segment.dau_tinh_toan_lit || 0;
                            const currentHeSoCoHang = segment.he_so_co_hang || 0;
                            const currentHeSoKhongHang = segment.he_so_khong_hang || 0;
                            const currentNhomCuLy = segment.nhom_cu_ly || '';
                            
                            html = `
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Lưu ý:</strong> Nếu bạn thay đổi điểm đi hoặc điểm đến, hệ thống sẽ tự động:
                                    <ul class="mb-0 mt-2">
                                        <li>Tính toán lại khoảng cách và hệ số nhiên liệu mới</li>
                                        <li>Cập nhật lại nhiên liệu tiêu thụ</li>
                                        <li>Cập nhật nhóm cự ly (nếu khoảng cách thay đổi)</li>
                                        <li>Cập nhật tất cả các thông tin liên quan</li>
                                    </ul>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Điểm đi <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control diem-input" id="edit_diem_di" name="diem_di" 
                                               value="${escapeHtml(currentDiemDi)}" 
                                               data-original-value="${escapeHtml(currentDiemDi)}"
                                               placeholder="Bắt đầu nhập để tìm kiếm..." 
                                               autocomplete="off"
                                               required
                                               onfocus="showAllDiem(document.getElementById('edit_diem_di_results'), '');"
                                               oninput="searchDiem(this, document.getElementById('edit_diem_di_results')); checkRouteChange();">
                                        <div class="dropdown-menu diem-results" id="edit_diem_di_results" style="width: 100%; max-height: 200px; overflow-y: auto; position: absolute; z-index: 1050;"></div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Click vào ô để hiện tất cả điểm có sẵn. Thay đổi điểm đi sẽ tính toán lại toàn bộ
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Điểm đến <span class="text-danger">*</span></label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control diem-input" id="edit_diem_den" name="diem_den" 
                                               value="${escapeHtml(currentDiemDen)}" 
                                               data-original-value="${escapeHtml(currentDiemDen)}"
                                               placeholder="Bắt đầu nhập để tìm kiếm..." 
                                               autocomplete="off"
                                               required
                                               onfocus="showAllDiem(document.getElementById('edit_diem_den_results'), document.getElementById('edit_diem_di').value);"
                                               oninput="searchDiem(this, document.getElementById('edit_diem_den_results')); checkRouteChange();">
                                        <div class="dropdown-menu diem-results" id="edit_diem_den_results" style="width: 100%; max-height: 200px; overflow-y: auto; position: absolute; z-index: 1050;"></div>
                                    </div>
                                    <small class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Click vào ô để hiện tất cả điểm có sẵn. Thay đổi điểm đến sẽ tính toán lại toàn bộ
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Khối lượng (tấn) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="edit_khoi_luong" name="khoi_luong_van_chuyen_t" 
                                           value="${currentKhoiLuong}" required>
                                    <small class="form-text text-muted">Thay đổi khối lượng sẽ tính lại nhiên liệu với hệ số hiện tại</small>
                                </div>
                                <div class="mb-3" id="current_route_info">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="fas fa-info-circle me-2"></i>Thông tin tuyến đường hiện tại:</h6>
                                            <p class="mb-1"><strong>Nhiên liệu:</strong> <span id="current_fuel">${formatNumber(currentNhiênLieu)}</span> lít</p>
                                            <p class="mb-1"><strong>Hệ số có hàng:</strong> <span id="current_kch">${formatNumber(currentHeSoCoHang, 7)}</span> Lít/T.Km</p>
                                            <p class="mb-1"><strong>Hệ số không hàng:</strong> <span id="current_kkh">${formatNumber(currentHeSoKhongHang, 6)}</span> Lít/Km</p>
                                            ${currentNhomCuLy ? `<p class="mb-0"><strong>Nhóm cự ly:</strong> ${escapeHtml(currentNhomCuLy)}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Loại hàng</label>
                                    <input type="text" class="form-control" id="edit_loai_hang" name="loai_hang" 
                                           value="${escapeHtml(segment.loai_hang || '')}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ghi chú</label>
                                    <input type="text" class="form-control" id="edit_ghi_chu" name="ghi_chu" 
                                           value="${escapeHtml(segment.ghi_chu || '')}">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ngày đi</label>
                                        <input type="text" class="form-control vn-date" id="edit_ngay_di" name="ngay_di" 
                                               placeholder="dd/mm/yyyy" value="${formatDateVN(segment.ngay_di || '')}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ngày đến</label>
                                        <input type="text" class="form-control vn-date" id="edit_ngay_den" name="ngay_den" 
                                               placeholder="dd/mm/yyyy" value="${formatDateVN(segment.ngay_den || '')}">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Ngày dỡ xong</label>
                                        <input type="text" class="form-control vn-date" id="edit_ngay_do_xong" name="ngay_do_xong" 
                                               placeholder="dd/mm/yyyy" value="${formatDateVN(segment.ngay_do_xong || '')}">
                                    </div>
                                </div>
                            `;
                        }
                        
                        document.getElementById('edit_segment_content').innerHTML = html;
                        
                        // Khởi tạo date picker cho các trường ngày
                        if (typeof initDatePickers === 'function') {
                            initDatePickers();
                        }
                    } else {
                        document.getElementById('edit_segment_content').innerHTML = '<div class="alert alert-danger">Lỗi khi tải dữ liệu</div>';
                    }
                })
                .catch(error => {
                    console.error('Lỗi khi tải dữ liệu đoạn:', error);
                    document.getElementById('edit_segment_content').innerHTML = '<div class="alert alert-danger">Lỗi khi tải dữ liệu</div>';
                });
        };
        
        // Hàm kiểm tra thay đổi tuyến đường
        window.checkRouteChange = function() {
            const diemDiInput = document.getElementById('edit_diem_di');
            const diemDenInput = document.getElementById('edit_diem_den');
            const infoCard = document.getElementById('current_route_info');
            
            if (!diemDiInput || !diemDenInput || !infoCard) return;
            
            const diemDiMoi = diemDiInput.value.trim();
            const diemDenMoi = diemDenInput.value.trim();
            
            // Lấy giá trị ban đầu từ data attribute hoặc từ segment ban đầu
            const diemDiCu = diemDiInput.dataset.originalValue || diemDiInput.defaultValue || '';
            const diemDenCu = diemDenInput.dataset.originalValue || diemDenInput.defaultValue || '';
            
            // So sánh điểm gốc (loại bỏ ghi chú)
            const diemDiCuGoc = diemDiCu.replace(/\s*（[^）]*）\s*$/u, '').replace(/\s*\([^)]*\)\s*$/, '').trim();
            const diemDenCuGoc = diemDenCu.replace(/\s*（[^）]*）\s*$/u, '').replace(/\s*\([^)]*\)\s*$/, '').trim();
            const diemDiMoiGoc = diemDiMoi.replace(/\s*（[^）]*）\s*$/u, '').replace(/\s*\([^)]*\)\s*$/, '').trim();
            const diemDenMoiGoc = diemDenMoi.replace(/\s*（[^）]*）\s*$/u, '').replace(/\s*\([^)]*\)\s*$/, '').trim();
            
            if (diemDiMoiGoc !== diemDiCuGoc || diemDenMoiGoc !== diemDenCuGoc) {
                // Tuyến đường đã thay đổi
                if (infoCard) {
                    infoCard.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Tuyến đường đã thay đổi!</strong><br>
                            Hệ thống sẽ tự động tính toán lại với hệ số mới của tuyến đường này khi bạn lưu.
                        </div>
                    `;
                }
            } else {
                // Tuyến đường không thay đổi, hiển thị lại thông tin cũ
                // (Có thể giữ nguyên hoặc reload từ server)
            }
        };
        
        window.saveSegmentEdit = function() {
            const idx = document.getElementById('edit_segment_idx');
            if (!idx || !idx.value || parseInt(idx.value) <= 0) {
                showAlert('Không tìm thấy đoạn cần sửa', 'warning');
                return;
            }
            
            const formData = new FormData();
            formData.append('idx', idx.value);
            
            // Lấy dữ liệu từ form
            const diemDi = document.getElementById('edit_diem_di');
            const diemDen = document.getElementById('edit_diem_den');
            const khoiLuong = document.getElementById('edit_khoi_luong');
            const loaiHang = document.getElementById('edit_loai_hang');
            const ghiChu = document.getElementById('edit_ghi_chu');
            const ngayDi = document.getElementById('edit_ngay_di');
            const ngayDen = document.getElementById('edit_ngay_den');
            const ngayDoXong = document.getElementById('edit_ngay_do_xong');
            const lyDoCapThem = document.getElementById('edit_ly_do_cap_them');
            const soLuongCapThem = document.getElementById('edit_so_luong_cap_them');
            
            // Validate điểm đi và điểm đến
            if (diemDi && diemDi.value.trim() === '') {
                showAlert('Vui lòng nhập điểm đi', 'warning');
                diemDi.focus();
                return;
            }
            if (diemDen && diemDen.value.trim() === '') {
                showAlert('Vui lòng nhập điểm đến', 'warning');
                diemDen.focus();
                return;
            }
            
            // Kiểm tra điểm đi và điểm đến không được giống nhau
            if (diemDi && diemDen && diemDi.value.trim() === diemDen.value.trim()) {
                showAlert('Điểm đi và điểm đến không được giống nhau', 'warning');
                return;
            }
            
            // Thêm dữ liệu vào FormData
            if (diemDi) formData.append('diem_di', diemDi.value.trim());
            if (diemDen) formData.append('diem_den', diemDen.value.trim());
            if (khoiLuong) formData.append('khoi_luong_van_chuyen_t', khoiLuong.value || '0');
            if (loaiHang) formData.append('loai_hang', loaiHang.value || '');
            if (ghiChu) formData.append('ghi_chu', ghiChu.value || '');
            if (ngayDi && ngayDi.value) formData.append('ngay_di', ngayDi.value);
            if (ngayDen && ngayDen.value) formData.append('ngay_den', ngayDen.value);
            if (ngayDoXong && ngayDoXong.value) formData.append('ngay_do_xong', ngayDoXong.value);
            if (lyDoCapThem) formData.append('ly_do_cap_them', lyDoCapThem.value || '');
            if (soLuongCapThem) formData.append('so_luong_cap_them_lit', soLuongCapThem.value || '0');
            
            // Lấy tên tàu để tính toán lại nếu cần
            const tenTau = document.getElementById('ten_tau');
            if (tenTau && tenTau.value) {
                formData.append('ten_tau', tenTau.value);
            }
            
            // Disable nút lưu để tránh double submit
            const saveBtn = document.querySelector('#editSegmentModal .btn-primary');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Đang lưu...';
            }
            
            // Log dữ liệu gửi đi để debug
            console.log('Sending update request with idx:', idx.value);
            const formDataEntries = [];
            for (let pair of formData.entries()) {
                formDataEntries.push(pair[0] + ': ' + pair[1]);
            }
            console.log('FormData:', formDataEntries.join(', '));
            
            fetch('api/update_segment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Kiểm tra response status
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Server response (text):', text);
                        try {
                            const jsonData = JSON.parse(text);
                            return jsonData;
                        } catch(e) {
                            console.error('Failed to parse JSON:', e, 'Response text:', text);
                            throw new Error('Lỗi server: ' + response.status + ' ' + response.statusText + '. Response: ' + text.substring(0, 200));
                        }
                    });
                }
                return response.json().catch(e => {
                    console.error('Failed to parse JSON response:', e);
                    return response.text().then(text => {
                        console.error('Response text:', text);
                        throw new Error('Lỗi parse JSON: ' + text.substring(0, 200));
                    });
                });
            })
            .then(data => {
                console.log('Update response:', data);
                if (data && data.success) {
                    showAlert('Đã cập nhật đoạn thành công', 'success');
                    
                    // Đóng modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editSegmentModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Tải lại danh sách đoạn
                    try {
                        window.onChuyenChange();
                    } catch(e) {
                        console.error('Lỗi khi tải lại dữ liệu:', e);
                        location.reload();
                    }
                } else {
                    const errorMsg = (data && data.error) ? data.error : 'Không xác định';
                    console.error('Update failed:', errorMsg);
                    showAlert('Lỗi: ' + errorMsg, 'danger');
                    // Re-enable nút lưu
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu thay đổi';
                    }
                }
            })
            .catch(error => {
                console.error('Lỗi khi cập nhật đoạn:', error);
                const errorMsg = error.message || 'Không xác định';
                showAlert('Lỗi khi cập nhật đoạn: ' + errorMsg, 'danger');
                // Re-enable nút lưu
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save me-1"></i>Lưu thay đổi';
                }
            });
        };
    </script>
</body>
</html>

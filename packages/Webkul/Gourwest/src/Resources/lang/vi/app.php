<?php

return [
    'menu' => [
        'title' => 'Trung tâm Toàn cầu Gourwest',
    ],

    'acl' => [
        'title' => 'Trung tâm Toàn cầu Gourwest',
        'customers' => 'Hồ sơ Hợp nhất 7 Quốc gia & Khử trùng lặp',
        'integrations' => 'Trung tâm Tích hợp 8 Hệ thống & Phát lại',
        'loyalty' => 'Hạng Thành viên & Điểm thưởng Xuyên biên giới',
        'sla' => 'Bàn Dịch vụ Khách hàng Khu vực & SLA',
        'benchmark' => 'Bảo hành Dịch vụ & Kiểm chuẩn <3 giây',
    ],

    'index' => [
        'title' => 'Trung tâm CRM & Tích hợp Toàn cầu Gourwest',
        'subtitle' => 'Hồ sơ Golden Record hợp nhất tại 7 quốc gia (AU, NZ, SG, MY, TH, UK, CA), giám sát thời gian thực 8 hệ thống, sổ cái điểm thưởng, bàn SLA khu vực và kiểm soát bảo hành <3 giây.',
        'seed-btn' => 'Khởi tạo Dữ liệu Toàn cầu Gourwest',
        'refresh-btn' => 'Làm mới Số liệu',
        'retry-btn' => 'Thử lại',
        'load-error' => 'Không thể tải số liệu Trung tâm Toàn cầu Gourwest. Vui lòng thử lại.',
        'empty-title' => 'Chưa Khởi tạo Hồ sơ Đa khu vực Gourwest',
        'empty-description' => 'Khởi tạo hồ sơ khách hàng tại 7 quốc gia, 11 nhân sự chủ chốt, 8 hợp đồng hệ thống, cặp trùng lặp xuyên biên giới và phiếu SLA chỉ với một cú nhấp.',
    ],

    'kpis' => [
        'golden-records' => 'Hồ sơ Khách hàng Chuẩn (Golden)',
        'loyalty-liability' => 'Tổng Điểm thưởng Hợp nhất',
        'integrations-health' => 'Hệ thống Tích hợp Ổn định',
        'sla-latency' => 'Độ trễ p95 & Phiếu SLA Mở',
    ],

    'tabs' => [
        'customers' => 'Khách hàng Hợp nhất 7 Khu vực',
        'integrations' => 'Trung tâm Tích hợp 8 Hệ thống',
        'sla' => 'Điểm thưởng & Bàn SLA Khu vực',
        'benchmark' => 'Bảo hành & Kiểm chuẩn <3 giây',
    ],

    'customers' => [
        'all-regions' => 'Tất cả 7 Khu vực',
        'search-placeholder' => 'Tìm mã thành viên, Golden UID, tên khách hoặc cửa hàng...',
        'duplicates-banner' => 'Phát hiện cụm hồ sơ trùng lặp xuyên khu vực',
        'deduplicate-btn' => 'Hợp nhất Hồ sơ Trùng lặp Xuyên khu vực',
        'adjust-points-btn' => 'Điều chỉnh Điểm',
        'merged-badge' => 'Bản phụ Đã gộp',
        'golden-badge' => 'Bản chính Golden',
    ],

    'integrations' => [
        'replay-btn' => 'Phát lại Sự kiện Lỗi (DLQ)',
        'sync-btn' => 'Đồng bộ Ngay',
        'recent-events' => 'Luồng Đồng bộ Liên hệ thống & Webhook Gần đây',
        'webhook-endpoint' => 'Điểm cuối Webhook HMAC-SHA256',
    ],

    'sla' => [
        'new-ticket-btn' => 'Tạo Phiếu SLA Khu vực',
        'resolve-btn' => 'Giải quyết Phiếu',
        'resolved-label' => 'Đã giải quyết',
        'loyalty-modal-title' => 'Điều chỉnh Điểm thưởng Xuyên biên giới',
        'ticket-modal-title' => 'Ghi nhận Phiếu SLA Chăm sóc Khách hàng Khu vực',
        'points-delta' => 'Điểm Điều chỉnh (+/-)',
        'reason' => 'Lý do Kiểm toán',
        'region' => 'Khu vực / Pháp lý',
        'channel' => 'Kênh Tiếp nhận',
        'category' => 'Phân loại Khiếu nại',
        'priority' => 'Mức ưu tiên SLA',
        'subject' => 'Tiêu đề',
        'summary' => 'Ghi chú Xử lý',
        'save-btn' => 'Lưu',
        'cancel-btn' => 'Hủy',
    ],

    'benchmark' => [
        'run-btn' => 'Chạy Kiểm chuẩn Trực tiếp 25 Truy vấn (<3 giây)',
        'sla-pass' => 'ĐẠT BẢO HÀNH <= 3.000 ms',
        'sla-fail' => 'VI PHẠM SLA (> 3.000 ms)',
        'index-audit-title' => 'Kiểm toán Chỉ mục Tổ hợp & Sẵn sàng 5.000.000 Bản ghi',
        'privacy-matrix-title' => 'Ma trận Luật Quyền riêng tư & Chủ quyền Dữ liệu 7 Quốc gia',
    ],

    'messages' => [
        'seed-success' => 'Đã khởi tạo bộ dữ liệu doanh nghiệp 7 quốc gia Gourwest thành công.',
        'deduplicate-success' => 'Đã hợp nhất :count hồ sơ trùng lặp xuyên khu vực thành Golden Records.',
        'sync-success' => 'Đã đồng bộ nhịp kết nối và số liệu khu vực cho :system.',
        'replay-success' => 'Đã phát lại thành công :count sự kiện tích hợp lỗi.',
        'loyalty-adjusted' => 'Đã cập nhật số dư điểm thưởng và tính lại hạng thành viên.',
        'ticket-created' => 'Đã tạo phiếu SLA khu vực thành công.',
        'ticket-resolved' => 'Đã giải quyết phiếu SLA khu vực và ghi nhật ký kiểm toán.',
        'benchmark-complete' => 'Đã hoàn tất kiểm chuẩn 25 truy vấn (<3 giây) và dung lượng 5M bản ghi.',
    ],
];

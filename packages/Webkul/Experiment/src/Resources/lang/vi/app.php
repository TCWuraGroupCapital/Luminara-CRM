<?php

return [
    'menu' => [
        'title' => 'Thử nghiệm A/B',
        'title-info' => 'Chạy các thử nghiệm phân tách A/B có kiểm soát và theo dõi mức tăng chuyển đổi trên CRM.',
    ],

    'acl' => [
        'title' => 'Thử nghiệm A/B',
        'create' => 'Tạo mới',
        'edit' => 'Chỉnh sửa',
        'delete' => 'Xóa',
    ],

    'statuses' => [
        'draft' => 'Bản nháp',
        'running' => 'Đang chạy',
        'paused' => 'Tạm dừng',
        'concluded' => 'Đã kết thúc',
    ],

    'index' => [
        'title' => 'Thử nghiệm A/B',
        'subtitle' => 'Phân bổ lưu lượng tất định theo điểm cơ bản với ghi nhận chuyển đổi bảo vệ quyền riêng tư.',
        'create-btn' => 'Tạo thử nghiệm',
        'edit-btn' => 'Sửa',
        'delete-btn' => 'Xóa',
        'retry-btn' => 'Thử lại',
        'traffic' => 'Lưu lượng',
        'sample-ready' => 'Đủ cỡ mẫu (n ≥ 100)',
        'sample-low' => 'Đang thu thập mẫu (n < 100)',
        'srm-warning' => 'Cảnh báo SRM (lệch phân bổ)',
        'empty-title' => 'Chưa có thử nghiệm A/B nào',
        'empty-description' => 'Tạo thử nghiệm có kiểm soát đầu tiên để đo lường mức cải thiện chuyển đổi trên biểu mẫu và phễu khách hàng.',
        'load-error' => 'Không thể tải danh sách thử nghiệm. Vui lòng thử lại.',
        'delete-confirm' => 'Xóa thử nghiệm này và toàn bộ dữ liệu sự kiện đã ghi nhận?',
        'create-success' => 'Đã tạo thử nghiệm thành công.',
        'update-success' => 'Đã cập nhật thử nghiệm thành công.',
        'delete-success' => 'Đã xóa thử nghiệm thành công.',
    ],

    'table' => [
        'variant' => 'Biến thể',
        'split' => 'Tỷ lệ',
        'exposures' => 'Lượt hiển thị',
        'conversions' => 'Chuyển đổi',
        'conv-rate' => 'Tỷ lệ CĐ',
        'lift' => 'Tăng trưởng so với Gốc',
        'p-value' => 'Giá trị p hiệu chỉnh',
        'ci-95' => 'KTC 95% (điểm %)',
        'prob-beat' => 'XS vượt Đối chứng',
        'value' => 'Giá trị Lead',
        'baseline' => 'Đối chứng',
    ],

    'form' => [
        'create-title' => 'Tạo thử nghiệm A/B',
        'edit-title' => 'Chỉnh sửa thử nghiệm A/B',
        'name' => 'Tên thử nghiệm',
        'code' => 'Mã thử nghiệm',
        'status' => 'Trạng thái',
        'traffic-bp' => 'Phân bổ lưu lượng (Điểm cơ bản)',
        'winner' => 'Biến thể chiến thắng khi kết thúc',
        'winner-none' => 'Mặc định về Đối chứng',
        'description' => 'Giả thuyết / Ghi chú',
        'variants' => 'Các biến thể',
        'add-variant' => 'Thêm biến thể',
        'cancel' => 'Hủy',
        'save' => 'Lưu thử nghiệm',
    ],

    'validations' => [
        'weights-sum' => 'Tổng trọng số các biến thể phải bằng đúng 10000 điểm cơ bản (100%).',
        'invalid-winner' => 'Biến thể chiến thắng phải trùng khớp với một trong các biến thể đã cấu hình.',
        'locked-after-start' => 'Không thể thay đổi mã thử nghiệm hoặc trọng số biến thể sau khi đã bắt đầu thu thập dữ liệu.',
    ],
];

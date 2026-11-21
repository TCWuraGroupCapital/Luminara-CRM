<?php

return [
    'link' => 'Đăng nhập một lần (SSO)',
    'start' => [
        'title' => 'Đăng nhập một lần',
        'intro' => 'Nhập email công việc. Chúng tôi sẽ đưa bạn đến trang đăng nhập của tổ chức.',
        'email' => 'Email công việc',
        'submit' => 'Tiếp tục',
        'password' => 'Đăng nhập bằng mật khẩu',
    ],
    'redirect' => [
        'title' => 'Đang chuyển bạn đến nhà cung cấp đăng nhập…',
        'continue' => 'Tiếp tục',
    ],
    'errors' => [
        'not-configured' => 'Đăng nhập một lần chưa được thiết lập cho địa chỉ email này. Hãy đăng nhập bằng mật khẩu hoặc hỏi quản trị viên.',
        'expired' => 'Phiên đăng nhập này đã hết hạn hoặc đã được dùng. Vui lòng bắt đầu lại.',
        'cancelled' => 'Việc đăng nhập đã bị hủy tại nhà cung cấp.',
        'provider' => 'Không thể kết nối với nhà cung cấp đăng nhập. Hãy thử lại hoặc nhờ quản trị viên kiểm tra cài đặt.',
        'token' => 'Không thể xác minh phản hồi từ nhà cung cấp đăng nhập. Vui lòng thử lại.',
        'unknown-user' => 'Tài khoản này không thể đăng nhập tại đây. Hãy nhờ quản trị viên thêm bạn.',
        'unavailable' => 'Không gian làm việc này không khả dụng.',
        'use-sso' => 'Tổ chức của bạn dùng đăng nhập một lần. Hãy tiếp tục bên dưới.',
    ],
    'configuration' => [
        'title' => 'Đăng nhập một lần',
        'info' => 'Cho phép nhóm của bạn đăng nhập bằng tài khoản của tổ chức.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Hoạt động với Google Workspace, Microsoft Entra ID, Okta và các nhà cung cấp OpenID Connect khác. Đăng ký địa chỉ chuyển hướng này với nhà cung cấp: <địa chỉ CRM của bạn>/admin/sso/callback',
            'enabled' => 'Bật đăng nhập một lần',
            'issuer' => 'Địa chỉ nhà cung cấp (issuer)',
            'issuer-info' => 'Với Google Workspace: https://accounts.google.com',
            'client-id' => 'Client ID',
            'client-secret' => 'Client secret',
            'domains' => 'Tên miền email',
            'domains-info' => 'Phân tách bằng dấu phẩy, ví dụ example.com. Chỉ địa chỉ thuộc các tên miền này mới dùng được đăng nhập một lần.',
            'required' => 'Bắt buộc đăng nhập một lần cho các tên miền này',
            'required-info' => 'Đăng nhập bằng mật khẩu bị từ chối với các địa chỉ này. Hãy giữ một tài khoản quản trị ngoài các tên miền này phòng khi nhà cung cấp gặp sự cố.',
            'trust-mfa' => 'Nhà cung cấp bắt buộc xác minh hai bước',
            'trust-mfa-info' => 'Bỏ qua bước xác minh hai bước của CRM sau khi đăng nhập một lần. Hãy để tắt trừ khi nhà cung cấp yêu cầu yếu tố thứ hai với mọi người.',
        ],
    ],
];

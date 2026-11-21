<?php

return [
    'link' => '통합 로그인(SSO)으로 로그인',
    'start' => [
        'title' => '통합 로그인',
        'intro' => '업무용 이메일을 입력하세요. 조직의 로그인 페이지로 이동합니다.',
        'email' => '업무용 이메일',
        'submit' => '계속',
        'password' => '비밀번호로 로그인',
    ],
    'redirect' => [
        'title' => '로그인 제공자로 이동하는 중…',
        'continue' => '계속',
    ],
    'errors' => [
        'not-configured' => '이 이메일 주소에는 통합 로그인이 설정되어 있지 않습니다. 비밀번호로 로그인하거나 관리자에게 문의하세요.',
        'expired' => '이 로그인은 만료되었거나 이미 사용되었습니다. 다시 시작하세요.',
        'cancelled' => '제공자에서 로그인이 취소되었습니다.',
        'provider' => '로그인 제공자에 연결할 수 없습니다. 다시 시도하거나 관리자에게 설정 확인을 요청하세요.',
        'token' => '로그인 제공자의 응답을 확인할 수 없습니다. 다시 시도하세요.',
        'unknown-user' => '이 계정으로는 여기에 로그인할 수 없습니다. 관리자에게 추가를 요청하세요.',
        'unavailable' => '이 워크스페이스를 사용할 수 없습니다.',
        'use-sso' => '조직에서 통합 로그인을 사용합니다. 아래에서 계속하세요.',
    ],
    'configuration' => [
        'title' => '통합 로그인',
        'info' => '팀이 조직 계정으로 로그인하도록 합니다.',
        'oidc' => [
            'title' => 'OpenID Connect',
            'info' => 'Google Workspace, Microsoft Entra ID, Okta 등 OpenID Connect 제공자와 함께 작동합니다. 이 리디렉션 주소를 제공자에 등록하세요: <CRM 주소>/admin/sso/callback',
            'enabled' => '통합 로그인 사용',
            'issuer' => '제공자 주소(issuer)',
            'issuer-info' => 'Google Workspace의 경우: https://accounts.google.com',
            'client-id' => '클라이언트 ID',
            'client-secret' => '클라이언트 시크릿',
            'domains' => '이메일 도메인',
            'domains-info' => '쉼표로 구분합니다(예: example.com). 이 도메인의 주소만 통합 로그인을 사용할 수 있습니다.',
            'required' => '이 도메인에 통합 로그인 필수',
            'required-info' => '이 주소의 비밀번호 로그인은 거부됩니다. 제공자를 사용할 수 없을 때를 대비해 이 도메인 밖에 관리자 계정을 하나 두세요.',
            'trust-mfa' => '제공자가 2단계 인증을 강제함',
            'trust-mfa-info' => '통합 로그인 후 CRM 자체 2단계 인증을 건너뜁니다. 제공자가 모든 사용자에게 2단계 인증을 요구하지 않는다면 끄세요.',
        ],
    ],
];

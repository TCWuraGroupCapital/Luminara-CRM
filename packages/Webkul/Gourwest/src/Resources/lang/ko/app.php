<?php

return [
    'menu' => [
        'title' => 'Gourwest 글로벌 허브',
    ],

    'acl' => [
        'title' => 'Gourwest 글로벌 허브',
        'customers' => '7개국 통합 고객 프로필 및 중복 제거',
        'integrations' => '8대 시스템 통합 허브 및 재처리',
        'loyalty' => '로열티 등급 및 국가 간 포인트 원장',
        'sla' => '지역 고객 서비스 및 SLA 데스크',
        'benchmark' => '서비스 보증 및 <3초 벤치마크',
    ],

    'index' => [
        'title' => 'Gourwest 글로벌 CRM 및 통합 허브',
        'subtitle' => '7개국(AU, NZ, SG, MY, TH, UK, CA) 통합 골든 레코드, 8대 시스템 실시간 텔레메트리, 로열티 원장, 지역 SLA 데스크 및 <3초 보증 제어.',
        'seed-btn' => 'Gourwest 글로벌 데이터셋 생성',
        'refresh-btn' => '텔레메트리 새로고침',
        'retry-btn' => '재시도',
        'load-error' => 'Gourwest 글로벌 허브 텔레메트리를 불러오지 못했습니다. 다시 시도해 주세요.',
        'empty-title' => '아직 프로비저닝된 Gourwest 다지역 프로필이 없습니다',
        'empty-description' => '클릭 한 번으로 7개국 고객 프로필, 11명의 이해관계자, 8대 시스템 계약, 교차 지역 중복 쌍 및 SLA 티켓을 생성합니다.',
    ],

    'kpis' => [
        'golden-records' => '골든 고객 레코드',
        'loyalty-liability' => '통합 로열티 포인트',
        'integrations-health' => '정상 시스템 계약',
        'sla-latency' => '조회 p95 지연시간 및 미해결 SLA',
    ],

    'tabs' => [
        'customers' => '7개 지역 통합 고객',
        'integrations' => '8대 시스템 통합 허브',
        'sla' => '로열티 및 지역 SLA 데스크',
        'benchmark' => '서비스 보증 및 <3초 벤치마크',
    ],

    'customers' => [
        'all-regions' => '전체 7개 지역',
        'search-placeholder' => '멤버십 번호, 골든 UID, 고객명 또는 매장 검색...',
        'duplicates-banner' => '지역 간 중복 고객 클러스터가 감지되었습니다',
        'deduplicate-btn' => '지역 간 중복 병합',
        'adjust-points-btn' => '포인트 조정',
        'merged-badge' => '병합된 보조 프로필',
        'golden-badge' => '골든 기본 프로필',
    ],

    'integrations' => [
        'replay-btn' => '데드레터 이벤트 재처리',
        'sync-btn' => '지금 동기화',
        'recent-events' => '최근 시스템 간 동기화 및 웹훅 스트림',
        'webhook-endpoint' => 'HMAC-SHA256 웹훅 엔드포인트',
    ],

    'sla' => [
        'new-ticket-btn' => '지역 SLA 티켓 등록',
        'resolve-btn' => '티켓 해결',
        'resolved-label' => '해결됨',
        'loyalty-modal-title' => '국가 간 로열티 포인트 조정',
        'ticket-modal-title' => '지역 고객 서비스 SLA 티켓 등록',
        'points-delta' => '포인트 증감 (+/-)',
        'reason' => '감사 사유',
        'region' => '관할 / 지역',
        'channel' => '서비스 채널',
        'category' => '민원 유형',
        'priority' => 'SLA 우선순위',
        'subject' => '제목',
        'summary' => '조사 메모',
        'save-btn' => '저장',
        'cancel-btn' => '취소',
    ],

    'benchmark' => [
        'run-btn' => '실시간 25쿼리 (<3초) 벤치마크 실행',
        'sla-pass' => '<= 3,000 ms 보증 통과',
        'sla-fail' => 'SLA 위반 (> 3,000 ms)',
        'index-audit-title' => '5,000,000건 대비 복합 인덱스 및 준비도 감사',
        'privacy-matrix-title' => '7개국 개인정보 보호법 및 데이터 주권 매트릭스',
    ],

    'messages' => [
        'seed-success' => 'Gourwest 7개국 엔터프라이즈 데이터셋이 생성되었습니다.',
        'deduplicate-success' => ':count개의 교차 지역 중복 프로필을 골든 레코드로 통합했습니다.',
        'sync-success' => ':system 하트비트 및 지역 텔레메트리를 동기화했습니다.',
        'replay-success' => ':count개의 데드레터 연동 이벤트를 정상 재처리했습니다.',
        'loyalty-adjusted' => '고객 로열티 포인트 잔액과 등급을 업데이트했습니다.',
        'ticket-created' => '지역 SLA 티켓이 등록되었습니다.',
        'ticket-resolved' => '지역 SLA 티켓을 해결하고 감사 로그에 기록했습니다.',
        'benchmark-complete' => '25쿼리 (<3초) 조회 및 500만 건 보증 벤치마크를 완료했습니다.',
    ],
];

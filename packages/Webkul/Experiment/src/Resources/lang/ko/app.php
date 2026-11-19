<?php

return [
    'menu' => [
        'title' => 'A/B 실험',
        'title-info' => '통제된 A/B 분할 실험을 실행하고 CRM 퍼널 전반의 전환율 상승을 측정합니다.',
    ],

    'acl' => [
        'title' => 'A/B 실험',
        'create' => '생성',
        'edit' => '수정',
        'delete' => '삭제',
    ],

    'statuses' => [
        'draft' => '초안',
        'running' => '실행 중',
        'paused' => '일시 중지',
        'concluded' => '종료됨',
    ],

    'index' => [
        'title' => 'A/B 실험',
        'subtitle' => '개인정보를 보호하는 전환 귀속 및 결정론적 베이시스 포인트 트래픽 할당.',
        'create-btn' => '실험 생성',
        'edit-btn' => '수정',
        'delete-btn' => '삭제',
        'retry-btn' => '재시도',
        'traffic' => '트래픽',
        'sample-ready' => '표본 충분 (n ≥ 100)',
        'sample-low' => '표본 수집 중 (n < 100)',
        'srm-warning' => 'SRM 경고 (트래픽 편향)',
        'empty-title' => '구성된 A/B 실험이 없습니다',
        'empty-description' => '첫 번째 실험을 생성하여 웹 폼 및 리드 퍼널의 전환율 개선을 측정하세요.',
        'load-error' => '실험 목록을 불러올 수 없습니다. 다시 시도해 주세요.',
        'delete-confirm' => '이 실험과 기록된 모든 측정 이벤트를 삭제하시겠습니까?',
        'create-success' => '실험이 성공적으로 생성되었습니다.',
        'update-success' => '실험이 성공적으로 업데이트되었습니다.',
        'delete-success' => '실험이 성공적으로 삭제되었습니다.',
    ],

    'table' => [
        'variant' => '배리언트',
        'split' => '비율',
        'exposures' => '노출 수',
        'conversions' => '전환 수',
        'conv-rate' => '전환율',
        'lift' => '대조군 대비 상승률',
        'p-value' => '보정 p-값',
        'ci-95' => '95% 신뢰구간 (%p)',
        'prob-beat' => '대조군 우위 확률',
        'value' => '리드 가치',
        'baseline' => '대조군',
    ],

    'form' => [
        'create-title' => 'A/B 실험 생성',
        'edit-title' => 'A/B 실험 수정',
        'name' => '실험 이름',
        'code' => '실험 키',
        'status' => '상태',
        'traffic-bp' => '트래픽 할당 (베이시스 포인트)',
        'winner' => '종료 시 우승 배리언트',
        'winner-none' => '대조군 기본 유지',
        'description' => '가설 / 메모',
        'variants' => '배리언트 목록',
        'add-variant' => '배리언트 추가',
        'cancel' => '취소',
        'save' => '실험 저장',
    ],

    'validations' => [
        'weights-sum' => '배리언트 가중치 합계는 정확히 10000 베이시스 포인트(100%)여야 합니다.',
        'invalid-winner' => '선택한 우승 배리언트는 설정된 배리언트 키와 일치해야 합니다.',
        'locked-after-start' => '데이터 수집 시작 후에는 실험 키와 배리언트 가중치를 변경할 수 없습니다.',
    ],
];

<?php

return [
    'acl' => [
        'privacy' => '개인정보 요청',
    ],
    'panel' => [
        'title' => '개인정보 요청',
        'intro' => '이 사람의 데이터 열람 또는 삭제 요청을 처리합니다.',
        'export' => '데이터 다운로드 (JSON)',
        'erase-title' => '이 사람 삭제',
        'erase-warning' => '이름, 연락처, 이메일, 첨부 파일, 메모, 고객 정보와 변경 원장에 있는 데이터를 삭제합니다. 거래와 견적은 유지됩니다. 되돌릴 수 없습니다.',
        'confirm-label' => '확인하려면 :name 을(를) 입력하세요',
        'erase-button' => '영구 삭제',
        'erased-on' => '이 사람의 개인정보가 :date 에 삭제되었습니다.',
    ],
    'erased-success' => '개인정보를 삭제했습니다. 거래와 견적은 유지되었습니다.',
    'confirm-mismatch' => '입력한 이름이 일치하지 않습니다. 아무것도 삭제되지 않았습니다.',
    'already-erased' => '이 사람은 이미 삭제되었습니다.',
];

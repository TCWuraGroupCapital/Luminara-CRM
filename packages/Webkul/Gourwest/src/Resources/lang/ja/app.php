<?php

return [
    'menu' => [
        'title' => 'Gourwest グローバルハブ',
    ],

    'acl' => [
        'title' => 'Gourwest グローバルハブ',
        'customers' => '7カ国統合顧客プロファイルと名寄せ',
        'integrations' => '8システム連携ハブと再実行',
        'loyalty' => 'ロイヤルティ階層と越境ポイント管理',
        'sla' => '地域カスタマーサービス・SLAデスク',
        'benchmark' => 'サービス保証と3秒未満ベンチマーク',
    ],

    'index' => [
        'title' => 'Gourwest グローバルCRM・統合ハブ',
        'subtitle' => '7カ国（AU、NZ、SG、MY、TH、UK、CA）の統合ゴールデンレコード、8システムのリアルタイム連携監視、ロイヤルティ台帳、地域SLAデスク、3秒未満保証コントロール。',
        'seed-btn' => 'Gourwest グローバルデータを投入',
        'refresh-btn' => 'テレメトリ更新',
        'retry-btn' => '再試行',
        'load-error' => 'Gourwest グローバルハブのテレメトリを読み込めませんでした。再試行してください。',
        'empty-title' => 'Gourwest 多地域プロファイルが未作成です',
        'empty-description' => '7カ国の顧客プロファイル、11名のステークホルダー、8システム契約、地域間重複ペア、地域SLAチケットをワンクリックで投入します。',
    ],

    'kpis' => [
        'golden-records' => 'ゴールデン顧客レコード',
        'loyalty-liability' => '統合ロイヤルティポイント',
        'integrations-health' => '正常稼働システム契約',
        'sla-latency' => '検索 p95 レイテンシと未解決SLA',
    ],

    'tabs' => [
        'customers' => '7地域統合顧客',
        'integrations' => '8システム連携ハブ',
        'sla' => 'ロイヤルティ・地域SLAデスク',
        'benchmark' => 'サービス保証・<3秒ベンチマーク',
    ],

    'customers' => [
        'all-regions' => '全7地域',
        'search-placeholder' => '会員番号、ゴールデンUID、氏名、店舗で検索...',
        'duplicates-banner' => '地域間の重複顧客クラスタが検出されました',
        'deduplicate-btn' => '地域間の重複を統合',
        'adjust-points-btn' => 'ポイント調整',
        'merged-badge' => '統合済セカンダリ',
        'golden-badge' => 'ゴールデンプライマリ',
    ],

    'integrations' => [
        'replay-btn' => 'デッドレターを再実行',
        'sync-btn' => '今すぐ同期',
        'recent-events' => '最新のシステム間同期・Webhookストリーム',
        'webhook-endpoint' => 'HMAC-SHA256 Webhook エンドポイント',
    ],

    'sla' => [
        'new-ticket-btn' => '地域SLAチケットを起票',
        'resolve-btn' => 'チケット解決',
        'resolved-label' => '解決済',
        'loyalty-modal-title' => '越境ロイヤルティポイントの調整',
        'ticket-modal-title' => '地域カスタマーサービスSLAチケット起票',
        'points-delta' => 'ポイント増減 (+/-)',
        'reason' => '監査理由',
        'region' => '管轄・地域',
        'channel' => '受付チャネル',
        'category' => '申告カテゴリ',
        'priority' => 'SLA優先度',
        'subject' => '件名',
        'summary' => '調査メモ',
        'save-btn' => '保存',
        'cancel-btn' => 'キャンセル',
    ],

    'benchmark' => [
        'run-btn' => '25クエリ (<3秒) ライブベンチマーク実行',
        'sla-pass' => '保証達成 (<= 3,000 ms)',
        'sla-fail' => 'SLA超過 (> 3,000 ms)',
        'index-audit-title' => '500万件対応 複合インデックス・準備監査',
        'privacy-matrix-title' => '7カ国プライバシー法規・データ主権マトリクス',
    ],

    'messages' => [
        'seed-success' => 'Gourwest 7カ国エンタープライズデータセットを投入しました。',
        'deduplicate-success' => ':count 件の地域間重複プロファイルをゴールデンレコードへ統合しました。',
        'sync-success' => ':system のハートビートと地域テレメトリを同期しました。',
        'replay-success' => ':count 件のデッドレター連携イベントを再実行しました。',
        'loyalty-adjusted' => 'ロイヤルティポイント残高と階層を更新しました。',
        'ticket-created' => '地域SLAチケットを起票しました。',
        'ticket-resolved' => '地域SLAチケットを解決し、監査ログに記録しました。',
        'benchmark-complete' => '25クエリ (<3秒) 検索および500万件保証ベンチマークが完了しました。',
    ],
];

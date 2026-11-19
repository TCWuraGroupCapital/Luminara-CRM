<?php

return [
    'menu' => [
        'title' => 'A/B テスト実験',
        'title-info' => '管理された A/B スプリット実験を実行し、CRM ファネルのコンバージョン改善を測定します。',
    ],

    'acl' => [
        'title' => 'A/B テスト実験',
        'create' => '作成',
        'edit' => '編集',
        'delete' => '削除',
    ],

    'statuses' => [
        'draft' => '下書き',
        'running' => '実行中',
        'paused' => '一時停止',
        'concluded' => '完了',
    ],

    'index' => [
        'title' => 'A/B テスト実験',
        'subtitle' => 'プライバシーを保護したコンバージョン計測と決定論的トラフィック配分。',
        'create-btn' => '実験を作成',
        'edit-btn' => '編集',
        'delete-btn' => '削除',
        'retry-btn' => '再試行',
        'traffic' => 'トラフィック',
        'sample-ready' => 'サンプル十分 (n ≥ 100)',
        'sample-low' => 'サンプル収集中 (n < 100)',
        'srm-warning' => 'SRM 警告 (配分偏り)',
        'empty-title' => 'A/B 実験はまだ設定されていません',
        'empty-description' => '最初の実験を作成して、Web フォームやリード獲得ファネルのコンバージョン率改善を測定しましょう。',
        'load-error' => '実験データを読み込めませんでした。再試行してください。',
        'delete-confirm' => 'この実験と記録されたすべての計測イベントを削除しますか？',
        'create-success' => '実験を作成しました。',
        'update-success' => '実験を更新しました。',
        'delete-success' => '実験を削除しました。',
    ],

    'table' => [
        'variant' => 'バリアント',
        'split' => '配分比率',
        'exposures' => '露出数',
        'conversions' => 'コンバージョン数',
        'conv-rate' => 'CVR',
        'lift' => 'リフト率',
        'p-value' => '調整済 p値',
        'ci-95' => '95% CI (pp)',
        'prob-beat' => '基準超え確率',
        'value' => 'リード価値',
        'baseline' => '基準',
    ],

    'form' => [
        'create-title' => 'A/B 実験を作成',
        'edit-title' => 'A/B 実験を編集',
        'name' => '実験名',
        'code' => '実験キー',
        'status' => 'ステータス',
        'traffic-bp' => 'トラフィック配分 (ベーシスポイント)',
        'winner' => '完了時の勝者バリアント',
        'winner-none' => '基準バリアントを維持',
        'description' => '仮説・メモ',
        'variants' => 'バリアント一覧',
        'add-variant' => 'バリアントを追加',
        'cancel' => 'キャンセル',
        'save' => '保存',
    ],

    'validations' => [
        'weights-sum' => 'バリアントの重みの合計は正確に 10000 ベーシスポイント (100%) である必要があります。',
        'invalid-winner' => '選択された勝者バリアントは定義済みのバリアントキーと一致する必要があります。',
        'locked-after-start' => '計測開始後は実験キーおよびバリアント配分比率を変更できません。',
    ],
];

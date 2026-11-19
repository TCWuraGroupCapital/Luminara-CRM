<?php

return [
    'menu' => [
        'title' => 'Gourwest 全球中枢',
    ],

    'acl' => [
        'title' => 'Gourwest 全球中枢',
        'customers' => '七国统一客户档案与主数据去重',
        'integrations' => '八大系统集成中枢与死信重放',
        'loyalty' => '会员等级与跨境积分账本',
        'sla' => '区域客户服务与 SLA 工作台',
        'benchmark' => '服务质保与 <3秒 性能基准',
    ],

    'index' => [
        'title' => 'Gourwest 全球 CRM 与系统集成中枢',
        'subtitle' => '覆盖 7 个国家（AU、NZ、SG、MY、TH、UK、CA）的统一黄金客户档案、8 大业务系统实时遥测、跨境积分账本、区域 SLA 服务台与 <3秒 质保监控。',
        'seed-btn' => '初始化 Gourwest 全球数据集',
        'refresh-btn' => '刷新遥测数据',
        'retry-btn' => '重试',
        'load-error' => '无法加载 Gourwest 全球中枢遥测数据，请重试。',
        'empty-title' => '尚未初始化 Gourwest 跨区域客户档案',
        'empty-description' => '一键初始化 7 国客户档案、11 位项目干系人、8 大业务系统契约、跨区域重复样本及区域 SLA 工单。',
    ],

    'kpis' => [
        'golden-records' => '黄金客户主档案',
        'loyalty-liability' => '统一会员总积分',
        'integrations-health' => '健康系统契约数',
        'sla-latency' => '检索 p95 延迟与待办 SLA',
    ],

    'tabs' => [
        'customers' => '七国统一客户档案',
        'integrations' => '八大系统集成中枢',
        'sla' => '会员积分与区域 SLA 台',
        'benchmark' => '服务质保与 <3秒 基准',
    ],

    'customers' => [
        'all-regions' => '全部 7 个区域',
        'search-placeholder' => '搜索会员卡号、黄金 UID、客户姓名或门店...',
        'duplicates-banner' => '检测到跨区域重复客户档案簇',
        'deduplicate-btn' => '合并跨区域重复档案',
        'adjust-points-btn' => '调整积分',
        'merged-badge' => '已合并从属档案',
        'golden-badge' => '黄金主档案',
    ],

    'integrations' => [
        'replay-btn' => '重放死信队列事件',
        'sync-btn' => '立即同步',
        'recent-events' => '近期跨系统同步与 Webhook 事件流',
        'webhook-endpoint' => 'HMAC-SHA256 Webhook 接口',
    ],

    'sla' => [
        'new-ticket-btn' => '新建区域 SLA 工单',
        'resolve-btn' => '解决工单',
        'resolved-label' => '已解决',
        'loyalty-modal-title' => '调整跨境会员积分',
        'ticket-modal-title' => '登记区域客户服务 SLA 工单',
        'points-delta' => '积分增减 (+/-)',
        'reason' => '审计原因',
        'region' => '司法辖区 / 区域',
        'channel' => '服务渠道',
        'category' => '投诉类别',
        'priority' => 'SLA 优先级',
        'subject' => '工单主题',
        'summary' => '核查备注',
        'save-btn' => '保存',
        'cancel-btn' => '取消',
    ],

    'benchmark' => [
        'run-btn' => '运行 25 次实时检索 (<3秒) 基准测试',
        'sla-pass' => '通过 <= 3,000 ms 服务质保',
        'sla-fail' => '超出 SLA (> 3,000 ms)',
        'index-audit-title' => '5,000,000 级复合索引与容量就绪审计',
        'privacy-matrix-title' => '七国隐私法规与数据主权合规矩阵',
    ],

    'messages' => [
        'seed-success' => 'Gourwest 七国企业级数据集初始化成功。',
        'deduplicate-success' => '已将 :count 条跨区域重复档案无损合并至黄金主档案。',
        'sync-success' => '已同步 :system 心跳与区域遥测数据。',
        'replay-success' => '已成功重放 :count 条死信集成事件。',
        'loyalty-adjusted' => '已更新客户积分余额并重新计算会员等级。',
        'ticket-created' => '已成功创建区域 SLA 服务工单。',
        'ticket-resolved' => '已解决区域 SLA 工单并写入安全审计日志。',
        'benchmark-complete' => '已完成 25 次 (<3秒) 检索与 500 万级容量质保基准测试。',
    ],
];

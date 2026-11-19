<?php

return [
    'menu' => [
        'title' => 'A/B 实验',
        'title-info' => '运行受控 A/B 分流实验并追踪 CRM 漏斗转化率提升。',
    ],

    'acl' => [
        'title' => 'A/B 实验',
        'create' => '创建',
        'edit' => '编辑',
        'delete' => '删除',
    ],

    'statuses' => [
        'draft' => '草稿',
        'running' => '运行中',
        'paused' => '已暂停',
        'concluded' => '已结束',
    ],

    'index' => [
        'title' => 'A/B 实验',
        'subtitle' => '基于基点的确定性流量分桶与隐私保护转化归因。',
        'create-btn' => '创建实验',
        'edit-btn' => '编辑',
        'delete-btn' => '删除',
        'retry-btn' => '重试',
        'traffic' => '流量比例',
        'sample-ready' => '样本充足 (n ≥ 100)',
        'sample-low' => '样本收集中 (n < 100)',
        'srm-warning' => 'SRM 警告 (分流偏差)',
        'empty-title' => '尚未配置任何 A/B 实验',
        'empty-description' => '创建您的第一个受控实验，以衡量网页表单与线索漏斗的转化提升。',
        'load-error' => '无法加载实验列表，请重试。',
        'delete-confirm' => '确定删除此实验及其记录的所有遥测事件吗？',
        'create-success' => '实验创建成功。',
        'update-success' => '实验更新成功。',
        'delete-success' => '实验删除成功。',
    ],

    'table' => [
        'variant' => '变体',
        'split' => '分流比例',
        'exposures' => '曝光数',
        'conversions' => '转化数',
        'conv-rate' => '转化率',
        'lift' => '相对对照组提升',
        'p-value' => '校正 p 值',
        'ci-95' => '95% 置信区间（百分点）',
        'prob-beat' => '胜出概率',
        'value' => '线索价值',
        'baseline' => '对照组',
    ],

    'form' => [
        'create-title' => '创建 A/B 实验',
        'edit-title' => '编辑 A/B 实验',
        'name' => '实验名称',
        'code' => '实验标识码',
        'status' => '状态',
        'traffic-bp' => '流量分配 (基点)',
        'winner' => '结束获胜变体',
        'winner-none' => '默认回退对照组',
        'description' => '实验假设 / 备注',
        'variants' => '变体配置',
        'add-variant' => '添加变体',
        'cancel' => '取消',
        'save' => '保存实验',
    ],

    'validations' => [
        'weights-sum' => '所有变体的权重总和必须恰好等于 10000 基点 (100%)。',
        'invalid-winner' => '所选获胜变体必须与已配置的变体键匹配。',
        'locked-after-start' => '实验开始收集遥测数据后，无法修改实验标识码和变体权重。',
    ],
];

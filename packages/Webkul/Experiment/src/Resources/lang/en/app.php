<?php

return [
    'menu' => [
        'title' => 'A/B Experiments',
        'title-info' => 'Run controlled A/B split experiments and track conversion lift across CRM funnels.',
    ],

    'acl' => [
        'title' => 'A/B Experiments',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],

    'statuses' => [
        'draft' => 'Draft',
        'running' => 'Running',
        'paused' => 'Paused',
        'concluded' => 'Concluded',
    ],

    'index' => [
        'title' => 'A/B Experiments',
        'subtitle' => 'Deterministic basis-point traffic bucketing with privacy-safe conversion attribution.',
        'create-btn' => 'Create Experiment',
        'edit-btn' => 'Edit',
        'delete-btn' => 'Delete',
        'retry-btn' => 'Retry',
        'traffic' => 'Traffic',
        'sample-ready' => 'Sample adequate (n ≥ 100)',
        'sample-low' => 'Collecting sample (n < 100)',
        'srm-warning' => 'SRM Alert (traffic skew)',
        'empty-title' => 'No A/B experiments configured yet',
        'empty-description' => 'Create your first controlled experiment to measure conversion lift across web forms, landing variants, and lead funnels.',
        'load-error' => 'Unable to load experiments. Please verify your connection and retry.',
        'delete-confirm' => 'Delete this experiment and all recorded telemetry events?',
        'create-success' => 'Experiment created successfully.',
        'update-success' => 'Experiment updated successfully.',
        'delete-success' => 'Experiment deleted successfully.',
    ],

    'table' => [
        'variant' => 'Variant',
        'split' => 'Split',
        'exposures' => 'Exposures',
        'conversions' => 'Conversions',
        'conv-rate' => 'Conv. Rate',
        'lift' => 'Lift vs Control',
        'p-value' => 'Adj. p-Value',
        'ci-95' => '95% CI (pp)',
        'prob-beat' => 'P(Beat Control)',
        'value' => 'Lead Value',
        'baseline' => 'Baseline',
    ],

    'form' => [
        'create-title' => 'Create A/B Experiment',
        'edit-title' => 'Edit A/B Experiment',
        'name' => 'Experiment Name',
        'code' => 'Experiment Key',
        'status' => 'Status',
        'traffic-bp' => 'Traffic Allocation (Basis Points)',
        'winner' => 'Concluded Winner Variant',
        'winner-none' => 'Default to Control Baseline',
        'description' => 'Hypothesis / Notes',
        'variants' => 'Variants',
        'add-variant' => 'Add Variant',
        'cancel' => 'Cancel',
        'save' => 'Save Experiment',
    ],

    'validations' => [
        'weights-sum' => 'Variant weights must sum to exactly 10000 basis points (100%).',
        'invalid-winner' => 'Selected winner variant must match one of the configured variant keys.',
        'locked-after-start' => 'Experiment key and variant weights cannot be modified after telemetry collection has started.',
    ],
];

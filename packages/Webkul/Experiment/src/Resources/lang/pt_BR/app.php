<?php

return [
    'menu' => [
        'title' => 'Experimentos A/B',
        'title-info' => 'Execute experimentos A/B controlados e acompanhe o ganho de conversão nos funis do CRM.',
    ],

    'acl' => [
        'title' => 'Experimentos A/B',
        'create' => 'Criar',
        'edit' => 'Editar',
        'delete' => 'Excluir',
    ],

    'statuses' => [
        'draft' => 'Rascunho',
        'running' => 'Em execução',
        'paused' => 'Pausado',
        'concluded' => 'Concluído',
    ],

    'index' => [
        'title' => 'Experimentos A/B',
        'subtitle' => 'Alocação determinística de tráfego com atribuição de conversões preservando a privacidade.',
        'create-btn' => 'Criar Experimento',
        'edit-btn' => 'Editar',
        'delete-btn' => 'Excluir',
        'retry-btn' => 'Tentar novamente',
        'traffic' => 'Tráfego',
        'sample-ready' => 'Amostra adequada (n ≥ 100)',
        'sample-low' => 'Coletando amostra (n < 100)',
        'srm-warning' => 'Alerta SRM (desvio de tráfego)',
        'empty-title' => 'Nenhum experimento A/B configurado',
        'empty-description' => 'Crie seu primeiro experimento controlado para medir o ganho de conversão em formulários e funis.',
        'load-error' => 'Não foi possível carregar os experimentos. Tente novamente.',
        'delete-confirm' => 'Excluir este experimento e todos os eventos registrados?',
        'create-success' => 'Experimento criado com sucesso.',
        'update-success' => 'Experimento atualizado com sucesso.',
        'delete-success' => 'Experimento excluído com sucesso.',
    ],

    'table' => [
        'variant' => 'Variante',
        'split' => 'Divisão',
        'exposures' => 'Exposições',
        'conversions' => 'Conversões',
        'conv-rate' => 'Taxa Conv.',
        'lift' => 'Ganho vs Controle',
        'p-value' => 'Valor-p ajust.',
        'ci-95' => 'IC 95% (p.p.)',
        'prob-beat' => 'P(Superar Controle)',
        'value' => 'Valor de Leads',
        'baseline' => 'Base',
    ],

    'form' => [
        'create-title' => 'Criar Experimento A/B',
        'edit-title' => 'Editar Experimento A/B',
        'name' => 'Nome do Experimento',
        'code' => 'Chave do Experimento',
        'status' => 'Status',
        'traffic-bp' => 'Alocação de Tráfego (Pontos-base)',
        'winner' => 'Variante Vencedora ao Concluir',
        'winner-none' => 'Padrão para Controle Base',
        'description' => 'Hipótese / Notas',
        'variants' => 'Variantes',
        'add-variant' => 'Adicionar Variante',
        'cancel' => 'Cancelar',
        'save' => 'Salvar Experimento',
    ],

    'validations' => [
        'weights-sum' => 'Os pesos das variantes devem somar exatamente 10000 pontos-base (100%).',
        'invalid-winner' => 'A variante vencedora deve corresponder a uma das variantes configuradas.',
        'locked-after-start' => 'A chave do experimento e os pesos não podem ser alterados após o início da coleta.',
    ],
];

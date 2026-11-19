<?php

return [
    'menu' => [
        'title' => 'Hub Global Gourwest',
    ],

    'acl' => [
        'title' => 'Hub Global Gourwest',
        'customers' => 'Perfis Unificados de 7 Países e Deduplicação',
        'integrations' => 'Hub de Integração de 8 Sistemas e Reprocessamento',
        'loyalty' => 'Níveis de Fidelidade e Pontos Internacionais',
        'sla' => 'Central Regional de Atendimento e SLA',
        'benchmark' => 'Garantia de Serviço e Benchmark <3s',
    ],

    'index' => [
        'title' => 'Hub Global de CRM e Integração Gourwest',
        'subtitle' => 'Golden Records unificados em 7 países (AU, NZ, SG, MY, TH, UK, CA), telemetria em tempo real de 8 sistemas, razão de fidelidade, central regional de SLA e controles de garantia <3s.',
        'seed-btn' => 'Popular Dados Globais Gourwest',
        'refresh-btn' => 'Atualizar Telemetria',
        'retry-btn' => 'Tentar Novamente',
        'load-error' => 'Não foi possível carregar a telemetria do Hub Global Gourwest. Tente novamente.',
        'empty-title' => 'Nenhum Perfil Multirregional Gourwest Provisionado',
        'empty-description' => 'Provisione os perfis de 7 países, 11 partes interessadas, 8 contratos de sistemas, pares duplicados e tickets de SLA com um clique.',
    ],

    'kpis' => [
        'golden-records' => 'Golden Records de Clientes',
        'loyalty-liability' => 'Pontos de Fidelidade Unificados',
        'integrations-health' => 'Contratos de Sistema Saudáveis',
        'sla-latency' => 'Latência p95 e SLAs Abertos',
    ],

    'tabs' => [
        'customers' => 'Clientes Unificados (7 Regiões)',
        'integrations' => 'Hub de 8 Sistemas',
        'sla' => 'Fidelidade e Central SLA Regional',
        'benchmark' => 'Garantia e Benchmark <3s',
    ],

    'customers' => [
        'all-regions' => 'Todas as 7 Regiões',
        'search-placeholder' => 'Buscar cartão, Golden UID, nome do cliente ou unidade...',
        'duplicates-banner' => 'Clusters duplicados entre regiões detectados',
        'deduplicate-btn' => 'Consolidar Duplicidades entre Regiões',
        'adjust-points-btn' => 'Ajustar Pontos',
        'merged-badge' => 'Secundário Mesclado',
        'golden-badge' => 'Golden Principal',
    ],

    'integrations' => [
        'replay-btn' => 'Reprocessar Dead-Letters',
        'sync-btn' => 'Sincronizar Agora',
        'recent-events' => 'Fluxo Recente de Sincronização e Webhooks',
        'webhook-endpoint' => 'Endpoint Webhook HMAC-SHA256',
    ],

    'sla' => [
        'new-ticket-btn' => 'Abrir Ticket SLA Regional',
        'resolve-btn' => 'Resolver Ticket',
        'resolved-label' => 'Resolvido',
        'loyalty-modal-title' => 'Ajustar Pontos de Fidelidade Internacionais',
        'ticket-modal-title' => 'Registrar Ticket Regional de Atendimento SLA',
        'points-delta' => 'Ajuste de Pontos (+/-)',
        'reason' => 'Motivo de Auditoria',
        'region' => 'Jurisdição / Região',
        'channel' => 'Canal de Atendimento',
        'category' => 'Categoria da Solicitação',
        'priority' => 'Prioridade SLA',
        'subject' => 'Assunto',
        'summary' => 'Notas de Investigação',
        'save-btn' => 'Salvar',
        'cancel-btn' => 'Cancelar',
    ],

    'benchmark' => [
        'run-btn' => 'Executar Benchmark ao Vivo de 25 Consultas (<3s)',
        'sla-pass' => 'APROVADO NA GARANTIA <= 3.000 ms',
        'sla-fail' => 'VIOLAÇÃO DE SLA (> 3.000 ms)',
        'index-audit-title' => 'Auditoria de Índices Compostos e Capacidade para 5.000.000 de Registros',
        'privacy-matrix-title' => 'Matriz de Leis de Privacidade e Soberania de Dados (7 Países)',
    ],

    'messages' => [
        'seed-success' => 'Conjunto de dados corporativos de 7 países da Gourwest populado com sucesso.',
        'deduplicate-success' => 'Consolidados :count perfis duplicados entre regiões em Golden Records.',
        'sync-success' => 'Pulso e telemetria regional sincronizados para :system.',
        'replay-success' => 'Reprocessados :count eventos dead-letter com sucesso.',
        'loyalty-adjusted' => 'Saldo de pontos atualizado e nível recalculado.',
        'ticket-created' => 'Ticket regional de SLA registrado com sucesso.',
        'ticket-resolved' => 'Ticket regional de SLA resolvido e auditado.',
        'benchmark-complete' => 'Benchmark de 25 consultas (<3s) e prontidão para 5M concluído.',
    ],
];

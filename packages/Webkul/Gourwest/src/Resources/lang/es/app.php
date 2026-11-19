<?php

return [
    'menu' => [
        'title' => 'Centro Global Gourwest',
    ],

    'acl' => [
        'title' => 'Centro Global Gourwest',
        'customers' => 'Perfiles Unificados de 7 Países y Deduplicación',
        'integrations' => 'Centro de Integración de 8 Sistemas y Reintento',
        'loyalty' => 'Niveles de Lealtad y Puntos Transfronterizos',
        'sla' => 'Mesa Regional de Atención al Cliente y SLA',
        'benchmark' => 'Garantía de Servicio y Benchmark <3s',
    ],

    'index' => [
        'title' => 'Centro Global de CRM e Integración Gourwest',
        'subtitle' => 'Registros maestros unificados en 7 países (AU, NZ, SG, MY, TH, UK, CA), telemetría en tiempo real de 8 sistemas, libro mayor de lealtad, mesa regional SLA y controles de garantía <3s.',
        'seed-btn' => 'Cargar Datos Globales de Gourwest',
        'refresh-btn' => 'Actualizar Telemetría',
        'retry-btn' => 'Reintentar',
        'load-error' => 'No se pudo cargar la telemetría del Centro Global Gourwest. Inténtelo de nuevo.',
        'empty-title' => 'Aún No Se Han Aprovisionado Perfiles Multirregionales de Gourwest',
        'empty-description' => 'Aprovisione los perfiles de clientes en 7 países, 11 partes interesadas, 8 contratos de sistemas, pares duplicados y tickets SLA con un clic.',
    ],

    'kpis' => [
        'golden-records' => 'Registros Maestros de Clientes',
        'loyalty-liability' => 'Puntos de Lealtad Unificados',
        'integrations-health' => 'Contratos de Sistema Saludables',
        'sla-latency' => 'Latencia p95 y SLAs Abiertos',
    ],

    'tabs' => [
        'customers' => 'Clientes Unificados (7 Regiones)',
        'integrations' => 'Centro de 8 Sistemas',
        'sla' => 'Lealtad y Mesa Regional SLA',
        'benchmark' => 'Garantía y Benchmark <3s',
    ],

    'customers' => [
        'all-regions' => 'Las 7 Regiones',
        'search-placeholder' => 'Buscar por ID de miembro, UID maestro, nombre o sucursal...',
        'duplicates-banner' => 'Se detectaron grupos duplicados entre regiones',
        'deduplicate-btn' => 'Resolver Duplicados Interregionales',
        'adjust-points-btn' => 'Ajustar Puntos',
        'merged-badge' => 'Secundario Fusionado',
        'golden-badge' => 'Maestro Principal',
    ],

    'integrations' => [
        'replay-btn' => 'Reprocesar Eventos Fallidos',
        'sync-btn' => 'Sincronizar Ahora',
        'recent-events' => 'Flujo Reciente de Sincronización y Webhooks',
        'webhook-endpoint' => 'Endpoint Webhook HMAC-SHA256',
    ],

    'sla' => [
        'new-ticket-btn' => 'Registrar Ticket SLA Regional',
        'resolve-btn' => 'Resolver Ticket',
        'resolved-label' => 'Resuelto',
        'loyalty-modal-title' => 'Ajustar Puntos de Lealtad Transfronterizos',
        'ticket-modal-title' => 'Registrar Ticket Regional de Servicio al Cliente SLA',
        'points-delta' => 'Ajuste de Puntos (+/-)',
        'reason' => 'Motivo de Auditoría',
        'region' => 'Jurisdicción / Región',
        'channel' => 'Canal de Atención',
        'category' => 'Categoría del Reclamo',
        'priority' => 'Prioridad SLA',
        'subject' => 'Asunto',
        'summary' => 'Notas de Investigación',
        'save-btn' => 'Guardar',
        'cancel-btn' => 'Cancelar',
    ],

    'benchmark' => [
        'run-btn' => 'Ejecutar Benchmark en Vivo de 25 Consultas (<3s)',
        'sla-pass' => 'CUMPLE GARANTÍA <= 3,000 ms',
        'sla-fail' => 'INCUMPLIMIENTO SLA (> 3,000 ms)',
        'index-audit-title' => 'Auditoría de Índices Compuestos y Capacidad de 5,000,000 de Registros',
        'privacy-matrix-title' => 'Matriz de Leyes de Privacidad y Soberanía de Datos (7 Países)',
    ],

    'messages' => [
        'seed-success' => 'Conjunto de datos empresariales de 7 países de Gourwest cargado correctamente.',
        'deduplicate-success' => 'Se consolidaron :count perfiles duplicados interregionales en Registros Maestros.',
        'sync-success' => 'Pulso y telemetría regional sincronizados para :system.',
        'replay-success' => 'Se reprocesaron :count eventos fallidos de integración correctamente.',
        'loyalty-adjusted' => 'Saldo de lealtad actualizado y nivel recalculado.',
        'ticket-created' => 'Ticket regional de SLA registrado correctamente.',
        'ticket-resolved' => 'Ticket regional de SLA resuelto y registrado en auditoría.',
        'benchmark-complete' => 'Benchmark de 25 consultas (<3s) y capacidad de 5M completado.',
    ],
];

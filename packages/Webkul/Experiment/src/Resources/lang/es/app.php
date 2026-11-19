<?php

return [
    'menu' => [
        'title' => 'Experimentos A/B',
        'title-info' => 'Ejecute pruebas A/B controladas y mida el impacto en conversiones del CRM.',
    ],

    'acl' => [
        'title' => 'Experimentos A/B',
        'create' => 'Crear',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
    ],

    'statuses' => [
        'draft' => 'Borrador',
        'running' => 'En ejecución',
        'paused' => 'En pausa',
        'concluded' => 'Concluido',
    ],

    'index' => [
        'title' => 'Experimentos A/B',
        'subtitle' => 'Asignación determinista por puntos básicos con atribución de conversiones privada.',
        'create-btn' => 'Crear experimento',
        'edit-btn' => 'Editar',
        'delete-btn' => 'Eliminar',
        'retry-btn' => 'Reintentar',
        'traffic' => 'Tráfico',
        'sample-ready' => 'Muestra suficiente (n ≥ 100)',
        'sample-low' => 'Recolectando muestra (n < 100)',
        'srm-warning' => 'Alerta SRM (sesgo de tráfico)',
        'empty-title' => 'Aún no hay experimentos A/B configurados',
        'empty-description' => 'Cree su primer experimento controlado para medir mejoras de conversión en formularios web y embudos.',
        'load-error' => 'No se pudieron cargar los experimentos. Inténtelo de nuevo.',
        'delete-confirm' => '¿Eliminar este experimento y toda su telemetría registrada?',
        'create-success' => 'Experimento creado correctamente.',
        'update-success' => 'Experimento actualizado correctamente.',
        'delete-success' => 'Experimento eliminado correctamente.',
    ],

    'table' => [
        'variant' => 'Variante',
        'split' => 'División',
        'exposures' => 'Exposiciones',
        'conversions' => 'Conversiones',
        'conv-rate' => 'Tasa Conv.',
        'lift' => 'Mejora vs Control',
        'p-value' => 'Valor p ajust.',
        'ci-95' => 'IC 95% (pp)',
        'prob-beat' => 'P(Superar Control)',
        'value' => 'Valor de Leads',
        'baseline' => 'Base',
    ],

    'form' => [
        'create-title' => 'Crear experimento A/B',
        'edit-title' => 'Editar experimento A/B',
        'name' => 'Nombre del experimento',
        'code' => 'Clave del experimento',
        'status' => 'Estado',
        'traffic-bp' => 'Asignación de tráfico (puntos básicos)',
        'winner' => 'Variante ganadora al concluir',
        'winner-none' => 'Volver al control base',
        'description' => 'Hipótesis / Notas',
        'variants' => 'Variantes',
        'add-variant' => 'Añadir variante',
        'cancel' => 'Cancelar',
        'save' => 'Guardar experimento',
    ],

    'validations' => [
        'weights-sum' => 'Los pesos de las variantes deben sumar exactamente 10000 puntos básicos (100%).',
        'invalid-winner' => 'La variante ganadora debe coincidir con una de las variantes configuradas.',
        'locked-after-start' => 'La clave del experimento y los pesos no pueden modificarse tras iniciar la recolección.',
    ],
];

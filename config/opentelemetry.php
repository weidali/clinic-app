<?php

return [
    'enabled' => env('OTEL_ENABLED', false),

    'service' => [
        'name' => env('OTEL_SERVICE_NAME', 'clinic-app'),
        'version' => env('OTEL_SERVICE_VERSION', '1.0.0'),
    ],

    'traces' => [
        'enabled' => true,
        'sampler' => [
            'type' => env('OTEL_TRACES_SAMPLER', 'traceidratio'),
            'rate' => env('OTEL_TRACES_SAMPLER_ARG', 0.5),
        ],
        'exporter' => [
            'type' => 'zipkin',
            'endpoint' => env('OTEL_EXPORTER_ENDPOINT', 'http://localhost:9411/api/v2/spans'),
        ],
    ],
];

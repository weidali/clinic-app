<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Zipkin\Exporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOffSampler;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class OpenTelemetryProvider extends ServiceProvider
{
    private ?TracerInterface $tracer = null;

    public function register(): void
    {
        $this->app->singleton(TracerInterface::class, function ($app) {
            return $this->createTracer();
        });
    }

    public function boot(): void
    {
        if (!Config::get('opentelemetry.enabled')) {
            return;
        }

        // Получаем и сохраняем tracer
        $this->tracer = $this->app->make(TracerInterface::class);
        // Регистрируем глобальный доступ (опционально)
        $this->registerGlobalTracer();

        if (Config::get('opentelemetry.metrics.enabled', false)) {
            $this->setupMetrics();
        }

        if (Config::get('opentelemetry.logs.enabled', false)) {
            $this->setupLogs();
        }
    }

    private function setupMetrics(): void
    {
        // OpenTelemetry metrics пока не полностью реализованы в PHP
    }

    private function setupLogs(): void
    {
        $logLevel = Config::get('opentelemetry.logs.level', 'info');
        // Настройка логирования
    }

    private function createTracer(): TracerInterface
    {
        $sampler = $this->createSampler();

        // экспортер для Zipkin
        $exporter = new Exporter(
            Config::get('opentelemetry.service.name'),
            Config::get('opentelemetry.traces.exporter.endpoint'),
        );

        $spanProcessor = new SimpleSpanProcessor($exporter);

        $tracerProvider = new TracerProvider(
            $spanProcessor,
            null, // Resource
            $sampler, // Sampler
            null, // SpanLimits
            null, // IdGenerator
            Attributes::create([
                'environment' => app()->environment(),
                'app.version' => Config::get('opentelemetry.service.version'),
                'deployment.environment' => app()->environment(),
            ])
        );

        return $tracerProvider->getTracer(
            Config::get('opentelemetry.service.name'),
            Config::get('opentelemetry.service.version'),
        );
    }

    private function createSampler(): TraceIdRatioBasedSampler|AlwaysOffSampler|AlwaysOnSampler
    {
        $samplerConfig = Config::get('opentelemetry.traces.sampler');

        switch ($samplerConfig['type']) {
            case 'always_off':
                return new AlwaysOffSampler();
            case 'traceidratio':
                return new TraceIdRatioBasedSampler($samplerConfig['rate'] ?? 0.5);
            default:
                return new AlwaysOnSampler();
        }
    }

    private function registerGlobalTracer(): void
    {
        if ($this->tracer && method_exists(Globals::class, 'setTracerProvider')) {
            $tracerProvider = $this->tracer->getTracerProvider();
            Globals::setTracerProvider($tracerProvider);
        }
    }

    /**
     * Получить tracer для использования в приложении
     */
    public static function getTracer(): ?TracerInterface
    {
        return app(TracerInterface::class);
    }
}

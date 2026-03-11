<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Zipkin\Exporter;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
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
        if (!Config::get('opentelemetry.enabled', false)) {
            return;
        }

        $this->tracer = $this->app->make(TracerInterface::class);
    }

    private function createTracer(): TracerInterface
    {
        $transport = PsrTransportFactory::discover()->create(
            Config::get('opentelemetry.traces.exporter.endpoint', 'http://localhost:9411/api/v2/spans'),
            'application/json',
            [
                'Content-Type' => 'application/json',
            ]
        );
        $exporter = new Exporter($transport);
        $spanProcessor = new SimpleSpanProcessor($exporter);
        $sampler = $this->createSampler();
        $tracerProvider = new TracerProvider(
            $spanProcessor,
            $sampler
        );

        return $tracerProvider->getTracer(
            Config::get('opentelemetry.service.name', 'clinic-app')
        );
    }

    private function createSampler()
    {
        $samplerConfig = Config::get('opentelemetry.traces.sampler', ['type' => 'always_on', 'rate' => 0.5]);

        return match($samplerConfig['type'] ?? 'always_on') {
            'always_off' => new AlwaysOffSampler(),
            'traceidratio' => new TraceIdRatioBasedSampler((float) ($samplerConfig['rate'] ?? 0.5)),
            default => new AlwaysOnSampler(),
        };
    }

    public static function getTracer(): ?TracerInterface
    {
        return app(TracerInterface::class);
    }
}

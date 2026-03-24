<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return 'it works';
});

Route::get('/test-trace', function() {
    $tracer = App\Providers\OpenTelemetryProvider::getTracer();

    $span = $tracer->spanBuilder('manual.test.span')
        ->setAttribute('test.key', 'test.value')
        ->startSpan();

    $span->end();

    return 'Trace sent! Check Zipkin';
});

Route::get('/zipkin-test', function() {
    $tracer = App\Providers\OpenTelemetryProvider::getTracer();

    $span = $tracer->spanBuilder('manual.test.from.browser')
        ->setAttribute('test.source', 'browser')
        ->setAttribute('test.time', now()->toIso8601String())
        ->setAttribute('test.random', rand(1, 1000))
        ->startSpan();

    usleep(rand(10000, 100000));

    $span->setAttribute('test.completed', true);
    $span->end();

    return response()->json([
        'status' => 'trace sent',
        'message' => 'Проверьте Zipkin через 5-10 секунд',
        'zipkin_url' => 'http://localhost:9411'
    ]);
});

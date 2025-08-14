<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Swoole\Http\Response;

final class MetricsController {
    public function __construct(private CollectorRegistry $registry) {}
    public function expose(array $_, Response $res): void {
        $renderer = new RenderTextFormat();
        $res->header('Content-Type', RenderTextFormat::MIME_TYPE);
        $res->end($renderer->render($this->registry->getMetricFamilySamples()));
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class MetricsController extends Controller
{
    /**
     * Menyajikan metrik Prometheus dalam format teks standar (Prometheus exposition format).
     */
    public function __invoke(): Response
    {
        $filePath = storage_path('framework/prometheus_metrics.json');
        $data = [];

        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if (!empty($content)) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        $lines = [];
        $lines[] = '# HELP http_requests_total Total number of HTTP requests processed by Book Management API.';
        $lines[] = '# TYPE http_requests_total counter';

        if (empty($data)) {
            // Berikan baseline counter 0 agar Prometheus langsung mengenali metrik bahkan sebelum ada traffic
            $lines[] = 'http_requests_total{method="GET",path="/api/books",status="200"} 0';
        } else {
            foreach ($data as $key => $count) {
                $parts = explode('|', $key);
                if (count($parts) === 3) {
                    [$method, $path, $status] = $parts;
                    $cleanMethod = addslashes($method);
                    $cleanPath = addslashes($path);
                    $cleanStatus = addslashes($status);
                    $lines[] = sprintf(
                        'http_requests_total{method="%s",path="%s",status="%s"} %d',
                        $cleanMethod,
                        $cleanPath,
                        $cleanStatus,
                        $count
                    );
                }
            }
        }

        // Output wajib berakhiran newline
        $body = implode("\n", $lines) . "\n";

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}


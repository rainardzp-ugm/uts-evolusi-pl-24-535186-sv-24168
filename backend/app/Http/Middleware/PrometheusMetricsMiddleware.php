<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetricsMiddleware
{
    /**
     * Menangani request masuk dan mencatat metrik HTTP request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Jangan catat endpoint internal monitoring agar tidak looping atau mengotori data
        if ($request->is('metrics') || $request->is('health') || $request->is('up')) {
            return $response;
        }

        try {
            $this->recordMetric($request, $response);
        } catch (\Throwable $e) {
            // Abaikan kesalahan pencatatan agar tidak menggagalkan response aplikasi utama
        }

        return $response;
    }

    /**
     * Menyimpan counter HTTP request ke file storage dengan file locking.
     */
    private function recordMetric(Request $request, Response $response): void
    {
        $method = $request->method();

        // Normalisasi path menggunakan route pattern jika tersedia untuk mencegah cardinality explosion
        // Contoh: /api/books/12 -> /api/books/{id}
        $route = $request->route();
        if ($route) {
            $path = '/' . ltrim($route->uri(), '/');
        } else {
            $path = '/' . ltrim($request->path(), '/');
        }

        $status = (string) $response->getStatusCode();
        $key = "{$method}|{$path}|{$status}";

        $storageDir = storage_path('framework');
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }

        $filePath = $storageDir . '/prometheus_metrics.json';

        $fp = @fopen($filePath, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $size = filesize($filePath);
            $content = $size > 0 ? fread($fp, $size) : '';
            $data = !empty($content) ? json_decode($content, true) : [];
            if (!is_array($data)) {
                $data = [];
            }

            $data[$key] = ($data[$key] ?? 0) + 1;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}


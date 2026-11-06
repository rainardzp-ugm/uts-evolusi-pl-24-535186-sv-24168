<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $checks = [
            'database' => $database,
            'cache' => $database['status'] === 'ok'
                ? $this->checkCache()
                : $this->cacheUnavailable(),
        ];

        $healthy = collect($checks)->every(
            fn (array $check): bool => $check['status'] === 'ok'
        );

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            $config = DB::connection()->getConfig();
            if ($config['driver'] === 'mysql') {
                $socket = @fsockopen(
                    (string) $config['host'],
                    (int) $config['port'],
                    $errorCode,
                    $errorMessage,
                    2
                );

                if (! is_resource($socket)) {
                    throw new \RuntimeException('Database host is unreachable.');
                }

                fclose($socket);
            }

            DB::connection()->select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            Log::error('Health check dependency failed', [
                'actor' => 'system',
                'action' => 'check database connectivity',
                'reason' => 'database connection check failed',
                'exception' => $exception::class,
            ]);

            return [
                'status' => 'failed',
                'reason' => 'database unavailable',
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health-check-'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);
            $cacheAvailable = Cache::get($key) === 'ok';
            Cache::forget($key);

            if (! $cacheAvailable) {
                throw new \RuntimeException('Cache read/write check failed.');
            }

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            Log::error('Health check dependency failed', [
                'actor' => 'system',
                'action' => 'check cache read and write',
                'reason' => 'cache read/write check failed',
                'exception' => $exception::class,
            ]);

            return [
                'status' => 'failed',
                'reason' => 'cache unavailable',
            ];
        }
    }

    private function cacheUnavailable(): array
    {
        Log::error('Health check dependency failed', [
            'actor' => 'system',
            'action' => 'check cache read and write',
            'reason' => 'cache unavailable because database is unavailable',
        ]);

        return [
            'status' => 'failed',
            'reason' => 'cache unavailable',
        ];
    }
}

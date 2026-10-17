<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DatabaseAvailability
{
    public static function sqlState(Throwable $exception): ?string
    {
        do {
            if ($exception instanceof PDOException) {
                $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());

                if (str_starts_with($state, '08') || in_array($state, ['57P01', '57P02', '57P03', '53300', '28000', '28P01', '3D000'], true)) {
                    return $state;
                }
            }
        } while ($exception = $exception->getPrevious());

        return null;
    }

    public static function report(Throwable $exception): void
    {
        $state = self::sqlState($exception);

        try {
            // The default cache and sessions may both depend on the failed database.
            if (Cache::store('file')->add('database-outage:'.$state, true, 60)) {
                Log::channel('database_outages')->warning('Database connection unavailable.', [
                    'sqlstate' => $state,
                    'driver' => config('database.connections.'.config('database.default').'.driver'),
                ]);
            }
        } catch (Throwable) {
            // A logging/storage failure must not replace the safe outage response.
        }
    }

    public static function response(Request $request): Response
    {
        $headers = [
            'Cache-Control' => 'no-store, private',
            'Retry-After' => '60',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ];

        if ($request->routeIs('database.readiness')) {
            return response()->json(['status' => 'unavailable'], 503, $headers);
        }

        if ($request->is('api/*') || ($request->expectsJson() && ! $request->header('X-Inertia'))) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'Service temporarily unavailable. Check the request status before retrying any submitted operation. No action has been queued.',
            ], 503, $headers);
        }

        return response()->view('errors.database-unavailable', [], 503, $headers);
    }
}

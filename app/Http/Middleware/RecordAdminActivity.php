<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAdminActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe()) {
            return $response;
        }

        $route = $request->route();
        $subject = null;
        $params = [];

        foreach ($route?->parameters() ?? [] as $key => $value) {
            if ($value instanceof Model) {
                $subject ??= $value;
                $params[$key] = $value->getKey();
            } elseif (is_scalar($value)) {
                $params[$key] = $value;
            }
        }

        // Only the route name, bound parameters and status are stored; request
        // bodies may contain passwords and are deliberately never recorded.
        ActivityLog::record((string) $route?->getName(), $subject, [
            'params' => $params,
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}

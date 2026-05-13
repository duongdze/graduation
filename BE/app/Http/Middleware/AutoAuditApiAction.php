<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AutoAuditApiAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldAudit($request, $response)) {
            [$entityType, $entityId] = $this->resolveEntity($request);

            $this->auditLogService->log(
                $request->user()?->id,
                $this->resolveAction($request),
                $entityType,
                $entityId,
                null,
                $this->safePayload($request),
                'api',
                $request
            );
        }

        return $response;
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        return $request->is('api/*')
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && $response->getStatusCode() < 400
            && $request->user() !== null
            && ! str_starts_with($request->path(), 'api/notifications/read')
            && ! str_contains($request->path(), '/read');
    }

    private function resolveEntity(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return [class_basename($parameter), (string) $parameter->getKey()];
            }

            if (is_string($parameter) || is_numeric($parameter)) {
                return ['RouteParameter', (string) $parameter];
            }
        }

        return ['ApiRequest', substr(hash('sha256', $request->path()), 0, 32)];
    }

    private function resolveAction(Request $request): string
    {
        $action = strtolower($request->method()).'.'.str_replace('/', '.', $request->path());

        return substr($action, 0, 100);
    }

    private function safePayload(Request $request): array
    {
        return collect($request->except([
            'password',
            'password_confirmation',
            'current_password',
            'code',
            'gateway_response',
            'file',
        ]))->take(50)->all();
    }
}

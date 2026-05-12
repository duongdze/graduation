<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePlatformFeeConfigRequest;
use App\Models\PlatformFeeConfig;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformFeeConfigController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $configs = PlatformFeeConfig::query()
            ->with('creator')
            ->orderByDesc('effective_from')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched platform fee configs successfully', $configs);
    }

    public function store(StorePlatformFeeConfigRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['effective_from'] = $data['effective_from'] ?? now();
        $data['created_by'] = $request->user()->id;

        $config = PlatformFeeConfig::create($data);

        $this->auditLogService->log(
            $request->user()->id,
            'platform_fee_config.created',
            'PlatformFeeConfig',
            (string) $config->id,
            null,
            $config->toArray(),
            'finance',
            $request
        );

        return ApiResponse::success('Platform fee config created successfully', $config->load('creator'), 201);
    }
}

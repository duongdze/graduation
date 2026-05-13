<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Moderation\UpdateModerationConfigRequest;
use App\Models\ModerationConfig;
use App\Services\ModerationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ModerationConfigController extends Controller
{
    public function __construct(private readonly ModerationService $moderationService) {}

    public function index(): JsonResponse
    {
        $configs = collect(ModerationService::defaultConfigs())
            ->map(function (array $default, string $key) {
                $config = ModerationConfig::find($key);

                return [
                    'key' => $key,
                    'value' => $config?->castedValue() ?? $default['value'],
                    'value_type' => $config?->value_type ?? $default['value_type'],
                    'description' => $config?->description ?? $default['description'],
                    'updated_by' => $config?->updated_by,
                    'updated_at' => $config?->updated_at,
                ];
            })
            ->values()
            ->concat(
                ModerationConfig::whereNotIn('key', array_keys(ModerationService::defaultConfigs()))
                    ->orderBy('key')
                    ->get()
                    ->map(fn (ModerationConfig $config) => [
                        'key' => $config->key,
                        'value' => $config->castedValue(),
                        'value_type' => $config->value_type,
                        'description' => $config->description,
                        'updated_by' => $config->updated_by,
                        'updated_at' => $config->updated_at,
                    ])
            )
            ->values();

        return ApiResponse::success('Fetched moderation configs successfully', $configs);
    }

    public function update(UpdateModerationConfigRequest $request, string $key): JsonResponse
    {
        $default = ModerationService::defaultConfigs()[$key] ?? null;
        $valueType = $request->validated('value_type')
            ?? ModerationConfig::find($key)?->value_type
            ?? ($default['value_type'] ?? 'string');

        $config = ModerationConfig::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $request->validated('value'),
                'value_type' => $valueType,
                'description' => $request->validated('description') ?? ($default['description'] ?? null),
                'updated_by' => $request->user()->id,
            ]
        );

        return ApiResponse::success('Moderation config updated successfully', [
            'key' => $config->key,
            'value' => $config->castedValue(),
            'value_type' => $config->value_type,
            'description' => $config->description,
        ]);
    }
}

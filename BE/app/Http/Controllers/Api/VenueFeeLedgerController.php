<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VenueFeeLedger;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VenueFeeLedgerController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = VenueFeeLedger::query()
            ->with(['booking', 'cluster'])
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->string('cluster_id')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched venue fee ledgers successfully', $ledgers);
    }

    public function reconcile(Request $request, VenueFeeLedger $venueFeeLedger): JsonResponse
    {
        $ledger = DB::transaction(function () use ($request, $venueFeeLedger) {
            $locked = VenueFeeLedger::whereKey($venueFeeLedger->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'reconciled') {
                $locked->update([
                    'status' => 'reconciled',
                    'reconciled_at' => now(),
                ]);
            }

            $this->auditLogService->log(
                $request->user()->id,
                'venue_fee_ledger.reconciled',
                'VenueFeeLedger',
                $locked->id,
                null,
                ['status' => 'reconciled'],
                'finance',
                $request
            );

            return $locked->fresh(['booking', 'cluster']);
        });

        return ApiResponse::success('Venue fee ledger reconciled successfully', $ledger);
    }

    public function reconcileBatch(Request $request): JsonResponse
    {
        $request->validate([
            'ledger_ids' => ['required_without:cluster_id', 'array'],
            'ledger_ids.*' => ['uuid', 'exists:venue_fee_ledgers,id'],
            'cluster_id' => ['required_without:ledger_ids', 'uuid', 'exists:venue_clusters,id'],
            'to' => ['nullable', 'date'],
        ]);

        $count = DB::transaction(function () use ($request) {
            $query = VenueFeeLedger::where('status', 'pending')->lockForUpdate();

            if ($request->filled('ledger_ids')) {
                $query->whereIn('id', $request->input('ledger_ids'));
            }

            if ($request->filled('cluster_id')) {
                $query->where('cluster_id', $request->input('cluster_id'));
            }

            if ($request->filled('to')) {
                $query->where('created_at', '<=', $request->date('to')->endOfDay());
            }

            $ledgers = $query->get();
            foreach ($ledgers as $ledger) {
                $ledger->update([
                    'status' => 'reconciled',
                    'reconciled_at' => now(),
                ]);
            }

            $this->auditLogService->log(
                $request->user()->id,
                'venue_fee_ledger.reconciled_batch',
                'VenueFeeLedger',
                $ledgers->first()?->id ?? '00000000-0000-0000-0000-000000000000',
                null,
                ['count' => $ledgers->count()],
                'finance',
                $request
            );

            return $ledgers->count();
        });

        return ApiResponse::success('Venue fee ledgers reconciled successfully', [
            'reconciled_count' => $count,
        ]);
    }
}

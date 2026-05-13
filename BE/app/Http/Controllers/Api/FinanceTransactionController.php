<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\VenueFeeLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FinanceTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'cluster_id' => ['nullable', 'uuid', 'exists:venue_clusters,id'],
            'type' => ['nullable', 'in:payment,refund,platform_fee'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $items = collect();
        $type = $request->input('type');

        if ($type === null || $type === 'payment') {
            $items = $items->merge($this->payments($request));
        }

        if ($type === null || $type === 'refund') {
            $items = $items->merge($this->refunds($request));
        }

        if ($type === null || $type === 'platform_fee') {
            $items = $items->merge($this->ledgers($request));
        }

        $items = $items->sortByDesc('occurred_at')->values();
        $perPage = $request->integer('per_page', 15);
        $page = max($request->integer('page', 1), 1);
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'message' => 'Fetched finance transactions successfully',
            'data' => $slice,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $items->count(),
                'last_page' => (new LengthAwarePaginator($items, $items->count(), $perPage, $page))->lastPage(),
            ],
        ]);
    }

    private function payments(Request $request)
    {
        return Payment::query()
            ->with('booking:id,booking_code,cluster_id')
            ->when($request->filled('cluster_id'), fn ($query) => $query->whereHas('booking', fn ($booking) => $booking->where('cluster_id', $request->input('cluster_id'))))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'type' => 'payment',
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'booking_id' => $payment->booking_id,
                'booking_code' => $payment->booking?->booking_code,
                'cluster_id' => $payment->booking?->cluster_id,
                'occurred_at' => $payment->paid_at?->toIso8601String() ?? $payment->created_at?->toIso8601String(),
            ]);
    }

    private function refunds(Request $request)
    {
        return Refund::query()
            ->with('booking:id,booking_code,cluster_id')
            ->when($request->filled('cluster_id'), fn ($query) => $query->whereHas('booking', fn ($booking) => $booking->where('cluster_id', $request->input('cluster_id'))))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->get()
            ->map(fn (Refund $refund) => [
                'id' => $refund->id,
                'type' => 'refund',
                'amount' => -1 * (float) $refund->amount,
                'status' => $refund->status,
                'booking_id' => $refund->booking_id,
                'booking_code' => $refund->booking?->booking_code,
                'cluster_id' => $refund->booking?->cluster_id,
                'occurred_at' => $refund->processed_at?->toIso8601String() ?? $refund->created_at?->toIso8601String(),
            ]);
    }

    private function ledgers(Request $request)
    {
        return VenueFeeLedger::query()
            ->with('booking:id,booking_code')
            ->when($request->filled('cluster_id'), fn ($query) => $query->where('cluster_id', $request->input('cluster_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->get()
            ->map(fn (VenueFeeLedger $ledger) => [
                'id' => $ledger->id,
                'type' => 'platform_fee',
                'amount' => (float) $ledger->fee_amount,
                'status' => $ledger->status,
                'booking_id' => $ledger->booking_id,
                'booking_code' => $ledger->booking?->booking_code,
                'cluster_id' => $ledger->cluster_id,
                'occurred_at' => $ledger->reconciled_at?->toIso8601String() ?? $ledger->created_at?->toIso8601String(),
            ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Payment;
use App\Models\VenueCluster;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AuthorizesVenueScope;

    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $payments = Payment::query()
            ->with('booking')
            ->when(! $this->isPlatformAdmin($user), function ($query) use ($user) {
                $clusterIds = $this->payableClusterIdsForUser($user);
                $query->whereHas('booking', function ($bookingQuery) use ($user, $clusterIds) {
                    $bookingQuery->where(function ($scopeQuery) use ($user, $clusterIds) {
                        $scopeQuery->where('customer_id', $user->id)
                            ->orWhere('created_by', $user->id);

                        if ($clusterIds !== []) {
                            $scopeQuery->orWhereIn('cluster_id', $clusterIds);
                        }
                    });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('booking_id'), fn ($query) => $query->where('booking_id', $request->string('booking_id')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $payments->getCollection()->each->makeHidden('gateway_response');

        return ApiResponse::paginated('Fetched payments successfully', $payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createPayment($request->validated(), $request->user())->makeHidden('gateway_response');

        return ApiResponse::success('Payment created successfully', [
            'payment' => $payment,
            'checkout' => $this->paymentService->checkoutPayload($payment),
        ], 201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->assertCanAccessPayment($request, $payment);

        return ApiResponse::success('Fetched payment successfully', $payment->load('booking')->makeHidden('gateway_response'));
    }

    public function checkout(Request $request, Payment $payment, PaymentGatewayService $paymentGatewayService): JsonResponse
    {
        $paymentGatewayService->assertValidToken($payment, $request->query('token'));

        return ApiResponse::success('Fetched payment checkout successfully', [
            'payment' => $payment->load('booking')->makeHidden('gateway_response'),
            'checkout' => $paymentGatewayService->checkoutPayload($payment),
        ]);
    }

    public function completeCheckout(Request $request, Payment $payment, PaymentGatewayService $paymentGatewayService): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'status' => ['required', 'in:success,failed'],
        ]);

        $paymentGatewayService->assertValidToken($payment, $request->input('token'));

        $payment = $this->paymentService->processGatewayResult(
            $payment,
            'LOCAL-'.$payment->id,
            $request->input('status'),
            [
                'provider' => 'local_mvp',
                'status' => $request->input('status'),
                'completed_at' => now()->toIso8601String(),
            ]
        )->makeHidden('gateway_response');

        return ApiResponse::success('Checkout completed successfully', $payment);
    }

    public function markPaid(Request $request, Payment $payment): JsonResponse
    {
        $this->assertCanOperatePayment($request, $payment);

        $payment = $this->paymentService->markPaid($payment)->makeHidden('gateway_response');

        return ApiResponse::success('Payment marked as paid successfully', $payment);
    }

    public function markFailed(Request $request, Payment $payment): JsonResponse
    {
        $this->assertCanOperatePayment($request, $payment);

        $payment = $this->paymentService->markFailed($payment)->makeHidden('gateway_response');

        return ApiResponse::success('Payment marked as failed successfully', $payment);
    }

    private function assertCanAccessPayment(Request $request, Payment $payment): void
    {
        $payment->loadMissing('booking');
        $user = $request->user();

        abort_unless(
            $this->isPlatformAdmin($user)
            || $payment->booking?->customer_id === $user->id
            || $payment->booking?->created_by === $user->id
            || in_array($payment->booking?->cluster_id, $this->payableClusterIdsForUser($user), true),
            403,
            'You cannot access this payment.'
        );
    }

    private function assertCanOperatePayment(Request $request, Payment $payment): void
    {
        $payment->loadMissing('booking');
        $user = $request->user();

        abort_unless(
            $this->isPlatformAdmin($user)
            || $payment->booking?->created_by === $user->id
            || in_array($payment->booking?->cluster_id, $this->payableClusterIdsForUser($user), true),
            403,
            'You cannot operate this payment.'
        );
    }

    private function payableClusterIdsForUser($user): array
    {
        return array_values(array_unique(array_merge(
            VenueCluster::where('owner_id', $user->id)->pluck('id')->all(),
            $this->venueScopeIds($user)
        )));
    }
}

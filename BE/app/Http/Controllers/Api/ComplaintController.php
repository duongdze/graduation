<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\ResolveComplaintRequest;
use App\Http\Requests\Feedback\StoreComplaintRequest;
use App\Models\Booking;
use App\Models\Complaint;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComplaintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::query()
            ->with(['booking', 'customer', 'resolver'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched complaints successfully', $complaints);
    }

    public function show(Complaint $complaint): JsonResponse
    {
        return ApiResponse::success('Fetched complaint successfully', $complaint->load(['booking', 'customer', 'resolver', 'media']));
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $booking = Booking::findOrFail($request->validated('booking_id'));
        if ($booking->customer_id !== $request->user()->id && ! $request->user()->hasPermission('complaint.resolve')) {
            throw ValidationException::withMessages([
                'booking_id' => ['You can only create a complaint for your own booking.'],
            ]);
        }

        $complaint = Complaint::create([
            'booking_id' => $booking->id,
            'customer_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'status' => 'open',
        ]);

        return ApiResponse::success('Complaint created successfully', $complaint->load(['booking', 'customer']), 201);
    }

    public function resolve(ResolveComplaintRequest $request, Complaint $complaint): JsonResponse
    {
        $complaint->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()->id,
            'resolve_note' => $request->validated('resolve_note'),
            'resolved_at' => now(),
        ]);

        return ApiResponse::success('Complaint resolved successfully', $complaint->fresh(['booking', 'customer', 'resolver']));
    }
}

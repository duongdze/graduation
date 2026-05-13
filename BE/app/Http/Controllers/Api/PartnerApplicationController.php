<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerApplication;
use App\Models\Role;
use App\Services\EmailNotificationService;
use App\Services\MediaService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EmailNotificationService $emailService
    ) {}

    /**
     * List all partner applications (admin) with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $applications = PartnerApplication::query()
            ->with(['user', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search')->toString();
                $q->where(function ($sq) use ($search) {
                    $sq->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('full_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('submitted_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched partner applications successfully', $applications);
    }

    /**
     * Current user's own applications.
     */
    public function my(Request $request): JsonResponse
    {
        $applications = PartnerApplication::query()
            ->where('user_id', $request->user()->id)
            ->with('reviewer')
            ->orderByDesc('submitted_at')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched my applications successfully', $applications);
    }

    /**
     * Show single application detail.
     */
    public function show(Request $request, PartnerApplication $partnerApplication): JsonResponse
    {
        // Users can only see their own; admins can see all (handled by permission middleware)
        if (! $request->user()->hasPermission('partner_application.view_all') && $partnerApplication->user_id !== $request->user()->id) {
            return ApiResponse::error('Unauthorized.', [], 403);
        }

        return ApiResponse::success('Fetched application successfully', $partnerApplication->load(['user', 'reviewer', 'media']));
    }

    /**
     * Submit a new partner application.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'tax_code' => ['nullable', 'string', 'max:50'],
        ]);

        // Check if user already has a pending application
        $existing = PartnerApplication::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return ApiResponse::error('You already have a pending application.', [], 422);
        }

        $application = PartnerApplication::create([
            'user_id' => $request->user()->id,
            'business_name' => $request->input('business_name'),
            'tax_code' => $request->input('tax_code'),
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return ApiResponse::success('Application submitted successfully', $application->load('user'), 201);
    }

    public function uploadDocument(Request $request, PartnerApplication $partnerApplication, MediaService $mediaService): JsonResponse
    {
        if (! $request->user()->hasPermission('partner_application.view_all') && $partnerApplication->user_id !== $request->user()->id) {
            return ApiResponse::error('Unauthorized.', [], 403);
        }

        if ($partnerApplication->status !== 'pending') {
            return ApiResponse::error('Documents can only be uploaded while the application is pending.', [], 422);
        }

        $request->validate([
            'collection' => ['required', 'string', 'in:business_license,id_card_front,id_card_back,venue_photo,other_document'],
            'file' => ['required', 'image', 'max:5120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $media = $mediaService->store([
            'mediable_type' => 'partner_application',
            'mediable_id' => $partnerApplication->id,
            'collection' => $request->input('collection'),
            'sort_order' => $request->integer('sort_order', 0),
        ], $request->file('file'));

        return ApiResponse::success('Partner application document uploaded successfully', [
            'application' => $partnerApplication->fresh(['user', 'reviewer', 'media']),
            'media' => $media,
        ], 201);
    }

    /**
     * Approve a partner application (admin).
     */
    public function approve(Request $request, PartnerApplication $partnerApplication): JsonResponse
    {
        if ($partnerApplication->status !== 'pending') {
            return ApiResponse::error('This application has already been reviewed.', [], 422);
        }

        DB::transaction(function () use ($request, $partnerApplication) {
            $partnerApplication->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'reject_reason' => null,
            ]);

            // Assign venue_owner role to the applicant
            $venueOwnerRole = Role::where('name', 'venue_owner')->first();
            if ($venueOwnerRole) {
                $partnerApplication->user->userRoles()->firstOrCreate([
                    'role_id' => $venueOwnerRole->id,
                    'scope_type' => 'system',
                    'scope_id' => null,
                ], [
                    'granted_by' => $request->user()->id,
                ]);
            }

            // Send notification to applicant
            $this->notificationService->createForUser(
                $partnerApplication->user_id,
                'partner_approved',
                'Đơn đăng ký đối tác đã được duyệt',
                'Chúc mừng! Bạn đã trở thành chủ sân. Hãy bắt đầu tạo cụm sân.',
                'PartnerApplication',
                $partnerApplication->id
            );

            $this->emailService->sendPartnerApplicationDecision($partnerApplication->fresh('user'));
        });

        return ApiResponse::success('Application approved successfully', $partnerApplication->fresh(['user', 'reviewer']));
    }

    /**
     * Reject a partner application (admin).
     */
    public function reject(Request $request, PartnerApplication $partnerApplication): JsonResponse
    {
        if ($partnerApplication->status !== 'pending') {
            return ApiResponse::error('This application has already been reviewed.', [], 422);
        }

        $request->validate([
            'reject_reason' => ['required', 'string'],
        ]);

        $partnerApplication->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reject_reason' => $request->input('reject_reason'),
        ]);

        $this->notificationService->createForUser(
            $partnerApplication->user_id,
            'partner_rejected',
            'Đơn đăng ký đối tác bị từ chối',
            'Lý do: ' . $request->input('reject_reason'),
            'PartnerApplication',
            $partnerApplication->id
        );

        $this->emailService->sendPartnerApplicationDecision($partnerApplication->fresh('user'));

        return ApiResponse::success('Application rejected successfully', $partnerApplication->fresh(['user', 'reviewer']));
    }
}

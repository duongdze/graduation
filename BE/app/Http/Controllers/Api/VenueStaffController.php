<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\VenueCluster;
use App\Support\ApiResponse;
use App\Traits\AuthorizesVenueScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class VenueStaffController extends Controller
{
    use AuthorizesVenueScope;

    public function index(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $this->assertCanManageCluster($request, $venueCluster);

        $staff = User::query()
            ->whereHas('userRoles', function ($query) use ($venueCluster) {
                $query->where('scope_type', 'venue')
                    ->where('scope_id', $venueCluster->id)
                    ->whereHas('role', fn ($roleQuery) => $roleQuery->where('name', 'venue_staff'));
            })
            ->with('roles')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated('Fetched venue staff successfully', $staff);
    }

    public function store(Request $request, VenueCluster $venueCluster): JsonResponse
    {
        $this->assertCanManageCluster($request, $venueCluster);

        $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'full_name' => ['required_without:user_id', 'string', 'max:255'],
            'email' => ['required_without:user_id', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:15', Rule::unique('users', 'phone')],
            'password' => ['required_without:user_id', 'nullable', Password::min(8)],
        ]);

        $role = Role::where('name', 'venue_staff')->firstOrFail();

        $user = DB::transaction(function () use ($request, $venueCluster, $role) {
            $user = $request->filled('user_id')
                ? User::findOrFail($request->input('user_id'))
                : User::create([
                    'full_name' => $request->input('full_name'),
                    'email' => strtolower($request->input('email')),
                    'phone' => $request->input('phone'),
                    'password' => Hash::make($request->input('password')),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

            $user->userRoles()->firstOrCreate([
                'role_id' => $role->id,
                'scope_type' => 'venue',
                'scope_id' => $venueCluster->id,
            ], [
                'granted_by' => $request->user()->id,
            ]);

            return $user;
        });

        return ApiResponse::success('Venue staff saved successfully', $user->fresh('roles'), 201);
    }

    public function destroy(Request $request, VenueCluster $venueCluster, User $user): JsonResponse
    {
        $this->assertCanManageCluster($request, $venueCluster);
        $role = Role::where('name', 'venue_staff')->firstOrFail();

        $deleted = $user->userRoles()
            ->where('role_id', $role->id)
            ->where('scope_type', 'venue')
            ->where('scope_id', $venueCluster->id)
            ->delete();

        return ApiResponse::success('Venue staff removed successfully', [
            'removed' => $deleted > 0,
        ]);
    }
}

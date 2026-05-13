<?php

namespace App\Traits;

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AuthorizesVenueScope
{
    protected function isPlatformAdmin(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('system_staff');
    }

    protected function venueScopeIds(User $user): array
    {
        return $user->userRoles()
            ->where('scope_type', 'venue')
            ->whereNotNull('scope_id')
            ->pluck('scope_id')
            ->all();
    }

    protected function scopeVenueClustersForUser(Builder $query, User $user): Builder
    {
        if ($this->isPlatformAdmin($user)) {
            return $query;
        }

        $scopeIds = $this->venueScopeIds($user);

        if ($user->hasRole('venue_owner') || $scopeIds !== []) {
            return $query->where(function (Builder $scopeQuery) use ($user, $scopeIds) {
                $scopeQuery->where('owner_id', $user->id);

                if ($scopeIds !== []) {
                    $scopeQuery->orWhereIn('id', $scopeIds);
                }
            });
        }

        return $query;
    }

    protected function assertCanManageCluster(Request $request, VenueCluster $cluster): void
    {
        $user = $request->user();

        abort_unless(
            $this->isPlatformAdmin($user)
            || $cluster->owner_id === $user->id
            || in_array($cluster->id, $this->venueScopeIds($user), true),
            403,
            'You cannot manage this venue.'
        );
    }

    protected function assertCanManageCourt(Request $request, VenueCourt $court): void
    {
        $court->loadMissing('cluster');
        $this->assertCanManageCluster($request, $court->cluster);
    }
}

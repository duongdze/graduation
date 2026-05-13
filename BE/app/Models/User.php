<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'email_verified_at',
        'phone_verified_at',
        'password',
        'avatar_url',
        'status',
        'lock_reason',
        'locked_at',
        'locked_by',
        'bio',
        'address',
        'ward',
        'district',
        'city',
        'preferred_sports',
        'preferred_position',
        'player_rating_avg',
        'player_rating_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'locked_at' => 'datetime',
            'password' => 'hashed',
            'preferred_sports' => 'array',
            'player_rating_avg' => 'decimal:2',
        ];
    }

    // ── RBAC ───────────────────────────────────────────────

    /** Roles assigned to this user (with scope support) */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** Roles via belongsToMany (system-level shortcut) */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('scope_type', 'scope_id', 'granted_by', 'created_at');
    }

    /** Permissions revoked from this user */
    public function permissionRevokes(): HasMany
    {
        return $this->hasMany(UserPermissionRevoke::class);
    }

    /** Check whether the user has a role in an optional scope. */
    public function hasRole(string $role, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        return $this->userRoles()
            ->whereHas('role', fn ($query) => $query->where('name', $role))
            ->when($scopeType !== null, function ($query) use ($scopeType, $scopeId) {
                $query->where('scope_type', $scopeType);

                if ($scopeId === null) {
                    $query->whereNull('scope_id');
                } else {
                    $query->where('scope_id', $scopeId);
                }
            })
            ->exists();
    }

    /** Resolve role permissions minus user-level revokes. */
    public function getAllPermissions(?string $scopeType = null, ?string $scopeId = null): Collection
    {
        if ($this->hasRole('super_admin')) {
            return Permission::query()->orderBy('code')->get();
        }

        $permissions = Permission::query()
            ->select('permissions.*')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $this->getKey())
            ->when($scopeType !== null, function ($query) use ($scopeType, $scopeId) {
                $query->where(function ($scopeQuery) use ($scopeType, $scopeId) {
                    $scopeQuery->where('user_roles.scope_type', 'system')
                        ->orWhere(function ($targetScopeQuery) use ($scopeType, $scopeId) {
                            $targetScopeQuery->where('user_roles.scope_type', $scopeType);

                            if ($scopeId === null) {
                                $targetScopeQuery->whereNull('user_roles.scope_id');
                            } else {
                                $targetScopeQuery->where('user_roles.scope_id', $scopeId);
                            }
                        });
                });
            })
            ->distinct()
            ->orderBy('permissions.code')
            ->get();

        $revokedPermissionIds = $this->permissionRevokes()
            ->when($scopeType !== null, function ($query) use ($scopeType, $scopeId) {
                $query->where(function ($scopeQuery) use ($scopeType, $scopeId) {
                    $scopeQuery->where('scope_type', 'system')
                        ->orWhere(function ($targetScopeQuery) use ($scopeType, $scopeId) {
                            $targetScopeQuery->where('scope_type', $scopeType);

                            if ($scopeId === null) {
                                $targetScopeQuery->whereNull('scope_id');
                            } else {
                                $targetScopeQuery->where('scope_id', $scopeId);
                            }
                        });
                });
            })
            ->pluck('permission_id')
            ->all();

        return $permissions
            ->reject(fn (Permission $permission) => in_array($permission->id, $revokedPermissionIds, true))
            ->values();
    }

    /** Check permission by code; super_admin always passes. */
    public function hasPermission(string $permission, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->getAllPermissions($scopeType, $scopeId)
            ->contains(fn (Permission $resolvedPermission) => $resolvedPermission->code === $permission);
    }

    // ── Venue ──────────────────────────────────────────────

    /** Venue clusters owned by this user (as partner) */
    public function ownedClusters(): HasMany
    {
        return $this->hasMany(VenueCluster::class, 'owner_id');
    }

    /** Partner applications submitted */
    public function partnerApplications(): HasMany
    {
        return $this->hasMany(PartnerApplication::class);
    }

    // ── Booking ────────────────────────────────────────────

    /** Bookings made by this user as customer */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /** Bookings created by this user (staff/owner counter booking) */
    public function createdBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'created_by');
    }

    // ── Feedback ───────────────────────────────────────────

    /** Reviews written by this user */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    /** Player ratings given by this user */
    public function givenPlayerRatings(): HasMany
    {
        return $this->hasMany(PlayerRating::class, 'rater_id');
    }

    /** Player ratings received by this user */
    public function receivedPlayerRatings(): HasMany
    {
        return $this->hasMany(PlayerRating::class, 'rated_user_id');
    }

    /** Reports filed by this user */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    /** Complaints filed by this user */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'customer_id');
    }

    // ── Recruitment ────────────────────────────────────────

    /** Player posts authored by this user */
    public function playerPosts(): HasMany
    {
        return $this->hasMany(PlayerPost::class, 'author_id');
    }

    /** Post participations by this user */
    public function postParticipations(): HasMany
    {
        return $this->hasMany(PlayerPostParticipant::class);
    }

    // ── Chat ───────────────────────────────────────────────

    /** Conversations this user participates in */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->withPivot('last_read_at', 'joined_at');
    }

    /** Messages sent by this user */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // ── System ─────────────────────────────────────────────

    /** Notifications for this user */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Audit log entries by this user */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // ── Media ──────────────────────────────────────────────

    /** Polymorphic media (avatar, etc.) */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Reports targeting this user (polymorphic) */
    public function reportsReceived(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    /** Community posts authored by this user */
    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class, 'author_id');
    }

    /** Venue favorites saved by this user */
    public function favoriteVenues(): HasMany
    {
        return $this->hasMany(FavoriteVenue::class);
    }

    /** Verification codes */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }
}

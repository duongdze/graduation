<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UpdatePasswordRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return ApiResponse::success('Profile updated successfully', $request->user()->fresh('roles'));
    }

    public function updateAvatar(Request $request, MediaService $mediaService): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $media = $mediaService->store([
            'mediable_type' => 'user',
            'mediable_id' => $request->user()->id,
            'collection' => 'avatar',
            'sort_order' => 0,
        ], $request->file('file'));

        $request->user()->update([
            'avatar_url' => Storage::disk('public')->url($media->file_path),
        ]);

        return ApiResponse::success('Avatar updated successfully', [
            'user' => $request->user()->fresh('roles'),
            'media' => $media,
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        if (! Hash::check($request->validated('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        $request->user()->tokens()->delete();

        return ApiResponse::success('Password updated successfully');
    }
}

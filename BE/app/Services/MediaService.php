<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\Media;
use App\Models\PartnerApplication;
use App\Models\PlayerPost;
use App\Models\Refund;
use App\Models\Review;
use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaService
{
    private const MODEL_ALIASES = [
        'user' => User::class,
        'partner_application' => PartnerApplication::class,
        'venue_cluster' => VenueCluster::class,
        'venue_court' => VenueCourt::class,
        'player_post' => PlayerPost::class,
        'refund' => Refund::class,
        'review' => Review::class,
        'complaint' => Complaint::class,
    ];

    public function store(array $data, UploadedFile $file): Media
    {
        $modelClass = self::MODEL_ALIASES[$data['mediable_type']] ?? $data['mediable_type'];

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw ValidationException::withMessages([
                'mediable_type' => ['Unsupported mediable_type.'],
            ]);
        }

        $model = $modelClass::query()->find($data['mediable_id']);
        if (! $model) {
            throw ValidationException::withMessages([
                'mediable_id' => ['Mediable record was not found.'],
            ]);
        }

        $path = $file->store('media/'.($data['collection'] ?? 'default'), 'public');

        try {
            return DB::transaction(fn () => Media::create([
                'mediable_type' => $modelClass,
                'mediable_id' => $model->getKey(),
                'collection' => $data['collection'] ?? 'default',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
                'sort_order' => $data['sort_order'] ?? 0,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    public function delete(Media $media): void
    {
        Storage::disk('public')->delete($media->file_path);
        $media->delete();
    }
}

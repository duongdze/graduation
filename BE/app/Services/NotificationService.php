<?php

namespace App\Services;

use App\Models\Notification;

class NotificationService
{
    /**
     * Create a notification for a single user.
     */
    public function createForUser(
        string $userId,
        string $type,
        string $title,
        ?string $body = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $data = []
    ): Notification {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'data' => ! empty($data) ? $data : null,
        ]);
    }

    /**
     * Create a notification for multiple users.
     */
    public function createForUsers(
        array $userIds,
        string $type,
        string $title,
        ?string $body = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $data = []
    ): int {
        $count = 0;
        foreach (array_unique($userIds) as $userId) {
            $this->createForUser($userId, $type, $title, $body, $referenceType, $referenceId, $data);
            $count++;
        }

        return $count;
    }
}

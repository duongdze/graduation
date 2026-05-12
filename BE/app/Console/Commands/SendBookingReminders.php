<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders {--minutes=120 : Send reminders for bookings starting within N minutes}';

    protected $description = 'Create reminder notifications for upcoming paid bookings';

    public function handle(NotificationService $notificationService): int
    {
        $minutes = (int) $this->option('minutes');
        $from = now();
        $to = now()->addMinutes($minutes);

        $bookings = Booking::query()
            ->with('cluster')
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereRaw('TIMESTAMP(booking_date, start_time) BETWEEN ? AND ?', [$from, $to])
            ->get();

        $created = 0;
        foreach ($bookings as $booking) {
            if ($booking->customer_id && ! $this->alreadyReminded($booking->customer_id, $booking->id)) {
                $notificationService->createForUser(
                    $booking->customer_id,
                    'booking_reminder',
                    'Upcoming booking reminder',
                    "Booking {$booking->booking_code} starts at {$booking->start_time}.",
                    'Booking',
                    $booking->id
                );
                $created++;
            }

            if ($booking->cluster?->owner_id && ! $this->alreadyReminded($booking->cluster->owner_id, $booking->id)) {
                $notificationService->createForUser(
                    $booking->cluster->owner_id,
                    'booking_reminder',
                    'Upcoming venue booking',
                    "Booking {$booking->booking_code} starts at {$booking->start_time}.",
                    'Booking',
                    $booking->id
                );
                $created++;
            }
        }

        $this->info("Created {$created} booking reminder notifications.");

        return self::SUCCESS;
    }

    private function alreadyReminded(string $userId, string $bookingId): bool
    {
        return Notification::where('user_id', $userId)
            ->where('type', 'booking_reminder')
            ->where('reference_type', 'Booking')
            ->where('reference_id', $bookingId)
            ->exists();
    }
}

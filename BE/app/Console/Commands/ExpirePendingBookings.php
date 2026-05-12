<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SlotLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending {--minutes=15 : Minutes after which pending bookings expire}';

    protected $description = 'Expire pending_payment bookings older than N minutes and clean up expired slot locks';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        // 1. Expire pending bookings
        $expiredBookings = DB::transaction(function () use ($minutes) {
            $cutoff = now()->subMinutes($minutes);

            $bookings = Booking::where('status', 'pending_payment')
                ->where('created_at', '<', $cutoff)
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $booking->update([
                    'status' => 'expired',
                    'cancel_reason' => 'auto_expired',
                ]);

                // Remove associated slot locks
                SlotLock::where('booking_id', $booking->id)->delete();

                $count++;
            }

            return $count;
        });

        $this->info("Expired {$expiredBookings} pending bookings.");

        // 2. Clean up expired auto slot locks
        $cleanedLocks = SlotLock::where('lock_type', 'auto')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Cleaned up {$cleanedLocks} expired auto slot locks.");

        return self::SUCCESS;
    }
}

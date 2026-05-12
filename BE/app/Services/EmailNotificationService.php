<?php

namespace App\Services;

use App\Models\PartnerApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    public function sendVerificationCode(string $email, string $code, string $purpose): bool
    {
        return $this->sendPlain(
            $email,
            $this->subjectForPurpose($purpose),
            "Your verification code is {$code}. This code expires in 5 minutes."
        );
    }

    public function sendPartnerApplicationDecision(PartnerApplication $application): bool
    {
        $application->loadMissing('user');

        $body = $application->status === 'approved'
            ? "Your partner application for {$application->business_name} has been approved."
            : "Your partner application for {$application->business_name} was rejected. Reason: {$application->reject_reason}";

        return $this->sendPlain(
            $application->user->email,
            'Partner application update',
            $body
        );
    }

    private function sendPlain(string $email, string $subject, string $body): bool
    {
        try {
            Mail::raw($body, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Unable to send email notification', [
                'email' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function subjectForPurpose(string $purpose): string
    {
        return match ($purpose) {
            'register' => 'Verify your account',
            'reset_password' => 'Reset your password',
            'phone_verify' => 'Verify your phone number',
            default => 'Verification code',
        };
    }
}

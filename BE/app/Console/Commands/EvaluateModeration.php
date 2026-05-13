<?php

namespace App\Console\Commands;

use App\Services\ModerationService;
use Illuminate\Console\Command;

class EvaluateModeration extends Command
{
    protected $signature = 'moderation:evaluate';

    protected $description = 'Evaluate report and bad-rating moderation thresholds and apply warnings or locks';

    public function handle(ModerationService $moderationService): int
    {
        $moderationService->seedDefaultConfigs();
        $summary = $moderationService->evaluate();

        foreach ($summary as $key => $count) {
            $this->line("{$key}: {$count}");
        }

        return self::SUCCESS;
    }
}

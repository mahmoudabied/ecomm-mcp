<?php

namespace Amacraft\MarketingAutomation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Amacraft\MarketingAutomation\Enums\ConsentStatus;

class AuditConsentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:audit-consent {--days=30 : How far back to audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find marketing messages sent without a granted consent at send time (SC-004)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $violations = $this->violations(now()->subDays((int) $this->option('days')));

        if ($violations === []) {
            $this->info('No violations.');

            return self::SUCCESS;
        }

        $this->table(
            ['Log ID', 'Contact ID', 'Channel', 'Sent At', 'Reason'],
            $violations
        );

        $this->error(count($violations).' consent violation(s) found.');

        return self::FAILURE;
    }

    /**
     * The marketing messages whose consent was not granted at send time.
     *
     * @return array<int, array<int, string|int>>
     */
    protected function violations($cutoff): array
    {
        $logs = DB::table('automation_message_logs')
            ->where('is_marketing', true)
            ->where('is_test', false)
            ->where('created_at', '>=', $cutoff)
            ->whereIn('status', ['queued', 'sent', 'delivered', 'read'])
            ->orderBy('id')
            ->get(['id', 'contact_id', 'message_channel', 'sent_at', 'created_at']);

        $violations = [];

        foreach ($logs as $log) {
            $reason = $this->violationReason($log);

            if ($reason !== null) {
                $violations[] = [
                    $log->id,
                    $log->contact_id,
                    $log->message_channel,
                    (string) $log->sent_at,
                    $reason,
                ];
            }
        }

        return $violations;
    }

    /**
     * Why the send violated consent, or null when it was compliant.
     */
    protected function violationReason(object $log): ?string
    {
        $latest = DB::table('automation_consent_events')
            ->where('contact_id', $log->contact_id)
            ->where('channel', $log->message_channel)
            ->where('created_at', '<=', $log->sent_at ?? $log->created_at ?? now())
            ->orderByDesc('created_at')
            ->first(['status']);

        if ($latest === null) {
            return 'no_granted_consent';
        }

        return $latest->status === ConsentStatus::Revoked->value
            ? 'consent_revoked_before_send'
            : null;
    }
}

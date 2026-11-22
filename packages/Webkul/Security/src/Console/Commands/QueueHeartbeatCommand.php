<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use Throwable;
use Webkul\Security\Support\QueueHeartbeat;
use Webkul\Security\Support\ReadinessProbe;

class QueueHeartbeatCommand extends Command
{
    protected $signature = 'luminara:queue-heartbeat
                            {--check : Exit non-zero when the heartbeat is missing or stale}';

    protected $description = 'Record the scheduler heartbeat used by the readiness endpoint.';

    public function handle(QueueHeartbeat $heartbeat, ReadinessProbe $probe): int
    {
        if ((bool) $this->option('check')) {
            $check = $probe->checkQueue();
            $ok = in_array($check['status'] ?? '', ['ok', 'starting'], true);

            if (! $ok) {
                $this->error('Queue heartbeat is missing or stale.');
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        }

        try {
            $stamp = $heartbeat->record();
        } catch (Throwable) {
            $this->error('Unable to record the queue heartbeat.');

            return self::FAILURE;
        }

        $this->info('Queue heartbeat recorded at '.$stamp.'.');

        return self::SUCCESS;
    }
}

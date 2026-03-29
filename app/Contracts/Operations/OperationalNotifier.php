<?php

namespace App\Contracts\Operations;

/**
 * Operasyonel olaylar için bildirim kanalı (log, Slack, SMS vb. sürümleri bağlanabilir).
 */
interface OperationalNotifier
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function notify(string $event, array $context = []): void;
}

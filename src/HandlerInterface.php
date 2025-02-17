<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;

interface HandlerInterface
{
    /**
     * Send the given queue via HTTP, save into database or export to some files,...
     *
     * @param  array<int, Transaction|Segment>  $queue
     *
     * @throws MonitorException
     */
    public function handle(array $queue): void;
}

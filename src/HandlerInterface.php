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
     * @param  Transaction[]|Segment[]  $queue
     * @return mixed
     *
     * @throws MonitorException
     */
    public function handle(array $queue);
}

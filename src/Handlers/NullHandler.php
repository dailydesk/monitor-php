<?php

namespace DailyDesk\Monitor\Handlers;

use DailyDesk\Monitor\HandlerInterface;

class NullHandler implements HandlerInterface
{
    public function handle(array $queue)
    {
        // Do nothing.
    }
}

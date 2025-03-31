<?php

namespace DailyDesk\Monitor\Handlers;

use DailyDesk\Monitor\HandlerInterface;

readonly class NullHandler implements HandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handle(array $queue): void
    {
        // Do nothing.
    }
}

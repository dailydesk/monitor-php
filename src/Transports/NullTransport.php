<?php

namespace DailyDesk\Monitor\Transports;

use Inspector\Models\Arrayable;
use Inspector\Transports\TransportInterface;

class NullTransport implements TransportInterface
{
    protected array $queue = [];

    public function addEntry(Arrayable $entry)
    {
        $this->queue[] = $entry;

        return $this;
    }

    public function flush()
    {
        $this->resetQueue();

        return $this;
    }

    public function resetQueue()
    {
        $this->queue = [];

        return $this;
    }

    public function getQueue()
    {
        return $this->queue;
    }
}

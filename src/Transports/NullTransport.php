<?php

namespace DailyDesk\Monitor\Transports;

use Inspector\Models\Arrayable;
use Inspector\Transports\TransportInterface;

class NullTransport implements TransportInterface
{
    public function addEntry(Arrayable $entry)
    {
        //
    }

    public function flush()
    {
        //
    }

    public function resetQueue()
    {
        //
    }
}

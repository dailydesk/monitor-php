<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Transports\SyncTransport;
use Inspector\Inspector;

class Monitor extends Inspector
{
    public function __construct(Configuration $configuration)
    {
        parent::__construct($configuration);

        $this->setTransport(
            new SyncTransport($configuration)
        );
    }
}

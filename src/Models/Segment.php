<?php

namespace DailyDesk\Monitor\Models;

class Segment extends \Inspector\Models\Segment
{
    public function isStarted(): bool
    {
        return isset($this->timestamp);
    }

    public function isEnded(): bool
    {
        return isset($this->duration) && $this->duration > 0;
    }
}

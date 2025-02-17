<?php

namespace DailyDesk\Monitor\Models;

use Inspector\Models\Segment as BaseSegment;

/**
 * @property string $model
 * @property string $type
 * @property string $label
 * @property float $start
 * @property float $timestamp
 * @property float $duration
 * @property array<string, mixed> $context
 * @property array<string, mixed> $transaction
 */
class Segment extends BaseSegment
{
    public const TYPE_ERROR = 'error';

    public function isStarted(): bool
    {
        return isset($this->timestamp);
    }

    public function isEnded(): bool
    {
        return isset($this->duration) && $this->duration > 0;
    }
}

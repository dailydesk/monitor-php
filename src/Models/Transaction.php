<?php

namespace Monitor\Models;

use Inspector\Models\Transaction as BaseTransaction;

class Transaction extends BaseTransaction
{
    /**
     * Create a new Transaction instance.
     */
    public function __construct(string $name, string $type)
    {
        parent::__construct($name);
        $this->type = $type;
    }

    public function end(int|float|null $duration = null): self
    {
        parent::end($duration);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'type' => $this->type,
            'name' => $this->name,
            'start' => $this->timestamp,
            'duration' => $this->duration,
            'context' => $this->context,
            'memory_peak' => $this->memory_peak,
        ];
    }
}

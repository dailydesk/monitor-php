<?php

namespace DailyDesk\Monitor\Models;

class Transaction extends \Inspector\Models\Transaction
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_ERROR = 'error';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->setResult(self::RESULT_SUCCESS);
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isStarted(): bool
    {
        return isset($this->timestamp);
    }
}

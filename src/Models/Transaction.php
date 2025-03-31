<?php

namespace DailyDesk\Monitor\Models;

use Inspector\Models\Partials\Http;
use Inspector\Models\Transaction as BaseTransaction;

/**
 * @property string $model
 * @property string $hash
 * @property string $type
 * @property string $name
 * @property string $result
 * @property float $timestamp
 * @property float $duration
 * @property float $memory_peak
 * @property array<string, mixed> $context
 * @property Host $host
 * @property ?Http $http
 * @property ?User $user
 */
class Transaction extends BaseTransaction
{
    public const TYPE_COMMAND = 'command';
    public const TYPE_REQUEST = 'request';
    public const TYPE_UNEXPECTED = 'unexpected';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILED = 'failed';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->setResult(self::RESULT_SUCCESS);
    }

    public function markAsCommand(): self
    {
        $this->setType(self::TYPE_COMMAND);

        return $this;
    }

    public function markAsRequest(): self
    {
        $this->setType(self::TYPE_REQUEST);
        $this->http = new Http();

        return $this;
    }

    public function markAsSuccess(): self
    {
        $this->result = self::RESULT_SUCCESS;

        return $this;
    }

    public function markAsFailed(): self
    {
        $this->result = self::RESULT_FAILED;

        return $this;
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

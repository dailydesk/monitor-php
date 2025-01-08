<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Transports\NullTransport;
use DailyDesk\Monitor\Transports\SyncTransport;
use Exception;
use Inspector\Exceptions\InspectorException;
use Inspector\Inspector;
use Inspector\Models\PerformanceModel;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use InvalidArgumentException;

class Monitor extends Inspector
{
    /**
     * @param  Configuration|string  $configuration
     * @throws InvalidArgumentException|InspectorException
     */
    public function __construct($configuration)
    {
        if ($configuration instanceof Configuration) {
            parent::__construct($configuration);
        } elseif (is_string($configuration)) {
            $configuration = new Configuration($configuration);

            parent::__construct($configuration);
        } else {
            throw new InvalidArgumentException('$configuration must be a string or an instance of ' . Configuration::class);
        }

        if ($this->configuration->getTransport() === 'null') {
            $this->setTransport(new NullTransport);
        } else {
            $this->setTransport(
                new SyncTransport($configuration)
            );
        }
    }

    public function getConfiguration(): \Inspector\Configuration
    {
        return $this->configuration;
    }

    public function getTransport(): \Inspector\Transports\TransportInterface
    {
        return $this->transport;
    }

    /**
     * @param  string  $name
     * @return Transaction
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function startTransaction($name): Transaction
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Transaction name must be a string.');
        }
        if ($name == '') {
            throw new InvalidArgumentException('Transaction name cannot be empty.');
        }

        $transaction = parent::startTransaction($name);

        $transaction->hash = 'transaction-' . $transaction->hash;

        return $transaction;
    }

    /**
     * @param string $type
     * @param string|null $label
     * @return PerformanceModel|Segment
     * @throws InvalidArgumentException
     */
    public function startSegment($type, $label = null)
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('Segment type must be a string.');
        }

        if ($type == '') {
            throw new InvalidArgumentException('Segment type cannot be empty.');
        }

        if (!is_string($label)) {
            throw new InvalidArgumentException('Segment label must be a string.');
        }

        if ($label == '') {
            throw new InvalidArgumentException('Segment label cannot be empty.');
        }

        $segment = parent::startSegment($type, $label);

        $segment->hash = 'segment-' . (new Transaction('tmp'))->generateUniqueHash();

        return $segment;
    }
}

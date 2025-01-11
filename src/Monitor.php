<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Transports\CurlTransport;
use DailyDesk\Monitor\Transports\NullTransport;
use Exception;
use Inspector\Exceptions\InspectorException;
use Inspector\Inspector;
use Inspector\Models\Error;
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
            $this->setTransport(new NullTransport());
        } else {
            $this->setTransport(
                new CurlTransport($configuration)
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
        if (! is_string($name)) {
            throw new InvalidArgumentException('Transaction name must be a string.');
        }
        if ($name == '') {
            throw new InvalidArgumentException('Transaction name cannot be empty.');
        }

        return parent::startTransaction($name);
    }

    /**
     * @param string $type
     * @param string|null $label
     * @return PerformanceModel|Segment
     * @throws InvalidArgumentException
     */
    public function startSegment($type, $label = null)
    {
        if (! is_string($type)) {
            throw new InvalidArgumentException('Segment type must be a string.');
        }

        if ($type == '') {
            throw new InvalidArgumentException('Segment type cannot be empty.');
        }

        if (! is_string($label)) {
            throw new InvalidArgumentException('Segment label must be a string.');
        }

        if ($label == '') {
            throw new InvalidArgumentException('Segment label cannot be empty.');
        }

        $segment = parent::startSegment($type, $label);

        $segment->hash = (new Transaction('tmp'))->generateUniqueHash();

        $segment->transaction = [
            'hash' => $segment->transaction['hash'],
        ];

        unset($segment->host);

        return $segment;
    }

    public function reportException(\Throwable $exception, $handled = true)
    {
        if (!$this->hasTransaction()) {
            $this->startDefaultTransaction();
        }

        $this->transaction()->setResult('error');

        $segment = $this->startSegment('error', $exception->getMessage());

        $error = (new Error($exception, $this->transaction))->setHandled($handled);
        $segment->end();

        unset($error->host, $error->transaction);
        $error->segment = [
            'hash' => $segment->hash,
        ];

        $this->addEntries($error);

        return $error;
    }

    private function startDefaultTransaction()
    {
        if ($this->isRunningInConsole()) {
            $type = 'command';
            $name = implode(' ', $_SERVER['argv']);
        } else {
            $type = 'request';
            $name = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'];
        }

        return $this->startTransaction($name)->setType($type)->setResult('success');
    }

    private function isRunningInConsole(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    public function begin()
    {
        $this->startDefaultTransaction();

        return $this;
    }
}

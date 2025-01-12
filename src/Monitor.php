<?php

namespace DailyDesk\Monitor;

use Closure;
use DailyDesk\Monitor\Exceptions\LogicException;
use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\Transports\CurlTransport;
use DailyDesk\Monitor\Transports\NullTransport;
use Exception;
use Inspector\Configuration;
use Inspector\Exceptions\InspectorException;
use Inspector\Inspector;
use Inspector\Models\Error;
use Inspector\Models\PerformanceModel;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use InvalidArgumentException;
use Throwable;

class Monitor extends Inspector
{
    public const VERSION = '1.x-dev';

    public const URL = 'https://monitor.dailydesk.app';

    protected Closure $detectRunningInConsoleCallback;

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

            $configuration->setUrl(static::URL)->setVersion(static::VERSION);

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

        $this->detectRunningInConsoleCallback = function () {
            return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
        };
    }

    /**
     * Get the Configuration instance.
     *
     * @return \Inspector\Configuration
     */
    public function getConfiguration(): \Inspector\Configuration
    {
        return $this->configuration;
    }

    /**
     * Get the Transport instance.
     *
     * @return \Inspector\Transports\TransportInterface
     */
    public function getTransport(): \Inspector\Transports\TransportInterface
    {
        return $this->transport;
    }

    /**
     * Set the callback to detect if it's running in console. E.g.: useful for testing or custom runtime.
     *
     * @param  Closure  $callback
     * @return $this
     */
    public function detectRunningInConsoleUsing(Closure $callback)
    {
        $this->detectRunningInConsoleCallback = $callback;

        return $this;
    }

    /**
     * Start a new transaction with the given name.
     *
     * @param string $name
     * @return Transaction
     * @throws InvalidArgumentException|Exception
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
            'timestamp' => $segment->transaction['timestamp'],
        ];

        unset($segment->host);

        return $segment;
    }

    public function reportException(Throwable $exception, $handled = true)
    {
        if (!$this->hasTransaction()) {
            $this->start();
        }

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

    private function isRunningInConsole(): bool
    {
        return call_user_func($this->detectRunningInConsoleCallback, $this);
    }

    /**
     * Start a new Transaction instance.
     *
     * @param  Closure|string|null  $callback
     * @return \Inspector\Models\Transaction
     * @throws Exception
     */
    public function start($callback = null)
    {
        if ($this->isRunningInConsole()) {
            $type = 'command';
            $name = implode(' ', $_SERVER['argv']);
        } else {
            $type = 'request';
            $name = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'];
        }

        if (is_string($callback)) {
            $name = $callback;
        }

        $transaction = $this->startTransaction($name)->setType($type)->setResult('success');

        if ($callback instanceof Closure) {
            call_user_func($callback, $transaction);
        }

        return $transaction;
    }

    /**
     * Record a new segment.
     *
     * @param  string $type
     * @param  string  $label
     * @param callable $callback
     * @return Segment
     * @throws LogicException|MonitorException
     */
    public function segment(string $type, string $label, callable $callback)
    {
        if (is_null($this->transaction)) {
            throw new LogicException('Transaction must be started before recording a segment.');
        }

        try {
            return $this->addSegment($callback, $type, $label);
        } catch (Throwable $e) {
            throw new MonitorException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Report a new error.
     *
     * @param Throwable $e
     * @return \Inspector\Models\Error
     * @throws LogicException|MonitorException
     */
    public function report(Throwable $e)
    {
        if (is_null($this->transaction)) {
            throw new LogicException('Transaction must be started before reporting an error.');
        }

        try {
            return $this->reportException($e);
        } catch (Throwable $e) {
            throw new MonitorException($e->getMessage(), $e->getCode());
        }
    }
}

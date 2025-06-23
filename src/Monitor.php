<?php

namespace Monitor;

use Monitor\Exceptions\MonitorException;
use Monitor\Handlers\Handler;
use Monitor\Models\Error;
use Monitor\Models\Segment;
use Monitor\Models\Transaction;
use Throwable;

class Monitor
{
    /**
     * Determine if it should be enabled.
     */
    private bool $enabled = true;

    /**
     * Determine if it should flush on shutdown.
     */
    protected bool $flushOnShutdown = true;

    /**
     * The Handler instance.
     */
    protected Handler $handler;

    /**
     * The current Transaction instance.
     */
    private ?Transaction $transaction = null;

    /**
     * Create a new Monitor instance.
     */
    public function __construct(?Handler $handler = null)
    {
        $this->handler = $handler ?? new Handlers\NullHandler();

        register_shutdown_function(function () {
            if ($this->flushOnShutdown) {
                $this->flush();
            }
        });
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isFlushOnShutdown(): bool
    {
        return $this->flushOnShutdown;
    }

    public function setFlushOnShutdown(bool $flushOnShutdown): self
    {
        $this->flushOnShutdown = $flushOnShutdown;

        return $this;
    }

    public function getHandler(): Handler
    {
        return $this->handler;
    }

    public function setHandler(Handler $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    public function start(string $name, string $type = 'transaction'): self
    {
        if ($this->transaction) {
            throw new MonitorException('A transaction is already in progress. Please finish the current transaction before starting a new one.');
        }

        $this->transaction = new Transaction($name, $type);
        $this->transaction->setResult('unknown');

        $this->transaction->start();

        return $this;
    }

    public function context(string $key, mixed $value): self
    {
        if (!$this->transaction) {
            throw new MonitorException('No transaction is currently in progress. Please start a transaction before adding context.');
        }

        $this->transaction->addContext($key, $value);

        return $this;
    }

    public function result(string $value): self
    {
        if (!$this->transaction) {
            throw new MonitorException('No transaction is currently in progress. Please start a transaction before setting a result.');
        }

        $this->transaction->setResult($value);

        return $this;
    }

    public function end(): self
    {
        if (!$this->transaction) {
            throw new MonitorException('No transaction is currently in progress.');
        }

        $this->transaction->end();

        return $this;
    }

    public function startSegment(string $type, ?string $label = null): Segment
    {
        if (!$this->transaction) {
            throw new MonitorException('No transaction is currently in progress. Please start a transaction before starting a segment.');
        }

        $segment = new Segment($this->transaction, addslashes($type), $label);
        $segment->start();
        $this->transaction->addSegment($segment);
        return $segment;
    }

    public function addSegment(callable $callback, string $type, ?string $label = null, bool $throw = false): mixed
    {
        $segment = $this->startSegment($type, $label);

        try {
            return $callback($segment);
        } catch (\Throwable $exception) {
            if ($throw === true) {
                throw $exception;
            }

            $this->report($exception);
        } finally {
            $segment->end();
        }

        return null;
    }

    public function report(Throwable $exception, bool $handled = false): Segment
    {
        $segment = $this->startSegment('exception', $exception->getMessage());

        $error = (new Error($exception, $this->transaction))->setHandled($handled);

        $segment->setError($error)->end();

        return $segment;
    }

    public function flush(): self
    {
        if ($this->transaction) {
            $this->end();
        }

        if ($this->enabled && $this->transaction) {
            $this->handler->handle($this->transaction);
        }

        $this->transaction = null;

        memory_reset_peak_usage();

        return $this;
    }
}

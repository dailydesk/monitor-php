<?php

namespace DailyDesk\Monitor;

use Closure;
use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;

class Monitor
{
    public const VERSION = 'dev-main';

    public const URL = 'https://monitor.dailydesk.app';

    /**
     * Determine if this monitor is recording.
     *
     * @var bool
     */
    protected bool $recording = true;

    /**
     * Determine if this monitor should flush on shutdown.
     *
     * @var bool
     */
    protected bool $flushOnShutdown = true;

    /**
     * The handler instance.
     *
     * @var HandlerInterface|Closure|null
     */
    protected $handler = null;

    /**
     * The current queue.
     *
     * @var \DailyDesk\Monitor\Models\Transaction[]|\DailyDesk\Monitor\Models\Segment[]
     */
    protected array $queue = [];

    /**
     * The current transaction.
     *
     * @var Transaction|null
     */
    protected ?Transaction $transaction = null;

    /**
     * Create a new Monitor instance.
     *
     * @throws MonitorException
     */
    public function __construct()
    {
        register_shutdown_function(function () {
            if ($this->isFlushOnShutdown()) {
                $this->flush();
            }
        });
    }

    /**
     * Determine if this monitor is recording.
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        return $this->recording;
    }

    /**
     * Start recording.
     *
     * @return $this
     */
    public function startRecording(): self
    {
        $this->recording = true;

        return $this;
    }

    /**
     * Stop recording.
     *
     * @return $this
     */
    public function stopRecording(): self
    {
        $this->recording = false;

        return $this;
    }

    public function isFlushOnShutdown(): bool
    {
        return $this->flushOnShutdown;
    }

    /**
     * Turn on the flush on shutdown mode.
     *
     * @return $this
     */
    public function enableFlushOnShutdown(): self
    {
        $this->flushOnShutdown = true;

        return $this;
    }

    /**
     * Turn off the flush on shutdown mode.
     *
     * @return $this
     */
    public function disableFlushOnShutdown(): self
    {
        $this->flushOnShutdown = false;

        return $this;
    }

    /**
     * Get the current handler instance.
     *
     * @return Closure|HandlerInterface|null
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Set a new handler instance.
     *
     * @param Closure|HandlerInterface|null $handler
     * @return $this
     */
    public function setHandler($handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Get the current queue.
     *
     * @return Segment[]|Transaction[]
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * Push one or many entries into the current queue.
     *
     * @param  Transaction|Segment|Transaction[]|Segment[]  $entries
     * @return $this
     */
    public function pushIntoQueue($entries): self
    {
        if ($this->isRecording()) {
            $entries = is_array($entries) ? $entries : [$entries];
            foreach ($entries as $entry) {
                if ($entry->isStarted()) {
                    $entry->start();
                }
                $this->queue[] = $entry;
            }
        }

        return $this;
    }

    /**
     * Reset the current queue.
     *
     * @return $this
     */
    public function resetQueue(): self
    {
        $this->queue = [];

        return $this;
    }

    /**
     * Determine if the monitor holds a transaction.
     *
     * @return bool
     */
    public function hasTransaction(): bool
    {
        return isset($this->transaction);
    }

    /**
     * Determine if the monitor needs to start a new transaction.
     *
     * @return bool
     */
    public function needTransaction(): bool
    {
        return $this->isRecording() && ! $this->hasTransaction();
    }

    /**
     * Determine if the monitor can add segments.
     *
     * @return bool
     */
    public function canAddSegments(): bool
    {
        return $this->isRecording() && $this->hasTransaction();
    }

    /**
     * Get the current transaction.
     *
     * @return Transaction|null
     */
    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * Start a new transaction.
     *
     * @param string $name
     * @return Transaction
     * @throws \Exception
     */
    public function startTransaction(string $name): Transaction
    {
        if ($this->hasTransaction()) {
            $this->endEntriesInQueue();
        }

        $this->transaction = new Transaction($name);
        $this->transaction->start();

        $this->pushIntoQueue([$this->transaction]);

        return $this->transaction;
    }

    /**
     * Start a new segment.
     *
     * @param string $type
     * @param string $label
     * @return Segment
     */
    public function startSegment(string $type, string $label): Segment
    {
        $segment = new Segment($this->transaction, addslashes($type), $label);
        $segment->start();

        unset($segment->host);

        // TODO: Check if it can add segments
        if ($this->canAddSegments()) {
            $this->pushIntoQueue([$segment]);
        }

        return $segment;
    }

    /**
     * Add a new segment.
     *
     * @return mixed
     * @throws \Throwable
     */
    public function addSegment(callable $callback, string $type, string $label, bool $throw = false)
    {
        if (! $this->hasTransaction()) {
            return $callback();
        }

        try {
            $segment = $this->startSegment($type, $label);

            return $callback($segment);
        } catch (\Throwable $e) {
            if ($throw === true) {
                throw $e;
            }

            $this->report($e);
        } finally {
            $segment->end();
        }
    }

    /**
     * Report a Throwable instance.
     *
     * @param  \Throwable  $e
     * @param $handled
     * @return Segment
     * @throws \Exception
     */
    public function report(\Throwable $e, $handled = false): Segment
    {
        $class = get_class($e);

        if (! $this->hasTransaction()) {
            $this->startTransaction($class)->setType('error');
        }

        $segment = $this->startSegment('error', $e->getMessage());
        $segment->addContext('_monitor', [
            'error' => Helper::parseErrorData($e, $handled),
        ]);
        $segment->end();

        return $segment;
    }

    /**
     * Handle all entries in the current queue, then reset it.
     *
     * @throws MonitorException
     */
    public function flush(): self
    {
        $this->endEntriesInQueue();

        if ($this->handler instanceof Closure) {
            call_user_func($this->handler, $this->queue);
        } elseif ($this->handler instanceof HandlerInterface) {
            $this->handler->handle($this->queue);
        }

        $this->resetQueue();

        return $this;
    }

    /**
     * @internal
     *
     * @return void
     */
    private function endEntriesInQueue(): void
    {
        foreach ($this->queue as $entry) {
            if (! $entry->isEnded()) {
                $entry->end();
            }
        }
    }
}

<?php

namespace DailyDesk\Monitor;

use Closure;
use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\Handlers\TransportHandler;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;
use DailyDesk\Monitor\Transports\CurlTransport;
use Throwable;

class Monitor
{
    public const VERSION = '1.x-dev';

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
    protected bool $autoFlushEnabled = true;

    /**
     * The handler instance.
     *
     * @var HandlerInterface|Closure|null
     */
    protected $handler = null;

    /**
     * The current queue.
     *
     * @var array<int, Transaction|Segment>
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
            if ($this->isAutoFlush()) {
                $this->flush();
            }
        });
    }

    /**
     * Create a new Monitor instance with the given key.
     *
     * @param  string  $key
     * @param  array $options
     * @return static
     * @throws MonitorException
     */
    public static function create(string $key, array $options = []): static
    {
        try {
            $url = $options['url'] ?? 'https://monitor.dailydesk.app';

            $endpoint = rtrim($url, '/') . '/ingest/entries';
            $version = $options['version'] ?? static::VERSION;
            $maxItems = $options['max_items'] ?? 1000;

            $configuration = new \Inspector\Configuration($key);
            $configuration->setUrl($endpoint);
            $configuration->setVersion($version);
            $configuration->setMaxItems($maxItems);

            $transport = new CurlTransport($configuration);

            $handler = new TransportHandler($transport);

            $monitor = new static();

            $monitor->setHandler($handler);

            return $monitor;
        } catch (\Inspector\Exceptions\InspectorException $e) {
            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }
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

    public function isAutoFlush(): bool
    {
        return $this->autoFlushEnabled;
    }

    /**
     * Turn on the flush on shutdown mode.
     *
     * @return $this
     */
    public function enableAutoFlushMode(): self
    {
        $this->autoFlushEnabled = true;

        return $this;
    }

    /**
     * Turn off the flush on shutdown mode.
     *
     * @return $this
     */
    public function disableAutoFlushMode(): self
    {
        $this->autoFlushEnabled = false;

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
     * @param  Transaction|Segment|array<int, Transaction|Segment>  $entries
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
     * @throws MonitorException
     */
    public function startTransaction(string $name): Transaction
    {
        if ($this->hasTransaction()) {
            $this->endEntriesInQueue();
        }

        try {
            $this->transaction = new Transaction($name);
            $this->transaction->start();

            $this->pushIntoQueue($this->transaction);
        } catch (Throwable $e) {
            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }

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
            $this->pushIntoQueue($segment);
        }

        return $segment;
    }

    /**
     * Add a new segment.
     *
     * @return mixed|void
     *
     * @throws Throwable
     */
    public function addSegment(callable $callback, string $type, string $label, bool $throw = false)
    {
        if (! $this->hasTransaction()) {
            return $callback();
        }

        try {
            $segment = $this->startSegment($type, $label);

            return $callback($segment);
        } catch (Throwable $e) {
            if ($throw) {
                throw $e;
            }

            $this->report($e);
        } finally {
            if (isset($segment)) {
                $segment->end();
            }
        }
    }

    /**
     * Report a Throwable instance.
     *
     * @throws Throwable
     */
    public function report(Throwable $e, bool $handled = false): Segment
    {
        $class = get_class($e);

        if (! $this->hasTransaction()) {
            $this->startTransaction($class)->setType(Transaction::TYPE_UNEXPECTED)->markAsFailed();
        }

        $segment = $this->startSegment(Segment::TYPE_ERROR, $e->getMessage());
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

        $this->transaction = null;

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

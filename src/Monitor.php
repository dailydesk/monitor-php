<?php

namespace DailyDesk\Monitor;

use Closure;
use DailyDesk\Monitor\Exceptions\MissingTransactionException;
use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\Handlers\TransportHandler;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;
use DailyDesk\Monitor\Transports\CurlTransport;
use Inspector\Configuration as InspectorConfiguration;
use Inspector\Exceptions\InspectorException;
use Throwable;

class Monitor
{
    public const VERSION = '1.x-dev';

    /**
     * Determine if this monitor is recording.
     */
    protected bool $recording = true;

    /**
     * Determine if this monitor should flush on shutdown.
     */
    protected bool $flushOnShutdown = true;

    /**
     * The handler instance.
     */
    protected HandlerInterface|Closure|null $handler = null;

    /**
     * The current transaction.
     */
    protected ?Transaction $transaction = null;

    /**
     * @var array<int, Segment>
     */
    protected array $segments = [];

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
     * Create a new Monitor instance with the given key.
     *
     * @param  string  $key
     * @param  array<string, mixed>  $options
     * @return static
     * @throws MonitorException
     */
    public static function create(string $key, array $options = []): static
    {
        try {
            $base = $options['url'] ?? 'https://ingest.dailydesk.app';

            $url = rtrim($base, '/') . '/entries';
            $version = $options['version'] ?? static::VERSION;
            $maxItems = $options['max_items'] ?? 1000;

            $configuration = new InspectorConfiguration($key);
            $configuration->setUrl($url);
            $configuration->setVersion($version);
            $configuration->setMaxItems($maxItems);

            $transport = new CurlTransport($configuration);

            $handler = new TransportHandler($transport);

            $monitor = new static();

            $monitor->setHandler($handler);

            return $monitor;
        } catch (InspectorException $e) {
            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Determine if this monitor is recording.
     */
    public function isRecording(): bool
    {
        return $this->recording;
    }

    /**
     * Start recording.
     */
    public function startRecording(): self
    {
        $this->recording = true;

        return $this;
    }

    /**
     * Stop recording.
     */
    public function stopRecording(): self
    {
        $this->recording = false;

        return $this;
    }

    /**
     * Determine if the "flush on shutdown" mode is enabled.
     */
    public function isFlushOnShutdown(): bool
    {
        return $this->flushOnShutdown;
    }

    /**
     * Turn off the "flush on shutdown" mode.
     */
    public function disableFlushOnShutdown(): self
    {
        $this->flushOnShutdown = false;

        return $this;
    }

    /**
     * Turn on the "flush on shutdown" mode.
     */
    public function enableFlushOnShutdown(): self
    {
        $this->flushOnShutdown = true;

        return $this;
    }

    /**
     * Get the handler instance.
     */
    public function getHandler(): HandlerInterface|Closure|null
    {
        return $this->handler;
    }

    /**
     * Set a new handler instance.
     */
    public function setHandler(HandlerInterface|Closure|null $handler): self
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Get the current transaction.
     */
    public function transaction(): ?Transaction
    {
        return $this->transaction;
    }

    /**
     * Determine if the monitor holds a transaction.
     */
    public function hasTransaction(): bool
    {
        return isset($this->transaction);
    }

    /**
     * Determine if the monitor needs to start a new transaction.
     */
    public function needTransaction(): bool
    {
        return $this->isRecording() && ! $this->hasTransaction();
    }

    /**
     * Determine if the monitor can add segments.
     */
    public function canAddSegments(): bool
    {
        return $this->isRecording() && $this->hasTransaction();
    }

    /**
     * @return array<int, Segment>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     * Start a new transaction.
     *
     * @throws MonitorException
     */
    public function startTransaction(string $name): Transaction
    {
        if (! $this->isRecording()) {
            throw new MissingTransactionException('You must turn on recording to start a transaction.');
        }

        try {
            $this->transaction = new Transaction($name);
            $this->transaction->start();
        } catch (Throwable $e) {
            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->transaction;
    }

    /**
     * Start a new segment.
     */
    public function startSegment(string $type, string $label): Segment
    {
        if (! $this->hasTransaction()) {
            $transaction = new Transaction('dummy');
            $transaction->start();
            return new Segment($transaction, addslashes($type), $label);
        }

        $segment = new Segment($this->transaction, addslashes($type), $label);
        $segment->start();

        unset($segment->host);

        $this->segments[] = $segment;

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

            return $callback();
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

        if ($this->needTransaction()) {
            $this->startTransaction($class)
                ->setType(Transaction::TYPE_UNEXPECTED)
                ->markAsFailed();
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
        $entries = array_filter([
            $this->transaction,
            ...$this->segments,
        ]);

        foreach ($entries as $entry) {
            if (! $entry->isEnded()) {
                $entry->end();
            }
        }

        if ($this->handler instanceof Closure) {
            call_user_func($this->handler, $entries);
        } elseif ($this->handler instanceof HandlerInterface) {
            $this->handler->handle($entries);
        }

        $this->clear();

        return $this;
    }

    /**
     * Clear the transaction and segments.
     */
    public function clear(): self
    {
        $this->transaction = null;
        $this->segments = [];

        return $this;
    }
}

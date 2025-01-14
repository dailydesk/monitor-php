<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Transports\AsyncTransport;
use DailyDesk\Monitor\Transports\CurlTransport;
use DailyDesk\Monitor\Transports\NullTransport;
use Inspector\Configuration;
use Inspector\Exceptions\InspectorException;
use Inspector\Inspector;
use Inspector\Models\Error;
use Inspector\Models\Segment;
use Inspector\Models\Transaction;
use Inspector\Transports\TransportInterface;

class Monitor extends Inspector
{
    public const VERSION = '1.x-dev';

    public const URL = 'https://monitor.dailydesk.app';

    /**
     * @param  Configuration|string  $configuration
     * @throws InspectorException
     */
    public function __construct($configuration)
    {
        if (is_string($configuration)) {
            $configuration = new Configuration($configuration);
            $configuration->setUrl(self::URL);
        }

        parent::__construct($configuration);

        $this->transport = match ($configuration->getTransport()) {
            'async' => new AsyncTransport($configuration),
            'sync', 'curl' => new CurlTransport($configuration),
            default => new NullTransport(),
        };
    }

    /**
     * Get the Configuration instance.
     *
     * @return Configuration
     */
    public function configuration(): Configuration
    {
        return $this->configuration;
    }

    /**
     * Get the Transport instance.
     *
     * @return TransportInterface
     */
    public function transport(): TransportInterface
    {
        return $this->transport;
    }

    public function startTransaction($name): Transaction
    {
        return parent::startTransaction($name);
    }

    public function startSegment($type, $label = null): Segment
    {
        $segment = parent::startSegment($type, $label);

        unset($segment->host);

        return $segment;
    }

    public function report(\Throwable $e, $handled = true)
    {
        if (!$this->hasTransaction()) {
            $this->startTransaction(get_class($e))->setType('error');
        }

        $segment = $this->startSegment('error', $e->getMessage());

        $error = (new Error($e, $this->transaction))->setHandled($handled);
        $error->segment = $segment->only(['hash']);

        $segment->addContext('error', $error->only([
            'message', 'class', 'file', 'line', 'code', 'stack', 'handled',
        ]));

        $segment->end();

        return $error;
    }
}

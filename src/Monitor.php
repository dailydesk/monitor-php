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
            'curl' => new CurlTransport($configuration),
            default => new NullTransport(),
        };
    }

    public static function generateUniqueHash(int $length = 32): string
    {
        return (new Transaction('hash'))->generateUniqueHash($length);
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

    /**
     * @return array
     * @throws \Exception
     */
    public function flush()
    {
        $url = $this->configuration->getUrl();

        $this->configuration->setUrl(trim($url, '/') . '/ingest/entries');

        $entries = [];
        $prevEntry = null;

        foreach ($this->transport()->getQueue() as $entry) {
            if ($entry instanceof Segment) {
                unset($entry->host);

                if (isset($entry->context['Error']) && $entry->context['Error'] instanceof Error) {
                    unset($entry->context['Error']);
                }

                $entry->hash = static::generateUniqueHash();
                $entry->transaction = ['hash' => $entry->transaction['hash']];
            } elseif ($entry instanceof Error) {
                unset($entry->host, $entry->transaction);

                $entry->timestamp = $prevEntry->timestamp;
                $entry->segment = ['hash' => $prevEntry->hash];
            }

            $prevEntry = $entry;

            $entries[] = $entry->toArray();
        }

        parent::flush();

        $this->configuration->setUrl($url);

        return $entries;
    }
}

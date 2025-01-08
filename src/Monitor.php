<?php

namespace DailyDesk\Monitor;

use DailyDesk\Monitor\Transports\SyncTransport;
use Inspector\Exceptions\InspectorException;
use Inspector\Inspector;
use InvalidArgumentException;

class Monitor extends Inspector
{
    public const URL = 'https://monitor.dailydesk.app';

    public const VERSION = 'dev-main';

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

            $configuration
                ->setUrl(static::URL)
                ->setVersion(static::VERSION);

            parent::__construct($configuration);
        } else {
            throw new InvalidArgumentException('$configuration must be a string or an instance of ' . Configuration::class);
        }

        $this->setTransport(
            new SyncTransport($configuration)
        );
    }

    public function getConfiguration(): \Inspector\Configuration
    {
        return $this->configuration;
    }

    public function getTransport(): \Inspector\Transports\TransportInterface
    {
        return $this->transport;
    }
}

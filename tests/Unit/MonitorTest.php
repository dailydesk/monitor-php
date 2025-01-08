<?php

use DailyDesk\Monitor\Configuration;
use DailyDesk\Monitor\Monitor;
use DailyDesk\Monitor\Transports\SyncTransport;

test('it accepts a string in the constructor.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $this->assertInstanceOf(Configuration::class, $monitor->getConfiguration());
    $this->assertInstanceOf(SyncTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
    $this->assertSame(Monitor::URL, $monitor->getConfiguration()->getUrl());
    $this->assertSame(Monitor::VERSION, $monitor->getConfiguration()->getVersion());
});

test('it accepts an instanceof DailyDesk\Monitor\Configuration in the constructor.', function () {
    $configuration = new Configuration('an-ingestion-key');
    $url = $configuration->getUrl();
    $version = $configuration->getVersion();

    $monitor = new Monitor($configuration);

    $this->assertSame($configuration, $monitor->getConfiguration());
    $this->assertInstanceOf(SyncTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
    $this->assertSame($url, $monitor->getConfiguration()->getUrl());
    $this->assertSame($version, $monitor->getConfiguration()->getVersion());
});

test('it does not accept a value that is not either a string or an instanceof DailyDesk\Monitor\Configuration in the constructor.', function () {
    try {
        new Monitor(123);
        $this->fail('It must throw an exception here.');
    } catch (\Exception $e) {
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertSame('$configuration must be a string or an instance of ' . Configuration::class, $e->getMessage());
    }

    try {
        new Monitor(new \stdClass);
        $this->fail('It must throw an exception here.');
    } catch (\Exception $e) {
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertSame('$configuration must be a string or an instance of ' . Configuration::class, $e->getMessage());
    }
});

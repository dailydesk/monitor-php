<?php

use DailyDesk\Monitor\Configuration;
use DailyDesk\Monitor\Monitor;
use DailyDesk\Monitor\Transports\CurlTransport;
use DailyDesk\Monitor\Transports\NullTransport;

test('it accepts a string in the constructor.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $this->assertInstanceOf(Configuration::class, $monitor->getConfiguration());
    $this->assertInstanceOf(CurlTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
});

test('it accepts an instanceof DailyDesk\Monitor\Configuration in the constructor.', function () {
    $configuration = new Configuration('an-ingestion-key');

    $monitor = new Monitor($configuration);

    $this->assertSame($configuration, $monitor->getConfiguration());
    $this->assertInstanceOf(CurlTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
});

test('it does not accept a value that is not either a string or an instance of DailyDesk\Monitor\Configuration in the constructor.', function () {
    try {
        new Monitor(123);
        $this->fail('It must throw an exception here.');
    } catch (Exception $e) {
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertSame('$configuration must be a string or an instance of ' . Configuration::class, $e->getMessage());
    }

    try {
        new Monitor(new \stdClass());
        $this->fail('It must throw an exception here.');
    } catch (Exception $e) {
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertSame('$configuration must be a string or an instance of ' . Configuration::class, $e->getMessage());
    }
});

test('it requires the transaction name to be a string.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->startTransaction(123);
})->throws(InvalidArgumentException::class, 'Transaction name must be a string.');

test('it requires the transaction name to not be empty.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->startTransaction('');
})->throws(InvalidArgumentException::class, 'Transaction name cannot be empty.');

test('it requires the segment type to be a string.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment(123, 'segment-label');
})->throws(InvalidArgumentException::class, 'Segment type must be a string.');

test('it requires the segment type to not be empty.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('', 'segment-label');
})->throws(InvalidArgumentException::class, 'Segment type cannot be empty.');

test('it requires the segment label to be a string.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('segment-type', 123);
})->throws(InvalidArgumentException::class, 'Segment label must be a string.');

test('it requires the segment label to not be empty.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('segment-type', '');
})->throws(InvalidArgumentException::class, 'Segment label cannot be empty.');

test('it automatically generates hash for a new segment.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $segment = $monitor->startSegment('segment-type', 'segment-label');

    $this->assertNotEmpty($segment->hash);
});

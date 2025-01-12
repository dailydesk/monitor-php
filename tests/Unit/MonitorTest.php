<?php

use DailyDesk\Monitor\Monitor;
use DailyDesk\Monitor\Transports\CurlTransport;
use DailyDesk\Monitor\Transports\NullTransport;
use Inspector\Configuration;

test('it accepts a string in the constructor.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $this->assertInstanceOf(Configuration::class, $monitor->getConfiguration());
    $this->assertInstanceOf(CurlTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
});

test('it accepts an instanceof \Inspector\Configuration in the constructor.', function () {
    $configuration = new Configuration('an-ingestion-key');

    $monitor = new Monitor($configuration);

    $this->assertSame($configuration, $monitor->getConfiguration());
    $this->assertInstanceOf(CurlTransport::class, $monitor->getTransport());

    $this->assertSame('an-ingestion-key', $monitor->getConfiguration()->getIngestionKey());
});

test('it does not accept a value that is not either a string or an instance of \Inspector\Configuration in the constructor.', function () {
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

test('the start method accepts a string.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $transaction = $monitor->start('foobar');

    $this->assertSame('foobar', $transaction->name);
});

test('The start method accepts a callback.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $transaction = $monitor->start(function (\Inspector\Models\Transaction $transaction) {
        $transaction->type = 'type-via-callback';
        $transaction->name = 'name-via-callback';
    });

    $this->assertSame('type-via-callback', $transaction->type);
    $this->assertSame('name-via-callback', $transaction->name);
});

test('it can set the transaction name for a command.', function () {
    $origin['argv'] = $_SERVER['argv'];

    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport($transport = new NullTransport());

    $_SERVER['argv'] = ['main.php'];

    $transaction = $monitor->start();

    $this->assertSame('command', $transaction->type);
    $this->assertSame('main.php', $transaction->name);

    $_SERVER['argv'] = ['main.php', 'Developer', '--language=PHP'];

    $transaction = $monitor->start();

    $this->assertSame('command', $transaction->type);
    $this->assertSame('main.php Developer --language=PHP', $transaction->name);

    $_SERVER['argv'] = $origin['argv'];
});

test('it can set the transaction name for a request.', function () {
    $origin['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? null;
    $origin['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? null;

    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->detectRunningInConsoleUsing(function () {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/foobar';
        return false;
    });

    $transaction = $monitor->start();

    $this->assertSame('request', $transaction->type);
    $this->assertSame('GET /foobar', $transaction->name);

    $_SERVER['REQUEST_METHOD'] = $origin['REQUEST_METHOD'];
    $_SERVER['REQUEST_URI'] = $origin['REQUEST_URI'];
});

test('it requires a transaction to be started before adding a segment', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->segment('segment-type', 'segment-label', function () {
        //
    });
})->throws(
    \DailyDesk\Monitor\Exceptions\LogicException::class,
    'Transaction must be started before recording a segment.'
);

test('it requires a transaction to be started before reporting an error', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->report(new Exception('Something went wrong'));
})->throws(
    \DailyDesk\Monitor\Exceptions\LogicException::class,
    'Transaction must be started before reporting an error.'
);

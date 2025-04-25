<?php

use DailyDesk\Monitor\Exceptions\MissingTransactionException;
use DailyDesk\Monitor\Handlers\NullHandler;
use DailyDesk\Monitor\Handlers\TransportHandler;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;
use DailyDesk\Monitor\Monitor;

test('it should start recording by default.', function () {
    $monitor = new Monitor();

    $this->assertTrue($monitor->isRecording());
});

test('it can start or stop recording.', function () {
    $monitor = new Monitor();

    $monitor->stopRecording();
    $this->assertFalse($monitor->isRecording());

    $monitor->startRecording();
    $this->assertTrue($monitor->isRecording());
});

test('it should enable "flush on shutdown" by default.', function () {
    $monitor = new Monitor();
    $this->assertTrue($monitor->isFlushOnShutdown());
});

test('it can enable or disable "flush on shutdown".', function () {
    $monitor = new Monitor();

    $monitor->disableFlushOnShutdown();
    $this->assertFalse($monitor->isFlushOnShutdown());

    $monitor->enableFlushOnShutdown();
    $this->assertTrue($monitor->isFlushOnShutdown());
});

test('it does not have a handler by default.', function () {
    $monitor = new Monitor();

    $this->assertNull($monitor->getHandler());
});

test('it can set a handler.', function () {
    $monitor = new Monitor();

    $monitor->setHandler($handler = new NullHandler());
    $this->assertSame($handler, $monitor->getHandler());

    $monitor->setHandler($handler = function () {});
    $this->assertSame($handler, $monitor->getHandler());
});

test('it must turn on recording before starting a transaction.', function () {
    $monitor = new Monitor();

    $monitor->stopRecording();

    $monitor->startTransaction('pest:test');
})->throws(MissingTransactionException::class);

test('it can start a transaction.', function () {
    $monitor = new Monitor();

    $transaction = $monitor->startTransaction('pest:test');

    $this->assertSame($transaction, $monitor->transaction());
});

test('it can start a segment before starting transaction.', function () {
    $monitor = new Monitor();

    $segment = $monitor->startSegment('query', 'Get 10 users from database.');

    $this->assertSame('dummy', $segment->transaction['name']);
    $this->assertEmpty($monitor->getSegments());

    $result = $monitor->addSegment(function () {
        return 'hello';
    }, 'query', 'Get 10 users from database.');

    $this->assertSame('hello', $result);
    $this->assertEmpty($monitor->getSegments());
});

test('it can start a segment after starting transaction.', function () {
    $monitor = new Monitor();

    $transaction = $monitor->startTransaction('pest:test');

    $s1 = $monitor->startSegment('query', 'Get 10 users from database.');

    $this->assertSame($transaction->name, $s1->transaction['name']);
    $this->assertSame($transaction->hash, $s1->transaction['hash']);
    $this->assertSame($transaction->timestamp, $s1->transaction['timestamp']);

    $this->assertSame($transaction, $monitor->transaction());
    $this->assertSame([$s1], $monitor->getSegments());

    $result = $monitor->addSegment(function () {
        return 'hello';
    }, 'query', 'Get 10 users from database.');

    $this->assertSame('hello', $result);
    $this->assertCount(2, $monitor->getSegments());

    $s2 = $monitor->getSegments()[1];

    $this->assertSame($transaction->name, $s2->transaction['name']);
    $this->assertSame($transaction->hash, $s2->transaction['hash']);
    $this->assertSame($transaction->timestamp, $s2->transaction['timestamp']);
});

test('it can report an exception before starting transaction.', function () {
    $monitor = new Monitor();

    $segment = $monitor->report(new Exception('Something went wrong'));

    $this->assertSame(Segment::TYPE_ERROR, $segment->type);
    $this->assertArrayHasKey('_monitor', $segment->context);
    $this->assertArrayHasKey('error', $segment->context['_monitor']);

    $transaction = $monitor->transaction();

    $this->assertSame($transaction->name, $segment->transaction['name']);
    $this->assertSame($transaction->hash, $segment->transaction['hash']);
    $this->assertSame($transaction->timestamp, $segment->transaction['timestamp']);
});

test('it can report an exception after starting transaction.', function () {
    $monitor = new Monitor();
    $transaction = $monitor->startTransaction('pest:test');
    $segment = $monitor->report(new Exception('Something went wrong'));
    $this->assertSame($transaction->name, $segment->transaction['name']);
    $this->assertSame($transaction->hash, $segment->transaction['hash']);
    $this->assertSame($transaction->timestamp, $segment->transaction['timestamp']);
});

test('it can create an instance with a ingestion key.', function () {
    $key = 'JhQY9Rc2NWVLNDtqhLLffA5cWq1ZM3al';
    $monitor = Monitor::create($key);
    $this->assertInstanceOf(Monitor::class, $monitor);
    $this->assertInstanceOf(TransportHandler::class, $monitor->getHandler());
    $this->assertTrue($monitor->isRecording());
    $this->assertTrue($monitor->isFlushOnShutdown());
});

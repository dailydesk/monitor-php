<?php

use DailyDesk\Monitor\HandlerInterface;
use DailyDesk\Monitor\Handlers\NullHandler;
use DailyDesk\Monitor\Models\Segment;
use DailyDesk\Monitor\Models\Transaction;
use DailyDesk\Monitor\Monitor;

test('it starts recording by default.', function () {
    $monitor = new Monitor();

    $this->assertTrue($monitor->isRecording());
});

test('it can start/stop recording anytime.', function () {
    $monitor = new Monitor();

    $monitor->stopRecording();
    $this->assertFalse($monitor->isRecording());

    $monitor->startRecording();
    $this->assertTrue($monitor->isRecording());
});

test('it enables flush on shutdown by default.', function () {
    $monitor = new Monitor();
    $this->assertTrue($monitor->isFlushOnShutdown());
});

test('it can enable/disable flush on shutdown later.', function () {
    $monitor = new Monitor();

    $monitor->disableFlushOnShutdown();
    $this->assertFalse($monitor->isFlushOnShutdown());

    $monitor->enableFlushOnShutdown();
    $this->assertTrue($monitor->isFlushOnShutdown());
});

test('it does not have a handler instance by default.', function () {
    $monitor = new Monitor();

    $this->assertNull($monitor->getHandler());
});

test('it can get/set a handler instance later.', function () {
    $monitor = new Monitor();

    $monitor->setHandler($handler = new NullHandler());
    $this->assertSame($handler, $monitor->getHandler());

    $monitor->setHandler($handler = function () {});
    $this->assertSame($handler, $monitor->getHandler());
});

test('it will push entries into the queue when recording is on.', function () {
    $monitor = new Monitor();
    $this->assertEmpty($monitor->getQueue());

    $monitor->pushIntoQueue($transaction = new Transaction('test'));
    $monitor->pushIntoQueue($segment1 = new Segment($transaction, 'type', 'label'));
    $monitor->pushIntoQueue($segment2 = new Segment($transaction, 'type', 'label'));
    $monitor->pushIntoQueue($segment3 = new Segment($transaction, 'type', 'label'));

    $this->assertEquals([$transaction, $segment1, $segment2, $segment3], $monitor->getQueue());
});

test('it will not push entries into the queue when recording is off.', function () {
    $monitor = new Monitor();
    $this->assertEmpty($monitor->getQueue());

    $monitor->pushIntoQueue($transaction = new Transaction('test'));
    $monitor->pushIntoQueue($segment1 = new Segment($transaction, 'type', 'label'));

    $monitor->stopRecording();

    $monitor->pushIntoQueue(new Segment($transaction, 'type', 'label'));
    $monitor->pushIntoQueue(new Segment($transaction, 'type', 'label'));

    $this->assertEquals([$transaction, $segment1], $monitor->getQueue());
});

test('it can reset the current queue.', function () {
    $monitor = new Monitor();
    $this->assertEmpty($monitor->getQueue());

    $monitor->pushIntoQueue($transaction = new Transaction('test'));
    $monitor->pushIntoQueue(new Segment($transaction, 'type', 'label'));
    $monitor->pushIntoQueue(new Segment($transaction, 'type', 'label'));
    $monitor->pushIntoQueue(new Segment($transaction, 'type', 'label'));

    $this->assertCount(4, $monitor->getQueue());

    $monitor->resetQueue();

    $this->assertCount(0, $monitor->getQueue());
});

test('it can start a new transaction.', function () {
    $monitor = new Monitor();

    $this->assertFalse($monitor->hasTransaction());
    $t1 = $monitor->startTransaction('test1');
    $this->assertTrue($monitor->hasTransaction());
    $this->assertSame($t1, $monitor->transaction());
    $t2 = $monitor->startTransaction('test2');
    $this->assertSame($t2, $monitor->transaction());
});

test('it need a new transaction when recording is on and there is no transaction.', function () {
    $monitor = new Monitor();

    $this->assertTrue($monitor->isRecording());
    $this->assertFalse($monitor->hasTransaction());
    $this->assertTrue($monitor->needTransaction());

    $monitor->stopRecording();

    $this->assertFalse($monitor->isRecording());
    $this->assertFalse($monitor->hasTransaction());
    $this->assertFalse($monitor->needTransaction());

    $monitor->startRecording()->startTransaction('test');

    $this->assertTrue($monitor->isRecording());
    $this->assertTrue($monitor->hasTransaction());
    $this->assertFalse($monitor->needTransaction());
});

test('it must start a new transaction before adding segments', function () {
    $monitor = new Monitor();

    $this->assertFalse($monitor->canAddSegments());

    $monitor->startTransaction('test');

    $this->assertTrue($monitor->canAddSegments());
});

test('it starts new segments on the current transaction.', function () {
    $monitor = new Monitor();

    $this->assertFalse($monitor->canAddSegments());

    $monitor->startTransaction('test');

    $this->assertTrue($monitor->canAddSegments());
});

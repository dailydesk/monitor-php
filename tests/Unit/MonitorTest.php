<?php

use DailyDesk\Monitor\Monitor;
use DailyDesk\Monitor\Transports\NullTransport;
use Inspector\Configuration;

test('The Monitor constructor accepts an ingestion key as the first parameter.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $this->assertInstanceOf(Configuration::class, $monitor->configuration());

    $this->assertSame('an-ingestion-key', $monitor->configuration()->getIngestionKey());
});

test('It returns a list of entries after flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('test', 'segment1')->end();
    $monitor->startSegment('test', 'segment2')->end();

    $queue = $monitor->transport()->getQueue();

    $entries = $monitor->flush();

    $this->assertCount(3, $entries);

    $this->assertSame($queue[0]->hash, $entries[0]['hash']);

    $this->assertSame($queue[0]->hash, $entries[1]['transaction']['hash']);

    $this->assertSame($queue[0]->hash, $entries[2]['transaction']['hash']);

});

test('It assigns a hash value to every segment before flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('test', 'segment1')->end();
    $monitor->startSegment('test', 'segment2')->end();

    $entries = $monitor->flush();

    $this->assertNotEmpty($entries[1]['hash']);
    $this->assertNotEmpty($entries[2]['hash']);
});

test('It removes transaction name and timestamp from every segment before flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('test', 'segment1')->end();
    $monitor->startSegment('test', 'segment2')->end();

    $entries = $monitor->flush();

    $this->assertSame($entries[0]['hash'], $entries[1]['transaction']['hash']);
    $this->assertArrayNotHasKey('name', $entries[1]['transaction']);
    $this->assertArrayNotHasKey('timestamp', $entries[1]['transaction']);

    $this->assertSame($entries[0]['hash'], $entries[2]['transaction']['hash']);
    $this->assertArrayNotHasKey('name', $entries[2]['transaction']);
    $this->assertArrayNotHasKey('timestamp', $entries[2]['transaction']);
});

test('It removes host from every segment before flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->startSegment('test', 'segment1')->end();
    $monitor->startSegment('test', 'segment2')->end();

    $entries = $monitor->flush();

    $this->assertSame($entries[0]['hash'], $entries[1]['transaction']['hash']);
    $this->assertArrayNotHasKey('host', $entries[1]);

    $this->assertSame($entries[0]['hash'], $entries[2]['transaction']['hash']);
    $this->assertArrayNotHasKey('host', $entries[2]);
});

test('It assigns every error to their segments before flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->reportException(new Exception('exception1'));
    $monitor->reportException(new Exception('exception2'));

    $entries = $monitor->flush();

    $this->assertCount(5, $entries);

    $this->assertSame($entries[1]['hash'], $entries[2]['segment']['hash']);
    $this->assertSame($entries[3]['hash'], $entries[4]['segment']['hash']);
});

test('It removes transaction and host from every error before flushing.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $monitor->setTransport(new NullTransport());

    $monitor->startTransaction('test');

    $monitor->reportException(new \Exception('exception1'));
    $monitor->reportException(new \Exception('exception2'));

    $entries = $monitor->flush();

    $this->assertCount(5, $entries);

    $this->assertArrayNotHasKey('host', $entries[2]);
    $this->assertArrayNotHasKey('transaction', $entries[2]);
    $this->assertArrayNotHasKey('host', $entries[4]);
    $this->assertArrayNotHasKey('transaction', $entries[4]);
});

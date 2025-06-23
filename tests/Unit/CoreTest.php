<?php

use Monitor\Exceptions\MonitorException;
use Monitor\Monitor;

test('it can start a transaction.', function () {
    $monitor = new Monitor();

    $monitor->start('pest:test');

    $transaction = $monitor->transaction();

    $this->assertInstanceOf(\Monitor\Models\Transaction::class, $transaction);

    $this->assertNotEmpty($transaction->hash);
    $this->assertSame('transaction', $transaction->type);
    $this->assertSame('pest:test', $transaction->name);
    $this->assertSame('unknown', $transaction->result);
    $this->assertNotEmpty($transaction->timestamp);
    $this->assertNull($transaction->duration);
    $this->assertNull($transaction->memory_peak);

    $transaction->end();

    $this->assertNotEmpty($transaction->duration);
    $this->assertNotEmpty($transaction->memory_peak);
});

test('it can start a transaction with a specific type.', function () {
    $monitor = new Monitor();

    $monitor->start('pest:test', 'command');

    $transaction = $monitor->transaction();

    $this->assertInstanceOf(\Monitor\Models\Transaction::class, $transaction);

    $this->assertSame('command', $transaction->type);
});

test('it throws an exception if a transaction is already in progress.', function () {
    $monitor = new Monitor();

    $monitor->start('pest:test');

    $this->expectException(MonitorException::class);
    $this->expectExceptionMessage('A transaction is already in progress. Please finish the current transaction before starting a new one.');

    $monitor->start('pest:test2');
});

test('it can add context to a transaction.', function () {
    $monitor = new Monitor();

    $monitor->start('pest:test');

    $monitor->context('key', 'value');

    $transaction = $monitor->transaction();

    $this->assertSame(['key' => 'value'], $transaction->context);

    $transaction->end();
});

test('it throws an exception if no transaction is in progress when adding context.', function () {
    $monitor = new Monitor();

    $this->expectException(MonitorException::class);
    $this->expectExceptionMessage('No transaction is currently in progress. Please start a transaction before adding context.');

    $monitor->context('key', 'value');
});

test('it can set a result for a transaction.', function () {
    $monitor = new Monitor();

    $monitor->start('pest:test');

    $monitor->result('success');

    $transaction = $monitor->transaction();

    $this->assertSame('success', $transaction->result);

    $transaction->end();
});

test('it throws an exception if no transaction is in progress when setting a result.', function () {
    $monitor = new Monitor();

    $this->expectException(MonitorException::class);
    $this->expectExceptionMessage('No transaction is currently in progress. Please start a transaction before setting a result.');

    $monitor->result('success');
});

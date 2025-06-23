<?php

use Monitor\Monitor;

test('it should be enabled by default.', function () {
    $monitor = new Monitor();

    $this->assertTrue($monitor->isEnabled());
});

test('it can be disabled.', function () {
    $monitor = new Monitor();

    $monitor->setEnabled(false);

    $this->assertFalse($monitor->isEnabled());
});

test('it should enable flush on shutdown by default.', function () {
    $monitor = new Monitor();

    $this->assertTrue($monitor->isFlushOnShutdown());
});

test('it can disable flush on shutdown.', function () {
    $monitor = new Monitor();

    $monitor->setFlushOnShutdown(false);

    $this->assertFalse($monitor->isFlushOnShutdown());
});

test('it use NullHandler by default.', function () {
    $monitor = new Monitor();

    $this->assertInstanceOf(\Monitor\Handlers\NullHandler::class, $monitor->getHandler());
});

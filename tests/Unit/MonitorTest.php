<?php

use DailyDesk\Monitor\Monitor;
use DailyDesk\Monitor\Transports\NullTransport;
use Inspector\Configuration;

test('The Monitor constructor accepts an ingestion key as the first parameter.', function () {
    $monitor = new Monitor('an-ingestion-key');

    $this->assertInstanceOf(Configuration::class, $monitor->configuration());

    $this->assertSame('an-ingestion-key', $monitor->configuration()->getIngestionKey());
});

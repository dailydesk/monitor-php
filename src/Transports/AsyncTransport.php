<?php

namespace DailyDesk\Monitor\Transports;

use Inspector\Transports\AsyncTransport as BaseAsyncTransport;

class AsyncTransport extends BaseAsyncTransport
{
    /**
     * @inheritDoc
     */
    protected function getApiHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Monitor-Key' => $this->config->getIngestionKey(),
            'X-Monitor-Version' => $this->config->getVersion(),
        ];
    }
}

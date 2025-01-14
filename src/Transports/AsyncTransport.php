<?php

namespace DailyDesk\Monitor\Transports;

class AsyncTransport extends \Inspector\Transports\AsyncTransport
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

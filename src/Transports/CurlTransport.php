<?php

namespace DailyDesk\Monitor\Transports;

use Inspector\Transports\CurlTransport as BaseCurlTransport;

class CurlTransport extends BaseCurlTransport
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

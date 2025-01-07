<?php

namespace DailyDesk\Monitor\Transports;

use Inspector\Transports\CurlTransport;

class SyncTransport extends CurlTransport
{
    /**
     * @inheritDoc
     */
    public function sendChunk($data)
    {
        $url = $this->config->getUrl();
        $this->config->setUrl($url . '/ingest/entries');
        parent::sendChunk($data);
        $this->config->setUrl($url);
    }

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

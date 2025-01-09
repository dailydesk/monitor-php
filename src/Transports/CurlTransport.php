<?php

namespace DailyDesk\Monitor\Transports;

class CurlTransport extends \Inspector\Transports\CurlTransport
{
    /**
     * @inheritDoc
     */
    public function sendChunk($data)
    {
        $url = $this->config->getUrl();
        $this->config->setUrl(
            trim($url, '/') . '/ingest/entries'
        );

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

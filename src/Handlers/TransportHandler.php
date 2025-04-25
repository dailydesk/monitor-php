<?php

namespace DailyDesk\Monitor\Handlers;

use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\HandlerInterface;
use Inspector\Transports\TransportInterface;
use Throwable;

readonly class TransportHandler implements HandlerInterface
{
    public function __construct(private TransportInterface $transport)
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function handle(array $entries): void
    {
        try {
            foreach ($entries as $entry) {
                $this->transport->addEntry($entry);
            }

            $this->transport->flush();
        } catch (Throwable $e) {

            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

<?php

namespace DailyDesk\Monitor\Handlers;

use DailyDesk\Monitor\Exceptions\MonitorException;
use DailyDesk\Monitor\HandlerInterface;
use Inspector\Transports\TransportInterface;

class TransportHandler implements HandlerInterface
{
    public function __construct(private readonly TransportInterface $transport)
    {
        //
    }

    /**
     * @param  array  $queue
     * @return array
     * @throws MonitorException
     */
    public function handle(array $queue)
    {
        try {
            foreach ($queue as $entry) {
                $this->transport->addEntry($entry);
            }

            $this->transport->flush();
        } catch (\Throwable $e) {

            throw new MonitorException($e->getMessage(), $e->getCode(), $e);
        }


        return $queue;
    }
}

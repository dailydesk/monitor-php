<?php

namespace Monitor\Handlers;

use Inspector\Transports\TransportInterface;
use Monitor\Models\Transaction;

class InspectorHandler implements Handler
{
    public function __construct(private TransportInterface $transport)
    {
        //
    }

    /**
     * @inheritDoc
     */
    public function handle(Transaction $transaction): void
    {
        $this->transport->addEntry($transaction)->flush();

        foreach ($transaction->getSegments() as $segment) {
            $this->transport->addEntry($segment);

            if ($error = $segment->getError()) {
                $this->transport->addEntry($error);
            }
        }

        $this->transport->flush();
    }
}

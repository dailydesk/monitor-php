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
        $this->transport->addEntry($transaction)->flush();;
    }
}

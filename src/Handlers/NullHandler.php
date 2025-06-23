<?php

namespace Monitor\Handlers;

use Monitor\Models\Transaction;

class NullHandler implements Handler
{
    /**
     * @inheritDoc
     */
    public function handle(Transaction $transaction): void
    {
        //
    }
}

<?php

namespace Monitor\Handlers;

use Monitor\Exceptions\MonitorException;
use Monitor\Models\Transaction;

interface Handler
{
    /**
     * Handle the given transaction.
     *
     * @return mixed
     * @throws MonitorException
     */
    public function handle(Transaction $transaction);
}

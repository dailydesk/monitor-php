<?php

namespace DailyDesk\Monitor;

use Inspector\Models\Error;
use Inspector\Models\Transaction;
use Throwable;

class Helper
{
    public static function parseErrorData(Throwable $e, bool $handled = false): array
    {
        $error = (new Error($e, new Transaction('error')))->setHandled($handled);

        return $error->only([
            'message',
            'class',
            'file',
            'line',
            'code',
            'stack',
            'handled',
        ]);
    }
}

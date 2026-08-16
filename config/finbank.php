<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Large Transaction Threshold
    |--------------------------------------------------------------------------
    |
    | This lab value is not a real fraud rule. It exists so the application can
    | produce a security event that Wazuh can parse later.
    |
    */

    'large_transaction_threshold' => env('LARGE_TRANSACTION_THRESHOLD', 1000000),
];

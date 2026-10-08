<?php

return [
    // Which gateway implementation to use. Only "fake" ships with this sample.
    'gateway' => env('PAYMENTS_GATEWAY', 'fake'),

    // Shared secret used to sign webhook bodies (HMAC SHA-256).
    'webhook_secret' => env('PAYMENTS_WEBHOOK_SECRET', ''),
];

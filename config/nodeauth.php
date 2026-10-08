<?php

return [
    // Shared secret with the Node auth service (same value on both sides).
    'secret' => env('NODE_JWT_SECRET', ''),

    'algo' => 'HS256',

    // Seconds of clock drift allowed between the two servers.
    'leeway' => 30,
];

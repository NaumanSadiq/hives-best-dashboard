<?php

return [
    'version' => substr(hash('sha256',
        hash_file('sha256', public_path('images/brand/logo-full.png')).
        hash_file('sha256', public_path('images/brand/logo-emblem.png')).
        hash_file('sha256', public_path('images/brand/favicon.ico'))
    ), 0, 12),
];

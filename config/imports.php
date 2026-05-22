<?php

return [
    'queue' => env('IMPORTS_QUEUE', env('EXPORTS_QUEUE', 'exports')),
];

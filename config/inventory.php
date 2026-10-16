<?php

return [
    'image_disk' => env('INVENTORY_IMAGE_DISK', 'local'),
    'image_max_kb' => (int) env('INVENTORY_IMAGE_MAX_KB', 10240),
    'image_max_pixels' => (int) env('INVENTORY_IMAGE_MAX_PIXELS', 24000000),
    'units' => ['piece', 'box', 'liter', 'kilogram', 'meter'],
];

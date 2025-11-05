<?php

return [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_inventory,write_inventory,read_locations,write_locations'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
];


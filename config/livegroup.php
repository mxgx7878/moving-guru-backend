<?php
return [
  'merchant_id' => env('LIVEGROUP_MERCHANT_ID'),
  'api_key'     => env('LIVEGROUP_API_KEY'),
  'base_url'    => rtrim(env('LIVEGROUP_BASE_URL',''),'/'),
  'timeout'     => 15,
];

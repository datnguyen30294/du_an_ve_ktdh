<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Default Commission
    |--------------------------------------------------------------------------
    |
    | Fallback values when platform service external API is unavailable.
    | Platform is always deducted first (sort_order = 1) in the distribution.
    |
    */
    'platform_default_percent' => (float) env('COMMISSION_PLATFORM_PERCENT', 0),
    'platform_default_fixed' => (float) env('COMMISSION_PLATFORM_FIXED', 0),
];

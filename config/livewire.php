<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Livewire Configuration
    |--------------------------------------------------------------------------
    */

    'hash_key' => env('LIVEWIRE_HASH_KEY', 'livewire'),

    /*
    |--------------------------------------------------------------------------
    | Livewire Asset URL
    |--------------------------------------------------------------------------
    */

    'asset_url' => env('LIVEWIRE_ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Livewire Attribute Syntax
    |--------------------------------------------------------------------------
    */

    'attribute_syntax' => 'new', // 'new' for Livewire 3 style

    /*
    |--------------------------------------------------------------------------
    | Livewire Lazy Loading (NEW - PERFORMANCE OPTIMIZATION)
    |--------------------------------------------------------------------------
    | Enable lazy loading for large Livewire components. This improves
    | initial page load time significantly.
    */

    'lazy_loading' => [
        'enabled' => true,
        'throttle' => 'throttle:1s',
        'defer' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Pagination
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        'default_per_page' => 15,  // Reduced from 20 for faster initial loads
        'default_paginate_view' => 'pagination::tailwind',
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Rendering Options
    |--------------------------------------------------------------------------
    | Configure how components render
    */

    'render' => [
        'layout' => 'layouts.app',
        'lazy_placeholder' => '<div class="flex justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div></div>',
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire Temporary Storage
    |--------------------------------------------------------------------------
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'directory' => 'livewire-tmp',
        'rules' => 'file|max:12288', // 12MB
        'cleanup' => true,
    ],

];

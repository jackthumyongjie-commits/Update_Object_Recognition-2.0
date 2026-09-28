<?php

declare(strict_types=1);

/**
 * Copy this file to config.php and fill in your values.
 * Never commit config.php with real API keys.
 */
return [
    // AI provider: auto | gemini | agnes
    'ai_provider' => 'auto',

    // Gemini Vision (Google AI Studio / Generative Language API)
    'gemini_api_key' => '',
    'gemini_model' => 'gemini-flash-latest',
    'gemini_fallback_models' => "gemini-3.6-flash\ngemini-3-flash-preview",
    'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',

    // Agnes Vision (OpenAI-compatible — base URL ending in /v1)
    'agnes_api_key' => '',
    'agnes_model' => 'agnes-3.0-flash',
    'agnes_fallback_models' => "agnes-2.5-flash",
    'agnes_endpoint' => 'https://apihub.agnes-ai.com/v1',

    // Request timeout in seconds (10–120)
    'request_timeout' => 45,

    // Optional MySQL scan history (leave empty to disable)
    'db_host' => '127.0.0.1',
    'db_name' => 'ai_ar_scanner',
    'db_user' => 'root',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    'history_enabled' => false,

    // Optional web product lookup after AI recognition
    'web_lookup_enabled' => true,

    // Application display name
    'app_name' => 'AI + AR Object Scanner',
];

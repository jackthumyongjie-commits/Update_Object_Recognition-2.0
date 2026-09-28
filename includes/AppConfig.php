<?php

declare(strict_types=1);

/**
 * Loads and merges file config with optional runtime settings.
 */
final class AppConfig
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $values;

    private string $runtimePath;

    private function __construct()
    {
        $root = dirname(__DIR__);
        $fileConfig = $root . '/config/config.php';
        $example = $root . '/config/config.example.php';

        if (is_file($fileConfig)) {
            /** @var array<string, mixed> $loaded */
            $loaded = require $fileConfig;
        } elseif (is_file($example)) {
            /** @var array<string, mixed> $loaded */
            $loaded = require $example;
        } else {
            $loaded = [];
        }

        $this->runtimePath = $root . '/storage/settings/runtime.json';
        $runtime = $this->readRuntime();
        $this->values = array_merge($loaded, $runtime);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Public-safe settings (no API keys).
     *
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        return [
            'ai_provider' => $this->getString('ai_provider', 'auto'),
            'gemini_model' => $this->getString('gemini_model'),
            'gemini_fallback_models' => $this->getString('gemini_fallback_models'),
            'agnes_model' => $this->getString('agnes_model'),
            'agnes_fallback_models' => $this->getString('agnes_fallback_models'),
            'agnes_endpoint' => $this->getString('agnes_endpoint'),
            'request_timeout' => $this->getInt('request_timeout', 45),
            'history_enabled' => $this->getBool('history_enabled'),
            'web_lookup_enabled' => $this->getBool('web_lookup_enabled'),
            'app_name' => $this->getString('app_name', 'AI + AR Object Scanner'),
            'gemini_key_configured' => $this->getString('gemini_api_key') !== '',
            'agnes_key_configured' => $this->getString('agnes_api_key') !== '',
        ];
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function saveRuntime(array $updates): void
    {
        $allowed = [
            'ai_provider',
            'gemini_api_key',
            'gemini_model',
            'gemini_fallback_models',
            'agnes_api_key',
            'agnes_model',
            'agnes_fallback_models',
            'agnes_endpoint',
            'request_timeout',
            'history_enabled',
            'web_lookup_enabled',
        ];

        $current = $this->readRuntime();

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $updates)) {
                continue;
            }

            $value = $updates[$key];

            // Keep existing key when blank password field is submitted
            if (($key === 'gemini_api_key' || $key === 'agnes_api_key') && $value === '') {
                continue;
            }

            if ($key === 'request_timeout') {
                $timeout = (int) $value;
                $current[$key] = max(10, min(120, $timeout));
                continue;
            }

            if ($key === 'ai_provider') {
                $provider = strtolower((string) $value);
                $current[$key] = in_array($provider, ['auto', 'gemini', 'agnes'], true)
                    ? $provider
                    : 'auto';
                continue;
            }

            if ($key === 'history_enabled' || $key === 'web_lookup_enabled') {
                $current[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            $current[$key] = is_string($value) ? trim($value) : $value;
        }

        $dir = dirname($this->runtimePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->runtimePath,
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        $this->values = array_merge($this->values, $current);
    }

    /**
     * @return array<string, mixed>
     */
    private function readRuntime(): array
    {
        if (!is_file($this->runtimePath)) {
            return [];
        }

        $raw = file_get_contents($this->runtimePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Simple logger that never writes secrets.
     */
    public static function log(string $message): void
    {
        $path = dirname(__DIR__) . '/storage/logs/app.log';
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safe = preg_replace('/(api[_-]?key|authorization|bearer)\s*[:=]\s*\S+/i', '$1=***', $message) ?? $message;
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $safe . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}

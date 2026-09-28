<?php

declare(strict_types=1);

/**
 * Orchestrates provider selection, fallback, validation, and optional lookup/history.
 */
final class ObjectRecognizer
{
    private AppConfig $config;
    private GeminiVision $gemini;
    private AgnesVision $agnes;
    private ProductLookup $lookup;

    public function __construct(?AppConfig $config = null)
    {
        $this->config = $config ?? AppConfig::getInstance();
        $this->gemini = new GeminiVision($this->config);
        $this->agnes = new AgnesVision($this->config);
        $this->lookup = new ProductLookup($this->config);
    }

    /**
     * @return array{ok:true,result:ProductResult,stage:string}|array{ok:false,error:string}
     */
    public function recognize(string $imageBinary, string $mime, string $lang = 'en'): array
    {
        $lang = strtolower($lang) === 'zh' ? 'zh' : 'en';
        $mode = strtolower($this->config->getString('ai_provider', 'auto'));

        if ($mode === 'gemini') {
            if (!$this->gemini->isConfigured()) {
                return ['ok' => false, 'error' => $this->msg('not_configured', $lang)];
            }
            $outcome = $this->gemini->recognize($imageBinary, $mime, $lang);
            return $this->finalize($outcome, $lang);
        }

        if ($mode === 'agnes') {
            if (!$this->agnes->isConfigured()) {
                return ['ok' => false, 'error' => $this->msg('not_configured', $lang)];
            }
            $outcome = $this->agnes->recognize($imageBinary, $mime, $lang);
            return $this->finalize($outcome, $lang);
        }

        $errors = [];
        $hasAny = false;

        if ($this->gemini->isConfigured()) {
            $hasAny = true;
            $outcome = $this->gemini->recognize($imageBinary, $mime, $lang);
            if ($outcome['ok']) {
                return $this->finalize($outcome, $lang);
            }
            $errors[] = $outcome['error'];
        }

        if ($this->agnes->isConfigured()) {
            $hasAny = true;
            $outcome = $this->agnes->recognize($imageBinary, $mime, $lang);
            if ($outcome['ok']) {
                return $this->finalize($outcome, $lang);
            }
            $errors[] = $outcome['error'];
        }

        if (!$hasAny) {
            return ['ok' => false, 'error' => $this->msg('not_configured', $lang)];
        }

        return [
            'ok' => false,
            'error' => $errors[0] ?? $this->msg('identify_fail', $lang),
        ];
    }

    /**
     * @return array{gemini:array{ok:bool,message:string},agnes:array{ok:bool,message:string}}
     */
    public function testConnections(): array
    {
        return [
            'gemini' => $this->gemini->testConnection(),
            'agnes' => $this->agnes->testConnection(),
        ];
    }

    /**
     * @param array{ok:true,result:ProductResult}|array{ok:false,error:string} $outcome
     * @return array{ok:true,result:ProductResult,stage:string}|array{ok:false,error:string}
     */
    private function finalize(array $outcome, string $lang = 'en'): array
    {
        if (!$outcome['ok']) {
            return [
                'ok' => false,
                'error' => $outcome['error'] !== ''
                    ? $outcome['error']
                    : $this->msg('identify_fail', $lang),
            ];
        }

        /** @var ProductResult $result */
        $result = $outcome['result'];
        $stage = 'ai';

        try {
            $refined = $this->lookup->refine($result);
            if ($refined->productName !== $result->productName
                || $refined->description !== $result->description) {
                $stage = 'lookup';
            }
            $result = $refined;
        } catch (Throwable $e) {
            AppConfig::log('Web lookup skipped: ' . $e->getMessage());
        }

        try {
            ScanHistory::maybeSave($result);
        } catch (Throwable $e) {
            AppConfig::log('History save skipped: ' . $e->getMessage());
        }

        return [
            'ok' => true,
            'result' => $result,
            'stage' => $stage,
        ];
    }

    private function msg(string $key, string $lang): string
    {
        $zh = [
            'not_configured' => 'AI 提供商尚未配置。',
            'identify_fail' => '无法识别该物体。',
        ];
        $en = [
            'not_configured' => 'AI provider is not configured.',
            'identify_fail' => 'Unable to identify the object.',
        ];

        return ($lang === 'zh' ? $zh : $en)[$key] ?? $en[$key] ?? $key;
    }
}

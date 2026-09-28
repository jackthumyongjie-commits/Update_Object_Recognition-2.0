<?php

declare(strict_types=1);

/**
 * Normalized recognition result.
 */
final class ProductResult
{
    public function __construct(
        public string $objectLabel,
        public string $productName,
        public string $manufacturer,
        public string $specification,
        public string $description,
        /** @var array{ymin:int,xmin:int,ymax:int,xmax:int} */
        public array $boundingBox,
        public float $confidence,
        public string $provider = ''
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, string $provider = ''): self
    {
        $box = $data['boundingBox'] ?? [];
        if (!is_array($box)) {
            $box = [];
        }

        $ymin = self::clampCoord($box['ymin'] ?? 100);
        $xmin = self::clampCoord($box['xmin'] ?? 100);
        $ymax = self::clampCoord($box['ymax'] ?? 900);
        $xmax = self::clampCoord($box['xmax'] ?? 900);

        if ($ymax < $ymin) {
            [$ymin, $ymax] = [$ymax, $ymin];
        }
        if ($xmax < $xmin) {
            [$xmin, $xmax] = [$xmax, $xmin];
        }

        $confidence = (float) ($data['confidence'] ?? 0.5);
        if ($confidence > 1.0) {
            $confidence = $confidence / 100.0;
        }
        $confidence = max(0.0, min(1.0, $confidence));

        $objectLabel = trim((string) ($data['objectLabel'] ?? ''));
        $productName = trim((string) ($data['productName'] ?? ''));

        if ($objectLabel === '' && $productName !== '') {
            $objectLabel = $productName;
        }
        if ($productName === '' && $objectLabel !== '') {
            $productName = $objectLabel;
        }

        return new self(
            objectLabel: $objectLabel !== '' ? $objectLabel : 'unknown object',
            productName: $productName !== '' ? $productName : 'Unknown object',
            manufacturer: trim((string) ($data['manufacturer'] ?? 'Unknown')),
            specification: trim((string) ($data['specification'] ?? '')),
            description: trim((string) ($data['description'] ?? '')),
            boundingBox: [
                'ymin' => $ymin,
                'xmin' => $xmin,
                'ymax' => $ymax,
                'xmax' => $xmax,
            ],
            confidence: $confidence,
            provider: $provider !== '' ? $provider : (string) ($data['provider'] ?? '')
        );
    }

    public function isComplete(): bool
    {
        return $this->objectLabel !== ''
            && $this->objectLabel !== 'unknown object'
            && $this->productName !== ''
            && $this->productName !== 'Unknown object';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'objectLabel' => $this->objectLabel,
            'productName' => $this->productName,
            'manufacturer' => $this->manufacturer,
            'specification' => $this->specification,
            'description' => $this->description,
            'boundingBox' => $this->boundingBox,
            'confidence' => round($this->confidence, 4),
            'provider' => $this->provider,
        ];
    }

    private static function clampCoord(mixed $value): int
    {
        $n = (int) round((float) $value);
        return max(0, min(1000, $n));
    }
}

<?php

declare(strict_types=1);

/**
 * Optional MySQL scan history via PDO.
 */
final class ScanHistory
{
    private static ?PDO $pdo = null;

    public static function isEnabled(): bool
    {
        $config = AppConfig::getInstance();
        return $config->getBool('history_enabled')
            && $config->getString('db_name') !== '';
    }

    public static function maybeSave(ProductResult $result): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO scan_history
            (object_label, product_name, manufacturer, specification, description, confidence, provider, created_at)
            VALUES (:object_label, :product_name, :manufacturer, :specification, :description, :confidence, :provider, NOW())'
        );

        $stmt->execute([
            ':object_label' => $result->objectLabel,
            ':product_name' => $result->productName,
            ':manufacturer' => $result->manufacturer,
            ':specification' => $result->specification,
            ':description' => $result->description,
            ':confidence' => $result->confidence,
            ':provider' => $result->provider,
        ]);
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,perPage:int}
     */
    public static function list(string $search = '', int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        if (!self::isEnabled()) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE product_name LIKE :q OR object_label LIKE :q OR manufacturer LIKE :q';
            $params[':q'] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM scan_history {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id, object_label, product_name, manufacturer, specification, description,
                       confidence, provider, created_at
                FROM scan_history {$where}
                ORDER BY id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'objectLabel' => (string) $row['object_label'],
                'productName' => (string) $row['product_name'],
                'manufacturer' => (string) $row['manufacturer'],
                'specification' => (string) $row['specification'],
                'description' => (string) $row['description'],
                'confidence' => (float) $row['confidence'],
                'provider' => (string) $row['provider'],
                'createdAt' => (string) $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        if (!self::isEnabled() || $id < 1) {
            return null;
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, object_label, product_name, manufacturer, specification, description,
                    confidence, provider, created_at
             FROM scan_history WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'objectLabel' => (string) $row['object_label'],
            'productName' => (string) $row['product_name'],
            'manufacturer' => (string) $row['manufacturer'],
            'specification' => (string) $row['specification'],
            'description' => (string) $row['description'],
            'confidence' => (float) $row['confidence'],
            'provider' => (string) $row['provider'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    public static function clear(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        $pdo = self::pdo();
        if ($pdo === null) {
            return false;
        }

        $pdo->exec('DELETE FROM scan_history');
        return true;
    }

    private static function pdo(): ?PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = AppConfig::getInstance();
        $host = $config->getString('db_host', '127.0.0.1');
        $name = $config->getString('db_name');
        $user = $config->getString('db_user', 'root');
        $pass = $config->getString('db_pass');
        $charset = $config->getString('db_charset', 'utf8mb4');

        if ($name === '') {
            return null;
        }

        try {
            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$pdo;
        } catch (Throwable $e) {
            AppConfig::log('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}

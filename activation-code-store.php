<?php

class ActivationCodeStore
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $this->createPdo();
        $this->ensureSchema();
    }

    public function generateCode(?string $createdBy = null, ?string $expiresAt = null, int $length = 16): string
    {
        $code = $this->randomCode($length);
        $createdAt = $this->now();
        $status = 'active';

        $statement = $this->pdo->prepare(
            'INSERT INTO activation_codes (code, created_at, expires_at, created_by, status)
             VALUES (:code, :created_at, :expires_at, :created_by, :status)'
        );
        $statement->execute([
            ':code' => $code,
            ':created_at' => $createdAt,
            ':expires_at' => $expiresAt,
            ':created_by' => $createdBy,
            ':status' => $status,
        ]);

        return $code;
    }

    public function getCode(string $code): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM activation_codes WHERE code = :code');
        $statement->execute([':code' => $code]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markUsed(string $code, ?string $usedBy = null): bool
    {
        $usedAt = $this->now();
        $status = 'used';
        $statement = $this->pdo->prepare(
            'UPDATE activation_codes
             SET used_at = :used_at, used_by = :used_by, status = :status
             WHERE code = :code AND status = :active_status'
        );
        $statement->execute([
            ':used_at' => $usedAt,
            ':used_by' => $usedBy,
            ':status' => $status,
            ':code' => $code,
            ':active_status' => 'active',
        ]);

        return $statement->rowCount() > 0;
    }

    private function createPdo(): PDO
    {
        if (defined('DB_PATH')) {
            $directory = dirname(DB_PATH);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $pdo = new PDO(DB_DSN);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS activation_codes (
                code TEXT PRIMARY KEY,
                created_at TEXT NOT NULL,
                expires_at TEXT,
                used_at TEXT,
                used_by TEXT,
                created_by TEXT,
                status TEXT NOT NULL
            )'
        );
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_activation_codes_status ON activation_codes (status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_activation_codes_expires_at ON activation_codes (expires_at)');
    }

    private function randomCode(int $length): string
    {
        $bytes = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

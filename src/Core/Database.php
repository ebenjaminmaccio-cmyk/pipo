<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Acceso a MySQL con PDO. Siempre consultas preparadas: nunca concatenar datos en SQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                Config::get('db.host'),
                (int) Config::get('db.port', 3306),
                Config::get('db.name')
            );
            self::$pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.password'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    /** @param array<string|int,mixed> $params */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @param array<string|int,mixed> $params @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<string|int,mixed> $params @return list<array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            throw new \InvalidArgumentException('Tabla inválida');
        }
        $cols = array_keys($data);
        foreach ($cols as $c) {
            if (!preg_match('/^[a-z_]+$/', $c)) {
                throw new \InvalidArgumentException('Columna inválida');
            }
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', $cols),
            implode(',', array_map(fn ($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }
}

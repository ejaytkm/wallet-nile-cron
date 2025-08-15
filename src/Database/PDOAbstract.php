<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOStatement;

final class PDOAbstract
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array
    {
        $stmt = $this->run($sql, $params);
        return $stmt->fetchAll($fetchMode);
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->run($sql, $params);
        return $stmt->rowCount() > 0;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function begin(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    public function select(string $sql, array $params = [], int $fetch = PDO::FETCH_ASSOC): array
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->fetchAll($fetch);
    }

    public function set(array $assoc, string $prefix = 'p'): string
    {
        $parts = [];
        foreach ($assoc as $k => $_) $parts[] = $this->qid($k) . '=:' . $this->pf($prefix, $k);
        return implode(',', $parts);
    }


    private function prepareBindings(string $sql, array $params): array
    {
        $bind = [];
        foreach ($params as $name => $value) {
            if (is_array($value) && $this->isList($value) && $this->hasPh($sql, $name)) {
                [$sql, $adds] = $this->expandList($sql, $name, $value);
                $bind += $adds;
            } else {
                $bind[$name] = $value;
            }
        }
        return [$sql, $bind];
    }

    private function expandList(string $sql, string $name, array $list): array
    {
        $phs = []; $adds = []; $i = 0;
        foreach ($list as $v) {
            $ph = ':' . $name . '_' . $i++;
            $phs[] = $ph;
            $adds[ltrim($ph, ':')] = $v;
        }
        $sql = preg_replace('/(?<!:):' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/', implode(',', $phs), $sql, 1);
        return [$sql, $adds];
    }

    private function bindAll(PDOStatement $st, array $bind): void
    {
        foreach ($bind as $k => $v) $st->bindValue(is_int($k) ? $k + 1 : ':' . $k, $v);
    }

    private function isList(array $v): bool
    {
        return array_values($v) === $v;
    }

    private function qid(string $id): string
    {
        return '`' . str_replace('`', '``', $id) . '`';
    }

    private function pf(string $prefix, string $key): string
    {
        return $prefix . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $key);
    }

    private function hasPh(string $sql, string $name): bool
    {
        return (bool) preg_match('/(?<!:):' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/', $sql);
    }
}
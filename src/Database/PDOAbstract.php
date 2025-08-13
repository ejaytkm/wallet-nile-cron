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

    // ---------- Core ----------

    public function select(string $sql, array $params = [], int $fetch = PDO::FETCH_ASSOC): array
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->fetchAll($fetch);
    }

    public function selectOne(string $sql, array $params = [], int $fetch = PDO::FETCH_ASSOC): mixed
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->fetch($fetch) ?: null;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public function exec(string $sql, array $params = []): int
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->rowCount();
    }

    public function insertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    // ---------- Convenience fetchers ----------

    public function selectAssoc(string $sql, array $params = []): array
    {
        return $this->select($sql, $params, PDO::FETCH_ASSOC);
    }

    public function selectNum(string $sql, array $params = []): array
    {
        return $this->select($sql, $params, PDO::FETCH_NUM);
    }

    public function selectBoth(string $sql, array $params = []): array
    {
        return $this->select($sql, $params, PDO::FETCH_BOTH);
    }

    public function selectObj(string $sql, array $params = []): array
    {
        return $this->select($sql, $params, PDO::FETCH_OBJ);
    }

    /** FETCH_CLASS */
    public function selectClass(string $sql, array $params, string $class, array $ctorArgs = []): array
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_CLASS, $class, $ctorArgs);
    }

    /** FETCH_COLUMN into array */
    public function selectColumn(string $sql, array $params = [], int $column = 0): array
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        $out = [];
        while (($v = $st->fetchColumn($column)) !== false) $out[] = $v;
        return $out;
    }

    /** FETCH_KEY_PAIR (first col -> second col) */
    public function selectPairs(string $sql, array $params = []): array
    {
        [$sql2, $bind] = $this->prepareBindings($sql, $params);
        $st = $this->pdo->prepare($sql2);
        $this->bindAll($st, $bind);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // ---------- Assoc helpers for SET/INSERT ----------

    public function set(array $assoc, string $prefix = 'p'): string
    {
        $parts = [];
        foreach ($assoc as $k => $_) $parts[] = $this->qid($k) . '=:' . $this->pf($prefix, $k);
        return implode(',', $parts);
    }

    public function cols(array $assoc): string
    {
        return implode(',', array_map([$this, 'qid'], array_keys($assoc)));
    }

    public function marks(array $assoc, string $prefix = 'p'): string
    {
        $marks = [];
        foreach ($assoc as $k => $_) $marks[] = ':' . $this->pf($prefix, $k);
        return implode(',', $marks);
    }

    public function flatten(array $assoc, string $prefix = 'p'): array
    {
        $out = [];
        foreach ($assoc as $k => $v) $out[$this->pf($prefix, $k)] = $v;
        return $out;
    }

    // ---------- Internals ----------

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
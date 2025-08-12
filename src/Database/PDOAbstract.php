<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOStatement;
use InvalidArgumentException;

final class PDOAbstract {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $this->pdo = $pdo;
    }

    // Example: $db->select("SELECT * FROM users WHERE id IN (:ids)", ["ids"=>[1,2,3]])
    public function query(string $sql, array $params = []): PDOStatement {
        [$sql2, $params2] = $this->expandArrayParams($sql, $params);
        $stmt = $this->pdo->prepare($sql2);
        foreach ($params2 as $k => $v) {
            $name = is_int($k) ? $k+1 : (':' . ltrim((string)$k, ':'));
            $type = is_int($v) ? PDO::PARAM_INT :
                (is_bool($v) ? PDO::PARAM_BOOL :
                    (is_null($v) ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($name, $v, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public function select(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function exec(string $sql, array $params = []): int {
        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $fn) {
        $this->pdo->beginTransaction();
        try {
            $r = $fn($this);
            $this->pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function expandArrayParams(string $sql, array $params): array {
        $out = $params;
        foreach ($params as $name => $value) {
            if (is_array($value)) {
                if ($value === []) throw new InvalidArgumentException("Empty array for parameter :$name");
                $ph = [];
                foreach (array_values($value) as $i => $val) {
                    $key = "{$name}_{$i}";
                    $ph[] = ':' . $key;
                    $out[$key] = $val;
                }
                unset($out[$name]);
                $sql = preg_replace('/:' . preg_quote((string)$name, '/') . '\b/', implode(',', $ph), $sql);
            }
        }
        return [$sql, $out];
    }
}
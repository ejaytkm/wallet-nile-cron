<?php
declare(strict_types=1);
namespace App\Container;
use Psr\Container\ContainerInterface;

final class ContainerFactory {
    public static function build(): ContainerInterface {
        return new class implements ContainerInterface {
            private array $entries = [];
            public function get(string $id) {
                if (!isset($this->entries[$id])) {
                    $this->entries[$id] = $this->make($id);
                }
                return $this->entries[$id];
            }
            public function has(string $id): bool {
                return class_exists($id) || isset($this->entries[$id]);
            }

            public function set(string $id, mixed $entry): void {
                $this->entries[$id] = $entry;
            }

            private function make(string $id) {
                $r = new \ReflectionClass($id);
                $ctor = $r->getConstructor();
                if (!$ctor) return new $id();
                $deps = [];
                foreach ($ctor->getParameters() as $p) {
                    $t = $p->getType();
                    if ($t && !$t->isBuiltin()) {
                        $deps[] = $this->get($t->getName());
                    } elseif ($p->isDefaultValueAvailable()) {
                        $deps[] = $p->getDefaultValue();
                    } else {
                        $deps[] = null;
                    }
                }
                return $r->newInstanceArgs($deps);
            }
        };
    }
}

<?php

/**
 * Ejecutor mínimo de las pruebas cuando PHPUnit no está disponible (sin internet / sin Composer).
 * Implementa solo las aserciones que usan estas pruebas. Uso: php tests/tools/mini-runner.php
 * La forma recomendada sigue siendo: composer install && composer test
 */

namespace PHPUnit\Framework {
    class AssertionFailedError extends \Exception {}

    abstract class TestCase
    {
        public static int $aserciones = 0;
        private function ok(bool $c, string $m): void { self::$aserciones++; if (!$c) { throw new AssertionFailedError($m); } }
        public function assertSame($e, $a): void { $this->ok($e === $a, 'Se esperaba ' . var_export($e, true) . ' y se obtuvo ' . var_export($a, true)); }
        public function assertContains($n, array $h): void { $this->ok(in_array($n, $h, true), var_export($n, true) . ' no está en ' . var_export($h, true)); }
        public function assertTrue($c): void { $this->ok($c === true, 'Se esperaba true'); }
        public function assertFalse($c): void { $this->ok($c === false, 'Se esperaba false'); }
        public function assertNull($c): void { $this->ok($c === null, 'Se esperaba null'); }
        public function assertMatchesRegularExpression(string $p, string $s): void { $this->ok(preg_match($p, $s) === 1, "$s no cumple $p"); }
        public function assertStringContainsString(string $n, string $h): void { $this->ok(str_contains($h, $n), "No contiene $n"); }
    }
}

namespace {
    $raiz = dirname(__DIR__, 2);
    spl_autoload_register(function ($c) use ($raiz) {
        $p = 'GlpiPlugin\\Actasentrega\\';
        if (str_starts_with($c, $p) && !str_contains($c, '\\Tests\\')) {
            $f = $raiz . '/src/' . str_replace('\\', '/', substr($c, strlen($p))) . '.php';
            if (is_file($f)) { require $f; }
        }
    });

    $total = $fallas = 0;
    foreach (glob($raiz . '/tests/*Test.php') as $archivo) {
        $antes = get_declared_classes();
        require $archivo;
        foreach (array_diff(get_declared_classes(), $antes) as $clase) {
            if (!is_subclass_of($clase, \PHPUnit\Framework\TestCase::class)) { continue; }
            foreach (get_class_methods($clase) as $m) {
                if (!str_starts_with($m, 'test')) { continue; }
                $total++;
                try {
                    (new $clase())->$m();
                    echo "  ✔ $m\n";
                } catch (\Throwable $e) {
                    $fallas++;
                    echo "  ✘ $m — " . $e->getMessage() . "\n";
                }
            }
        }
    }
    printf("\n%d pruebas, %d aserciones, %d fallidas\n", $total, \PHPUnit\Framework\TestCase::$aserciones, $fallas);
    exit($fallas === 0 ? 0 : 1);
}

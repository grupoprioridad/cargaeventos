<?php
/**
 * ORQUESTADOR DE SCRAPERS — Carga de Eventos
 * 
 * Uso:
 *   php orchestrator.php --city=valdivia       # Ejecuta todos para Valdivia
 *   php orchestrator.php --city=pucon          # Ejecuta todos para Pucón
 *   php orchestrator.php --city=valdivia --list # Lista scrapers
 *   php orchestrator.php --city=pucon chilecultura  # Solo uno
 */

$ciudad = '';
$scrapersArgs = [];
$onlyList = false;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--city=')) {
        $ciudad = substr($arg, 7);
    } elseif ($arg === '--list') {
        $onlyList = true;
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "ORQUESTADOR DE SCRAPERS\n";
        echo "Uso: php orchestrator.php --city=valdivia|pucon [scraper|--list]\n";
        exit(0);
    } else {
        $scrapersArgs[] = $arg;
    }
}

if (!$ciudad) {
    echo "ERROR: Debes especificar --city=valdivia o --city=pucon\n";
    exit(1);
}

// Cargar configuración de la ciudad
$configFile = __DIR__ . "/../config/{$ciudad}.php";
if (!file_exists($configFile)) {
    echo "ERROR: No existe config/{$ciudad}.php\n";
    exit(1);
}
require_once $configFile;

echo "\n=== CARGA DE EVENTOS: " . strtoupper($ciudad) . " ===\n";
echo "Inicio: " . date('Y-m-d H:i:s') . "\n";
echo "Config: {$ciudad}.php\n\n";

// Cargar scrapers
$SCRAPERS = [];
$scraperDir = __DIR__;
foreach (glob($scraperDir . '/*.php') as $file) {
    $basename = basename($file);
    if (in_array($basename, ['ScraperBase.php', 'orchestrator.php'])) continue;
    try {
        require_once $file;
        foreach (array_reverse(get_declared_classes()) as $c) {
            if (is_subclass_of($c, 'ScraperBase')) {
                $ref = new ReflectionClass($c);
                if ($ref->getFileName() === $file) {
                    $instancia = new $c();
                    $SCRAPERS[$instancia->slug] = $instancia;
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
        echo "[orq] ERROR cargando $basename: {$e->getMessage()}\n";
    }
}

// Listar
if ($onlyList) {
    echo "Scrapers (" . count($SCRAPERS) . "):\n";
    foreach ($SCRAPERS as $s) echo "  {$s->slug}  — {$s->nombre}\n";
    exit(0);
}

// Determinar qué ejecutar
$ejecutar = empty($scrapersArgs) ? array_keys($SCRAPERS) : array_intersect($scrapersArgs, array_keys($SCRAPERS));

// Ejecutar
$resultados = [];
$inicio = microtime(true);
$totalEnviados = 0;

foreach ($ejecutar as $slug) {
    $s = $SCRAPERS[$slug];
    echo ">>> {$s->nombre} ({$slug})\n";
    try {
        $res = $s->run();
        $st = $res['stats'];
        $totalEnviados += $st['enviados'];
        $dup = $st['duplicados'] > 0 ? ", {$st['duplicados']} dup" : '';
        echo "    {$st['encontrados']} encont, {$st['enviados']} env, {$st['errores']} err{$dup}\n";
    } catch (\Throwable $e) {
        echo "    FALLO: {$e->getMessage()}\n";
    }
    if (count($ejecutar) > 1) sleep(1);
}

$duracion = round(microtime(true) - $inicio, 2);
echo "\n=== RESUMEN ===\n";
echo "  Ciudad: " . strtoupper($ciudad) . "\n";
echo "  Duración: {$duracion}s\n";
echo "  Scrapers: " . count($ejecutar) . "\n";
echo "  Enviados: $totalEnviados\n";

// Log
$logFile = __DIR__ . '/../scraper.log';
$linea = date('Y-m-d H:i:s') . " | ciudad={$ciudad} scrapers=" . count($ejecutar) . " enviados=$totalEnviados duracion={$duracion}s\n";
file_put_contents($logFile, $linea, FILE_APPEND);

exit($totalEnviados > 0 ? 0 : 0);

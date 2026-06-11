<?php
/**
 * API Pública de Eventos — Para consumo del widget JS
 * 
 * GET /api/eventos.php?ciudad=valdivia&limite=10
 * GET /api/eventos.php?ciudad=pucon&limite=10
 * 
 * Devuelve eventos aprobados en formato JSON para el widget.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$ciudad = $_GET['ciudad'] ?? 'valdivia';
$limite = min((int)($_GET['limite'] ?? 10), 50);

// Mapear ciudad a configuración de BD
$dbConfig = [
    'valdivia' => [
        'db'    => 'agendavaldivia',
        'host'  => 'localhost',
        'user'  => 'agendavaldivia',
        'pass'  => 'Valdivia2026!',
        'base_url' => 'https://agendavaldivia.j.prioridad.cl',
    ],
    'pucon' => [
        'db'    => 'agendapucon',
        'host'  => 'localhost',
        'user'  => 'agendapucon',
        'pass'  => 'AgendaPucon2026!',
        'base_url' => 'https://www.agendapucon.cl',
    ],
];

if (!isset($dbConfig[$ciudad])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ciudad no válida: use valdivia o pucon']);
    exit;
}

$cfg = $dbConfig[$ciudad];

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Eventos próximos aprobados
    $stmt = $pdo->prepare("
        SELECT id, nombre, fecha, fecha_termino, hora, hora_termino, 
               lugar, descripcion, foto, contacto, fuente
        FROM eventos 
        WHERE estado = 'aprobado' 
          AND fecha >= CURDATE()
        ORDER BY fecha ASC, hora ASC 
        LIMIT :limite
    ");
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear
    foreach ($eventos as &$ev) {
        $ev['fecha_fmt'] = date('d/m/Y', strtotime($ev['fecha']));
        $ev['hora_fmt'] = $ev['hora'] ? substr($ev['hora'], 0, 5) : '';
        $ev['hora_fin_fmt'] = $ev['hora_termino'] ? substr($ev['hora_termino'], 0, 5) : '';
        $ev['foto_url'] = $ev['foto'] ? (str_starts_with($ev['foto'], 'http') ? $ev['foto'] : $cfg['base_url'] . '/' . $ev['foto']) : '';

        // Limpiar HTML de descripción
        $ev['descripcion'] = strip_tags($ev['descripcion'] ?? '');
        $ev['descripcion'] = html_entity_decode($ev['descripcion'], ENT_QUOTES, 'UTF-8');
    }
    unset($ev);

    echo json_encode([
        'ok' => true,
        'ciudad' => $ciudad,
        'total' => count($eventos),
        'eventos' => $eventos,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión']);
}

<?php
/**
 * ScraperBase — Clase base para scrapers de eventos multi-ciudad
 * 
 * La configuración de ciudad se carga desde config/{ciudad}.php
 */

abstract class ScraperBase
{
    public string $nombre;
    public string $slug;
    protected string $apiUrl;
    protected string $apiToken;
    protected string $apiHost;
    protected int $regionId;
    protected string $ciudad;
    protected array $stats = [
        'encontrados' => 0, 'enviados' => 0, 'duplicados' => 0, 'errores' => 0,
    ];
    protected array $eventos = [];
    protected array $log = [];

    public function __construct()
    {
        $this->apiUrl   = defined('API_URL') ? API_URL : 'http://localhost/api_evento.php';
        $this->apiToken = defined('API_TOKEN') ? API_TOKEN : '';
        $this->apiHost  = defined('API_HOST') ? API_HOST : '';
        $this->regionId = defined('REGION_ID') ? REGION_ID : 14;
        $this->ciudad   = defined('CIUDAD_NOMBRE') ? CIUDAD_NOMBRE : 'Valdivia';
    }

    abstract protected function fetchEventos(): array;

    public function run(): array
    {
        $this->log("Iniciando: {$this->nombre} ({$this->slug})");

        try {
            $this->eventos = $this->fetchEventos();
            $this->stats['encontrados'] = count($this->eventos);
            $this->log("Encontrados {$this->stats['encontrados']} eventos");
        } catch (\Throwable $e) {
            $this->log("ERROR: " . $e->getMessage());
            $this->stats['errores'] = count($this->eventos) ?: 1;
            return $this->resultado();
        }

        foreach ($this->eventos as $ev) {
            $resultado = $this->enviarEvento($ev);
            if ($resultado['ok'] ?? false) {
                if ($resultado['duplicado'] ?? false) $this->stats['duplicados']++;
                else $this->stats['enviados']++;
            } else {
                $this->stats['errores']++;
            }
        }

        $this->log("Fin: {$this->stats['enviados']} enviados, {$this->stats['errores']} errores");
        return $this->resultado();
    }

    protected function enviarEvento(array $evento): array
    {
        $payload = array_merge([
            'nombre' => '', 'fecha' => date('Y-m-d'), 'fecha_fin' => '',
            'hora' => '', 'hora_fin' => '', 'lugar' => $this->ciudad,
            'descripcion' => '', 'contacto' => '', 'foto' => '',
            'fuente' => $this->slug, 'token' => $this->apiToken,
        ], $evento);

        $headers = ['Content-Type: application/json'];
        if ($this->apiHost) $headers[] = "Host: {$this->apiHost}";

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $resp) {
            $data = json_decode($resp, true);
            return $data ?: ['ok' => false, 'error' => 'JSON inválido'];
        }
        return ['ok' => false, 'error' => "HTTP $httpCode"];
    }

    protected function fetchUrl(string $url, int $timeout = 15): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'CargaEventos/1.0 (+https://agendavaldivia.cl)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpCode >= 200 && $httpCode < 400 && $html !== false) ? $html : null;
    }

    protected function fetchRss(string $url): ?SimpleXMLElement
    {
        $xml = $this->fetchUrl($url);
        if (!$xml) return null;
        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($xml);
        if ($rss === false) { $this->log("Error parseando RSS: $url"); return null; }
        return $rss;
    }

    protected function xmlText(?SimpleXMLElement $el): string
    {
        return $el ? trim((string)$el) : '';
    }

    protected function extraerImagen(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m)) {
            $url = $m[1];
            if (str_starts_with($url, '//')) return 'https:' . $url;
            return $url;
        }
        return null;
    }

    protected function stripHtml(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'))));
    }

    protected function extraerHora(string $texto): string
    {
        if (preg_match('/(\d{1,2}):(\d{2})\s*(?:horas?|hrs?|h)?/iu', $texto, $m))
            return sprintf('%02d:%02d', $m[1], $m[2]);
        return '';
    }

    protected function log(string $msg): void
    {
        $this->log[] = "[" . date('Y-m-d H:i:s') . "] $msg";
        echo "[{$this->slug}] $msg\n";
    }

    /**
     * Detectar si un texto describe un evento
     */
    protected function esEvento(string $titulo, string $descripcion = ''): bool
    {
        $texto = mb_strtolower($titulo . ' ' . $descripcion, 'UTF-8');
        $palabras = [
            'concierto', 'taller', 'charla', 'seminario', 'feria', 'exposición',
            'exposicion', 'muestra', 'festival', 'encuentro', 'lanzamiento',
            'presentación', 'presentacion', 'jornada', 'ciclo', 'conferencia',
            'espectáculo', 'espectaculo', 'teatro', 'obra', 'función', 'funcion',
            'curso', 'capacitación', 'capacitacion', 'celebración', 'celebracion',
            'aniversario', 'conmemoración', 'conmemoracion', 'actividad', 'evento',
            'inauguración', 'inauguracion', 'torneo', 'competencia', 'campeonato',
            'día', 'jornada', 'invita', 'abierto', 'participa', 'inscripción',
            'inscripcion', 'gratis', 'entrada', 'show', 'recital', 'clínica',
            'clinica', 'conversatorio', 'ciclo', 'feria', 'paseo', 'recorrido',
            'visita', 'apertura', 'lanzamiento', 'presenta', 'convocatoria',
            ' viene', 'llega', 'imperdible', 'agenda', 'cartelera',
        ];
        foreach ($palabras as $p) {
            if (str_contains($texto, $p)) return true;
        }
        return false;
    }

    protected function resultado(): array
    {
        return ['fuente' => $this->slug, 'nombre' => $this->nombre, 'stats' => $this->stats];
    }
}

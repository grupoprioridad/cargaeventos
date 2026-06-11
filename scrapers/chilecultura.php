<?php
/**
 * Scraper: ChileCultura — Ministerio de las Culturas
 * Busca eventos en múltiples regiones cercanas a la ciudad.
 * 
 * Para Pucón: busca Araucanía (intentar varios IDs)
 * Para Valdivia: busca Los Ríos
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperChileCultura extends ScraperBase
{
    public string $nombre = 'ChileCultura';
    public string $slug   = 'chilecultura';

    // Posibles IDs de región para probar (el API no usa el número oficial de regiones)
    private array $regionesPosibles = [];

    protected function fetchEventos(): array
    {
        $eventos = [];

        // Configurar regiones según ciudad
        if ($this->regionId === 14) { // Valdivia
            $this->regionesPosibles = [14, 13, 12, 10];
        } else { // Pucón - probar varios IDs
            $this->regionesPosibles = [9, 10, 11, 12, 13, 8];
        }

        // Buscar eventos del Ministerio de las Culturas
        $eventos = $this->buscarChileCultura();

        // También buscar en CONAF (Parques Nacionales)
        $eventosConaf = $this->buscarConaf();
        $eventos = array_merge($eventos, $eventosConaf);

        return $eventos;
    }

    private function buscarChileCultura(): array
    {
        $eventos = [];
        $vistos = [];

        foreach ($this->regionesPosibles as $regionId) {
            $page = 1;
            while ($page <= 2) {
                $url = "https://chilecultura.gob.cl/api/v1.0/eventos/search"
                     . "?region={$regionId}&limite=50&page={$page}";

                $resp = $this->fetchUrl($url);
                if (!$resp) break;

                $data = json_decode($resp, true);
                if (!$data || empty($data['results'])) break;

                foreach ($data['results'] as $ev) {
                    // Saltar guías/librerías
                    $disciplina = $ev['main_discipline'] ?? '';
                    if (str_contains($disciplina, 'Guía') || str_contains($disciplina, 'Librería')) continue;

                    $nombre = $ev['name'] ?? '';
                    if (!$nombre || isset($vistos[$nombre])) continue;
                    $vistos[$nombre] = true;

                    $comuna = $ev['commune'] ?? '';
                    $region = $ev['region'] ?? '';

                    // Para Pucón: aceptar Araucanía o comunas cercanas
                    $textoBusqueda = $comuna . ' ' . $region;
                    $ciudadFiltro = defined('CIUDAD_FILTRO') ? CIUDAD_FILTRO : '';

                    $comunasCercanas = ['Pucón', 'Villarrica', 'Curarrehue', 'Loncoche', 'Freire', 'Pitrufquén', 'Gorbea'];
                    $regionValida = false;
                    foreach ($comunasCercanas as $c) {
                        if (str_contains(mb_strtolower($textoBusqueda, 'UTF-8'), mb_strtolower($c, 'UTF-8'))) {
                            $regionValida = true;
                            break;
                        }
                    }
                    // También aceptar si la región contiene Araucanía
                    if (!$regionValida && str_contains(mb_strtolower($region, 'UTF-8'), 'araucan')) {
                        $regionValida = true;
                    }

                    if (!$regionValida) continue;

                    $fecha    = $ev['start_date'] ?? date('Y-m-d');
                    $fechaFin = $ev['end_date'] ?? $fecha;
                    $lugar    = $ev['venue_name'] ?? '';
                    $imagen   = $ev['image'] ?? '';
                    $desc     = $this->stripHtml($ev['description'] ?? '');
                    $desc     = mb_substr($desc, 0, 500);
                    $hora     = $this->extraerHora($desc);

                    $lugarCompleto = $lugar;
                    if ($comuna && !str_contains($lugar, $comuna)) {
                        $lugarCompleto .= ", $comuna";
                    }
                    if (!$lugarCompleto) $lugarCompleto = $this->ciudad;

                    $eventos[] = [
                        'nombre' => $nombre, 'fecha' => $fecha,
                        'fecha_fin' => $fecha !== $fechaFin ? $fechaFin : '',
                        'hora' => $hora, 'lugar' => $lugarCompleto,
                        'descripcion' => $desc,
                        'contacto' => $disciplina ? "Disciplina: $disciplina" : '',
                        'foto' => $imagen ?? '',
                    ];
                }
                if (!$data['next']) break;
                $page++;
            }
        }
        return $eventos;
    }

    /**
     * Buscar actividades en Parques Nacionales (CONAF)
     */
    private function buscarConaf(): array
    {
        $eventos = [];
        $parques = [];

        if ($this->regionId === 14) { // Valdivia - Parques de Los Ríos
            $parques = [
                'Parque Nacional Alerce Costero',
                'Parque Nacional Puyehue',
                'Reserva Nacional Valdivia',
                'Monumento Natural Alerce Costero',
            ];
        } else { // Pucón - Parques de Araucanía
            $parques = [
                'Parque Nacional Villarrica',
                'Parque Nacional Huerquehue',
                'Parque Nacional Conguillío',
                'Parque Nacional Tolhuaca',
                'Reserva Nacional China Muerta',
                'Monumento Natural Cerro Ñielol',
            ];
        }

        // Buscar en sitemap de CONAF
        $sitemap = $this->fetchUrl('https://www.conaf.cl/sitemap.xml');
        if (!$sitemap) return $eventos;

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sitemap);
        if (!$xml || !isset($xml->sitemap)) return $eventos;

        foreach ($xml->sitemap as $sub) {
            $loc = $this->xmlText($sub->loc);
            if (!str_contains($loc, 'post-sitemap')) continue;

            $subXml = $this->fetchUrl($loc);
            if (!$subXml) continue;

            $subData = simplexml_load_string($subXml);
            if (!$subData || !isset($subData->url)) continue;

            foreach ($subData->url as $urlItem) {
                $url = $this->xmlText($urlItem->loc);
                foreach ($parques as $parque) {
                    $parqueSlug = str_replace(' ', '-', mb_strtolower($parque, 'UTF-8'));
                    if (str_contains(mb_strtolower($url, 'UTF-8'), mb_strtolower($parqueSlug, 'UTF-8'))) {
                        $nombre = "Actividad en $parque";
                        // Evitar duplicados
                        foreach ($eventos as $e) {
                            if ($e['nombre'] === $nombre) continue 2;
                        }
                        $eventos[] = [
                            'nombre' => $nombre,
                            'fecha'  => date('Y-m-d', strtotime('+1 day')),
                            'lugar'  => "$parque, {$this->ciudad}",
                            'descripcion' => 'Ver más en ' . $url,
                            'contacto' => 'CONAF',
                        ];
                    }
                }
            }
        }

        return $eventos;
    }
}

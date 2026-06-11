<?php
/**
 * Scraper: SERNATUR — Región configurada
 * Busca eventos/actividades turísticas para la región de la ciudad configurada.
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperSernatur extends ScraperBase
{
    public string $nombre = 'SERNATUR';
    public string $slug   = 'sernatur';

    protected function fetchEventos(): array
    {
        $eventos = [];
        $slugRegion = '';

        // Mapear región ID a slug de URL
        $regiones = [
            14 => 'los-rios',    // Valdivia
            9  => 'la-araucania', // Pucón
        ];
        $slugRegion = $regiones[$this->regionId] ?? '';

        if (!$slugRegion) return $eventos;

        // Scrapear página de región
        $html = $this->fetchUrl("https://www.sernatur.cl/region/{$slugRegion}/");
        if (!$html) return $eventos;

        // Buscar títulos de secciones destacadas
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/i', $html, $titulos)) {
            foreach ($titulos[1] as $titulo) {
                $tituloLimpio = trim(strip_tags(html_entity_decode($titulo, ENT_QUOTES, 'UTF-8')));
                if (!$tituloLimpio || strlen($tituloLimpio) < 10) continue;

                $eventos[] = [
                    'nombre' => $tituloLimpio,
                    'fecha'  => date('Y-m-d'),
                    'lugar'  => $this->ciudad . ', Chile',
                    'descripcion' => "Actividad turística en {$this->ciudad} — SERNATUR",
                    'contacto' => 'SERNATUR',
                ];
            }
        }

        return $eventos;
    }
}

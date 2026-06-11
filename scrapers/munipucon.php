<?php
/**
 * Scraper: Municipalidad de Pucón
 * 
 * El sitio municipalidadpucon.cl tiene Wordfence bloqueando scraping directo.
 * Se usa Google Cache para obtener el contenido textual.
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperMuniPucon extends ScraperBase
{
    public string $nombre = 'Municipalidad de Pucón';
    public string $slug   = 'munipucon';

    protected function fetchEventos(): array
    {
        $eventos = [];

        // Solo para Pucón
        if ($this->regionId !== 9) return $eventos;

        // Intentar obtener HTML vía Google Cache
        $html = $this->fetchUrl('https://webcache.googleusercontent.com/search?q=cache:municipalidadpucon.cl&strip=1&vwsrc=0');
        if (!$html) {
            $this->log("No se pudo obtener Google Cache de municipalidadpucon.cl");
            return $eventos;
        }

        $this->log("Google Cache obtenido (" . strlen($html) . " bytes)");

        // Buscar títulos de noticias/eventos (WordPress genera h2 con títulos)
        if (preg_match_all('/<h[23][^>]*class="[^"]*(?:entry-title|post-title|title)[^"]*"[^>]*>(.*?)<\/h[23]>/si', $html, $matches)) {
            foreach ($matches[1] as $tituloHtml) {
                $titulo = trim(strip_tags($tituloHtml));
                $titulo = html_entity_decode($titulo, ENT_QUOTES, 'UTF-8');
                if (!$titulo || strlen($titulo) < 5) continue;

                // Filtrar solo eventos
                if (!$this->esEvento($titulo, $titulo)) continue;

                $fecha = $this->extraerFecha($titulo, $html);
                if (!$fecha) $fecha = date('Y-m-d');

                $eventos[] = [
                    'nombre' => $titulo,
                    'fecha'  => $fecha,
                    'lugar'  => 'Pucón',
                    'descripcion' => 'Actividad municipal en Pucón',
                    'contacto' => 'Municipalidad de Pucón',
                ];
            }
        }

        // Buscar también en el HTML plano cualquier texto con fechas + palabras de evento
        if (preg_match_all('/<a[^>]*>(.*?)<\/a>/si', $html, $links)) {
            foreach ($links[1] as $texto) {
                $texto = trim(strip_tags($texto));
                $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
                if (!$texto || strlen($texto) < 10) continue;

                // Buscar patrones como "23 de mayo" + palabra de evento
                if ($this->contieneFecha($texto) && $this->esEvento($texto, $texto)) {
                    $fecha = $this->extraerFecha($texto, '');
                    $eventos[] = [
                        'nombre' => $texto,
                        'fecha'  => $fecha ?: date('Y-m-d'),
                        'lugar'  => 'Pucón',
                        'descripcion' => '',
                        'contacto' => 'Municipalidad de Pucón',
                    ];
                }
            }
        }

        $this->log("Encontrados " . count($eventos) . " eventos potenciales");
        return $eventos;
    }

    private function contieneFecha(string $texto): bool
    {
        return (bool)preg_match('/\d{1,2}\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre)/iu', $texto);
    }

    private function extraerFecha(string $titulo, string $html): ?string
    {
        $meses = ['enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04',
                  'mayo'=>'05','junio'=>'06','julio'=>'07','agosto'=>'08',
                  'septiembre'=>'09','octubre'=>'10','noviembre'=>'11','diciembre'=>'12'];

        // Buscar en el título primero
        if (preg_match('/(\d{1,2})\s+de\s+(' . implode('|', array_keys($meses)) . ')(\s+de\s+(\d{4}))?/iu', $titulo, $m)) {
            $mes = $meses[mb_strtolower($m[2], 'UTF-8')];
            $ano = !empty($m[4]) ? $m[4] : date('Y');
            return "$ano-$mes-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        // Buscar en el HTML time tags de WordPress
        if (preg_match('/<time[^>]*datetime="(\d{4}-\d{2}-\d{2})"/i', $html, $m)) {
            return $m[1];
        }

        return null;
    }
}

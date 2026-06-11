<?php
/**
 * Scraper: Visita Pucón — WordPress RSS con actividades y eventos turísticos
 * Solo para ciudad = Pucón (región 9)
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperVisitaPucon extends ScraperBase
{
    public string $nombre = 'Visita Pucón';
    public string $slug   = 'visitapucon';

    protected function fetchEventos(): array
    {
        $eventos = [];

        // Solo ejecutar para Pucón
        if ($this->regionId !== 9) {
            $this->log("Scraper solo para Pucón, saltando");
            return $eventos;
        }

        $rss = $this->fetchRss('https://visitapucon.cl/feed/');
        if (!$rss || !isset($rss->channel->item)) {
            $this->log("No se pudo obtener RSS de Visita Pucón");
            return $eventos;
        }

        foreach ($rss->channel->item as $item) {
            $titulo    = $this->xmlText($item->title);
            $desc      = $this->xmlText($item->description);
            $contenido = $this->xmlText($item->children('content', true)->encoded);
            $pubDate   = $this->xmlText($item->pubDate);
            $cats      = [];

            if (isset($item->category)) {
                foreach ($item->category as $cat) {
                    $cats[] = mb_strtolower($this->xmlText($cat), 'UTF-8');
                }
            }

            $textoCompleto = $titulo . ' ' . $desc . ' ' . strip_tags($contenido ?? '');

            // Filtrar solo contenido que parezca evento/actividad
            if (!$this->esEvento($titulo, $textoCompleto)) continue;

            $fecha = $this->extraerFechaEvento($textoCompleto);
            if (!$fecha) continue;

            $hora  = $this->extraerHora($textoCompleto);
            $imagen = $this->extraerImagen($contenido ?: $desc);

            $eventos[] = [
                'nombre' => $titulo,
                'fecha'  => $fecha,
                'hora'   => $hora,
                'lugar'  => 'Pucón',
                'descripcion' => $this->stripHtml(mb_substr($desc, 0, 300)),
                'contacto' => 'Visita Pucón',
                'foto'   => $imagen ?? '',
            ];
        }

        return $eventos;
    }

    private function extraerFechaEvento(string $texto): ?string
    {
        $meses = ['enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04',
                  'mayo'=>'05','junio'=>'06','julio'=>'07','agosto'=>'08',
                  'septiembre'=>'09','octubre'=>'10','noviembre'=>'11','diciembre'=>'12'];

        if (preg_match('/(\d{1,2})\s+de\s+(' . implode('|', array_keys($meses)) . ')(\s+de\s+(\d{4}))?/iu', $texto, $m)) {
            $mes = $meses[mb_strtolower($m[2], 'UTF-8')];
            $ano = !empty($m[4]) ? $m[4] : date('Y');
            return "$ano-$mes-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $texto, $m)) return $m[0];
        return null;
    }
}

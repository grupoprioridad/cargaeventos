<?php
/**
 * Scraper: Facebook — Páginas municipales
 * Usa RSS-Bridge instalado localmente
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperFacebook extends ScraperBase
{
    public string $nombre = 'Facebook Municipal';
    public string $slug   = 'facebook_muni';

    protected function fetchEventos(): array
    {
        $eventos = [];
        $paginas = [];

        if ($this->regionId === 14) {
            $paginas = ['munivaldivia'];
        } else {
            $paginas = ['municipalidadpucon'];
        }

        $rssBridge = 'https://j.prioridad.cl/rss-bridge/';

        foreach ($paginas as $pagina) {
            $url = $rssBridge . '?action=display&bridge=FacebookBridge&u=' . urlencode($pagina) . '&format=Atom';
            $feed = $this->fetchUrl($url);

            if (!$feed) {
                $this->log("No se pudo obtener feed de Facebook: $pagina");
                continue;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($feed);
            if (!$xml || !isset($xml->entry)) {
                $this->log("Sin entries en Facebook: $pagina");
                continue;
            }

            $count = 0;
            foreach ($xml->entry as $entry) {
                if ($count >= 10) break;

                $titulo    = (string)$entry->title;
                $content   = (string)$entry->content;
                $link      = (string)$entry->link['href'];
                $published = (string)$entry->published;

                $texto = $titulo . ' ' . strip_tags($content);

                // Solo posts que parezcan eventos
                if (!$this->esEvento($titulo, $texto)) continue;

                $fecha = $this->extraerFecha($texto);
                if (!$fecha) $fecha = date('Y-m-d', strtotime($published));

                $imagen = $this->extraerImagen($content) ?? '';

                $eventos[] = [
                    'nombre' => mb_substr($titulo, 0, 150),
                    'fecha'  => $fecha,
                    'lugar'  => $this->ciudad,
                    'descripcion' => mb_substr($this->stripHtml($content), 0, 300),
                    'contacto' => "Facebook: /$pagina",
                    'foto'   => $imagen,
                ];
                $count++;
            }

            $this->log("Facebook /$pagina: $count eventos encontrados");
        }

        return $eventos;
    }

    private function extraerFecha(string $texto): ?string
    {
        $meses = ['enero'=>'01','febrero'=>'02','marzo'=>'03','abril'=>'04',
                  'mayo'=>'05','junio'=>'06','julio'=>'07','agosto'=>'08',
                  'septiembre'=>'09','octubre'=>'10','noviembre'=>'11','diciembre'=>'12'];

        if (preg_match('/(\d{1,2})\s+de\s+(' . implode('|', array_keys($meses)) . ')(\s+de\s+(\d{4}))?/iu', $texto, $m)) {
            $mes = $meses[mb_strtolower($m[2], 'UTF-8')];
            $ano = !empty($m[4]) ? $m[4] : date('Y');
            return "$ano-$mes-" . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        return null;
    }
}

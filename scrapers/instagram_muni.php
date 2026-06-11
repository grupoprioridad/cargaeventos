<?php
/**
 * Scraper: Instagram — Redes sociales municipales
 * Usa RSS-Bridge instalado localmente para obtener posts
 * 
 * Municipalidades: 
 *   - Valdivia: @munivaldivia
 *   - Pucón: @municipalidadpucon
 */

require_once __DIR__ . '/ScraperBase.php';

class ScraperInstagram extends ScraperBase
{
    public string $nombre = 'Instagram Municipal';
    public string $slug   = 'instagram_muni';

    private array $cuentas = [];

    protected function fetchEventos(): array
    {
        $eventos = [];

        // Configurar cuentas según ciudad
        if ($this->regionId === 14) { // Valdivia
            $this->cuentas = ['munivaldivia'];
        } else { // Pucón
            $this->cuentas = ['municipalidadpucon'];
        }

        $rssBridge = 'https://j.prioridad.cl/rss-bridge/';

        foreach ($this->cuentas as $cuenta) {
            $url = $rssBridge . '?action=display&bridge=InstagramBridge&u=' . urlencode($cuenta) . '&format=Atom';
            $feed = $this->fetchUrl($url);

            if (!$feed) {
                $this->log("No se pudo obtener feed de Instagram: $cuenta");
                continue;
            }

            // Parsear Atom
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($feed);
            if (!$xml) {
                $this->log("Error parseando feed de Instagram: $cuenta");
                continue;
            }

            $count = 0;
            foreach ($xml->entry as $entry) {
                if ($count >= 10) break; // Máximo 10 posts

                $titulo    = (string)$entry->title;
                $link      = (string)$entry->link['href'];
                $published = (string)$entry->published;
                $content   = (string)$entry->content;
                $imagen    = '';

                // Extraer imagen del contenido media:thumbnail o media:content
                $media = $entry->children('media', true);
                if ($media && isset($media->thumbnail)) {
                    $imagen = (string)$media->thumbnail['url'];
                } elseif ($media && isset($media->content)) {
                    $imagen = (string)$media->content['url'];
                }

                // Extraer imagen del contenido HTML
                if (!$imagen) {
                    $imagen = $this->extraerImagen($content) ?? '';
                }

                // Verificar si el post menciona un evento
                $texto = $titulo . ' ' . strip_tags($content);
                if (!$this->esEvento($titulo, $texto)) continue;

                // Extraer fecha del texto del post
                $fecha = $this->extraerFecha($texto);
                if (!$fecha) {
                    // Usar fecha de publicación como referencia
                    $fecha = date('Y-m-d', strtotime($published));
                }

                // Extraer lugar
                $lugar = $this->ciudad;
                if (preg_match('/en\s+(el\s+)?([A-ZÁÉÍÓÚÑ][^.\n]+)/iu', $texto, $mLugar)) {
                    $lugarCandidato = trim($mLugar[2]);
                    if (strlen($lugarCandidato) < 60) {
                        $lugar = "$lugarCandidato, {$this->ciudad}";
                    }
                }

                $eventos[] = [
                    'nombre'      => $this->limpiarTitulo($titulo),
                    'fecha'       => $fecha,
                    'lugar'       => $lugar,
                    'descripcion' => mb_substr($this->stripHtml($texto), 0, 300),
                    'contacto'    => "Instagram: @$cuenta",
                    'foto'        => $imagen,
                ];

                $count++;
            }

            $this->log("Instagram @$cuenta: $count eventos encontrados");
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
        // También intentar DD/MM
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{4}))?/', $texto, $m)) {
            if (!empty($m[3])) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }
        return null;
    }

    private function limpiarTitulo(string $titulo): string
    {
        // Instagram a veces pone íconos (▶, 📅, etc.) al inicio
        $titulo = preg_replace('/^[▶▸➤📅📢🎉🎭🎶📍🏛️🌟✨🔥\s]+/u', '', $titulo);
        // Truncar si es muy largo
        if (mb_strlen($titulo) > 120) {
            $titulo = mb_substr($titulo, 0, 117) . '...';
        }
        return trim($titulo);
    }
}

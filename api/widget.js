/**
 * Widget de Eventos — Agenda Ciudad
 * 
 * Uso en lavozdepucon.cl o pucon.online:
 *   <div id="agenda-widget" data-ciudad="pucon" data-limite="5"></div>
 *   <script src="https://j.prioridad.cl/cargaeventos/api/widget.js"></script>
 * 
 * Para Valdivia:
 *   <div id="agenda-widget" data-ciudad="valdivia" data-limite="5"></div>
 */

(function() {
    'use strict';

    const API_BASE = (function() {
        var s = document.currentScript || document.scripts[document.scripts.length - 1];
        return s.src.substring(0, s.src.lastIndexOf('/')) + '/../api/eventos.php';
    })();

    // Buscar contenedor
    var container = document.getElementById('agenda-widget');
    if (!container) {
        container = document.createElement('div');
        container.id = 'agenda-widget';
        document.body.appendChild(container);
    }

    var ciudad = container.getAttribute('data-ciudad') || 'pucon';
    var limite = parseInt(container.getAttribute('data-limite')) || 5;
    var titulo = container.getAttribute('data-titulo') || '📅 Próximos eventos';

    // Estilos
    var styles = document.createElement('style');
    styles.textContent = `
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        .agenda-widget {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            max-width: 100%;
            border-radius: 16px;
            overflow: hidden;
        }

        .agenda-widget-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 20px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .agenda-widget-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .agenda-widget-item {
            display: flex;
            gap: 14px;
            padding: 14px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            transition: background 0.2s;
            cursor: default;
        }

        .agenda-widget-item:hover {
            background: rgba(0,0,0,0.02);
        }

        .agenda-widget-date {
            flex-shrink: 0;
            width: 50px;
            text-align: center;
        }

        .agenda-widget-date .day {
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
            display: block;
        }

        .agenda-widget-date .month {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-top: 2px;
        }

        .agenda-widget-info {
            flex: 1;
            min-width: 0;
        }

        .agenda-widget-info .title {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.4;
            margin: 0 0 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .agenda-widget-info .meta {
            font-size: 12px;
            opacity: 0.6;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .agenda-widget-info .meta span {
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .agenda-widget-empty {
            padding: 40px 20px;
            text-align: center;
            opacity: 0.5;
            font-size: 14px;
        }

        .agenda-widget-footer {
            padding: 12px 20px;
            text-align: center;
            font-size: 12px;
            opacity: 0.5;
        }

        .agenda-widget-footer a {
            text-decoration: none;
            font-weight: 500;
        }

        /* Temas */
        .agenda-widget.theme-pucon {
            background: #faf8f5;
            border: 1px solid rgba(0,0,0,0.06);
        }
        .agenda-widget.theme-pucon .agenda-widget-header {
            background: linear-gradient(135deg, #ea580c, #f97316);
            color: #fff;
        }
        .agenda-widget.theme-pucon .agenda-widget-date .day { color: #ea580c; }
        .agenda-widget.theme-pucon .agenda-widget-date .month { color: #ea580c; }
        .agenda-widget.theme-pucon .agenda-widget-footer a { color: #ea580c; }

        .agenda-widget.theme-valdivia {
            background: #f0f7fc;
            border: 1px solid rgba(0,0,0,0.06);
        }
        .agenda-widget.theme-valdivia .agenda-widget-header {
            background: linear-gradient(135deg, #0d47a1, #1565C0);
            color: #fff;
        }
        .agenda-widget.theme-valdivia .agenda-widget-date .day { color: #1565C0; }
        .agenda-widget.theme-valdivia .agenda-widget-date .month { color: #1565C0; }
        .agenda-widget.theme-valdivia .agenda-widget-footer a { color: #1565C0; }

        .agenda-widget.theme-light {
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .agenda-widget.theme-light .agenda-widget-header {
            background: #f9fafb;
            color: #111827;
            border-bottom: 1px solid #e5e7eb;
        }
        .agenda-widget.theme-light .agenda-widget-date .day { color: #374151; }
        .agenda-widget.theme-light .agenda-widget-date .month { color: #6b7280; }
        .agenda-widget.theme-light .agenda-widget-footer a { color: #374151; }
    `;
    document.head.appendChild(styles);

    // Meses abreviados
    var meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

    // Render
    function render(eventos) {
        var theme = ciudad === 'valdivia' ? 'valdivia' : (ciudad === 'pucon' ? 'pucon' : 'light');

        var html = '<div class="agenda-widget theme-' + theme + '">';
        html += '<div class="agenda-widget-header">' + titulo + '</div>';

        if (!eventos || eventos.length === 0) {
            html += '<div class="agenda-widget-empty">✨ No hay eventos próximos</div>';
        } else {
            html += '<ul class="agenda-widget-list">';
            for (var i = 0; i < eventos.length; i++) {
                var ev = eventos[i];
                var fecha = new Date(ev.fecha + 'T12:00:00');
                var dia = fecha.getDate();
                var mes = meses[fecha.getMonth()];
                var hora = ev.hora_fmt ? '🕐 ' + ev.hora_fmt : '';
                var lugar = ev.lugar ? '📍 ' + ev.lugar : '';

                html += '<li class="agenda-widget-item">';
                html += '<div class="agenda-widget-date">';
                html += '<span class="day">' + dia + '</span>';
                html += '<span class="month">' + mes + '</span>';
                html += '</div>';
                html += '<div class="agenda-widget-info">';
                html += '<p class="title">' + escapeHtml(ev.nombre) + '</p>';
                html += '<div class="meta">';
                if (hora) html += '<span>' + hora + '</span>';
                if (lugar) html += '<span>' + lugar + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</li>';
            }
            html += '</ul>';
        }

        var urls = {
            pucon: 'https://www.agendapucon.cl',
            valdivia: 'https://agendavaldivia.j.prioridad.cl'
        };
        var url = urls[ciudad] || urls.pucon;
        html += '<div class="agenda-widget-footer">';
        html += '🇨🇱 <a href="' + url + '" target="_blank" rel="noopener">Agenda ' + capitalizar(ciudad) + '</a>';
        html += '</div>';
        html += '</div>';

        container.innerHTML = html;
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function capitalizar(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Fetch eventos
    var xhr = new XMLHttpRequest();
    xhr.open('GET', API_BASE + '?ciudad=' + ciudad + '&limite=' + limite, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                render(data.eventos || []);
            } catch(e) {
                render([]);
            }
        } else {
            render([]);
        }
    };
    xhr.onerror = function() { render([]); };
    xhr.send();
})();

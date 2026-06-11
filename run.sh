#!/bin/bash
# Carga de Eventos — Ejecución nocturna para todas las ciudades
# Se ejecuta desde cron

cd /var/www/cargaeventos/scrapers

echo "=== CARGA DE EVENTOS $(date) ==="

echo "--- Valdivia ---"
php orchestrator.php --city=valdivia

echo ""
echo "--- Pucón ---"
php orchestrator.php --city=pucon

echo "=== FIN $(date) ==="

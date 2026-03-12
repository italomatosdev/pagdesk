#!/bin/sh
# =============================================================================
# SCHEDULER - Loop do Cron do Laravel
# PagDesk - Executa schedule:run a cada minuto
# =============================================================================

set -e

echo "=========================================="
echo "  PagDesk - Scheduler iniciado"
echo "  Executando a cada 60 segundos..."
echo "=========================================="

# Aguardar app estar pronto
sleep 10

# Loop infinito
while true; do
    # Timestamp atual
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] Executando schedule:run..."
    
    # Executar scheduler do Laravel
    php /var/www/html/artisan schedule:run --verbose --no-interaction
    
    # Aguardar até o próximo minuto
    # Calcula segundos restantes até o próximo minuto para sincronizar
    current_second=$(date +%S)
    sleep_time=$((60 - ${current_second#0}))
    
    if [ $sleep_time -gt 0 ] && [ $sleep_time -le 60 ]; then
        sleep $sleep_time
    else
        sleep 60
    fi
done

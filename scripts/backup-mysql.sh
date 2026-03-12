#!/bin/bash
# =============================================================================
# Backup MySQL - PagDesk
# 
# Executa backup diário do banco de dados e envia para storage externo.
# 
# Instalação:
#   1. Copiar para /opt/scripts/backup-mysql.sh no servidor MySQL
#   2. chmod +x /opt/scripts/backup-mysql.sh
#   3. Configurar variáveis abaixo
#   4. Adicionar ao cron: 0 3 * * * /opt/scripts/backup-mysql.sh
#
# Dependências:
#   - mysqldump
#   - gzip
#   - aws cli (opcional, para S3)
#
# =============================================================================

set -e

# -----------------------------------------------------------------------------
# CONFIGURAÇÕES (ajustar conforme ambiente)
# -----------------------------------------------------------------------------
DATE=$(date +%Y-%m-%d_%H%M)
BACKUP_DIR="/backups/mysql"
LOG_FILE="/var/log/backup-mysql.log"
RETENTION_DAYS=30

# Banco de dados
DB_NAME="pagdesk_production"
DB_USER="backup_user"
DB_PASS=""  # Ou usar arquivo ~/.my.cnf

# Storage externo (S3)
S3_ENABLED=false
S3_BUCKET="s3://pagdesk-backups/mysql"

# Notificação (opcional)
NOTIFY_EMAIL=""
NOTIFY_ON_ERROR=true

# -----------------------------------------------------------------------------
# FUNÇÕES
# -----------------------------------------------------------------------------
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

error() {
    log "ERRO: $1"
    if [ "$NOTIFY_ON_ERROR" = true ] && [ -n "$NOTIFY_EMAIL" ]; then
        echo "Falha no backup MySQL: $1" | mail -s "[ALERTA] Backup MySQL falhou" "$NOTIFY_EMAIL"
    fi
    exit 1
}

check_disk_space() {
    local available=$(df "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    local required=5242880  # 5GB em KB
    
    if [ "$available" -lt "$required" ]; then
        error "Espaço em disco insuficiente. Disponível: ${available}KB, Necessário: ${required}KB"
    fi
}

# -----------------------------------------------------------------------------
# INÍCIO
# -----------------------------------------------------------------------------
log "=========================================="
log "Iniciando backup MySQL - $DB_NAME"
log "=========================================="

# Criar diretório se não existir
mkdir -p "$BACKUP_DIR"

# Verificar espaço em disco
check_disk_space

# Nome do arquivo
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

# -----------------------------------------------------------------------------
# EXECUTAR MYSQLDUMP
# -----------------------------------------------------------------------------
log "Executando mysqldump..."

if [ -n "$DB_PASS" ]; then
    MYSQL_PWD="$DB_PASS" mysqldump \
        -u"$DB_USER" \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --quick \
        --lock-tables=false \
        "$DB_NAME" 2>> "$LOG_FILE" | gzip > "$BACKUP_FILE"
else
    # Usar ~/.my.cnf para credenciais
    mysqldump \
        --single-transaction \
        --routines \
        --triggers \
        --events \
        --quick \
        --lock-tables=false \
        "$DB_NAME" 2>> "$LOG_FILE" | gzip > "$BACKUP_FILE"
fi

if [ $? -ne 0 ]; then
    error "Falha no mysqldump"
fi

# Verificar se arquivo foi criado e não está vazio
if [ ! -f "$BACKUP_FILE" ]; then
    error "Arquivo de backup não foi criado"
fi

SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')
SIZE_BYTES=$(stat -f%z "$BACKUP_FILE" 2>/dev/null || stat -c%s "$BACKUP_FILE" 2>/dev/null)

if [ "$SIZE_BYTES" -lt 1000 ]; then
    error "Arquivo de backup muito pequeno: $SIZE_BYTES bytes"
fi

log "Backup criado: $BACKUP_FILE ($SIZE)"

# -----------------------------------------------------------------------------
# VERIFICAR INTEGRIDADE
# -----------------------------------------------------------------------------
log "Verificando integridade do arquivo..."
if ! gzip -t "$BACKUP_FILE" 2>/dev/null; then
    error "Arquivo de backup corrompido"
fi
log "Integridade verificada: OK"

# -----------------------------------------------------------------------------
# ENVIAR PARA S3 (se habilitado)
# -----------------------------------------------------------------------------
if [ "$S3_ENABLED" = true ]; then
    if command -v aws &> /dev/null; then
        log "Enviando para S3: $S3_BUCKET/"
        if aws s3 cp "$BACKUP_FILE" "$S3_BUCKET/" --quiet; then
            log "Upload para S3 concluído"
        else
            log "AVISO: Falha no upload para S3"
        fi
    else
        log "AVISO: AWS CLI não instalado, pulando upload para S3"
    fi
fi

# -----------------------------------------------------------------------------
# LIMPEZA DE BACKUPS ANTIGOS
# -----------------------------------------------------------------------------
log "Limpando backups antigos (>${RETENTION_DAYS} dias)..."

# Local
DELETED_LOCAL=$(find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
log "Removidos $DELETED_LOCAL backups locais antigos"

# S3 (se habilitado)
if [ "$S3_ENABLED" = true ] && command -v aws &> /dev/null; then
    log "Limpando backups antigos no S3..."
    # Implementar limpeza no S3 se necessário
fi

# -----------------------------------------------------------------------------
# RESUMO
# -----------------------------------------------------------------------------
TOTAL_BACKUPS=$(ls -1 "$BACKUP_DIR"/*.sql.gz 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | awk '{print $1}')

log "=========================================="
log "Backup concluído com sucesso!"
log "Arquivo: $BACKUP_FILE"
log "Tamanho: $SIZE"
log "Total de backups: $TOTAL_BACKUPS"
log "Espaço usado: $TOTAL_SIZE"
log "=========================================="

exit 0

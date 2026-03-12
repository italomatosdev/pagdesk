# Backup e Recuperação - PagDesk

Política de backup, procedimentos de restore e plano de recuperação de desastres.

## Índice

1. [Visão Geral](#1-visão-geral)
2. [Política de Backup](#2-política-de-backup)
3. [Backup do MySQL](#3-backup-do-mysql)
4. [Backup de Arquivos](#4-backup-de-arquivos)
5. [Snapshots de VPS](#5-snapshots-de-vps)
6. [Restore](#6-restore)
7. [Teste de Restore](#7-teste-de-restore)
8. [Recuperação de Desastres](#8-recuperação-de-desastres)

---

## 1. Visão Geral

### O que é backupeado

| Componente | Método | Frequência | Retenção |
|------------|--------|------------|----------|
| MySQL (dados) | mysqldump | Diário | 30 dias |
| MySQL (binlog) | Rotação | Contínuo | 7 dias |
| Storage Laravel | rsync/tar | Diário | 14 dias |
| VPS App | Snapshot | Semanal | 4 semanas |
| VPS MySQL | Snapshot | Semanal | 4 semanas |

### Destinos de Backup

| Tipo | Destino Primário | Destino Secundário |
|------|------------------|-------------------|
| Dumps MySQL | Storage externo (S3) | Disco local /backups |
| Arquivos | Storage externo (S3) | - |
| Snapshots | Provedor VPS | - |

---

## 2. Política de Backup

### 2.1 Horários

| Backup | Horário | Janela |
|--------|---------|--------|
| MySQL dump | 03:00 UTC-3 | ~30 min |
| Arquivos storage | 04:00 UTC-3 | ~15 min |
| Snapshot semanal | Domingo 05:00 | ~1h |

### 2.2 Retenção

```
Backups diários: 30 dias
Backups semanais: 4 semanas (via snapshots)
Backups mensais: 12 meses (1º de cada mês)
```

### 2.3 Verificação

| Verificação | Frequência |
|-------------|------------|
| Backup completou | Diário (automático) |
| Integridade do arquivo | Semanal |
| Teste de restore | Mensal |

---

## 3. Backup do MySQL

### 3.1 Script de Backup

**Localização:** `/opt/scripts/backup-mysql.sh` (servidor MySQL)

```bash
#!/bin/bash
# =============================================================================
# Backup MySQL - PagDesk
# Executa backup diário e envia para storage externo
# =============================================================================

set -e

# Configurações
DATE=$(date +%Y-%m-%d_%H%M)
BACKUP_DIR="/backups/mysql"
LOG_FILE="/var/log/backup-mysql.log"
RETENTION_DAYS=30
DB_NAME="pagdesk_production"
DB_USER="backup_user"
DB_PASS="<senha-do-backup>"
S3_BUCKET="s3://pagdesk-backups/mysql"

# Funções
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Início
log "========== Iniciando backup =========="

# Criar diretório se não existir
mkdir -p "$BACKUP_DIR"

# Nome do arquivo
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz"

# Executar mysqldump
log "Executando mysqldump..."
mysqldump \
    -u"$DB_USER" \
    -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --quick \
    --lock-tables=false \
    "$DB_NAME" | gzip > "$BACKUP_FILE"

# Verificar tamanho
SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')
log "Backup criado: $BACKUP_FILE ($SIZE)"

# Enviar para S3 (se configurado)
if command -v aws &> /dev/null; then
    log "Enviando para S3..."
    aws s3 cp "$BACKUP_FILE" "$S3_BUCKET/" --quiet
    log "Upload concluído"
fi

# Limpar backups antigos locais
log "Limpando backups antigos (>${RETENTION_DAYS} dias)..."
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Limpar backups antigos no S3
if command -v aws &> /dev/null; then
    log "Limpando backups antigos no S3..."
    aws s3 ls "$S3_BUCKET/" | while read -r line; do
        FILE_DATE=$(echo "$line" | awk '{print $1}')
        FILE_NAME=$(echo "$line" | awk '{print $4}')
        if [[ $(date -d "$FILE_DATE" +%s) -lt $(date -d "-${RETENTION_DAYS} days" +%s) ]]; then
            aws s3 rm "$S3_BUCKET/$FILE_NAME"
        fi
    done
fi

log "========== Backup concluído =========="
```

### 3.2 Instalação do Script

```bash
# Criar diretórios
sudo mkdir -p /opt/scripts
sudo mkdir -p /backups/mysql
sudo mkdir -p /var/log

# Copiar script
sudo cp backup-mysql.sh /opt/scripts/
sudo chmod +x /opt/scripts/backup-mysql.sh

# Criar usuário de backup no MySQL
mysql -u root -p
```

```sql
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY '<senha-forte>';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES, EVENT ON pagdesk_production.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3.3 Configurar Cron

```bash
# Editar crontab
sudo crontab -e

# Adicionar linha
0 3 * * * /opt/scripts/backup-mysql.sh >> /var/log/backup-mysql.log 2>&1
```

### 3.4 Configurar AWS CLI (para S3)

```bash
# Instalar AWS CLI
sudo apt install awscli -y

# Configurar credenciais
aws configure
# AWS Access Key ID: <sua-key>
# AWS Secret Access Key: <sua-secret>
# Default region: sa-east-1
# Default output format: json
```

---

## 4. Backup de Arquivos

### 4.1 O que backupear

```
/var/www/html/storage/app/       # Uploads de usuários
/var/www/html/storage/logs/      # Logs (opcional)
/opt/pagdesk/.env                # Configurações (importante!)
```

### 4.2 Script de Backup de Arquivos

```bash
#!/bin/bash
# backup-files.sh

DATE=$(date +%Y-%m-%d)
BACKUP_DIR="/backups/files"
APP_DIR="/var/www/html"
S3_BUCKET="s3://pagdesk-backups/files"

mkdir -p "$BACKUP_DIR"

# Backup do storage
tar -czf "$BACKUP_DIR/storage_$DATE.tar.gz" -C "$APP_DIR" storage/app

# Backup do .env
cp /opt/pagdesk/.env "$BACKUP_DIR/env_$DATE.backup"

# Upload para S3
aws s3 sync "$BACKUP_DIR/" "$S3_BUCKET/"

# Limpar antigos
find "$BACKUP_DIR" -mtime +14 -delete
```

---

## 5. Snapshots de VPS

### 5.1 Quando Usar

- Antes de atualizações grandes
- Semanalmente (automático)
- Antes de mudanças de infraestrutura

### 5.2 Como Criar (Linode)

```bash
# Via CLI
linode-cli images create --disk_id <disk_id> --label "pagdesk-app-$(date +%Y%m%d)"

# Ou via Linode Cloud Manager:
# 1. Acessar painel
# 2. Selecionar Linode
# 3. Backups > Take Snapshot Now
```

### 5.3 Como Criar (DigitalOcean)

```bash
# Via doctl
doctl compute droplet-action snapshot <droplet_id> --snapshot-name "pagdesk-$(date +%Y%m%d)"
```

---

## 6. Restore

### 6.1 Restore MySQL

**1. Identificar backup:**
```bash
# Listar backups locais
ls -la /backups/mysql/

# Listar backups no S3
aws s3 ls s3://pagdesk-backups/mysql/
```

**2. Baixar backup (se necessário):**
```bash
aws s3 cp s3://pagdesk-backups/mysql/pagdesk_production_2026-01-24_0300.sql.gz ./
```

**3. Descompactar:**
```bash
gunzip pagdesk_production_2026-01-24_0300.sql.gz
```

**4. Parar aplicação (opcional, recomendado):**
```bash
# No servidor de app
docker compose stop app queue scheduler
```

**5. Restaurar:**
```bash
mysql -u root -p pagdesk_production < pagdesk_production_2026-01-24_0300.sql
```

**6. Verificar:**
```bash
mysql -u root -p -e "SELECT COUNT(*) FROM pagdesk_production.emprestimos;"
```

**7. Reiniciar aplicação:**
```bash
docker compose start app queue scheduler
```

### 6.2 Restore de Arquivos

```bash
# Baixar backup
aws s3 cp s3://pagdesk-backups/files/storage_2026-01-24.tar.gz ./

# Extrair
tar -xzf storage_2026-01-24.tar.gz -C /var/www/html/

# Ajustar permissões
chown -R www-data:www-data /var/www/html/storage
```

### 6.3 Restore de Snapshot

**Linode:**
1. Criar novo Linode a partir do snapshot
2. Ou restaurar snapshot no Linode existente (via Rescue Mode)

**DigitalOcean:**
1. Criar Droplet a partir do snapshot
2. Ou restaurar via painel

---

## 7. Teste de Restore

### 7.1 Por que testar?

- Garantir que backups estão funcionando
- Validar procedimentos documentados
- Treinar equipe
- Identificar problemas antes de uma emergência real

### 7.2 Procedimento de Teste Mensal

**1. Provisionar ambiente de teste:**
```bash
# Criar VPS temporária ou usar ambiente de staging
```

**2. Restaurar último backup:**
```bash
# Seguir procedimento de restore
```

**3. Verificar integridade:**
```bash
# Queries de verificação
mysql -e "SELECT COUNT(*) FROM users;"
mysql -e "SELECT COUNT(*) FROM emprestimos;"
mysql -e "SELECT COUNT(*) FROM parcelas;"
mysql -e "SELECT SUM(valor) FROM pagamentos;"
```

**4. Testar aplicação:**
```bash
# Subir aplicação apontando para banco restaurado
# Fazer login
# Navegar em telas principais
# Verificar dados
```

**5. Documentar resultado:**
```
Data do teste: YYYY-MM-DD
Backup utilizado: pagdesk_production_YYYY-MM-DD.sql.gz
Tempo de restore: XX minutos
Resultado: [ ] Sucesso / [ ] Falha
Observações: ...
Responsável: Nome
```

**6. Destruir ambiente de teste:**
```bash
# Remover VPS temporária
# Limpar dados de teste
```

### 7.3 Checklist de Teste

- [ ] Backup baixado com sucesso
- [ ] Arquivo descompactado sem erros
- [ ] MySQL restaurado sem erros
- [ ] Contagem de registros confere
- [ ] Aplicação inicia
- [ ] Login funciona
- [ ] Dados aparecem corretamente
- [ ] Ambiente de teste destruído

---

## 8. Recuperação de Desastres

### 8.1 Cenários de Desastre

| Cenário | Impacto | RTO | RPO |
|---------|---------|-----|-----|
| Servidor App indisponível | Total | 1h | 0 |
| MySQL primário falhou | Total | 15min | ~segundos |
| Dados corrompidos | Total | 2h | até 24h |
| Datacenter indisponível | Total | 4h | até 24h |
| Deleção acidental | Parcial | 1h | até 24h |

> **RTO** = Recovery Time Objective (tempo máximo para restaurar)
> **RPO** = Recovery Point Objective (máximo de dados que podem ser perdidos)

### 8.2 Procedimento de Recuperação

#### Cenário: MySQL Primário Indisponível

**Tempo estimado:** 15 minutos

1. **Confirmar falha** (2 min)
   ```bash
   mysql -h 10.0.0.20 -u pagdesk_app -p -e "SELECT 1"
   # Se falhar, continuar
   ```

2. **Verificar réplica** (2 min)
   ```bash
   mysql -h 10.0.0.21 -u root -p -e "SHOW REPLICA STATUS\G"
   ```

3. **Promover réplica** (3 min)
   ```sql
   -- Na réplica
   STOP REPLICA;
   RESET REPLICA ALL;
   SET GLOBAL read_only = OFF;
   ```

4. **Atualizar aplicação** (5 min)
   ```bash
   # Editar .env no servidor de app
   DB_HOST=10.0.0.21
   
   # Reiniciar
   docker compose restart app queue scheduler
   ```

5. **Verificar** (3 min)
   - Acessar aplicação
   - Verificar logs
   - Testar operações

---

#### Cenário: Dados Corrompidos/Deletados

**Tempo estimado:** 2 horas

1. **Parar aplicação**
   ```bash
   docker compose stop app queue scheduler nginx
   ```

2. **Identificar backup anterior ao problema**
   ```bash
   aws s3 ls s3://pagdesk-backups/mysql/
   ```

3. **Restaurar backup**
   ```bash
   # Seguir procedimento de restore
   ```

4. **Verificar dados**
   ```bash
   # Queries de verificação
   ```

5. **Reiniciar aplicação**
   ```bash
   docker compose start app queue scheduler nginx
   ```

6. **Comunicar usuários sobre possível perda de dados**

---

#### Cenário: Servidor App Destruído

**Tempo estimado:** 1-2 horas

1. **Provisionar nova VPS**
   - Mesmo tamanho ou maior
   - Mesma região (rede privada)

2. **Restaurar snapshot** (se disponível)
   - Ou instalar Docker e clonar repositório

3. **Configurar rede**
   - IP privado na mesma faixa
   - Firewall

4. **Atualizar DNS** (se IP público mudou)

5. **Deploy da aplicação**
   ```bash
   git clone ...
   cp .env.backup .env
   docker compose up -d
   ```

6. **Verificar conexão com MySQL**

7. **Verificar funcionamento**

---

### 8.3 Contatos de Emergência

| Função | Nome | Telefone | E-mail |
|--------|------|----------|--------|
| Responsável técnico | - | - | - |
| Backup | - | - | - |
| Provedor VPS | - | - | suporte@... |
| Provedor DNS | - | - | - |

### 8.4 Runbook de Emergência

**Ao identificar um incidente:**

1. [ ] Avaliar impacto e severidade
2. [ ] Notificar equipe (Slack/WhatsApp)
3. [ ] Iniciar procedimento de recuperação
4. [ ] Documentar ações tomadas
5. [ ] Comunicar stakeholders se necessário
6. [ ] Após recuperação, fazer post-mortem

---

## Monitoramento de Backups

### Alertas Recomendados

| Alerta | Condição |
|--------|----------|
| Backup não executou | Arquivo do dia não existe às 04:00 |
| Backup muito pequeno | Tamanho < 50% da média |
| Falha no upload S3 | Erro no log |
| Replicação parada | `Seconds_Behind_Source > 300` |

### Verificação Manual

```bash
# Último backup
ls -la /backups/mysql/ | tail -5

# Tamanho dos backups
du -sh /backups/mysql/*

# Logs de backup
tail -50 /var/log/backup-mysql.log

# Status da replicação
mysql -h replica -e "SHOW REPLICA STATUS\G" | grep -E "Running|Behind"
```

---

*Última atualização: Janeiro 2026*

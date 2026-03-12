# Checklist de Produção - PagDesk

Lista de verificação antes de ir para produção.

---

## 1. Aplicação Laravel

### Configurações Essenciais

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` gerada e única (`php artisan key:generate`)
- [ ] `APP_URL` com domínio correto (https://...)
- [ ] `LOG_LEVEL=warning` ou `error`
- [ ] `DEBUGBAR_ENABLED=false`

### Performance

- [ ] `php artisan config:cache` executado
- [ ] `php artisan route:cache` executado
- [ ] `php artisan view:cache` executado
- [ ] `php artisan optimize` executado
- [ ] Migrations executadas (`php artisan migrate --force`)

### Storage e Permissões

- [ ] Diretório `storage/` com permissões corretas (755)
- [ ] Diretório `bootstrap/cache/` com permissões corretas (755)
- [ ] Dono dos arquivos: `www-data:www-data`
- [ ] Link simbólico criado (`php artisan storage:link`)

---

## 2. Banco de Dados

### MySQL Primário

- [ ] Servidor MySQL provisionado
- [ ] Usuário específico criado (não usar root)
- [ ] Senha forte (mínimo 16 caracteres)
- [ ] Firewall configurado (apenas IPs autorizados)
- [ ] Bind-address configurado (IP privado)
- [ ] Charset utf8mb4 configurado

### MySQL Réplica

- [ ] Servidor réplica provisionado
- [ ] Replicação configurada
- [ ] `SHOW REPLICA STATUS` mostrando `Replica_IO_Running: Yes`
- [ ] `SHOW REPLICA STATUS` mostrando `Replica_SQL_Running: Yes`
- [ ] `Seconds_Behind_Source` próximo de 0

### Conectividade

- [ ] Aplicação conecta no MySQL (testar com `php artisan tinker`)
- [ ] Query `SELECT 1` executa com sucesso
- [ ] Migrations executam sem erros

### Migrations (Antes de cada Deploy)

- [ ] Migrations revisadas por outro desenvolvedor
- [ ] Migrations testadas em staging com dados similares
- [ ] Backup de produção executado antes do deploy
- [ ] Migrations não contêm operações destrutivas sem análise
- [ ] Plano de contingência definido (voltar versão da aplicação)
- [ ] **Nunca** usar `migrate:rollback` automaticamente
- [ ] Correções serão feitas com **novas migrations** (forward-only)

---

## 3. Redis

- [ ] Container Redis rodando
- [ ] `REDIS_HOST=redis` configurado
- [ ] `ping` responde `PONG`
- [ ] Cache funcionando (testar com `Cache::put/get`)
- [ ] Sessões funcionando
- [ ] Filas funcionando

---

## 4. Docker e Containers

### Containers Obrigatórios

- [ ] `pagdesk-app` rodando e healthy
- [ ] `pagdesk-nginx` rodando e healthy
- [ ] `pagdesk-queue` rodando e healthy
- [ ] `pagdesk-scheduler` rodando e healthy
- [ ] `pagdesk-redis` rodando e healthy

### Containers de Monitoramento

- [ ] `pagdesk-prometheus` rodando
- [ ] `pagdesk-grafana` rodando
- [ ] `pagdesk-node-exporter` rodando
- [ ] `pagdesk-cadvisor` rodando

### Configurações

- [ ] Docker Compose usando `docker-compose.prod.yml`
- [ ] Imagens com tag específica (não `latest` em produção crítica)
- [ ] Restart policy configurado (`unless-stopped` ou `always`)
- [ ] Healthchecks configurados e passando
- [ ] Logs não ocupando disco (configurar rotação)

---

## 5. Queue e Scheduler

### Queue Worker

- [ ] Worker processando jobs
- [ ] Comando: `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- [ ] Jobs falhados sendo registrados
- [ ] Tabela `failed_jobs` existe

### Scheduler

- [ ] Container scheduler rodando
- [ ] `php artisan schedule:run` executando a cada minuto
- [ ] Tarefas agendadas registrando execução
- [ ] Marcação de parcelas atrasadas funcionando

---

## 6. Segurança

### Acesso SSH

- [ ] Acesso apenas por chave SSH
- [ ] `PasswordAuthentication no` em sshd_config
- [ ] `PermitRootLogin no` em sshd_config
- [ ] Porta SSH alterada (opcional, mas recomendado)

### Firewall (UFW)

- [ ] UFW habilitado
- [ ] Apenas portas necessárias abertas (22, 80, 443)
- [ ] MySQL não exposto publicamente
- [ ] Redis não exposto publicamente
- [ ] Prometheus não exposto publicamente

### HTTPS

- [ ] Certificado SSL instalado e válido
- [ ] Redirecionamento HTTP → HTTPS configurado
- [ ] `APP_URL` usando https://
- [ ] Cookies marcados como secure

### Credenciais

- [ ] Senhas fortes em todas as contas
- [ ] `.env` fora do repositório git
- [ ] Secrets configurados no GitHub Actions
- [ ] Grafana com senha alterada (não admin/admin)
- [ ] MySQL com senha forte
- [ ] Nenhuma credencial hardcoded no código

---

## 7. Monitoramento

### Prometheus

- [ ] Coletando métricas de node-exporter
- [ ] Coletando métricas de cadvisor
- [ ] Não exposto publicamente (apenas localhost)

### Grafana

- [ ] Acessível via URL definida
- [ ] Senha admin alterada
- [ ] Datasource Prometheus configurado
- [ ] Dashboards de sistema importados
- [ ] Dashboard de aplicação importado

### Health Checks

- [ ] `GET /health` retorna 200 quando saudável
- [ ] `GET /health/live` retorna 200 (verificação básica)
- [ ] `GET /health/ready` retorna 200 (verificação de prontidão)
- [ ] Verifica banco de dados (latência < 100ms)
- [ ] Verifica Redis/cache funcionando
- [ ] Retorna informações de queue (size, failed)
- [ ] Retorna informações de scheduler (last_run, status)
- [ ] CI/CD valida /health após deploy

### Alertas (Configurar conforme necessidade)

- [ ] Alerta para app indisponível
- [ ] Alerta para fila acumulando
- [ ] Alerta para jobs falhando
- [ ] Alerta para container reiniciando
- [ ] Alerta para CPU/RAM alta
- [ ] Alerta para disco cheio
- [ ] Canal de notificação definido (email, Slack, etc)

---

## 8. Backup

### MySQL

- [ ] Script de backup configurado (`/opt/scripts/backup-mysql.sh`)
- [ ] Cron configurado (diário às 3h)
- [ ] Backup executando com sucesso
- [ ] Arquivo de backup sendo gerado
- [ ] Upload para storage externo funcionando (S3)
- [ ] Retenção configurada (30 dias)

### Arquivos

- [ ] Backup do storage configurado (se aplicável)
- [ ] `.env` backup seguro

### Restore

- [ ] Procedimento de restore documentado
- [ ] Restore testado com sucesso em ambiente isolado
- [ ] Tempo de restore conhecido

### Snapshots

- [ ] Snapshot do servidor de app (semanal)
- [ ] Snapshot do servidor MySQL (semanal)
- [ ] Retenção de snapshots configurada

---

## 9. Documentação

- [ ] `INFRAESTRUTURA.md` atualizado
- [ ] `BACKUP.md` atualizado
- [ ] `OBSERVABILIDADE.md` consultado
- [ ] Procedimento de deploy documentado
- [ ] Procedimento de rollback de **aplicação** documentado
- [ ] Política de migrations entendida (forward-only)
- [ ] Contatos de emergência definidos
- [ ] Runbook de operações criado

---

## 10. Testes Finais

### Funcionalidade

- [ ] Login funciona
- [ ] Criação de empréstimo funciona
- [ ] Registro de pagamento funciona
- [ ] Relatórios funcionam
- [ ] Envio de e-mail funciona (se configurado)

### Performance

- [ ] Tempo de resposta aceitável (< 2s)
- [ ] Sem erros 500 nas rotas principais
- [ ] Logs não mostram erros críticos

### Load Test (Opcional)

- [ ] Teste de carga realizado
- [ ] Sistema aguenta X requisições simultâneas
- [ ] Sem degradação sob carga esperada

---

## Aprovação Final

| Item | Responsável | Data | Status |
|------|-------------|------|--------|
| Infraestrutura | | | [ ] |
| Segurança | | | [ ] |
| Backup | | | [ ] |
| Monitoramento | | | [ ] |
| Testes | | | [ ] |

---

**Data do checklist:** ___/___/______

**Responsável pelo deploy:** _______________________

**Aprovado por:** _______________________

---

## Pós Go-Live

Após entrar em produção, verificar nos primeiros dias:

- [ ] Logs sem erros críticos
- [ ] Performance dentro do esperado
- [ ] Backups executando normalmente
- [ ] Scheduler executando tarefas
- [ ] Queue processando jobs
- [ ] Nenhum alerta crítico
- [ ] Usuários conseguindo usar o sistema

---

*Última atualização: Janeiro 2026*

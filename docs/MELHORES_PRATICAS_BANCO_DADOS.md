# Melhores práticas para não perder dados do banco (tecnologia e segurança)

Resumo do que a indústria recomenda hoje para proteger dados do banco.

---

## 1. Backup automático e frequente

| Prática | Por quê |
|--------|--------|
| **Backup automático** (cron/scheduler) | Evita esquecer; não depende de pessoa. |
| **Frequência** | Diário no mínimo; em produção, 2–4x ao dia ou contínuo (replicação). |
| **Retenção** | Mínimo 7 dias; 30 dias para auditoria; 1 ano se exigido por norma. |

**Tecnologia:**

- **MySQL/MariaDB:** `mysqldump` (lógico) ou **Percona XtraBackup** (físico, mais rápido para restauração).
- **Cloud:** Se usar banco gerenciado (AWS RDS, DigitalOcean Managed DB, etc.), ativar **backups automáticos** e retenção configurada.
- **Laravel:** Pacote **spatie/laravel-backup** (banco + arquivos, envio para S3/outros).

---

## 2. Backups fora do servidor (regra 3-2-1)

| Regra | Significado |
|-------|-------------|
| **3** cópias dos dados | Produção + pelo menos 2 cópias de backup. |
| **2** mídias diferentes | Ex.: disco no servidor + S3/outro datacenter. |
| **1** cópia off-site | Backup em outro lugar (nuvem, outro servidor). |

**Por quê:** Se o servidor queimar, for invadido ou o disco corromper, o backup no mesmo disco pode ser perdido. Backups em S3, outro VPS ou nuvem garantem recuperação.

---

## 3. Criptografia e acesso

| Prática | Por quê |
|--------|--------|
| **Criptografar backups** | Se o arquivo vazar, dados não ficam legíveis. |
| **Senhas fora do código** | Usar `.env` e variáveis de ambiente; nunca commitar senha no repositório. |
| **Acesso restrito** | Apenas quem precisa (ex.: deploy/ops) acessa pasta/S3 de backups. |
| **Logs de acesso** | Saber quem baixou/restaurou backup (auditoria). |

**Exemplo (backup criptografado com OpenSSL):**

```bash
# Credenciais em ~/.my.cnf (permissão 600)
mysqldump --defaults-file=~/.my.cnf cred | gzip | openssl enc -aes-256-cbc -out backup_$(date +%Y%m%d).sql.gz.enc
```

---

## 4. Testar restauração (o backup só vale se restaurar)

| Prática | Por quê |
|--------|--------|
| **Restore em ambiente de teste** | Garantir que o dump restaura sem erro. |
| **Frequência** | Pelo menos 1x por mês; em sistemas críticos, 1x por semana. |
| **Documentar o passo a passo** | Em incidente, ninguém perde tempo procurando como restaurar. |

Se nunca testou o restore, não considere o backup “confiável”.

---

## 5. Banco gerenciado (quando fizer sentido)

Usar **banco gerenciado** (AWS RDS, DigitalOcean Managed MySQL, etc.) costuma dar:

- Backups automáticos diários com retenção configurável.
- Restore pontual (point-in-time recovery) em muitos provedores.
- Alta disponibilidade (réplicas) e menos risco de perda por falha de disco/servidor.

Desvantagem: custo e menos controle total. Para muitos projetos, o ganho em segurança compensa.

---

## 6. Réplicas (alta disponibilidade e menos perda de dados)

| Recurso | O que faz |
|--------|-----------|
| **Réplica de leitura** | Cópia do banco em outro servidor; se o principal cair, pode promover a réplica. |
| **Replicação síncrona** | Dados só confirmados quando replicados (menor perda em falha; mais complexo e custo maior). |

Para a maioria dos sistemas, **backup automático + off-site + teste de restore** já é um bom padrão; réplicas entram quando o negócio exige menos downtime e menor perda (RPO próximo de zero).

---

## 7. Ambiente de desenvolvimento e testes

| Prática | Por quê |
|--------|--------|
| **Banco separado para testes** | Testes (RefreshDatabase/migrate:fresh) não rodam no banco de produção nem no de desenvolvimento. |
| **phpunit.xml com DB_DATABASE=cred_test** | Garante que `php artisan test` nunca use o banco principal. |

Evita perda de dados por execução acidental de testes no banco errado.

---

## 8. Checklist resumido

- [ ] Backup automático do banco (diário ou mais frequente).
- [ ] Backup enviado para **fora do servidor** (S3, outro servidor, nuvem).
- [ ] Retenção definida (ex.: 7–30 dias).
- [ ] **Teste de restore** feito periodicamente (ex.: 1x por mês).
- [ ] Documentação de **como restaurar** (passo a passo).
- [ ] Senhas e credenciais só em `.env` / variáveis de ambiente.
- [ ] (Opcional) Backups criptografados.
- [ ] (Produção) Banco gerenciado com backup automático ou pacote (ex.: spatie/laravel-backup).

---

## 9. Para este projeto (sistema-cred)

**Mínimo recomendado:**

1. **Cron ou Laravel Scheduler** rodando `mysqldump` (ou comando Artisan) diariamente.
2. **Cópia do dump** para outro lugar (outro disco, S3, outro VPS).
3. **Teste de restore** em ambiente de teste pelo menos 1x por mês.
4. **phpunit.xml** com `DB_DATABASE=cred_test` (já configurado) e banco `cred_test` criado para testes.

**Próximo nível:**  
Usar **spatie/laravel-backup** para automatizar banco + `storage/app`, retenção e envio para S3 (ou outro disco).

Detalhes de exemplo de cron e do que fazer backup estão em **`docs/BACKUP_E_SAUDE.md`**.

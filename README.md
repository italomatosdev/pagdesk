# PagDesk — Sistema de Gestão de Crédito

Sistema web para gestão de crédito, empréstimos, cobranças, caixa e aprovações. Multi-empresa (tenant por empresa), com papéis (Super Admin, Admin, Gestor, Consultor) e operações por empresa.

---

## Sobre o projeto

O **PagDesk** permite:

- **Clientes:** cadastro (PF/PJ), vinculação a operações, limite de crédito por operação, documentos obrigatórios configuráveis por operação, vínculo entre empresas.
- **Empréstimos:** criação com validação de dívida ativa e limite, aprovação automática ou pendente, parcelas (Sistema Price), tipos: dinheiro, troca de cheque, empenho, garantia.
- **Cobranças:** cobranças do dia, parcelas atrasadas (comando agendado), filtros por operação e data.
- **Pagamentos:** registro de pagamentos, atualização de parcelas, movimentação de caixa, comprovantes.
- **Liberações:** fluxo de liberação de empréstimos (consultor → gestor), comprovantes.
- **Caixa / Financeiro:** movimentações, saldo por operação, prestação de contas (settlement).
- **Vendas (crediário):** cadastro de **produtos** por operação (com estoque, fotos e anexos), registro de **vendas** com itens, formas de pagamento (Dinheiro, PIX, Cartão, Crediário). Pagamentos à vista geram entrada no caixa; crediário gera empréstimo e parcelas automaticamente. Produtos vinculados à operação; regra de produto obrigatório com operação.
- **Aprovações:** fila de empréstimos pendentes, aprovar/rejeitar com motivo, auditoria.
- **Super Admin:** gestão de empresas, operações, usuários globais, auditoria, tarefas agendadas (crons) e execução manual pelo front.
- **Dashboard:** visão por perfil (Super Admin, Admin, Gestor, Consultor) com filtro de datas.
- **Painel de pendências (Kanban):** organização visual de pendências.
- **Radar:** consulta cadastral interna por CPF/CNPJ — exibe empréstimos ativos, pendências atrasadas e resumo por operação (mesmos dados do modal de verificação, em tela dedicada).
- **Busca global:** clientes, empréstimos, etc.
- **Recuperação de senha:** fluxo “Esqueci minha senha” e e-mails em português.

Interface em **português (pt_BR)** em toda a aplicação.

---

## Stack técnico

| Camada        | Tecnologia |
|---------------|------------|
| Backend       | PHP 8.2+, Laravel 11 |
| Frontend      | Blade, Bootstrap 5, Vite, JavaScript (SweetAlert2, Select2) |
| Banco de dados | PostgreSQL ou MySQL |
| Filas / cache | Redis (opcional; padrão: file/sync) |
| Autenticação  | Laravel UI (login, recuperação de senha), sessão web |
| Filas em background | Laravel Horizon (opcional) |

---

## Requisitos

- PHP 8.2+
- Composer
- Node.js e npm
- PostgreSQL ou MySQL
- Extensões PHP: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

**Para Docker:**
- Docker Desktop
- MySQL rodando localmente (ou usar MySQL no Docker)

---

## Instalação com Docker (Recomendado)

### 1. Clonar e configurar

```bash
git clone <url-do-repositorio> sistema-cred
cd sistema-cred
cp .env.example .env
```

### 2. Configurar .env para Docker

```env
# Banco de dados (MySQL local)
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=cred
DB_USERNAME=root
DB_PASSWORD=

# Redis (container Docker)
REDIS_HOST=redis

# Portas (ajuste se necessário)
NGINX_HTTP_PORT=8080
```

### 3. Subir os containers

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

### 4. Executar migrations

```bash
docker exec pagdesk-app php artisan migrate --seed
```

### 5. Acessar

- **Aplicação:** http://localhost:8080
- **Grafana:** http://localhost:3000 (admin/admin)
- **Mailpit:** http://localhost:8025

> **Documentação completa:** Veja `docs/DOCKER.md` para mais detalhes.

---

## Instalação Manual (sem Docker)

### 1. Clonar e dependências

```bash
git clone <url-do-repositorio> sistema-cred
cd sistema-cred

composer install
npm install
```

### 2. Ambiente

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Banco de dados

Edite o `.env`:

```env
DB_CONNECTION=pgsql   # ou mysql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sistema_cred
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 4. Migrations e seeders

```bash
php artisan migrate
php artisan db:seed
```

> **Nota:** Migrations que alteram colunas existentes (ex.: `->nullable()->change()`) podem exigir o pacote `doctrine/dbal`. Se aparecer erro ao rodar `migrate`, instale com: `composer require doctrine/dbal` e execute as migrations novamente.

### 5. Assets e storage

```bash
npm run dev
# ou para produção: npm run build

php artisan storage:link
```

### 6. Servidor

```bash
php artisan serve
```

Acesse: **http://localhost:8000**

---

## Usuários padrão (após seed)

| Perfil       | E-mail                     | Senha    |
|-------------|-----------------------------|----------|
| Super Admin | superadmin@sistema-cred.com | 12345678 |
| Admin       | admin@sistema-cred.com       | 12345678 |
| Gestor      | gestor@sistema-cred.com     | 12345678 |
| Consultor   | consultor@sistema-cred.com  | 12345678 |

---

## Estrutura do projeto

```
sistema-cred/
├── app/
│   ├── Console/Commands/     # Comandos Artisan (ex.: parcelas:marcar-atrasadas)
│   ├── Http/Controllers/
│   │   ├── Auth/            # Login, recuperação de senha
│   │   ├── SuperAdmin/      # Empresas, operações, usuários, auditoria, tarefas agendadas
│   │   └── ...
│   ├── Models/              # User, ScheduledTaskRun, Scopes
│   ├── Modules/
│   │   ├── Core/            # Clientes, operações, usuários, dashboard, kanban, busca, notificações, vendas, produtos, radar
│   │   ├── Loans/           # Empréstimos, parcelas, pagamentos, liberações, cheques, garantias
│   │   ├── Cash/            # Caixa, settlement
│   │   └── Approvals/       # Aprovações de empréstimos
│   ├── Services/            # ScheduledTaskRunService
│   ├── Notifications/       # ResetPasswordNotification
│   └── ...
├── config/
├── database/migrations/
├── docs/                    # Documentação detalhada (instalação, backup, Redis, etc.)
├── lang/pt_BR/              # Traduções (auth, validation, passwords)
├── resources/views/         # Views Blade (layouts, clientes, empréstimos, super-admin, etc.)
├── routes/
│   ├── web.php              # Rotas web
│   └── api.php
└── tests/
```

- **Super Admin:** `/super-admin/*` (empresas, operações, usuários, auditoria, tarefas agendadas).
- **Dashboard:** `/dashboard`.
- **Clientes:** `/clientes/*`.
- **Radar:** `/radar` — consulta por CPF/CNPJ (Administrador, Gestor, Consultor).
- **Vendas:** `/vendas/*` (listagem, nova venda, detalhes). **Produtos:** `/produtos/*` (CRUD, fotos/anexos). Acesso: Administrador e Gestor.
- **Empréstimos:** `/emprestimos/*`, liberações, cobranças, pagamentos (inclui tipo *Crediário* gerado pelas vendas).
- **Caixa:** `/caixa`, `/prestacao-contas`.
- **Aprovações:** `/aprovacoes`.
- **Health Checks:**
  - `GET /health` — status completo da aplicação
  - `GET /health/live` — liveness probe (aplicação viva?)
  - `GET /health/ready` — readiness probe (pronta para tráfego?)

---

## Comandos úteis

### Com Docker

```bash
# Iniciar ambiente
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Parar ambiente
docker compose -f docker-compose.yml -f docker-compose.dev.yml down

# Ver logs
docker logs -f pagdesk-app

# Executar artisan
docker exec pagdesk-app php artisan <comando>

# Limpar caches
docker exec pagdesk-app php artisan cache:clear
docker exec pagdesk-app php artisan view:clear
```

### Sem Docker

```bash
# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Marcar parcelas atrasadas (também disponível no Super Admin > Tarefas Agendadas)
php artisan parcelas:marcar-atrasadas

# Recriar banco (apaga todos os dados)
php artisan migrate:fresh --seed
```

---

## Tarefas agendadas (cron)

### Com Docker

O container `pagdesk-scheduler` executa automaticamente `php artisan schedule:run` a cada minuto. Não precisa configurar nada.

### Sem Docker

Configure o cron:

```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O sistema registra execuções na tabela `scheduled_task_runs`. O Super Admin pode ver o histórico e executar tarefas permitidas manualmente em **Super Admin > Tarefas Agendadas**.

---

## Documentação

### Acesso Rápido

| Objetivo | Documento |
|----------|-----------|
| **Rodar localmente** | [docs/DOCKER.md](docs/DOCKER.md) |
| **Deploy em produção** | [docs/INFRAESTRUTURA.md](docs/INFRAESTRUTURA.md) |
| **Monitoramento** | [docs/OBSERVABILIDADE.md](docs/OBSERVABILIDADE.md) |
| **Antes de ir para produção** | [docs/CHECKLIST_PRODUCAO.md](docs/CHECKLIST_PRODUCAO.md) |

### Documentação Completa

A pasta **`docs/`** contém guias e referências:

**Infraestrutura e DevOps:**
- [INFRAESTRUTURA.md](docs/INFRAESTRUTURA.md) — Arquitetura de produção, MySQL, CI/CD
- [DOCKER.md](docs/DOCKER.md) — Ambiente Docker para desenvolvimento local
- [DEPLOY.md](docs/DEPLOY.md) — Guia de deploy
- [BACKUP.md](docs/BACKUP.md) — Backup, restore e recuperação de desastres
- [OBSERVABILIDADE.md](docs/OBSERVABILIDADE.md) — Health checks, métricas, alertas
- [CHECKLIST_PRODUCAO.md](docs/CHECKLIST_PRODUCAO.md) — Checklist pré-produção

**Aplicação:**
- [GUIA_INSTALACAO.md](docs/GUIA_INSTALACAO.md) — Instalação passo a passo
- [MODULO_VENDAS.md](docs/MODULO_VENDAS.md) — Vendas, produtos, crediário
- [RADAR.md](docs/RADAR.md) — Consulta cadastral (CPF/CNPJ)

**Técnico:**
- [CONFIGURAR_REDIS.md](docs/CONFIGURAR_REDIS.md) — Redis e cache
- [FILAS_E_HORIZON.md](docs/FILAS_E_HORIZON.md) — Filas e Laravel Horizon

---

## Licença

O projeto utiliza o framework Laravel, open-source sob a [licença MIT](https://opensource.org/licenses/MIT).

# Modo manutenção

## Visão geral

O sistema possui um **modo manutenção** controlado pelo **Super Admin**. Quando ativado, apenas o Super Admin continua acessando o sistema; os demais usuários são redirecionados para uma página informando que o sistema está em manutenção.

O estado (ligado/desligado) é armazenado em **cache** (chave `sistema.manutencao`), sem uso de banco de dados.

---

## Onde configurar

- **Menu:** Super Admin → **Configurações do sistema**
- **URL:** `/super-admin/configuracoes`
- Na página, a seção **Modo manutenção** exibe o status atual e o botão **Ativar manutenção** ou **Desativar manutenção**.

Apenas usuários com perfil **Super Admin** podem acessar essa página e alterar o modo.

---

## Comportamento

### Quando a manutenção está **desativada**
- Todas as rotas funcionam normalmente para usuários autenticados conforme seus perfis.

### Quando a manutenção está **ativada**
- **Super Admin:** continua acessando todas as rotas (incluindo login e Configurações do sistema).
- **Demais usuários (e visitantes):** ao acessar qualquer rota protegida, são redirecionados para a **página de manutenção** (`/manutencao`).

### Rotas sempre liberadas (mesmo em manutenção)
- `/login` — para o Super Admin poder entrar e desativar a manutenção.
- `/manutencao` — página exibida durante a manutenção.
- `/health`, `/health/live`, `/health/ready` — health checks (monitoramento/load balancer).
- `/cadastro/cliente*` — cadastro público do cliente via link.
- `/logout` (GET) — redirecionamento para login.

---

## Página de manutenção

- **URL:** `/manutencao`
- **View:** `resources/views/pages-maintenance.blade.php`
- Exibe mensagem em português (“Sistema em manutenção”, “Voltamos em breve”) e um botão **Atualizar**.

**Botão “Atualizar”:** ao clicar, o usuário é enviado novamente para `/manutencao`. O servidor verifica o status no cache:

- Se a manutenção **ainda estiver ativa** → a mesma página é exibida.
- Se a manutenção **tiver sido desativada** → o usuário é redirecionado para **`/dashboard`**.

---

## Arquivos envolvidos

| Função | Arquivo |
|--------|---------|
| Middleware (redireciona para /manutencao quando ativo) | `app/Http/Middleware/CheckManutencaoSistema.php` |
| Chave de cache | `CheckManutencaoSistema::CACHE_KEY` = `'sistema.manutencao'` |
| Toggle (ativar/desativar) | `app/Http/Controllers/SuperAdmin/ManutencaoController.php` |
| Página Configurações do sistema | `app/Http/Controllers/SuperAdmin/ConfiguracoesSistemaController.php` |
| View Configurações do sistema | `resources/views/super-admin/configuracoes/index.blade.php` |
| View página de manutenção | `resources/views/pages-maintenance.blade.php` |
| Registro do middleware | `app/Http/Kernel.php` (grupo `web`) |
| Rotas | `routes/web.php`: GET `/manutencao`, GET `/super-admin/configuracoes`, POST `/super-admin/manutencao/toggle` |

---

## Rotas

| Método | URI | Nome | Descrição |
|--------|-----|------|-----------|
| GET | `/manutencao` | `manutencao` | Se manutenção inativa → redireciona para `/dashboard`. Se ativa → exibe a página de manutenção. |
| GET | `/super-admin/configuracoes` | `super-admin.configuracoes.index` | Página Configurações do sistema (apenas Super Admin). |
| POST | `/super-admin/manutencao/toggle` | `super-admin.manutencao.toggle` | Liga/desliga o modo manutenção (apenas Super Admin). |

---

## Segurança

- **Toggle:** o `ManutencaoController` verifica `auth()->user()->isSuperAdmin()`; quem não for Super Admin recebe **403**.
- **Configurações do sistema:** o `ConfiguracoesSistemaController` aplica a mesma verificação.
- **CSRF:** o formulário de toggle usa `@csrf`.
- **Throttle:** as rotas do Super Admin estão no grupo com `throttle.sensitive`.

Recomendações: usar **HTTPS** em produção; em ambiente com múltiplos servidores, usar cache compartilhado (ex.: Redis) para que o estado de manutenção seja único.

---

## Cache

- **Driver:** o valor segue o `CACHE_DRIVER` do `.env` (ex.: `file`, `redis`).
- **Persistência:** com `file`, o estado fica em `storage/framework/cache`; com Redis, no servidor Redis configurado.
- Não é necessária migration; nenhuma tabela é usada para o modo manutenção.

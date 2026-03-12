# Sistema de Notificações

## Visão Geral

O sistema de notificações foi implementado para alertar usuários sobre eventos importantes no sistema de crédito. As notificações são exibidas no topbar através de um ícone de sino com badge indicando a quantidade de notificações não lidas.

## Arquitetura

### Estrutura de Banco de Dados

**Tabela: `notificacoes`**

- `id` - ID único da notificação
- `user_id` - ID do usuário destinatário
- `tipo` - Tipo da notificação (enum)
- `titulo` - Título da notificação
- `mensagem` - Mensagem descritiva
- `dados` - Dados adicionais em JSON (IDs de empréstimos, parcelas, etc.)
- `url` - Link para a ação relacionada
- `lida` - Indica se a notificação foi lida
- `lida_em` - Data/hora em que foi marcada como lida
- `created_at` / `updated_at` - Timestamps

### Tipos de Notificações

1. **`emprestimo_pendente`** - Novo empréstimo aguardando aprovação
2. **`emprestimo_aprovado`** - Empréstimo aprovado aguardando liberação
3. **`liberacao_disponivel`** - Dinheiro liberado para consultor pagar ao cliente
4. **`parcela_vencendo`** - Parcela vencendo hoje (futuro)
5. **`parcela_atrasada`** - Parcela atrasada (futuro)
6. **`prestacao_pendente`** - Prestação de contas pendente (futuro)
7. **`prestacao_aprovada`** - Prestação de contas aprovada (futuro)
8. **`prestacao_rejeitada`** - Prestação de contas rejeitada (futuro)
9. **`pagamento_registrado`** - Pagamento ao cliente confirmado
10. **`emprestimo_cancelado`** - Empréstimo cancelado por administrador

## Componentes

### Model: `Notificacao`

Localização: `app/Modules/Core/Models/Notificacao.php`

**Métodos principais:**
- `marcarComoLida()` - Marca a notificação como lida
- `getIconeAttribute()` - Retorna o ícone baseado no tipo
- `getCorAttribute()` - Retorna a cor baseada no tipo
- `getTempoRelativoAttribute()` - Retorna tempo relativo (ex: "há 5 minutos")

### Service: `NotificacaoService`

Localização: `app/Modules/Core/Services/NotificacaoService.php`

**Métodos principais:**
- `criar(array $dados)` - Cria uma notificação para um usuário
- `criarParaMultiplos(array $userIds, array $dados)` - Cria notificações para múltiplos usuários
- `criarParaRole(string $role, array $dados)` - Cria notificações para todos os usuários com uma role específica
- `listar(int $userId, int $limit, bool $apenasNaoLidas)` - Lista notificações do usuário
- `contarNaoLidas(int $userId)` - Conta notificações não lidas
- `marcarComoLida(int $notificacaoId, int $userId)` - Marca uma notificação como lida
- `marcarTodasComoLidas(int $userId)` - Marca todas as notificações do usuário como lidas
- `limparAntigas()` - Remove notificações antigas (mais de 30 dias)

### Controller: `NotificacaoController`

Localização: `app/Modules/Core/Controllers/NotificacaoController.php`

**Endpoints API:**

- `GET /api/notificacoes` - Lista notificações do usuário autenticado
  - Query params: `limit` (padrão: 20), `apenas_nao_lidas` (boolean)
- `GET /api/notificacoes/contar` - Conta notificações não lidas
- `POST /api/notificacoes/{id}/marcar-lida` - Marca uma notificação como lida
- `POST /api/notificacoes/marcar-todas-lidas` - Marca todas as notificações como lidas

## Integração com Serviços

### EmprestimoService

**Quando um empréstimo é criado:**
- Se status = `pendente`: Notifica administradores
- Se status = `aprovado`: Notifica gestores

**Quando um empréstimo é aprovado:**
- Notifica gestores sobre liberação pendente

### LiberacaoService

**Quando dinheiro é liberado:**
- Notifica consultor sobre liberação disponível

**Quando pagamento ao cliente é confirmado:**
- Notifica gestores sobre pagamento confirmado

## Interface do Usuário

### Topbar

O topbar foi atualizado para exibir notificações reais:

- **Badge**: Mostra quantidade de notificações não lidas (oculto se 0)
- **Dropdown**: Lista as últimas 10 notificações
- **Marcar todas como lidas**: Link para marcar todas de uma vez
- **Links de ação**: Cada notificação é clicável e leva à página relacionada

### JavaScript (Polling)

Localização: `resources/js/app.js`

**Funcionalidades:**
- Carrega notificações ao abrir o dropdown
- Atualiza badge automaticamente a cada 60 segundos
- Marca notificação como lida ao clicar
- Redireciona para URL da notificação após marcar como lida

## Fluxo de Uso

1. **Evento ocorre** (ex: empréstimo criado)
2. **Service cria notificação** via `NotificacaoService`
3. **Badge é atualizado** automaticamente (polling a cada 60s)
4. **Usuário clica no sino** → Dropdown abre e carrega notificações
5. **Usuário clica na notificação** → Marca como lida e redireciona
6. **Badge é atualizado** imediatamente

## Próximos Passos (Futuro)

1. **Notificações de Parcelas**: Alertar sobre parcelas vencendo/atrasadas
2. **Notificações de Prestação de Contas**: Alertar sobre prestações pendentes
3. **WebSockets**: Substituir polling por WebSockets para tempo real
4. **Notificações por Email**: Enviar email para notificações importantes
5. **Preferências de Notificação**: Permitir usuário configurar quais notificações receber
6. **Notificações Push**: Notificações do navegador (Push API)

## Exemplo de Uso

```php
// Criar notificação para um usuário
$notificacaoService = app(NotificacaoService::class);
$notificacaoService->criar([
    'user_id' => $userId,
    'tipo' => 'emprestimo_pendente',
    'titulo' => 'Novo Empréstimo Pendente',
    'mensagem' => "Empréstimo de R$ 1.000,00 para João Silva aguardando aprovação",
    'url' => route('aprovacoes.index'),
    'dados' => ['emprestimo_id' => 123],
]);

// Criar notificação para todos os gestores
$notificacaoService->criarParaRole('gestor', [
    'tipo' => 'emprestimo_aprovado',
    'titulo' => 'Empréstimo Aprovado',
    'mensagem' => "Empréstimo aprovado aguardando liberação",
    'url' => route('liberacoes.index'),
    'dados' => ['emprestimo_id' => 123],
]);
```

## Migração

Para aplicar as mudanças no banco de dados:

```bash
php artisan migrate
```

## Notas Técnicas

- As notificações são armazenadas no banco de dados
- O polling é feito a cada 60 segundos para atualizar o badge
- Notificações antigas (mais de 30 dias e lidas) podem ser limpas automaticamente
- O sistema suporta múltiplos tipos de notificações com ícones e cores diferentes
- Cada notificação pode ter uma URL de ação personalizada

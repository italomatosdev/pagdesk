# Plano de implementação: Papel por operação

**Objetivo:** Um usuário pode ter um **papel diferente em cada operação** (ex.: Consultor na HM Cred, Administrador na Total Business, Gestor na ITMS Cred).  
**Escopo:** 1 papel por operação (por enquanto).  
**Produção:** App já em produção — plano inclui rollback e testes.

---

## Resumo do modelo atual vs. novo

| Aspecto | Atual | Novo |
|--------|--------|------|
| Operações do usuário | N operações (tabela `operacao_user`: user_id, operacao_id) | Igual |
| Papel | Global (tabela `role_user`: user_id, role_id) — mesmo em todas as operações | **Por operação**: coluna `role` na tabela `operacao_user` |
| Super Admin | Sem empresa, sem operação, acesso total | Mantido: ignora checagem por operação |
| Empresa | 1 usuário = 1 empresa (`users.empresa_id`) | Sem mudança |

---

## Fase 0: Preparação e segurança (produção)

- [ ] **Backup do banco** antes de qualquer migration.
- [ ] **Branch dedicada** para a feature (ex.: `feat/papel-por-operacao`).
- [ ] **Rollback:** a migration deve ser reversível; ao dar rollback, o código que usar apenas `getOperacoesIds()` e o novo helper de papel continua funcionando se você **não** remover os papéis globais de `role_user` na primeira entrega (veja Fase 6).
- [ ] **Comunicar** aos usuários janela de deploy se houver parada.

---

## Fase 1: Banco de dados

### 1.1 Migration: adicionar `role` em `operacao_user`

- **Arquivo:** nova migration (ex.: `add_role_to_operacao_user_table.php`).
- **Alteração:** adicionar coluna `role` (string, nullable no primeiro deploy para não quebrar dados existentes).
  - Valores permitidos: `consultor`, `gestor`, `administrador` (mesmos nomes da tabela `roles` ou do uso atual).
  - Sugestão: `$table->string('role', 50)->nullable()->after('user_id');`
- **Índice (opcional):** `$table->index('role');` se for filtrar muito por papel.
- **down:** remover a coluna `role`.

### 1.2 Dados (manual)

- Você preenche **na mão** cada linha de `operacao_user` com o `role` correto (consultor, gestor ou administrador).
- **Não** é necessário script de migração a partir de `role_user`: o preenchimento é manual conforme regra de negócio.

### 1.3 Papéis globais (`role_user`)

- **Opção A (recomendada na 1ª entrega):** Manter `role_user` como está. Na lógica, passar a usar **só** o papel da pivot `operacao_user` quando houver **contexto de operação**. Assim, se algo falhar, o comportamento antigo (por role global) ainda existe.
- **Opção B (fase futura):** Remover uso de `role_user` para consultor/gestor/administrador e usar apenas `operacao_user.role`. Exige garantir que todos os pontos do plano já foram migrados.

---

## Fase 2: Model e helpers no User

### 2.1 Pivot com `role`

- **Arquivo:** `app/Models/User.php` (ou onde estiver o relacionamento `operacoes()`).
- No `belongsToMany(Operacao::class, 'operacao_user', ...)` adicionar **`->withPivot('role')`** para carregar o papel na operação.

### 2.2 Novos métodos no User

Implementar (exemplo de assinaturas):

```php
// Retorna o papel do usuário na operação (string ou null)
public function getPapelNaOperacao(int $operacaoId): ?string

// True se na operação $operacaoId o usuário tem o papel $papel
public function temPapelNaOperacao(int $operacaoId, string $papel): bool

// True se na operação $operacaoId o usuário tem algum dos papéis
public function temAlgumPapelNaOperacao(int $operacaoId, array $papeis): bool

// IDs das operações em que o usuário tem um dos papéis (útil para sidebar/relatórios)
public function getOperacoesIdsOndeTemPapel(array $papeis): array
```

- **Super Admin:** em todos esses métodos, retornar “tem acesso” / “todos os papéis” conforme a regra atual (ex.: `getPapelNaOperacao` retorna `'administrador'` ou lista completa; `temPapelNaOperacao` true para qualquer papel; etc.).
- **Fonte da verdade:** ler de `operacao_user.role` (via relação `operacoes` com pivot, ou query direta na pivot).

### 2.3 Ajuste de `temAcessoOperacao` (opcional na 1ª entrega)

- Hoje: Super Admin e “administrador” (global) têm acesso a todas as operações.
- Com papel por operação: pode manter “tem acesso se está na operação” (só `operacao_user` com esse `operacao_id`) e tratar “administrador” como “tem papel administrador **nessa** operação”. Ou manter temporariamente a regra antiga para administrador global até remover `role_user`.

---

## Fase 3: Controllers e Services — lista completa

Regra geral:
- Onde existe **recurso com operação** (empréstimo, liberação, parcela, fechamento, etc.): trocar `hasRole` / `hasAnyRole` por **temPapelNaOperacao / temAlgumPapelNaOperacao($operacaoId, $papeis)** usando o `operacao_id` do recurso.
- Onde **não** existe recurso (tela de listagem, menu, dashboard): usar **getOperacoesIdsOndeTemPapel** ou “tem em pelo menos uma operação o papel X” conforme a regra abaixo.

### 3.1 EmprestimoController

| Arquivo | Onde / linha (ref) | Contexto | Ação |
|---------|--------------------|----------|------|
| EmprestimoController.php | index, create (operacoes disponíveis) | operacao_id no request ou lista de operações | Filtro de operações: manter getOperacoesIds(); criar empréstimo: só operações onde tem papel consultor/gestor/admin conforme regra; onde hoje hasAnyRole(['gestor','administrador']) com temAcessoOperacao → temAlgumPapelNaOperacao($opId, ['gestor','administrador']) |
| | store (validar consultor na operação) | operacao_id no request | Trocar hasRole/ temAcessoOperacao por temPapelNaOperacao($operacao_id, 'consultor') (ou papel que possa criar) |
| | show($id) | emprestimo->operacao_id | Já checa operação com getOperacoesIds(); adicionar ou substituir por temPapelNaOperacao($emprestimo->operacao_id, …) se a regra for “só quem tem papel X vê” |
| | renovar($id) | emprestimo->operacao_id | Gestor/admin na **operação do empréstimo**: temAlgumPapelNaOperacao($emprestimo->operacao_id, ['gestor','administrador']) |
| | cancelar, executarGarantia, etc. | emprestimo->operacao_id | Trocar hasRole('administrador') / hasAnyRole por temPapelNaOperacao($emprestimo->operacao_id, 'administrador') ou temAlgumPapelNaOperacao(..., ['gestor','administrador']) |
| | create (negociacao), processarNegociacao | emprestimoOrigem->operacao_id | Idem: papel na operação do empréstimo de origem |

### 3.2 LiberacaoController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| LiberacaoController.php | Todas as ações que checam hasAnyRole(['gestor','administrador']) | Recursos têm emprestimo->operacao_id. Trocar por temAlgumPapelNaOperacao($liberacao->emprestimo->operacao_id, ['gestor','administrador']) (ou $emprestimo->operacao_id onde houver) |
| | show($id) | temAcessoOperacao($liberacao->emprestimo->operacao_id) pode virar “está na operação E tem papel que pode ver” (consultor = próprio, gestor/admin = temAlgumPapelNaOperacao) |
| | index (listagem) | Manter getOperacoesIds(); opcional filtrar só operações onde é gestor/admin com getOperacoesIdsOndeTemPapel(['gestor','administrador']) |
| | minhasLiberacoes | Consultor: já filtra por consultor_id; operação pode continuar com getOperacoesIds() ou por papel “consultor” nas operações |

### 3.3 PagamentoController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| PagamentoController.php | create (parcela_id), store | parcela->emprestimo->operacao_id. Trocar checagem de operação por temPapelNaOperacao / temAlgumPapelNaOperacao na operação do empréstimo |
| | quitarDiariasCreate, quitarDiariasStore | emprestimo->operacao_id. Idem |
| | Outros métodos com hasRole('consultor') / hasAnyRole | Se o contexto for “quem pode registrar pagamento” = consultor naquela operação, usar getPapelNaOperacao ou temPapelNaOperacao |

### 3.4 PagamentoService

| Arquivo | Contexto | Ação |
|---------|----------|------|
| PagamentoService.php | hasAnyRole(['administrador','gestor']) com emprestimo | Usar operacao_id do empréstimo e temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador','gestor']) |

### 3.5 QuitacaoController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| QuitacaoController.php | quitar, store | emprestimo->operacao_id. Trocar hasAnyRole por temAlgumPapelNaOperacao($emprestimo->operacao_id, ['gestor','administrador']) para gestor/admin; consultor = dono do empréstimo |
| | indexPendentes, aprovar, rejeitar | SolicitacaoQuitacao tem emprestimo->operacao_id. Checar papel na operação do empréstimo da solicitação |

### 3.6 GarantiaController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| GarantiaController.php | Todos os métodos que usam emprestimo | emprestimo->operacao_id. Substituir hasAnyRole(['administrador','gestor']) por temAlgumPapelNaOperacao($emprestimo->operacao_id, ['administrador','gestor']) |

### 3.7 ChequeController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| ChequeController.php | Todos os métodos com emprestimo | emprestimo->operacao_id. Trocar hasAnyRole e temAcessoOperacao por temAlgumPapelNaOperacao($emprestimo->operacao_id, ['gestor','administrador']) e “está na operação” |

### 3.8 ParcelaController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| ParcelaController.php | cobrancasDoDia | getOperacoesIds() pode ficar; “consultor” filtrar por operações onde tem papel consultor: getOperacoesIdsOndeTemPapel(['consultor']) ou manter getOperacoesIds e filtrar consultor_id |
| | parcelasAtrasadas | Idem: filtro por operação onde é consultor/gestor/admin conforme regra (getOperacoesIdsOndeTemPapel ou getOperacoesIds) |

### 3.9 FechamentoCaixaController / SettlementController (Cash)

| Arquivo | Contexto | Ação |
|---------|----------|------|
| FechamentoCaixaController.php | Todas as checagens hasAnyRole(['gestor','administrador']) | settlement / operacao_id do recurso. Usar temAlgumPapelNaOperacao($operacaoId, ['gestor','administrador']) |
| SettlementController.php | Idem | Sempre que houver operacao_id (request ou settlement->operacao_id). Trocar hasRole/hasAnyRole por papel na operação |

### 3.10 CashController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| CashController.php | index, create, store, etc. | Onde há operação (movimentação por operação). Gestor/admin = temAlgumPapelNaOperacao na operação em questão; consultor = papel consultor na operação |

### 3.11 CategoriaMovimentacaoController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| CategoriaMovimentacaoController.php | hasAnyRole(['gestor','administrador']) | Acesso à tela: “tem em pelo menos uma operação papel gestor ou administrador” → getOperacoesIdsOndeTemPapel(['gestor','administrador']) não vazio. Nas ações por operacao_id, checar temPapelNaOperacao |

### 3.12 AprovacaoController / AprovacaoService

| Arquivo | Contexto | Ação |
|---------|----------|------|
| AprovacaoController.php | hasRole('administrador') | Aprovar empréstimo: só quem tem papel administrador **na operação do empréstimo**: temPapelNaOperacao($emprestimo->operacao_id, 'administrador') |
| AprovacaoService.php | getOperacoesIds() do aprovador | Listar pendentes: operações onde o usuário tem papel administrador: getOperacoesIdsOndeTemPapel(['administrador']) |

### 3.13 LiberacaoController (cont.)

- Todas as ~20 ocorrências de hasAnyRole(['gestor','administrador']) em ações com liberação/emprestimo: usar **operacao_id** do emprestimo da liberação e **temAlgumPapelNaOperacao**.

### 3.14 DashboardController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| DashboardController.php | Qual dashboard mostrar (administrador, gestor, consultor) | Decisão: “qual o maior papel em qualquer operação?” ou “tem pelo menos uma operação como admin?”. Ex.: se getOperacoesIdsOndeTemPapel(['administrador']) não vazio → dashboard admin; senão gestor; senão consultor. Ajustar getDateRangeFromRequest e filtros por operação para usar getOperacoesIds() ou getOperacoesIdsOndeTemPapel conforme o dashboard |
| | dashboardConsultor | Já filtra por getOperacoesIds(); pode restringir a operações onde tem papel “consultor” com getOperacoesIdsOndeTemPapel(['consultor']) |

### 3.15 UsuarioController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| UsuarioController.php | Gestor não pode atribuir administrador | “Gestor” = tem papel gestor em alguma operação e não tem papel administrador em nenhuma? Ou manter regra global até remover role_user. Definir: gestor só em algumas ops não pode atribuir role administrador a outro usuário (em nenhuma op) |
| | Listagem de usuários, operações permitidas | getOperacoesIds() continua; ao atribuir operação ao usuário, passar a atribuir também o **papel** naquela operação (novo campo no form) |

### 3.16 OperacaoController, VendaController, ProdutoController, RelatorioController, RadarController, SearchController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| OperacaoController.php | hasAnyRole(['administrador','gestor']) | Acesso à tela: “tem em pelo menos uma operação papel gestor ou administrador” → getOperacoesIdsOndeTemPapel(['gestor','administrador']) não vazio |
| VendaController.php | Idem | Idem; nas ações com operacao_id, usar temPapelNaOperacao / temAlgumPapelNaOperacao |
| ProdutoController.php | Idem | Idem |
| RelatorioController.php | hasAnyRole(['administrador','gestor']) em cada relatório | “Pode acessar relatório” = tem em pelo menos uma operação papel gestor ou admin; dentro do relatório filtrar por operações onde tem esse papel |
| RadarController.php | hasAnyRole(['administrador','gestor','consultor']) | Acesso = tem em pelo menos uma operação um desses papéis |
| SearchController.php | hasRole('administrador') | Definir: busca avançada só para quem tem papel administrador em alguma operação? getOperacoesIdsOndeTemPapel(['administrador']) não vazio |

### 3.17 KanbanService / KanbanBoardController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| KanbanService.php | hasRole('consultor'), hasAnyRole(['gestor','administrador']) | Colunas/ítens por operação: mostrar só operações onde o usuário tem o papel que permite ver aquele tipo de pendência (ex.: liberações = operações onde é gestor ou admin) |
| KanbanBoardController.php | getOperacoesIds() | Pode restringir a getOperacoesIdsOndeTemPapel(['consultor','gestor','administrador']) |

### 3.18 NotificacaoService

| Arquivo | Contexto | Ação |
|---------|----------|------|
| NotificacaoService.php | getOperacoesIds() para notificar gestor/admin | Notificar “gestores da operação” = usuários que têm papel gestor ou administrador **nessa** operação (query em operacao_user onde operacao_id = X e role in ('gestor','administrador')) |

### 3.19 ClienteController, CadastroClienteController

| Arquivo | Contexto | Ação |
|---------|----------|------|
| ClienteController.php | getOperacoesIds(), temAcessoOperacao | Manter “acesso ao cliente” por operação; não depende de papel, só de estar na operação. Opcional: restringir edição a quem tem papel gestor/admin na operação do cliente (se cliente tiver operação) — hoje cliente é global, acesso é por operation_clients |
| CadastroClienteController.php | temAcessoOperacao($operacaoId) | Manter; ou acrescentar “e tem papel que pode cadastrar cliente” (ex. consultor naquela op) |

### 3.20 HorizonServiceProvider

| Arquivo | Contexto | Ação |
|---------|----------|------|
| HorizonServiceProvider.php | hasRole('administrador') para acessar Horizon | Manter global ou “tem papel administrador em pelo menos uma operação” (getOperacoesIdsOndeTemPapel(['administrador']) não vazio) |

---

## Fase 4: Views (Blade)

Regra: onde a view usa `auth()->user()->hasRole(...)` ou `hasAnyRole(...)` **e** existe um recurso com operação (empréstimo, liberação, settlement), passar a usar um helper ou variável passada pelo controller (ex.: `$podeAprovar = $user->temPapelNaOperacao($emprestimo->operacao_id, 'administrador')`).

### 4.1 Lista de arquivos e pontos

| View | Uso atual | Ação |
|------|-----------|------|
| liberacoes/show.blade.php | hasAnyRole(['gestor','administrador']), hasRole('consultor') | Controller deve passar flags por operação (ex.: $podeLiberar, $podeConfirmarPagamento) com base em temAlgumPapelNaOperacao($liberacao->emprestimo->operacao_id, ...) |
| layouts/sidebar.blade.php | hasAnyRole(['administrador','gestor']), hasRole('consultor'), hasRole('administrador') | Mostrar menu “Liberações”, “Aprovações”, “Relatórios”, “Administração” se getOperacoesIdsOndeTemPapel(['gestor','administrador']) não vazio; “Aprovações” só se getOperacoesIdsOndeTemPapel(['administrador']) não vazio; “Minhas Liberações” se é consultor em alguma op |
| liberacoes/index.blade.php | hasRole('administrador') | Trocar por “tem em alguma operação papel administrador” (controller ou @php getOperacoesIdsOndeTemPapel) |
| emprestimos/create.blade.php | hasAnyRole(['gestor','administrador']), hasRole('gestor') | Controller já envia dados; passar $ehGestorOuAdmin por operação ou “em alguma op” conforme regra |
| emprestimos/show.blade.php | hasAnyRole(['gestor','administrador']), hasRole('administrador') | Controller deve passar variáveis (ex.: $podeCancelar, $podeExecutarGarantia) calculadas com temPapelNaOperacao($emprestimo->operacao_id, ...) |
| pagamentos/create.blade.php | hasAnyRole(['administrador','gestor']) | Idem: $podeExecutarGarantia etc. com base na operação da parcela |
| parcelas/atrasadas.blade.php | hasRole('consultor') | “Sou apenas consultor” = em todas as minhas operações sou consultor? Ou “tem papel consultor em pelo menos uma”? Definir e usar getOperacoesIdsOndeTemPapel ou flag do controller |
| caixa/index.blade.php, caixa/fechamento/index.blade.php, caixa/fechamento/show.blade.php | hasAnyRole(['gestor','administrador']) | Controller passa flags ou view usa getOperacoesIdsOndeTemPapel(['gestor','administrador']) |
| prestacoes/index.blade.php, prestacoes/show.blade.php | hasAnyRole(['gestor','administrador']) | Por settlement->operacao_id: temAlgumPapelNaOperacao($settlement->operacao_id, ['gestor','administrador']) — melhor no controller |

**Recomendação:** Preferir que o **controller** calcule “pode fazer X” e passe para a view (compact('podeCancelar', 'podeExecutarGarantia', ...)) para não repetir lógica e não expor helpers demais na view.

---

## Fase 5: Cadastro de usuário × operação (papel)

### 5.1 Tela de usuários (vínculo operação + papel)

- Onde hoje se **vincula** usuário a operações (checkboxes ou multi-select de operações), passar a ter **por cada operação**: seleção de **papel** (Consultor, Gestor, Administrador).
- Exemplo: tabela “Operações” com colunas [Operação, Papel]; ao salvar, gravar em `operacao_user` (user_id, operacao_id, **role**).
- **Arquivo:** UsuarioController (método que atualiza operações do usuário) + view (form de edição/criação de usuário).

### 5.2 Validação

- Em cada operação, exatamente um papel (obrigatório após preencher manualmente o banco).
- Super Admin: não precisa estar em operações; mantém acesso total.

---

## Fase 6: Papéis globais (role_user) e rollback

- **Primeira entrega:** Manter `role_user` e **não** remover papéis. Código passa a usar **primeiro** o papel da operação (quando houver operacao_id); onde não houver contexto de operação (ex.: sidebar), usar getOperacoesIdsOndeTemPapel. Assim, se precisar rollback, basta reverter o código; os dados em `operacao_user.role` podem ficar.
- **Rollback:** Reverter commits da feature; a coluna `role` pode permanecer nullable. Se tiver preenchido tudo, não reverter a migration evita recriar dados manualmente.

---

## Fase 7: Testes e checklist antes do deploy

- [ ] Super Admin: continua acessando tudo sem depender de operação/papel.
- [ ] Usuário com 1 operação e papel “consultor”: vê só o que um consultor vê naquela operação; não vê outras operações.
- [ ] Usuário com 2 operações: consultor na A, administrador na B. Em recurso da operação A: só ações de consultor; em recurso da operação B: ações de administrador.
- [ ] Sidebar: “Liberações” e “Aprovações” só aparecem se tiver pelo menos uma operação com papel gestor ou administrador.
- [ ] Dashboard: consultor vê dashboard consultor; gestor (só em algumas ops) vê dashboard gestor; admin (em pelo menos uma op) vê dashboard admin — conforme regra definida.
- [ ] Aprovação de empréstimo: só quem tem papel “administrador” **na operação do empréstimo** pode aprovar.
- [ ] Liberações: gestor/admin apenas nas operações onde têm esse papel.
- [ ] Fechamento de caixa: aprovar/rejeitar apenas quem tem papel gestor/admin na operação do fechamento.
- [ ] Listagens (empréstimos, liberações, etc.): filtro por operação mostra só operações onde o usuário está; dentro disso, ações (botões) respeitam o papel na operação.
- [ ] Tela “Minhas operações”: exibe operação + papel (já usa pivot; garantir que lê `role` da pivot).

---

## Ordem sugerida de execução

1. Fase 0 (backup, branch).  
2. Fase 1 (migration + preencher `role` na mão).  
3. Fase 2 (model + helpers no User).  
4. Fase 5.1–5.2 (cadastro usuário × operação com papel) para poder testar com dados novos.  
5. Fase 3 por módulo: primeiro EmprestimoController e LiberacaoController (núcleo), depois Pagamento, Quitação, Garantia, Cheque, Caixa, Aprovações, depois Dashboard, Usuario, Relatórios, etc.  
6. Fase 4 (views) em paralelo ou logo após cada controller.  
7. Fase 7 (checklist) em homologação antes de produção.  
8. Fase 6 (decisão sobre remover role_user) em etapa futura, se desejado.

---

## Resumo de arquivos a alterar (contagem aproximada)

- **Migration:** 1 novo arquivo.  
- **User (model + helpers):** 1 arquivo.  
- **Controllers:** ~20 arquivos (Emprestimo, Liberacao, Pagamento, Quitacao, Garantia, Cheque, Parcela, FechamentoCaixa, Settlement, Cash, CategoriaMovimentacao, Aprovacao, Dashboard, Usuario, Operacao, Venda, Produto, Relatorio, Radar, Search, KanbanBoard, Cliente, CadastroCliente).  
- **Services:** PagamentoService, QuitacaoService, KanbanService, NotificacaoService, AprovacaoService, SettlementService.  
- **Views:** ~15 arquivos (liberacoes, emprestimos, pagamentos, parcelas, caixa, prestacoes, sidebar, etc.).  
- **Provider:** HorizonServiceProvider (1).

Total aproximado: **~45 pontos de atenção** (muitos em um mesmo arquivo). Trabalho médio, repetitivo, com risco baixo se seguir o plano e os testes da Fase 7.

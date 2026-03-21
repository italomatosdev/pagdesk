# Plano: varredura e correção — dados cadastrais vs ficha por operação (Fase 4)

Documento de **planejamento** (varredura já realizada em análise estática do repositório). **Não substitui** o `PLANO_OPERACAO_DADOS_CLIENTE.md`; complementa o eixo **“de onde vêm os dados exibidos?”** (além de links/URLs).

---

## 1. O que é o Radar e por que é “global” (contexto de produto)

O **Radar** é descrito no código como a consulta cadastral interna do sistema — analogia explícita no comentário da classe: **“Serasa/SPC do sistema”** (`RadarController`).

### Comportamento técnico (resumo)

- A tela chama o mesmo fluxo de **`ClienteController::buscarPorCpf`** usado no modal de verificação de documento ao criar empréstimo.
- A **busca do cliente** é **global**: usa `Cliente::buscarPorDocumento()` **sem** limitar à empresa do usuário — o CPF/CNPJ é encontrado em **qualquer empresa** cadastrada no sistema.
- Quando o cliente é **da empresa atual** do usuário, a **ficha de risco** agrega:
  - **empréstimos ativos** com escopo **global** (`Emprestimo::withoutGlobalScope(EmpresaScope)`), ou seja, contratos em **todas as empresas**;
  - **parcelas em atraso / pendentes vencidas** também **globais**, agrupadas por operação e empresa.
- Ou seja: para **exposição de risco e histórico de dívidas ativas**, o Radar **não** é “só minha operação” nem “só minha empresa” — é uma visão **transversal** do sistema, alinhada à ideia de **consulta interna tipo bureau**.

### Acesso

- Usuário precisa estar autenticado e **não** pode ser alguém sem operações vinculadas (exceto super admin, conforme regras do middleware).
- Super admin: verificar política de acesso ao Radar (o controller hoje exige vínculo com operação para não super admin).

### Relação com a “ficha por operação”

- O Radar responde principalmente: **“este documento já existe? que dívidas ativas e atrasos existem no universo do sistema?”**
- Os **dados cadastrais** mostrados no cartão (nome, telefone, e-mail, vínculos `operation_clients`) vêm do **modelo `Cliente`** (e dos **accessors** que misturam `ClienteDadosEmpresa`), **não** de uma ficha `operacao_dados_clientes` escolhida — porque a pergunta do Radar **não** é “qual o telefone na Operação X?”, e sim **“quem é esse CPF no sistema e qual o risco agregado?”**.

**Implicação para o plano de correção:** tratar o Radar como **caso especial**: alinhar à ficha por operação **só se o produto quiser** mostrar contato por operação (ex.: abas por operação); caso contrário, manter **visão única de cadastro + agregação financeira global** e documentar essa decisão.

---

## 2. Regra alvo (Fase 4 — eixo dados)

| Contexto | Fonte desejada para nome de exibição, telefone, e-mail, endereço, observações, responsável PJ |
|----------|------------------------------------------------------------------------------------------------|
| Usuário olha o cliente **no contexto da Operação X** (empréstimo, venda, filtro de operação, `?operacao_id=`) | Preferir **`operacao_dados_clientes`** para `(cliente_id, operacao_id)` quando existir linha; fallback documentado. |
| Identidade (CPF/CNPJ, tipo pessoa) | Continuar em **`clientes`**. |
| Tela explicitamente **cadastro geral** (`geral=1`, ou política super admin) | Pode usar **`clientes`** + regras atuais de `ClienteDadosEmpresa` / accessors. |
| Ferramenta **global de risco** (Radar) | Ver §1 — **agregado**; correção opcional e dependente de produto. |

---

## 3. Estado atual (resumo da varredura)

### 3.1 Já alinhado com a ficha (onde há contexto explícito)

- **`clientes/show`** com `operacao_id`: bloco cadastral usa **`OperacaoDadosCliente`** com fallback em `Cliente`.
- **`clientes/edit`** com operação: defaults via **`OperacaoDadosClienteService::valoresFormularioParaOperacao`**.
- **`clientes/index`** e **export** com **filtro por operação**: nome, telefone, e-mail priorizam **`operacaoDadosClientes`** da operação filtrada.
- **Cadastro público** e fluxos de **store/update** já gravam na ficha quando o escopo foi implementado.

### 3.2 Camada transversal que ainda mistura fontes

- Model **`Cliente`**: getters de **nome, telefone, email, endereço, CEP, cidade, estado, observações, responsável…** aplicam override com **`ClienteDadosEmpresa`** da empresa atual (`getDadosEmpresaAtual()`), **independente** de `operacao_dados_clientes`.
- Qualquer tela que use `$cliente->telefone` sem carregar a ficha da operação do contrato pode estar **incorreta** frente à regra “ficha na Operação X”.

### 3.3 Perímetro operacional (alto impacto se fichas divergirem)

- Telas com **`$emprestimo->cliente`** / **`$parcela->emprestimo->cliente`**: em geral só **nome** na tabela; **WhatsApp** usa **`whatsapp_link`** → deriva de **`telefone`** do `Cliente` (accessor), **não** da ficha da `operacao_id` do empréstimo.
- **`relatorios/parcelas-atrasadas` (aba Rota de cobrança)**: endereço montado a partir de **`$cliente->endereco`**, etc. — **sem** `operacao_dados_clientes` da operação do empréstimo.
- **`clientes/index`** com filtro por operação: coluna de telefone pode vir da ficha, mas **WhatsApp** ainda usa **`$cliente->whatsapp_link`** → pode **divergir** do telefone exibido da ficha.
- **API JSON** (`buscarPorCpf` / uso no Radar e no create empréstimo): `telefone`/`email` serializados a partir do **`Cliente`** (accessors), não por operação.

### 3.4 Backend (notificações / mensagens)

- Vários serviços usam **`$cliente->nome`** para texto; o nome já pode ser o de **`ClienteDadosEmpresa`**, não o da ficha da operação do contrato — impacto geralmente **baixo** (rótulo), mas inconsistente com a “verdade” por operação.

### 3.5 Links (fora do escopo deste doc, mas relacionado)

- Já existe **`ClienteUrl`** para navegação com `operacao_id` onde o contexto existe. Este documento foca em **conteúdo**, não em URL.

---

## 4. Plano de correção (fases sugeridas)

### Fase A — Quick wins e inconsistências óbvias

| # | Item | Ação sugerida | Estado |
|---|------|----------------|--------|
| A1 | `clientes/index` com filtro por operação | Alinhar **WhatsApp** ao telefone da **ficha** quando `$fichaLista` tiver telefone (ou montar link a partir da ficha). | **Feito** — `App\Support\WhatsappLink`; fallback `cliente->whatsapp_link`. |
| A2 | `clientes/show` (PJ) | Revisar exibição de **CPF formatado do responsável** para preferir dados da ficha quando em contexto de operação. | **Já atendido** — ficha usa `ValidacaoDocumento::formatarCpf` no CPF do responsável. |
| A3 | Relatório **rota de cobrança** | Resolver endereço (e opcionalmente telefone) via **`obterParaOperacao(cliente_id, emprestimo.operacao_id)`** (ou eager da ficha no controller do relatório). | **Feito** — `RelatorioController::parcelasAtrasadas` carrega `OperacaoDadosCliente` por pares; view agrupa por `cliente_id`+`operacao_id` e monta endereço/nome com fallback. |

### Fase B — Telas com `emprestimo.operacao_id`

| # | Item | Ação sugerida |
|---|------|----------------|
| B1 | `emprestimos/show`, `parcelas/atrasadas`, `cobrancas`, `liberacoes/show`, `pagamentos/*`, etc. | Onde houver **contato ou endereço** ou **WhatsApp**, carregar ficha da operação do empréstimo e usar **fallback** `Cliente`/accessor. |
| B2 | Dashboards (admin/gestor/consultor) | Mesma regra **apenas** onde exibir telefone/endereço/WhatsApp; nome pode permanecer como hoje se produto aceitar. |

### Fase C — API e Radar

| # | Item | Ação sugerida |
|---|------|----------------|
| C1 | JSON de `buscarPorCpf` | Decidir: manter telefone “global” do cliente **ou** retornar **mapa por operação** (`operacao_id` → telefone/email da ficha) para o front escolher. |
| C2 | Radar | Se C1 evoluir, opcionalmente exibir contatos **por operação** na UI; senão, **documentar** que o Radar mostra cadastro agregado + risco global, não ficha única. |

### Fase D — Modelo e legado

| # | Item | Ação sugerida |
|---|------|----------------|
| D1 | Accessors `Cliente` + `ClienteDadosEmpresa` | Decisão de produto: convivência até migração, ou reduzir override em telas sensíveis passando a **DTO/view model** com fonte explícita. |
| D2 | Notificações | Opcional: incluir nome da ficha da operação do empréstimo quando disponível. |

---

## 5. Critérios de pronto (por entrega)

- **A1–A3:** QA com cliente em **duas operações** com telefone/endereço **diferentes** na ficha; conferir index (com filtro), show, relatório rota.
- **B\*:** mesmos cenários nas telas alteradas; regressão em cliente com **uma** operação e sem linha em `operacao_dados_clientes` (fallback).
- **C\*:** contrato da API documentado; Radar com comportamento explícito no README ou neste doc.

---

## 6. Referências no repositório

- Plano macro: `docs/PLANO_OPERACAO_DADOS_CLIENTE.md`
- Serviço: `App\Modules\Core\Services\OperacaoDadosClienteService`
- Radar: `App\Modules\Core\Controllers\RadarController.php` → `ClienteController::buscarPorCpf`
- Model `Cliente` (accessors): `getNomeAttribute`, `getTelefoneAttribute`, `getDadosEmpresaAtual()`, etc.

---

*Documento vivo — ajustar após decisões de produto (especialmente Radar e API).*

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
- Os **dados cadastrais** no cartão principal (nome, documento, vínculos `operation_clients`) continuam ancorados no **`Cliente`**; **contato por operação** (nome/telefone/e-mail da ficha) é exibido em bloco separado via **`fichas_por_operacao`** retornado por `buscarPorCpf` (Fase C1).
- A **agregação financeira** (ativos, pendências) permanece **global** no sistema, alinhada à ideia de bureau interno.

**Implicação:** Radar = **risco global** + **contatos por operação** (ficha) quando o usuário tem acesso às operações correspondentes.

---

## 2. Regra alvo (Fase 4 — eixo dados)

| Contexto | Fonte desejada para nome de exibição, telefone, e-mail, endereço, observações, responsável PJ |
|----------|------------------------------------------------------------------------------------------------|
| Usuário olha o cliente **no contexto da Operação X** (empréstimo, venda, filtro de operação, `?operacao_id=`) | Preferir **`operacao_dados_clientes`** para `(cliente_id, operacao_id)` quando existir linha; fallback documentado. |
| Identidade (CPF/CNPJ, tipo pessoa) | Continuar em **`clientes`**. |
| Tela explicitamente **cadastro geral** (`geral=1`, ou política super admin) | Pode usar **`clientes`** + regras atuais de `ClienteDadosEmpresa` / accessors. |
| Ferramenta **global de risco** (Radar) | Ver §1 — financeiro **agregado**; contato **por operação** via ficha na UI/API (C1). |

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
- **API JSON** (`buscarPorCpf` / uso no Radar e no create empréstimo): `cliente.telefone` / `cliente.email` continuam vindos do **`Cliente`** (accessors); além disso existe **`fichas_por_operacao`** (ficha por operação, filtrada às operações do usuário quando não super admin).

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

| # | Item | Ação sugerida | Estado |
|---|------|----------------|--------|
| B1 | `emprestimos/show`, `parcelas/atrasadas`, `cobrancas`, `liberacoes/show`, etc. | Onde houver **WhatsApp**, carregar ficha da operação do empréstimo e usar **fallback** `Cliente`/accessor. | **Feito** — `FichaContatoLookup` + `WhatsappLink::urlPreferindoFicha`; `pagamentos/*` sem botão WA no escopo atual. |
| B2 | Dashboards (admin/gestor/consultor) | Mesma regra onde houver **WhatsApp**. | **Feito** — consultor (listas de parcelas); admin/gestor na tabela **Parcelas vencidas**. |

### Fase C — API e Radar

| # | Item | Ação sugerida | Estado |
|---|------|----------------|--------|
| C1 | JSON de `buscarPorCpf` | Manter `cliente.telefone` / `email` (legado) **e** retornar **`fichas_por_operacao`**: array `{ operacao_id, operacao_nome, nome, telefone, email }` por linha em `operacao_dados_clientes`, filtrado às operações do usuário (exceto super admin). | **Feito** — `ClienteController::fichasPorOperacaoParaCliente`. |
| C2 | Radar / modais | Exibir bloco **Contato por operação (ficha)** quando houver itens em `fichas_por_operacao`. | **Feito** — `radar/index.blade.php` + `RadarController`; Swal **criar empréstimo** e **criar cliente** (consulta cruzada e mesma empresa). |

**Contrato da API:** `buscarPorCpf` mantém `cliente.telefone` e `cliente.email` (accessors) e acrescenta **`fichas_por_operacao`** (pode ser `[]`). Consumidores antigos ignoram a chave nova.

### Fase D — Modelo e legado

| # | Item | Ação sugerida |
|---|------|----------------|
| D1 | Accessors `Cliente` + `ClienteDadosEmpresa` | Decisão de produto: convivência até migração, ou reduzir override em telas sensíveis passando a **DTO/view model** com fonte explícita. |
| D2 | Notificações | Opcional: incluir nome da ficha da operação do empréstimo quando disponível. |

---

## 5. Critérios de pronto (por entrega)

- **A1–A3:** QA com cliente em **duas operações** com telefone/endereço **diferentes** na ficha; conferir index (com filtro), show, relatório rota.
- **B\*:** mesmos cenários nas telas alteradas; regressão em cliente com **uma** operação e sem linha em `operacao_dados_clientes` (fallback).
- **C\*:** cliente com fichas em **duas operações** (telefones distintos): conferir `fichas_por_operacao` na API, bloco no Radar e no modal Swal do create empréstimo; usuário só com uma operação vê só essa ficha; super admin vê todas (respeitando dados existentes).

---

## 6. Referências no repositório

- Plano macro: `docs/PLANO_OPERACAO_DADOS_CLIENTE.md`
- Serviço: `App\Modules\Core\Services\OperacaoDadosClienteService`
- Helpers: `App\Support\WhatsappLink`, `App\Support\FichaContatoLookup`
- Radar: `App\Modules\Core\Controllers\RadarController.php` → `ClienteController::buscarPorCpf`
- Model `Cliente` (accessors): `getNomeAttribute`, `getTelefoneAttribute`, `getDadosEmpresaAtual()`, etc.

---

*Documento vivo — ajustar após decisões de produto (especialmente Radar e API).*

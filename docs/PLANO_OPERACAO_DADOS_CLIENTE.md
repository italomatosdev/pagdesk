# Plano: dados de cliente por operação + anexos com `operacao_id`

**Estado atual (concluído):**
- Tabela `operacao_dados_clientes` criada e populada (backfill 1:1 com `operation_clients`).
- Coluna `client_documents.operacao_id` (nullable) criada; registros legados permanecem `NULL`.
- **Cadastro por link público** grava/atualiza `operacao_dados_clientes` e anexos com `operacao_id` (Fase 2).
- **CRUD interno (Fase 3 — parcial):** cadastro com operação grava ficha + `operacao_id` nos documentos; edição com `?operacao_id=` atualiza ficha e anexos da operação (link “Editar ficha” na tabela de vínculos do `show`). Listagens/buscas globais ainda podem usar só `clientes` até evolução da Fase 3/4.

**Objetivo de negócio:**
- Ficha cadastral (nome, contato, endereço, etc.) **por par** `(cliente_id, operacao_id)`.
- Identidade (`documento` / CPF-CNPJ) permanece em `clientes`.
- Anexos podem ser **por operação** quando `operacao_id` estiver preenchido; legado continua com `operacao_id = NULL`.

---

## Fase 1 — Camada de resolução de dados (core)

**Entregável:** um único lugar que responde “quais dados exibir/editar para este cliente nesta operação?”.

1. **Criar serviço** (ex.: `OperacaoDadosClienteService` ou métodos em `ClienteService`):
   - `obterParaOperacao(int $clienteId, int $operacaoId): ?OperacaoDadosCliente` (eager safe).
   - `salvarOuAtualizar(int $clienteId, int $operacaoId, array $dados, ?int $empresaIdOperacao): OperacaoDadosCliente`.
   - Regras:
     - Se existir linha em `operacao_dados_clientes` → usar para **exibição/edição** nesse contexto.
     - Se não existir (edge) → fallback: copiar de `clientes` ou criar linha on-read/on-write (definir política mínima).

2. **Não remover ainda** a lógica de `ClienteDadosEmpresa` + accessors em `Cliente` até a Fase 4 decidir o destino (convivência ou deprecação gradual).

**Critério de pronto:** testes unitários ou de feature mínimos no serviço; chamadas futuras passam por ele.

**Implementado (Fase 1):**
- `App\Modules\Core\Services\OperacaoDadosClienteService` — `obterParaOperacao`, `garantirRegistro`, `salvarOuAtualizar`, `payloadBrutoFromCliente`.
- Testes: `OperacaoDadosClienteServicePayloadTest.php` (sem banco); `OperacaoDadosClienteServicePersistenceTest.php` (`DatabaseTransactions`, grupo `database`).
- **PHPUnit:** rode **no host** (`composer install` + `./vendor/bin/phpunit ...`). O `phpunit.xml` força `DB_HOST=127.0.0.1` e `DB_DATABASE=cred` para o PHP da máquina não depender de `host.docker.internal` (que só resolve **dentro** do Docker). MySQL precisa estar acessível em `127.0.0.1` (ex.: serviço local ou porta publicada do container). CI/outro host: ajuste variáveis ou use `.env.testing`.
- `operacao-dados-clientes:backfill` usa `payloadBrutoFromCliente` do serviço.

---

## Fase 2 — Cadastro público via link (`CadastroClienteController`)

**Arquivo-chave:** `app/Modules/Core/Controllers/CadastroClienteController.php`.

1. **Cliente novo:** após `cadastrar()` + `vincularOperacao()`, criar/atualizar **`operacao_dados_clientes`** com os dados do formulário (mesma operação do `ref`).
2. **Cliente existente, mesma operação** (já vinculado): além de atualizar `clientes` (comportamento atual), **atualizar** `operacao_dados_clientes` para essa operação (alinhado à regra “ficha por operação”).
3. **Cliente existente, outra operação** (fluxo “CPF já em outra operação”):
   - **Parar** de depender só de `ClienteDadosEmpresa` como único override se a regra for “sempre por operação”.
   - Gravar em **`operacao_dados_clientes`** para `(cliente_id, operacao_id)` do link.
   - Manter `clientes` conforme política acordada (ex.: não sobrescrever identidade; opcionalmente sincronizar “cadastro mestre” só se produto pedir).
4. **Anexos (opcional nesta fase ou Fase 3):** ao criar `ClientDocument` neste fluxo, passar **`operacao_id`** da operação do link.

**Critério de pronto:** fluxos manuais: primeiro cadastro, segundo link mesma operação, segundo link outra operação; conferir linhas em `operacao_dados_clientes` e `operation_clients`.

**Implementado (Fase 2):**
- `CadastroClienteController`: chama `salvarOuAtualizar` no cadastro **novo** e no fluxo “CPF já existe, outra operação”. Se **já vinculado à mesma operação**, não grava nada — só redireciona para a página final com mensagem (“já cadastrado”; mantém `ClienteDadosEmpresa` no fluxo outra operação).
- `ClienteService::cadastrar`: aceita `operacao_id_documentos` nos dados; `processarDocumentos` grava `operacao_id` em `ClientDocument` quando informado (cadastro novo pelo link). Fluxo “CPF já existe, outra operação”: `processarDocumentosParaOperacao` após o vínculo, com o mesmo `operacao_id` do link.
- `ClientDocument`: `operacao_id` no `fillable` + relação `operacao()`.

---

## Fase 3 — CRUD interno (consultor/gestor/admin): criar/editar/listar cliente

1. **`ClienteController` + `ClienteService::cadastrar` / atualizações:** ao vincular ou criar cliente já com operação, garantir **linha em `operacao_dados_clientes`** para cada operação relevante.
2. **Telas `clientes/*`:** ao exibir/editar cliente **no contexto de uma operação** (quando houver `operacao_id` na rota ou no vínculo selecionado), carregar dados do serviço da Fase 1, não só `Cliente` “cru”.
3. **`Cliente::documentos()` / upload:** ao anexar arquivo com contexto de operação, preencher **`operacao_id`**.
4. **Listagens e buscas:** onde o nome/telefone importam “por rota”, considerar join ou subconsulta em `operacao_dados_clientes` filtrando por `operacao_id` (definir com calma para não quebrar busca global).

**Critério de pronto:** criar cliente internamente + editar + anexar com operação; `client_documents` novos com `operacao_id` quando aplicável.

**Implementado (Fase 3 — escopo atual):**
- `ClienteController::store`: `operacao_id_documentos` na criação; após `vincularOperacao`, `OperacaoDadosClienteService::salvarOuAtualizar` com `payloadFromFormularioValidado`. Se o CPF/CNPJ **já existir** em `clientes`, o fluxo espelha o link público: não cria novo cliente; se já vinculado à mesma operação → redirect com aviso; senão → `ClienteDadosEmpresa`, `vincularClienteEmpresa` (se preciso), `vincularOperacao`, `salvarOuAtualizar`, `processarDocumentosParaOperacao`.
- `ClienteController::edit`: **sempre** no contexto de operação. Sem `?operacao_id=`: se uma operação acessível → redirect com o id; se várias → tela `edit-escolher-operacao`; se nenhuma → volta ao `show` com erro. Com `operacao_id` válido: formulário com ficha da operação.
- `ClienteController::update`: `operacao_para_ficha_id` **obrigatório**; sempre `salvarOuAtualizar` na ficha da operação; uploads só via `processarDocumentosParaOperacao`. **Não** atualiza `clientes` nem `cliente_dados_empresa` neste fluxo — só a ficha por operação (+ documentos com `operacao_id`).
- `resources/views/clientes/edit.blade.php`: hidden + alerta de contexto de operação.
- `resources/views/clientes/show.blade.php`: coluna “Ficha” com link `clientes/{id}/edit?operacao_id=...`.
- **Formulário de edição com `?operacao_id=`:** `OperacaoDadosClienteService::valoresFormularioParaOperacao` — usa linha em `operacao_dados_clientes` ou fallback `payloadBrutoFromCliente`; view usa `data_get($formDefaultsOperacao, ...)`.
- **Edição — documentos:** lista principal filtra por `operacao_id` da ficha; bloco opcional “Outros documentos” (`operacao_id` null) só referência, sem alteração ao salvar.
- **Listagem/export (`clientes.index` / `export`):** com filtro por operação, busca por nome também em `operacao_dados_clientes` (nome, telefone, e-mail da ficha); tabela e CSV priorizam dados da ficha para exibição.
- **Pendente (evolução Fase 4):** accessors / telas que ainda leem só `clientes` sem contexto de operação.

**Detalhe da tela `show` (Fase 4 — em andamento):**
- `ClienteController::show`: query opcional **`?operacao_id=`**. Valida vínculo cliente–operação e permissão (super admin ou operação nas operações do usuário); se inválido → redirect sem query + flash de erro. Se ok → `operacaoContextoShow` com `id`, `nome` da operação e ficha (`OperacaoDadosCliente|null` via `obterParaOperacao`).
- **Sem `operacao_id`:** mesmo critério do `edit` — lista `operacoesVinculadasParaEdicaoFicha`; **uma** operação → redirect para `show?operacao_id=`; **várias** → view `show-escolher-operacao`; **nenhuma** (ex.: super admin, cliente sem vínculos) → página geral sem ficha por operação. **`?geral=1`** força essa visão geral (evita loop com “Ver cadastro geral” e com Voltar em telas de escolha).
- `resources/views/clientes/show.blade.php`: título e bloco de informações (nome, contato, endereço, observações, WhatsApp) **priorizam a ficha** quando há contexto; alerta com link “cadastro geral (sem filtro)” (`geral=1`); botão Editar e tabela de vínculos com **Ver** / **Editar** por operação (`show` / `edit` com `operacao_id`).
- **`clientes.index`:** com filtro por operação ativo, o botão **Ver** abre `clientes.show` **com** `operacao_id` na URL para manter o mesmo contexto.

---

## Fase 4 — Convivência com `ClienteDadosEmpresa` e accessors do `Cliente`

Hoje `Cliente` usa accessors que misturam **empresa criadora** vs **`cliente_dados_empresa`**.

1. **Documentar regra alvo:** para usuário vendo cliente na **Operação X**, a fonte é **`operacao_dados_clientes`** (par cliente + operação X).
2. **Refatorar gradualmente:**
   - Onde a tela já tem `operacao_id`, passar a usar o serviço da Fase 1.
   - Reduzir dependência de accessors “mágicos” para telas sensíveis, ou estender accessors com parâmetro implícito (difícil) — preferir **DTO/view model** por tela.
3. **Decisão explícita:** manter `ClienteDadosEmpresa` só para casos legados / outra empresa, ou migrar dados para `operacao_dados_clientes` e depreciar (fora do escopo imediato).

**Critério de pronto:** nenhuma regressão nas telas críticas; super admin / multi-empresa validado.

**Avanço recente (links com contexto):**
- Helper `App\Support\ClienteUrl::show($clienteId, ?$operacaoId)` para montar `clientes.show` com `operacao_id` quando a tela tem operação (empréstimo, venda, parcelas atrasadas, liberações, cheques, garantias, renovações com filtro, radar com um único vínculo visível).
- Busca global (`SearchController`): URL do cliente com `operacao_id` quando o usuário tem exatamente **uma** operação em comum com o cliente.

---

## Fase 5 — Backfill opcional de `client_documents.operacao_id`

**Somente se** produto exigir histórico classificado por operação.

1. Definir regra (ex.: cliente com **uma** operação → preencher; com **várias** → `NULL` ou escolher operação “principal”).
2. Comando Artisan idempotente + `--dry-run`.
3. Rodar em staging, depois produção.

**Critério de pronto:** contagem de documentos com `operacao_id` coerente com a regra; UI filtra corretamente.

---

## Fase 6 — Documentação e operação

1. Atualizar `docs/` (fluxo de cadastro, backup, troubleshooting).
2. Atualizar `README` ou runbook: ordem deploy (migrate → backfill `operacao_dados_clientes` já feito; futuros backfills documentados).

---

## Ordem de execução recomendada

| Ordem | Fase | Risco |
|------:|------|--------|
| 1 | Fase 1 — Serviço de resolução | Baixo |
| 2 | Fase 2 — Link público | Médio (impacto direto no cliente final) |
| 3 | Fase 3 — CRUD interno + anexos | Médio |
| 4 | Fase 4 — Accessors / empresa | Alto (efeitos colaterais) |
| 5 | Fase 5 — Backfill anexos | Baixo/Médio (depende da regra) |
| 6 | Fase 6 — Docs | Baixo |

---

## Próximo passo imediato (implementação)

Priorizar **listagens/buscas** considerando `operacao_dados_clientes` quando fizer sentido ao produto; em seguida **Fase 4** (accessors / convivência com `ClienteDadosEmpresa`).

---

*Documento vivo — ajustar conforme decisões de produto (especialmente Fase 4 e Fase 5).*

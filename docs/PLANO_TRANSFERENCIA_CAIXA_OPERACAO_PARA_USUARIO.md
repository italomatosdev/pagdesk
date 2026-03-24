# Plano de execução — Transferência (Caixa da Operação → gestor/admin)

## 1. Objetivo

Permitir que **apenas o administrador da operação** transfira valor do **Caixa da Operação** (`consultor_id = NULL`) para o **caixa de um responsável** (gestor ou administrador) **na mesma operação**, em um único fluxo com **dois lançamentos pareados**:

1. **Saída** no Caixa da Operação (`consultor_id` NULL).  
2. **Entrada** no caixa do **destinatário** (`consultor_id` = usuário escolhido).

Nome de produto: **Transferência** (espelho conceitual da **Sangria**, que é pessoa → operação).

---

## 2. Escopo funcional

| Item | Definição |
|------|-----------|
| **Quem executa** | Usuário com papel **apenas administrador** na operação em questão: `temAlgumPapelNaOperacao($operacaoId, ['administrador'])` **e** **não** liberar gestor nesta v1 (regra explícita: só **admin da operação**). |
| **Origem** | **Caixa da Operação** na `operacao_id` escolhida (`consultor_id` NULL). |
| **Destino** | Um **usuário** da mesma operação com papel **gestor** ou **administrador** (validar `temAlgumPapelNaOperacao($operacaoId, ['gestor','administrador'])` no destinatário). **Não** permitir consultor como destino na v1 (opcional: fase futura). |
| **Valor** | `0 < valor <= saldo do Caixa da Operação` na operação (`CashService::calcularSaldoOperacao($operacaoId)`), com arredondamento a 2 casas. |
| **Destinatário: outro ou si mesmo** | **Decisão:** permitir **ambos** — transferência para **outro** gestor/admin **ou** para **si mesmo** (`destinatario_id === auth()->id()`). Quando o destino for o próprio executor, exibir **aviso explícito** na UI (ex.: alerta Bootstrap) antes de confirmar: *“O valor sairá do Caixa da Operação e entrará no seu caixa pessoal nesta operação.”* |
| **Fora de escopo (v1)** | Múltiplas operações em um clique; destino consultor; gestor como executor; estorno automático. |

---

## 3. Comportamento contábil (ledger)

**Padrão alinhado à Sangria:** `origem = automatica` nos dois lançamentos; categorias dedicadas via `CashCategoriaAutomaticaService` (ex.: `transferencia_caixa_operacao|saida` e `|entrada`), **sem** novo valor em `origem`.

Em **uma transação** (`DB::transaction`):

1. **Saída** — `tipo = saida`, `operacao_id`, **`consultor_id = NULL`**, `origem = automatica`, `valor`, `data_movimentacao`, descrição padronizada (ex.: `Transferência do Caixa da Operação → [nome destino]`), `referencia_tipo` sugerido: `transferencia_caixa_operacao`, `referencia_id` após criar o par (ver §3.1).
2. **Entrada** — `tipo = entrada`, mesma `operacao_id`, **`consultor_id = destinatario_id`**, mesmo `valor`, mesma data, descrição espelhada (ex.: `Transferência recebida — Caixa da Operação`), mesmo `referencia_tipo`, `referencia_id` ligando ao primeiro lançamento (igual ao padrão da sangria: entrada referencia a saída).

**Saldo:** a soma “total da operação” no modelo atual permanece coerente; muda apenas a **composição** (menos no caixa NULL, mais no usuário destino).

### 3.1 Comprovante (opcional)

Mesmo critério da sangria: arquivo **opcional**; se informado, **mesmo** `comprovante_path` nos dois lançamentos; armazenamento `comprovantes/transferencia-operacao` (ou prefixo unificado com sangria, alinhar na implementação).

---

## 4. Camadas técnicas

| Camada | Ação |
|--------|------|
| **Service** | Ex.: `CashService::transferirDoCaixaOperacaoParaUsuario(int $executorId, int $operacaoId, int $destinatarioId, float $valor, ?string $observacoes, ?string $comprovantePath): array` — valida saldo do caixa da operação, papel **admin** do executor na operação, destinatário gestor/admin na operação, executor com acesso à operação; cria os dois lançamentos; audita. |
| **Controller** | `GET/POST` em rotas sob `caixa` (ex.: `caixa/transferencia-operacao/create`, `caixa/transferencia-operacao`), mesma área da sangria. |
| **View** | Formulário: operação, **saldo do Caixa da Operação** (read-only), select **destinatário** (gestores/admins da operação), valor, observações, comprovante opcional. Botão de acesso **somente** se `temAlgumPapelNaOperacao(..., ['administrador'])` **e** não super admin bloqueado pelo `CashController` (ver §5). |
| **Listagem** | Filtro por `referencia_tipo = transferencia_caixa_operacao` na tela de movimentações; badge na coluna Referência. |

---

## 5. Regras de negócio e segurança

- **Executor:** obrigatório **administrador na operação**; **gestor sem papel administrador** → 403.  
- **Super Admin:** manter política atual do módulo Caixa (se hoje não acessa caixa, não executa esta ação; se no futuro passar a acessar, definir regra explícita).  
- **Destinatário:** deve pertencer à operação com papel **gestor** ou **administrador**.  
- **Saldo:** recalcular **dentro da transação** antes de debitar (`calcularSaldoOperacao`).  
- **Concorrência:** mesma mitigação da sangria (transação + revalidação de saldo).  

---

## 6. UX (resumo)

| Item | Definição |
|------|-----------|
| **Onde** | Mesma filosofia da sangria: **tela de Movimentações de Caixa** (`/caixa`), botão visível **apenas para administrador da operação** (pelo menos uma operação em que o usuário seja admin). **Sem** novo item obrigatório no sidebar (opcional link duplicado depois). |
| **Texto** | Título: **Transferência do Caixa da Operação**; ajuda curta explicando que só **admin** executa e que o valor sai do **caixa da casa** para o **caixa do destinatário**. **Se destinatário = usuário logado:** mostrar aviso (§2, linha “Destinatário”). Opcional: reforçar no modal de confirmação. |
| **Pós-sucesso** | Redirect para `caixa.index` com filtros `operacao_id` + `referencia_tipo=transferencia_caixa_operacao`. |

---

## 7. Testes (mínimo)

- Admin com saldo Caixa da Operação 500 transfere 200 para gestor → `calcularSaldoOperacao` desce 300; saldo do gestor sobe 200.  
- Valor > saldo do caixa da operação → erro.  
- Gestor (não admin) tenta acessar rota → 403.  
- Destino = consultor → 422/validação.  
- Destino sem vínculo na operação → erro.  
- Destino = próprio admin (si mesmo) → permitido; saldos coerentes (Caixa da Operação ↓, caixa do usuário ↑).  
- Rollback se segundo insert falhar.  

---

## 8. Ordem de implementação sugerida

1. Mapeamento em `CashCategoriaAutomaticaService` para `transferencia_caixa_operacao|saida` e `|entrada`.  
2. `CashService::transferirDoCaixaOperacaoParaUsuario` (+ comprovante opcional).  
3. `CashController` + rotas + validações.  
4. View + botão condicional em `caixa/index` (só admin).  
5. Filtro e badge na listagem; `CHANGELOG.md` / cruzamento com `MODELO_CAIXA_OPERACAO.md`.  

---

## 9. Relação com a Sangria

| Fluxo | Direção | Executor (v1) |
|-------|---------|-----------------|
| **Sangria** | Pessoa → Caixa da Operação | Gestor ou admin |
| **Transferência** (este plano) | Caixa da Operação → Pessoa | **Apenas administrador da operação** |

---

## 10. Dependências

- `cash_ledger_entries` com `consultor_id` nullable.  
- `calcularSaldoOperacao` e `registrarMovimentacao` existentes.  

---

*Implementado em 2026-01-24 (ver `CHANGELOG.md`).*

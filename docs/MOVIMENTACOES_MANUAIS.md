# Movimentações Manuais de Caixa

## Visão Geral

O sistema permite a criação de movimentações manuais de caixa (entradas e saídas) para registrar operações financeiras que não estão diretamente relacionadas ao fluxo de empréstimos. Esta funcionalidade é restrita a **Gestores** e **Administradores**.

## Objetivo

Permitir o registro completo de todas as movimentações financeiras do sistema, incluindo:
- Aportes de capital
- Despesas operacionais
- Transferências entre operações/consultores
- Ajustes e correções
- Reembolsos
- Outras receitas/despesas

## Arquitetura

### Modelo de Dados

**Tabela:** `cash_ledger_entries`

**Campos adicionados:**
- `origem` (VARCHAR 20): Tipo de origem da movimentação
  - `'automatica'`: Movimentações criadas automaticamente pelo sistema (padrão)
  - `'manual'`: Movimentações criadas manualmente por gestores/administradores
- `comprovante_path` (VARCHAR nullable): Caminho do arquivo de comprovante (se houver)

### Estrutura de Arquivos

```
app/Modules/Cash/
├── Controllers/
│   └── CashController.php          # Métodos create() e store() para movimentações manuais
├── Models/
│   └── CashLedgerEntry.php         # Modelo com métodos isManual() e isAutomatica()
└── Services/
    └── CashService.php             # Método registrarMovimentacao() atualizado

resources/views/caixa/
├── index.blade.php                  # Listagem com botão e coluna de origem
└── movimentacao/
    └── create.blade.php            # Formulário de criação

database/migrations/
├── 2026_01_20_023033_add_origem_to_cash_ledger_entries_table.php
├── 2026_01_20_023118_add_comprovante_to_cash_ledger_entries_table.php
└── 2026_01_20_023224_update_existing_cash_ledger_entries_origem.php
```

## Funcionalidades

### 1. Criação de Movimentação Manual

**Acesso:** Apenas Gestores e Administradores

**Rota:** `GET /caixa/movimentacao/create`

**Formulário inclui:**
- Tipo: Entrada ou Saída (obrigatório)
- Operação: Seleção de operação (obrigatório)
- Consultor: Seleção de consultor (obrigatório)
- Valor: Valor da movimentação (obrigatório, mínimo R$ 0,01)
- Data da Movimentação: Data (obrigatório, não pode ser futura)
- Descrição: Descrição da movimentação (obrigatório, máx. 255 caracteres)
- Observações: Informações adicionais (opcional, máx. 1000 caracteres)
- Comprovante: Upload de arquivo PDF ou imagem (opcional, máx. 2MB)

### 2. Validações Implementadas

**Validações de Negócio:**
- Usuário deve ter acesso à operação selecionada
- Consultor selecionado deve pertencer à operação
- Usuário selecionado deve ter papel de consultor
- Data não pode ser futura
- Valor deve ser positivo

**Validações de Arquivo:**
- Tipos aceitos: PDF, JPG, JPEG, PNG
- Tamanho máximo: 2MB

### 3. Listagem de Movimentações

**Melhorias na visualização:**
- Coluna "Origem" adicionada à tabela
- Badge diferenciado:
  - **Manual** (amarelo): Movimentações criadas manualmente
  - **Automática** (azul): Movimentações criadas pelo sistema
- Botão "Nova Movimentação Manual" no cabeçalho (apenas gestor/admin)

## Fluxo de Funcionamento

### Movimentações Automáticas (Sistema)

1. **Liberação de Dinheiro (Gestor → Consultor)**
   - Saída no caixa do gestor/operação
   - Entrada no caixa do consultor
   - Origem: `'automatica'`

2. **Pagamento de Parcela**
   - Entrada no caixa do consultor
   - Origem: `'automatica'`

3. **Confirmação de Pagamento ao Cliente**
   - Saída no caixa do consultor
   - Origem: `'automatica'`

### Movimentações Manuais (Gestor/Admin)

1. Gestor/Admin acessa "Movimentações de Caixa"
2. Clica em "Nova Movimentação Manual"
3. Preenche o formulário:
   - Seleciona tipo (Entrada/Saída)
   - Seleciona operação e consultor
   - Informa valor, data e descrição
   - Opcionalmente anexa comprovante
4. Confirma criação (Sweet Alert)
5. Sistema valida permissões e dados
6. Movimentação é criada com origem `'manual'`
7. Saldo do consultor é atualizado automaticamente

## Permissões e Segurança

### Controle de Acesso

- **Consultores:** Apenas visualização de suas próprias movimentações
- **Gestores:** Podem criar movimentações para consultores de suas operações
- **Administradores:** Podem criar movimentações para qualquer consultor

### Validações de Segurança

- Middleware `auth` em todas as rotas
- Middleware `role:gestor,administrador` nas rotas de criação
- Validação de acesso à operação antes de criar
- Validação de vínculo consultor-operacao
- Auditoria de todas as movimentações criadas

## Métricas e Relatórios

### Cards de Métricas

A tela de movimentações exibe 4 cards principais:

1. **Saldo Atual:** Saldo total do consultor/operação
2. **Total Entradas:** Soma de todas as entradas no período filtrado
3. **Total Saídas:** Soma de todas as saídas no período filtrado
4. **Diferença do Período:** Entradas - Saídas no período filtrado

**Nota:** Todas as métricas consideram movimentações automáticas e manuais.

## Auditoria

Todas as movimentações manuais são registradas na tabela `audit_logs` com:
- Ação: `'registrar_movimentacao_caixa'`
- Usuário que criou
- Dados completos da movimentação
- Timestamp

## Migrations Necessárias

Execute as seguintes migrations na ordem:

```bash
php artisan migrate
```

1. `add_origem_to_cash_ledger_entries_table` - Adiciona campo origem
2. `add_comprovante_to_cash_ledger_entries_table` - Adiciona campo comprovante_path
3. `update_existing_cash_ledger_entries_origem` - Atualiza registros existentes

## Exemplos de Uso

### Exemplo 1: Aporte de Capital
- **Tipo:** Entrada
- **Descrição:** "Aporte inicial de capital para operação"
- **Valor:** R$ 50.000,00
- **Comprovante:** Transferência bancária

### Exemplo 2: Despesa Operacional
- **Tipo:** Saída
- **Descrição:** "Pagamento de aluguel do escritório"
- **Valor:** R$ 3.500,00
- **Comprovante:** Boleto pago

### Exemplo 3: Transferência entre Consultores
- **Tipo:** Saída (consultor A) + Entrada (consultor B)
- **Descrição:** "Transferência de recursos entre consultores"
- **Valor:** R$ 1.000,00

## Considerações Importantes

1. **Impacto no Saldo:** Movimentações manuais afetam diretamente o saldo do consultor
2. **Rastreabilidade:** Todas as movimentações são auditadas
3. **Comprovantes:** Recomenda-se sempre anexar comprovante para movimentações manuais
4. **Data:** Não é possível criar movimentações com data futura
5. **Permissões:** Apenas gestores e administradores podem criar movimentações manuais

## Troubleshooting

### Problema: Botão não aparece
**Solução:** Verifique se o usuário tem papel de gestor ou administrador

### Problema: Erro ao criar movimentação
**Solução:** 
- Verifique se o consultor pertence à operação selecionada
- Verifique se a data não é futura
- Verifique se o arquivo de comprovante não excede 2MB

### Problema: Movimentações antigas sem origem
**Solução:** Execute a migration `update_existing_cash_ledger_entries_origem`

## Melhorias Futuras (Sugestões)

1. Edição de movimentações manuais
2. Exclusão de movimentações manuais (com permissão especial)
3. Filtro por origem na listagem
4. Exportação de relatórios de movimentações
5. Categorização de movimentações manuais (Aporte, Despesa, Transferência, etc.)
6. Aprovação de movimentações manuais acima de determinado valor

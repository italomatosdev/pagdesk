# Modelo de Caixa: Operação e Usuários

## Visão Geral

O sistema implementa um modelo flexível de caixa que permite movimentações tanto no **caixa central da operação** quanto no **caixa individual de gestores e consultores**, sempre vinculados a uma operação específica.

## Conceito Fundamental

### O Dinheiro Pertence à Operação

- **Todas as movimentações** são sempre vinculadas a uma `operacao_id`
- O dinheiro **tecnicamente pertence à operação**, não a pessoas individuais
- Gestores e consultores são **responsáveis temporários** pelo dinheiro

### Três Níveis de Caixa

1. **Caixa da Operação** (`consultor_id = NULL`)
   - Recursos não alocados a nenhum usuário específico
   - Usado para: aportes de capital, despesas operacionais gerais, ajustes contábeis

2. **Caixa do Gestor** (`consultor_id = id_do_gestor`)
   - Dinheiro sob responsabilidade de um gestor específico
   - Usado para: receber prestações de contas dos consultores, liberar dinheiro para consultores

3. **Caixa do Consultor** (`consultor_id = id_do_consultor`)
   - Dinheiro sob responsabilidade de um consultor específico
   - Usado para: receber liberações do gestor, pagar clientes, receber parcelas

## Estrutura de Dados

### Tabela: `cash_ledger_entries`

```sql
- id (PK)
- operacao_id (FK, obrigatório) → Sempre vinculado a uma operação
- consultor_id (FK, nullable) → NULL = caixa da operação, preenchido = caixa do usuário
- tipo (enum: 'entrada', 'saida')
- origem (enum: 'automatica', 'manual')
- valor (decimal)
- descricao (string)
- observacoes (text, nullable)
- comprovante_path (string, nullable)
- data_movimentacao (date)
- referencia_tipo (string, nullable) → Tipo de origem (ex: 'liberacao_emprestimo')
- referencia_id (int, nullable) → ID da origem
```

### Regras de Negócio

1. **`operacao_id`**: Sempre obrigatório
2. **`consultor_id`**: 
   - `NULL` = Movimentação do caixa central da operação
   - Preenchido = Movimentação do caixa de um usuário específico (gestor ou consultor)
3. **Movimentações automáticas**: Sempre têm `consultor_id` preenchido (fluxo do sistema)
4. **Movimentações manuais**: Podem ter `consultor_id` NULL (caixa da operação) ou preenchido

## Fluxos de Movimentação

### 1. Aporte de Capital (Caixa da Operação)

**Cenário**: Investidor faz aporte de R$ 50.000,00 na operação

```
Movimentação:
- operacao_id: 1
- consultor_id: NULL (caixa da operação)
- tipo: entrada
- origem: manual
- valor: 50000.00
- descricao: "Aporte inicial de capital para operação"
```

**Impacto**: 
- Caixa da operação: +R$ 50.000,00
- Caixa de gestores/consultores: não alterado

### 2. Gestor Retira Dinheiro da Operação

**Cenário**: Gestor precisa de R$ 10.000,00 para operar em campo

**Movimentação 1 - Saída da Operação:**
```
- operacao_id: 1
- consultor_id: NULL
- tipo: saida
- origem: manual
- valor: 10000.00
- descricao: "Retirada para operação de campo - Gestor João"
```

**Movimentação 2 - Entrada no Caixa do Gestor:**
```
- operacao_id: 1
- consultor_id: id_do_gestor
- tipo: entrada
- origem: manual
- valor: 10000.00
- descricao: "Recebimento de recursos da operação"
```

**Impacto**:
- Caixa da operação: -R$ 10.000,00
- Caixa do gestor: +R$ 10.000,00

### 3. Liberação de Dinheiro (Gestor → Consultor)

**Cenário**: Gestor libera R$ 1.000,00 para consultor pagar cliente

**Movimentação 1 - Saída do Caixa do Gestor:**
```
- operacao_id: 1
- consultor_id: id_do_gestor
- tipo: saida
- origem: automatica
- valor: 1000.00
- descricao: "Liberação para consultor Maria - Empréstimo #123"
```

**Movimentação 2 - Entrada no Caixa do Consultor:**
```
- operacao_id: 1
- consultor_id: id_do_consultor
- tipo: entrada
- origem: automatica
- valor: 1000.00
- descricao: "Liberação de dinheiro recebida - Empréstimo #123"
```

**Impacto**:
- Caixa do gestor: -R$ 1.000,00
- Caixa do consultor: +R$ 1.000,00
- Caixa da operação: não alterado (já estava no gestor)

### 4. Prestação de Contas (Consultor → Gestor)

**Cenário**: Consultor devolve R$ 500,00 ao gestor

**Movimentação 1 - Saída do Caixa do Consultor:**
```
- operacao_id: 1
- consultor_id: id_do_consultor
- tipo: saida
- origem: manual (ou automatica via settlement)
- valor: 500.00
- descricao: "Prestação de contas - Período 01/2026"
```

**Movimentação 2 - Entrada no Caixa do Gestor:**
```
- operacao_id: 1
- consultor_id: id_do_gestor
- tipo: entrada
- origem: manual (ou automatica via settlement)
- valor: 500.00
- descricao: "Recebimento de prestação de contas - Consultor Maria"
```

**Impacto**:
- Caixa do consultor: -R$ 500,00
- Caixa do gestor: +R$ 500,00

### 5. Despesa Operacional (Caixa da Operação)

**Cenário**: Pagamento de aluguel do escritório

```
Movimentação:
- operacao_id: 1
- consultor_id: NULL
- tipo: saida
- origem: manual
- valor: 3500.00
- descricao: "Pagamento de aluguel do escritório - Janeiro/2026"
```

**Impacto**:
- Caixa da operação: -R$ 3.500,00
- Caixa de gestores/consultores: não alterado

## Cálculos de Saldo

### Saldo do Caixa da Operação

```php
$saldoOperacao = calcularSaldoOperacao($operacaoId);
// Soma todas as entradas - saídas onde consultor_id IS NULL
```

### Saldo de um Usuário (Gestor ou Consultor)

```php
$saldoUsuario = calcularSaldo($userId, $operacaoId);
// Soma todas as entradas - saídas onde consultor_id = $userId
```

### Saldo Total da Operação (Todos os Caixas)

```php
$saldoTotal = calcularSaldoTotal($operacaoId);
// Soma todas as entradas - saídas da operação (incluindo caixa da operação + todos os usuários)
```

## Permissões e Segurança

### Criação de Movimentações Manuais

- **Apenas Gestores e Administradores** podem criar movimentações manuais
- **Validações**:
  - Usuário deve ter acesso à operação
  - Se `consultor_id` for preenchido, o usuário deve pertencer à operação
  - Se `consultor_id` for NULL, descrição deve ter pelo menos 20 caracteres (auditoria)

### Visualização

- **Consultores**: Veem apenas seu próprio caixa
- **Gestores**: Veem caixa próprio + caixa dos consultores de suas operações + caixa da operação
- **Administradores**: Veem tudo

## Vantagens do Modelo

### 1. Flexibilidade Operacional

- Suporta múltiplos gestores na mesma operação
- Permite movimentações gerais sem vincular a pessoa específica
- Mantém rastreabilidade completa

### 2. Rastreabilidade

- Sabe exatamente onde está cada centavo
- Pode rastrear fluxo completo: Operação → Gestor → Consultor → Cliente
- Auditoria completa de todas as movimentações

### 3. Relatórios Precisos

- Pode gerar relatórios por:
  - Operação (total)
  - Gestor (individual)
  - Consultor (individual)
  - Período específico

### 4. Segurança

- Validações em múltiplas camadas
- Auditoria obrigatória
- Permissões granulares
- Soft deletes para recuperação

## Exemplos Práticos

### Exemplo 1: Múltiplos Gestores

**Operação**: "Operação Sul"

- **Gestor A** tem R$ 5.000,00 em caixa
- **Gestor B** tem R$ 3.000,00 em caixa
- **Caixa da Operação** tem R$ 20.000,00

**Total da Operação**: R$ 28.000,00

### Exemplo 2: Fluxo Completo

1. Aporte de R$ 50.000,00 → Caixa da Operação: R$ 50.000,00
2. Gestor retira R$ 10.000,00 → Caixa da Operação: R$ 40.000,00 | Gestor: R$ 10.000,00
3. Gestor libera R$ 1.000,00 para consultor → Gestor: R$ 9.000,00 | Consultor: R$ 1.000,00
4. Consultor paga cliente R$ 1.000,00 → Consultor: R$ 0,00
5. Cliente paga parcela R$ 100,00 → Consultor: R$ 100,00
6. Consultor presta contas R$ 100,00 → Consultor: R$ 0,00 | Gestor: R$ 9.100,00

## Migrations Necessárias

```bash
php artisan migrate
```

A migration `make_consultor_id_nullable_in_cash_ledger_entries_table` torna o campo `consultor_id` nullable, permitindo movimentações do caixa da operação.

## Troubleshooting

### Problema: Não consigo criar movimentação sem consultor

**Solução**: Verifique se você tem papel de gestor ou administrador. Apenas esses papéis podem criar movimentações manuais.

### Problema: Saldo não está batendo

**Solução**: 
- Verifique se está filtrando corretamente por `operacao_id`
- Verifique se está considerando `consultor_id` NULL para caixa da operação
- Verifique se há movimentações com soft delete

### Problema: Movimentação criada mas não aparece

**Solução**:
- Verifique os filtros aplicados (operacao_id, consultor_id, datas)
- Verifique se o usuário tem acesso à operação
- Verifique se não há soft delete

## Melhorias Futuras (Sugestões)

1. **Validação de Saldo**: Bloquear saídas quando saldo insuficiente (opcional por operação)
2. **Aprovação de Movimentações**: Movimentações acima de X valor precisam de aprovação
3. **Categorização**: Adicionar categoria de movimentação (Aporte, Despesa, Transferência, etc.)
4. **Reconciliação**: Ferramenta para reconciliar saldos esperados vs calculados
5. **Alertas**: Notificar quando saldo de operação ficar abaixo de X valor
6. **Histórico de Saldos**: Tabela de snapshot de saldos por data para relatórios históricos

# Fluxo de Caixa - Liberação de Dinheiro

## 💰 Movimentações Criadas

Quando o gestor libera dinheiro para o consultor, o sistema cria **DUAS movimentações de caixa**:

### 1. Saída no Caixa do Gestor

**Perspectiva**: Dinheiro saindo do caixa do gestor/operação

```
Tipo: SAÍDA
Consultor: gestor_id (gestor que liberou)
Operação: operacao_id
Valor: valor_liberado
Descrição: "Liberação para consultor [Nome do Consultor] - Empréstimo #X"
Referência: liberacao_emprestimo, liberacao_id
```

**Exemplo:**
- Gestor: João Silva (ID: 5)
- Consultor: Maria Santos (ID: 10)
- Valor: R$ 1.000,00
- Empréstimo: #123

**Movimentação criada:**
```
Tipo: SAÍDA
Consultor: 5 (João Silva - gestor)
Valor: R$ 1.000,00
Descrição: "Liberação para consultor Maria Santos - Empréstimo #123"
```

### 2. Entrada no Caixa do Consultor

**Perspectiva**: Dinheiro entrando no caixa do consultor

```
Tipo: ENTRADA
Consultor: consultor_id (consultor que recebe)
Operação: operacao_id
Valor: valor_liberado
Descrição: "Liberação de dinheiro recebida - Empréstimo #X"
Referência: liberacao_emprestimo, liberacao_id
```

**Exemplo:**
- Consultor: Maria Santos (ID: 10)
- Valor: R$ 1.000,00
- Empréstimo: #123

**Movimentação criada:**
```
Tipo: ENTRADA
Consultor: 10 (Maria Santos)
Valor: R$ 1.000,00
Descrição: "Liberação de dinheiro recebida - Empréstimo #123"
```

## 📊 Impacto nos Saldos

### Saldo do Gestor

```
Saldo Anterior: R$ 10.000,00
- Saída (liberação): R$ 1.000,00
= Saldo Atual: R$ 9.000,00
```

### Saldo do Consultor

```
Saldo Anterior: R$ 500,00
+ Entrada (liberação): R$ 1.000,00
= Saldo Atual: R$ 1.500,00
```

## 🔄 Fluxo Completo de Movimentações

### 1. Gestor Libera Dinheiro

```
Gestor libera R$ 1.000,00
    ↓
SAÍDA no caixa do gestor: -R$ 1.000,00
ENTRADA no caixa do consultor: +R$ 1.000,00
```

### 2. Consultor Confirma Pagamento ao Cliente

```
Consultor confirma pagamento de R$ 1.000,00
    ↓
SAÍDA no caixa do consultor: -R$ 1.000,00
```

### 3. Cliente Paga Parcelas

```
Cliente paga parcela de R$ 100,00
    ↓
ENTRADA no caixa do consultor: +R$ 100,00
```

## 💡 Por Que Duas Movimentações?

### Rastreabilidade Completa

- **Sabe de onde saiu**: Caixa do gestor
- **Sabe para onde foi**: Caixa do consultor
- **Auditoria completa**: Duas movimentações vinculadas pela mesma referência

### Saldos Corretos

- **Gestor**: Saldo diminui (dinheiro saiu)
- **Consultor**: Saldo aumenta (dinheiro entrou)
- **Operação**: Pode ver movimentações de todos

### Relatórios Precisos

- Pode ver quanto cada gestor liberou
- Pode ver quanto cada consultor recebeu
- Pode rastrear o fluxo completo

## 📝 Exemplo Prático

### Cenário

- **Gestor**: João (ID: 5)
- **Consultor**: Maria (ID: 10)
- **Valor**: R$ 1.000,00
- **Empréstimo**: #123

### Movimentações Criadas

**1. Saída do Gestor:**
```
ID: 1001
Tipo: SAÍDA
Consultor: 5 (João)
Operação: 1
Valor: R$ 1.000,00
Descrição: "Liberação para consultor Maria Santos - Empréstimo #123"
Referência: liberacao_emprestimo, liberacao_id: 50
```

**2. Entrada do Consultor:**
```
ID: 1002
Tipo: ENTRADA
Consultor: 10 (Maria)
Operação: 1
Valor: R$ 1.000,00
Descrição: "Liberação de dinheiro recebida - Empréstimo #123"
Referência: liberacao_emprestimo, liberacao_id: 50
```

**Ambas têm a mesma referência** (`liberacao_id: 50`), permitindo rastrear que são parte da mesma operação.

## ✅ Benefícios

1. **Rastreabilidade**: Sabe exatamente de onde saiu e para onde foi
2. **Saldos Corretos**: Cada pessoa tem seu saldo atualizado corretamente
3. **Auditoria**: Histórico completo de movimentações
4. **Relatórios**: Pode gerar relatórios por gestor, consultor, operação
5. **Transparência**: Fluxo de dinheiro totalmente visível

## 🔍 Como Verificar

### No Caixa do Gestor

```sql
SELECT * FROM cash_ledger_entries 
WHERE consultor_id = 5 -- ID do gestor
AND tipo = 'saida'
AND referencia_tipo = 'liberacao_emprestimo';
```

### No Caixa do Consultor

```sql
SELECT * FROM cash_ledger_entries 
WHERE consultor_id = 10 -- ID do consultor
AND tipo = 'entrada'
AND referencia_tipo = 'liberacao_emprestimo';
```

### Ambas as Movimentações

```sql
SELECT * FROM cash_ledger_entries 
WHERE referencia_tipo = 'liberacao_emprestimo'
AND referencia_id = 50; -- ID da liberação
```

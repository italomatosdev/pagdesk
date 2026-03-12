# Valor de Aprovação Automática por Operação

## 📋 Funcionalidade

Cada operação pode ter um **valor de aprovação automática** configurado. Empréstimos com valor menor ou igual a este valor são **aprovados automaticamente**, ignorando validações de dívida ativa e limite de crédito.

## 🎯 Objetivo

Agilizar a aprovação de empréstimos de pequeno valor, permitindo que consultores trabalhem com mais autonomia para valores menores, enquanto valores maiores ainda passam pelas validações normais.

## ⚙️ Como Funciona

### Lógica de Aprovação

```
Empréstimo criado
    ↓
Operação tem valor_aprovacao_automatica configurado?
    ↓ SIM
Valor do empréstimo ≤ valor_aprovacao_automatica?
    ↓ SIM
✅ APROVADO AUTOMATICAMENTE
    (Ignora dívida ativa e limite de crédito)
    ↓ NÃO
Aplicar validações normais:
    - Tem dívida ativa?
    - Excede limite de crédito?
    ↓
✅ Aprovado ou ⏳ Pendente
```

### Comportamento

1. **Se valor ≤ valor_aprovacao_automatica**:
   - ✅ Aprovado automaticamente
   - Ignora dívida ativa
   - Ignora limite de crédito
   - Status: `aprovado` → `ativo`

2. **Se valor > valor_aprovacao_automatica**:
   - Aplica validações normais
   - Verifica dívida ativa
   - Verifica limite de crédito
   - Status: `aprovado` ou `pendente` conforme validações

3. **Se valor_aprovacao_automatica = null**:
   - Comportamento padrão (sempre valida)
   - Aplica todas as validações

## 📝 Configuração

### Adicionar/Editar na Operação

1. Acesse **Operações** → **Editar Operação**
2. Preencha o campo **"Valor de Aprovação Automática"**
3. Salve a operação

### Campo no Banco de Dados

- **Tabela**: `operacoes`
- **Campo**: `valor_aprovacao_automatica`
- **Tipo**: `decimal(15,2)`
- **Nullable**: Sim (null = desabilitado)

## 💡 Exemplos Práticos

### Exemplo 1: Operação com R$ 500,00

**Configuração:**
- Operação: "Operação Principal"
- `valor_aprovacao_automatica`: R$ 500,00

**Cenários:**

| Valor Empréstimo | Dívida Ativa | Limite | Resultado |
|-----------------|--------------|--------|-----------|
| R$ 300,00 | Sim | Excedido | ✅ **Aprovado** (≤ R$ 500) |
| R$ 500,00 | Sim | Excedido | ✅ **Aprovado** (≤ R$ 500) |
| R$ 501,00 | Não | OK | ✅ **Aprovado** (validações OK) |
| R$ 501,00 | Sim | OK | ⏳ **Pendente** (validações) |
| R$ 1.000,00 | Sim | Excedido | ⏳ **Pendente** (validações) |

### Exemplo 2: Operação sem Limite

**Configuração:**
- Operação: "Operação Secundária"
- `valor_aprovacao_automatica`: `null` (não configurado)

**Comportamento:**
- Todos os empréstimos passam pelas validações normais
- Sempre verifica dívida ativa e limite de crédito

## 🔄 Apenas Novos Empréstimos

**Importante**: A configuração afeta **apenas novos empréstimos** criados após a edição da operação.

- ✅ Empréstimos criados **depois** da edição → Usam nova configuração
- ❌ Empréstimos criados **antes** da edição → Não são afetados

Isso garante que:
- Empréstimos antigos mantêm seu status original
- Mudanças na configuração não afetam empréstimos já criados
- Histórico permanece consistente

## 📊 Fluxo Completo

### 1. Criar/Editar Operação

```php
// No formulário
valor_aprovacao_automatica: R$ 500,00
```

### 2. Criar Empréstimo

```php
// EmprestimoService::criar()
$operacao = Operacao::find($operacao_id);
$valorAprovacaoAutomatica = $operacao->valor_aprovacao_automatica;

if ($valorAprovacaoAutomatica && $valorEmprestimo <= $valorAprovacaoAutomatica) {
    // Aprovado automaticamente
    $status = 'aprovado';
} else {
    // Validações normais
    // ...
}
```

### 3. Resultado

- **Aprovado**: Status `ativo`, parcelas geradas
- **Pendente**: Aguarda aprovação manual

## 🛡️ Validações

### Validação no Controller

```php
'valor_aprovacao_automatica' => 'nullable|numeric|min:0'
```

- **nullable**: Pode ser vazio (desabilitado)
- **numeric**: Deve ser numérico
- **min:0**: Não pode ser negativo

## 📝 Auditoria

A auditoria registra quando um empréstimo é aprovado automaticamente:

```
"Empréstimo aprovado automaticamente (valor dentro do limite de aprovação automática da operação)"
```

## ✅ Benefícios

1. **Agilidade**: Empréstimos pequenos aprovados instantaneamente
2. **Autonomia**: Consultores podem trabalhar com valores menores sem esperar aprovação
3. **Flexibilidade**: Cada operação pode ter seu próprio limite
4. **Controle**: Valores maiores ainda passam pelas validações
5. **Configurável**: Pode ser ajustado a qualquer momento
6. **Segurança**: Apenas novos empréstimos são afetados

## 🔍 Verificação

Para verificar se um empréstimo foi aprovado automaticamente:

1. Ver o status do empréstimo (`ativo` = aprovado)
2. Verificar a auditoria (mensagem específica)
3. Comparar valor do empréstimo com `valor_aprovacao_automatica` da operação

## 📚 Referências

- Migration: `2024_12_20_100010_add_valor_aprovacao_automatica_to_operacoes_table.php`
- Model: `app/Modules/Core/Models/Operacao.php`
- Service: `app/Modules/Loans/Services/EmprestimoService.php`
- Views: `resources/views/operacoes/create.blade.php`, `edit.blade.php`, `show.blade.php`

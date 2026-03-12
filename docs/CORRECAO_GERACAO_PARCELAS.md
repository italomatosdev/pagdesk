# Correção: Geração Automática de Parcelas

## 🐛 Problema Identificado

As parcelas **não estavam sendo geradas automaticamente** quando o empréstimo era criado com status 'pendente'.

### Comportamento Anterior

- ✅ Parcelas geradas apenas quando status = 'aprovado'
- ❌ Parcelas **NÃO** geradas quando status = 'pendente'
- ❌ Empréstimos pendentes ficavam sem parcelas até serem aprovados

### Problema

Se um empréstimo fosse criado como 'pendente' (por ter dívida ativa ou limite excedido), as parcelas não eram geradas. Isso causava:
- Empréstimos sem parcelas visíveis
- Necessidade de aprovar primeiro para ver as parcelas
- Confusão sobre quando as parcelas seriam geradas

## ✅ Correção Implementada

### Mudança Principal

**Agora as parcelas são geradas SEMPRE na criação do empréstimo**, independente do status (pendente, aprovado, etc.).

### Código Atualizado

**Antes:**
```php
// Se aprovado automaticamente, gerar parcelas
if ($status === 'aprovado') {
    $this->gerarParcelas($emprestimo);
    $emprestimo->update(['status' => 'ativo']);
}
```

**Depois:**
```php
// Sempre gerar parcelas ao criar o empréstimo
// As parcelas ficam pendentes até o empréstimo ser aprovado
$this->gerarParcelas($emprestimo);

// Se aprovado automaticamente, atualizar status para ativo
if ($status === 'aprovado') {
    $emprestimo->update(['status' => 'ativo']);
}
```

### Proteção contra Duplicação

Adicionada verificação no método `gerarParcelas()` para evitar gerar parcelas duplicadas:

```php
public function gerarParcelas(Emprestimo $emprestimo): void
{
    // Verificar se já existem parcelas geradas
    if ($emprestimo->parcelas()->count() > 0) {
        return; // Parcelas já foram geradas, não gerar novamente
    }
    
    // ... resto do código
}
```

### Ajuste no Método `aprovar()`

O método `aprovar()` agora verifica se as parcelas já existem antes de tentar gerar:

```php
// Verificar se parcelas já foram geradas (geradas na criação)
// Se não existirem, gerar agora (para empréstimos antigos)
if ($emprestimo->parcelas()->count() === 0) {
    $this->gerarParcelas($emprestimo);
}
```

## 📊 Comportamento Atual

### Fluxo de Criação

1. **Empréstimo criado** (qualquer status)
2. **Parcelas geradas automaticamente** ✅
3. **Status definido**:
   - Se sem dívida ativa e dentro do limite → 'aprovado' → 'ativo'
   - Se com dívida ativa ou limite excedido → 'pendente'

### Fluxo de Aprovação

1. **Empréstimo pendente é aprovado**
2. **Verifica se parcelas existem**:
   - Se existem → apenas atualiza status para 'ativo'
   - Se não existem → gera parcelas (para empréstimos antigos)

## ✅ Benefícios

1. **Parcelas sempre visíveis**: Mesmo empréstimos pendentes mostram as parcelas
2. **Transparência**: Cliente/consultor vê o calendário de pagamento desde o início
3. **Consistência**: Todos os empréstimos têm parcelas geradas
4. **Segurança**: Proteção contra duplicação de parcelas

## 🧪 Como Testar

1. Criar um empréstimo com status 'pendente'
2. Verificar se as parcelas foram geradas
3. Aprovar o empréstimo
4. Verificar se as parcelas continuam as mesmas (não duplicadas)

## 📝 Notas

- As parcelas são geradas com status 'pendente' inicialmente
- O valor das parcelas já inclui os juros (se houver)
- As datas de vencimento são calculadas conforme a frequência (diária, semanal, mensal)
- Empréstimos antigos (criados antes da correção) terão parcelas geradas na aprovação

# Sistema Price - Empréstimo com Amortização (Parcela Fixa)

## 📋 Visão Geral

O **Sistema Price** é um novo tipo de empréstimo que utiliza sistema de amortização similar a financiamentos bancários, onde cada parcela tem valor fixo, mas é composta por juros (decrescentes) e amortização (crescente), com rastreamento do saldo devedor.

**Status:** ✅ Implementado  
**Tipo de Empréstimo:** `price`  
**Compatibilidade:** Empréstimos antigos (`dinheiro`) continuam funcionando normalmente

---

## 🎯 Conceito

### Diferença entre Sistemas

| Aspecto | Empréstimo Dinheiro (Juros Simples) | Empréstimo Price (Amortização) |
|---------|-------------------------------------|--------------------------------|
| **Cálculo de Juros** | Juros simples sobre valor total | Juros sobre saldo devedor restante |
| **Valor da Parcela** | Fixo (mas juros não decrescem) | Fixo (juros decrescem, amortização cresce) |
| **Estrutura** | Valor total + juros ÷ parcelas | Tabela de amortização completa |
| **Saldo Devedor** | Não rastreado | Rastreado e reduzido a cada parcela |
| **Transparência** | Mostra valor total e parcela | Mostra juros, amortização e saldo por parcela |

### Exemplo Prático

**Empréstimo:** R$ 10.000,00  
**Taxa:** 2% ao mês  
**Parcelas:** 5x mensais  
**Sistema:** Price

| Parcela | Valor Parcela | Juros (2%) | Amortização | Saldo Devedor |
|---------|---------------|------------|-------------|---------------|
| 0 | - | - | - | R$ 10.000,00 |
| 1 | R$ 2.121,58 | R$ 200,00 | R$ 1.921,58 | R$ 8.078,42 |
| 2 | R$ 2.121,58 | R$ 161,57 | R$ 1.960,01 | R$ 6.118,41 |
| 3 | R$ 2.121,58 | R$ 122,37 | R$ 1.999,21 | R$ 4.119,20 |
| 4 | R$ 2.121,58 | R$ 82,38 | R$ 2.039,20 | R$ 2.080,00 |
| 5 | R$ 2.121,58 | R$ 41,58 | R$ 2.080,00 | R$ 0,00 |

**Observações:**
- ✅ Parcela fixa: R$ 2.121,58
- ✅ Juros diminuem a cada parcela (calculados sobre saldo restante)
- ✅ Amortização aumenta a cada parcela
- ✅ Saldo devedor reduz progressivamente até zero

---

## 🗄️ Estrutura de Dados

### Tabela `emprestimos`

**Novo campo:**
```php
'tipo' => enum('dinheiro', 'price', 'troca_cheque', 'empenho')
// Default: 'dinheiro' (para compatibilidade)
```

### Tabela `parcelas`

**Novos campos (nullable para compatibilidade):**
```php
'valor_juros' => decimal(15,2) // Juros da parcela
'valor_amortizacao' => decimal(15,2) // Amortização da parcela
'saldo_devedor' => decimal(15,2) // Saldo após esta parcela
```

**Importante:**
- Campos são `nullable` para não quebrar parcelas existentes
- Apenas preenchidos quando `emprestimo.tipo = 'price'`
- Empréstimos antigos (`tipo = 'dinheiro'`) continuam com `NULL` nesses campos

---

## 📐 Fórmulas e Cálculos

### Fórmula Price (Parcela Fixa)

```
P = PV × [i(1+i)^n] / [(1+i)^n - 1]

Onde:
- P = Valor da Parcela (fixo)
- PV = Valor Presente (valor_total do empréstimo)
- i = Taxa de juros (taxa_juros / 100)
- n = Número de parcelas
```

### Cálculo por Parcela

Para cada parcela:
1. **Juros** = Saldo Devedor Anterior × Taxa de Juros
2. **Amortização** = Valor da Parcela - Juros
3. **Saldo Devedor** = Saldo Devedor Anterior - Amortização

### Implementação no Código

**Model `Emprestimo`:**
```php
public function calcularParcelaPrice(): float
{
    $taxaDecimal = $this->taxa_juros / 100;
    $numerador = $taxaDecimal * pow(1 + $taxaDecimal, $this->numero_parcelas);
    $denominador = pow(1 + $taxaDecimal, $this->numero_parcelas) - 1;
    return $this->valor_total * ($numerador / $denominador);
}
```

---

## 🔄 Fluxo Completo

### 1. Criação do Empréstimo

```
1. Consultor acessa "Novo Empréstimo"
2. Seleciona "Tipo: Financiamento (Sistema Price)"
3. Preenche:
   - Cliente
   - Valor total
   - Número de parcelas
   - Frequência (diária, semanal, mensal)
   - Taxa de juros (obrigatória para Price)
4. Sistema calcula parcela fixa automaticamente
5. Sistema mostra preview da tabela de amortização
6. Consultor confirma criação
7. Sistema gera parcelas com juros, amortização e saldo devedor
```

### 2. Validações

- ✅ Taxa de juros obrigatória se tipo = `price`
- ✅ Taxa de juros > 0
- ✅ Número de parcelas > 0
- ✅ Valor total > 0

### 3. Geração de Parcelas

O sistema gera automaticamente:
- Valor da parcela (fixo)
- Juros (calculados sobre saldo devedor)
- Amortização (parcela - juros)
- Saldo devedor (reduzido progressivamente)

### 4. Visualização

**Na página de detalhes do empréstimo:**
- Tabela de amortização completa (se tipo = `price`)
- Colunas: Parcela, Valor Parcela, Juros, Amortização, Saldo Devedor, Status
- Lista de parcelas com detalhes de juros e amortização

### 5. Pagamento

- Ao pagar parcela, sistema valida valor (deve ser igual à parcela fixa)
- Status da parcela é atualizado
- Saldo devedor já está calculado (não precisa recalcular)

---

## 💻 Implementação Técnica

### Migrations

**1. Adicionar campo `tipo` em `emprestimos`:**
```php
$table->enum('tipo', ['dinheiro', 'price', 'troca_cheque', 'empenho'])
      ->default('dinheiro')
      ->after('status');
```

**2. Adicionar campos em `parcelas`:**
```php
$table->decimal('valor_juros', 15, 2)->nullable();
$table->decimal('valor_amortizacao', 15, 2)->nullable();
$table->decimal('saldo_devedor', 15, 2)->nullable();
```

### Models

**`Emprestimo`:**
- `isPrice()`: Verifica se é tipo Price
- `isDinheiro()`: Verifica se é tipo dinheiro
- `calcularParcelaPrice()`: Calcula parcela fixa (fórmula Price)
- `gerarTabelaAmortizacaoPrice()`: Gera tabela completa

**`Parcela`:**
- `isPrice()`: Verifica se pertence a empréstimo Price
- `getJurosAttribute()`: Retorna valor dos juros
- `getAmortizacaoAttribute()`: Retorna valor da amortização

### Services

**`EmprestimoService`:**
- `gerarParcelas()`: Verifica tipo e chama método apropriado
- `gerarParcelasPrice()`: Gera parcelas com amortização
- `gerarParcelasSimples()`: Gera parcelas com juros simples (método atual)

### Controllers

**`EmprestimoController`:**
- Validação: taxa de juros obrigatória se tipo = `price`
- Validação: tipo deve ser `dinheiro` ou `price`

### Views

**`emprestimos/create.blade.php`:**
- Campo de seleção de tipo
- Preview da tabela de amortização (JavaScript)
- Validação: taxa de juros obrigatória para Price

**`emprestimos/show.blade.php`:**
- Tabela de amortização completa (se tipo = `price`)
- Detalhes de juros e amortização nas parcelas

---

## ✅ Compatibilidade

### Empréstimos Existentes

- ✅ Todos recebem `tipo = 'dinheiro'` (via migration default)
- ✅ Continuam funcionando normalmente
- ✅ Campos novos em `parcelas` ficam `NULL` (não afetam)

### Queries e Relatórios

```php
// Filtrar por tipo
$emprestimosDinheiro = Emprestimo::where('tipo', 'dinheiro')->get();
$emprestimosPrice = Emprestimo::where('tipo', 'price')->get();

// Em relatórios, verificar tipo antes de calcular
if ($emprestimo->isPrice()) {
    // Usar saldo_devedor das parcelas
} else {
    // Usar cálculo atual (juros simples)
}
```

---

## 🎨 Interface do Usuário

### Formulário de Criação

**Campo de Seleção:**
- "Empréstimo em Dinheiro (Juros Simples)" - padrão
- "Financiamento (Sistema Price - Parcela Fixa)"

**Preview da Tabela:**
- Aparece automaticamente ao selecionar "Price"
- Atualiza em tempo real conforme valores são preenchidos
- Mostra: Parcela, Valor Parcela, Juros, Amortização, Saldo Devedor

### Página de Detalhes

**Tabela de Amortização:**
- Card destacado com borda azul
- Tabela completa com todas as parcelas
- Cores: verde (paga), vermelho (atrasada), amarelo (pendente)
- Explicação do sistema Price abaixo da tabela

**Lista de Parcelas:**
- Mostra juros e amortização em cada parcela (se Price)
- Mantém funcionalidades existentes (pagamento, detalhes, etc.)

---

## 📊 Exemplos de Uso

### Exemplo 1: Empréstimo Mensal

```
Valor: R$ 5.000,00
Taxa: 3% ao mês
Parcelas: 10x mensais
Sistema: Price

Parcela Fixa: R$ 586,15
Total a Pagar: R$ 5.861,50
Juros Totais: R$ 861,50
```

### Exemplo 2: Empréstimo Semanal

```
Valor: R$ 1.000,00
Taxa: 1% por semana
Parcelas: 12x semanais
Sistema: Price

Parcela Fixa: R$ 88,85
Total a Pagar: R$ 1.066,20
Juros Totais: R$ 66,20
```

---

## 🔍 Validações e Regras

### Validações Obrigatórias

- ✅ Taxa de juros > 0 (se tipo = `price`)
- ✅ Número de parcelas > 0
- ✅ Valor total > 0
- ✅ Tipo deve ser `dinheiro` ou `price`

### Regras de Negócio

- ✅ Price funciona com qualquer frequência (diária, semanal, mensal)
- ✅ Taxa de juros é aplicada conforme a frequência
- ✅ Última parcela ajusta para garantir saldo zero
- ✅ Não permite mudança de tipo após criação

---

## 🚀 Vantagens

### Para o Cliente

- ✅ Transparência: vê exatamente quanto está pagando de juros e amortização
- ✅ Padrão de mercado: similar a financiamentos bancários
- ✅ Juros justos: calculados sobre o saldo real, não sobre o valor total

### Para o Sistema

- ✅ Não mexe nos empréstimos existentes
- ✅ Código separado por tipo (fácil manutenção)
- ✅ Escalável (fácil adicionar SAC no futuro)
- ✅ Compatível com relatórios existentes
- ✅ Interface condicional (mostra o que é relevante)

---

## 📝 Notas Técnicas

### Precisão Decimal

- Usa `round()` com 2 casas decimais
- Última parcela ajusta para garantir saldo zero
- Cálculos feitos com `pow()` para precisão

### Performance

- Cálculos feitos na criação (não em tempo de execução)
- Tabela de amortização gerada uma vez
- Campos indexados para consultas rápidas

### Segurança

- Validações no controller e service
- Taxa de juros limitada a 0-100%
- Valores sempre positivos

---

## 🔮 Futuras Melhorias

### Possíveis Expansões

1. **Sistema SAC (Sistema de Amortização Constante)**
   - Amortização fixa
   - Parcela decrescente
   - Juros decrescentes

2. **Pagamento Parcial**
   - Recalcular saldo devedor
   - Ajustar juros das parcelas futuras

3. **Antecipação de Parcelas**
   - Desconto proporcional
   - Recalcular tabela

4. **Relatórios Avançados**
   - Gráfico de evolução do saldo devedor
   - Comparação entre sistemas
   - Exportação de tabela (PDF/Excel)

---

## 📚 Referências

- **Fórmula Price:** Tabela Price (Sistema Francês de Amortização)
- **Padrão:** Similar a financiamentos bancários e imobiliários
- **Documentação:** Este documento + código comentado

---

## ✅ Checklist de Implementação

- [x] Migration: campo `tipo` em `emprestimos`
- [x] Migration: campos de amortização em `parcelas`
- [x] Model `Emprestimo`: métodos Price
- [x] Model `Parcela`: novos campos e métodos
- [x] Service: geração de parcelas Price
- [x] Controller: validações
- [x] View: formulário com campo tipo
- [x] View: preview da tabela (JavaScript)
- [x] View: tabela de amortização na página de detalhes
- [x] Documentação completa

---

**Última atualização:** 22/01/2026  
**Versão:** 1.0.0

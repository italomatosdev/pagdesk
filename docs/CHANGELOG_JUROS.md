# Changelog - Correção do Cálculo de Juros

## [2024-12-20] - Correção: Aplicação de Juros no Cálculo de Parcelas

### ✅ Corrigido

**Problema**: O campo `taxa_juros` era salvo no banco, mas não estava sendo aplicado no cálculo do valor das parcelas.

**Solução**: Atualizado o método `calcularValorParcela()` para aplicar os juros automaticamente.

### 📝 Mudanças

#### 1. Model `Emprestimo` (`app/Modules/Loans/Models/Emprestimo.php`)

**Métodos Adicionados:**
- `calcularValorTotalComJuros()`: Retorna o valor total com juros aplicados
- `calcularValorJuros()`: Retorna apenas o valor dos juros

**Método Atualizado:**
- `calcularValorParcela()`: Agora aplica os juros antes de dividir pelas parcelas

**Antes:**
```php
public function calcularValorParcela(): float
{
    if ($this->numero_parcelas > 0) {
        return round($this->valor_total / $this->numero_parcelas, 2);
    }
    return 0;
}
```

**Depois:**
```php
public function calcularValorParcela(): float
{
    if ($this->numero_parcelas > 0) {
        $valorTotalComJuros = $this->calcularValorTotalComJuros();
        return round($valorTotalComJuros / $this->numero_parcelas, 2);
    }
    return 0;
}
```

#### 2. View `emprestimos/show.blade.php`

**Melhorias:**
- Exibe "Valor do Empréstimo" (sem juros)
- Exibe "Taxa de Juros" (se houver)
- Exibe "Valor dos Juros" (se houver)
- Exibe "Valor Total a Pagar" (com juros)
- Exibe "Valor da Parcela" (já com juros aplicados)

### 📊 Exemplo de Cálculo

**Antes (Incorreto):**
- Valor Total: R$ 1.000,00
- Taxa de Juros: 30%
- Parcelas: 30x
- **Valor da Parcela: R$ 33,33** ❌ (sem juros)

**Depois (Correto):**
- Valor do Empréstimo: R$ 1.000,00
- Taxa de Juros: 30%
- Valor dos Juros: R$ 300,00
- Valor Total a Pagar: R$ 1.300,00
- Parcelas: 30x
- **Valor da Parcela: R$ 43,33** ✅ (com juros)

### 🔧 Fórmula Aplicada

```
Valor Total com Juros = Valor Total × (1 + Taxa Juros / 100)
Valor da Parcela = Valor Total com Juros ÷ Número de Parcelas
```

### ✅ Testes

Para testar:
1. Criar um empréstimo com valor R$ 1.000,00
2. Definir taxa de juros de 30%
3. Definir 30 parcelas
4. Verificar se o valor da parcela é R$ 43,33 (não R$ 33,33)

### 📝 Notas

- Se `taxa_juros` for 0 ou null, o cálculo funciona normalmente (sem juros)
- O valor dos juros é calculado sobre o valor total do empréstimo
- O arredondamento é feito com 2 casas decimais

# Explicação: Número de Parcelas vs Frequência

## 📊 Conceitos

### Número de Parcelas
**O que é**: Quantas vezes o cliente vai pagar o empréstimo.

**Exemplos**:
- 10 parcelas = cliente vai pagar 10 vezes
- 30 parcelas = cliente vai pagar 30 vezes
- 1 parcela = pagamento único

### Frequência
**O que é**: Com que intervalo de tempo cada parcela será gerada/cobrada.

**Opções**:
- **Diária**: Uma parcela por dia
- **Semanal**: Uma parcela por semana
- **Mensal**: Uma parcela por mês

## 🔄 Como Funcionam Juntos

A combinação de **Número de Parcelas** + **Frequência** define:
1. **Quantas parcelas** serão criadas
2. **Quando cada parcela vence**

### Exemplos Práticos

#### Exemplo 1: Empréstimo Mensal
- **Valor Total**: R$ 1.000,00
- **Número de Parcelas**: 10
- **Frequência**: Mensal
- **Data de Início**: 01/01/2024

**Resultado**:
- 10 parcelas de R$ 100,00 cada
- Vencimentos:
  - Parcela 1: 01/01/2024
  - Parcela 2: 01/02/2024
  - Parcela 3: 01/03/2024
  - ...
  - Parcela 10: 01/10/2024
- **Duração total**: 10 meses

#### Exemplo 2: Empréstimo Semanal
- **Valor Total**: R$ 1.000,00
- **Número de Parcelas**: 10
- **Frequência**: Semanal
- **Data de Início**: 01/01/2024

**Resultado**:
- 10 parcelas de R$ 100,00 cada
- Vencimentos:
  - Parcela 1: 01/01/2024
  - Parcela 2: 08/01/2024
  - Parcela 3: 15/01/2024
  - ...
  - Parcela 10: 04/03/2024
- **Duração total**: 10 semanas (~2,5 meses)

#### Exemplo 3: Empréstimo Diário
- **Valor Total**: R$ 1.000,00
- **Número de Parcelas**: 10
- **Frequência**: Diária
- **Data de Início**: 01/01/2024

**Resultado**:
- 10 parcelas de R$ 100,00 cada
- Vencimentos:
  - Parcela 1: 01/01/2024
  - Parcela 2: 02/01/2024
  - Parcela 3: 03/01/2024
  - ...
  - Parcela 10: 10/01/2024
- **Duração total**: 10 dias

## 💡 Resumo

| Número de Parcelas | Frequência | Significado |
|-------------------|------------|-------------|
| 10 | Mensal | 10 meses de pagamento |
| 10 | Semanal | 10 semanas de pagamento |
| 10 | Diária | 10 dias de pagamento |
| 30 | Mensal | 30 meses (2,5 anos) |
| 52 | Semanal | 52 semanas (1 ano) |
| 365 | Diária | 365 dias (1 ano) |

## 🎯 Cálculo do Valor da Parcela

O sistema calcula automaticamente:
```
Valor da Parcela = Valor Total ÷ Número de Parcelas
```

**Exemplo**:
- R$ 1.000,00 ÷ 10 parcelas = R$ 100,00 por parcela

## 📅 Geração Automática

O sistema gera as parcelas automaticamente quando o empréstimo é aprovado:

1. **Primeira parcela**: Vence na data de início
2. **Parcelas seguintes**: Vencem conforme a frequência:
   - **Diária**: +1 dia
   - **Semanal**: +7 dias
   - **Mensal**: +1 mês

## 🔍 No Código

A lógica está em `EmprestimoService::gerarParcelas()`:

```php
// Para cada parcela (1 até numero_parcelas)
for ($i = 1; $i <= $emprestimo->numero_parcelas; $i++) {
    // Se não for a primeira, adiciona intervalo conforme frequência
    if ($i > 1) {
        switch ($emprestimo->frequencia) {
            case 'diaria': $dataVencimento->addDay(); break;
            case 'semanal': $dataVencimento->addWeek(); break;
            case 'mensal': $dataVencimento->addMonth(); break;
        }
    }
    // Cria a parcela com data de vencimento calculada
}
```

## ✅ Conclusão

- **Número de Parcelas**: Define **quantas** parcelas serão criadas
- **Frequência**: Define **quando** cada parcela vence (intervalo entre elas)
- Juntos, definem a **duração total** do empréstimo e o **calendário de pagamento**

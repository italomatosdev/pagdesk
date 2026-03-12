# Melhoria na Exibição de Próximas Cobranças

## Problema Identificado

A coluna "Dias" na tabela de "Próximas Cobranças" estava exibindo valores numéricos simples (ex: "7 dias", "3 dias"), o que:
- Poderia gerar valores quebrados ou confusos
- Era redundante com a data de vencimento já exibida
- Não transmitia claramente a urgência de cada cobrança

## Solução Implementada

### Mudanças Realizadas

1. **Remoção da coluna "Dias"**
   - A coluna foi removida do cabeçalho da tabela
   - O `colspan` no estado vazio foi ajustado de 6 para 5 colunas

2. **Badge amigável na coluna "Vencimento"**
   - A data continua sendo exibida normalmente (formato: `dd/mm/yyyy`)
   - Um badge colorido foi adicionado ao lado da data com texto amigável

### Formatação dos Badges

A lógica de formatação segue a seguinte regra:

| Condição | Badge | Cor | Texto |
|----------|-------|-----|-------|
| Vence hoje | Vermelho | `bg-danger` | "Hoje" |
| Vence amanhã | Amarelo | `bg-warning` | "Amanhã" |
| 2-6 dias | Azul | `bg-info` | "Em X dias" |
| 7 dias | Cinza | `bg-secondary` | "Em 1 semana" |
| Mais de 7 dias | Cinza | `bg-secondary` | "Em X dias" |

### Exemplo Visual

**Antes:**
```
Vencimento        | Dias
------------------|----------
18/01/2026        | 3 dias
19/01/2026        | 2 dias
20/01/2026        | 1 dias
```

**Depois:**
```
Vencimento
--------------------------
18/01/2026 [Em 3 dias]
19/01/2026 [Amanhã]
20/01/2026 [Hoje]
```

## Arquivos Modificados

1. **`resources/views/dashboard/consultor.blade.php`**
   - Removida coluna "Dias" do cabeçalho
   - Adicionada lógica PHP para calcular e formatar o badge amigável
   - Badge exibido ao lado da data usando `d-flex` e `gap-2`
   - Ajustado `colspan` no estado vazio

## Benefícios

1. **Mais intuitivo**: Textos como "Hoje" e "Amanhã" são mais claros que números
2. **Melhor hierarquia visual**: Cores diferentes indicam urgência
3. **Menos redundância**: Remove informação duplicada
4. **Melhor UX**: Usuário entende rapidamente a urgência sem precisar calcular

## Observações Técnicas

- A lógica usa `Carbon::diffInDays()` para calcular a diferença
- O cálculo considera apenas dias completos (não horas)
- Badges são renderizados usando classes Bootstrap 5
- A formatação é responsiva e mantém o alinhamento visual

## Próximas Melhorias Possíveis

- Adicionar ícones aos badges (ex: ⚠️ para "Hoje", 📅 para "Amanhã")
- Agrupar cobranças por período na tabela
- Adicionar filtro por período (Hoje, Esta Semana, Próxima Semana)
- Destacar visualmente cobranças de alto valor

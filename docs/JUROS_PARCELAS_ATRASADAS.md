# Sistema de Juros/Multa para Parcelas Atrasadas

## Visão Geral

Foi implementado um sistema completo de juros e multas para parcelas atrasadas, permitindo que consultores apliquem diferentes tipos de cobrança adicional ao registrar pagamentos de parcelas em atraso.

## Funcionalidades Implementadas

### 1. Configuração por Operação

Cada operação pode ter configurada:
- **Taxa de Juros por Atraso**: Taxa percentual (ex.: 1.5 = 1,5%)
- **Tipo de Cálculo**: 
  - `por_dia`: Taxa multiplicada pelos dias de atraso
  - `por_mes`: Taxa aplicada proporcionalmente (dias/30)

**Localização**: `operacoes/create.blade.php` e `operacoes/edit.blade.php`

### 2. Opções de Juros no Pagamento

Quando uma parcela está atrasada, o consultor tem 4 opções ao registrar o pagamento:

#### Opção 1: Sem Juros
- Paga apenas o valor original da parcela
- Nenhum valor adicional é cobrado

#### Opção 2: Juros Automático
- Usa a taxa configurada na operação
- Cálculo automático baseado em dias de atraso
- Só aparece se a operação tiver `taxa_juros_atraso > 0`

**Fórmula**:
- Por dia: `juros = valor_parcela × (taxa / 100) × dias_atraso`
- Por mês: `juros = valor_parcela × (taxa / 100) × (dias_atraso / 30)`

#### Opção 3: Juros Manual
- Consultor informa uma taxa % no momento do pagamento
- Cálculo automático baseado na taxa informada e dias de atraso

**Fórmula**: `juros = valor_parcela × (taxa_informada / 100) × dias_atraso`

#### Opção 4: Valor Fixo Manual
- Consultor informa um valor fixo em R$ diretamente
- Não depende de dias ou taxa

**Fórmula**: `juros = valor_fixo_informado`

### 3. Interface Interativa

A interface do formulário de pagamento:
- Mostra informações da parcela (valor, vencimento, dias de atraso)
- Exibe a taxa da operação (se configurada)
- Calcula juros em tempo real conforme a opção selecionada
- Atualiza automaticamente o campo "Valor do Pagamento"
- Campos condicionais aparecem/desaparecem conforme a seleção

### 4. Registro Completo

Todos os dados são registrados na tabela `pagamentos`:
- `tipo_juros`: 'nenhum', 'automatico', 'manual', 'fixo'
- `taxa_juros_aplicada`: Taxa usada (para automático e manual)
- `valor_juros`: Valor calculado ou fixo
- `valor`: Valor total pago (original + juros)

### 5. Exibição nos Detalhes

No modal de detalhes do pagamento (`emprestimos/show.blade.php`):
- Valor original da parcela
- Tipo de juros aplicado
- Taxa aplicada (se aplicável)
- Valor de juros/multa
- Valor total pago

## Estrutura de Dados

### Tabela `operacoes` (novos campos)
```sql
taxa_juros_atraso DECIMAL(5,2) DEFAULT 0
tipo_calculo_juros ENUM('por_dia', 'por_mes') DEFAULT 'por_dia'
```

### Tabela `pagamentos` (novos campos)
```sql
tipo_juros ENUM('nenhum', 'automatico', 'manual', 'fixo') DEFAULT NULL
taxa_juros_aplicada DECIMAL(5,2) DEFAULT NULL
valor_juros DECIMAL(10,2) DEFAULT 0
```

## Arquivos Modificados

### Migrations
1. `2026_01_19_184133_add_juros_atraso_to_operacoes_table.php`
2. `2026_01_19_184134_add_juros_to_pagamentos_table.php`

### Models
1. `app/Modules/Core/Models/Operacao.php`
   - Adicionados campos `taxa_juros_atraso` e `tipo_calculo_juros`

2. `app/Modules/Loans/Models/Pagamento.php`
   - Adicionados campos `tipo_juros`, `taxa_juros_aplicada`, `valor_juros`
   - Métodos: `hasJuros()`, `getDescricaoTipoJurosAttribute()`

### Services
1. `app/Modules/Loans/Services/PagamentoService.php`
   - Método `calcularJuros()`: Calcula juros conforme tipo selecionado
   - Método `registrar()`: Atualizado para incluir juros no pagamento

### Controllers
1. `app/Modules/Loans/Controllers/PagamentoController.php`
   - Validação dos campos de juros
   - Validação condicional (taxa_manual requerida se tipo=manual, etc.)

2. `app/Modules/Core/Controllers/OperacaoController.php`
   - Validação dos campos `taxa_juros_atraso` e `tipo_calculo_juros`

### Views
1. `resources/views/pagamentos/create.blade.php`
   - Interface completa com 4 opções de juros
   - JavaScript para cálculo em tempo real
   - Campos condicionais

2. `resources/views/emprestimos/show.blade.php`
   - Exibição de juros no modal de detalhes do pagamento

3. `resources/views/operacoes/create.blade.php`
   - Campos para configurar taxa de juros

4. `resources/views/operacoes/edit.blade.php`
   - Campos para editar taxa de juros

## Como Usar

### 1. Configurar Taxa na Operação

1. Acesse **Operações** → **Editar Operação**
2. Na seção "Configuração de Juros por Atraso":
   - Informe a **Taxa de Juros por Atraso** (ex.: 1.5 para 1,5%)
   - Selecione o **Tipo de Cálculo** (Por Dia ou Por Mês)
3. Salve a operação

### 2. Registrar Pagamento com Juros

1. Ao registrar pagamento de uma parcela atrasada:
   - O sistema detecta automaticamente que a parcela está atrasada
   - Exibe as 4 opções de juros
2. Selecione uma opção:
   - **Sem juros**: Paga apenas o valor original
   - **Juros automático**: Usa a taxa da operação (se configurada)
   - **Juros manual**: Informe a taxa % e o sistema calcula
   - **Valor fixo**: Informe o valor em R$ diretamente
3. O campo "Valor do Pagamento" é atualizado automaticamente
4. Complete o registro normalmente

### 3. Visualizar Juros Aplicados

1. Na tela de detalhes do empréstimo
2. Clique no ícone de recibo (📄) na coluna "Pagamentos"
3. O modal exibe:
   - Valor original da parcela
   - Tipo de juros aplicado
   - Taxa aplicada (se houver)
   - Valor de juros
   - Valor total pago

## Exemplos Práticos

### Exemplo 1: Juros Automático
- **Parcela**: R$ 500,00
- **Dias atraso**: 10
- **Taxa operação**: 1,5% ao dia
- **Cálculo**: R$ 500,00 × 1,5% × 10 = R$ 75,00
- **Total**: R$ 575,00

### Exemplo 2: Juros Manual
- **Parcela**: R$ 500,00
- **Dias atraso**: 10
- **Taxa informada**: 2% ao dia
- **Cálculo**: R$ 500,00 × 2% × 10 = R$ 100,00
- **Total**: R$ 600,00

### Exemplo 3: Valor Fixo
- **Parcela**: R$ 500,00
- **Valor fixo**: R$ 25,00
- **Total**: R$ 525,00

## Validações

- Taxa de juros: Entre 0% e 100%
- Valor fixo: >= 0
- Juros automático: Só aparece se operação tiver taxa configurada
- Valor do pagamento: Deve ser >= valor original da parcela

## Observações Importantes

1. **Apenas para parcelas atrasadas**: As opções de juros só aparecem se `dias_atraso > 0` ou `status = 'atrasada'`

2. **Cálculo em tempo real**: O JavaScript calcula automaticamente conforme a opção selecionada

3. **Registro completo**: Todos os dados são salvos para auditoria e relatórios

4. **Flexibilidade**: Consultor pode escolher a melhor opção para cada situação

5. **Rastreabilidade**: Histórico completo de juros aplicados em cada pagamento

## Próximos Passos

Para usar a funcionalidade, execute as migrations:

```bash
php artisan migrate
```

Isso criará os campos necessários nas tabelas `operacoes` e `pagamentos`.

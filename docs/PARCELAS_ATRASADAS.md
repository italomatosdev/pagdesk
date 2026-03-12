# Página de Parcelas Atrasadas

## Visão Geral

Foi implementada uma página dedicada para listar todas as parcelas atrasadas do sistema, com filtros avançados e paginação. Os cards de parcelas atrasadas nas dashboards agora são clicáveis e redirecionam para esta página.

## Funcionalidades

### 1. Listagem de Parcelas Atrasadas

- **Rota**: `/parcelas/atrasadas`
- **Controller**: `ParcelaController::parcelasAtrasadas()`
- **View**: `resources/views/parcelas/atrasadas.blade.php`

### 2. Filtros Disponíveis

A página oferece os seguintes filtros:

- **Operação**: Filtrar por operação específica
- **Consultor**: Filtrar por consultor (apenas para Admin/Gestor)
- **Dias de Atraso (mínimo)**: Filtrar parcelas com pelo menos X dias de atraso
- **Valor Mínimo**: Filtrar parcelas com valor pendente maior ou igual a X
- **Ordenação**: Ordenar por:
  - Dias de Atraso (padrão)
  - Data de Vencimento
  - Valor
- **Direção**: Crescente ou Decrescente

### 3. Resumo Estatístico

A página exibe um resumo com:

- **Total de Parcelas**: Quantidade total de parcelas atrasadas
- **Valor Total em Atraso**: Soma de todos os valores pendentes
- **Média de Dias Atraso**: Média de dias de atraso
- **Parcela Mais Atrasada**: Maior número de dias de atraso

### 4. Tabela de Parcelas

A tabela exibe as seguintes informações:

- Cliente (com link para detalhes)
- Empréstimo (com link para detalhes)
- Parcela (número/total)
- Consultor
- Valor (pendente e pago, se houver)
- Data de Vencimento
- Dias de Atraso (com badge colorido)
- Operação
- Ações (Registrar Pagamento, Ver Detalhes)

### 5. Destaques Visuais

- **Linhas vermelhas**: Parcelas com mais de 30 dias de atraso
- **Linhas amarelas**: Parcelas com mais de 15 dias de atraso
- **Badges coloridos**: 
  - Vermelho: Mais de 30 dias
  - Amarelo: Mais de 15 dias
  - Azul: Menos de 15 dias

### 6. Paginação

A lista é paginada com 15 registros por página, mantendo os filtros na URL.

## Permissões e Acesso

### Admin e Gestor
- Podem ver todas as parcelas atrasadas
- Podem filtrar por operação e consultor
- Têm acesso a todos os filtros

### Consultor
- Vê apenas suas próprias parcelas atrasadas
- Não pode filtrar por consultor (filtro oculto)
- Tem acesso aos demais filtros

## Cards Clicáveis nas Dashboards

Os seguintes cards nas dashboards foram atualizados para serem clicáveis:

### Dashboard Admin
- **Card "Valor em Atraso"**: Redireciona para `/parcelas/atrasadas`
- **Botão "Ver Parcelas Atrasadas"**: Na seção de ações pendentes

### Dashboard Gestor
- **Card "Parcelas Vencidas"**: Redireciona para `/parcelas/atrasadas`
- **Card "Valor em Atraso"**: Redireciona para `/parcelas/atrasadas`

### Dashboard Consultor
- **Card "Parcelas Atrasadas"**: Redireciona para `/parcelas/atrasadas`

## Arquivos Modificados

1. **`app/Modules/Loans/Controllers/ParcelaController.php`**
   - Adicionado método `parcelasAtrasadas()`

2. **`routes/web.php`**
   - Adicionada rota `parcelas.atrasadas`

3. **`resources/views/parcelas/atrasadas.blade.php`**
   - Nova view criada

4. **`resources/views/dashboard/admin.blade.php`**
   - Card "Valor em Atraso" tornou-se clicável
   - Botão atualizado para "Ver Parcelas Atrasadas"

5. **`resources/views/dashboard/gestor.blade.php`**
   - Cards "Parcelas Vencidas" e "Valor em Atraso" tornaram-se clicáveis

6. **`resources/views/dashboard/consultor.blade.php`**
   - Card "Parcelas Atrasadas" tornou-se clicável

## Uso

### Acesso Direto
```
/parcelas/atrasadas
```

### Com Filtros
```
/parcelas/atrasadas?operacao_id=1&consultor_id=2&dias_atraso_min=30&valor_min=100&ordenacao=dias_atraso&direcao=desc
```

### A partir dos Dashboards
Clique em qualquer um dos cards de parcelas atrasadas/vencidas nas dashboards para ser redirecionado automaticamente.

## Observações Técnicas

1. **Cálculo de Dias de Atraso**: 
   - A página usa o campo `dias_atraso` quando disponível (atualizado pelo comando agendado)
   - Caso contrário, calcula dinamicamente usando `calcularDiasAtraso()`

2. **Performance**: 
   - A consulta usa eager loading (`with()`) para evitar N+1 queries
   - Paginação implementada para melhor performance com grandes volumes

3. **Filtros Persistentes**: 
   - Os filtros são mantidos na URL usando `withQueryString()` na paginação
   - Permite compartilhar links com filtros aplicados

## Melhorias Futuras

- Exportação para Excel/PDF
- Gráficos de inadimplência
- Notificações automáticas para consultores
- Histórico de atrasos por cliente
- Ações em lote (marcar múltiplas parcelas)

# Execução de Garantias (Empréstimos Tipo Empenho)

## Visão Geral

O sistema permite que **gestores e administradores** executem garantias de empréstimos do tipo **"empenho"** quando há parcelas atrasadas. A execução da garantia finaliza automaticamente o empréstimo, marca a garantia como executada e quita todas as parcelas não pagas com o status `quitada_garantia`, sem cobrança de juros ou valor da parcela e sem gerar movimentações de caixa.

## Ciclo de Vida das Garantias

```
┌─────────┐     ┌──────────┐     ┌──────────┐
│  ativa  │ ──► │ liberada │     │ executada│
└─────────┘     └──────────┘     └──────────┘
     │                │                │
     │                │                │
     └────────────────┴────────────────┘
```

### Status das Garantias

| Status | Descrição | Quando Ocorre |
|--------|-----------|---------------|
| `ativa` | Garantia está vinculada ao empréstimo e pode ser executada | Status inicial quando a garantia é cadastrada |
| `liberada` | Garantia foi liberada porque o empréstimo foi totalmente pago | Automático quando todas as parcelas são pagas |
| `executada` | Garantia foi executada devido a inadimplência | Manual por gestor/administrador quando há parcela atrasada |

## Quando uma Garantia Pode Ser Executada

Uma garantia pode ser executada **apenas** se todas as condições abaixo forem atendidas:

1. ✅ **Usuário é gestor ou administrador** - Apenas usuários com roles `gestor` ou `administrador` podem executar
2. ✅ **Empréstimo é tipo "empenho"** - Apenas empréstimos do tipo empenho possuem garantias
3. ✅ **Empréstimo está ativo** - Não pode executar garantia de empréstimos finalizados ou cancelados
4. ✅ **Garantia está ativa** - Não pode executar garantia já liberada ou executada
5. ✅ **Há parcela atrasada** - A garantia só pode ser executada quando há pelo menos uma parcela em atraso
6. ✅ **Há garantias ativas** - Deve existir pelo menos uma garantia com status `ativa`

## Fluxo de Execução

### Opção 1: Via Página de Detalhes do Empréstimo

1. Usuário acessa a página de detalhes de um empréstimo tipo "empenho"
2. Se houver parcela atrasada e garantia ativa, aparece um alerta com botão "Executar Garantia"
3. Ao clicar no botão, é redirecionado para a página de pagamento
4. A opção "Executar Garantia" já vem pré-selecionada
5. Campos de pagamento ficam ocultos
6. Botão muda para "Executar Garantia" (vermelho)
7. Usuário preenche observações/motivo (obrigatório, mínimo 10 caracteres)
8. Ao confirmar, a garantia é executada e o empréstimo é finalizado

### Opção 2: Via Formulário de Pagamento

1. Usuário acessa o formulário de pagamento de uma parcela atrasada
2. Se o empréstimo for tipo "empenho" com garantia ativa, aparece a opção "Executar Garantia" nas opções de tipo de juros/multa
3. Ao selecionar "Executar Garantia":
   - Campos de pagamento são ocultados automaticamente
   - Botão muda para "Executar Garantia" (vermelho)
   - Campo de observações/motivo aparece (obrigatório)
4. Usuário preenche observações e confirma
5. A garantia é executada e o empréstimo é finalizado

## O que Acontece ao Executar uma Garantia

### 1. Atualização da Garantia

```php
$garantia->update([
    'status' => 'executada',
    'data_execucao' => Carbon::now(),
    'observacoes' => $observacoes_anteriores . "\n\n[EXECUTADA EM " . Carbon::now()->format('d/m/Y H:i') . "]\nExecutor: ID {$executorId}\nMotivo: {$observacoes}",
]);
```

**Campos alterados:**
- `status`: `ativa` → `executada`
- `data_execucao`: `null` → data/hora atual
- `observacoes`: Adiciona histórico da execução

### 2. Atualização das Parcelas

**Comportamento:**
- Parcelas já pagas: **preservadas** (mantém status `paga` e histórico)
- Parcelas não pagas (pendentes/atrasadas): marcadas como `quitada_garantia`

```php
foreach ($emprestimo->parcelas as $parcela) {
    if ($parcela->status !== 'paga') {
        $parcela->update([
            'status' => 'quitada_garantia',
            'valor_pago' => 0, // Não houve pagamento, apenas execução de garantia
            'data_pagamento' => Carbon::now(),
            'dias_atraso' => 0,
        ]);
    }
}
```

**Campos alterados (apenas parcelas não pagas):**
- `status`: `pendente`/`atrasada` → `quitada_garantia`
- `valor_pago`: mantém `0` (não houve pagamento)
- `data_pagamento`: preenchida com data/hora atual
- `dias_atraso`: zerado

**Exemplo com múltiplas parcelas:**
- Empréstimo com 5 parcelas:
  - Parcela #1: `paga` → mantém `paga` (preserva histórico)
  - Parcela #2: `atrasada` → `quitada_garantia`
  - Parcelas #3, #4, #5: `pendente` → `quitada_garantia`

### 3. Finalização do Empréstimo

```php
$emprestimo->update([
    'status' => 'finalizado',
    'motivo_rejeicao' => "Garantia executada: {$observacoes}",
]);
```

**Campos alterados:**
- `status`: `ativo` → `finalizado`
- `motivo_rejeicao`: Preenchido com o motivo da execução

**Lógica de finalização:**
- Empréstimo é finalizado quando todas as parcelas estão:
  - `paga` (pagas pelo cliente) OU
  - `quitada_garantia` (quitadas via execução de garantia)

### 3. Auditoria

Duas auditorias são registradas:

**a) Auditoria da Execução da Garantia:**
- Tipo: `executar_garantia`
- Model: `EmprestimoGarantia`
- Antes: `status: ativa, data_execucao: null`
- Depois: `status: executada, data_execucao: {data}, observacoes: {motivo}`

**b) Auditoria da Finalização do Empréstimo:**
- Tipo: `finalizar_emprestimo`
- Model: `Emprestimo`
- Antes: `status: ativo`
- Depois: `status: finalizado, motivo: Garantia executada`

### 4. Notificações

Notificações são enviadas para:

1. **Consultor do empréstimo** (se houver):
   - Tipo: `garantia_executada`
   - Título: "Garantia Executada"
   - Mensagem: "A garantia do empréstimo #X do cliente Y foi executada."

2. **Cliente** (se tiver usuário no sistema):
   - Tipo: `garantia_executada`
   - Título: "Garantia Executada"
   - Mensagem: "A garantia do seu empréstimo #X foi executada."

### 5. Movimentações de Caixa

**IMPORTANTE:** A execução da garantia **NÃO** cria movimentações de caixa. Não há cobrança de juros ou valor da parcela. O empréstimo é simplesmente finalizado.

### 6. Status das Parcelas

**Novo Status: `quitada_garantia`**

Quando uma garantia é executada, todas as parcelas **não pagas** são marcadas com o status `quitada_garantia`:

- **Parcelas já pagas**: Mantêm status `paga` (preserva histórico de pagamento real)
- **Parcelas não pagas**: Mudam para `quitada_garantia` com `valor_pago = 0`

**Características do status `quitada_garantia`:**
- `valor_pago = 0` (não houve pagamento real)
- `data_pagamento` preenchida (data da execução da garantia)
- `dias_atraso = 0` (zerado)
- Considerado como "quitada" para finalização do empréstimo
- **NÃO** gera movimentação de caixa
- **NÃO** permite novos pagamentos (validação bloqueia)

**Enum de status de parcelas:**
- `pendente`: Aguardando pagamento
- `paga`: Cliente pagou normalmente
- `atrasada`: Vencida e não paga
- `cancelada`: Cancelada (empréstimo cancelado)
- `quitada_garantia`: Quitada via execução de garantia (NOVO)

## Liberação Automática de Garantias

Quando um empréstimo tipo "empenho" é totalmente pago (todas as parcelas pagas), as garantias são **automaticamente liberadas**:

```php
// Em PagamentoService::verificarFinalizacaoEmprestimo()
if ($emprestimo->isEmpenho() && $emprestimo->status === 'finalizado') {
    foreach ($emprestimo->garantias->where('status', 'ativa') as $garantia) {
        $garantia->update([
            'status' => 'liberada',
            'data_liberacao' => Carbon::now(),
        ]);
        
        // Auditoria e notificações...
    }
}
```

**O que acontece:**
- Status da garantia: `ativa` → `liberada`
- `data_liberacao`: Preenchida com data/hora atual
- Auditoria registrada
- Notificações enviadas

## Interface do Usuário

### Página de Detalhes do Empréstimo

Quando há parcela atrasada e garantia ativa, aparece:

```
┌─────────────────────────────────────────────────┐
│ ⚠️ Parcela Atrasada - Executar Garantia        │
├─────────────────────────────────────────────────┤
│ Este empréstimo possui parcelas atrasadas.     │
│ Você pode executar a garantia para finalizar   │
│ o empréstimo.                                   │
│                                                 │
│ [🛡️ Executar Garantia] (botão vermelho)        │
└─────────────────────────────────────────────────┘
```

### Formulário de Pagamento

Quando "Executar Garantia" está selecionado:

```
┌─────────────────────────────────────────────────┐
│ Tipo de Juros/Multa                              │
├─────────────────────────────────────────────────┤
│ ● Executar Garantia                              │
│   ┌───────────────────────────────────────────┐  │
│   │ ⚠️ Atenção: Esta opção irá:              │  │
│   │ • Executar a garantia                     │  │
│   │ • Finalizar o empréstimo                  │  │
│   │ • Não cobrar juros                        │  │
│   │                                            │  │
│   │ Garantias disponíveis:                    │  │
│   │ • Veículo - Honda Civic 2020              │  │
│   │                                            │  │
│   │ Observações/Motivo: *                      │  │
│   │ [Textarea obrigatório]                    │  │
│   │                                            │  │
│   │ Valor a pagar: R$ 0,00                    │  │
│   └───────────────────────────────────────────┘  │
│                                                 │
│ [Campos de pagamento OCULTOS]                   │
│                                                 │
│ [Cancelar]  [🛡️ Executar Garantia] (vermelho)   │
└─────────────────────────────────────────────────┘
```

## Validações

### Frontend (JavaScript)

1. **Observações obrigatórias:**
   - Campo deve ter pelo menos 10 caracteres
   - Validação antes de submeter o formulário

2. **Campos de pagamento:**
   - Ocultados quando "Executar Garantia" está selecionado
   - Atributo `required` removido dos campos

3. **Botão:**
   - Texto muda para "Executar Garantia"
   - Cor muda para vermelho (`btn-danger`)
   - Ícone muda para `bx-shield-x`

### Backend (Laravel)

1. **Validação de Request:**
```php
'observacoes_executar_garantia' => 'nullable|string|min:10|max:1000|required_if:tipo_juros,executar_garantia',
```

2. **Validações de Negócio:**
- Empréstimo deve ser tipo "empenho"
- Empréstimo deve estar ativo
- Parcela deve estar atrasada
- Deve haver garantia ativa
- Garantia deve pertencer ao empréstimo

## Código Responsável

### Controller

**Arquivo:** `app/Modules/Loans/Controllers/PagamentoController.php`

**Método:** `store()`
- Processa a execução quando `tipo_juros = 'executar_garantia'`
- Valida condições
- Chama `EmprestimoService::executarGarantia()`

**Método:** `create()`
- Aceita parâmetro `executar_garantia` na URL
- Pré-seleciona a opção na view

### Service

**Arquivo:** `app/Modules/Loans/Services/EmprestimoService.php`

**Método:** `executarGarantia($emprestimoId, $garantiaId, $executorId, $observacoes)`
- Executa todas as validações
- Atualiza status da garantia
- Marca parcelas não pagas como `quitada_garantia`
- Finaliza o empréstimo
- Registra auditorias (garantia, empréstimo e cada parcela)
- Envia notificações

**Arquivo:** `app/Modules/Loans/Services/PagamentoService.php`

**Método:** `verificarFinalizacaoEmprestimo($emprestimo)`
- Considera parcelas `paga` OU `quitada_garantia` como quitadas
- Finaliza empréstimo quando todas estão quitadas
- Libera garantias automaticamente quando empréstimo tipo "empenho" é totalmente pago

### Models

**Arquivo:** `app/Modules/Loans/Models/EmprestimoGarantia.php`

**Métodos auxiliares:**
- `isAtiva()`: Verifica se status é `ativa`
- `isLiberada()`: Verifica se status é `liberada`
- `isExecutada()`: Verifica se status é `executada`
- `getStatusNomeAttribute()`: Retorna nome legível do status
- `getStatusCorAttribute()`: Retorna cor do badge do status

**Arquivo:** `app/Modules/Loans/Models/Parcela.php`

**Métodos auxiliares:**
- `isPaga()`: Verifica se status é `paga`
- `isQuitadaGarantia()`: Verifica se status é `quitada_garantia` (NOVO)
- `isQuitada()`: Verifica se está paga OU quitada por garantia (NOVO)
- `isTotalmentePaga()`: Considera `quitada_garantia` como totalmente quitada (atualizado)
- `getStatusNomeAttribute()`: Retorna nome legível do status (NOVO)
- `getStatusCorAttribute()`: Retorna cor do badge do status (NOVO)

### Views

**Arquivo:** `resources/views/emprestimos/show.blade.php`
- Botão "Executar Garantia" na página de detalhes
- Redireciona para formulário de pagamento

**Arquivo:** `resources/views/pagamentos/create.blade.php`
- Opção "Executar Garantia" no formulário de pagamento
- JavaScript para ocultar campos e alterar botão
- Validação de observações

### Rotas

**Arquivo:** `routes/web.php`

```php
// Execução via formulário de pagamento (já existe)
Route::post('/pagamentos', [PagamentoController::class, 'store'])->name('pagamentos.store');

// Execução direta (ainda existe, mas não é mais usada na UI principal)
Route::post('/{id}/garantias/{garantiaId}/executar', [EmprestimoController::class, 'executarGarantia'])->name('garantias.executar');
```

## Migrations

**Arquivo:** `database/migrations/2026_01_26_191709_add_status_to_emprestimo_garantias_table.php`

Adiciona:
- `status`: ENUM('ativa', 'liberada', 'executada') - default 'ativa'
- `data_liberacao`: DATETIME nullable
- `data_execucao`: DATETIME nullable

**Arquivo:** `database/migrations/2026_01_26_203033_add_quitada_garantia_to_parcelas_status_enum.php` (NOVO)

Adiciona:
- `quitada_garantia` ao enum de `status` na tabela `parcelas`
- Enum completo: `('pendente', 'paga', 'atrasada', 'cancelada', 'quitada_garantia')`

## Exemplos de Uso

### Exemplo 1: Execução via Página de Detalhes (Parcela Única)

1. **Situação:**
   - Empréstimo #123 tipo "empenho"
   - Valor: R$ 5.000,00
   - Parcela única vencida há 5 dias
   - Garantia: Veículo Honda Civic 2020

2. **Ação:**
   - Administrador acessa detalhes do empréstimo
   - Clica em "Executar Garantia"
   - É redirecionado para formulário de pagamento
   - Preenche: "Cliente não compareceu para negociar após 3 tentativas de contato"
   - Confirma

3. **Resultado:**
   - Garantia: `ativa` → `executada` (data_execucao: 2026-01-26 15:30:00)
   - Parcela: `atrasada` → `quitada_garantia` (valor_pago: 0, data_pagamento: 2026-01-26 15:30:00)
   - Empréstimo: `ativo` → `finalizado` (motivo: "Garantia executada: Cliente não compareceu...")
   - Auditoria registrada (garantia, empréstimo e parcela)
   - Notificações enviadas
   - **Nenhuma movimentação de caixa**

### Exemplo 1.1: Execução com Múltiplas Parcelas

1. **Situação:**
   - Empréstimo #124 tipo "empenho"
   - Valor: R$ 10.000,00
   - 5 parcelas de R$ 2.000,00
   - Parcela #1: `paga` (cliente pagou)
   - Parcela #2: `atrasada` (vencida há 10 dias)
   - Parcelas #3, #4, #5: `pendente` (ainda não venceram)
   - Garantia: Imóvel - Casa em São Paulo

2. **Ação:**
   - Gestor executa garantia com motivo: "Cliente não respondeu aos contatos"

3. **Resultado:**
   - Garantia: `ativa` → `executada`
   - Parcela #1: mantém `paga` (preserva histórico)
   - Parcela #2: `atrasada` → `quitada_garantia` (valor_pago: 0)
   - Parcelas #3, #4, #5: `pendente` → `quitada_garantia` (valor_pago: 0 cada)
   - Empréstimo: `ativo` → `finalizado`
   - Todas as 5 parcelas consideradas quitadas (1 paga + 4 quitadas_garantia)
   - Auditoria registrada para cada parcela
   - **Nenhuma movimentação de caixa**

### Exemplo 2: Execução via Formulário de Pagamento

1. **Situação:**
   - Mesmo empréstimo do exemplo anterior
   - Administrador acessa formulário de pagamento da parcela

2. **Ação:**
   - Seleciona "Executar Garantia" nas opções de tipo de juros
   - Campos de pagamento desaparecem
   - Botão muda para "Executar Garantia" (vermelho)
   - Preenche observações: "Cliente não respondeu aos contatos"
   - Confirma

3. **Resultado:**
   - Mesmo resultado do Exemplo 1

### Exemplo 3: Liberação Automática

1. **Situação:**
   - Empréstimo #124 tipo "empenho"
   - 3 parcelas, todas pagas
   - Garantia: Imóvel - Casa em São Paulo

2. **Ação:**
   - Sistema detecta que todas as parcelas foram pagas
   - Executa `verificarFinalizacaoEmprestimo()`

3. **Resultado:**
   - Empréstimo: `ativo` → `finalizado`
   - Garantia: `ativa` → `liberada` (data_liberacao: 2026-01-26 16:00:00)
   - Auditoria registrada
   - Notificações enviadas

## Diferenças entre Execução e Liberação

| Aspecto | Execução | Liberação |
|---------|----------|-----------|
| **Quando ocorre** | Manual quando há parcela atrasada | Automática quando todas parcelas são pagas |
| **Quem pode fazer** | Gestor/Administrador | Automático pelo sistema |
| **Status final** | `executada` | `liberada` |
| **Empréstimo** | Finalizado | Finalizado |
| **Cobrança** | Não há cobrança | Cliente pagou tudo |
| **Motivo** | Inadimplência | Quitação total |

## Notificações

### Tipos de Notificação

1. **`garantia_executada`**
   - Quando uma garantia é executada manualmente
   - Enviada para consultor e cliente

2. **`garantia_liberada`**
   - Quando uma garantia é liberada automaticamente
   - Enviada para consultor e cliente

### Ícones e Cores

Definidos em `app/Modules/Core/Models/Notificacao.php`:

```php
case 'garantia_executada':
    return 'bx-shield-x'; // Ícone
    return 'danger'; // Cor (vermelho)

case 'garantia_liberada':
    return 'bx-shield-check'; // Ícone
    return 'success'; // Cor (verde)
```

## Considerações Importantes

1. **Não há cobrança:** A execução da garantia não cria movimentações de caixa. O empréstimo é simplesmente finalizado.

2. **Status das parcelas:** Parcelas não pagas são marcadas como `quitada_garantia` com `valor_pago = 0`. Parcelas já pagas são preservadas (mantém histórico).

3. **Irreversível:** Uma vez executada, a garantia não pode ser revertida automaticamente. Seria necessário intervenção manual no banco de dados.

4. **Uma garantia por vez:** Atualmente, o sistema executa apenas a primeira garantia ativa encontrada. Se houver múltiplas garantias, apenas uma é executada.

5. **Observações obrigatórias:** O campo de observações/motivo é obrigatório e deve ter no mínimo 10 caracteres para garantir rastreabilidade.

6. **Finalização automática:** Quando uma garantia é executada, o empréstimo é automaticamente finalizado. Todas as parcelas não pagas são marcadas como `quitada_garantia`.

7. **Preservação de histórico:** Parcelas já pagas pelo cliente mantêm status `paga` e não são alteradas, preservando o histórico de pagamento real.

8. **Validação de pagamentos:** Parcelas com status `quitada_garantia` não podem receber novos pagamentos (validação bloqueia).

9. **Renovação com garantia:** Quando um empréstimo tipo "empenho" é renovado, as garantias são copiadas para o novo empréstimo, mantendo o vínculo.

10. **Relatórios:** Relatórios devem considerar que `quitada_garantia` é diferente de `paga` para cálculos de recebimentos (apenas `paga` gera movimentação de caixa).

## Troubleshooting

### Problema: Botão "Executar Garantia" não aparece

**Possíveis causas:**
1. Empréstimo não é tipo "empenho"
2. Não há parcela atrasada
3. Não há garantia ativa
4. Usuário não é gestor/administrador

**Solução:** Verificar todas as condições listadas em "Quando uma Garantia Pode Ser Executada"

### Problema: Erro ao executar garantia

**Possíveis causas:**
1. Observações com menos de 10 caracteres
2. Garantia já foi executada ou liberada
3. Empréstimo não está ativo

**Solução:** Verificar mensagem de erro específica e condições de validação

### Problema: Garantia não foi liberada automaticamente

**Possíveis causas:**
1. Empréstimo não é tipo "empenho"
2. Ainda há parcelas não pagas
3. Erro no método `verificarFinalizacaoEmprestimo()`

**Solução:** Verificar logs do Laravel e status das parcelas

## Histórico de Mudanças

### Versão Atual (2026-01-26)

- ✅ Implementação inicial da execução de garantias
- ✅ Status de garantias (`ativa`, `liberada`, `executada`)
- ✅ Execução via formulário de pagamento como opção de tipo de juros/multa
- ✅ Botão na página de detalhes que redireciona para formulário
- ✅ Liberação automática quando empréstimo é totalmente pago
- ✅ Ocultação de campos de pagamento quando executar garantia
- ✅ Mudança de botão para "Executar Garantia" (vermelho)
- ✅ Validações frontend e backend
- ✅ Auditoria e notificações
- ✅ **Novo status de parcelas: `quitada_garantia`**
- ✅ Parcelas não pagas marcadas como `quitada_garantia` quando garantia é executada
- ✅ Parcelas já pagas preservadas (mantém histórico)
- ✅ Finalização considera parcelas `paga` OU `quitada_garantia` como quitadas
- ✅ Validações atualizadas para considerar novo status
- ✅ Views atualizadas para exibir novo status com badge/cor apropriados

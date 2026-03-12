# Cancelamento de Empréstimos

## Visão Geral

O sistema permite que **administradores** cancelem empréstimos que ainda não foram pagos ao cliente. O cancelamento é uma ação irreversível que mantém o histórico do empréstimo, mas altera seu status para `cancelado` e, quando necessário, cria movimentações de estorno para devolver o dinheiro ao gestor.

## Quando um Empréstimo Pode Ser Cancelado

Um empréstimo pode ser cancelado **apenas** se todas as condições abaixo forem atendidas:

1. ✅ **Usuário é administrador** - Apenas usuários com role `administrador` podem cancelar
2. ✅ **Empréstimo não está cancelado** - Não pode cancelar um empréstimo já cancelado
3. ✅ **Empréstimo não está finalizado** - Não pode cancelar empréstimos finalizados
4. ✅ **Empréstimo não é renovado** - Não pode cancelar empréstimos que foram criados via renovação
5. ✅ **Dinheiro não foi pago ao cliente** - Se existe liberação, ela não pode estar com status `pago_ao_cliente`
6. ✅ **Não há parcelas pagas** - Nenhuma parcela pode ter `valor_pago > 0` ou ter pagamentos registrados

## Cenários de Cancelamento

### Cenário 1: Empréstimo `pendente` (sem liberação)

**Situação:**
- Empréstimo criado e aguardando aprovação
- Nenhuma liberação foi criada ainda
- Nenhum dinheiro foi movimentado

**O que acontece:**
1. Status do empréstimo muda para `cancelado`
2. Motivo do cancelamento é registrado
3. Parcelas são deletadas (soft delete) se não houver pagamentos
4. Nenhuma movimentação de caixa é criada
5. Auditoria é registrada

**Exemplo:**
```
Empréstimo #123 - Status: pendente
Administrador cancela com motivo: "Cliente desistiu"
Resultado: Status = cancelado, nenhuma movimentação de caixa
```

### Cenário 2: Empréstimo `aprovado` com liberação `aguardando`

**Situação:**
- Empréstimo foi aprovado
- Liberação foi criada, mas gestor ainda não liberou o dinheiro
- Dinheiro ainda está no caixa do gestor/operação

**O que acontece:**
1. Status do empréstimo muda para `cancelado`
2. Status da liberação muda para `cancelado`
3. Motivo do cancelamento é registrado
4. Parcelas são deletadas (soft delete) se não houver pagamentos
5. Nenhuma movimentação de caixa é criada (dinheiro nunca saiu)
6. Auditoria é registrada

**Exemplo:**
```
Empréstimo #124 - Status: aprovado
Liberação #45 - Status: aguardando
Administrador cancela com motivo: "Documentação incompleta"
Resultado: 
  - Empréstimo: cancelado
  - Liberação: cancelado
  - Nenhuma movimentação de caixa (dinheiro nunca foi liberado)
```

### Cenário 3: Empréstimo `aprovado` com liberação `liberado` (dinheiro no consultor)

**Situação:**
- Empréstimo foi aprovado
- Gestor liberou o dinheiro para o consultor
- Consultor ainda não pagou ao cliente
- Dinheiro está no caixa do consultor

**O que acontece:**
1. Status do empréstimo muda para `cancelado`
2. Status da liberação muda para `cancelado`
3. Motivo do cancelamento é registrado
4. Parcelas são deletadas (soft delete) se não houver pagamentos
5. **MOVIMENTAÇÕES DE ESTORNO SÃO CRIADAS:**
   - **SAÍDA** do caixa do consultor (devolve o dinheiro)
   - **ENTRADA** no caixa do gestor (dinheiro volta)
6. Auditoria é registrada

**Exemplo:**
```
Empréstimo #125 - Status: aprovado
Liberação #46 - Status: liberado
Valor: R$ 1.000,00
Consultor: Maria (ID: 10)
Gestor: João (ID: 5)

Administrador cancela com motivo: "Cliente não compareceu"

Resultado:
  - Empréstimo: cancelado
  - Liberação: cancelado
  - Movimentação 1: SAÍDA de R$ 1.000,00 do caixa da Maria
  - Movimentação 2: ENTRADA de R$ 1.000,00 no caixa do João
```

## Fluxo de Movimentações de Caixa

### Quando o Dinheiro Já Foi Liberado

Quando o gestor libera dinheiro para o consultor, o sistema cria duas movimentações:

1. **SAÍDA** no caixa do gestor
2. **ENTRADA** no caixa do consultor

Ao cancelar, o sistema cria movimentações **reversas** (estorno):

1. **SAÍDA** no caixa do consultor (devolve)
2. **ENTRADA** no caixa do gestor (volta)

**Exemplo Visual:**

```
Estado Inicial:
Gestor: R$ 10.000,00
Consultor: R$ 500,00

Gestor libera R$ 1.000,00:
Gestor: R$ 9.000,00 (-R$ 1.000,00)
Consultor: R$ 1.500,00 (+R$ 1.000,00)

Cancelamento (estorno):
Gestor: R$ 10.000,00 (+R$ 1.000,00)
Consultor: R$ 500,00 (-R$ 1.000,00)
```

### Cenário 4: Empréstimo Renovado (BLOQUEADO)

**Situação:**
- Empréstimo foi criado via renovação (tem `emprestimo_origem_id`)
- Empréstimo original já foi finalizado
- Dinheiro já foi pago ao cliente no empréstimo original
- Garantias foram transferidas do original para o renovado

**O que acontece:**
- ❌ **CANCELAMENTO BLOQUEADO** - Não é possível cancelar

**Motivos do bloqueio:**
1. **Dinheiro já foi pago**: O dinheiro foi pago ao cliente no empréstimo original, não há como reverter
2. **Garantias transferidas**: As garantias foram copiadas do original, cancelar deixaria bem empenhado sem empréstimo ativo
3. **Histórico preservado**: O empréstimo original já está finalizado, cancelar o renovado quebraria a continuidade
4. **Inconsistências**: Cancelar criaria situação onde bem está empenhado mas empréstimo cancelado

**Exemplo:**
```
Empréstimo #10 (original):
- Status: finalizado
- Dinheiro: pago ao cliente
- Garantia: Veículo avaliado em R$ 5.000,00

Empréstimo #11 (renovado):
- Status: ativo
- emprestimo_origem_id: 10
- Garantia: Veículo (copiada do #10)

Tentativa de cancelar #11:
❌ ERRO: "Não é possível cancelar um empréstimo renovado..."
```

**Mensagem exibida na interface:**
- Para administradores: Alerta informativo explicando que empréstimos renovados não podem ser cancelados
- Botão de cancelamento não aparece

## Validações Implementadas

### Validações no Backend

1. **Permissão**: Apenas administradores podem cancelar
2. **Status do empréstimo**: Não pode cancelar se já está cancelado ou finalizado
3. **Empréstimo renovado**: Não pode cancelar se é um empréstimo criado via renovação
4. **Pagamento ao cliente**: Não pode cancelar se dinheiro já foi pago ao cliente
5. **Parcelas pagas**: Não pode cancelar se há parcelas com pagamentos
6. **Motivo obrigatório**: Motivo do cancelamento é obrigatório (10-1000 caracteres)

### Validações no Frontend

O botão de cancelamento só aparece se:
- Usuário é administrador
- Empréstimo não está cancelado
- Empréstimo não está finalizado
- Empréstimo não é renovado (não tem `emprestimo_origem_id`)
- Dinheiro não foi pago ao cliente (se existe liberação)
- Nenhuma parcela tem pagamentos

**Nota:** Se o empréstimo for renovado, aparece uma mensagem informativa explicando que não pode ser cancelado.

## Estrutura de Dados

### Tabela: `emprestimos`

Quando um empréstimo é cancelado:
- `status` = `'cancelado'`
- `motivo_rejeicao` = motivo informado pelo administrador
- `aprovado_por` = ID do administrador que cancelou
- `aprovado_em` = data/hora do cancelamento

### Tabela: `emprestimo_liberacoes`

Quando uma liberação é cancelada:
- `status` = `'cancelado'` (adicionado ao enum)

**Migration:** `2026_01_26_173616_add_cancelado_status_to_emprestimo_liberacoes_table.php`

### Tabela: `cash_ledger_entries`

Quando há estorno, são criadas duas movimentações:

**Movimentação 1 - Saída do Consultor:**
```php
[
    'operacao_id' => $emprestimo->operacao_id,
    'consultor_id' => $liberacao->consultor_id,
    'tipo' => 'saida',
    'origem' => 'automatica',
    'valor' => $liberacao->valor_liberado,
    'descricao' => "Estorno - Cancelamento Empréstimo #{$emprestimo->id}",
    'referencia_tipo' => 'cancelamento_emprestimo',
    'referencia_id' => $emprestimo->id,
]
```

**Movimentação 2 - Entrada no Gestor:**
```php
[
    'operacao_id' => $emprestimo->operacao_id,
    'consultor_id' => $liberacao->gestor_id,
    'tipo' => 'entrada',
    'origem' => 'automatica',
    'valor' => $liberacao->valor_liberado,
    'descricao' => "Estorno - Cancelamento Empréstimo #{$emprestimo->id} - Consultor {$liberacao->consultor->name}",
    'referencia_tipo' => 'cancelamento_emprestimo',
    'referencia_id' => $emprestimo->id,
]
```

## Código Responsável

### Service: `EmprestimoService::cancelar()`

**Arquivo:** `app/Modules/Loans/Services/EmprestimoService.php`

**Método:**
```php
public function cancelar(int $emprestimoId, int $administradorId, string $motivoCancelamento): Emprestimo
```

**Responsabilidades:**
- Validar condições de cancelamento
- Criar movimentações de estorno (se necessário)
- Atualizar status do empréstimo e liberação
- Deletar parcelas sem pagamentos
- Registrar auditoria
- Enviar notificações

### Controller: `EmprestimoController::cancelar()`

**Arquivo:** `app/Modules/Loans/Controllers/EmprestimoController.php`

**Rota:** `POST /emprestimos/{id}/cancelar`

**Validações:**
- Verifica se usuário é administrador
- Valida motivo do cancelamento (required, min:10, max:1000)

### View: `emprestimos/show.blade.php`

**Botão de Cancelamento:**
- Aparece apenas para administradores
- Aparece apenas se empréstimo pode ser cancelado
- Abre modal de confirmação

**Modal de Cancelamento:**
- Exibe informações do empréstimo
- Campo obrigatório para motivo (10-1000 caracteres)
- Alerta sobre estorno (se dinheiro já foi liberado)
- Confirmação antes de submeter

## Auditoria

Todas as ações de cancelamento são registradas na tabela `audit_logs`:

```php
[
    'action' => 'cancelar_emprestimo',
    'model_type' => 'App\Modules\Loans\Models\Emprestimo',
    'model_id' => $emprestimo->id,
    'user_id' => $administradorId,
    'old_values' => ['status' => $oldStatus],
    'new_values' => ['status' => 'cancelado', 'motivo_rejeicao' => $motivoCancelamento],
    'observations' => "Empréstimo cancelado por administrador. Motivo: {$motivoCancelamento}..."
]
```

## Notificações

Quando um empréstimo é cancelado, o consultor responsável recebe uma notificação:

```php
[
    'user_id' => $emprestimo->consultor_id,
    'tipo' => 'emprestimo_cancelado',
    'titulo' => 'Empréstimo Cancelado',
    'mensagem' => "O empréstimo #{$emprestimo->id} do cliente {$cliente->nome} foi cancelado. Motivo: {$motivoCancelamento}",
    'url' => route('emprestimos.show', $emprestimo->id),
]
```

## Diferença entre Cancelamento e Rejeição

### Rejeição (`rejeitar()`)
- Acontece quando empréstimo está `pendente`
- Gestor/Administrador rejeita antes de aprovar
- Status muda para `cancelado`
- Não há liberação ainda

### Cancelamento (`cancelar()`)
- Pode acontecer em empréstimos `pendente` ou `aprovado`
- Apenas administradores podem cancelar
- Pode criar estornos se dinheiro já foi liberado
- Mais flexível e completo

## Exemplo de Uso

### Interface do Usuário

1. Administrador acessa página de detalhes do empréstimo
2. Se o empréstimo pode ser cancelado, aparece botão "Cancelar Empréstimo"
3. Ao clicar, abre modal de confirmação
4. Administrador preenche motivo (obrigatório, 10-1000 caracteres)
5. Confirma cancelamento
6. Sistema valida, cancela e cria estornos (se necessário)
7. Página recarrega com mensagem de sucesso
8. Status do empréstimo muda para "Cancelado"

### Via Código

```php
use App\Modules\Loans\Services\EmprestimoService;

$emprestimoService = app(EmprestimoService::class);

try {
    $emprestimo = $emprestimoService->cancelar(
        emprestimoId: 123,
        administradorId: auth()->id(),
        motivoCancelamento: 'Cliente desistiu do empréstimo após análise'
    );
    
    // Empréstimo cancelado com sucesso
    // Se dinheiro estava no consultor, estornos foram criados
} catch (\Illuminate\Validation\ValidationException $e) {
    // Erro de validação (ex: já foi pago ao cliente)
    $errors = $e->errors();
}
```

## Limitações

1. **Não pode cancelar após pagamento ao cliente**: Uma vez que o dinheiro foi pago ao cliente, o empréstimo não pode mais ser cancelado
2. **Não pode cancelar com parcelas pagas**: Se há parcelas com pagamentos registrados, o cancelamento não é permitido
3. **Não pode cancelar empréstimos renovados**: Empréstimos criados via renovação não podem ser cancelados, pois:
   - O dinheiro já foi pago ao cliente no empréstimo original
   - As garantias foram transferidas e não podem ser revertidas automaticamente
   - O empréstimo original já foi finalizado
   - Cancelar criaria inconsistências (bem empenhado sem empréstimo ativo)
4. **Apenas administradores**: Apenas usuários com role `administrador` podem cancelar
5. **Irreversível**: O cancelamento não pode ser desfeito (mas o histórico é mantido)

## Histórico e Rastreabilidade

- Todas as movimentações de estorno são registradas com `referencia_tipo = 'cancelamento_emprestimo'`
- O motivo do cancelamento fica salvo em `emprestimo.motivo_rejeicao`
- Auditoria completa é registrada
- Notificações são enviadas aos envolvidos
- Status da liberação também é atualizado para `cancelado`

## Troubleshooting

### Problema: "Não é possível cancelar um empréstimo que já possui parcelas pagas"

**Solução:** Verifique se há parcelas com `valor_pago > 0` ou com pagamentos registrados. Se houver, o cancelamento não é permitido.

### Problema: "Não é possível cancelar um empréstimo cujo dinheiro já foi pago ao cliente"

**Solução:** O dinheiro já foi pago ao cliente. O cancelamento não é mais possível. Considere outras ações (ex: renegociação).

### Problema: Movimentações de estorno não foram criadas

**Verificação:**
1. Verifique se a liberação tinha status `liberado` antes do cancelamento
2. Verifique se `gestor_id` estava preenchido na liberação
3. Verifique logs de erro do sistema
4. Consulte a tabela `audit_logs` para ver o que foi registrado

## Migrations Necessárias

Execute a migration para adicionar o status `cancelado` à tabela de liberações:

```bash
php artisan migrate
```

**Arquivo:** `database/migrations/2026_01_26_173616_add_cancelado_status_to_emprestimo_liberacoes_table.php`

# Sistema de Liberação de Dinheiro

## 📋 Funcionalidade

Sistema completo para rastrear o fluxo de dinheiro do gestor para o consultor e do consultor para o cliente.

## 🔄 Fluxo Completo

```
1. Consultor cria empréstimo
   ↓
2. Sistema aprova (automático ou manual)
   ↓
3. Sistema cria liberação pendente automaticamente
   ↓
4. GESTOR vê liberação pendente e libera dinheiro
   ↓
5. CONSULTOR recebe notificação e confirma pagamento ao cliente
   ↓
6. Empréstimo fica ATIVO e cliente começa a pagar parcelas
```

## 📊 Estrutura de Dados

### Tabela `emprestimo_liberacoes`

Campos principais:
- `emprestimo_id`: Empréstimo relacionado
- `consultor_id`: Consultor que receberá o dinheiro
- `gestor_id`: Gestor que liberou (null até ser liberado)
- `valor_liberado`: Valor a ser liberado
- `status`: `aguardando` | `liberado` | `pago_ao_cliente`
- `liberado_em`: Data/hora da liberação
- `pago_ao_cliente_em`: Data/hora do pagamento ao cliente
- `observacoes_liberacao`: Observações do gestor
- `observacoes_pagamento`: Observações do consultor

## 🎯 Funcionalidades

### Para o Gestor

1. **Ver Liberações Pendentes**
   - Menu: "Liberações de Dinheiro"
   - Lista empréstimos aprovados aguardando liberação
   - Badge com quantidade pendente

2. **Liberar Dinheiro**
   - Clica em "Liberar Dinheiro"
   - Sistema registra:
     - `gestor_id` = gestor que liberou
     - `liberado_em` = agora
     - `status` = `liberado`
   - Cria movimentação de caixa (entrada no caixa do consultor)

### Para o Consultor

1. **Ver Minhas Liberações**
   - Menu: "Minhas Liberações"
   - Lista todas as liberações do consultor
   - Filtro por status

2. **Confirmar Pagamento ao Cliente**
   - Aparece apenas para liberações com status `liberado`
   - Consultor confirma que pagou o cliente
   - Sistema registra:
     - `pago_ao_cliente_em` = agora
     - `status` = `pago_ao_cliente`
   - Atualiza empréstimo para `ativo`
   - Cria movimentação de caixa (saída no caixa do consultor)

## 💰 Movimentações de Caixa

### Quando Gestor Libera

**Duas movimentações são criadas:**

1. **SAÍDA no caixa do gestor:**
   ```
   Tipo: SAÍDA
   Consultor: gestor_id (gestor que liberou)
   Valor: valor_liberado
   Descrição: "Liberação para consultor [Nome] - Empréstimo #X"
   Referência: liberacao_emprestimo, liberacao_id
   ```

2. **ENTRADA no caixa do consultor:**
   ```
   Tipo: ENTRADA
   Consultor: consultor_id (consultor que recebe)
   Valor: valor_liberado
   Descrição: "Liberação de dinheiro recebida - Empréstimo #X"
   Referência: liberacao_emprestimo, liberacao_id
   ```

### Quando Consultor Confirma Pagamento

```
Tipo: SAÍDA
Consultor: consultor_id
Valor: valor_liberado
Descrição: "Pagamento ao cliente - Empréstimo #X"
Referência: pagamento_cliente, emprestimo_id
```

## 🔄 Status do Empréstimo

### Fluxo de Status

```
pendente → aprovado → ativo
                ↓
        (cria liberação pendente)
                ↓
        (gestor libera dinheiro)
                ↓
        (consultor confirma pagamento)
                ↓
              ativo
```

## 📝 Criação Automática

A liberação é criada automaticamente quando:
- Empréstimo é aprovado automaticamente (dentro do limite)
- Empréstimo pendente é aprovado pelo administrador

**Código:**
```php
// EmprestimoService::criar() ou aprovar()
if ($status === 'aprovado') {
    $liberacaoService->criarPendente($emprestimo);
}
```

## 🛡️ Validações

### Ao Liberar (Gestor)
- ✅ Apenas gestor ou administrador pode liberar
- ✅ Liberação deve estar com status `aguardando`
- ✅ Cria movimentação de caixa automaticamente

### Ao Confirmar Pagamento (Consultor)
- ✅ Apenas o consultor dono da liberação pode confirmar
- ✅ Liberação deve estar com status `liberado`
- ✅ Atualiza empréstimo para `ativo`
- ✅ Cria movimentação de caixa automaticamente

## 📊 Relacionamentos

### Model `LiberacaoEmprestimo`

```php
// Pertence a
- emprestimo() → Emprestimo
- consultor() → User
- gestor() → User (nullable)
```

### Model `Emprestimo`

```php
// Tem uma
- liberacao() → LiberacaoEmprestimo
```

## 🎨 Interface

### Menu Gestor/Admin
- **"Liberações de Dinheiro"**
- Badge com quantidade pendente
- Lista empréstimos aguardando

### Menu Consultor
- **"Minhas Liberações"**
- Lista todas as liberações do consultor
- Filtro por status
- Botão para confirmar pagamento

## ✅ Benefícios

1. **Rastreabilidade Completa**: Sabe exatamente quando dinheiro foi liberado e pago
2. **Controle**: Gestor controla quando libera o dinheiro
3. **Segurança**: Consultor só confirma após receber e pagar
4. **Auditoria**: Tudo registrado com timestamps e usuários
5. **Movimentações**: Caixa atualizado automaticamente

## 📚 Arquivos Criados

- Migration: `2024_12_20_200300_create_emprestimo_liberacoes_table.php`
- Model: `app/Modules/Loans/Models/LiberacaoEmprestimo.php`
- Service: `app/Modules/Loans/Services/LiberacaoService.php`
- Controller: `app/Modules/Loans/Controllers/LiberacaoController.php`
- Views: `resources/views/liberacoes/index.blade.php`, `consultor.blade.php`
- Migration: `2024_12_20_300010_add_referencia_to_cash_ledger_entries_table.php`

## 🔧 Próximos Passos

1. Executar migrations:
   ```bash
   php artisan migrate
   ```

2. Testar fluxo:
   - Criar empréstimo
   - Verificar liberação pendente
   - Gestor libera dinheiro
   - Consultor confirma pagamento
   - Verificar movimentações de caixa

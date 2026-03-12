# Configurações Flexíveis por Empresa

## 📋 Visão Geral

O sistema permite que cada empresa tenha seu próprio workflow configurado, adaptando-se desde empresas pequenas (1 pessoa faz tudo) até grandes operações com múltiplos gestores e controles rigorosos.

## 🎯 Objetivo

Permitir que diferentes empresas tenham processos diferentes:
- **Empresas pequenas**: Não precisam de aprovação/liberação, tudo é automático
- **Empresas grandes**: Requerem aprovação de gestores e liberação de dinheiro com controles

## ⚙️ Estrutura das Configurações

As configurações são armazenadas no campo `configuracoes` (JSON) da tabela `empresas`:

```json
{
  "workflow": {
    "requer_aprovacao": true/false,
    "requer_liberacao": true/false,
    "aprovacao_automatica_valor_max": 0.00
  },
  "operacoes": {
    "permite_multiplas_operacoes": true/false
  }
}
```

## 📝 Campos de Configuração

### 1. Requer Aprovação de Empréstimos
- **Tipo**: Switch (checkbox)
- **Padrão**: `true`
- **Descrição**: Se desmarcado, todos os empréstimos serão aprovados automaticamente (ignora validações de dívida e limite)
- **Impacto**: 
  - `false`: Empréstimos vão direto para `aprovado` sem validações
  - `true`: Empréstimos podem ficar `pendente` se houver dívida ativa ou limite excedido

### 2. Requer Liberação de Dinheiro
- **Tipo**: Switch (checkbox)
- **Padrão**: `true`
- **Descrição**: Se desmarcado, o dinheiro será liberado automaticamente após aprovação (sem necessidade de gestor liberar)
- **Impacto**:
  - `false`: Após aprovação, liberação é automática (consultor pode confirmar pagamento imediatamente)
  - `true`: Após aprovação, gestor precisa liberar dinheiro manualmente

### 3. Valor Máximo para Aprovação Automática
- **Tipo**: Input numérico (decimal)
- **Padrão**: `0.00`
- **Descrição**: Empréstimos com valor menor ou igual a este valor serão aprovados automaticamente (mesmo com "Requer Aprovação" marcado)
- **Impacto**:
  - `0.00`: Desabilitado (sempre segue validações normais)
  - `> 0.00`: Empréstimos até este valor são aprovados automaticamente, ignorando dívida ativa e limite de crédito
- **Nota**: O sistema usa o maior valor entre este campo e o `valor_aprovacao_automatica` da operação

### 4. Permitir Múltiplas Operações
- **Tipo**: Switch (checkbox)
- **Padrão**: `true`
- **Descrição**: Se desmarcado, a empresa terá apenas uma operação
- **Impacto**: Controla se a empresa pode ter múltiplas operações ou apenas uma

## 🔄 Fluxos de Trabalho

### Fluxo 1: Empresa Pequena (1 Pessoa)

**Configuração:**
```json
{
  "workflow": {
    "requer_aprovacao": false,
    "requer_liberacao": false,
    "aprovacao_automatica_valor_max": 0
  }
}
```

**Fluxo:**
```
1. Consultor cria empréstimo
   ↓
2. Status: 'aprovado' (automático, ignora validações)
   ↓
3. Liberação criada e liberada automaticamente
   ↓
4. Consultor confirma pagamento ao cliente
   ↓
5. Status: 'ativo'
```

**Características:**
- ✅ Sem pendências
- ✅ Sem necessidade de gestor
- ✅ Processo rápido
- ✅ Tudo feito pela mesma pessoa

### Fluxo 2: Empresa Grande (Múltiplos Gestores)

**Configuração:**
```json
{
  "workflow": {
    "requer_aprovacao": true,
    "requer_liberacao": true,
    "aprovacao_automatica_valor_max": 1000.00
  }
}
```

**Fluxo:**
```
1. Consultor cria empréstimo
   ↓
2. Se valor ≤ R$ 1.000 → Status: 'aprovado' (automático)
   Se valor > R$ 1.000 → Status: 'pendente' (aguarda aprovação)
   ↓
3. Se pendente → Gestor aprova → Status: 'aprovado'
   ↓
4. Gestor libera dinheiro → Status: 'liberado'
   ↓
5. Consultor confirma pagamento ao cliente
   ↓
6. Status: 'ativo'
```

**Características:**
- ✅ Controles rigorosos
- ✅ Validações de dívida e limite
- ✅ Aprovação de gestor necessária
- ✅ Liberação de dinheiro controlada

### Fluxo 3: Empresa Média (Aprovação Automática por Valor)

**Configuração:**
```json
{
  "workflow": {
    "requer_aprovacao": true,
    "requer_liberacao": false,
    "aprovacao_automatica_valor_max": 500.00
  }
}
```

**Fluxo:**
```
1. Consultor cria empréstimo
   ↓
2. Se valor ≤ R$ 500 → Status: 'aprovado' (automático, ignora validações)
   Se valor > R$ 500 → Aplica validações → 'pendente' ou 'aprovado'
   ↓
3. Se aprovado → Liberação automática
   ↓
4. Consultor confirma pagamento ao cliente
   ↓
5. Status: 'ativo'
```

**Características:**
- ✅ Valores pequenos: automático
- ✅ Valores grandes: requer aprovação
- ✅ Liberação sempre automática após aprovação

## 💻 Implementação Técnica

### 1. Model: `Empresa`

**Métodos Helper:**
```php
// Verificar se requer aprovação
$empresa->requerAprovacao(): bool

// Verificar se requer liberação
$empresa->requerLiberacao(): bool

// Obter valor máximo para aprovação automática
$empresa->getValorAprovacaoAutomatica(): float

// Obter configuração específica
$empresa->getConfiguracao(string $key, $default = null)

// Definir configuração específica
$empresa->setConfiguracao(string $key, $value): void
```

### 2. Service: `EmprestimoService`

#### Método `criar()`

**Lógica de Aprovação:**
```php
// 1. Verificar se empresa requer aprovação
$requerAprovacao = $empresa->requerAprovacao();

// 2. Se não requer → aprovar automaticamente
if (!$requerAprovacao) {
    $status = 'aprovado';
}
// 3. Se requer → aplicar validações normais
else {
    // Verificar valor de aprovação automática
    $valorAprovacaoAutomatica = max(
        $operacao->valor_aprovacao_automatica ?? 0,
        $empresa->getValorAprovacaoAutomatica()
    );
    
    if ($valorAprovacaoAutomatica > 0 && $valor <= $valorAprovacaoAutomatica) {
        $status = 'aprovado'; // Aprovação automática
    } else {
        // Aplicar validações (dívida ativa, limite de crédito)
        $status = $temDividaAtiva || $limiteExcedido ? 'pendente' : 'aprovado';
    }
}
```

**Lógica de Liberação:**
```php
// Se aprovado, verificar se requer liberação
if ($status === 'aprovado') {
    $requerLiberacao = $empresa->requerLiberacao();
    
    if ($requerLiberacao) {
        // Criar liberação pendente (gestor precisa liberar)
        $this->criarLiberacaoPendente($emprestimo);
    } else {
        // Liberar automaticamente
        $liberacao = $this->criarLiberacaoPendente($emprestimo);
        $liberacaoService->liberar($liberacao->id, $consultorId, 'Liberação automática');
    }
}
```

#### Método `aprovar()`

**Lógica:**
```php
// Verificar se empresa requer liberação
$requerLiberacao = $empresa->requerLiberacao();

if ($requerLiberacao) {
    // Criar liberação pendente (gestor precisa liberar)
    $this->criarLiberacaoPendente($emprestimo);
} else {
    // Liberar automaticamente
    $liberacao = $this->criarLiberacaoPendente($emprestimo);
    $liberacaoService->liberar($liberacao->id, $aprovadorId, 'Liberação automática');
}
```

### 3. Controller: `SuperAdmin/EmpresaController`

**Método `store()` e `update()`:**
```php
// Montar configurações a partir do request
$configuracoes = [
    'workflow' => [
        'requer_aprovacao' => $request->has('requer_aprovacao') ? (bool) $request->requer_aprovacao : true,
        'requer_liberacao' => $request->has('requer_liberacao') ? (bool) $request->requer_liberacao : true,
        'aprovacao_automatica_valor_max' => (float) ($request->aprovacao_automatica_valor_max ?? 0),
    ],
    'operacoes' => [
        'permite_multiplas_operacoes' => $request->has('permite_multiplas_operacoes') ? (bool) $request->permite_multiplas_operacoes : true,
    ],
];

$validated['configuracoes'] = $configuracoes;
```

### 4. Views

**Localização:**
- `resources/views/super-admin/empresas/create.blade.php`
- `resources/views/super-admin/empresas/edit.blade.php`

**Seção:** "Configurações de Workflow"

**Campos:**
- Switch: `requer_aprovacao`
- Switch: `requer_liberacao`
- Input numérico: `aprovacao_automatica_valor_max`
- Switch: `permite_multiplas_operacoes`

## 📊 Tabela de Decisão

| Requer Aprovação | Requer Liberação | Valor ≤ Limite Auto | Resultado |
|-----------------|------------------|---------------------|-----------|
| ❌ | ❌ | - | Aprovado → Liberado automaticamente |
| ❌ | ✅ | - | Aprovado → Liberação pendente |
| ✅ | ❌ | ✅ | Aprovado (auto) → Liberado automaticamente |
| ✅ | ❌ | ❌ | Pendente/Aprovado → Liberado automaticamente |
| ✅ | ✅ | ✅ | Aprovado (auto) → Liberação pendente |
| ✅ | ✅ | ❌ | Pendente/Aprovado → Liberação pendente |

## 🔍 Validações e Regras

### Regra 1: Valor de Aprovação Automática
- O sistema usa o **maior valor** entre:
  - `operacao.valor_aprovacao_automatica`
  - `empresa.configuracoes.workflow.aprovacao_automatica_valor_max`

### Regra 2: Aprovação Automática
- Se `requer_aprovacao = false` → **Sempre aprova**, ignora todas as validações
- Se `requer_aprovacao = true` e `valor ≤ limite_auto` → **Aprova automaticamente**, ignora validações
- Se `requer_aprovacao = true` e `valor > limite_auto` → **Aplica validações** (dívida ativa, limite de crédito)

### Regra 3: Liberação Automática
- Se `requer_liberacao = false` → **Libera automaticamente** após aprovação
- Se `requer_liberacao = true` → **Aguarda gestor** liberar manualmente

### Regra 4: Status do Empréstimo
- `aprovado` → Empréstimo aprovado, aguardando liberação/pagamento
- `ativo` → Empréstimo ativo, dinheiro já foi pago ao cliente
- `pendente` → Aguardando aprovação de gestor

## 🎨 Interface do Usuário

### Seção de Configurações

A seção "Configurações de Workflow" aparece nas views de criação/edição de empresa com:

1. **Alerta informativo**: Explica que empresas pequenas geralmente não precisam de aprovação/liberação
2. **Switches**: Para ativar/desativar funcionalidades
3. **Input numérico**: Para valor de aprovação automática
4. **Textos explicativos**: Abaixo de cada campo, explicando o impacto

### Exemplo Visual

```
┌─────────────────────────────────────────────────┐
│ Configurações de Workflow                       │
├─────────────────────────────────────────────────┤
│ ℹ️ Configure como a empresa trabalha.          │
│    Empresas pequenas (1 pessoa) geralmente não   │
│    precisam de aprovação/liberação.             │
│                                                 │
│ ☑ Requer Aprovação de Empréstimos              │
│   Se desmarcado, todos os empréstimos serão     │
│   aprovados automaticamente                     │
│                                                 │
│ ☑ Requer Liberação de Dinheiro                 │
│   Se desmarcado, o dinheiro será liberado       │
│   automaticamente após aprovação                │
│                                                 │
│ Valor Máximo para Aprovação Automática:         │
│ [1000.00]                                       │
│ Empréstimos com valor menor ou igual serão      │
│ aprovados automaticamente                       │
│                                                 │
│ ☑ Permitir Múltiplas Operações                 │
└─────────────────────────────────────────────────┘
```

## 📝 Exemplos de Uso

### Exemplo 1: Empresa "João Sozinho"

**Cenário:** João trabalha sozinho, não precisa de controles.

**Configuração:**
- Requer Aprovação: ❌
- Requer Liberação: ❌
- Valor Máximo Auto: 0

**Resultado:**
- João cria empréstimo → Aprovado automaticamente
- Liberação automática → João confirma pagamento
- Empréstimo ativo → Sem pendências

### Exemplo 2: Empresa "Grande Operação"

**Cenário:** Múltiplos gestores, controles rigorosos.

**Configuração:**
- Requer Aprovação: ✅
- Requer Liberação: ✅
- Valor Máximo Auto: 1000.00

**Resultado:**
- Consultor cria empréstimo de R$ 500 → Aprovado automaticamente
- Consultor cria empréstimo de R$ 5000 → Pendente → Gestor aprova → Gestor libera → Consultor confirma
- Processo completo com controles

### Exemplo 3: Empresa "Média Operação"

**Cenário:** Alguns controles, mas valores pequenos são automáticos.

**Configuração:**
- Requer Aprovação: ✅
- Requer Liberação: ❌
- Valor Máximo Auto: 500.00

**Resultado:**
- Empréstimo de R$ 300 → Aprovado automaticamente → Liberado automaticamente
- Empréstimo de R$ 2000 → Pendente → Gestor aprova → Liberado automaticamente
- Valores pequenos: rápido | Valores grandes: requer aprovação

## 🔧 Manutenção e Extensibilidade

### Adicionar Nova Configuração

1. **Adicionar campo na view:**
```blade
<div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" name="nova_config" id="nova_config" value="1">
    <label class="form-check-label" for="nova_config">
        <strong>Nova Configuração</strong>
    </label>
</div>
```

2. **Processar no controller:**
```php
$configuracoes = [
    'workflow' => [
        // ... outras configs
        'nova_config' => $request->has('nova_config') ? (bool) $request->nova_config : false,
    ],
];
```

3. **Adicionar método helper no model:**
```php
public function temNovaConfig(): bool
{
    return $this->getConfiguracao('workflow.nova_config', false);
}
```

4. **Usar no service:**
```php
if ($empresa->temNovaConfig()) {
    // Lógica específica
}
```

## ⚠️ Observações Importantes

1. **Compatibilidade com Operações:**
   - O valor de aprovação automática da empresa é combinado com o da operação
   - O sistema usa o maior valor entre os dois

2. **Status do Empréstimo:**
   - Mesmo com liberação automática, o status só muda para `ativo` quando o consultor confirma o pagamento ao cliente
   - Isso mantém o controle de que o dinheiro foi realmente pago

3. **Auditoria:**
   - Todas as ações são auditadas, incluindo liberações automáticas
   - A mensagem de auditoria indica quando é automática: "Liberação automática - empresa não requer aprovação de gestor"

4. **Notificações:**
   - Quando a liberação é automática, o consultor recebe notificação imediatamente
   - Não há notificação para gestores (já que não há ação deles)

## 🚀 Próximos Passos (Futuro)

- [ ] Adicionar configuração por operação (além de por empresa)
- [ ] Permitir configuração de permissões específicas por empresa
- [ ] Dashboard de configurações para visualizar todas as empresas e seus workflows
- [ ] Histórico de mudanças nas configurações
- [ ] Templates de configuração (pequena, média, grande empresa)

## 📚 Arquivos Relacionados

- `app/Modules/Core/Models/Empresa.php` - Model com métodos helper
- `app/Modules/Core/Services/EmpresaService.php` - Service para gerenciar empresas
- `app/Modules/Loans/Services/EmprestimoService.php` - Integração no fluxo de empréstimos
- `app/Http/Controllers/SuperAdmin/EmpresaController.php` - Controller para gerenciar empresas
- `resources/views/super-admin/empresas/create.blade.php` - View de criação
- `resources/views/super-admin/empresas/edit.blade.php` - View de edição
- `database/migrations/2026_01_23_190610_create_empresas_table.php` - Migration da tabela empresas

---

**Última atualização:** 2026-01-23
**Versão:** 1.0.0

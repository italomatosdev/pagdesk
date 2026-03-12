# Arquitetura do Sistema

## 📐 Visão Geral

O sistema foi projetado seguindo uma arquitetura modular (bounded contexts) dentro do mesmo Laravel, permitindo escalabilidade e manutenção facilitada.

## 🏗️ Estrutura Modular

### Módulo Core (Base)

**Responsabilidade**: Funcionalidades base do sistema que são compartilhadas por todos os módulos.

**Componentes:**
- **Operações**: Empresas internas do sistema
- **Usuários e Papéis**: Sistema de autenticação e autorização
- **Clientes Globais**: Cadastro único de clientes (sem vínculo direto com operação)
- **Documentos KYC**: Documentos dos clientes
- **Auditoria**: Sistema de log de ações críticas

**Localização:**
```
app/Modules/Core/
├── Models/
│   ├── Operacao.php
│   ├── Usuario.php (extends User)
│   ├── Cliente.php
│   ├── ClientDocument.php
│   └── OperationClient.php
├── Services/
│   ├── ClienteService.php
│   ├── OperacaoService.php
│   └── PermissionService.php
└── Controllers/
    ├── ClienteController.php
    ├── OperacaoController.php
    └── UsuarioController.php
```

### Módulo Loans (Empréstimos)

**Responsabilidade**: Gestão completa de empréstimos em dinheiro.

**Componentes:**
- **Empréstimos**: Criação, aprovação, gestão
- **Parcelas**: Geração automática e gestão
- **Pagamentos**: Registro de recebimentos
- **Cobranças**: Listagem de parcelas vencidas e do dia

**Regras de Negócio:**
- Cliente só pode ter um empréstimo ativo por operação
- Validação de limite de crédito por operação
- Aprovação automática ou pendente conforme regras
- Geração automática de parcelas (diária, semanal, mensal)

**Localização:**
```
app/Modules/Loans/
├── Models/
│   ├── Emprestimo.php
│   ├── Parcela.php
│   └── Pagamento.php
├── Services/
│   ├── EmprestimoService.php
│   ├── ParcelaService.php
│   └── PagamentoService.php
└── Controllers/
    ├── EmprestimoController.php
    ├── ParcelaController.php
    └── PagamentoController.php
```

### Módulo Cash (Caixa/Financeiro)

**Responsabilidade**: Gestão financeira e prestação de contas.

**Componentes:**
- **Movimentações de Caixa**: Entradas e saídas do consultor
- **Prestação de Contas**: Settlements dos consultores
- **Conferência**: Validação pelo Gestor

**Localização:**
```
app/Modules/Cash/
├── Models/
│   ├── CashLedgerEntry.php
│   └── Settlement.php
├── Services/
│   ├── CashService.php
│   └── SettlementService.php
└── Controllers/
    ├── CashController.php
    └── SettlementController.php
```

### Módulo Approvals (Aprovações)

**Responsabilidade**: Gestão de aprovações e exceções.

**Componentes:**
- **Fila de Aprovações**: Empréstimos pendentes
- **Histórico**: Decisões de aprovação/rejeição
- **Auditoria**: Registro de decisões

**Localização:**
```
app/Modules/Approvals/
├── Models/
│   └── Aprovacao.php
├── Services/
│   └── AprovacaoService.php
└── Controllers/
    └── AprovacaoController.php
```

## 🔄 Fluxo de Dados

### Fluxo de Empréstimo

```
1. Consultor cria empréstimo
   ↓
2. EmprestimoService valida:
   - Dívida ativa? → Pendente
   - Limite excedido? → Pendente
   - Tudo OK? → Aprovado automaticamente
   ↓
3. Se aprovado → Gera parcelas automaticamente
   ↓
4. Se pendente → Vai para fila de aprovações
   ↓
5. Admin aprova/rejeita → Auditoria registrada
```

### Fluxo de Pagamento

```
1. Consultor registra pagamento
   ↓
2. PagamentoService:
   - Cria registro de pagamento
   - Marca parcela como paga
   - Cria movimentação no caixa do consultor
   ↓
3. Consultor presta contas (settlement)
   ↓
4. Gestor confere e valida
   ↓
5. Auditoria registrada
```

## 🗄️ Modelo de Dados

### Cliente Global

- **Tabela**: `clientes`
- **Características**:
  - CPF único (VARCHAR(11), apenas números)
  - Sem `operacao_id` (global)
  - Vínculo com operações via `operation_clients` (pivô)

### Vínculo Cliente-Operação

- **Tabela**: `operation_clients` (pivô)
- **Campos importantes**:
  - `credit_limit`: Limite de crédito por operação
  - `status`: ativo/bloqueado
  - `notas_internas`: Observações

### Empréstimo

- **Tabela**: `loans` (emprestimos)
- **Status**: draft, pendente, aprovado, ativo, finalizado, cancelado
- **Relacionamentos**:
  - Pertence a uma Operação
  - Pertence a um Cliente
  - Criado por um Consultor
  - Tem muitas Parcelas

### Parcela

- **Tabela**: `installments` (parcelas)
- **Status**: pendente, paga, atrasada, cancelada
- **Campos**:
  - `data_vencimento`: Data de vencimento
  - `valor`: Valor da parcela
  - `valor_pago`: Valor já pago (suporta parcial no futuro)

## 🔐 Sistema de Permissões

### Papéis

1. **Administrador**: Acesso total
2. **Gestor**: Visualização e conferência
3. **Consultor**: Operações do dia a dia
4. **Cliente**: Sem acesso (apenas entidade)

### Implementação

- Usar `spatie/laravel-permission` ou sistema próprio
- Permissões granulares por ação
- Middleware de autorização em rotas

## 📝 Auditoria

### Ações Auditadas

- Criar empréstimo
- Aprovar/rejeitar empréstimo
- Alterar limite de crédito
- Registrar pagamento
- Ajustar parcela
- Estornar pagamento
- Validar prestação de contas

### Estrutura de Auditoria

- **Tabela**: `audit_logs`
- **Campos**:
  - `user_id`: Quem fez
  - `action`: Ação realizada
  - `model_type`: Tipo do modelo afetado
  - `model_id`: ID do modelo afetado
  - `old_values`: Valores anteriores (JSON)
  - `new_values`: Valores novos (JSON)
  - `ip_address`: IP do usuário
  - `user_agent`: Navegador
  - `created_at`: Timestamp

## 🎨 Frontend

### Estrutura de Views

```
resources/views/
├── layouts/
│   ├── master.blade.php      # Layout principal
│   ├── sidebar.blade.php     # Menu lateral
│   └── ...
├── clientes/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── show.blade.php
├── emprestimos/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── show.blade.php
└── ...
```

### Componentes Reutilizáveis

- Cards do Webadmin
- Tables do Webadmin
- Forms do Webadmin
- Modals do Webadmin

## 🔧 Padrões de Código

### Services

- **Responsabilidade**: Toda regra de negócio
- **Localização**: `app/Modules/{Module}/Services/`
- **Convenção**: `{Entity}Service.php`
- **Métodos**: camelCase

### Controllers

- **Responsabilidade**: Apenas orquestração
- **Localização**: `app/Modules/{Module}/Controllers/`
- **Convenção**: `{Entity}Controller.php`
- **Ações**: index, create, store, show, edit, update, destroy

### Models

- **Responsabilidade**: Relacionamentos e acessors/mutators
- **Localização**: `app/Modules/{Module}/Models/`
- **Convenção**: PascalCase (ex: `Cliente`, `Emprestimo`)
- **Tabelas**: snake_case (ex: `clientes`, `emprestimos`)

## 🚀 Escalabilidade

### Preparação para Futuro

- **Módulo Troca de Cheque**: Estrutura preparada
- **Módulo Garantia/Empenho**: Estrutura preparada
- **Jobs**: Preparados para alertas e tarefas agendadas
- **API**: Estrutura preparada para futura API REST

## 📊 Decisões Arquiteturais

1. **Modular mas monolítico**: Módulos separados mas no mesmo Laravel
2. **Services para regras**: Nenhuma regra de negócio em Controllers
3. **Auditoria obrigatória**: Todas as ações críticas são auditadas
4. **Cliente global**: CPF único, vínculo via pivô
5. **Multi-operação**: Dados operacionais sempre com `operacao_id`
6. **Aprovação flexível**: Sistema permite exceções com aprovação manual


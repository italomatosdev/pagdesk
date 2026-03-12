# Estrutura de Pastas do Projeto

## 📁 Visão Geral

```
sistema-cred/
├── app/
│   ├── Console/
│   │   ├── Commands/
│   │   │   └── MarcarParcelasAtrasadas.php
│   │   └── Kernel.php
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── HomeController.php
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Models/
│   │   └── User.php
│   ├── Modules/                    # Módulos do sistema
│   │   ├── Core/
│   │   │   ├── Controllers/
│   │   │   │   ├── ClienteController.php
│   │   │   │   ├── OperacaoController.php
│   │   │   │   └── UsuarioController.php
│   │   │   ├── Models/
│   │   │   │   ├── Cliente.php
│   │   │   │   ├── Operacao.php
│   │   │   │   ├── OperationClient.php
│   │   │   │   ├── ClientDocument.php
│   │   │   │   ├── Role.php
│   │   │   │   ├── Permission.php
│   │   │   │   └── Auditoria.php
│   │   │   ├── Services/
│   │   │   │   ├── ClienteService.php
│   │   │   │   ├── OperacaoService.php
│   │   │   │   └── PermissionService.php
│   │   │   └── Traits/
│   │   │       └── Auditable.php
│   │   ├── Loans/
│   │   │   ├── Controllers/
│   │   │   │   ├── EmprestimoController.php
│   │   │   │   ├── ParcelaController.php
│   │   │   │   └── PagamentoController.php
│   │   │   ├── Models/
│   │   │   │   ├── Emprestimo.php
│   │   │   │   ├── Parcela.php
│   │   │   │   └── Pagamento.php
│   │   │   └── Services/
│   │   │       ├── EmprestimoService.php
│   │   │       ├── ParcelaService.php
│   │   │       └── PagamentoService.php
│   │   ├── Cash/
│   │   │   ├── Controllers/
│   │   │   │   ├── CashController.php
│   │   │   │   └── SettlementController.php
│   │   │   ├── Models/
│   │   │   │   ├── CashLedgerEntry.php
│   │   │   │   └── Settlement.php
│   │   │   └── Services/
│   │   │       ├── CashService.php
│   │   │       └── SettlementService.php
│   │   └── Approvals/
│   │       ├── Controllers/
│   │       │   └── AprovacaoController.php
│   │       ├── Models/
│   │       │   └── Aprovacao.php
│   │       └── Services/
│   │           └── AprovacaoService.php
│   └── Providers/
├── bootstrap/
├── config/
├── database/
│   ├── migrations/
│   │   ├── 2024_12_20_100000_create_operacoes_table.php
│   │   ├── 2024_12_20_100100_create_roles_table.php
│   │   ├── 2024_12_20_100200_create_permissions_table.php
│   │   ├── 2024_12_20_100300_create_role_user_table.php
│   │   ├── 2024_12_20_100400_create_permission_role_table.php
│   │   ├── 2024_12_20_100500_create_clientes_table.php
│   │   ├── 2024_12_20_100600_create_client_documents_table.php
│   │   ├── 2024_12_20_100700_create_operation_clients_table.php
│   │   ├── 2024_12_20_200000_create_emprestimos_table.php
│   │   ├── 2024_12_20_200100_create_parcelas_table.php
│   │   ├── 2024_12_20_200200_create_pagamentos_table.php
│   │   ├── 2024_12_20_300000_create_cash_ledger_entries_table.php
│   │   ├── 2024_12_20_300100_create_settlements_table.php
│   │   ├── 2024_12_20_400000_create_aprovacoes_table.php
│   │   └── 2024_12_20_500000_create_audit_logs_table.php
│   ├── seeders/
│   │   ├── DatabaseSeeder.php
│   │   ├── RoleSeeder.php
│   │   ├── PermissionSeeder.php
│   │   ├── UserSeeder.php
│   │   └── OperacaoSeeder.php
│   └── factories/
├── docs/                          # Documentação
│   ├── arquitetura.md
│   ├── PLANO_IMPLEMENTACAO.md
│   ├── PROGRESSO.md
│   ├── STATUS_ATUAL.md
│   ├── rotas.md
│   ├── GUIA_INSTALACAO.md
│   └── ESTRUTURA_PASTAS.md
├── public/
│   └── build/                     # Assets compilados
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── master.blade.php
│   │   │   ├── sidebar.blade.php
│   │   │   └── ...
│   │   ├── clientes/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   ├── show.blade.php
│   │   │   └── edit.blade.php
│   │   ├── emprestimos/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   └── show.blade.php
│   │   ├── cobrancas/
│   │   │   └── index.blade.php
│   │   ├── pagamentos/
│   │   │   └── create.blade.php
│   │   ├── caixa/
│   │   │   └── index.blade.php
│   │   ├── prestacoes/
│   │   │   ├── index.blade.php
│   │   │   └── create.blade.php
│   │   ├── aprovacoes/
│   │   │   └── index.blade.php
│   │   ├── operacoes/
│   │   └── usuarios/
│   ├── js/
│   ├── scss/
│   └── lang/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
└── tests/
```

## 📂 Descrição das Pastas Principais

### `app/Modules/`
Estrutura modular do sistema, organizada por contexto de negócio.

### `database/migrations/`
Migrations organizadas por data e módulo:
- `100xxx` - Core
- `200xxx` - Loans
- `300xxx` - Cash
- `400xxx` - Approvals
- `500xxx` - Auditoria

### `resources/views/`
Views Blade organizadas por módulo, reutilizando componentes do template Webadmin.

### `docs/`
Documentação completa do projeto.

## 🔍 Convenções

- **Models**: PascalCase (ex: `Cliente`, `Emprestimo`)
- **Controllers**: PascalCase + Controller (ex: `ClienteController`)
- **Services**: PascalCase + Service (ex: `ClienteService`)
- **Tabelas**: snake_case (ex: `clientes`, `emprestimos`)
- **Migrations**: Data + descrição (ex: `2024_12_20_100000_create_clientes_table.php`)

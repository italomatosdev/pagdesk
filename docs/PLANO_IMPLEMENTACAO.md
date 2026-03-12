# Plano de Implementação - Fase 1

## 📅 Visão Geral

Este documento detalha o plano de implementação da Fase 1 do Sistema de Crédito e Cobrança, incluindo ordem de execução, dependências e entregáveis.

## 🎯 Objetivo da Fase 1

Implementar a fundação do sistema + MVP do módulo de Empréstimos em Dinheiro, incluindo:
- Core (base do sistema)
- Módulo de Empréstimos funcional
- Caixa e Prestação de Contas (mínimo)
- Sistema de Aprovações
- Auditoria
- Interface completa com menus

## 📋 Sequência de Implementação

### Etapa 1: Preparação e Estrutura Base ✅
- [x] Criar documentação inicial
- [ ] Copiar estrutura do template Webadmin
- [ ] Configurar ambiente Laravel
- [ ] Criar estrutura modular (app/Modules/)

**Entregáveis:**
- Projeto Laravel funcional
- Estrutura de pastas organizada
- Template Webadmin integrado

---

### Etapa 2: Core - Migrations e Models
- [ ] Criar migrations:
  - `operations` (operacoes)
  - `users` (usuarios) - ajustar se necessário
  - `roles` e `permissions` (ou usar spatie/laravel-permission)
  - `clients` (clientes) - com CPF único
  - `client_documents` (documentos KYC)
  - `operation_clients` (vinculos_cliente_operacao) - com credit_limit
- [ ] Criar Models:
  - `Operacao`
  - `Usuario` (ajustar User existente)
  - `Cliente`
  - `ClientDocument`
  - `OperationClient` (VinculoClienteOperacao)
- [ ] Configurar relacionamentos Eloquent

**Entregáveis:**
- Todas as migrations do Core
- Models com relacionamentos configurados
- Seeders básicos para testar

---

### Etapa 3: Core - Services e Regras
- [ ] Criar Services:
  - `ClienteService` (cadastro, busca por CPF, vinculação)
  - `OperacaoService` (CRUD)
  - `PermissionService` (gerenciar papéis e permissões)
- [ ] Implementar validações:
  - CPF único
  - Cliente global (sem operacao_id)
  - Vínculo cliente-operacao

**Entregáveis:**
- Services do Core funcionais
- Validações implementadas
- Testes básicos

---

### Etapa 4: Módulo Loans - Migrations
- [ ] Criar migrations:
  - `loans` (emprestimos)
  - `installments` (parcelas)
  - `payments` (pagamentos)
- [ ] Definir enums/status:
  - Loan: draft, pendente, aprovado, ativo, finalizado, cancelado
  - Installment: pendente, paga, atrasada, cancelada
  - Payment: métodos (dinheiro, pix, outro)

**Entregáveis:**
- Migrations do módulo Loans
- Enums definidos

---

### Etapa 5: Módulo Loans - Models e Services
- [ ] Criar Models:
  - `Emprestimo` (Loan)
  - `Parcela` (Installment)
  - `Pagamento` (Payment)
- [ ] Criar Services:
  - `EmprestimoService`:
    - Validar dívida ativa
    - Validar limite de crédito
    - Aprovação automática ou pendente
    - Gerar parcelas automaticamente
  - `ParcelaService`:
    - Marcar como paga
    - Marcar como atrasada (job)
  - `PagamentoService`:
    - Registrar pagamento
    - Atualizar status da parcela

**Entregáveis:**
- Models com relacionamentos
- Services com regras de negócio
- Geração automática de parcelas

---

### Etapa 6: Módulo Cash - Migrations e Models
- [ ] Criar migrations:
  - `cash_ledger_entries` (movimentacoes_caixa)
  - `settlements` (prestacoes_contas)
- [ ] Criar Models:
  - `CashLedgerEntry`
  - `Settlement`
- [ ] Criar Services:
  - `CashService`: registrar movimentações
  - `SettlementService`: criar e validar prestações

**Entregáveis:**
- Estrutura de Caixa implementada
- Prestação de contas funcional

---

### Etapa 7: Módulo Approvals
- [ ] Criar migration: `approvals` (aprovacoes)
- [ ] Criar Model: `Aprovacao`
- [ ] Criar Service: `AprovacaoService`
  - Listar pendentes
  - Aprovar/rejeitar
  - Registrar auditoria

**Entregáveis:**
- Sistema de aprovações funcional
- Fila de pendências

---

### Etapa 8: Auditoria
- [ ] Criar migration: `audit_logs`
- [ ] Criar Model: `Auditoria`
- [ ] Criar Helper/Trait: `Auditable`
- [ ] Criar Middleware (se necessário)
- [ ] Integrar em Services críticos:
  - Criar empréstimo
  - Aprovar/rejeitar
  - Alterar limite
  - Registrar pagamento
  - Validar prestação

**Entregáveis:**
- Sistema de auditoria completo
- Logs de ações críticas

---

### Etapa 9: Controllers e Rotas
- [ ] Criar Controllers:
  - `ClienteController`
  - `EmprestimoController`
  - `ParcelaController` (Cobranças do Dia)
  - `PagamentoController`
  - `CashController`
  - `SettlementController`
  - `AprovacaoController`
  - `OperacaoController` (admin)
  - `UsuarioController` (admin)
- [ ] Criar Requests (validação):
  - Para cada ação crítica
- [ ] Configurar rotas em `web.php`
- [ ] Aplicar middleware de autenticação e permissões

**Entregáveis:**
- Todos os controllers criados
- Rotas configuradas
- Validações implementadas

---

### Etapa 10: Views Blade
- [ ] Criar views reutilizando componentes Webadmin:
  - `clientes/index.blade.php` (listagem)
  - `clientes/create.blade.php` (cadastro)
  - `clientes/show.blade.php` (detalhes)
  - `emprestimos/index.blade.php`
  - `emprestimos/create.blade.php`
  - `emprestimos/show.blade.php`
  - `cobrancas/index.blade.php` (Cobranças do Dia)
  - `pagamentos/create.blade.php`
  - `caixa/index.blade.php`
  - `prestacoes/index.blade.php`
  - `aprovacoes/index.blade.php`
  - `operacoes/index.blade.php` (admin)
  - `usuarios/index.blade.php` (admin)
- [ ] Usar componentes do template (cards, tables, forms)

**Entregáveis:**
- Interface completa
- Design consistente com Webadmin

---

### Etapa 11: Menus e Navegação
- [ ] Editar `resources/views/layouts/sidebar.blade.php`
- [ ] Adicionar menus:
  - Clientes
  - Empréstimos
  - Cobranças do Dia
  - Caixa / Prestação de Contas
  - Aprovações (admin)
  - Operações (admin)
  - Usuários/Permissões (admin)
- [ ] Aplicar permissões nos menus (mostrar apenas para quem tem acesso)

**Entregáveis:**
- Sidebar completo
- Navegação funcional

---

### Etapa 12: Seeders
- [ ] Criar `RoleSeeder`: papéis (Administrador, Gestor, Consultor, Cliente)
- [ ] Criar `PermissionSeeder`: permissões mínimas
- [ ] Criar `UserSeeder`: usuário admin
- [ ] Criar `OperacaoSeeder`: operação de exemplo (dev)
- [ ] Atualizar `DatabaseSeeder`

**Entregáveis:**
- Seeders completos
- Ambiente dev configurado

---

### Etapa 13: Jobs e Scheduler
- [ ] Criar Job: `MarcarParcelasAtrasadas`
- [ ] Configurar no `app/Console/Kernel.php`
- [ ] (Opcional) Criar Job: `GerarAlertas` (preparar para futuro)

**Entregáveis:**
- Jobs configurados
- Scheduler funcionando

---

### Etapa 14: Documentação Final
- [ ] Documentar todas as rotas
- [ ] Criar guia de uso
- [ ] Documentar estrutura de pastas
- [ ] Criar diagramas (se necessário)
- [ ] Atualizar README

**Entregáveis:**
- Documentação completa
- Guias de uso

---

## ✅ Checklist Final

- [ ] Todas as migrations executadas
- [ ] Seeders executados
- [ ] Rotas testadas
- [ ] Permissões funcionando
- [ ] Interface completa
- [ ] Auditoria registrando
- [ ] Jobs agendados
- [ ] Documentação atualizada

## 📊 Métricas de Sucesso

- ✅ Sistema funcional end-to-end
- ✅ Regras de negócio implementadas
- ✅ Interface completa e responsiva
- ✅ Permissões funcionando corretamente
- ✅ Auditoria registrando ações críticas
- ✅ Documentação completa

## 🔄 Próximas Fases (Futuro)

- Módulo Troca de Cheque
- Módulo Garantia/Empenho
- Integração WhatsApp (alertas)
- Relatórios avançados
- Dashboard com métricas


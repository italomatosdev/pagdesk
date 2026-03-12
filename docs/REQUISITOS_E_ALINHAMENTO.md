# Requisitos de Negócio, Carga e Alinhamento do Sistema

Documento que responde às perguntas de requisitos, carga, totalizadores, modelagem, arquitetura e infraestrutura, e **confronta com o estado atual** do sistema (sistema-cred).

---

## 1) Requisitos de negócio

### Papéis do sistema

**Resposta desejada:** Admin, Empresa, Consultor, Operador, etc.

**Estado atual:**  
- **Administrador** – Acesso total; gerencia operações e usuários; vê todas as operações.  
- **Gestor** – Visualização e conferência: empréstimos, parcelas, pagamentos, cobranças do dia, caixa, conferir prestação de contas.  
- **Consultor** – Operacional: criar/editar cliente, criar empréstimo, registrar pagamento, ver cobranças do dia, caixa, criar prestação de contas.  
- **Cliente** – Papel definido no seed, mas sem acesso ao sistema (futuro).  

Não existe papel explícito “Empresa” ou “Operador”; o controle por empresa é via `empresa_id` no usuário e escopo global (EmpresaScope). Operação é um conceito dentro de empresa (uma empresa tem várias operações).

---

### Multiempresa e CPF/cliente

**Pergunta:** É multiempresa? Um CPF/cliente pode existir em mais de uma empresa?

**Estado atual:**  
- **Sim, multiempresa.** Existe `empresas`, `empresa_id` em users, operações, clientes, empréstimos, parcelas, pagamentos, liberações, caixa, settlements.  
- **Cliente:** Chave natural é `documento` (CPF/CNPJ), **única globalmente** na tabela `clientes` (não é único por empresa). Um mesmo CPF existe uma vez; pode ser **vinculado** a várias empresas via `empresa_cliente_vinculos`. A empresa que cadastrou tem `cliente.empresa_id`; as outras acessam o cliente por vínculo. Ou seja: um CPF/cliente **pode** “existir” em mais de uma empresa via vinculação, mas há um único registro de cliente no banco.

---

### Trilha de auditoria

**Pergunta:** Precisamos de trilha de auditoria (quem alterou, quando, antes/depois)?

**Estado atual:**  
- **Sim, implementada.** Tabela `audit_logs` (model `Auditoria`) com: `user_id`, `action`, `model_type`, `model_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `observacoes`.  
- Trait `Auditable` e método `auditar()` usados em: ClienteService, EmprestimoService, PagamentoService, LiberacaoService, ParcelaService, ChequeService, CashService, SettlementService, AprovacaoService, OperacaoService, PermissionService e em alguns Commands.  
- Tela de auditoria em Super Admin (`/super-admin/auditoria`).  
- **Gap:** Nem todo controller/service chama `auditar()` em toda alteração; pode haver pontos sem registro (ex.: atualizações pontuais de campos).

---

### Impressão/recibo e exportação (Excel/PDF)

**Pergunta:** Precisamos de impressão/recibo, exportação Excel/PDF?

**Estado atual:**  
- **Impressão/recibo:** Não há módulo dedicado de “recibo” ou “imprimir contrato/parcela”.  
- **Exportação:** Não há rotas ou jobs para exportar listagens/relatórios em Excel ou PDF.  
- Há apenas **upload** de arquivos (comprovantes em PDF/imagem) em pagamentos, liberações, cheques, movimentações de caixa, prestação de contas.  
- **Conclusão:** Impressão/recibo e exportação Excel/PDF **não estão atendidos**; são requisitos a implementar se forem necessários.

---

### Os 3 dashboards mais importantes

**Pergunta:** Quais são os 3 dashboards mais importantes do cliente?

**Estado atual:**  
- **Dashboard por papel:** Super Admin, Administrador, Gestor, Consultor (um único endpoint `/dashboard` que escolhe a view conforme o papel).  
- **Super Admin:** Empresas, usuários, operações, auditoria.  
- **Administrador:** Totalizadores por operação (clientes, empréstimos, parcelas, liberações, caixa, empenhos, cheques), gráficos (empréstimos por status, cheques por status), tabelas (liberações pendentes, empréstimos aprovados, parcelas vencidas, ranking consultores, etc.).  
- **Gestor:** Métricas operacionais (liberações pendentes, valor pendente, parcelas/valor em atraso, valor a receber, liberado/recebido hoje/semana/mês, taxa recuperação, fluxo de caixa, projeção), tabelas (liberações aguardando, empréstimos aprovados, parcelas vencidas, ranking consultores, consultores com liberações não pagas / alta inadimplência, resumo por operação) e **seção de cheques** (totais e gráfico por status).  
- **Consultor:** Foco em cobranças do dia e prestação de contas.  

Os “3 mais importantes” para o negócio podem ser definidos como: (1) **Dashboard Gestor**, (2) **Dashboard Admin**, (3) **Dashboard Consultor** (ou Kanban de pendências). O sistema já entrega esses três; falta apenas formalizar prioridade e SLA com o cliente.

---

### Telas que precisam ser instantâneas (até ~1s)

**Pergunta:** Quais telas precisam ser instantâneas (até 1s)?

**Estado atual:**  
- Nenhum contrato de performance (SLA) explícito no código.  
- Dashboard (admin/gestor) faz várias queries agregadas no request, sem cache; em volume alto pode passar de 1s.  
- Listagens (clientes, empréstimos, parcelas, cheques, etc.) usam paginação; podem ficar pesadas se não houver índices e se houver N+1.  
- **Recomendação:** Definir com o negócio quais telas são “críticas” (ex.: dashboard, listagem de cobranças do dia, tela de pagamento) e medir; depois cache/índices/async conforme necessário.

---

## 2) Carga e performance

### Volume por dia (inserções, atualizações, consultas)

**Pergunta:** Volume por dia de inserções, atualizações, consultas; picos; evento mais pesado; importação em massa.

**Estado atual:**  
- Nenhum número ou meta documentada no projeto.  
- Não há métricas de uso (APM, logs de request, contadores).  
- **Recomendação:** Coletar estimativas do negócio (ex.: X empréstimos/dia, Y pagamentos/dia, Z acessos ao dashboard) e definir pico (ex.: 9h–12h). O “evento” mais pesado tende a ser o **dashboard** (muitas agregações) ou uma listagem grande sem índice; importação em massa **não existe** hoje (sem CSV/API de lote).

---

### Importação em massa (CSV/planilha/API)

**Estado atual:**  
- Não há rotas ou jobs para importação em massa (clientes, empréstimos, parcelas).  
- Se for requisito, será feature nova (API ou upload CSV + fila).

---

## 3) Totalizadores e relatórios

### Totalizadores existentes

**Pergunta:** Quais totalizadores existem? (total recebido hoje/mês, em atraso, a vencer, saldo caixa, por consultor, etc.)

**Estado atual (no DashboardController):**  
- Total recebido: hoje, semana, mês (e crescimento mensal).  
- Em atraso: parcelas vencidas, valor parcelas vencidas.  
- A vencer: valor total a receber, projeção 7 dias.  
- Liberações: pendentes, valor pendente, liberado hoje/semana/mês, taxa pagamento ao cliente, tempo médio liberação.  
- Caixa: fluxo de caixa (entradas − saídas).  
- Por consultor: ranking (valor emprestado, recebido, taxa, inadimplência).  
- Por operação: resumo (quantidade, valor total, valor recebido, taxa recuperação).  
- Cheques: total, valor bruto/líquido, vencendo hoje, por status.  
- Empenhos: totais e por status (quando há dados).  

Ou seja: os principais totalizadores **existem** e são calculados no request.

---

### Totalizadores: tempo real ou cache (30–60s)? Dashboard com cache (Redis) ou tabela pré-agregada?

**Pergunta:** Totalizadores em tempo real ou aceitam 30–60s? Dashboard pode usar cache (Redis) com TTL ou tabela de resumo?

**Estado atual:**  
- Totalizadores são **sempre calculados em tempo real** no request (sem cache Redis nem tabela de resumo).  
- Cache default do Laravel é `file` (config cache.php); não há uso de Redis para dashboard.  
- **Conclusão:** Se o volume crescer, faz sentido: (1) cache Redis com TTL (ex.: 30–60s) para totais do dashboard, e/ou (2) tabela de resumo atualizada por job/evento. Hoje não está implementado.

---

### Relatórios assíncronos (fila)

**Pergunta:** Quais relatórios podem ser assíncronos (fila) em vez de rodar no request?

**Estado atual:**  
- Não há relatórios “pesados” implementados (nem export Excel/PDF).  
- Quando existirem (ex.: relatório de inadimplência, extrato por período), o ideal é **job em fila** + notificação ou download quando pronto. Hoje a fila default é `sync` (config queue.php); não há jobs de relatório.

---

## 4) Modelagem de dados e integridade

### Chave natural de Cliente (CPF/documento); multiempresa

**Pergunta:** Chave natural de Cliente? CPF? Único por empresa?

**Estado atual:**  
- Chave natural: **documento** (CPF ou CNPJ, sem formatação).  
- **Único globalmente** na tabela `clientes` (`unique('documento')`), não “por empresa”.  
- Multiempresa: mesmo cliente (mesmo documento) acessado por várias empresas via `empresa_cliente_vinculos`; quem criou é `cliente.empresa_id`.

---

### Campos críticos (NOT NULL, constraints, FKs); valores calculados; fonte da verdade

**Pergunta:** Campos críticos com NOT NULL/constraints/FKs? Valores calculados gravados? Fonte da verdade (pagamentos vs parcela)? Concorrência (transaction + lock)?

**Estado atual:**  
- Migrations usam `nullable()` em vários campos; não há documento central listando “obrigatórios por regra de negócio”.  
- Foreign keys existem (ex.: `emprestimo_id`, `parcela_id`, `empresa_id`).  
- **Valores calculados:** `parcela.valor_pago` é atualizado ao registrar pagamento (PagamentoService); soma dos pagamentos da parcela é a fonte lógica, e `valor_pago` é cache na parcela.  
- **Fonte da verdade:** Pagamentos estão na tabela `pagamentos`; a parcela agrega em `valor_pago` e `status` para performance.  
- **Concorrência:** PagamentoService usa `DB::transaction` e `Parcela::lockForUpdate()` ao registrar pagamento, evitando duplicar/somar errado.  
- **Gap:** Revisar migrations para garantir NOT NULL onde a regra exige e documentar; manter FKs e transações com lock nos fluxos críticos.

---

## 5) Índices e consultas

### Top queries, filtros, N+1, paginação

**Pergunta:** Top 10 queries, filtros mais usados (empresa_id, status, data, consultor_id), N+1, paginação obrigatória, revisão com EXPLAIN/slow log.

**Estado atual:**  
- Filtros típicos: `empresa_id` (via scope), `operacao_id` (dashboard e listagens), `status` (empréstimo, parcela, liberação, cheque), `data_vencimento`, `data_pagamento`, `consultor_id`.  
- Índices: existem em tabelas (ex.: `documento` em clientes); não há documento de “índices recomendados” por query.  
- N+1: risco em listagens que carregam relacionamentos (ex.: cliente->emprestimos, parcela->emprestimo->cliente); uso de `with()` em vários pontos, mas não garantido em todas as listagens.  
- Paginação: usada em várias listagens (ex.: clientes, empréstimos); não está “obrigatória” por política em todas.  
- **Recomendação:** Listar as 10 telas/relatórios mais usados, rodar EXPLAIN nas queries principais e ajustar índices; ativar slow query log; revisar N+1 (eager load) e forçar paginação em listagens grandes.

---

## 6) Arquitetura Laravel

### Service layer / Actions, DTO, Request validation, Jobs

**Pergunta:** Service layer/Actions, DTO/Request validation, Jobs.

**Estado atual:**  
- **Services:** Sim. Há camada de serviços (ClienteService, EmprestimoService, PagamentoService, LiberacaoService, ParcelaService, ChequeService, CashService, SettlementService, AprovacaoService, OperacaoService, PermissionService, etc.); controllers delegam para eles.  
- **Actions:** Não há padrão “Action” explícito (objetos únicos por ação); a lógica está nos services.  
- **DTO:** Não há DTOs formais; arrays e models são usados.  
- **Request validation:** Validação feita nos controllers com `$request->validate(...)`; não há Form Request classes dedicadas em quantidade.  
- **Jobs:** Não há jobs de aplicação (apenas uso de queue config com driver `sync` por default).  
- **Conclusão:** Service layer forte; DTO e Form Requests podem evoluir; Jobs ainda não usados para processos pesados.

---

### Controle de acesso (Policies/Gates, CASL, matriz por empresa)

**Pergunta:** Policies/Gates (e CASL no front), matriz de permissões por empresa.

**Estado atual:**  
- **Permissões:** Tabelas `roles`, `permissions`, `role_user`, `permission_role`; seeders definem papéis e permissões por nome.  
- **Checagem:** `$user->hasRole()`, `$user->hasPermission()` (e `hasAnyRole`) nos controllers; não há Policies/Gates registrados no AuthServiceProvider.  
- **Front:** Não há CASL ou lib equivalente referenciada; o menu/rotas são condicionados por papel no backend (e possivelmente no Blade).  
- **Por empresa:** Acesso por empresa é via `empresa_id` no user e EmpresaScope (global scope); usuário só vê dados da própria empresa (e vinculados). Não há “matriz” explícita por empresa além disso.  
- **Recomendação:** Se a regra crescer (ex.: permissões por operação ou por empresa), considerar Policies + possível CASL no front.

---

### Logs, exceções, respostas (API)

**Pergunta:** Padronização de logs, exceções, respostas API.

**Estado atual:**  
- Logs: Laravel default (channel em config/logging); não há padrão estruturado (ex.: request_id, empresa_id, user_id) em todos os pontos.  
- Exceções: Handler padrão; ValidationException usada nos services; não há formato único de erro para API.  
- API: Rotas `api` existem (ex.: busca global, busca usuários); não há padrão documentado de JSON (ex.: envelope { data, meta, errors }).  
- **Recomendação:** Definir formato de resposta API e de log estruturado (incluindo contexto tenant/user) e aplicar de forma consistente.

---

## 7) Filas e processamento assíncrono

**Pergunta:** O que entra em fila desde o dia 1? Driver? Retries, dead-letter, idempotência?

**Estado atual:**  
- Queue default: **sync** (env('QUEUE_CONNECTION','sync')).  
- Nenhum job de aplicação (relatório, conciliação, webhook, notificação, recalcular totalizador) implementado.  
- Retries, dead-letter e idempotência não estão configurados para fila (apenas opções padrão do Laravel).  
- **Recomendação:** Para produção com relatórios/notificações: usar Redis + Horizon; criar jobs para relatórios pesados, notificações e, se necessário, atualização de totais; definir retries, falhas e idempotência (ex.: chave por recurso).

---

## 8) Cache (onde usar e invalidar)

**Pergunta:** O que cachear? Invalidação (por evento vs TTL)? Chaves por empresa?

**Estado atual:**  
- Cache default: **file**.  
- Nenhum uso explícito de cache para dashboard, listas ou permissões no código analisado.  
- **Recomendação:** Cache para totais do dashboard (TTL 30–60s), possivelmente listas “caras” e permissões do usuário; chaves tipo `empresa:{id}:dashboard:totals`; invalidação por TTL e, se necessário, por evento (ex.: ao registrar pagamento, invalidar totais da operação).

---

## 9) Infraestrutura e deploy

**Pergunta:** Ambiente (1 servidor vs separado), deploy (pipeline, rollback, migrations), PHP-FPM/Nginx (max_children, timeouts, upload).

**Estado atual:**  
- Guia de instalação (docs) cobre desenvolvimento (composer, migrations, seeders, storage).  
- Não há descrição de ambiente de produção (1 servidor vs app/db separados), nem pipeline (GitHub Actions, etc.), nem estratégia de rollback.  
- Upload: há script `ajustar-limites-upload.sh` e docs (CONFIGURACAO_UPLOAD, ERRO_UPLOAD_ARQUIVOS) para limites de upload.  
- **Recomendação:** Documentar ambiente alvo, pipeline de deploy, rollback e migrations sem derrubar app; revisar PHP-FPM/Nginx (max_children, timeouts, upload) conforme carga.

---

## 10) Banco de dados e backup

**Pergunta:** MySQL vs Postgres; backup (diário/horário, retenção, restore testado); monitoramento (slow queries, conexões, disco).

**Estado atual:**  
- Config: **MySQL** como default (env('DB_CONNECTION','mysql')); Postgres também configurado.  
- Docs mencionam backup apenas em avisos (ex.: “nunca zerar em produção sem backup”). Não há política de backup (frequência, retenção, restore testado) nem monitoramento de slow query/conexões/disco.  
- **Recomendação:** Definir backup (ex.: diário, retenção 7/30/90 dias), testar restore periodicamente; ativar e revisar slow query log; monitorar conexões e disco.

---

## 11) Observabilidade

**Pergunta:** Logs estruturados (empresa_id, user_id, request_id), métricas (tempo resposta, query, fila), Sentry/APM.

**Estado atual:**  
- Logs padrão Laravel; não há middleware ou padrão que injete request_id/empresa_id/user_id em todos os logs.  
- Não há métricas de tempo de resposta por endpoint, tempo de query ou tamanho da fila.  
- Sentry/APM não referenciados no projeto.  
- **Recomendação:** Log estruturado com contexto (request_id, user_id, empresa_id); métricas básicas (tempo de request, queries lentas); considerar Sentry para erros e, se necessário, APM.

---

## 12) Segurança e compliance

**Pergunta:** Rate limit (login, criação, pagamentos), anti-bot/brute force, criptografia de dados sensíveis, LGPD, cross-tenant.

**Estado atual:**  
- Rate limit: API tem RateLimiter 60/min no RouteServiceProvider; throttle genérico no Kernel; verificação de email tem throttle 6/min. **Login** usa trait AuthenticatesUsers (Laravel), que já aplica throttle em tentativas de login. Não há limite específico para “criação” ou “pagamentos”.  
- Anti-bot/brute force: apenas o throttle do Laravel no login.  
- Dados sensíveis: não há criptografia explícita de campos (ex.: documento) no banco; arquivos em storage.  
- LGPD: não há fluxo documentado de exclusão/anonimização nem política de retenção de logs.  
- Cross-tenant: EmpresaScope e filtros por `empresa_id`/operação evitam vazamento entre empresas; super admin não tem escopo (vê tudo).  
- **Recomendação:** Rate limit mais forte em login e, se necessário, em endpoints de pagamento/criação; definir criptografia para dados sensíveis e processo LGPD (exclusão/anonimização, logs).

---

## 13) Testes e qualidade

**Pergunta:** Quais fluxos terão testes? Staging igual produção? Seeds e dados de teste?

**Estado atual:**  
- Apenas ExampleTest (Feature e Unit) padrão do Laravel; **não há** testes de criação de cliente, geração de parcela, pagamento ou atualização de status.  
- Não há documentação de ambiente de staging nem de seeds/dados de teste representativos.  
- **Recomendação:** Priorizar testes automatizados para: criação de cliente, criação de empréstimo/parcelas, registro de pagamento e atualização de status; alinhar staging com produção e seeds para testes.

---

## 14) Perguntas de maturidade

**Perguntas:** O que acontece se Redis cair? Se o job rodar duas vezes? Se a migration travar? Plano de crescimento (separar DB do app)? Como garantir que totalizadores não fiquem inconsistentes?

**Estado atual:**  
- **Redis:** Não usado em produção hoje (queue sync, cache file); se passar a usar Redis, queda = perda de fila/cache; definir fallback (ex.: fila sync ou degradação).  
- **Job duas vezes:** Nenhum job crítico implementado; quando houver (ex.: pagamento, conciliação), desenhar idempotência (ex.: chave por parcela + data).  
- **Migration travar:** Sem estratégia documentada (lock timeout, rollback, deploy sem downtime).  
- **Crescimento:** Sem plano documentado de quando separar app e DB.  
- **Totalizadores:** Hoje sempre calculados nas queries (soma de pagamentos, etc.); consistência depende da integridade dos dados. Se no futuro houver tabela de resumo, ela precisa ser atualizada dentro de transação ou por job idempotente após eventos.  
- **Recomendação:** Documentar cenários de falha (Redis, job duplicado, migration), plano de crescimento e estratégia de consistência dos totalizadores (tempo real vs cache/resumo).

---

## Resumo: o que está alinhado e o que falta

| Área | Alinhado | Em falta / Recomendação |
|------|----------|--------------------------|
| Papéis | Admin, Gestor, Consultor, Cliente (sem acesso) | Formalizar “Operador”/“Empresa” se fizer sentido |
| Multiempresa | Sim; cliente por documento + vínculos | Documentar regra de unicidade por empresa (ou manter global) |
| Auditoria | Tabela + trait + tela Super Admin | Garantir auditoria em 100% das alterações críticas |
| Impressão/Export | Não | Implementar recibo e export Excel/PDF se necessário |
| Dashboards | 4 dashboards por papel; cheques no admin/gestor | Definir “3 mais importantes” e SLA (ex.: &lt;1s) |
| Totalizadores | Muitos totais no request | Cache Redis + TTL ou tabela resumo se volume subir |
| Fonte da verdade / Lock | Pagamentos + parcela.valor_pago; lockForUpdate no pagamento | Manter e replicar em outros fluxos críticos |
| Service layer | Forte | DTO/Form Request e Jobs onde fizer sentido |
| Permissões | Roles + permissions; hasRole/hasPermission | Policies (e CASL no front) se regras crescerem |
| Filas/Cache | Queue sync; cache file | Redis + Horizon; cache para dashboard e listas pesadas |
| Testes | Apenas exemplo | Testes em fluxos críticos (cliente, empréstimo, pagamento) |
| Infra/Backup/Obs/Segurança | Parcial ou ausente | Documentar e implementar backup, observabilidade, rate limit, LGPD |

Este documento serve como **checklist de requisitos e alinhamento**: cada seção pode ser detalhada ou alterada conforme decisões do negócio e da equipe técnica.

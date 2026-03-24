# Changelog

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

## [2026-01-24] - Transferência do Caixa da Operação (admin)

### ✅ Adicionado

- **Transferência (apenas administrador da operação):** saída no **Caixa da Operação** e entrada no caixa de um **gestor ou administrador** na mesma operação; destino pode ser **outro** ou **o próprio** admin (aviso + `confirm` no formulário).
- **Rotas:** `GET /caixa/transferencia-operacao/create`, `POST /caixa/transferencia-operacao`; comprovante opcional (`comprovantes/transferencia-operacao`).
- **Service:** `CashService::transferirDoCaixaOperacaoParaUsuario`; categorias `transferencia_caixa_operacao` em `CashCategoriaAutomaticaService`.
- **UI:** botão na listagem de caixa (só quem tem papel administrador em alguma operação); filtro e badge “Transferência”.

---

## [2026-01-24] - Sangria para o Caixa da Operação

### ✅ Adicionado

- **Sangria (gestor/admin):** transferência do **próprio caixa** para o **Caixa da Operação** (`consultor_id` NULL), em um fluxo único com saída + entrada (`origem` automática, categorias `sangria_caixa_operacao`).
- **Rotas:** `GET /caixa/sangria/create`, `POST /caixa/sangria` (`caixa.sangria.create` / `caixa.sangria.store`).
- **UI:** botão na tela de Movimentações de Caixa; filtro por tipo de referência “Sangria”; **comprovante opcional** (PDF/imagem, mesmas regras da movimentação manual), gravado nos dois lançamentos.
- **Service:** `CashService::transferirParaCaixaOperacao`.
- **Categorias automáticas:** mapeamento em `CashCategoriaAutomaticaService` para `sangria_caixa_operacao|saida` e `|entrada`.

## [2026-01-26] - Novo Status de Parcelas: quitada_garantia

### ✅ Adicionado

#### Novo Status de Parcelas: `quitada_garantia`
- **Novo status**: Parcelas podem ter status `quitada_garantia` quando garantia é executada
- **Enum atualizado**: `('pendente', 'paga', 'atrasada', 'cancelada', 'quitada_garantia')`
- **Comportamento**: Parcelas não pagas são marcadas como `quitada_garantia` com `valor_pago = 0`
- **Preservação de histórico**: Parcelas já pagas mantêm status `paga` (não são alteradas)
- **Finalização**: Empréstimo é finalizado quando todas as parcelas estão `paga` OU `quitada_garantia`
- **Validações**: Parcelas `quitada_garantia` não podem receber novos pagamentos
- **Métodos auxiliares no model Parcela**:
  - `isQuitadaGarantia()`: Verifica se status é `quitada_garantia`
  - `isQuitada()`: Verifica se está paga OU quitada por garantia
  - `isTotalmentePaga()`: Atualizado para considerar `quitada_garantia` como totalmente quitada
  - `getStatusNomeAttribute()`: Retorna nome legível do status
  - `getStatusCorAttribute()`: Retorna cor do badge do status (info/azul para quitada_garantia)
- **Migration**: `add_quitada_garantia_to_parcelas_status_enum.php`

### ✅ Modificado

#### EmprestimoService
- **Método `executarGarantia()`**: Atualizado para marcar parcelas não pagas como `quitada_garantia`
  - Preserva parcelas já pagas (mantém histórico)
  - Marca todas as parcelas não pagas (pendentes/atrasadas) como `quitada_garantia`
  - Registra auditoria para cada parcela marcada
  - `valor_pago = 0` para parcelas quitadas por garantia (não houve pagamento real)

#### PagamentoService
- **Método `verificarFinalizacaoEmprestimo()`**: Atualizado para considerar parcelas `paga` OU `quitada_garantia` como quitadas
  - Finaliza empréstimo quando todas as parcelas estão quitadas (pagas ou quitadas por garantia)
- **Método `registrar()`**: Validação atualizada para bloquear pagamentos em parcelas `quitada_garantia`
  - Mensagem específica para parcelas quitadas por garantia

#### ParcelaService
- **Método `marcarComoPaga()`**: Validação atualizada para considerar parcelas quitadas
  - Bloqueia tentativas de marcar como paga se já está quitada por garantia

#### Parcela Model
- **Método `calcularDiasAtraso()`**: Atualizado para considerar parcelas quitadas (retorna 0)

#### EmprestimoService (Cancelamento)
- **Método `cancelar()`**: Validação atualizada para considerar parcelas quitadas
  - Não permite cancelar se há parcelas `paga` OU `quitada_garantia`

#### FinalizarEmprestimosQuitados Command
- Atualizado para considerar parcelas `paga` OU `quitada_garantia` como quitadas
- Mensagens atualizadas para usar termo "quitadas" ao invés de "pagas"

#### Views
- **`emprestimos/show.blade.php`**: 
  - Usa `status_nome` e `status_cor` para exibir status das parcelas
  - Badge azul (info) para parcelas `quitada_garantia`
  - Botão de pagamento oculto para parcelas quitadas (`isQuitada()`)
- **`dashboard/consultor.blade.php`**: 
  - Atualizado para usar `status_nome` e `status_cor`

### 🔧 Técnico

#### Migration
- **`add_quitada_garantia_to_parcelas_status_enum`**: Adiciona `quitada_garantia` ao enum de status de parcelas

### 📝 Documentação
- **`docs/EXECUCAO_GARANTIAS.md`**: Atualizado com informações sobre novo status de parcelas
- **`docs/FINALIZACAO_EMPRESTIMOS.md`**: Atualizado para considerar `quitada_garantia` na finalização

## [2026-01-26] - Execução de Garantias (Empréstimos Tipo Empenho)

### ✅ Adicionado

#### Novo Status de Parcelas: `quitada_garantia`
- **Novo status**: Parcelas podem ter status `quitada_garantia` quando garantia é executada
- **Enum atualizado**: `('pendente', 'paga', 'atrasada', 'cancelada', 'quitada_garantia')`
- **Comportamento**: Parcelas não pagas são marcadas como `quitada_garantia` com `valor_pago = 0`
- **Preservação de histórico**: Parcelas já pagas mantêm status `paga` (não são alteradas)
- **Finalização**: Empréstimo é finalizado quando todas as parcelas estão `paga` OU `quitada_garantia`
- **Validações**: Parcelas `quitada_garantia` não podem receber novos pagamentos
- **Métodos auxiliares no model Parcela**:
  - `isQuitadaGarantia()`: Verifica se status é `quitada_garantia`
  - `isQuitada()`: Verifica se está paga OU quitada por garantia
  - `isTotalmentePaga()`: Atualizado para considerar `quitada_garantia` como totalmente quitada
  - `getStatusNomeAttribute()`: Retorna nome legível do status
  - `getStatusCorAttribute()`: Retorna cor do badge do status (info/azul para quitada_garantia)
- **Migration**: `add_quitada_garantia_to_parcelas_status_enum.php`

## [2026-01-26] - Execução de Garantias (Empréstimos Tipo Empenho)

### ✅ Adicionado

#### Sistema de Execução de Garantias
- **Status de garantias**: Sistema de status para garantias (`ativa`, `liberada`, `executada`)
- **Execução manual**: Gestores e administradores podem executar garantias quando há parcelas atrasadas
- **Liberação automática**: Garantias são liberadas automaticamente quando empréstimo é totalmente pago
- **Finalização automática**: Empréstimo é finalizado automaticamente quando garantia é executada
- **Campos adicionados**:
  - `status` em `emprestimo_garantias` (ENUM: ativa, liberada, executada)
  - `data_liberacao`: Data/hora quando garantia foi liberada
  - `data_execucao`: Data/hora quando garantia foi executada
- **Execução via formulário de pagamento**: Opção "Executar Garantia" como tipo de juros/multa
- **Interface adaptativa**: Campos de pagamento são ocultados quando executar garantia está selecionado
- **Botão dinâmico**: Botão muda para "Executar Garantia" (vermelho) quando opção está selecionada
- **Validações**: Observações obrigatórias (mínimo 10 caracteres) para rastreabilidade
- **Auditoria completa**: Registra auditoria tanto da execução da garantia quanto da finalização do empréstimo
- **Notificações**: Envia notificações para consultor e cliente quando garantia é executada ou liberada
- **Documentação**: `docs/EXECUCAO_GARANTIAS.md` criada com documentação completa

#### Fluxos de Execução
- **Via página de detalhes**: Botão "Executar Garantia" redireciona para formulário de pagamento com opção pré-selecionada
- **Via formulário de pagamento**: Opção "Executar Garantia" aparece nas opções de tipo de juros/multa quando há parcela atrasada
- **Pré-seleção automática**: Quando vem da página de detalhes, opção já vem marcada
- **Ocultação de campos**: Campos de pagamento são ocultados automaticamente quando executar garantia está selecionado

### ✅ Modificado

#### EmprestimoService
- **Método `executarGarantia()`**: Novo método que executa garantia e finaliza empréstimo
  - Valida todas as condições (tipo empenho, status ativo, parcela atrasada, garantia ativa)
  - Atualiza status da garantia para `executada`
  - Marca parcelas não pagas como `quitada_garantia` (preserva parcelas já pagas)
  - Finaliza empréstimo automaticamente
  - Registra auditorias (garantia, empréstimo e cada parcela)
  - Envia notificações

#### PagamentoService
- **Método `verificarFinalizacaoEmprestimo()`**: Atualizado para considerar parcelas `paga` OU `quitada_garantia` como quitadas
  - Finaliza empréstimo quando todas as parcelas estão quitadas (pagas ou quitadas por garantia)
  - Libera automaticamente garantias quando empréstimo tipo "empenho" é totalmente pago
  - Atualiza status da garantia para `liberada`
  - Preenche `data_liberacao`
  - Registra auditoria
  - Envia notificações
- **Método `registrar()`**: Validação atualizada para bloquear pagamentos em parcelas `quitada_garantia`

#### PagamentoController
- **Método `create()`**: Aceita parâmetro `executar_garantia` para pré-selecionar opção
- **Método `store()`**: Processa execução de garantia quando `tipo_juros = 'executar_garantia'`
  - Valida condições
  - Chama `EmprestimoService::executarGarantia()`
  - Não registra pagamento (ou registra R$ 0,00)
  - Validação condicional: campos de pagamento não obrigatórios quando executar garantia

#### EmprestimoGarantia Model
- **Novos métodos auxiliares**:
  - `isAtiva()`: Verifica se status é `ativa`
  - `isLiberada()`: Verifica se status é `liberada`
  - `isExecutada()`: Verifica se status é `executada`
  - `getStatusNomeAttribute()`: Retorna nome legível do status
  - `getStatusCorAttribute()`: Retorna cor do badge do status
- **Novos campos no `$fillable`**: `status`, `data_liberacao`, `data_execucao`
- **Novos casts**: `data_liberacao` e `data_execucao` como `datetime`

#### Parcela Model
- **Novos métodos auxiliares**:
  - `isQuitadaGarantia()`: Verifica se status é `quitada_garantia`
  - `isQuitada()`: Verifica se está paga OU quitada por garantia
  - `isTotalmentePaga()`: Atualizado para considerar `quitada_garantia` como totalmente quitada
  - `getStatusNomeAttribute()`: Retorna nome legível do status (incluindo "Quitada (Garantia)")
  - `getStatusCorAttribute()`: Retorna cor do badge do status (info/azul para quitada_garantia)
- **Método `calcularDiasAtraso()`**: Atualizado para considerar parcelas quitadas (retorna 0)

#### Views
- **`emprestimos/show.blade.php`**:
  - Botão "Executar Garantia" aparece quando há parcela atrasada e garantia ativa
  - Redireciona para formulário de pagamento com parâmetro `executar_garantia=1`
  - Exibe status da garantia com badge colorido (ativa/liberada/executada)
  - Exibe `data_liberacao` ou `data_execucao` quando aplicável
  - Exibe status das parcelas usando `status_nome` e `status_cor` (incluindo "Quitada (Garantia)")
  - Botão de pagamento oculto para parcelas quitadas (`isQuitada()`)
  
- **`pagamentos/create.blade.php`**:
  - Opção "Executar Garantia" nas opções de tipo de juros/multa
  - JavaScript oculta campos de pagamento quando opção está selecionada
  - Botão muda dinamicamente para "Executar Garantia" (vermelho)
  - Validação JavaScript de observações (mínimo 10 caracteres)
  - Pré-seleção automática quando vem com parâmetro `executar_garantia=1`
  - Campo de observações/motivo obrigatório para execução
  - Lista de garantias disponíveis na confirmação (SweetAlert)
  
- **`dashboard/consultor.blade.php`**:
  - Atualizado para usar `status_nome` e `status_cor` das parcelas

#### Notificacao Model
- **Novos tipos de notificação**:
  - `garantia_executada`: Quando garantia é executada manualmente
  - `garantia_liberada`: Quando garantia é liberada automaticamente
- **Ícones e cores**: Configurados para os novos tipos de notificação

### 🔧 Técnico

#### Migrations
- **`add_status_to_emprestimo_garantias_table`**: Adiciona campos `status`, `data_liberacao`, `data_execucao`
  - `status`: ENUM('ativa', 'liberada', 'executada') com default 'ativa'
  - Atualiza garantias existentes para status 'ativa' se não tiverem status
- **`add_quitada_garantia_to_parcelas_status_enum`**: Adiciona novo status `quitada_garantia` ao enum de parcelas
  - Enum completo: `('pendente', 'paga', 'atrasada', 'cancelada', 'quitada_garantia')`

#### Rotas
- **Rota existente mantida**: `POST /emprestimos/{id}/garantias/{garantiaId}/executar` (ainda funciona, mas não é mais usada na UI principal)
- **Nova integração**: Execução via formulário de pagamento (`POST /pagamentos`)

### 📝 Documentação
- **`docs/EXECUCAO_GARANTIAS.md`**: Documentação completa criada
  - Visão geral e ciclo de vida das garantias
  - Quando uma garantia pode ser executada
  - Fluxos de execução (via página de detalhes e via formulário)
  - O que acontece ao executar uma garantia
  - Liberação automática
  - Interface do usuário
  - Validações (frontend e backend)
  - Código responsável
  - Exemplos de uso
  - Troubleshooting

## [2026-01-24] - Sistema de Dados e Documentos do Cliente por Empresa

### ✅ Adicionado

#### Sistema de Override de Dados por Empresa
- **Nova tabela `cliente_dados_empresa`**: Armazena dados específicos de um cliente para cada empresa
- **Campos sobrescritos**: Nome, telefone, email, data_nascimento, responsável legal, endereço, observações
- **Lógica de prioridade**: Dados de override têm prioridade sobre dados originais
- **Accessors automáticos**: Todos os campos têm accessors que verificam override automaticamente
- **Cache de dados**: Sistema de cache para evitar múltiplas queries

#### Sistema de Documentos por Empresa
- **Campo `empresa_id` em `client_documents`**: Permite documentos específicos por empresa
- **Documentos originais**: `empresa_id = null` (da empresa criadora)
- **Documentos específicos**: `empresa_id` preenchido (da empresa que anexou)
- **Prioridade de exibição**: Documentos específicos têm prioridade sobre originais
- **Método `getDocumentoPorCategoria()`**: Retorna documento correto (específico ou original)

#### Modelos e Métodos
- **`Cliente::isEmpresaCriadora()`**: Verifica se empresa atual é criadora do cliente
- **`Cliente::dadosPorEmpresa()`**: Obtém dados específicos de uma empresa
- **`Cliente::getDocumentoPorCategoria()`**: Obtém documento priorizando específico da empresa
- **`ClientDocument::isDocumentoOriginal()`**: Verifica se é documento original
- **`ClientDocument::isDocumentoEmpresa()`**: Verifica se é documento específico de empresa

#### Comportamento por Tipo de Empresa
- **Empresa Criadora**:
  - Vê apenas dados e documentos originais
  - Pode editar dados originais diretamente
  - Não vê dados/documentos de outras empresas
- **Outras Empresas**:
  - Vê dados originais + seus próprios overrides
  - Pode criar/editar seus próprios dados (salvos em override)
  - Vê documentos originais (read-only) + seus próprios documentos
  - Pode adicionar/editar apenas seus próprios documentos

### ✅ Modificado

#### ClienteService
- **`atualizar()`**: Agora verifica se empresa é criadora e decide onde salvar
- **`processarDocumentosAtualizacao()`**: Considera empresa atual ao salvar documentos
- **Busca global**: Usa `withoutGlobalScope()` para permitir edição de clientes de outras empresas

#### ClienteController
- **`show()`**: Carrega `dadosEmpresa` e documentos filtrados por empresa
- **`edit()`**: Carrega dados de override se necessário
- **`update()`**: Chama método correto baseado em se empresa é criadora

#### Views
- **`clientes/show.blade.php`**: Usa `getDocumentoPorCategoria()` e exibe badges de tipo de documento
- **`clientes/edit.blade.php`**: Exibe alerta quando editando cliente de outra empresa

#### Modelo Cliente
- **Relacionamento `documentos()`**: Filtra documentos baseado na empresa atual
- **Accessors**: Todos os campos sobrescritos têm accessors que verificam override
- **Método `getDadosEmpresaAtual()`**: Busca dados específicos da empresa atual

### 📝 Documentação
- **`docs/DADOS_CLIENTE_POR_EMPRESA.md`**: Documentação completa do sistema
  - Estrutura de dados
  - Comportamento do sistema
  - Modelos e métodos
  - Exemplos de código
  - Fluxo de uso
  - Troubleshooting

### 🔧 Migrations
- **`create_cliente_dados_empresa_table`**: Cria tabela para dados específicos por empresa
- **`add_empresa_id_to_client_documents_table`**: Adiciona campo `empresa_id` em documentos

## [2026-01-20] - Prestação de Contas: Fluxo Completo com Confirmação de Recebimento

### ✅ Adicionado

#### Novo Fluxo de Prestação de Contas
- **4 etapas**: Criar → Aprovar → Anexar Comprovante → Confirmar Recebimento
- **Status simplificados**: `pendente`, `aprovado`, `enviado`, `concluido`, `rejeitado`
- **Anexar comprovante**: Consultor pode anexar comprovante após aprovação
- **Confirmar recebimento**: Gestor confirma recebimento e sistema gera movimentações automaticamente
- **Campos adicionados**:
  - `comprovante_path`: Caminho do comprovante anexado
  - `enviado_em`: Quando o consultor anexou o comprovante
  - `recebido_por`: ID do gestor que confirmou recebimento
  - `recebido_em`: Quando o gestor confirmou recebimento
- **Movimentações automáticas**: Ao confirmar recebimento, gera:
  - Saída do caixa do consultor (com comprovante)
  - Entrada no caixa do gestor (mesmo valor)
- **Validações**: 
  - Movimentações só são geradas após confirmação do gestor
  - Comprovante obrigatório antes de confirmar recebimento
  - Apenas consultor dono pode anexar comprovante
- **Documentação**: `docs/PRESTACAO_CONTAS_FLUXO.md` criada

### ✅ Modificado

#### SettlementService
- Método `aprovar()`: Unifica aprovação (gestor e admin podem aprovar)
- Método `anexarComprovante()`: Novo método para consultor anexar comprovante (não gera movimentações)
- Método `confirmarRecebimento()`: Novo método que gera movimentações automaticamente
- Método `rejeitar()`: Ajustado para permitir rejeição de prestações pendentes ou aprovadas
- Removidos métodos `conferir()` e `validar()` (substituídos por `aprovar()`)

#### SettlementController
- Método `aprovar()`: Substitui `conferir()` e `validar()`
- Método `anexarComprovante()`: Novo método para consultor anexar comprovante
- Método `confirmarRecebimento()`: Novo método para gestor confirmar recebimento
- Método `rejeitar()`: Ajustado para novo fluxo

#### Modelo Settlement
- Novos campos adicionados ao `$fillable`
- Novos métodos: `isAprovado()`, `isEnviado()`, `isConcluido()`
- Relacionamento `recebedor()` adicionado

#### Views
- `prestacoes/index.blade.php`: Atualizada com novos botões e status
- Modais Sweet Alert para anexar comprovante e rejeitar
- Exibição de comprovante quando anexado

#### Rotas
- `POST /prestacoes/{id}/aprovar` - Aprovar prestação
- `POST /prestacoes/{id}/rejeitar` - Rejeitar prestação
- `POST /prestacoes/{id}/anexar-comprovante` - Anexar comprovante
- `POST /prestacoes/{id}/confirmar-recebimento` - Confirmar recebimento
- Removidas rotas antigas: `conferir` e `validar`

### 🔧 Técnico

#### Migration
- `add_settlement_fields_for_payment_confirmation`: Adiciona campos necessários e ajusta enum de status

## [2026-01-20] - Modelo de Caixa: Operação e Usuários

### ✅ Adicionado

#### Sistema de Caixa da Operação
- **`consultor_id` nullable**: Movimentações podem ser do caixa central da operação (sem usuário específico)
- **Três níveis de caixa**:
  - Caixa da Operação (`consultor_id = NULL`): Recursos não alocados
  - Caixa do Gestor (`consultor_id = id_gestor`): Dinheiro sob responsabilidade do gestor
  - Caixa do Consultor (`consultor_id = id_consultor`): Dinheiro sob responsabilidade do consultor
- **Movimentações manuais**: Podem ser criadas para caixa da operação ou usuários específicos
- **Validações**: Descrição obrigatória com mínimo de 20 caracteres para movimentações do caixa da operação
- **Visualização**: Coluna "Responsável" na listagem mostra "Caixa da Operação" quando `consultor_id` é NULL
- **Métodos adicionais**: `calcularSaldoOperacao()` para calcular saldo apenas do caixa central
- **Documentação**: `docs/MODELO_CAIXA_OPERACAO.md` criada com explicação completa do modelo

### ✅ Modificado

#### CashService
- Métodos ajustados para lidar com `consultor_id` NULL
- `listarMovimentacoes()` agora aceita parâmetro `$apenasCaixaOperacao` para filtrar
- Cálculos de saldo consideram movimentações com `consultor_id` NULL

#### CashController
- Validação de `consultor_id` agora permite NULL
- Validação de descrição mais rigorosa para movimentações do caixa da operação
- Formulário permite seleção de "Caixa da Operação" ou usuário específico

#### Formulário de Movimentação Manual
- Campo "Usuário Responsável" agora permite selecionar "Caixa da Operação"
- Mensagens dinâmicas explicam o impacto da seleção
- Validação JavaScript atualizada para considerar caixa da operação

#### View de Listagem
- Coluna "Responsável" adicionada mostrando usuário ou "Caixa da Operação"
- Coluna "Comprovante" adicionada para visualização de comprovantes

### 🔧 Técnico

#### Migration
- `make_consultor_id_nullable_in_cash_ledger_entries_table`: Torna `consultor_id` nullable
- Remove e recria foreign key para permitir NULL
- Compatível com registros existentes

## [2026-01-20] - Movimentações Manuais de Caixa

### ✅ Adicionado

#### Sistema de Movimentações Manuais
- **Funcionalidade**: Gestores e Administradores podem criar movimentações manuais de caixa
- **Campos adicionados**: 
  - `origem` na tabela `cash_ledger_entries` (automatica/manual)
  - `comprovante_path` para armazenar comprovantes de movimentações manuais
- **Formulário completo**: Criação de entradas e saídas manuais com validações
- **Permissões**: Apenas gestores e administradores podem criar
- **Validações**: 
  - Acesso à operação
  - Consultor pertence à operação
  - Data não pode ser futura
  - Upload de comprovante opcional (PDF/imagem, máx. 2MB)
- **Visualização**: Badge diferenciado na listagem (Manual/Automática)
- **Métricas**: Cards de métricas na tela de movimentações
  - Saldo Atual
  - Total Entradas
  - Total Saídas
  - Diferença do Período
- **Documentação**: `docs/MOVIMENTACOES_MANUAIS.md` criada

#### Busca Global Inteligente
- **Funcionalidade**: Busca unificada no header do sistema
- **Detecção automática**: Identifica tipo de busca (CPF, ID de empréstimo, nome, etc.)
- **Busca em múltiplas entidades**: Clientes, Empréstimos, Operações, Usuários
- **Interface**: Dropdown com resultados em tempo real
- **Navegação por teclado**: Setas ↑↓ e Enter para navegar
- **Permissões**: Respeita filtros de operações do usuário

#### Melhorias no Formulário de Empréstimo
- **Select2 com AJAX**: Busca de clientes por CPF ou nome
- **Pré-seleção**: Botão "Criar Empréstimo" na página de detalhes do cliente
- **Autocomplete**: Busca em tempo real após 2 caracteres
- **Validação**: Cliente pré-selecionado quando vem da página de detalhes

#### Melhorias na Interface
- **Sidebar**: Tema padrão alterado para "brand"
- **Badge de liberações**: Cor alterada para vermelho (danger) para melhor legibilidade
- **Logo do header**: Redireciona para dashboard
- **Menu logout**: Texto alterado para "Deslogar"

## [2024-12-20] - Novas Funcionalidades e Correções

### ✅ Adicionado

#### Sistema de Liberação de Dinheiro
- **Tabela separada**: `emprestimo_liberacoes` para rastrear liberações
- **Fluxo completo**: Consultor cria → Aprova → Gestor libera → Consultor confirma pagamento
- **Movimentações automáticas**: Caixa atualizado automaticamente
  - **Saída** no caixa do gestor quando libera
  - **Entrada** no caixa do consultor quando recebe
- **Views**: Gestor vê liberações pendentes, Consultor vê suas liberações
- **Menus**: Adicionados no sidebar com badges de notificação
- **Comprovantes**: Sistema de upload de comprovantes
  - Gestor pode anexar comprovante ao liberar dinheiro
  - Consultor pode anexar comprovante ao confirmar pagamento ao cliente
  - Visualização e download de comprovantes nas listagens

#### Valor de Aprovação Automática por Operação
- **Campo editável**: Cada operação pode ter um `valor_aprovacao_automatica` configurado
- **Aprovação automática**: Empréstimos com valor ≤ este limite são aprovados automaticamente
- **Ignora validações**: Quando dentro do limite, ignora dívida ativa e limite de crédito
- **Apenas novos**: A configuração afeta apenas empréstimos criados após a edição
- **Views atualizadas**: Campo adicionado em create, edit e show de operações

### ✅ Corrigido

### ✅ Corrigido

#### Cálculo de Juros
- **Cálculo de Juros**: Corrigido o método `calcularValorParcela()` para aplicar automaticamente a taxa de juros no valor das parcelas
- **Views**: Atualizada a view `emprestimos/show.blade.php` para exibir informações detalhadas sobre juros

#### Geração Automática de Parcelas
- **Parcelas sempre geradas**: Agora as parcelas são geradas automaticamente na criação do empréstimo, independente do status (pendente ou aprovado)
- **Proteção contra duplicação**: Adicionada verificação para evitar gerar parcelas duplicadas

#### Soft Delete de Parcelas
- **Soft delete ao rejeitar**: Parcelas são soft deleted quando o empréstimo é rejeitado
- **Proteção**: Parcelas com pagamentos não são deletadas (integridade financeira)
- **Transação**: Processo de rejeição acontece em transação para garantir consistência

### ✅ Corrigido

#### Cálculo de Juros
- **Cálculo de Juros**: Corrigido o método `calcularValorParcela()` para aplicar automaticamente a taxa de juros no valor das parcelas
- **Views**: Atualizada a view `emprestimos/show.blade.php` para exibir informações detalhadas sobre juros

#### Geração Automática de Parcelas
- **Parcelas sempre geradas**: Agora as parcelas são geradas automaticamente na criação do empréstimo, independente do status (pendente ou aprovado)
- **Proteção contra duplicação**: Adicionada verificação para evitar gerar parcelas duplicadas

### 📝 Detalhes

**Juros:**
- Adicionados métodos `calcularValorTotalComJuros()` e `calcularValorJuros()` ao model `Emprestimo`
- Agora o valor da parcela é calculado com base no valor total + juros
- Exemplo: R$ 1.000,00 com 30% de juros = R$ 43,33 por parcela (30x) em vez de R$ 33,33

**Parcelas:**
- Parcelas são geradas sempre na criação, mesmo para empréstimos pendentes
- Método `aprovar()` verifica se parcelas já existem antes de gerar (compatibilidade com empréstimos antigos)
- Método `gerarParcelas()` verifica se já existem parcelas para evitar duplicação

## [Fase 1 - Em Desenvolvimento]

### Adicionado - 2024-12-XX

#### Estrutura Base
- ✅ Criação da estrutura de documentação (README, arquitetura, plano)
- ✅ Cópia da estrutura base do template Webadmin
- ✅ Criação da estrutura modular (Core, Loans, Cash, Approvals)
- ✅ Configuração do autoload do Composer para módulos

#### Documentação
- ✅ README.md com visão geral do projeto
- ✅ docs/arquitetura.md com detalhes da arquitetura
- ✅ docs/PLANO_IMPLEMENTACAO.md com sequência de implementação
- ✅ docs/CHANGELOG.md (este arquivo)

#### Migrations (15 tabelas) - 2024-12-20
- ✅ Core: operacoes, roles, permissions, role_user, permission_role, clientes, client_documents, operation_clients
- ✅ Loans: emprestimos, parcelas, pagamentos
- ✅ Cash: cash_ledger_entries, settlements
- ✅ Approvals: aprovacoes
- ✅ Auditoria: audit_logs

#### Models (13 models) - 2024-12-20
- ✅ Core: Operacao, Cliente, ClientDocument, OperationClient, Role, Permission, Auditoria
- ✅ Loans: Emprestimo, Parcela, Pagamento
- ✅ Cash: CashLedgerEntry, Settlement
- ✅ Approvals: Aprovacao
- ✅ User atualizado com suporte a múltiplos papéis

#### Services (6/9) - 2024-12-20
- ✅ ClienteService (cadastro, busca, vinculação, limite)
- ✅ OperacaoService (CRUD)
- ✅ EmprestimoService (criar, validar, aprovar, gerar parcelas)
- ✅ ParcelaService (cobranças do dia, marcar paga, atrasadas)
- ✅ PagamentoService (registrar, criar movimentação caixa)
- ✅ AprovacaoService (listar pendentes, aprovar/rejeitar)

#### Auditoria - 2024-12-20
- ✅ Trait Auditable
- ✅ Integração nos Services principais

#### Próximos Passos
- [ ] Completar Services restantes (CashService, SettlementService, PermissionService)
- [ ] Controllers e Rotas
- [ ] Views Blade
- [ ] Menus e Navegação
- [ ] Seeders
- [ ] Jobs e Scheduler
- [ ] Jobs e Scheduler

---

## Formato

Este changelog segue o formato [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

### Tipos de Mudanças

- **Adicionado**: Para novas funcionalidades
- **Modificado**: Para mudanças em funcionalidades existentes
- **Depreciado**: Para funcionalidades que serão removidas
- **Removido**: Para funcionalidades removidas
- **Corrigido**: Para correções de bugs
- **Segurança**: Para vulnerabilidades


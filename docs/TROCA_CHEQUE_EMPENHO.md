# Troca de Cheque e Empenho - Documentação de Features Futuras

## 📋 Visão Geral

Este documento descreve duas novas features que serão implementadas no sistema: **Troca de Cheque** e **Empenho**. Ambas são tipos de empréstimo diferentes do atual "Empréstimo em Dinheiro".

**Status:** Planejado para implementação futura  
**Permissões Iniciais:** Apenas Administrador (escalável via permissões no futuro)

---

## 🎯 1. Troca de Cheque

### Conceito

A **Troca de Cheque** é um tipo de empréstimo onde:
- Cliente **entrega um cheque** (que será depositado no futuro)
- Sistema **paga ao cliente um valor menor** (descontando os juros antecipadamente)
- Quando o cheque vencer, sistema deposita e recebe o valor total do cheque
- **Lucro = Juros descontados antecipadamente**

### Exemplo Prático

```
Cliente entrega cheque de R$ 1.000 (vencimento em 30 dias)
         ↓
Sistema calcula juros (10% = R$ 100)
         ↓
Sistema paga R$ 900 ao cliente (R$ 1.000 - R$ 100 de juros)
         ↓
Sistema guarda o cheque (físico ou foto)
         ↓
Quando o cheque vencer (30 dias), sistema deposita
         ↓
Sistema recebe R$ 1.000 (lucro de R$ 100 = juros)
```

### Diferenças do Empréstimo em Dinheiro

| Aspecto | Empréstimo em Dinheiro | Troca de Cheque |
|---------|------------------------|-----------------|
| Cliente recebe | Dinheiro completo | Dinheiro menor (desconta juros) |
| Cliente entrega | Nada (só promessa) | Cheque (garantia física) |
| Pagamento | Parcelas ao longo do tempo | Cheque único (depositado no vencimento) |
| Risco | Maior (sem garantia física) | Menor (tem o cheque) |
| Juros | Calculados sobre parcelas | Descontados antecipadamente |
| Fluxo de caixa | Saída inicial, entrada parcelada | Saída menor inicial, entrada única no vencimento |

### Fluxo Completo

#### 1. Criação do Empréstimo (Tipo: Troca de Cheque)
```
Consultor cria empréstimo → Seleciona tipo "Troca de Cheque" →
Preenche:
- Cliente
- Valor do cheque (ex: R$ 1.000)
- Data de vencimento do cheque
- Taxa de juros da operação (ex: 10%)
- Dados do cheque (banco, agência, conta, número)
→ Sistema calcula: Valor a pagar = R$ 1.000 - (R$ 1.000 × 10%) = R$ 900
```

#### 2. Aprovação
- Mesmas regras: dívida ativa, limite de crédito, aprovação automática
- Se aprovado, cria liberação (mas diferente do empréstimo em dinheiro)

#### 3. Liberação e Pagamento
```
Gestor libera → Consultor recebe R$ 900 (não R$ 1.000) →
Consultor paga R$ 900 ao cliente →
Sistema guarda o cheque (físico ou foto)
```

#### 4. Vencimento e Depósito
```
Cheque vence em 30 dias → Sistema alerta consultor/gestor →
Consultor deposita o cheque →
Sistema marca como "depositado" →
Quando compensar → Sistema marca como "compensado" →
Empréstimo finalizado automaticamente
```

### Estrutura de Dados

#### Tabela `emprestimos` (ajustes)
Adicionar campo `tipo`:
```php
'tipo' => 'enum:dinheiro,troca_cheque,empenho'
```

#### Nova Tabela `cheques`
```php
- id
- emprestimo_id (FK obrigatória - um empréstimo de troca de cheque tem UM cheque)
- operacao_id
- consultor_id
- cliente_id
- banco
- agencia
- conta
- numero_cheque
- valor_cheque (valor total do cheque, ex: R$ 1.000)
- valor_pago_cliente (valor que foi pago ao cliente, ex: R$ 900)
- valor_juros (juros descontados, ex: R$ 100)
- data_vencimento (data do cheque)
- data_recebimento (quando recebeu o cheque)
- data_deposito (nullable - quando depositou)
- data_compensacao (nullable - quando compensou)
- status (recebido, aguardando_vencimento, depositado, compensado, devolvido, cancelado)
- foto_cheque_path (nullable - foto do cheque)
- comprovante_deposito_path (nullable)
- observacoes
- created_at, updated_at, deleted_at
```

### Cálculo de Juros

**Fórmula:**
```
Valor do Cheque = R$ 1.000
Taxa de Juros = 10%
Juros = R$ 1.000 × 10% = R$ 100
Valor a Pagar ao Cliente = R$ 1.000 - R$ 100 = R$ 900
```

**Quando o cheque compensar:**
```
Sistema recebe: R$ 1.000
Sistema pagou: R$ 900
Lucro (juros): R$ 100
```

### Integração com Sistema Atual

#### 1. EmprestimoService
- Adicionar `tipo` ao criar empréstimo
- Se `tipo = 'troca_cheque'`:
  - Não gerar parcelas (ou gerar 1 parcela única no vencimento)
  - Criar registro na tabela `cheques`
  - Calcular valor a pagar (cheque - juros)

#### 2. LiberacaoService
- Se for troca de cheque, liberar valor menor (valor_cheque - juros)
- Não precisa de "confirmação de pagamento ao cliente" (já tem o cheque)

#### 3. Parcelas
- **Opção A:** Não gerar parcelas (empréstimo finaliza quando cheque compensar)
- **Opção B:** Gerar 1 parcela única no vencimento do cheque

#### 4. Pagamentos
- Não há pagamentos de parcelas (o cheque é a garantia)
- Quando o cheque compensar, empréstimo finaliza automaticamente

#### 5. Caixa
- **Saída:** Valor pago ao cliente (ex: R$ 900)
- **Entrada:** Valor do cheque quando compensar (ex: R$ 1.000)
- **Lucro:** Diferença (ex: R$ 100)

### Funcionalidades Específicas

1. **Criar Troca de Cheque**
   - Formulário similar ao empréstimo em dinheiro
   - Campos adicionais: dados do cheque, data de vencimento
   - Cálculo automático do valor a pagar

2. **Listar Cheques**
   - Filtros: status, data de vencimento, cliente, operação
   - Alertas: cheques vencendo em X dias, cheques vencidos não depositados

3. **Gerenciar Cheque**
   - Upload de foto do cheque
   - Marcar como depositado (com data e comprovante)
   - Marcar como compensado
   - Marcar como devolvido (com motivo)

4. **Dashboard**
   - Total de cheques pendentes
   - Cheques vencendo nos próximos dias
   - Cheques vencidos não depositados
   - Valor total em cheques por status

---

## 🚗 2. Empenho

### Conceito

O **Empenho** é um tipo de empréstimo onde:
- Cliente **recebe dinheiro** (igual ao empréstimo em dinheiro)
- Cliente **deixa um bem como garantia** (geralmente carro, mas pode ser outros bens)
- Cliente **paga parcelas normalmente**
- **Se pagar tudo → Cliente recebe o bem de volta**
- **Se não pagar → Sistema fica com o bem** (executa a garantia)

### Exemplo Prático

```
Cliente precisa de R$ 5.000
         ↓
Cliente oferece carro como garantia (valor R$ 20.000)
         ↓
Sistema avalia o bem e aprova o empréstimo
         ↓
Cliente deixa o carro (chaves, documentos, etc.)
         ↓
Sistema paga R$ 5.000 ao cliente
         ↓
Cliente paga parcelas normalmente
         ↓
SE PAGAR TUDO → Cliente recebe o carro de volta
SE NÃO PAGAR → Sistema fica com o carro (executa a garantia)
```

### Diferenças dos Outros Tipos

| Aspecto | Empréstimo em Dinheiro | Troca de Cheque | Empenho |
|---------|------------------------|-----------------|---------|
| Cliente recebe | Dinheiro completo | Dinheiro menor (desconta juros) | Dinheiro completo |
| Cliente entrega | Nada (só promessa) | Cheque | Bem físico (carro, etc.) |
| Garantia | Nenhuma | Cheque (garantia) | Bem físico (garantia) |
| Pagamento | Parcelas ao longo do tempo | Cheque único (no vencimento) | Parcelas ao longo do tempo |
| Risco | Maior | Médio | Menor (tem bem físico) |
| Se não pagar | Cobrança judicial | Deposita o cheque | Fica com o bem |

### Fluxo Completo

#### 1. Criação do Empréstimo (Tipo: Empenho)
```
Consultor cria empréstimo → Seleciona tipo "Empenho" →
Preenche:
- Cliente
- Valor do empréstimo (ex: R$ 5.000)
- Número de parcelas e frequência
- Taxa de juros
- DADOS DO BEM (tipo, descrição, valor estimado)
→ Sistema valida: valor do bem >= valor do empréstimo?
→ Se sim, pode aprovar automaticamente
```

#### 2. Registro da Garantia
```
Antes de liberar dinheiro → Registrar bem como garantia →
Preenche:
- Tipo (veículo, imóvel, eletrônico, outro)
- Descrição detalhada
- Valor estimado/avaliado
- Dados específicos (se carro: marca, modelo, ano, placa, etc.)
- Upload de fotos/documentos
- Localização do bem (onde está guardado)
```

#### 3. Aprovação e Liberação
- Validações: dívida ativa, limite de crédito, valor do bem vs. valor do empréstimo
- Se aprovado, cria liberação normalmente

#### 4. Pagamento de Parcelas
- Cliente paga parcelas normalmente (igual ao empréstimo em dinheiro)
- Sistema registra pagamentos normalmente

#### 5. Finalização (Dois Cenários)

**Cenário A: Cliente paga tudo**
```
Cliente quita todas as parcelas →
Sistema marca empréstimo como "finalizado" →
Sistema libera a garantia (cliente pega o bem de volta) →
Status da garantia: "liberada"
```

**Cenário B: Cliente não paga**
```
Cliente para de pagar (parcelas atrasadas) →
Sistema alerta gestor →
Gestor decide executar a garantia →
Sistema marca garantia como "executada" →
Sistema fica com o bem (vende, usa, etc.) →
Empréstimo finalizado (com perda)
```

### Estrutura de Dados

#### Tabela `emprestimos` (ajustes)
Adicionar campo `tipo`:
```php
'tipo' => 'enum:dinheiro,troca_cheque,empenho'
```

#### Nova Tabela `garantias`
```php
- id
- emprestimo_id (FK obrigatória - um empréstimo de empenho tem UMA garantia)
- operacao_id
- consultor_id
- cliente_id
- tipo (veiculo, imovel, eletronico, joia, outro)
- descricao
- marca (nullable - se veículo)
- modelo (nullable - se veículo)
- ano (nullable - se veículo)
- placa (nullable - se veículo)
- chassi (nullable - se veículo)
- valor_estimado (valor que o cliente diz que vale)
- valor_avaliado (valor que o sistema avaliou)
- localizacao (onde está guardado o bem)
- data_recebimento (quando recebeu o bem)
- data_liberacao (nullable - quando devolveu ao cliente)
- data_execucao (nullable - quando executou a garantia)
- status (ativa, liberada, executada, cancelada)
- observacoes
- created_at, updated_at, deleted_at
```

#### Nova Tabela `garantia_documentos` (Fotos e Documentos)
```php
- id
- garantia_id (FK)
- tipo (foto, documento, laudo, contrato)
- arquivo_path
- descricao
- created_at, updated_at
```

### Validações Específicas

#### 1. Valor do Bem vs. Valor do Empréstimo
```
Valor do Bem: R$ 20.000
Valor do Empréstimo: R$ 5.000
Margem de Segurança: 4x o valor (bom!)

Valor do Bem: R$ 3.000
Valor do Empréstimo: R$ 5.000
Margem de Segurança: 0.6x o valor (ruim! Risco alto)
```

**Regra sugerida:**
- Valor do bem deve ser >= valor do empréstimo × 2 (margem de segurança)
- Se não atender, pode exigir aprovação manual

#### 2. Tipo de Bem
- **Veículo:** Mais comum, fácil de avaliar e executar
- **Imóvel:** Mais complexo, precisa de documentação
- **Eletrônicos:** Menor valor, mais fácil de perder valor
- **Joias:** Precisa de avaliação especializada

#### 3. Documentação Obrigatória
- Fotos do bem (múltiplas)
- Documentos (se veículo: CRLV, chave, etc.)
- Contrato de empenho (assinado)
- Laudo de avaliação (opcional, mas recomendado)

### Integração com Sistema Atual

#### 1. EmprestimoService
- Adicionar `tipo` ao criar empréstimo
- Se `tipo = 'empenho'`:
  - Validar se garantia foi registrada
  - Validar valor do bem vs. valor do empréstimo
  - Gerar parcelas normalmente (igual ao empréstimo em dinheiro)

#### 2. LiberacaoService
- Se for empenho, validar se garantia está registrada antes de liberar
- Liberar dinheiro normalmente (igual ao empréstimo em dinheiro)

#### 3. Parcelas e Pagamentos
- Funciona igual ao empréstimo em dinheiro
- Cliente paga parcelas normalmente
- Sistema registra pagamentos normalmente

#### 4. Finalização do Empréstimo

**Se cliente pagar tudo:**
```
Todas as parcelas pagas →
Empréstimo finalizado →
Garantia liberada (cliente pega o bem) →
Status garantia: "liberada"
```

**Se cliente não pagar:**
```
Parcelas atrasadas (ex: > 90 dias) →
Sistema alerta gestor →
Gestor decide executar garantia →
Status garantia: "executada" →
Empréstimo finalizado (com perda) →
Sistema fica com o bem
```

#### 5. Impacto no Limite de Crédito
- Com garantia, pode aumentar o limite de crédito do cliente
- Exemplo: limite normal R$ 1.000, com garantia de R$ 10.000 → limite pode subir para R$ 5.000

### Funcionalidades Específicas

1. **Criar Empenho**
   - Formulário similar ao empréstimo em dinheiro
   - Seção adicional: "Registrar Garantia"
   - Campos: tipo de bem, descrição, valor, fotos, documentos
   - Validação automática: valor do bem >= 2x valor do empréstimo?

2. **Gerenciar Garantia**
   - Upload de fotos/documentos
   - Avaliar bem (valor estimado vs. valor avaliado)
   - Marcar como liberada (quando cliente paga tudo)
   - Marcar como executada (quando não paga)
   - Histórico de movimentações

3. **Alertas e Notificações**
   - Garantias com empréstimos atrasados
   - Garantias próximas de execução (ex: > 60 dias atrasado)
   - Garantias executadas (relatório)

4. **Dashboard**
   - Total de garantias ativas
   - Valor total em garantias
   - Garantias com risco (empréstimo atrasado)
   - Garantias executadas no período

---

## 📊 Comparação dos 3 Tipos de Empréstimo

| Aspecto | Empréstimo em Dinheiro | Troca de Cheque | Empenho |
|---------|------------------------|-----------------|---------|
| **Cliente recebe** | Dinheiro completo | Dinheiro menor (desconta juros) | Dinheiro completo |
| **Cliente entrega** | Nada (só promessa) | Cheque | Bem físico (carro, etc.) |
| **Garantia** | Nenhuma | Cheque (garantia) | Bem físico (garantia) |
| **Pagamento** | Parcelas ao longo do tempo | Cheque único (no vencimento) | Parcelas ao longo do tempo |
| **Risco** | Maior | Médio | Menor (tem bem físico) |
| **Se não pagar** | Cobrança judicial | Deposita o cheque | Fica com o bem |
| **Juros** | Calculados sobre parcelas | Descontados antecipadamente | Calculados sobre parcelas |
| **Fluxo de caixa** | Saída inicial, entrada parcelada | Saída menor inicial, entrada única | Saída inicial, entrada parcelada |

---

## 🔐 Permissões

### Inicial (Fase 1)
- **Apenas Administrador** pode criar/gerenciar troca de cheque e empenho
- Consultores e Gestores não têm acesso inicialmente

### Futuro (Escalável)
- Criar permissões específicas:
  - `emprestimos.criar.troca_cheque`
  - `emprestimos.criar.empenho`
  - `cheques.gerenciar`
  - `garantias.gerenciar`
  - `garantias.executar`
- Permitir atribuir essas permissões a Gestores e Consultores conforme necessário

---

## 📁 Estrutura de Módulos

### Módulo Cheques (Futuro)
```
app/Modules/Cheques/
├── Models/
│   └── Cheque.php
├── Services/
│   └── ChequeService.php
└── Controllers/
    └── ChequeController.php
```

### Módulo Garantias (Futuro)
```
app/Modules/Garantias/
├── Models/
│   ├── Garantia.php
│   └── GarantiaDocumento.php
├── Services/
│   └── GarantiaService.php
└── Controllers/
    └── GarantiaController.php
```

### Ajustes no Módulo Loans
```
app/Modules/Loans/
├── Models/
│   └── Emprestimo.php (adicionar campo 'tipo')
├── Services/
│   └── EmprestimoService.php (ajustar para suportar novos tipos)
└── Enums/
    └── TipoEmprestimo.php (novo enum)
```

---

## 🗄️ Migrations Necessárias

### 1. Adicionar campo `tipo` em `emprestimos`
```php
Schema::table('emprestimos', function (Blueprint $table) {
    $table->enum('tipo', ['dinheiro', 'troca_cheque', 'empenho'])
          ->default('dinheiro')
          ->after('status');
});
```

### 2. Criar tabela `cheques`
```php
Schema::create('cheques', function (Blueprint $table) {
    $table->id();
    $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
    $table->foreignId('operacao_id')->constrained('operacoes');
    $table->foreignId('consultor_id')->constrained('users');
    $table->foreignId('cliente_id')->constrained('clientes');
    $table->string('banco');
    $table->string('agencia');
    $table->string('conta');
    $table->string('numero_cheque');
    $table->decimal('valor_cheque', 10, 2);
    $table->decimal('valor_pago_cliente', 10, 2);
    $table->decimal('valor_juros', 10, 2);
    $table->date('data_vencimento');
    $table->date('data_recebimento');
    $table->date('data_deposito')->nullable();
    $table->date('data_compensacao')->nullable();
    $table->enum('status', [
        'recebido',
        'aguardando_vencimento',
        'depositado',
        'compensado',
        'devolvido',
        'cancelado'
    ])->default('recebido');
    $table->string('foto_cheque_path')->nullable();
    $table->string('comprovante_deposito_path')->nullable();
    $table->text('observacoes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 3. Criar tabela `garantias`
```php
Schema::create('garantias', function (Blueprint $table) {
    $table->id();
    $table->foreignId('emprestimo_id')->constrained('emprestimos')->onDelete('cascade');
    $table->foreignId('operacao_id')->constrained('operacoes');
    $table->foreignId('consultor_id')->constrained('users');
    $table->foreignId('cliente_id')->constrained('clientes');
    $table->enum('tipo', ['veiculo', 'imovel', 'eletronico', 'joia', 'outro']);
    $table->text('descricao');
    $table->string('marca')->nullable();
    $table->string('modelo')->nullable();
    $table->integer('ano')->nullable();
    $table->string('placa')->nullable();
    $table->string('chassi')->nullable();
    $table->decimal('valor_estimado', 10, 2);
    $table->decimal('valor_avaliado', 10, 2)->nullable();
    $table->string('localizacao');
    $table->date('data_recebimento');
    $table->date('data_liberacao')->nullable();
    $table->date('data_execucao')->nullable();
    $table->enum('status', ['ativa', 'liberada', 'executada', 'cancelada'])->default('ativa');
    $table->text('observacoes')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### 4. Criar tabela `garantia_documentos`
```php
Schema::create('garantia_documentos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('garantia_id')->constrained('garantias')->onDelete('cascade');
    $table->enum('tipo', ['foto', 'documento', 'laudo', 'contrato']);
    $table->string('arquivo_path');
    $table->string('descricao')->nullable();
    $table->timestamps();
});
```

---

## 🔄 Fluxos de Integração

### Troca de Cheque

```
1. Criar Empréstimo (tipo: troca_cheque)
   ↓
2. EmprestimoService valida e cria
   ↓
3. Criar registro em 'cheques' (vinculado ao empréstimo)
   ↓
4. Calcular valor a pagar (cheque - juros)
   ↓
5. Criar liberação (valor menor)
   ↓
6. Gestor libera → Consultor recebe valor menor
   ↓
7. Consultor paga cliente → Empréstimo fica ativo
   ↓
8. Cheque vence → Sistema alerta
   ↓
9. Consultor deposita → Marca como depositado
   ↓
10. Cheque compensa → Marca como compensado → Empréstimo finaliza
```

### Empenho

```
1. Criar Empréstimo (tipo: empenho)
   ↓
2. Registrar Garantia (antes de liberar)
   ↓
3. EmprestimoService valida (valor bem >= 2x empréstimo?)
   ↓
4. Criar registro em 'garantias' (vinculado ao empréstimo)
   ↓
5. Upload de fotos/documentos
   ↓
6. Criar liberação normalmente
   ↓
7. Gestor libera → Consultor recebe → Cliente recebe
   ↓
8. Cliente paga parcelas normalmente
   ↓
9. SE PAGAR TUDO → Libera garantia
   SE NÃO PAGAR → Executa garantia
```

---

## 📝 Notas de Implementação

### Prioridades
1. **Fase 1:** Preparar estrutura (enums, migrations, models básicos)
2. **Fase 2:** Implementar Troca de Cheque (mais simples)
3. **Fase 3:** Implementar Empenho (mais complexo, precisa de gestão de bens)

### Considerações
- Manter compatibilidade com empréstimos existentes (tipo 'dinheiro')
- Validar bem os cálculos de juros na troca de cheque
- Implementar alertas automáticos para cheques vencendo
- Implementar alertas para garantias com empréstimos atrasados
- Criar relatórios específicos para cada tipo de empréstimo

### Testes Necessários
- Criar troca de cheque e validar cálculos
- Criar empenho e validar validações de garantia
- Testar fluxo completo de cada tipo
- Testar permissões (apenas admin inicialmente)
- Testar integração com caixa e prestação de contas

---

## 📚 Referências

- Documentação atual do sistema: `docs/arquitetura.md`
- Fluxo de empréstimo: `docs/LIBERACAO_DINHEIRO.md`
- Fluxo de caixa: `docs/FLUXO_CAIXA_LIBERACAO.md`

---

**Última atualização:** 2025-01-20  
**Status:** Documentação para implementação futura

# Documentação: Renovação de Empréstimos

## Visão Geral

A **renovação de empréstimos** é um fluxo específico para empréstimos com **1 parcela única**, onde o cliente paga **apenas os juros do período** e renova o prazo do valor principal (valor emprestado). Funciona para qualquer frequência (diária, semanal ou mensal).

### Exemplo Prático (Mensal)

- **Empréstimo Original:**
  - Valor Principal: R$ 1.000,00
  - Taxa de Juros: 10% ao mês
  - Valor dos Juros: R$ 100,00
  - Parcela Única: R$ 1.100,00 (vencimento em 30 dias)

- **Na Renovação:**
  - Cliente paga apenas os **R$ 100,00 de juros**
  - O principal de **R$ 1.000,00** continua devendo
  - Sistema cria um **novo empréstimo** de R$ 1.000,00 com nova data de vencimento (+30 dias)

### Exemplos para Diferentes Frequências

#### Empréstimo Diário (1 parcela)
- **Valor:** R$ 1.000,00
- **Taxa:** 1% ao dia
- **Juros:** R$ 10,00
- **Vencimento:** 26/01/2026

**Renovação:**
- Cliente paga R$ 10,00 (juros)
- Novo empréstimo: R$ 1.000,00
- Novo vencimento: **27/01/2026** (+1 dia)

#### Empréstimo Semanal (1 parcela)
- **Valor:** R$ 1.000,00
- **Taxa:** 5% por semana
- **Juros:** R$ 50,00
- **Vencimento:** 26/01/2026

**Renovação:**
- Cliente paga R$ 50,00 (juros)
- Novo empréstimo: R$ 1.000,00
- Novo vencimento: **02/02/2026** (+7 dias)

#### Empréstimo Mensal (1 parcela)
- **Valor:** R$ 1.000,00
- **Taxa:** 10% ao mês
- **Juros:** R$ 100,00
- **Vencimento:** 26/01/2026

**Renovação:**
- Cliente paga R$ 100,00 (juros)
- Novo empréstimo: R$ 1.000,00
- Novo vencimento: **26/02/2026** (+30 dias)

---

## Regras de Negócio

### Condições para Renovação

Um empréstimo pode ser renovado **apenas** se atender **todas** as condições abaixo:

1. ✅ **Status do Empréstimo:** `ativo`
2. ✅ **Número de Parcelas:** `1` (uma única parcela)
3. ✅ **Parcela está atrasada:** A parcela deve estar com status `atrasada` ou com `data_vencimento < hoje`
4. ✅ **Frequência:** Qualquer (diária, semanal ou mensal)
5. ✅ **Juros não pagos:** O valor pago na parcela deve ser **menor** que o valor dos juros calculados

### Validações de Segurança

- ❌ **Bloqueia renovação** se os juros já foram pagos (evita duplicação)
- ❌ **Bloqueia renovação** se o empréstimo não está ativo
- ❌ **Bloqueia renovação** se não for 1 parcela única
- ❌ **Bloqueia renovação** se a parcela não está atrasada
- ✅ **Permite qualquer frequência** (diária, semanal ou mensal) desde que seja 1 parcela
- ✅ **Permite apenas** gestores e administradores realizarem a renovação

---

## Fluxo Técnico Detalhado

### 1. Validações Iniciais

Quando o usuário clica em "Renovar Empréstimo", o sistema executa as seguintes validações:

```php
// Verifica se empréstimo está ativo
if (!$emprestimo->isAtivo()) {
    throw ValidationException::withMessages([
        'emprestimo' => 'Apenas empréstimos ativos podem ser renovados.'
    ]);
}

// Verifica se é 1 parcela (qualquer frequência)
if ($emprestimo->numero_parcelas !== 1) {
    throw ValidationException::withMessages([
        'emprestimo' => 'Este fluxo de renovação é permitido apenas para empréstimos mensais com 1 parcela.'
    ]);
}

// Verifica se os juros já foram pagos
if ($emprestimo->jurosJaForamPagos()) {
    throw ValidationException::withMessages([
        'emprestimo' => 'Os juros deste empréstimo já foram pagos. Não é necessário renovar.'
    ]);
}
```

### 2. Pagamento Automático dos Juros

O sistema registra **automaticamente** o pagamento dos juros:

```php
// Calcula valor dos juros
$valorJuros = $emprestimo->calcularValorJuros(); // Ex: R$ 100,00

// Registra pagamento automaticamente
$pagamentoService->registrar([
    'parcela_id' => $parcela->id,
    'consultor_id' => auth()->id(),
    'valor' => $valorJuros, // R$ 100,00
    'metodo' => 'dinheiro',
    'data_pagamento' => Carbon::today(),
    'observacoes' => "Pagamento automático de juros na renovação do empréstimo #{$emprestimo->id}",
]);
```

**O que acontece internamente:**
- ✅ Cria registro em `pagamentos` com valor = juros
- ✅ Atualiza `valor_pago` da parcela (ex: de R$ 0,00 para R$ 100,00)
- ✅ Cria movimentação de caixa de **ENTRADA** para o consultor
- ✅ Atualiza status da parcela (se necessário)

### 3. Criação do Novo Empréstimo

O sistema cria um **novo registro** em `emprestimos`:

```php
$novoEmprestimo = Emprestimo::create([
    'operacao_id' => $emprestimo->operacao_id,        // Mesma operação
    'cliente_id' => $emprestimo->cliente_id,          // Mesmo cliente
    'consultor_id' => $emprestimo->consultor_id,      // Mesmo consultor
    'valor_total' => $emprestimo->valor_total,        // Mesmo principal (R$ 1.000,00)
    'numero_parcelas' => 1,                            // 1 parcela
    'frequencia' => $emprestimo->frequencia,          // Preserva frequência (diária, semanal ou mensal)
    'data_inicio' => Carbon::today(),                  // Data de hoje
    'taxa_juros' => $emprestimo->taxa_juros,          // Mesma taxa (10%)
    'tipo' => $emprestimo->tipo,                      // Preserva tipo (empenho, dinheiro, price)
    'status' => 'ativo',                               // Já ativo (não precisa aprovação)
    'observacoes' => $emprestimo->observacoes,        // Copia observações
    'emprestimo_origem_id' => $emprestimo->id,        // Link para empréstimo original
]);

// Se tinha garantias, copiar para o novo empréstimo
if ($emprestimo->garantias->isNotEmpty()) {
    foreach ($emprestimo->garantias as $garantiaOriginal) {
        $novaGarantia = EmprestimoGarantia::create([...]); // Copia garantia
        foreach ($garantiaOriginal->anexos as $anexo) {
            EmprestimoGarantiaAnexo::create([...]); // Copia anexos
        }
    }
}
```

**Características importantes:**
- ✅ **Não passa pelo fluxo de aprovação** (já nasce `ativo`)
- ✅ **Não gera nova liberação de dinheiro** (dinheiro já foi liberado no empréstimo original)
- ✅ **Mantém mesmo valor principal** (R$ 1.000,00)
- ✅ **Cria vínculo** com empréstimo original via `emprestimo_origem_id`
- ✅ **Preserva tipo do empréstimo** (se original era "empenho", novo também será)
- ✅ **Transfere garantias** (se original tinha garantias, são copiadas para o novo empréstimo)

### 4. Geração de Parcelas

O sistema gera automaticamente a nova parcela:

```php
// Gera 1 parcela mensal para o novo empréstimo
Parcela::create([
    'emprestimo_id' => $novoEmprestimo->id,
    'numero' => 1,
    'valor' => $novoEmprestimo->calcularValorParcela(), // R$ 1.100,00 (R$ 1.000 + 10%)
    'valor_pago' => 0,
    'data_vencimento' => Carbon::today()->addMonth(),  // +30 dias
    'status' => 'pendente',
]);
```

### 5. Encerramento do Empréstimo Original

O empréstimo antigo é marcado como finalizado:

```php
$emprestimo->update([
    'status' => 'finalizado',
]);
```

**Importante:**
- ✅ **Não apaga nada** - mantém histórico completo
- ✅ **Parcela antiga permanece** com valor parcialmente pago (R$ 100,00 de R$ 1.100,00)
- ✅ **Histórico completo** fica disponível para consulta

### 6. Auditoria

O sistema registra a ação na auditoria:

```php
self::auditar(
    'renovar_emprestimo',
    $novoEmprestimo,
    null,
    [
        'novo_emprestimo_id' => $novoEmprestimo->id,
        'emprestimo_origem_id' => $emprestimo->id,
        'status_origem_anterior' => 'ativo',
        'status_origem_atual' => 'finalizado',
        'valor_juros_pago' => 100.00,
        'pagamento_automatico' => true,
    ],
    "Empréstimo #10 renovado para o empréstimo #11 - Pagamento de juros (R$ 100,00) registrado automaticamente"
);
```

---

## Estrutura de Dados

### Tabela `emprestimos`

**Campo adicionado para renovação:**

```sql
emprestimo_origem_id (unsignedBigInteger, nullable, FK -> emprestimos.id)
```

- **Quando preenchido:** Indica que este empréstimo é uma **renovação** de outro
- **Quando NULL:** Indica que este é um empréstimo **original** (não é renovação)

### Relacionamentos no Modelo

```php
// Empréstimo de origem (quando este é uma renovação)
public function emprestimoOrigem()
{
    return $this->belongsTo(self::class, 'emprestimo_origem_id');
}

// Renovações geradas a partir deste empréstimo
public function renovacoes()
{
    return $this->hasMany(self::class, 'emprestimo_origem_id');
}

// Verificar se é renovação
public function isRenovacao(): bool
{
    return !is_null($this->emprestimo_origem_id);
}
```

### Histórico de Renovações

O método `getHistoricoRenovacoes()` retorna a **cadeia completa** de renovações:

```php
// Exemplo: Empréstimo #15 (renovação de #14, que é renovação de #13)
$historico = $emprestimo->getHistoricoRenovacoes();
// Retorna: [Empréstimo #13, Empréstimo #14, Empréstimo #15]
```

---

## Interface do Usuário

### Tela de Detalhes do Empréstimo

Na tela de detalhes (`emprestimos/show.blade.php`), o sistema exibe:

#### 1. Badges de Renovação

**Se o empréstimo é uma renovação:**
```blade
<span class="badge bg-info">
    Renovação do empréstimo 
    <a href="{{ route('emprestimos.show', $emprestimo->emprestimoOrigem->id) }}">
        #{{ $emprestimo->emprestimoOrigem->id }}
    </a>
</span>
```

**Se o empréstimo já foi renovado:**
```blade
<span class="badge bg-secondary">
    Renovado para o empréstimo 
    <a href="{{ route('emprestimos.show', $ultimaRenovacao->id) }}">
        #{{ $ultimaRenovacao->id }}
    </a>
</span>
```

#### 2. Bloco de Renovação

**Quando pode renovar:**
- Exibe alerta informativo sobre o processo
- Mostra botão "Renovar Empréstimo (Pagar Só Juros)"
- Abre modal de confirmação com:
  - Resumo dos valores
  - Valor dos juros a serem pagos
  - Checkbox de confirmação obrigatória

**Quando juros já foram pagos:**
- Exibe alerta de aviso
- **Não mostra** botão de renovação
- Informa que os juros já foram pagos

### Modal de Confirmação

O modal exibe:
- ✅ Valor Principal: R$ 1.000,00
- ✅ Juros do Período: R$ 100,00
- ✅ Valor Total da Parcela: R$ 1.100,00
- ✅ Nova Data de Início: Hoje
- ✅ Informação de que o pagamento será automático
- ✅ Lista de passos da renovação

---

## Relatório de Renovações

### Lista de Renovações (`renovacoes/index`)

- Mostra todas as renovações agrupadas por cliente
- Filtros disponíveis:
  - Por operação
  - Por cliente (ID)
- Exibe:
  - Número do empréstimo
  - Valor principal
  - Data de início
  - Status
  - Quantidade de renovações

### Histórico por Cliente (`renovacoes/show-cliente`)

- Mostra **cadeia completa** de renovações de um cliente
- Exibe em formato de tabela:
  - Empréstimo original (badge "Original")
  - Renovações sequenciais (badge "Renovação #1", "#2", etc.)
  - Valores e datas de cada renovação
  - Totais de juros pagos

---

## Exemplo Completo de Fluxo

### Cenário: Cliente João - Empréstimo Mensal

**1. Empréstimo Original (#10) - Criado em 01/01/2024**
- Valor: R$ 1.000,00
- Taxa: 10% ao mês
- Parcela: R$ 1.100,00 (vencimento 31/01/2024)
- Status: `ativo`

**2. Vencimento (31/01/2024) - Cliente quer renovar**

**3. Gestor acessa empréstimo #10:**
- Sistema mostra: "Renovação de Empréstimo disponível" (apenas se a parcela estiver atrasada)
- Botão: "Renovar Empréstimo (Pagar Só Juros)"
- Valor dos juros: R$ 100,00

**4. Gestor clica em renovar:**
- Sistema registra pagamento de R$ 100,00 automaticamente
- Cria empréstimo #11:
  - Valor: R$ 1.000,00 (mesmo principal)
  - Parcela: R$ 1.100,00 (vencimento 29/02/2024)
  - Status: `ativo`
  - `emprestimo_origem_id = 10`
- Empréstimo #10 → Status: `finalizado`

**5. Empréstimo #11 - Tela de Detalhes:**
- Badge: "Renovação do empréstimo #10"
- Link para ver empréstimo original

**6. Empréstimo #10 - Tela de Detalhes:**
- Badge: "Renovado para o empréstimo #11"
- Link para ver renovação
- Parcela mostra: R$ 100,00 pago de R$ 1.100,00

**7. Histórico Completo:**
- Relatório de renovações mostra cadeia: #10 → #11
- Total de juros pagos: R$ 100,00

---

## Validações e Segurança

### Validação de Juros Pagos

O método `jurosJaForamPagos()` verifica:

```php
public function jurosJaForamPagos(): bool
{
    if ($this->frequencia !== 'mensal' || $this->numero_parcelas !== 1) {
        return false;
    }

    $parcela = $this->parcelas->first();
    if (!$parcela) {
        return false;
    }

    $valorJuros = $this->calcularValorJuros();
    return $parcela->valor_pago >= $valorJuros;
}
```

**Lógica:**
- Compara `valor_pago` da parcela com `valor_juros` calculado
- Se `valor_pago >= valor_juros` → Juros já foram pagos → **Bloqueia renovação**

### Permissões

- ✅ **Administradores** podem renovar qualquer empréstimo
- ✅ **Gestores** podem renovar qualquer empréstimo
- ✅ **Consultores** podem renovar **apenas seus próprios empréstimos** (onde são o consultor responsável)

---

## Renovação de Empréstimos com Garantia (Tipo Empenho)

### Comportamento Especial

Quando um empréstimo do tipo **"empenho"** (com garantias) é renovado:

1. **Tipo preservado:**
   - O novo empréstimo também será tipo "empenho"
   - Campo `tipo` é copiado do empréstimo original

2. **Garantias transferidas:**
   - Todas as garantias do empréstimo original são **copiadas** para o novo empréstimo
   - Os anexos (fotos, documentos) também são copiados
   - Os arquivos físicos permanecem os mesmos (bem continua o mesmo)
   - Observação é adicionada indicando que foi transferida da renovação

3. **Histórico preservado:**
   - Garantias originais permanecem no empréstimo original (finalizado)
   - Garantias também aparecem no novo empréstimo (ativo)
   - Permite rastreabilidade completa

### Exemplo: Renovação com Garantia

**Empréstimo #10 (tipo empenho):**
- Valor: R$ 1.000,00
- Garantia: Veículo avaliado em R$ 5.000,00
- Anexos: 3 fotos do veículo, 1 documento

**Renovação:**
- Cliente paga R$ 100,00 (juros)
- Sistema cria empréstimo #11:
  - Tipo: **"empenho"** (preservado)
  - Garantia: Veículo avaliado em R$ 5.000,00 (copiada)
  - Anexos: 3 fotos + 1 documento (copiados)
  - Status: `ativo`

**Resultado:**
- ✅ Bem continua empenhado e vinculado ao novo empréstimo
- ✅ Documentação completa disponível no novo empréstimo
- ✅ Histórico preservado no empréstimo original

## Pontos de Atenção

### ⚠️ Importante

1. **Não gera nova liberação:**
   - O novo empréstimo já nasce `ativo`
   - Não passa pelo fluxo de aprovação/liberação
   - O dinheiro já foi liberado no empréstimo original

2. **Pagamento automático:**
   - O pagamento dos juros é registrado **automaticamente**
   - Não precisa registrar manualmente
   - Gera movimentação de caixa automaticamente

3. **Histórico preservado:**
   - Empréstimo antigo não é deletado
   - Parcela antiga mantém histórico de pagamento parcial
   - Cadeia completa de renovações fica disponível

4. **Validação de juros:**
   - Sistema impede renovação se juros já foram pagos
   - Evita duplicação de pagamentos
   - Protege integridade financeira

---

## Arquivos Relacionados

### Backend

- `app/Modules/Loans/Models/Emprestimo.php` - Modelo com relacionamentos e métodos
- `app/Modules/Loans/Services/EmprestimoService.php` - Lógica de renovação
- `app/Modules/Loans/Controllers/EmprestimoController.php` - Controller com método `renovar()`
- `app/Modules/Loans/Controllers/RenovacaoController.php` - Relatórios de renovações
- `app/Modules/Loans/Services/PagamentoService.php` - Registro automático de pagamento

### Frontend

- `resources/views/emprestimos/show.blade.php` - Tela de detalhes com botão de renovação
- `resources/views/renovacoes/index.blade.php` - Lista de renovações
- `resources/views/renovacoes/show-cliente.blade.php` - Histórico por cliente

### Banco de Dados

- `database/migrations/2026_01_20_210000_add_emprestimo_origem_to_emprestimos_table.php` - Migração do campo `emprestimo_origem_id`

### Rotas

- `POST /emprestimos/{id}/renovar` - Renovar empréstimo
- `GET /renovacoes` - Lista de renovações
- `GET /renovacoes/cliente/{id}` - Histórico por cliente

---

## Conclusão

A renovação de empréstimos é um fluxo automatizado que permite ao cliente **rolar a dívida** pagando apenas os juros mensais, mantendo o principal em aberto. O sistema garante:

- ✅ **Rastreabilidade completa** (histórico de renovações)
- ✅ **Segurança financeira** (validações e auditoria)
- ✅ **Automação** (pagamento automático de juros)
- ✅ **Transparência** (relatórios e visualização de cadeias)

Este fluxo é especialmente útil para clientes que precisam de **flexibilidade** no pagamento, permitindo que paguem apenas os juros enquanto mantêm o principal em aberto.

# Prestação de Contas - Fluxo Completo

## Visão Geral

A prestação de contas é um processo onde o consultor presta contas ao gestor sobre o dinheiro recebido em um período específico. Após aprovação, o consultor anexa comprovante de envio do dinheiro, e o gestor confirma o recebimento, gerando automaticamente as movimentações de caixa.

## Fluxo Completo (4 Etapas)

### 1. Criação (Consultor)

**O que acontece:**
- Consultor acessa "Prestações de Contas" → "Nova Prestação"
- Preenche:
  - Operação
  - Data Início
  - Data Fim
  - Observações (opcional)

**O que o sistema faz:**
- Calcula automaticamente o valor total somando todas as **entradas** do consultor na operação no período
- Cria registro na tabela `settlements` com status `pendente`
- Registra na auditoria

**Status:** `pendente`

---

### 2. Aprovação (Gestor ou Administrador)

**O que acontece:**
- Gestor ou Administrador visualiza a prestação pendente
- Revisa o valor calculado
- Aprova ou rejeita

**O que o sistema faz:**
- Se **aprovado**: Status muda para `aprovado`
- Se **rejeitado**: Status muda para `rejeitado` + motivo da rejeição
- Registra quem aprovou/rejeitou e quando

**Status:** `aprovado` ou `rejeitado`

**Validações:**
- Apenas prestações com status `pendente` podem ser aprovadas/rejeitadas
- Apenas gestores e administradores podem aprovar/rejeitar

---

### 3. Anexar Comprovante (Consultor)

**O que acontece:**
- Consultor visualiza sua prestação aprovada
- Anexa comprovante de envio do dinheiro (transferência, depósito, etc.)
- Sistema armazena o comprovante

**O que o sistema faz:**
- Status muda para `enviado`
- Armazena `comprovante_path`
- Registra `enviado_em`
- **IMPORTANTE:** Movimentações de caixa ainda NÃO são geradas

**Status:** `enviado`

**Validações:**
- Apenas prestações com status `aprovado` podem ter comprovante anexado
- Apenas o consultor dono da prestação pode anexar comprovante
- Comprovante é obrigatório (PDF ou imagem, máx. 2MB)

---

### 4. Confirmar Recebimento (Gestor ou Administrador)

**O que acontece:**
- Gestor ou Administrador visualiza a prestação com comprovante anexado
- Revisa o comprovante
- Confirma que recebeu o dinheiro

**O que o sistema faz:**
- Status muda para `concluido`
- Registra `recebido_por` e `recebido_em`
- **GERA MOVIMENTAÇÕES DE CAIXA AUTOMATICAMENTE:**
  - **Saída** do caixa do consultor
  - **Entrada** no caixa do gestor que confirmou

**Status:** `concluido`

**Validações:**
- Apenas prestações com status `enviado` podem ter recebimento confirmado
- Comprovante deve estar anexado
- Apenas gestores e administradores podem confirmar recebimento

---

## Movimentações de Caixa Geradas

Quando o gestor confirma recebimento (Etapa 4), o sistema cria **duas movimentações** automaticamente:

### Movimentação 1: Saída do Caixa do Consultor

```
Tipo: SAÍDA
Consultor: id_do_consultor
Operação: id_da_operacao
Valor: valor_total_da_prestacao
Descrição: "Prestação de contas - Período 01/01/2026 a 31/01/2026"
Referência: settlement, settlement_id
Origem: automatica
Comprovante: caminho_do_comprovante_anexado
```

### Movimentação 2: Entrada no Caixa do Gestor

```
Tipo: ENTRADA
Consultor: id_do_gestor (que confirmou recebimento)
Operação: id_da_operacao
Valor: valor_total_da_prestacao
Descrição: "Recebimento de prestação de contas - Consultor Maria - Período 01/01/2026 a 31/01/2026"
Referência: settlement, settlement_id
Origem: automatica
Comprovante: NULL (gestor não precisa anexar)
```

---

## Status Possíveis

| Status | Descrição | Próxima Ação |
|--------|-----------|--------------|
| `pendente` | Criada pelo consultor, aguardando aprovação | Gestor/Admin aprova ou rejeita |
| `aprovado` | Aprovada, aguardando comprovante | Consultor anexa comprovante |
| `enviado` | Comprovante anexado, aguardando confirmação de recebimento | Gestor/Admin confirma recebimento |
| `concluido` | Processo completo, movimentações geradas | - |
| `rejeitado` | Rejeitada pelo gestor/admin | - |

---

## Exemplo Prático

**Cenário:**
- Consultor: Maria
- Gestor: João
- Operação: Operação Principal
- Período: 01/01/2026 a 31/01/2026
- Valor total calculado: R$ 2.000,00

**Fluxo:**

1. **Maria cria prestação**
   - Status: `pendente`
   - Valor: R$ 2.000,00

2. **João aprova**
   - Status: `aprovado`
   - `conferido_por`: João
   - `conferido_em`: 01/02/2026 10:00

3. **Maria anexa comprovante**
   - Anexa comprovante de transferência
   - Status: `enviado`
   - `comprovante_path`: `comprovantes/prestacoes/settlement_123.pdf`
   - `enviado_em`: 01/02/2026 14:30
   - **Movimentações:** Ainda não criadas

4. **João confirma recebimento**
   - Status: `concluido`
   - `recebido_por`: João
   - `recebido_em`: 01/02/2026 15:00
   - **Movimentações criadas automaticamente:**
     - Saída do caixa de Maria: -R$ 2.000,00
     - Entrada no caixa de João: +R$ 2.000,00

---

## Estrutura de Dados

### Tabela: `settlements`

**Campos principais:**
- `id`: ID da prestação
- `operacao_id`: Operação
- `consultor_id`: Consultor que prestou contas
- `data_inicio` / `data_fim`: Período
- `valor_total`: Valor calculado automaticamente
- `status`: Status atual (`pendente`, `aprovado`, `enviado`, `concluido`, `rejeitado`)
- `comprovante_path`: Caminho do comprovante anexado
- `enviado_em`: Quando o consultor anexou o comprovante
- `recebido_por`: ID do gestor que confirmou recebimento
- `recebido_em`: Quando o gestor confirmou recebimento
- `conferido_por`: ID de quem aprovou (gestor/admin)
- `conferido_em`: Quando foi aprovado
- `motivo_rejeicao`: Motivo (se rejeitada)
- `observacoes`: Observações gerais

---

## Permissões

### Consultor
- ✅ Criar prestações de contas
- ✅ Anexar comprovante nas suas próprias prestações
- ✅ Ver apenas suas próprias prestações

### Gestor
- ✅ Ver prestações dos consultores de suas operações
- ✅ Aprovar/rejeitar prestações pendentes
- ✅ Confirmar recebimento de prestações com comprovante

### Administrador
- ✅ Ver todas as prestações
- ✅ Aprovar/rejeitar prestações pendentes
- ✅ Confirmar recebimento de prestações com comprovante

---

## Validações Importantes

### Antes de Aprovar
- Prestação deve estar `pendente`
- Apenas gestor ou administrador pode aprovar

### Antes de Anexar Comprovante
- Prestação deve estar `aprovado`
- Apenas o consultor dono pode anexar
- Comprovante é obrigatório

### Antes de Confirmar Recebimento
- Prestação deve estar `enviado`
- Comprovante deve estar anexado
- Apenas gestor ou administrador pode confirmar

### Ao Confirmar Recebimento
- Movimentações são criadas em **transação** (tudo ou nada)
- Ambas as movimentações são vinculadas ao settlement via `referencia_tipo` e `referencia_id`
- Comprovante é copiado para a movimentação de saída do consultor

---

## Vantagens do Modelo

1. **Segurança**: Movimentações só são geradas após confirmação do gestor
2. **Rastreabilidade**: Sabe exatamente quando foi enviado e recebido
3. **Comprovante**: Sempre disponível para auditoria
4. **Automático**: Movimentações geradas automaticamente, sem necessidade de criar manualmente
5. **Controle**: Status controlam o fluxo, não permite ações fora de ordem

---

## Fluxo Visual

```
┌─────────────────────────────────────────────────────────────┐
│ 1. CONSULTOR CRIA PRESTAÇÃO                                 │
│    Status: PENDENTE                                         │
│    Sistema calcula valor automaticamente                    │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. GESTOR/ADMIN APROVA                                       │
│    Status: APROVADO                                         │
│    Agora o consultor precisa anexar comprovante             │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. CONSULTOR ANEXA COMPROVANTE                              │
│    Status: ENVIADO                                          │
│    ⚠️ MOVIMENTAÇÕES AINDA NÃO GERADAS                      │
└─────────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. GESTOR/ADMIN CONFIRMA RECEBIMENTO                         │
│    Status: CONCLUÍDO                                        │
│    ✅ AGORA SIM: Gera movimentações                        │
│    • Saída do consultor: -R$ 2.000,00                      │
│    • Entrada do gestor: +R$ 2.000,00                        │
└─────────────────────────────────────────────────────────────┘
```

---

## Casos Especiais

### E se o gestor não confirmar recebimento?
- Prestação fica em `enviado` indefinidamente
- Movimentações não são geradas
- Consultor pode solicitar revisão ou cancelar

### E se o gestor rejeitar o comprovante?
- Status volta para `aprovado` (ou pode criar novo status `comprovante_rejeitado`)
- Consultor pode anexar novo comprovante
- Movimentações não são geradas

### E se o valor não bater?
- Gestor pode rejeitar e solicitar ajuste
- Ou confirmar parcialmente (se implementado no futuro)

### E se o consultor não tiver saldo suficiente?
- Sistema pode bloquear confirmação de recebimento
- Ou permitir saldo negativo com alerta
- Depende da regra de negócio definida

---

## Rotas

- `GET /prestacoes` - Listar prestações
- `GET /prestacoes/create` - Formulário de criação
- `POST /prestacoes` - Criar prestação
- `POST /prestacoes/{id}/aprovar` - Aprovar prestação
- `POST /prestacoes/{id}/rejeitar` - Rejeitar prestação
- `POST /prestacoes/{id}/anexar-comprovante` - Anexar comprovante
- `POST /prestacoes/{id}/confirmar-recebimento` - Confirmar recebimento (gera movimentações)

---

## Migrations Necessárias

```bash
php artisan migrate
```

A migration `add_settlement_fields_for_payment_confirmation` adiciona:
- `comprovante_path`
- `enviado_em`
- `recebido_por`
- `recebido_em`
- Ajusta enum de status para incluir `enviado` e `concluido`

---

## Troubleshooting

### Problema: Não consigo anexar comprovante
**Solução**: Verifique se a prestação está com status `aprovado` e se você é o consultor dono da prestação.

### Problema: Não consigo confirmar recebimento
**Solução**: 
- Verifique se a prestação está com status `enviado`
- Verifique se há comprovante anexado
- Verifique se você tem papel de gestor ou administrador

### Problema: Movimentações não foram geradas
**Solução**: 
- Verifique se o status está `concluido`
- Verifique os logs de erro
- Verifique se há movimentações vinculadas ao settlement via `referencia_tipo = 'settlement'`

---

## Melhorias Futuras (Sugestões)

1. **Notificações**: Notificar gestor quando consultor anexa comprovante
2. **Validação de saldo**: Verificar se consultor tem saldo suficiente antes de confirmar
3. **Pagamento parcial**: Permitir prestações parciais
4. **Histórico de comprovantes**: Permitir múltiplos comprovantes
5. **Exportação**: Exportar relatório de prestações de contas
6. **Dashboard**: Métricas de prestações pendentes/aprovadas/concluídas

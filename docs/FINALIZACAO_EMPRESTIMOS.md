# Finalização Automática de Empréstimos

## Visão Geral

O sistema possui uma funcionalidade de **finalização automática** de empréstimos. Quando todas as parcelas de um empréstimo são pagas, o status é automaticamente alterado de `ativo` para `finalizado`.

## Ciclo de Vida do Empréstimo

```
┌─────────┐     ┌──────────┐     ┌──────────┐     ┌────────┐     ┌─────────────┐
│  draft  │ ──► │ pendente │ ──► │ aprovado │ ──► │ ativo  │ ──► │ finalizado  │
└─────────┘     └──────────┘     └──────────┘     └────────┘     └─────────────┘
                                                       │
                                                       ▼
                                                 ┌───────────┐
                                                 │ cancelado │
                                                 └───────────┘
```

### Status

| Status | Descrição |
|--------|-----------|
| `draft` | Rascunho, ainda não enviado para aprovação |
| `pendente` | Aguardando aprovação do gestor/administrador |
| `aprovado` | Aprovado, aguardando liberação de dinheiro |
| `ativo` | Dinheiro liberado, empréstimo em andamento |
| `finalizado` | Todas as parcelas pagas ou renovado |
| `cancelado` | Empréstimo cancelado |

## Finalização Automática

### Quando acontece?

O empréstimo é finalizado automaticamente quando:

1. ✅ Todas as parcelas têm status `paga` OU `quitada_garantia`
2. ✅ Para parcelas `paga`: o valor pago é >= valor da parcela
3. ✅ Para parcelas `quitada_garantia`: consideradas quitadas mesmo com valor_pago = 0

### Como funciona?

Após cada pagamento registrado, o sistema:

1. Registra o pagamento
2. Atualiza o `valor_pago` da parcela
3. Se `valor_pago >= valor`, muda status da parcela para `paga`
4. Verifica se **todas** as parcelas estão pagas
5. Se sim, muda status do empréstimo para `finalizado`
6. Registra auditoria da finalização

### Pagamentos Parciais

Uma parcela pode receber pagamentos parciais:

| Pagamento | valor_pago | valor | Status Parcela | Status Empréstimo |
|-----------|------------|-------|----------------|-------------------|
| Nenhum | R$ 0,00 | R$ 100,00 | `pendente` | `ativo` |
| Parcial | R$ 50,00 | R$ 100,00 | `pendente` | `ativo` |
| Completo | R$ 100,00 | R$ 100,00 | `paga` | `ativo` ou `finalizado`* |

*O empréstimo só é finalizado quando **todas** as parcelas estão pagas.

### Código Responsável

**Arquivo:** `app/Modules/Loans/Services/PagamentoService.php`

```php
private function verificarFinalizacaoEmprestimo($emprestimo): void
{
    $emprestimo->load('parcelas');
    
    $totalParcelas = $emprestimo->parcelas->count();
    // Considerar parcelas pagas OU quitadas por garantia como quitadas
    $parcelasQuitadas = $emprestimo->parcelas->filter(function ($parcela) {
        return $parcela->status === 'paga' || $parcela->status === 'quitada_garantia';
    })->count();
    
    if ($totalParcelas > 0 && $parcelasQuitadas === $totalParcelas) {
        $emprestimo->update(['status' => 'finalizado']);
        
        // Registra auditoria
        self::auditar(
            'finalizar_emprestimo',
            $emprestimo,
            ['status' => $statusAnterior],
            ['status' => 'finalizado'],
            "Empréstimo finalizado automaticamente - Todas as parcelas foram quitadas"
        );
    }
}
```

## Comando Artisan

Para finalizar empréstimos que já deveriam estar finalizados (dados antigos), use o comando:

### Sintaxe

```bash
php artisan emprestimos:finalizar-quitados [opções]
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--dry-run` | Simula a execução sem fazer alterações |
| `--empresa-id=X` | Filtra por empresa específica |

### Exemplos de Uso

#### 1. Simular (recomendado rodar primeiro)

```bash
php artisan emprestimos:finalizar-quitados --dry-run
```

Mostra quais empréstimos seriam finalizados, sem alterar o banco.

#### 2. Executar de verdade

```bash
php artisan emprestimos:finalizar-quitados
```

Finaliza os empréstimos e registra auditoria.

#### 3. Filtrar por empresa

```bash
php artisan emprestimos:finalizar-quitados --empresa-id=1
```

Processa apenas empréstimos da empresa especificada.

### Exemplo de Saída

```
===========================================
  FINALIZAR EMPRÉSTIMOS QUITADOS
===========================================

📊 Total de empréstimos ativos encontrados: 5

✅ Empréstimo #123
   Cliente: João Silva
   Operação: Principal
   Valor: R$ 3.000,00
   Parcelas: 10/10 pagas
   ➡️  Status alterado para: FINALIZADO

===========================================
  RESUMO
===========================================

📊 Empréstimos ativos analisados: 5
✅ Empréstimos finalizados: 1
⏳ Empréstimos ainda pendentes: 4

+-----+-------------+-------------+----------+
| ID  | Cliente     | Valor       | Parcelas |
+-----+-------------+-------------+----------+
| 123 | João Silva  | R$ 3.000,00 | 10/10    |
+-----+-------------+-------------+----------+
```

## Outras Formas de Finalização

### 1. Renovação de Empréstimo

Quando um empréstimo é renovado:
- O empréstimo original é marcado como `finalizado`
- Um novo empréstimo é criado com status `ativo`
- Referência: `emprestimo_origem_id` no novo empréstimo

### 2. Manual (futuro)

Atualmente não há interface para finalização manual. Se necessário, pode ser implementado.

## Auditoria

Toda finalização é registrada na tabela de auditoria com:

- **Ação:** `finalizar_emprestimo`
- **Dados anteriores:** `{ status: 'ativo' }`
- **Dados novos:** `{ status: 'finalizado' }`
- **Observação:** Motivo da finalização

## Métodos Auxiliares no Model

**Arquivo:** `app/Modules/Loans/Models/Emprestimo.php`

```php
// Verificar se está finalizado
$emprestimo->isFinalizado(); // true ou false

// Verificar se todas as parcelas estão pagas
$emprestimo->todasParcelasPagas(); // true ou false

// Verificar se está cancelado
$emprestimo->isCancelado(); // true ou false
```

## Troubleshooting

### Empréstimo não foi finalizado automaticamente

1. Verifique se todas as parcelas têm status `paga`
2. Verifique se `valor_pago >= valor` em cada parcela
3. Execute o comando para corrigir:
   ```bash
   php artisan emprestimos:finalizar-quitados
   ```

### Parcela paga mas status ainda é "pendente"

Pode acontecer se o pagamento foi registrado antes da implementação desta feature. Verifique:

```sql
SELECT id, numero, valor, valor_pago, status 
FROM parcelas 
WHERE emprestimo_id = X;
```

Se `valor_pago >= valor` mas status é `pendente`, atualize manualmente:

```sql
UPDATE parcelas SET status = 'paga' WHERE valor_pago >= valor AND status = 'pendente';
```

Depois rode o comando artisan para finalizar os empréstimos.

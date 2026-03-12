# Soft Delete de Parcelas ao Rejeitar Empréstimo

## ✅ Implementação

### Funcionalidade

Quando um empréstimo é **rejeitado**, as parcelas associadas são **soft deleted** (marcadas como deletadas, mas mantidas no banco).

### Comportamento

1. **Empréstimo rejeitado** → Status muda para 'cancelado'
2. **Parcelas deletadas** → Soft delete aplicado automaticamente
3. **Proteção**: Parcelas com pagamentos **NÃO** são deletadas

## 🔧 Implementação Técnica

### Método `rejeitar()` Atualizado

```php
public function rejeitar(int $emprestimoId, int $aprovadorId, string $motivoRejeicao): Emprestimo
{
    return DB::transaction(function () use ($emprestimoId, $aprovadorId, $motivoRejeicao) {
        // ... validações ...
        
        // Fazer soft delete das parcelas (apenas se não houver pagamentos)
        $parcelas = $emprestimo->parcelas;
        foreach ($parcelas as $parcela) {
            // Verificar se a parcela já tem pagamentos
            if ($parcela->pagamentos()->count() === 0 && $parcela->valor_pago == 0) {
                // Apenas deletar se não houver pagamentos registrados
                $parcela->delete();
            }
        }
        
        // Atualizar status do empréstimo
        $emprestimo->update([
            'status' => 'cancelado',
            // ...
        ]);
    });
}
```

## 🛡️ Proteções Implementadas

### 1. Verificação de Pagamentos

As parcelas **NÃO** são deletadas se:
- ✅ Tiverem pagamentos registrados (`pagamentos()->count() > 0`)
- ✅ Tiverem valor pago (`valor_pago > 0`)

### 2. Transação

Todo o processo acontece dentro de uma **transação**:
- Se algo falhar, tudo é revertido
- Garante consistência dos dados

### 3. Soft Delete

- Parcelas não são **removidas** do banco
- Apenas marcadas como deletadas (`deleted_at`)
- Podem ser restauradas se necessário

## 📊 Comportamento nas Consultas

### Consultas Normais

```php
// Retorna apenas parcelas não deletadas
$emprestimo->parcelas; // Não inclui parcelas deletadas

// Para incluir deletadas
$emprestimo->parcelas()->withTrashed()->get();
```

### Relacionamento Padrão

O relacionamento `parcelas()` no model `Emprestimo` **automaticamente** exclui parcelas deletadas (comportamento padrão do Eloquent com SoftDeletes).

## 🔍 Casos de Uso

### Caso 1: Empréstimo Rejeitado (Sem Pagamentos)

```
Empréstimo criado → Parcelas geradas → Empréstimo rejeitado
Resultado: Parcelas soft deleted ✅
```

### Caso 2: Empréstimo Rejeitado (Com Pagamento Parcial)

```
Empréstimo criado → Parcelas geradas → Pagamento parcial → Empréstimo rejeitado
Resultado: Parcelas NÃO deletadas (proteção) ✅
```

### Caso 3: Restaurar Empréstimo

```php
// Restaurar empréstimo cancelado
$emprestimo->restore();

// Restaurar parcelas deletadas
$emprestimo->parcelas()->onlyTrashed()->restore();
```

## 📝 Benefícios

1. **Histórico Preservado**: Parcelas não são perdidas, apenas marcadas como deletadas
2. **Auditoria**: Possível rastrear o que foi deletado e quando
3. **Recuperação**: Parcelas podem ser restauradas se necessário
4. **Segurança**: Parcelas com pagamentos são protegidas
5. **Performance**: Consultas normais não incluem parcelas deletadas

## ⚠️ Observações Importantes

### 1. Relacionamento com Pagamentos

Se uma parcela tiver pagamentos, ela **NÃO** será deletada, mesmo que o empréstimo seja rejeitado. Isso garante integridade dos dados financeiros.

### 2. Consultas de Cobrança

As consultas de "Cobranças do Dia" automaticamente excluem parcelas deletadas, pois o relacionamento padrão já faz isso.

### 3. Limpeza de Dados

Para limpar permanentemente parcelas deletadas há muito tempo:

```php
// Deletar permanentemente parcelas deletadas há mais de 1 ano
Parcela::onlyTrashed()
    ->where('deleted_at', '<', now()->subYear())
    ->forceDelete();
```

## 🧪 Como Testar

1. **Criar empréstimo** com parcelas
2. **Verificar** que parcelas foram geradas
3. **Rejeitar** o empréstimo
4. **Verificar** que parcelas não aparecem mais nas consultas normais
5. **Verificar** que parcelas ainda existem no banco (com `deleted_at` preenchido)

## 📚 Referências

- [Laravel Soft Deletes](https://laravel.com/docs/11.x/eloquent#soft-deleting)
- [Eloquent Relationships with Soft Deletes](https://laravel.com/docs/11.x/eloquent-relationships#querying-relationship-existence)

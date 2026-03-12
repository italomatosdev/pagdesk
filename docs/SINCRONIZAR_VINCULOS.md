# Sincronizar Vínculos de Clientes com Operações

## 📋 Descrição

Este comando sincroniza os vínculos entre clientes e operações baseado nos empréstimos existentes. Ele é útil para criar vínculos retroativamente para clientes que já têm empréstimos mas não têm vínculos criados.

## 🎯 Quando Usar

- Após implementar a funcionalidade de criação automática de vínculos
- Para corrigir dados existentes que não têm vínculos
- Para garantir que todos os clientes com empréstimos tenham vínculos correspondentes

## 🚀 Como Usar

### Modo Normal (Executa as alterações)

```bash
php artisan clientes:sincronizar-vinculos
```

### Modo Dry-Run (Apenas visualiza, não executa)

```bash
php artisan clientes:sincronizar-vinculos --dry-run
```

## 📊 O que o Comando Faz

1. **Busca todas as combinações únicas** de `cliente_id + operacao_id` dos empréstimos existentes
2. **Para cada combinação:**
   - Verifica se já existe um vínculo
   - Se não existir, cria um novo vínculo com:
     - `limite_credito`: 0 (sem limite definido)
     - `status`: 'ativo'
     - `consultor_id`: consultor do empréstimo (se houver)
   - Se existir mas não tiver `consultor_id`, atualiza com o consultor do empréstimo

## 📈 Exemplo de Saída

```
📊 Encontradas 15 combinações únicas de cliente + operação.

[████████████████████████████████] 100%

✅ Sincronização concluída!

+---------------------------+------------+
| Ação                       | Quantidade |
+---------------------------+------------+
| Novos vínculos criados     | 12         |
| Vínculos atualizados       | 2          |
| Vínculos já existentes     | 1          |
| Total processado           | 15         |
+---------------------------+------------+
```

## ⚠️ Importante

- O comando é **idempotente**: pode ser executado múltiplas vezes sem causar problemas
- Use `--dry-run` primeiro para ver o que será feito antes de executar
- Os vínculos criados terão `limite_credito = 0` (sem limite). Você pode ajustar manualmente depois se necessário

## 🔍 Verificar Resultados

Após executar o comando, você pode verificar os vínculos criados:

1. Acesse a página de detalhes de um cliente que tinha empréstimo
2. A seção "Vínculos com Operações" deve mostrar os vínculos criados

## 📝 Notas Técnicas

- O comando agrupa empréstimos por `cliente_id` e `operacao_id`
- Se houver múltiplos empréstimos com consultores diferentes, usa o último consultor encontrado
- Vínculos já existentes não são alterados (exceto para adicionar `consultor_id` se estiver vazio)

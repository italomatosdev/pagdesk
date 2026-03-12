# Como Zerar o Banco e Reiniciar os Dados

## ⚠️ ATENÇÃO

**Isso vai APAGAR TODOS os dados do banco de dados!**

Use apenas em ambiente de desenvolvimento. **NUNCA** faça isso em produção sem backup.

## 🗑️ Opção 1: Recriar Banco Completo (Recomendado)

### Passo a Passo

```bash
# 1. Apagar todas as tabelas e recriar
php artisan migrate:fresh

# 2. Executar seeders (popular com dados iniciais)
php artisan db:seed
```

**O que faz:**
- ✅ Apaga todas as tabelas
- ✅ Recria todas as tabelas (migrations)
- ✅ Popula com dados iniciais (seeders)

## 🗑️ Opção 2: Recriar Banco + Seeders em um comando

```bash
php artisan migrate:fresh --seed
```

**O que faz:**
- ✅ Apaga todas as tabelas
- ✅ Recria todas as tabelas
- ✅ Executa seeders automaticamente

## 🗑️ Opção 3: Resetar e Recriar (Mais Seguro)

```bash
# 1. Reverter todas as migrations
php artisan migrate:reset

# 2. Executar migrations novamente
php artisan migrate

# 3. Executar seeders
php artisan db:seed
```

## 📋 O que será criado pelos Seeders

### RoleSeeder
- Papéis: Administrador, Gestor, Consultor, Cliente

### PermissionSeeder
- 18 permissões
- Atribuições de permissões aos papéis

### UserSeeder
- **Admin**: `admin@sistema-cred.com` / `12345678`
- **Gestor**: `gestor@sistema-cred.com` / `12345678`
- **Consultor**: `consultor@sistema-cred.com` / `12345678`

### OperacaoSeeder
- 2 operações de exemplo

## 🔄 Comandos Úteis

### Ver status das migrations
```bash
php artisan migrate:status
```

### Ver quais migrations serão executadas
```bash
php artisan migrate --pretend
```

### Limpar cache antes de resetar
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## ⚠️ Cuidados

> **Este documento é para DESENVOLVIMENTO apenas.**
> Em produção, veja [INFRAESTRUTURA.md - Política de Migrations](./INFRAESTRUTURA.md#104-política-de-migrations).

### Em Produção

- **NUNCA** use `migrate:fresh` em produção
- **NUNCA** use `migrate:rollback` sem análise manual
- Migrations são **forward-only**
- Sempre faça backup antes de qualquer alteração
- Correções devem ser feitas com **novas migrations**

### Em Desenvolvimento

- Pode usar `migrate:fresh --seed` sem problemas
- Pode usar `migrate:rollback` livremente
- Dados serão perdidos, mas seeders recriam tudo

## 📝 Exemplo Completo

```bash
# Limpar caches
php artisan cache:clear
php artisan config:clear

# Zerar e recriar banco
php artisan migrate:fresh --seed

# Verificar se funcionou
php artisan migrate:status
```

## ✅ Após Zerar

1. **Verificar usuários criados:**
   - Acesse: `http://localhost:8000`
   - Login: `admin@sistema-cred.com` / `12345678`

2. **Verificar operações:**
   - Menu: Operações
   - Deve ter 2 operações de exemplo

3. **Testar fluxo:**
   - Criar cliente
   - Criar empréstimo
   - Ver liberações

## 🔍 Troubleshooting

### Erro: "Table already exists"
```bash
# Forçar recriação
php artisan migrate:fresh --seed
```

### Erro: "Foreign key constraint"
```bash
# Desabilitar verificação de foreign keys temporariamente
# (apenas se necessário, o migrate:fresh já faz isso)
```

### Dados não aparecem
```bash
# Verificar se seeders foram executados
php artisan db:seed --class=UserSeeder
```

## 📚 Referências

- [Laravel Migrations](https://laravel.com/docs/11.x/migrations)
- [Laravel Seeders](https://laravel.com/docs/11.x/seeding)

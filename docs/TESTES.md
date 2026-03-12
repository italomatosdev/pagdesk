# Testes Automatizados

## ⚠️ IMPORTANTE: Banco separado

Os testes usam **RefreshDatabase**, que **apaga e recria** todas as tabelas. O `phpunit.xml` está configurado para usar **sempre** o banco `cred_test`. **Nunca** rode testes apontando para o banco principal (`cred` ou produção).

**Antes de rodar os testes pela primeira vez**, crie o banco de testes:

```bash
# No MySQL (ou mariaDB)
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS cred_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Ou no phpMyAdmin / cliente gráfico: crie um banco chamado `cred_test`.

Usuário e senha do banco são os mesmos do `.env` (DB_USERNAME, DB_PASSWORD, DB_HOST). Só o nome do banco muda para `cred_test` quando os testes rodam.

## Executar todos os testes

```bash
php artisan test
```

## Executar por suíte

```bash
# Apenas Unit
php artisan test tests/Unit

# Apenas Feature
php artisan test tests/Feature

# Um arquivo
php artisan test tests/Unit/Services/ClienteServiceTest.php
```

## Banco de dados em testes

O `phpunit.xml` já define `DB_DATABASE=cred_test`. Assim, ao rodar `php artisan test`, o Laravel usa **só** o banco `cred_test`; o banco do `.env` (ex.: `cred`) **não é alterado**.

- **RefreshDatabase:** as migrations rodam e as tabelas são recriadas **apenas** em `cred_test`.
- Se o banco `cred_test` não existir, os testes vão falhar com erro de conexão — crie-o com o comando da seção acima.

## Testes implementados

### Unit – Serviços

| Arquivo | Descrição |
|---------|-----------|
| `ClienteServiceTest` | Cadastro de cliente (CPF válido, empresa do usuário, CPF duplicado, CPF inválido) |
| `PagamentoServiceTest` | Validação: não registra pagamento quando empréstimo não está ativo |
| `ParcelaServiceTest` | `cobrancasDoDia()` retorna coleção |

### Feature – Acesso

| Arquivo | Descrição |
|---------|-----------|
| `ClienteCreationTest` | Visitante não acessa criar cliente; consultor acessa |
| `PagamentoAccessTest` | Visitante não acessa registrar pagamento; consultor acessa |

## Estrutura de suporte

- **`tests/Concerns/SetupEmpresaOperacaoUser.php`** – Trait que cria empresa, operação e usuário consultor para testes que precisam de usuário autenticado e escopo de empresa/operação.

## Adicionar novos testes

1. **Unit (regra de negócio):** criar em `tests/Unit/Services/NomeServiceTest.php`, usar `RefreshDatabase` e o trait `SetupEmpresaOperacaoUser` quando precisar de usuário/empresa/operação.
2. **Feature (HTTP/acesso):** criar em `tests/Feature/NomeTest.php`, usar `RefreshDatabase` e `actingAs($user)` para simular login.

# Rotas do Sistema

## 📋 Visão Geral

Este documento lista todas as rotas do sistema de crédito e cobrança.

## 🔐 Autenticação

Todas as rotas abaixo (exceto as de autenticação) requerem autenticação via middleware `auth`.

## 📍 Rotas Principais

### Clientes

| Método | URI | Nome | Controller | Descrição |
|--------|-----|------|------------|-----------|
| GET | `/clientes` | `clientes.index` | ClienteController@index | Listar clientes |
| GET | `/clientes/create` | `clientes.create` | ClienteController@create | Formulário de cadastro |
| POST | `/clientes` | `clientes.store` | ClienteController@store | Cadastrar cliente |
| GET | `/clientes/{id}` | `clientes.show` | ClienteController@show | Detalhes do cliente |
| GET | `/clientes/{id}/edit` | `clientes.edit` | ClienteController@edit | Formulário de edição |
| PUT | `/clientes/{id}` | `clientes.update` | ClienteController@update | Atualizar cliente |
| GET | `/clientes/buscar/cpf` | `clientes.buscar.cpf` | ClienteController@buscarPorCpf | Buscar por CPF (AJAX) |

### Empréstimos

| Método | URI | Nome | Controller | Descrição |
|--------|-----|------|------------|-----------|
| GET | `/emprestimos` | `emprestimos.index` | EmprestimoController@index | Listar empréstimos |
| GET | `/emprestimos/create` | `emprestimos.create` | EmprestimoController@create | Formulário de criação |
| POST | `/emprestimos` | `emprestimos.store` | EmprestimoController@store | Criar empréstimo |
| GET | `/emprestimos/{id}` | `emprestimos.show` | EmprestimoController@show | Detalhes do empréstimo |

### Cobranças do Dia

| Método | URI | Nome | Controller | Descrição |
|--------|-----|------|------------|-----------|
| GET | `/cobrancas` | `cobrancas.index` | ParcelaController@cobrancasDoDia | Listar cobranças do dia e atrasadas |

### Pagamentos

| Método | URI | Nome | Controller | Descrição |
|--------|-----|------|------------|-----------|
| GET | `/pagamentos/create` | `pagamentos.create` | PagamentoController@create | Formulário de registro |
| POST | `/pagamentos` | `pagamentos.store` | PagamentoController@store | Registrar pagamento |

### Aprovações

**Acesso restrito a Administradores**

| Método | URI | Nome | Controller | Descrição |
|--------|-----|------|------------|-----------|
| GET | `/aprovacoes` | `aprovacoes.index` | AprovacaoController@index | Listar empréstimos pendentes |
| POST | `/aprovacoes/{emprestimoId}/aprovar` | `aprovacoes.aprovar` | AprovacaoController@aprovar | Aprovar empréstimo |
| POST | `/aprovacoes/{emprestimoId}/rejeitar` | `aprovacoes.rejeitar` | AprovacaoController@rejeitar | Rejeitar empréstimo |

## 🔍 Filtros e Parâmetros

### Clientes
- `?cpf=` - Filtrar por CPF
- `?nome=` - Filtrar por nome

### Empréstimos
- `?operacao_id=` - Filtrar por operação
- `?status=` - Filtrar por status (draft, pendente, aprovado, ativo, finalizado, cancelado)
- `?cliente_id=` - Filtrar por cliente

### Cobranças
- `?operacao_id=` - Filtrar por operação

### Aprovações
- `?operacao_id=` - Filtrar por operação

## 📝 Notas

- Todas as rotas de criação/edição requerem autenticação
- Rotas de aprovação requerem papel de Administrador
- Consultores veem apenas suas próprias cobranças
- A rota catch-all `/{any}` deve ficar por último para não interceptar outras rotas

## 🚀 Exemplos de Uso

### Listar clientes
```
GET /clientes
```

### Criar empréstimo
```
POST /emprestimos
{
    "operacao_id": 1,
    "cliente_id": 1,
    "valor_total": 1000.00,
    "numero_parcelas": 10,
    "frequencia": "mensal",
    "data_inicio": "2024-01-01"
}
```

### Registrar pagamento
```
POST /pagamentos
{
    "parcela_id": 1,
    "valor": 100.00,
    "metodo": "dinheiro",
    "data_pagamento": "2024-01-15"
}
```

### Aprovar empréstimo
```
POST /aprovacoes/1/aprovar
{
    "motivo": "Cliente com bom histórico"
}
```

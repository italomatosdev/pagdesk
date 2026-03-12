# Quick Reference: Dados e Documentos do Cliente por Empresa

## 📋 Resumo Rápido

Sistema que permite múltiplas empresas compartilharem o cadastro de um cliente, cada uma mantendo seus próprios dados e documentos.

## 🎯 Conceitos Principais

### Dados do Cliente
- **Originais**: Armazenados em `clientes` (empresa criadora)
- **Override**: Armazenados em `cliente_dados_empresa` (outras empresas)
- **Prioridade**: Override > Original

### Documentos
- **Originais**: `empresa_id = null` (empresa criadora)
- **Específicos**: `empresa_id` preenchido (outras empresas)
- **Prioridade**: Específico > Original

## 🔄 Fluxo de Dados

```
Empresa A (Criadora)
├── Dados: Tabela `clientes` (originais)
└── Documentos: `empresa_id = null` (originais)

Empresa B (Usa cadastro)
├── Dados: Tabela `cliente_dados_empresa` (override)
└── Documentos: `empresa_id = B` (específicos)
```

## 💻 Uso no Código

### Verificar se é criadora
```php
$cliente->isEmpresaCriadora(); // true/false
```

### Obter dados (com override automático)
```php
$cliente->nome; // Retorna override se existir, senão original
$cliente->telefone; // Retorna override se existir, senão original
```

### Obter documento (prioriza específico)
```php
$documento = $cliente->getDocumentoPorCategoria('documento');
// Retorna específico da empresa se existir, senão original
```

### Verificar tipo de documento
```php
$documento->isDocumentoOriginal(); // true se empresa_id = null
$documento->isDocumentoEmpresa(); // true se empresa_id preenchido
```

## 📊 Tabelas

| Tabela | Propósito |
|--------|-----------|
| `clientes` | Dados originais (empresa criadora) |
| `cliente_dados_empresa` | Dados específicos por empresa (override) |
| `client_documents` | Documentos (originais e específicos) |

## 🎨 Visualização

### Empresa Criadora
- ✅ Vê dados originais
- ✅ Vê documentos originais
- ✅ Pode editar tudo
- ❌ Não vê dados/documentos de outras empresas

### Outras Empresas
- ✅ Vê dados originais + override próprio
- ✅ Vê documentos originais + específicos próprios
- ✅ Pode criar/editar override próprio
- ✅ Pode adicionar documentos próprios
- ❌ Não vê dados/documentos de outras empresas

## ⚠️ Regras Importantes

1. **Dados globais**: `tipo_pessoa` e `documento` não podem ser sobrescritos
2. **Empresa criadora**: Sempre usa dados originais (não cria override)
3. **Prioridade**: Override/específico sempre tem prioridade sobre original
4. **Isolamento**: Empresas não veem dados/documentos de outras empresas

## 🔍 Troubleshooting Rápido

| Problema | Solução |
|----------|---------|
| Dados não aparecem | Verificar se `dadosEmpresa` está carregado |
| Documento original aparece | Usar `getDocumentoPorCategoria()` |
| Erro "No query results" | Usar `withoutGlobalScope()` ao buscar |

## 📚 Documentação Completa

Para mais detalhes, consulte: `docs/DADOS_CLIENTE_POR_EMPRESA.md`

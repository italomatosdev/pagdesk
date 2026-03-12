# Sistema de Dados e Documentos do Cliente por Empresa

## Visão Geral

Este sistema permite que múltiplas empresas compartilhem o cadastro de um mesmo cliente, mas cada empresa pode ter suas próprias versões dos dados e documentos do cliente. Isso é útil quando uma empresa precisa usar um cliente que já foi cadastrado por outra empresa, mas quer manter seus próprios dados específicos.

## Estrutura de Dados

### Tabela `cliente_dados_empresa`

Esta tabela armazena dados específicos de um cliente para cada empresa que não é a criadora original do cliente.

**Campos:**
- `id`: ID único do registro
- `cliente_id`: ID do cliente (foreign key)
- `empresa_id`: ID da empresa (foreign key)
- `nome`: Nome do cliente (override)
- `telefone`: Telefone (override)
- `email`: Email (override)
- `data_nascimento`: Data de nascimento (override, para pessoa física)
- `responsavel_nome`: Nome do responsável legal (override, para pessoa jurídica)
- `responsavel_cpf`: CPF do responsável (override)
- `responsavel_rg`: RG do responsável (override)
- `responsavel_cnh`: CNH do responsável (override)
- `responsavel_cargo`: Cargo do responsável (override)
- `endereco`: Endereço (override)
- `numero`: Número do endereço (override)
- `cidade`: Cidade (override)
- `estado`: Estado (override)
- `cep`: CEP (override)
- `observacoes`: Observações (override)
- `created_at`: Data de criação
- `updated_at`: Data de atualização
- `deleted_at`: Data de exclusão (soft delete)

**Constraint único:** `cliente_id` + `empresa_id` (um cliente só pode ter um registro de override por empresa)

### Tabela `client_documents`

Esta tabela foi estendida para suportar documentos por empresa.

**Campo adicionado:**
- `empresa_id`: ID da empresa (nullable, foreign key)
  - `null` = documento original (da empresa criadora)
  - Preenchido = documento específico da empresa

## Comportamento do Sistema

### Dados do Cliente

#### Empresa Criadora (Empresa que cadastrou o cliente)
- **Vê e edita:** Apenas os dados originais na tabela `clientes`
- **Não vê:** Dados de override de outras empresas
- **Ao editar:** Atualiza diretamente a tabela `clientes`

#### Outras Empresas (Empresas que usam o cadastro)
- **Vê:** Dados originais + seus próprios dados de override (se existirem)
- **Ao editar:** 
  - Se já existe override: atualiza o registro em `cliente_dados_empresa`
  - Se não existe: cria novo registro em `cliente_dados_empresa`
- **Prioridade:** Dados de override têm prioridade sobre dados originais

### Documentos do Cliente

#### Empresa Criadora
- **Vê:** Apenas documentos originais (`empresa_id = null`)
- **Pode:** Adicionar, editar e excluir documentos originais
- **Não vê:** Documentos específicos de outras empresas

#### Outras Empresas
- **Vê:** Documentos originais (read-only) + seus próprios documentos específicos
- **Pode:** 
  - Adicionar seus próprios documentos (salvos com `empresa_id` da empresa)
  - Editar/excluir apenas seus próprios documentos
- **Prioridade:** Documentos específicos da empresa têm prioridade sobre originais

## Modelos e Métodos

### Modelo `Cliente`

#### Relacionamentos

```php
// Relacionamento: Dados específicos por empresa
public function dadosEmpresa()
{
    return $this->hasMany(ClienteDadosEmpresa::class, 'cliente_id');
}

// Relacionamento: Documentos (filtrado por empresa)
public function documentos()
{
    // Retorna documentos baseado na empresa atual:
    // - Empresa criadora: apenas originais
    // - Outras empresas: originais + específicos da empresa
}
```

#### Métodos Auxiliares

```php
// Verificar se a empresa atual é a criadora do cliente
public function isEmpresaCriadora(?int $empresaId = null): bool

// Obter dados específicos de uma empresa
public function dadosPorEmpresa(?int $empresaId = null): ?ClienteDadosEmpresa

// Obter documento por categoria, priorizando documentos específicos da empresa
public function getDocumentoPorCategoria(string $categoria): ?ClientDocument
```

#### Accessors

Todos os campos que podem ser sobrescritos têm accessors que verificam se existe override:

- `getNomeAttribute($value)`
- `getTelefoneAttribute($value)`
- `getEmailAttribute($value)`
- `getDataNascimentoAttribute($value)`
- `getResponsavelNomeAttribute($value)`
- `getResponsavelCpfAttribute($value)`
- `getResponsavelRgAttribute($value)`
- `getResponsavelCnhAttribute($value)`
- `getResponsavelCargoAttribute($value)`
- `getEnderecoAttribute($value)`
- `getNumeroAttribute($value)`
- `getCidadeAttribute($value)`
- `getEstadoAttribute($value)`
- `getCepAttribute($value)`
- `getObservacoesAttribute($value)`

**Lógica dos Accessors:**
1. Verifica se a empresa atual é a criadora
2. Se for criadora, retorna o valor original
3. Se não for, busca override em `cliente_dados_empresa`
4. Se encontrar override, retorna o valor do override
5. Se não encontrar, retorna o valor original

### Modelo `ClienteDadosEmpresa`

```php
// Relacionamentos
public function cliente()
public function empresa()

// Campos fillable incluem todos os campos que podem ser sobrescritos
```

### Modelo `ClientDocument`

#### Campos Adicionados

```php
'empresa_id' // null = documento original, preenchido = documento específico
```

#### Métodos Auxiliares

```php
// Verificar se é documento original
public function isDocumentoOriginal(): bool

// Verificar se é documento específico de uma empresa
public function isDocumentoEmpresa(): bool
```

## Serviços

### `ClienteService`

#### Método `atualizar()`

Atualiza dados do cliente. Verifica se a empresa atual é a criadora:

- **Se for criadora:** Atualiza diretamente na tabela `clientes`
- **Se não for:** Chama `atualizarDadosEmpresa()` para criar/atualizar override

```php
public function atualizar(int $clienteId, array $dados): Cliente
```

#### Método `atualizarDadosEmpresa()`

Cria ou atualiza dados específicos de uma empresa:

```php
public function atualizarDadosEmpresa(int $clienteId, int $empresaId, array $dados): Cliente
```

Usa `firstOrNew()` para buscar ou criar registro de override.

#### Método `processarDocumentosAtualizacao()`

Processa uploads de documentos considerando a empresa:

- **Empresa criadora:** Salva documentos com `empresa_id = null` (originais)
- **Outras empresas:** Salva documentos com `empresa_id = empresa_atual` (específicos)

```php
protected function processarDocumentosAtualizacao(int $clienteId, array $documentos): void
```

## Controllers

### `ClienteController`

#### Método `show()`

Carrega cliente e seus dados/documentos:

1. Busca cliente (com ou sem escopo, dependendo da empresa)
2. Carrega `dadosEmpresa` se cliente não pertence à empresa atual
3. Carrega documentos filtrados por empresa
4. Filtra histórico (vínculos, empréstimos) apenas da empresa atual

#### Método `edit()`

Carrega cliente para edição:

1. Busca cliente sem escopo (pode ser de outra empresa)
2. Carrega `dadosEmpresa` se necessário
3. Passa flag `isEmpresaCriadora` para a view

#### Método `update()`

Atualiza cliente:

1. Valida dados
2. Verifica se empresa atual é criadora
3. Chama `atualizar()` ou `atualizarDadosEmpresa()` conforme necessário

## Views

### `clientes/show.blade.php`

Exibe dados do cliente usando accessors (que automaticamente retornam override se existir).

**Documentos:**
- Usa `getDocumentoPorCategoria()` para obter documento correto
- Exibe badges indicando se é "Documento original" ou "Documento específico desta empresa"

### `clientes/edit.blade.php`

Formulário de edição:

- Exibe alerta se cliente não pertence à empresa atual
- Informa que alterações serão salvas apenas para a empresa atual
- Usa `getDocumentoPorCategoria()` para exibir documentos corretos

## Fluxo de Uso

### Cenário 1: Empresa A cadastra cliente

1. Empresa A cria cliente → Dados salvos em `clientes` (originais)
2. Empresa A anexa documentos → Documentos salvos com `empresa_id = null` (originais)

### Cenário 2: Empresa B usa cliente da Empresa A

1. Empresa B busca cliente por CPF/CNPJ → Encontra cliente da Empresa A
2. Empresa B clica "Usar cadastro" → Redireciona para `clientes.show`
3. Empresa B vê:
   - Dados originais da Empresa A
   - Documentos originais da Empresa A
4. Empresa B edita dados → Cria registro em `cliente_dados_empresa` com `empresa_id = B`
5. Empresa B anexa novo documento → Documento salvo com `empresa_id = B`
6. Empresa B visualiza:
   - Dados editados (override) + dados originais (se não editados)
   - Documento específico da Empresa B (prioridade) + documentos originais

### Cenário 3: Empresa A visualiza cliente novamente

1. Empresa A vê apenas:
   - Dados originais (não vê override da Empresa B)
   - Documentos originais (não vê documentos da Empresa B)

## Exemplos de Código

### Verificar se empresa é criadora

```php
$cliente = Cliente::find(1);
$isCriadora = $cliente->isEmpresaCriadora(); // true se empresa atual é criadora
```

### Obter dados específicos de uma empresa

```php
$dadosEmpresa = $cliente->dadosPorEmpresa($empresaId);
if ($dadosEmpresa) {
    echo $dadosEmpresa->nome; // Nome específico da empresa
}
```

### Obter documento priorizando específico da empresa

```php
$documento = $cliente->getDocumentoPorCategoria('documento');
// Retorna documento específico da empresa se existir, senão retorna original
```

### Acessar dados do cliente (com override automático)

```php
$cliente = Cliente::find(1);
echo $cliente->nome; // Automaticamente retorna override se existir
echo $cliente->telefone; // Automaticamente retorna override se existir
```

### Verificar tipo de documento

```php
$documento = ClientDocument::find(1);
if ($documento->isDocumentoOriginal()) {
    // É documento original
}
if ($documento->isDocumentoEmpresa()) {
    // É documento específico de uma empresa
}
```

## Migrations

### Migration: `create_cliente_dados_empresa_table`

Cria tabela para armazenar dados específicos por empresa.

### Migration: `add_empresa_id_to_client_documents_table`

Adiciona campo `empresa_id` à tabela `client_documents`.

## Considerações Importantes

1. **Dados Globais:** `tipo_pessoa` e `documento` são sempre globais e não podem ser sobrescritos
2. **Cache:** O modelo usa cache (`cachedDadosEmpresa`) para evitar múltiplas queries
3. **Soft Delete:** Tanto `ClienteDadosEmpresa` quanto `ClientDocument` usam soft delete
4. **Auditoria:** Todas as alterações são auditadas
5. **Validação:** Validações são aplicadas tanto para dados originais quanto para overrides

## Troubleshooting

### Problema: Dados não aparecem após edição

**Solução:** Verificar se o relacionamento `dadosEmpresa` está sendo carregado no controller e se o cache está sendo limpo.

### Problema: Documento original aparece ao invés do específico

**Solução:** Verificar se está usando `getDocumentoPorCategoria()` ao invés de `->first()` direto.

### Problema: Erro "No query results for model"

**Solução:** Verificar se está usando `withoutGlobalScope()` ao buscar cliente de outra empresa.

## Próximos Passos

- [ ] Adicionar histórico de alterações de dados por empresa
- [ ] Implementar notificações quando outra empresa edita dados
- [ ] Adicionar permissões para controlar quem pode editar dados de outras empresas
- [ ] Implementar sincronização de dados entre empresas (opcional)

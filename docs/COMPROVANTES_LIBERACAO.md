# Sistema de Comprovantes - Liberação de Dinheiro

## 📋 Funcionalidade

Sistema completo para anexar comprovantes em dois momentos críticos do fluxo de liberação de dinheiro:

1. **Quando o gestor libera dinheiro para o consultor**
2. **Quando o consultor confirma pagamento ao cliente**

## 📁 Estrutura de Dados

### Campos Adicionados na Tabela `emprestimo_liberacoes`

- `comprovante_liberacao`: Caminho do arquivo do comprovante quando o gestor libera
- `comprovante_pagamento_cliente`: Caminho do arquivo do comprovante quando o consultor paga ao cliente

### Localização dos Arquivos

Os comprovantes são armazenados em:
- **Liberação**: `storage/app/public/comprovantes/liberacoes/`
- **Pagamento ao Cliente**: `storage/app/public/comprovantes/pagamentos-cliente/`

## 🎯 Funcionalidades

### Para o Gestor

1. **Upload de Comprovante ao Liberar**
   - Ao clicar em "Liberar Dinheiro", abre um modal
   - Campo opcional para anexar comprovante
   - Formatos aceitos: PDF, JPG, JPEG, PNG
   - Tamanho máximo: 2MB

2. **Visualizar Comprovantes**
   - Na lista de liberações, coluna "Comprovante"
   - Botão "Ver" aparece se houver comprovante
   - Abre em nova aba para visualização/download

### Para o Consultor

1. **Upload de Comprovante ao Confirmar Pagamento**
   - Ao clicar em "Confirmar Pagamento", abre um modal
   - Campo opcional para anexar comprovante
   - Formatos aceitos: PDF, JPG, JPEG, PNG
   - Tamanho máximo: 2MB

2. **Visualizar Comprovantes**
   - Na lista "Minhas Liberações", coluna "Comprovantes"
   - Botões separados:
     - **"Liberação"**: Comprovante do gestor (se houver)
     - **"Pagamento"**: Comprovante do consultor (se houver)
   - Abrem em nova aba para visualização/download

## 🔧 Implementação Técnica

### Migration

```php
Schema::table('emprestimo_liberacoes', function (Blueprint $table) {
    $table->string('comprovante_liberacao')->nullable();
    $table->string('comprovante_pagamento_cliente')->nullable();
});
```

### Model - LiberacaoEmprestimo

**Campos adicionados ao `$fillable`:**
- `comprovante_liberacao`
- `comprovante_pagamento_cliente`

**Métodos auxiliares:**
- `getComprovanteLiberacaoUrlAttribute()`: Retorna URL pública do comprovante
- `getComprovantePagamentoClienteUrlAttribute()`: Retorna URL pública do comprovante
- `hasComprovanteLiberacao()`: Verifica se tem comprovante de liberação
- `hasComprovantePagamentoCliente()`: Verifica se tem comprovante de pagamento

### Service - LiberacaoService

**Método `liberar()` atualizado:**
```php
public function liberar(
    int $liberacaoId, 
    int $gestorId, 
    ?string $observacoes = null, 
    ?string $comprovantePath = null
): LiberacaoEmprestimo
```

**Método `confirmarPagamentoCliente()` atualizado:**
```php
public function confirmarPagamentoCliente(
    int $liberacaoId, 
    int $consultorId, 
    ?string $observacoes = null, 
    ?string $comprovantePath = null
): LiberacaoEmprestimo
```

### Controller - LiberacaoController

**Validação de upload:**
```php
'comprovante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048'
```

**Processamento:**
```php
if ($request->hasFile('comprovante')) {
    $comprovantePath = $request->file('comprovante')
        ->store('comprovantes/liberacoes', 'public');
}
```

### Views

**Modal de Liberação (Gestor):**
- Campo de upload de arquivo
- Aceita PDF, JPG, JPEG, PNG
- Máximo 2MB
- Opcional

**Modal de Confirmação (Consultor):**
- Campo de upload de arquivo
- Aceita PDF, JPG, JPEG, PNG
- Máximo 2MB
- Opcional

**Listagem:**
- Coluna "Comprovante" na lista do gestor
- Coluna "Comprovantes" na lista do consultor
- Botões para visualizar/download

## 📝 Exemplo de Uso

### Gestor Liberando Dinheiro

1. Gestor acessa "Liberações de Dinheiro"
2. Clica em "Liberar Dinheiro" na liberação desejada
3. Modal abre com:
   - Informações da liberação
   - Campo para anexar comprovante (opcional)
   - Campo para observações (opcional)
4. Anexa comprovante (ex: screenshot de transferência)
5. Clica em "Confirmar Liberação"
6. Sistema salva o arquivo e registra o caminho no banco

### Consultor Confirmando Pagamento

1. Consultor acessa "Minhas Liberações"
2. Clica em "Confirmar Pagamento" na liberação liberada
3. Modal abre com:
   - Informações do pagamento
   - Campo para anexar comprovante (opcional)
   - Campo para observações (opcional)
4. Anexa comprovante (ex: foto do recibo)
5. Clica em "Confirmar Pagamento"
6. Sistema salva o arquivo e registra o caminho no banco

## 🔒 Segurança

### Validações

- **Tipo de arquivo**: Apenas PDF, JPG, JPEG, PNG
- **Tamanho máximo**: 2MB
- **Opcional**: Não é obrigatório anexar comprovante
- **Armazenamento**: Arquivos salvos em `storage/app/public/` (acessível via link simbólico)

### Permissões

- **Gestor**: Pode anexar comprovante ao liberar
- **Consultor**: Pode anexar comprovante ao confirmar pagamento
- **Visualização**: Qualquer usuário autenticado pode ver os comprovantes (se tiver acesso à liberação)

## 📊 Benefícios

1. **Rastreabilidade**: Comprovantes documentam cada etapa do fluxo
2. **Auditoria**: Histórico completo com evidências
3. **Transparência**: Todos podem verificar os comprovantes
4. **Conformidade**: Facilita prestação de contas
5. **Segurança**: Evita fraudes e mal-entendidos

## 🔍 Como Acessar os Comprovantes

### Via Interface

- **Gestor**: Coluna "Comprovante" na lista de liberações
- **Consultor**: Coluna "Comprovantes" na lista de minhas liberações
- Clique no botão "Ver" ou "Liberação"/"Pagamento" para abrir

### Via URL Direta

```
https://seudominio.com/storage/comprovantes/liberacoes/nome-do-arquivo.pdf
https://seudominio.com/storage/comprovantes/pagamentos-cliente/nome-do-arquivo.jpg
```

## ⚠️ Observações Importantes

1. **Link Simbólico**: Certifique-se de que o link simbólico está criado:
   ```bash
   php artisan storage:link
   ```

2. **Permissões**: A pasta `storage/app/public` precisa ter permissões de escrita

3. **Backup**: Considere fazer backup regular dos comprovantes

4. **Limpeza**: Implemente rotina para limpar comprovantes antigos se necessário

5. **Privacidade**: Comprovantes podem conter dados sensíveis - proteja adequadamente

## 📈 Próximos Passos (Opcional)

- [ ] Notificação quando comprovante é anexado
- [ ] Preview de imagens antes do upload
- [ ] Múltiplos comprovantes por liberação
- [ ] Integração com cloud storage (S3, etc.)
- [ ] Compressão automática de imagens
- [ ] OCR para extrair dados de comprovantes

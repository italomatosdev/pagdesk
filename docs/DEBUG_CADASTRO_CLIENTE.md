# 🔍 Debug: Cliente não está sendo cadastrado

## O que foi implementado

1. **Logs detalhados** em todos os pontos críticos:
   - Início do cadastro no controller
   - Verificação de arquivos
   - Chamada ao service
   - Criação do cliente
   - Processamento de documentos
   - Erros capturados

2. **Tratamento de erros melhorado**:
   - Verificação se arquivos foram enviados
   - Validação de erros de upload
   - Mensagens de erro mais claras
   - Logs de todos os erros

## Como verificar o problema

### 1. Verificar os logs

Após tentar cadastrar um cliente, execute:

```bash
tail -100 storage/logs/laravel.log | grep -i "cliente\|error\|exception" | tail -50
```

Procure por:
- `"Iniciando cadastro de cliente"` - Confirma que o request chegou no controller
- `"Chamando ClienteService::cadastrar"` - Confirma que o service foi chamado
- `"Cliente criado com sucesso"` - Confirma que o cliente foi criado no banco
- `"Processando documentos"` - Confirma que os documentos estão sendo processados
- Qualquer linha com `ERROR` ou `Exception` - Mostra o erro específico

### 2. Verificar se há mensagens de erro na tela

Após tentar cadastrar, verifique se aparece alguma mensagem de erro na página. As mensagens podem aparecer:
- No topo da página (flash messages)
- Abaixo dos campos do formulário (validação)
- Em um alerta vermelho

### 3. Verificar o console do navegador

Abra o console do navegador (F12) e verifique:
- Se há erros JavaScript
- Se o formulário está sendo enviado (Network tab)
- Qual é o status da resposta (200, 302, 422, 500, etc.)

### 4. Verificar se o cliente foi criado no banco

Execute:

```bash
php artisan tinker
```

Depois:

```php
\App\Modules\Core\Models\Cliente::latest()->first();
```

Isso mostrará o último cliente criado. Se aparecer, o cliente foi criado mas pode haver um problema no redirecionamento.

### 5. Verificar se os arquivos foram salvos

```bash
ls -la storage/app/public/clientes/documentos/
ls -la storage/app/public/clientes/selfies/
```

Se houver arquivos aqui, os documentos foram salvos.

## Possíveis problemas e soluções

### Problema 1: Erro silencioso no processamento de documentos

**Sintoma:** Cliente é criado mas não aparece na listagem

**Solução:** Verifique os logs para ver se há erro no `processarDocumentos`. O erro pode estar impedindo o commit da transação.

### Problema 2: Validação falhando silenciosamente

**Sintoma:** Formulário não envia ou volta para a página sem mensagem

**Solução:** Verifique se todos os campos obrigatórios estão preenchidos e se os arquivos são válidos.

### Problema 3: Erro no redirecionamento

**Sintoma:** Cliente é criado mas aparece erro 404 ou página em branco

**Solução:** Verifique se a rota `clientes.show` existe e está funcionando.

### Problema 4: Erro de permissão no storage

**Sintoma:** Cliente não é criado e há erro ao salvar arquivos

**Solução:** Verifique permissões:
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

## Próximos passos

1. Tente cadastrar um cliente novamente
2. Verifique os logs imediatamente após tentar
3. Compartilhe as mensagens de erro encontradas nos logs
4. Verifique o console do navegador para erros JavaScript ou de rede

## Informações úteis para debug

- **Arquivo de log:** `storage/logs/laravel.log`
- **Rota do cadastro:** `POST /clientes`
- **Controller:** `App\Modules\Core\Controllers\ClienteController@store`
- **Service:** `App\Modules\Core\Services\ClienteService@cadastrar`

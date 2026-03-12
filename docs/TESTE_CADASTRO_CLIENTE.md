# 🧪 Teste: Cadastro de Cliente

## Como testar

1. **Acesse a página de cadastro:**
   ```
   http://localhost:8000/clientes/create
   ```

2. **Preencha o formulário:**
   - CPF: `12345678901` (ou qualquer CPF válido com 11 dígitos)
   - Nome: `Teste Cliente`
   - Documento do Cliente: Selecione um arquivo (PDF, JPG ou PNG)
   - Selfie com Documento: Selecione uma imagem (JPG ou PNG)
   - Outros campos são opcionais

3. **Clique em "Cadastrar Cliente"**

4. **Verifique os logs imediatamente:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
   
   Você deve ver:
   - `=== INÍCIO DO CADASTRO DE CLIENTE ===`
   - `Iniciando cadastro de cliente`
   - `Chamando ClienteService::cadastrar`
   - `Cliente criado com sucesso`
   - `Processando documentos`
   - `Documentos processados com sucesso`

5. **Verifique o console do navegador (F12):**
   - Aba "Console" - verifique se há erros JavaScript
   - Aba "Network" - verifique a requisição POST para `/clientes`
     - Status deve ser `200` ou `302` (redirect)
     - Se for `422`, há erro de validação
     - Se for `500`, há erro no servidor

## O que verificar

### Se NÃO aparecer `=== INÍCIO DO CADASTRO DE CLIENTE ===` nos logs:

**Problema:** O formulário não está sendo enviado ou há um erro antes de chegar no controller.

**Soluções:**
1. Verifique o console do navegador (F12) para erros JavaScript
2. Verifique se o botão de submit está funcionando
3. Verifique se há algum JavaScript que está impedindo o submit
4. Verifique se o CSRF token está presente (inspecione o formulário)

### Se aparecer `=== INÍCIO DO CADASTRO DE CLIENTE ===` mas não aparecer `Cliente criado com sucesso`:

**Problema:** Há um erro na validação ou no processamento.

**Soluções:**
1. Verifique a mensagem de erro completa nos logs
2. Verifique se os arquivos foram enviados corretamente
3. Verifique se o CPF já existe no banco

### Se aparecer `Cliente criado com sucesso` mas não redirecionar:

**Problema:** Erro no processamento dos documentos ou no redirecionamento.

**Soluções:**
1. Verifique os logs para erros no `processarDocumentos`
2. Verifique se a rota `clientes.show` existe e está funcionando
3. Verifique se o cliente foi criado no banco:
   ```bash
   php artisan tinker
   \App\Modules\Core\Models\Cliente::latest()->first();
   ```

## Informações de Debug

- **Rota:** `POST /clientes`
- **Controller:** `App\Modules\Core\Controllers\ClienteController@store`
- **Service:** `App\Modules\Core\Services\ClienteService@cadastrar`
- **Logs:** `storage/logs/laravel.log`

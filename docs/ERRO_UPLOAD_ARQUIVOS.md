# ❌ Erro: "The documento cliente failed to upload" / "The selfie documento failed to upload"

## Problema

Ao tentar cadastrar um cliente com documentos, você recebe as mensagens:
- "The documento cliente failed to upload"
- "The selfie documento failed to upload"

## Causa

Este erro acontece quando o **tamanho total do POST** excede o limite configurado no PHP (`post_max_size`), que por padrão é **8MB**.

O erro ocorre **ANTES** da validação do Laravel, no middleware `ValidatePostSize`, que verifica o tamanho do POST antes mesmo de processar os arquivos.

## ✅ Solução

### Passo 1: Verificar os limites atuais

Execute:
```bash
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

Se mostrar `8M` e `2M`, você precisa aumentar os limites.

### Passo 2: Editar o php.ini

**IMPORTANTE:** Se você está usando `php artisan serve`, o `.htaccess` **NÃO funciona**. Você **DEVE** editar o `php.ini`.

1. Encontrar o arquivo:
   ```bash
   php --ini
   ```
   
   Você verá algo como:
   ```
   Loaded Configuration File: /usr/local/etc/php/8.4/php.ini
   ```

2. Editar o arquivo:
   ```bash
   nano /usr/local/etc/php/8.4/php.ini
   ```
   
   Ou com seu editor preferido:
   ```bash
   code /usr/local/etc/php/8.4/php.ini
   ```

3. Procurar e alterar (use Ctrl+W ou Cmd+F):
   ```ini
   upload_max_filesize = 2M
   post_max_size = 8M
   ```
   
   Para:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 30M
   max_file_uploads = 20
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 256M
   ```

4. **SALVAR** o arquivo (Ctrl+O, Enter, Ctrl+X no nano)

### Passo 3: REINICIAR o servidor

**CRÍTICO:** Você **DEVE** reiniciar o servidor para as mudanças terem efeito:

1. Pare o servidor atual (Ctrl+C no terminal onde está rodando)
2. Inicie novamente:
   ```bash
   php artisan serve
   ```

### Passo 4: Verificar novamente

Execute novamente:
```bash
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

**Você DEVE ver:**
```
post_max_size: 30M
upload_max_filesize: 10M
```

Se ainda mostrar valores antigos:
- O arquivo não foi salvo corretamente
- O servidor não foi reiniciado
- Você editou o arquivo errado

## 🔍 Verificação Adicional

Se após seguir todos os passos o erro persistir:

1. Verifique se editou o arquivo correto:
   ```bash
   php --ini
   ```

2. Verifique se não há erros de sintaxe no php.ini:
   ```bash
   php -i | head -5
   ```
   Se houver erro, o PHP não iniciará.

3. Verifique se o servidor foi realmente reiniciado:
   - Pare completamente (Ctrl+C)
   - Inicie novamente (`php artisan serve`)
   - Verifique os limites novamente

## 📝 Notas Importantes

- **`post_max_size`** deve ser **maior** que `upload_max_filesize`
- Se você permitir múltiplos arquivos, `post_max_size` deve ser pelo menos `upload_max_filesize * número_de_arquivos`
- O `.htaccess` **só funciona com Apache**, não com `php artisan serve`
- As mudanças no `php.ini` **só têm efeito após reiniciar o servidor**

## 🆘 Ainda não funcionou?

Execute o script de ajuda:
```bash
bash ajustar-limites-upload.sh
```

Ele mostrará instruções detalhadas baseadas no seu ambiente.

# ❌ Erro: POST Content-Length exceeds the limit

## Problema

Você está recebendo o erro:
```
POST Content-Length of 23179779 bytes exceeds the limit of 8388608 bytes
```

Isso significa que o PHP ainda está com os limites padrão (8MB), mas você está tentando enviar ~22MB de dados.

## ✅ Solução IMEDIATA

### Passo 1: Encontrar o php.ini

Execute:
```bash
php --ini
```

Você verá algo como:
```
Loaded Configuration File: /usr/local/etc/php/8.4/php.ini
```

### Passo 2: Editar o php.ini

Abra o arquivo encontrado:
```bash
nano /usr/local/etc/php/8.4/php.ini
```

Ou se preferir usar outro editor:
```bash
code /usr/local/etc/php/8.4/php.ini  # VS Code
# ou
open -a TextEdit /usr/local/etc/php/8.4/php.ini  # TextEdit (macOS)
```

### Passo 3: Alterar os valores

Procure por estas linhas (use Ctrl+W ou Cmd+F para buscar):
```ini
upload_max_filesize = 2M
post_max_size = 8M
```

**Altere para:**
```ini
upload_max_filesize = 10M
post_max_size = 30M
max_file_uploads = 20
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

**IMPORTANTE:** 
- `post_max_size` DEVE ser maior que `upload_max_filesize`
- Se você permitir múltiplos arquivos, `post_max_size` deve ser pelo menos `upload_max_filesize * número_de_arquivos`

### Passo 4: Salvar e Reiniciar

1. **Salve o arquivo** (Ctrl+O, Enter, Ctrl+X no nano)
2. **Pare o servidor atual** (Ctrl+C no terminal onde está rodando `php artisan serve`)
3. **Inicie novamente:**
   ```bash
   php artisan serve
   ```

### Passo 5: Verificar

Execute:
```bash
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

**Você DEVE ver:**
```
post_max_size: 30M
upload_max_filesize: 10M
```

Se ainda mostrar `8M` e `2M`, o arquivo não foi salvo corretamente ou o servidor não foi reiniciado.

## ⚠️ Por que o .htaccess não funciona?

O arquivo `.htaccess` **só funciona com Apache**. Se você está usando:
- `php artisan serve` → **NÃO lê .htaccess** → Precisa editar php.ini
- Apache → Lê .htaccess → Já está configurado

## 🔍 Verificar qual servidor está usando

Se você iniciou o servidor com:
```bash
php artisan serve
```

Então você está usando o **servidor integrado do PHP**, que **NÃO lê .htaccess**.

## 📝 Resumo Rápido

1. Edite: `/usr/local/etc/php/8.4/php.ini`
2. Altere: `post_max_size = 30M` e `upload_max_filesize = 10M`
3. Salve o arquivo
4. Reinicie o servidor (`php artisan serve`)
5. Teste novamente

## 🆘 Ainda não funcionou?

1. Verifique se editou o arquivo correto:
   ```bash
   php --ini
   ```

2. Verifique se salvou o arquivo corretamente

3. Verifique se reiniciou o servidor (pare e inicie novamente)

4. Verifique se não há erros de sintaxe no php.ini:
   ```bash
   php -i | head -5
   ```
   Se houver erro, o PHP não iniciará.

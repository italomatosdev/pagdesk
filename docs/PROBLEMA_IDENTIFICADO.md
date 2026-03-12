# ✅ Problema Identificado: Limites do PHP

## 🔍 Diagnóstico

O log mostra que os arquivos **não estão sendo enviados**:
```
"has_documento":false,"has_selfie":false
```

E os limites do PHP ainda estão nos valores **padrão**:
```
post_max_size: 8M
upload_max_filesize: 2M
```

## ❌ Causa do Problema

Os arquivos estão sendo **rejeitados antes de chegar no controller** porque:
1. `upload_max_filesize = 2M` - Limite muito baixo para arquivos de 5MB
2. `post_max_size = 8M` - Limite muito baixo para múltiplos arquivos

Quando você tenta enviar arquivos maiores que esses limites, o PHP **rejeita silenciosamente** e os arquivos não chegam no Laravel.

## ✅ Solução

### 1. Editar o php.ini

Execute:
```bash
php --ini
```

Isso mostrará o caminho do arquivo. Exemplo:
```
Loaded Configuration File: /usr/local/etc/php/8.4/php.ini
```

### 2. Editar o arquivo

```bash
nano /usr/local/etc/php/8.4/php.ini
```

Procure e altere:
```ini
upload_max_filesize = 2M    →    upload_max_filesize = 10M
post_max_size = 8M          →    post_max_size = 30M
```

Adicione também:
```ini
max_file_uploads = 20
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

### 3. **REINICIAR O SERVIDOR**

**CRÍTICO:** Você **DEVE** reiniciar o servidor:

1. Pare o servidor atual (Ctrl+C no terminal onde está rodando `php artisan serve`)
2. Inicie novamente:
   ```bash
   php artisan serve
   ```

### 4. Verificar

```bash
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

**Você DEVE ver:**
```
post_max_size: 30M
upload_max_filesize: 10M
```

Se ainda mostrar `8M` e `2M`, o servidor não foi reiniciado ou o arquivo não foi salvo.

## 📝 Por que isso acontece?

Quando o PHP rejeita arquivos por exceder os limites:
- Os arquivos **não chegam** no Laravel
- `$request->hasFile()` retorna `false`
- `$request->file()` retorna `null`
- Mas os campos ainda aparecem no formulário (por isso aparecem em `all_inputs`)

## 🎯 Após corrigir

Depois de ajustar o `php.ini` e reiniciar o servidor, tente cadastrar o cliente novamente. Os arquivos devem chegar corretamente e o cadastro deve funcionar.

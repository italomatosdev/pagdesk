# Configuração de Limites de Upload

## Problema

Ao tentar fazer upload de documentos no cadastro de clientes, você pode receber o erro:
```
POST Content-Length of X bytes exceeds the limit of Y bytes
```

Isso acontece porque os limites padrão do PHP são muito baixos para uploads de arquivos.

## Solução Rápida

### Se estiver usando `php artisan serve` (Servidor Integrado)

O servidor integrado do PHP **não lê o .htaccess**. Você precisa editar o `php.ini` do sistema.

#### 1. Encontrar o arquivo php.ini:

```bash
php --ini
```

Isso mostrará o caminho do arquivo `php.ini` carregado.

#### 2. Editar o php.ini:

Abra o arquivo `php.ini` encontrado e procure por estas linhas:

```ini
upload_max_filesize = 2M
post_max_size = 8M
```

Altere para:

```ini
upload_max_filesize = 10M
post_max_size = 30M
max_file_uploads = 20
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

**Importante:** 
- `post_max_size` deve ser **maior** que `upload_max_filesize`
- Se você permitir múltiplos arquivos, `post_max_size` deve ser pelo menos `upload_max_filesize * número_de_arquivos`

#### 3. Reiniciar o servidor:

Após editar o `php.ini`, **reinicie o servidor**:

```bash
# Pare o servidor (Ctrl+C) e inicie novamente
php artisan serve
```

### Se estiver usando Apache

O arquivo `public/.htaccess` já foi configurado. Reinicie o Apache:

```bash
sudo apachectl restart
# ou
sudo service apache2 restart
```

### Se estiver usando Nginx + PHP-FPM

Edite o `php.ini` do PHP-FPM e reinicie:

```bash
sudo service php-fpm restart
```

## Verificar se Funcionou

Execute:

```bash
php -r "echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;"
```

Você deve ver:
```
post_max_size: 30M
upload_max_filesize: 10M
```

Se ainda mostrar valores antigos, o `php.ini` não foi carregado corretamente.

## Limites Configurados

- **upload_max_filesize**: 10MB por arquivo
- **post_max_size**: 30MB total (permite múltiplos arquivos)
- **max_file_uploads**: 20 arquivos simultâneos

## Nota Importante

Se você estiver usando **Docker** ou **Laravel Sail**, as configurações podem estar em:
- `vendor/laravel/sail/runtimes/8.4/php.ini` (ou versão correspondente)

Nesse caso, edite o arquivo correspondente à sua versão do PHP.

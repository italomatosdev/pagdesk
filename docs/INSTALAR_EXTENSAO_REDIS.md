# Instalar Extensão PHP Redis

O Laravel precisa da extensão PHP Redis para se conectar ao servidor Redis.

## 🔍 Verificar se está instalada

```bash
php -m | grep redis
```

Se não retornar nada, a extensão não está instalada.

## 🚀 Opção 1: PECL (tente primeiro)

```bash
brew install pkg-config
pecl install redis
```

Se aparecer **"No releases available"** (comum com PHP 8.4), use a **Opção 2**.

Após instalar, ative no `php.ini` (veja o caminho com `php --ini`):

```ini
extension=redis.so
```

---

## 🚀 Opção 2: Compilar do código-fonte (quando PECL falha)

Execute **no seu Mac** (assim o `redis.so` fica na mesma arquitetura do seu PHP):

```bash
# 1. Clonar repositório
cd /tmp && rm -rf phpredis && git clone --depth 1 https://github.com/phpredis/phpredis.git && cd phpredis

# 2. Compilar (use o mesmo PHP que você usa no dia a dia: php -v)
phpize && ./configure && make

# 3. Descobrir diretório de extensões do PHP
EXT_DIR=$(php -r "echo ini_get('extension_dir');")
echo "Diretório: $EXT_DIR"

# 4. Copiar redis.so (pode pedir senha se for em /usr/local)
sudo cp modules/redis.so "$EXT_DIR/"

# 5. Ativar no php.ini
PHP_INI=$(php --ini | grep "Loaded Configuration" | sed -e 's/.*: *//')
echo "Adicione esta linha ao arquivo: $PHP_INI"
echo "extension=redis.so"
```

Depois edite o `php.ini` (caminho mostrado acima), remova o `;` de `;extension=redis.so` ou adicione `extension=redis.so`.

**Importante:** Se der erro **"incompatible architecture (have 'arm64', need 'x86_64')"** (ou o contrário), você está usando um PHP de uma arquitetura e o `redis.so` foi compilado para outra. Rode os comandos acima no próprio Mac, com o mesmo `php` que usa no terminal (`which php`).

## ✅ Verificar instalação

```bash
# Verificar se a extensão está carregada
php -m | grep redis

# Deve retornar: redis

# Verificar versão
php -r "echo phpversion('redis');"
```

## 🔧 Testar no Laravel

Após instalar, teste:

```bash
# Limpar cache
php artisan config:clear
php artisan cache:clear

# Testar no tinker
php artisan tinker
>>> Cache::put('teste', 'funcionando', 60);
>>> Cache::get('teste');
# Deve retornar: "funcionando"
```

## ⚠️ Problemas Comuns

### Erro: "pecl: command not found"

```bash
# Instalar PECL via Homebrew
brew install php
```

### Erro: "Cannot find autoconf"

```bash
brew install autoconf
```

### Erro: "pkg-config not found"

```bash
brew install pkg-config
```

### Extensão não aparece após instalar

1. Verifique se adicionou `extension=redis.so` ao `php.ini` correto
2. Verifique qual `php.ini` está sendo usado:
   ```bash
   php --ini
   ```
3. Reinicie o servidor web/PHP-FPM:
   ```bash
   brew services restart php
   # OU
   sudo brew services restart php
   ```

### Erro: "No releases available for package pecl.php.net/redis"

O PECL às vezes não oferece build para PHP 8.4. Use a **Opção 2** (compilar do código-fonte) neste doc.

### Erro: "incompatible architecture (have 'arm64', need 'x86_64')"

O `redis.so` foi compilado para outra arquitetura que o seu PHP. Compile no seu Mac com os passos da **Opção 2**, usando o mesmo `php` do terminal (`which php`).

### Verificar qual PHP está sendo usado

```bash
which php
php -v
php --ini
uname -m
```

Certifique-se de que está usando o mesmo PHP que o Laravel usa.

## 📝 Nota

Se você não conseguir instalar a extensão agora, o sistema continua funcionando com:
- `CACHE_DRIVER=file` (cache em arquivos)
- `QUEUE_CONNECTION=sync` (filas síncronas)

O Redis é recomendado para melhor performance, mas não é obrigatório para desenvolvimento.

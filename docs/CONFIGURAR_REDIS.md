# Configurar Redis para Cache e Filas

O Redis é necessário para usar o cache do dashboard e o Laravel Horizon (filas).

## ✅ Verificar se Redis está instalado

```bash
which redis-server
```

Se retornar um caminho (ex: `/usr/local/bin/redis-server`), o Redis está instalado.

## 🚀 Iniciar o Redis

### macOS (Homebrew)

```bash
# Iniciar Redis manualmente
redis-server

# OU iniciar como serviço (recomendado)
brew services start redis

# Verificar se está rodando
redis-cli ping
# Deve retornar: PONG
```

### Linux (Ubuntu/Debian)

```bash
# Iniciar Redis
sudo systemctl start redis-server

# Verificar status
sudo systemctl status redis-server

# Habilitar para iniciar automaticamente
sudo systemctl enable redis-server
```

## ⚙️ Atualizar o arquivo .env

Altere as seguintes linhas no seu `.env`:

```env
# ANTES:
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# DEPOIS:
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

As configurações do Redis já estão corretas no seu `.env`:
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## ✅ Testar a conexão

Após iniciar o Redis e atualizar o `.env`, teste:

```bash
# Testar conexão Redis
redis-cli ping

# Testar no Laravel
php artisan tinker
>>> Cache::put('teste', 'funcionando', 60);
>>> Cache::get('teste');
# Deve retornar: "funcionando"
```

## 🎯 Iniciar o Horizon

Após configurar o Redis, você pode iniciar o Horizon:

```bash
php artisan horizon
```

O dashboard estará disponível em: `http://localhost:8000/horizon`

**Acesso:** Apenas administradores podem acessar.

## 📝 Notas

- **Desenvolvimento:** Você pode usar `QUEUE_CONNECTION=sync` temporariamente, mas os jobs serão executados síncronamente (mais lento).
- **Cache:** O cache do dashboard funciona melhor com Redis, mas também funciona com `file` (mais lento).
- **Produção:** Redis é **obrigatório** para produção com Horizon.

## 🔧 Troubleshooting

### Erro: "Connection refused" ao conectar no Redis

```bash
# Verificar se Redis está rodando
redis-cli ping

# Se não estiver, iniciar:
# macOS:
brew services start redis

# Linux:
sudo systemctl start redis-server
```

### Erro: "Class Redis not found" no PHP

Você precisa instalar a extensão PHP Redis. No macOS use **PECL** (não existe fórmula `php-redis` no Homebrew):

```bash
# 1. Instalar dependências (se necessário)
brew install pkg-config

# 2. Instalar extensão Redis via PECL
pecl install redis

# 3. Encontrar o arquivo php.ini
php --ini

# 4. Adicionar a extensão ao php.ini
# Procure por uma linha como: /usr/local/etc/php/8.4/php.ini
# E adicione no final do arquivo:
echo "extension=redis.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")

# OU edite manualmente o arquivo php.ini e adicione:
# extension=redis.so
```

Veja `docs/INSTALAR_EXTENSAO_REDIS.md` para o guia completo.

### Verificar extensão PHP Redis instalada

```bash
php -m | grep redis
```

Se retornar `redis`, a extensão está instalada.

### Após instalar, testar novamente

```bash
# Limpar cache do Laravel
php artisan config:clear
php artisan cache:clear

# Testar conexão
php artisan tinker
>>> Cache::put('teste', 'funcionando', 60);
>>> Cache::get('teste');
```

# Resolver Problema do Redis

## 🔍 Verificar se Redis está rodando

```bash
# Testar conexão
redis-cli ping

# Se retornar PONG, o Redis está funcionando! ✅
# Se der erro de conexão, continue com os passos abaixo.
```

## 🛑 Parar processos Redis existentes

Se a porta 6379 estiver ocupada, você precisa parar o processo:

```bash
# Ver qual processo está usando a porta 6379
lsof -i :6379

# OU usar ps para encontrar processos Redis
ps aux | grep redis

# Parar o processo (substitua PID pelo número do processo)
kill -9 <PID>

# OU parar todos os processos Redis
pkill redis-server
```

## 🚀 Iniciar Redis manualmente

Após parar processos existentes:

```bash
# Iniciar Redis em foreground (para ver logs)
redis-server

# OU iniciar em background
redis-server --daemonize yes
```

## ✅ Verificar se está funcionando

```bash
redis-cli ping
# Deve retornar: PONG
```

## 🔧 Alternativa: Usar brew services (requer Xcode license)

Se quiser usar `brew services`, primeiro aceite a licença do Xcode:

```bash
sudo xcodebuild -license accept
```

Depois:

```bash
# Parar serviço existente (se houver)
brew services stop redis

# Iniciar como serviço
brew services start redis

# Verificar status
brew services list | grep redis
```

## 📝 Atualizar .env

Depois que o Redis estiver rodando, atualize seu `.env`:

```env
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

E limpe o cache do Laravel:

```bash
php artisan config:clear
php artisan cache:clear
```

## 🎯 Testar no Laravel

```bash
php artisan tinker
>>> Cache::put('teste', 'funcionando', 60);
>>> Cache::get('teste');
# Deve retornar: "funcionando"
```

## ⚠️ Nota Importante

Se você não conseguir iniciar o Redis agora, o sistema continua funcionando com:
- `CACHE_DRIVER=file` (cache em arquivos)
- `QUEUE_CONNECTION=sync` (filas síncronas)

O Redis é recomendado para melhor performance, mas não é obrigatório para desenvolvimento.

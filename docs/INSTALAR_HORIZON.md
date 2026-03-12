# Como Instalar o Laravel Horizon

O Horizon foi adicionado ao `composer.json`, mas precisa ser instalado manualmente.

## Passos para Instalação

### 1. Instalar o pacote

```bash
composer require laravel/horizon
```

Se der erro de permissão ou cache, tente:

```bash
composer require laravel/horizon --no-cache
```

### 2. Publicar assets e configuração

```bash
php artisan horizon:install
```

Isso vai:
- Publicar o arquivo `config/horizon.php` (já criado, mas pode sobrescrever)
- Publicar assets do Horizon em `public/vendor/horizon`

### 3. Executar migrations

```bash
php artisan migrate
```

Isso cria as tabelas necessárias para o Horizon (`job_batches`, `failed_jobs`, etc.).

### 4. Configurar .env

```env
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Nota:** Se não tiver Redis instalado, você pode usar `QUEUE_CONNECTION=database` temporariamente, mas Redis é recomendado para produção.

### 5. Iniciar o Horizon

```bash
# Desenvolvimento
php artisan horizon

# Produção (com supervisor - ver docs/FILAS_E_HORIZON.md)
```

### 6. Acessar o Dashboard

Após iniciar o Horizon, acesse:

```
http://seu-dominio.com/horizon
```

**Acesso:** Apenas administradores podem acessar (configurado no `HorizonServiceProvider` via Gate `viewHorizon`).

---

## Se não conseguir instalar agora

O sistema funciona sem Horizon. Os jobs podem ser processados com:

```bash
# Usando queue worker padrão do Laravel
php artisan queue:work redis --tries=3
```

O Horizon apenas oferece um dashboard visual e melhor gerenciamento de workers. Os jobs (`GerarRelatorioJob`, `EnviarNotificacaoJob`) funcionam normalmente sem ele.

---

## Troubleshooting

### Erro: "Class Horizon not found"

O código já está protegido para funcionar sem Horizon instalado. Se você não vai usar Horizon agora, pode remover a linha do `composer.json`:

```bash
# Remover do composer.json (opcional)
composer remove laravel/horizon
```

E os jobs continuarão funcionando com `php artisan queue:work`.

### Erro de permissão no composer

```bash
# Limpar cache do composer
composer clear-cache

# Tentar novamente
composer require laravel/horizon
```

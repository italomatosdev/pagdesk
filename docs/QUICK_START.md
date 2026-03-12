# Quick Start - Início Rápido

## 🚀 Configuração Rápida (5 minutos)

### 1. Criar arquivo .env

**Opção A - Copiar do template:**
```bash
cd /Users/italomatos/Documents/Projetos/sistema-cred
cp template-webadmin/Admin/.env.example .env
```

**Opção B - Criar manualmente:**
Crie o arquivo `.env` na raiz com as configurações básicas (veja `docs/COMO_CRIAR_ENV.md`)

### 2. Gerar chave da aplicação
```bash
php artisan key:generate
```

### 3. Configurar banco de dados no .env
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cred
DB_USERNAME=root
DB_PASSWORD=sua_senha
```

### 4. Instalar dependências e configurar
```bash
# Instalar dependências PHP
composer install

# Instalar dependências NPM
npm install

# Executar migrations
php artisan migrate

# Executar seeders (cria usuários e dados exemplo)
php artisan db:seed

# Compilar assets
npm run dev
```

### 5. Iniciar servidor
```bash
php artisan serve
```

### 6. Acessar
- URL: `http://localhost:8000`
- Login: `admin@sistema-cred.com`
- Senha: `12345678`

## ✅ Pronto!

O sistema está funcionando! Você pode:
- Cadastrar clientes
- Criar empréstimos
- Ver cobranças do dia
- Registrar pagamentos
- Gerenciar operações (como admin)

## 📚 Documentação Completa

- [Guia de Instalação](./GUIA_INSTALACAO.md)
- [Como Criar .env](./COMO_CRIAR_ENV.md)
- [Localização de Arquivos](./LOCALIZACAO_ARQUIVOS.md)
- [Estrutura de Pastas](./ESTRUTURA_PASTAS.md)

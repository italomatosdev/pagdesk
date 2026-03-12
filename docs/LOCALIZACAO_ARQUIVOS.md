# Localização de Arquivos Importantes

## 📁 Arquivo .env

O arquivo `.env` fica na **raiz do projeto**:

```
sistema-cred/
├── .env              ← AQUI (na raiz)
├── .env.example      ← Template (criar .env a partir deste)
├── app/
├── config/
├── database/
└── ...
```

## 🔧 Como Criar o .env

### Opção 1: Copiar do exemplo
```bash
cd /Users/italomatos/Documents/Projetos/sistema-cred
cp .env.example .env
php artisan key:generate
```

### Opção 2: Criar manualmente
Crie o arquivo `.env` na raiz do projeto com o conteúdo do `.env.example` e ajuste as configurações.

## ⚙️ Configurações Importantes no .env

### Banco de Dados
```env
DB_CONNECTION=mysql    # ou pgsql
DB_HOST=127.0.0.1
DB_PORT=3306          # 5432 para PostgreSQL
DB_DATABASE=sistema_cred
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### Aplicação
```env
APP_NAME="Sistema de Crédito"
APP_ENV=local
APP_KEY=              # Gerado com: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost
```

### Locale (Português-BR)
```env
APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo
```

## 📝 Notas

- O arquivo `.env` **NÃO** deve ser commitado no Git (está no .gitignore)
- O arquivo `.env.example` **DEVE** ser commitado (template)
- Sempre use `.env.example` como base para criar o `.env`
- Após criar o `.env`, execute `php artisan key:generate` para gerar a chave da aplicação

## 🔍 Verificar se .env existe

```bash
# Verificar se existe
ls -la .env

# Ver conteúdo (sem senhas)
cat .env | grep -v PASSWORD
```

## ⚠️ Importante

- **Nunca** compartilhe o arquivo `.env` (contém senhas e chaves)
- **Sempre** use `.env.example` como referência
- Mantenha `.env.example` atualizado com todas as variáveis necessárias

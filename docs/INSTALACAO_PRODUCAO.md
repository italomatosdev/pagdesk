# Instalação da Infraestrutura de Produção - PagDesk

Este documento detalha o processo de instalação da infraestrutura de produção do sistema PagDesk.

## Visão Geral da Arquitetura

| Servidor | Função | IP Público | IP Privado (VPC) | Especificações |
|----------|--------|------------|------------------|----------------|
| **pagdesk-app** | Aplicação Docker | 172.235.157.83 | 10.0.0.2 | Linode Dedicated 4GB |
| **pagdesk-db-primary** | MySQL Principal | 172.235.157.93 | 10.0.0.3 | Linode Dedicated 4GB |
| **pagdesk-db-replica** | MySQL Réplica | 172.235.157.113 | 10.0.0.4 | Linode Dedicated 4GB |

**Provedor:** Linode (Akamai)  
**Região:** US, Miami, FL (us-mia)  
**Sistema Operacional:** Ubuntu 24.04 LTS  
**Custo Total:** $108/mês ($36 x 3)

---

## 1. Criação da VPC

Antes de criar os servidores, foi criada uma VPC para comunicação privada entre eles.

**Configuração da VPC:**
- **Nome:** `pagdesk-vpc`
- **Região:** US, Miami, FL (us-mia)
- **Subnet:** `pagdesk-subnet`
- **Range IPv4:** `10.0.0.0/24`

---

## 2. Criação dos Linodes

Cada Linode foi criado com as seguintes configurações:

- **Image:** Ubuntu 24.04 LTS
- **Region:** US, Miami, FL (us-mia)
- **Plan:** Dedicated 4GB ($36/mês)
- **Networking:**
  - Network Connection: VPC
  - VPC: `pagdesk-vpc`
  - Subnet: `pagdesk-subnet`
  - Auto-assign VPC IPv4: ✅
  - Allow public IPv4 access (1:1 NAT): ✅

**Add-ons:**
- `pagdesk-db-primary`: Backup automático habilitado ($5/mês)

---

## 3. Configuração Inicial (Todos os Servidores)

Executado em cada servidor como `root`:

```bash
# Atualizar sistema
apt update && apt upgrade -y

# Instalar pacotes essenciais
apt install -y curl wget git ufw fail2ban htop

# Criar usuário deploy
adduser deploy --disabled-password --gecos ""

# Adicionar ao grupo sudo
usermod -aG sudo deploy

# Permitir sudo sem senha para deploy
echo "deploy ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers.d/deploy

# Criar diretório SSH para o usuário deploy
mkdir -p /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chown deploy:deploy /home/deploy/.ssh
```

---

## 4. Configuração de Chave SSH

### No computador local (Mac):

```bash
# Verificar/criar chave SSH
cat ~/.ssh/id_ed25519.pub
# ou criar: ssh-keygen -t ed25519 -C "seu-email@exemplo.com"
```

### Em cada servidor (como root):

```bash
# Adicionar chave pública
echo "ssh-ed25519 AAAAC3... seu-email@exemplo.com" >> /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
```

### Teste de conexão:

```bash
ssh deploy@172.235.157.83    # app
ssh deploy@172.235.157.93    # db-primary
ssh deploy@172.235.157.113   # db-replica
```

---

## 5. Configuração do Firewall (UFW)

### No `pagdesk-app`:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow from 10.0.0.0/24 comment 'VPC Internal'
ufw --force enable
```

**Portas abertas:**
- 22/tcp (SSH)
- 80/tcp (HTTP)
- 443/tcp (HTTPS)
- 10.0.0.0/24 (VPC Internal)

### No `pagdesk-db-primary` e `pagdesk-db-replica`:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp comment 'SSH'
ufw allow from 10.0.0.0/24 to any port 3306 comment 'MySQL from VPC'
ufw allow from 10.0.0.0/24 comment 'VPC Internal'
ufw --force enable
```

**Portas abertas:**
- 22/tcp (SSH)
- 3306 apenas da VPC (MySQL)
- 10.0.0.0/24 (VPC Internal)

---

## 6. Desabilitar Login por Senha (SSH)

Executado em todos os servidores:

```bash
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd
```

---

## 7. Reinicialização dos Servidores

Após as configurações iniciais:

```bash
reboot
```

---

## 8. Instalação do MySQL no `pagdesk-db-primary`

```bash
# Instalar MySQL
apt update
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Configurar para aceitar conexões da VPC
sed -i 's/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf

# Configurar server-id para replicação (primary = 1)
echo -e "\n[mysqld]\nserver-id = 1\nlog_bin = /var/log/mysql/mysql-bin.log" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Reiniciar MySQL
systemctl restart mysql
```

### Criar banco e usuários:

```bash
mysql -u root <<EOF
-- Criar banco
CREATE DATABASE pagdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Usuário da aplicação (acesso do App server via VPC)
CREATE USER 'pagdesk'@'10.0.0.%' IDENTIFIED BY 'Pg2020dsk*pd';
GRANT ALL PRIVILEGES ON pagdesk.* TO 'pagdesk'@'10.0.0.%';

-- Usuário de replicação (para o replica server)
CREATE USER 'replicator'@'10.0.0.4' IDENTIFIED BY 'Pg2020dsk*pd';
GRANT REPLICATION SLAVE ON *.* TO 'replicator'@'10.0.0.4';

FLUSH PRIVILEGES;
EOF
```

### Verificar status do binlog:

```bash
mysql -u root -e "SHOW MASTER STATUS\G"
```

Resultado:
```
File: mysql-bin.000002
Position: 157
```

---

## 9. Instalação do MySQL no `pagdesk-db-replica`

```bash
# Instalar MySQL
apt update
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Permitir conexões da VPC
sed -i 's/bind-address.*=.*/bind-address = 0.0.0.0/' /etc/mysql/mysql.conf.d/mysqld.cnf

# Configurar server-id para replicação (replica = 2)
echo -e "\n[mysqld]\nserver-id = 2\nrelay-log = /var/log/mysql/mysql-relay-bin.log" >> /etc/mysql/mysql.conf.d/mysqld.cnf

# Reiniciar MySQL
systemctl restart mysql

# Criar banco (necessário antes da replicação)
mysql -u root -e "CREATE DATABASE pagdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Configurar replicação:

```bash
mysql -u root <<EOF
CHANGE REPLICATION SOURCE TO
    SOURCE_HOST='10.0.0.3',
    SOURCE_USER='replicator',
    SOURCE_PASSWORD='Pg2020dsk*pd',
    SOURCE_LOG_FILE='mysql-bin.000002',
    SOURCE_LOG_POS=157;

START REPLICA;
EOF
```

### Verificar status da replicação:

```bash
mysql -u root -e "SHOW REPLICA STATUS\G" | grep -E "Replica_IO_Running|Replica_SQL_Running|Seconds_Behind"
```

Resultado esperado:
```
Replica_IO_Running: Yes
Replica_SQL_Running: Yes
Seconds_Behind_Source: 0
```

### Testar replicação:

**No primary:**
```bash
mysql -u root -e "USE pagdesk; CREATE TABLE teste_rep (id INT); INSERT INTO teste_rep VALUES (999);"
```

**No replica:**
```bash
mysql -u root -e "USE pagdesk; SELECT * FROM teste_rep;"
# Deve retornar: 999
```

**Limpar tabela de teste:**
```bash
mysql -u root -e "USE pagdesk; DROP TABLE teste_rep;"
```

---

## 10. Credenciais de Acesso

### SSH

| Servidor | Comando |
|----------|---------|
| App | `ssh deploy@172.235.157.83` |
| DB Primary | `ssh deploy@172.235.157.93` |
| DB Replica | `ssh deploy@172.235.157.113` |

### MySQL (Aplicação)

| Parâmetro | Valor |
|-----------|-------|
| DB_HOST | `10.0.0.3` |
| DB_PORT | `3306` |
| DB_DATABASE | `pagdesk` |
| DB_USERNAME | `pagdesk` |
| DB_PASSWORD | `Pg2020dsk*pd` |

### MySQL (Replicação)

| Parâmetro | Valor |
|-----------|-------|
| HOST | `10.0.0.3` |
| USER | `replicator` |
| PASSWORD | `Pg2020dsk*pd` |

---

## 11. Próximos Passos

- [ ] Instalar Docker no `pagdesk-app`
- [ ] Configurar deploy da aplicação
- [ ] Configurar CI/CD com GitHub Actions
- [ ] Configurar HTTPS com certificado SSL
- [ ] Configurar monitoramento (Grafana/Prometheus)
- [ ] Configurar backups automáticos
- [ ] Configurar domínio e DNS

---

## Comandos Úteis

### Verificar status dos serviços:

```bash
# MySQL
systemctl status mysql

# Firewall
ufw status

# Fail2ban
systemctl status fail2ban
```

### Verificar replicação MySQL:

```bash
# No replica
mysql -u root -e "SHOW REPLICA STATUS\G"
```

### Logs importantes:

```bash
# MySQL
tail -f /var/log/mysql/error.log

# Sistema
tail -f /var/log/syslog

# Auth/SSH
tail -f /var/log/auth.log
```

---

## Troubleshooting

### Replicação não conecta

1. Verificar conectividade:
```bash
mysql -h 10.0.0.3 -u replicator -p'Pg2020dsk*pd' -e "SELECT 1"
```

2. Verificar firewall no primary:
```bash
ufw status
```

3. Verificar logs:
```bash
tail -20 /var/log/mysql/error.log
```

### Erro de autenticação na replicação

Se aparecer erro `caching_sha2_password`, alterar para `mysql_native_password`:

```bash
# No primary
mysql -u root -e "ALTER USER 'replicator'@'10.0.0.4' IDENTIFIED WITH mysql_native_password BY 'Pg2020dsk*pd'; FLUSH PRIVILEGES;"

# No replica
mysql -u root -e "STOP REPLICA; START REPLICA;"
```

---

## Histórico de Instalação

| Data | Ação |
|------|------|
| 2026-03-12 | Criação da VPC `pagdesk-vpc` |
| 2026-03-12 | Criação dos 3 Linodes |
| 2026-03-12 | Configuração inicial (usuário deploy, SSH, UFW, fail2ban) |
| 2026-03-12 | Instalação MySQL Primary com replicação |
| 2026-03-12 | Instalação MySQL Replica e configuração da replicação |
| 2026-03-12 | Teste de replicação bem-sucedido |

---

**Documento atualizado em:** 2026-03-12

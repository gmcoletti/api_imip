# API Solicitação Atendimento

API em **PHP 8.5** que consulta o **PostgreSQL** com um SQL configurável no `.env`, autentica via **Bearer Token** e documenta os endpoints no **Swagger** (`index.php`).

---

## Requisitos do servidor

| Item | Versão / detalhe |
|------|------------------|
| PHP | **8.5 ou superior** |
| Extensões PHP | `pdo`, `pdo_pgsql`, `json` |
| Banco | PostgreSQL acessível pela rede do servidor |
| Web server | Apache, Nginx ou IIS com PHP-FPM / FastCGI |

Verificar a versão no servidor:

```bash
php -v
```

Deve aparecer algo como `PHP 8.5.x`. Se for inferior, atualize o PHP antes de publicar.

Extensões:

```bash
php -m | findstr /i "pdo pgsql json"
```

No Linux:

```bash
php -m | grep -E 'pdo|pgsql|json'
```

---

## Estrutura dos arquivos

```
api_aqualidade/
├── .env                         # Configuração real (NÃO versionar)
├── .env.example                 # Modelo de configuração
├── .gitignore
├── composer.json                # Exige PHP >= 8.5 e extensões
├── config.php                   # Conexão, .env e autenticação
├── index.php                    # Swagger UI
├── openapi.yaml                 # Especificação OpenAPI
├── LEIA-ME.md                   # Este arquivo
└── api/
    └── solicitacao-atendimento.php
```

---

## Passo a passo da implantação

### 1. Copiar os arquivos para o servidor

Envie a pasta do projeto para o document root (ou um virtual host), por exemplo:

- Linux: `/var/www/api_aqualidade`
- Windows/IIS: `C:\inetpub\wwwroot\api_aqualidade`

### 2. Criar o `.env`

```bash
cp .env.example .env
```

No Windows (PowerShell):

```powershell
Copy-Item .env.example .env
```

Edite o `.env` com os dados reais:

```env
DB_HOST=IP_DO_POSTGRES
DB_PORT=5432
DB_USER=usuario
DB_PASSWORD=senha
DB_NAME=nome_do_banco

API_BEARER_TOKEN=asd1fa5sd41651326412654126

# Obrigatório: use o placeholder :numero_atendimento
SQL=SELECT * FROM solicitacao_atendimento WHERE numero_atendimento = :numero_atendimento
```

Ajuste o `SQL` para a tabela/colunas reais do ambiente. O placeholder `:numero_atendimento` é substituído pelo valor enviado na requisição.

### 3. Permissões

- O usuário do PHP (ex.: `www-data`, `nginx`, `IUSR`) precisa **ler** todos os arquivos.
- O arquivo `.env` deve ficar **somente leitura** para o PHP e **sem acesso público** quando possível.
- Não publique o `.env` no Git.

### 4. Document root / virtual host

Aponte o site para a pasta do projeto, de forma que:

| URL | Resultado |
|-----|-----------|
| `https://seu-dominio/` ou `/index.php` | Swagger |
| `https://seu-dominio/api/solicitacao-atendimento.php` | Endpoint da API |
| `https://seu-dominio/openapi.yaml` | Spec OpenAPI |

#### Apache (exemplo)

```apache
<VirtualHost *:80>
    ServerName api.seudominio.com
    DocumentRoot /var/www/api_aqualidade

    <Directory /var/www/api_aqualidade>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Garanta que o Apache passe o header `Authorization` (às vezes necessário no `.htaccess` ou no VirtualHost):

```apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

#### Nginx (exemplo)

```nginx
server {
    listen 80;
    server_name api.seudominio.com;
    root /var/www/api_aqualidade;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Garante o Bearer Token
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    }

    # Bloqueia acesso direto ao .env
    location ~ /\.env {
        deny all;
    }
}
```

### 5. Firewall / rede

O servidor da API precisa alcançar o PostgreSQL:

- IP/porta do `DB_HOST`:`DB_PORT` liberados
- Usuário do banco com permissão de `SELECT` nas tabelas usadas no `SQL`

Teste a conexão no servidor:

```bash
psql -h IP_DO_POSTGRES -U usuario -d nome_do_banco -c "SELECT 1;"
```

### 6. Validar

1. Abra no navegador: `https://seu-dominio/index.php` → deve abrir o **Swagger** com **API Solicitação Atendimento**.
2. Clique em **Authorize** e informe:

   ```
   asd1fa5sd41651326412654126
   ```

   (ou o valor configurado em `API_BEARER_TOKEN`)
3. Teste o endpoint `GET /api/solicitacao-atendimento.php` com um `numero_atendimento` válido.

---

## Como chamar a API

### Header obrigatório

```
Authorization: Bearer asd1fa5sd41651326412654126
```

### GET

```
GET /api/solicitacao-atendimento.php?numero_atendimento=123456
Authorization: Bearer asd1fa5sd41651326412654126
```

### POST

```
POST /api/solicitacao-atendimento.php
Authorization: Bearer asd1fa5sd41651326412654126
Content-Type: application/json

{
  "numero_atendimento": "123456"
}
```

### Resposta de sucesso

```json
{
  "success": true,
  "numero_atendimento": "123456",
  "total": 1,
  "data": [
    {
      "...": "colunas retornadas pelo SELECT do .env"
    }
  ]
}
```

### Erros comuns

| HTTP | Causa |
|------|--------|
| 400 | Faltou `numero_atendimento` |
| 401 | Token ausente ou inválido |
| 405 | Método diferente de GET/POST |
| 500 | Falha no banco, `.env` ou SQL sem `:numero_atendimento` |

---

## Checklist rápido

- [ ] PHP **8.5+** instalado
- [ ] Extensões `pdo`, `pdo_pgsql` e `json` ativas
- [ ] Arquivo `.env` preenchido (host, user, senha, database, SQL, token)
- [ ] SQL com placeholder `:numero_atendimento`
- [ ] PostgreSQL acessível a partir do servidor
- [ ] Header `Authorization` chegando no PHP (Apache/Nginx)
- [ ] `.env` bloqueado para acesso público
- [ ] Swagger abre em `index.php`
- [ ] Teste GET/POST com Bearer Token retorna JSON

---

## Segurança

- Troque o token padrão em produção se necessário (`API_BEARER_TOKEN`).
- Não versione o `.env`.
- Use HTTPS em produção.
- Restrinja o usuário do PostgreSQL ao mínimo necessário (somente leitura, se for o caso).
- Mantenha o SQL do `.env` apenas com `SELECT` (evite DML/DDL).

---

## Suporte local (desenvolvimento)

Na pasta do projeto:

```bash
php -S localhost:8080
```

Depois abra: [http://localhost:8080/index.php](http://localhost:8080/index.php)

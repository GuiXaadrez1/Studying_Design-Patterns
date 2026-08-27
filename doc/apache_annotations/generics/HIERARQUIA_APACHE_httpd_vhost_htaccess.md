# HIERARQUIA E ARQUITETURA DE CONFIGURAÇÃO APACHE
## httpd.conf vs vhost.conf vs .htaccess

**Nível:** Intermediário (2)  
**Público:** TI/Infraestrutura Apache  
**Contexto:** VPS/Dedicado com cPanel+WHM  

---

# PREFÁCIO

Muitos desenvolvedores tratam Apache como "caixa preta". Copiam regras de `.htaccess` sem entender:
- Por que não podem fazer certas coisas em `.htaccess` que poderiam em `httpd.conf`?
- Como dados de um domínio não "vazam" para outro domínio?
- Por que às vezes adicionar `.htaccess` "não funciona"?

Este documento explica a **arquitetura científica** de como Apache lê, processa e aplica configurações.

---

# CAPÍTULO 1: ESTRUTURA HIERÁRQUICA

## 1.1 - Os Três Níveis de Configuração

```
┌──────────────────────────────────────────────────────────────┐
│ NÍVEL 1: SISTEMA OPERACIONAL                                 │
│ Arquivo: /etc/apache2/httpd.conf (Linux)                     │
│ Acesso: SSH com permissão root                                │
│ Escopo: GLOBAL (afeta TODOS os domínios/sites)                │
│ Perigo: CRÍTICO (erros derrubam Apache inteiro)              │
│ Modificação: NÃO recomendado via cPanel                       │
│                                                               │
│ Função: Carregar módulos, definir paths, config global       │
└──────────┬───────────────────────────────────────────────────┘
           │ (Apache lê na sequência)
           │ (ORDEM 1)
           ↓
┌──────────────────────────────────────────────────────────────┐
│ NÍVEL 2: DOMÍNIO / VIRTUAL HOST                              │
│ Arquivo: /etc/apache2/conf.d/vhost.conf (gerado cPanel)      │
│ Acesso: cPanel (WHM para VPS/dedicado)                       │
│ Escopo: Por domínio (afeta 1 site)                            │
│ Perigo: Médio (erros afetam 1 domínio, não Apache)           │
│ Modificação: Via cPanel normalmente                           │
│                                                               │
│ Função: Definir DocumentRoot, SSL, logs, policy por domínio  │
└──────────┬───────────────────────────────────────────────────┘
           │ (Apache lê na sequência)
           │ (ORDEM 2)
           ↓
┌──────────────────────────────────────────────────────────────┐
│ NÍVEL 3: PASTA / PROJETO                                     │
│ Arquivo: .htaccess (criado por você em cada pasta)            │
│ Acesso: FTP/SSH/cPanel File Manager                           │
│ Escopo: Esta pasta + subpastas                                │
│ Perigo: Baixo (afeta só este projeto)                        │
│ Modificação: Via qualquer editor                              │
│                                                               │
│ Função: Rewrite de URLs, cache, headers, segurança local     │
└──────────────────────────────────────────────────────────────┘
           │ (Apache lê DURANTE requisição)
           │ (ORDEM 3)
           ↓
    ┌──────────────┐
    │ EXECUTAR     │
    │ (PHP/APP)    │
    └──────────────┘
```

### Fluxo de Leitura do Apache

```
BOOT (Uma vez ao iniciar Apache):
┌──────────────────────────────────────────────┐
│ 1. Apache lê /etc/apache2/httpd.conf          │
│    ├─ Carrega módulos (mod_rewrite, etc)     │
│    ├─ Define variáveis globais                │
│    ├─ Lê arquivo de includes                  │
│    │  └─ Include /etc/apache2/conf.d/*        │
│    └─ Processa TODOS vhost.conf               │
│       └─ Criado na ordem de domínios          │
│                                                │
│ 2. Apache sobe e FICA AGUARDANDO requisições │
└──────────────────────────────────────────────┘

REQUISIÇÃO (A cada acesso do cliente):
┌──────────────────────────────────────────────┐
│ 1. Cliente solicita: GET /usuarios/123        │
│                                                │
│ 2. Apache recebe (porta 80/443)               │
│    ├─ Identifica domínio (HTTP_HOST)         │
│    └─ Busca vhost.conf correspondente         │
│                                                │
│ 3. Verifica cada regra do vhost               │
│    ├─ DocumentRoot: /home/user/public_html   │
│    ├─ SSL settings                            │
│    └─ Outras diretivas                        │
│                                                │
│ 4. Apache entra na pasta (public_html)        │
│    ├─ Procura .htaccess                      │
│    └─ Se encontra, processa regras            │
│       ├─ RewriteEngine On                    │
│       ├─ RewriteCond ...                     │
│       └─ RewriteRule ...                     │
│                                                │
│ 5. Executa PHP com URL reescrita (se houver) │
└──────────────────────────────────────────────┘
```

---

## 1.2 - Hierarquia de Diretórios (Estrutura de Pastas)

```
/ (raiz do servidor)
│
├─ /etc/
│  └─ apache2/
│     ├─ httpd.conf                    ← NÍVEL 1 (GLOBAL)
│     ├─ conf.d/
│     │  ├─ vhost_001_ipaccontabilidade.com.br.conf    ← NÍVEL 2
│     │  ├─ vhost_002_api.ipaccontabilidade.com.br.conf ← NÍVEL 2
│     │  └─ vhost_003_crm.ipaccontabilidade.com.br.conf ← NÍVEL 2
│     │
│     └─ sites-enabled/ (Linux)
│        ├─ 001-ipaccontabilidade.com.br.conf
│        └─ 002-staging.ipaccontabilidade.com.br.conf
│
├─ /home/ (VPS/dedicado - cPanel)
│  └─ ipacuser/
│     ├─ public_html/                  ← DOCUMENTROOT para ipaccontabilidade.com.br
│     │  ├─ .htaccess                  ← NÍVEL 3 (SITE PRINCIPAL)
│     │  ├─ index.php
│     │  ├─ admin/
│     │  │  ├─ .htaccess               ← NÍVEL 3.1 (SUBFOLDER)
│     │  │  └─ dashboard.php
│     │  ├─ api/
│     │  │  ├─ .htaccess               ← NÍVEL 3.2 (SUBFOLDER)
│     │  │  ├─ index.php
│     │  │  └─ usuarios/
│     │  │     ├─ .htaccess            ← NÍVEL 3.3 (SUB-SUBFOLDER)
│     │  │     └─ controller.php
│     │  └─ uploads/
│     │     ├─ .htaccess               ← NÍVEL 3.4 (PASTA UPLOAD)
│     │     └─ logo.png
│     │
│     ├─ public_html_staging/          ← DOCUMENTROOT para staging.ipaccontabilidade.com.br
│     │  ├─ .htaccess                  ← NÍVEL 3 (STAGING)
│     │  ├─ index.php
│     │  └─ ...
│     │
│     └─ public_html_api/              ← DOCUMENTROOT para api.ipaccontabilidade.com.br
│        ├─ .htaccess                  ← NÍVEL 3 (API)
│        ├─ index.php
│        └─ ...
│
└─ /var/log/apache2/ (logs)
   ├─ access_log
   └─ error_log
```

### Explicação da Hierarquia de Pastas:

```
CADA DOMÍNIO = UM VHOST SEPARADO:

┌─ ipaccontabilidade.com.br
│  ├─ Vhost config: /etc/apache2/conf.d/vhost_001_ipaccontabilidade.com.br.conf
│  ├─ DocumentRoot: /home/ipacuser/public_html
│  └─ .htaccess: /home/ipacuser/public_html/.htaccess
│
├─ api.ipaccontabilidade.com.br
│  ├─ Vhost config: /etc/apache2/conf.d/vhost_002_api.ipaccontabilidade.com.br.conf
│  ├─ DocumentRoot: /home/ipacuser/public_html_api
│  └─ .htaccess: /home/ipacuser/public_html_api/.htaccess
│
└─ staging.ipaccontabilidade.com.br
   ├─ Vhost config: /etc/apache2/conf.d/vhost_003_staging.ipaccontabilidade.com.br.conf
   ├─ DocumentRoot: /home/ipacuser/public_html_staging
   └─ .htaccess: /home/ipacuser/public_html_staging/.htaccess


MÚLTIPLOS .htaccess NA MESMA ÁRVORE:

/home/ipacuser/public_html/
│
├─ .htaccess                          ← RAIZ (aplica a tudo)
│  (RewriteEngine On, HTTPS, cache)
│
├─ admin/
│  ├─ .htaccess                       ← ADMIN (sobrescreve raiz)
│  │  (Autenticação básica, restrict IP)
│  └─ dashboard.php
│
├─ api/
│  ├─ .htaccess                       ← API (sobrescreve raiz)
│  │  (JSON headers, CORS, versioning)
│  └─ usuarios/
│     └─ .htaccess                    ← USUARIOS (sobrescreve api)
│        (Específico para /api/usuarios)
│
└─ uploads/
   └─ .htaccess                       ← UPLOADS (sobrescreve raiz)
      (Bloquear execução PHP, Deny .htaccess)
```

---

# CAPÍTULO 2: ARQUIVO httpd.conf

## 2.1 - O que é httpd.conf?

```
ARQUIVO: /etc/apache2/httpd.conf
NÍVEL: Sistema operacional (GLOBAL)
ACESSO: Apenas SSH com permissão root
MODIFICAÇÃO: Requer restart Apache (apachectl restart)
ESCOPO: Afeta TODOS os domínios
PERIGO: CRÍTICO (erro = Apache não inicia)
```

## 2.2 - Conteúdo Típico

```apache
# ================================================
# /etc/apache2/httpd.conf (VERSÃO SIMPLIFICADA)
# ================================================

# SEÇÃO 1: DIRETIVAS GLOBAIS
ServerRoot "/etc/apache2"
Listen 80
Listen 443

# SEÇÃO 2: CARREGAMENTO DE MÓDULOS
LoadModule mpm_prefork_module modules/mod_mpm_prefork.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule php_module modules/mod_php.so
# ... mais 60+ módulos ...

# SEÇÃO 3: CONFIGURAÇÃO DE MÓDULOS CARREGADOS
<IfModule mod_ssl.c>
    SSLEngine on
    SSLCipherSuite HIGH:!aNULL:!MD5
</IfModule>

# SEÇÃO 4: INCLUDES (Carrega outras configs)
Include /etc/apache2/mods-enabled/*.load
Include /etc/apache2/mods-enabled/*.conf
Include /etc/apache2/conf.d/*.conf          ← LEIA VHOSTS AQUI!

# SEÇÃO 5: DEFAULT DOCUMENTROOT (raramente usado)
DocumentRoot "/var/www/html"

# SEÇÃO 6: DIRETIVA DE DOCUMENTROOT
<Directory "/var/www/html">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

# ================================================
# IMPORTANTE: O httpd.conf NÃO define sites individuais
# Cada site/domínio é definido em um vhost.conf separado
# ================================================
```

## 2.3 - Diretivas Críticas no httpd.conf

| Diretiva | Função | Exemplo |
|----------|--------|---------|
| **ServerRoot** | Caminho base para todos os arquivos | ServerRoot "/etc/apache2" |
| **Listen** | Portas que Apache escuta | Listen 80 (HTTP), Listen 443 (HTTPS) |
| **LoadModule** | Carrega módulo compilado | LoadModule rewrite_module modules/mod_rewrite.so |
| **Include** | Inclui outro arquivo de config | Include /etc/apache2/conf.d/*.conf |
| **DocumentRoot** | Pasta padrão de documentos | DocumentRoot "/var/www/html" |
| **DefaultType** | MIME type padrão | DefaultType text/html |
| **Timeout** | Tempo limite de requisição | Timeout 300 |
| **MaxConnections** | Máximo de conexões simultâneas | MaxConnections 256 |

## 2.4 - O Que NÃO FAZER no httpd.conf

```apache
# ❌ NÃO coloque .htaccess ou regras específicas de site aqui!
# O httpd.conf é para GLOBAL apenas

# ❌ ERRADO
RewriteEngine On
RewriteRule ^(.*)$ index.php [L]
# Isso afetaria TODOS os domínios! Caos!

# ✅ CORRETO
# Coloque em /etc/apache2/conf.d/vhost_seu_dominio.conf
# ou em .htaccess de cada site
```

---

# CAPÍTULO 3: ARQUIVO vhost.conf (Virtual Host)

## 3.1 - O que é vhost.conf?

```
ARQUIVO: /etc/apache2/conf.d/vhost_DOMINIO.conf
NÍVEL: Domínio específico
ACESSO: cPanel (WHM para gerenciar)
MODIFICAÇÃO: Requer restart Apache (cPanel faz automaticamente)
ESCOPO: Afeta UM domínio
PERIGO: Médio (erro afeta 1 domínio, não Apache)
```

## 3.2 - Exemplo Real de vhost.conf

```apache
# ================================================
# /etc/apache2/conf.d/vhost_001_ipaccontabilidade.com.br.conf
# (Gerado automaticamente pelo cPanel)
# ================================================

# BLOCO: Configuração do Virtual Host
<VirtualHost *:80>
    # Identificação do VirtualHost
    ServerName ipaccontabilidade.com.br
    ServerAlias www.ipaccontabilidade.com.br
    
    # Pasta raiz do domínio
    DocumentRoot /home/ipacuser/public_html
    
    # User e grupo do Apache
    User ipacuser
    Group ipacuser
    
    # ================================================
    # NÍVEL DE PERMISSÕES
    # ================================================
    # DIRETIVA CRÍTICA: AllowOverride All
    # Esta diretiva PERMITE que .htaccess sobrescreva config
    <Directory /home/ipacuser/public_html>
        Options FollowSymLinks
        AllowOverride All        # ← PERMITE .htaccess usar tudo
        Require all granted
    </Directory>
    
    # ================================================
    # LOGS
    # ================================================
    CustomLog /home/ipacuser/logs/access_log combined
    ErrorLog /home/ipacuser/logs/error_log
    
    # ================================================
    # SSL/HTTPS (Se certificado instalado)
    # ================================================
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ipaccontabilidade.com.br.crt
    SSLCertificateKeyFile /etc/ssl/private/ipaccontabilidade.com.br.key
    SSLCertificateChainFile /etc/ssl/certs/ipaccontabilidade.com.br.ca
</VirtualHost>

# BLOCO: Redirecionamento HTTP → HTTPS
<VirtualHost *:80>
    ServerName ipaccontabilidade.com.br
    ServerAlias www.ipaccontabilidade.com.br
    
    # Redireciona tudo para HTTPS
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
```

## 3.3 - Diretiva Crítica: AllowOverride

```apache
# A DIRETIVA MAIS IMPORTANTE PARA .htaccess:

<Directory /home/ipacuser/public_html>
    AllowOverride All
end</Directory>

# O que significa?
# AllowOverride All = PERMITTE que .htaccess sobrescreva tudo

# POSSÍVEIS VALORES:
AllowOverride All           # ✅ Permite TUDO (recomendado)
AllowOverride FileInfo      # Permite apenas Headers/Expires
AllowOverride AuthConfig    # Permite apenas Auth
AllowOverride Indexes       # Permite apenas Options -Indexes
AllowOverride Limit         # Permite Order/Deny/Allow
AllowOverride None          # ❌ BLOQUEIA .htaccess COMPLETAMENTE

# CONSEQUÊNCIA:
# Se AllowOverride None → .htaccess é IGNORADO (silenciosamente!)
```

## 3.4 - Como Acessar vhost.conf via cPanel

```
MÉTODO 1: Ver (não editar)
┌────────────────────────────────────────┐
│ cPanel Home                             │
│ → Apache Configuration                  │
│ → View/Edit Vhost Configuration        │
│ Procure seu domínio                    │
│ Vê o vhost.conf completo               │
└────────────────────────────────────────┘

MÉTODO 2: Editar (VPS/Dedicado com WHM)
┌────────────────────────────────────────┐
│ WHM (WebHost Manager)                   │
│ → Home → Service Configuration          │
│ → Apache Configuration                  │
│ → Edit Apache Configuration             │
│ Edite httpd.conf ou include directives │
│ Salve e recarregue Apache               │
└────────────────────────────────────────┘

AVISO: Editar vhost.conf via SSH é arriscado!
Melhor usar cPanel/WHM interface.
```

## 3.5 - Ordem de VirtualHosts

```
IMPORTANTE: A ordem importa!

Se você acessar http://ipaccontabilidade.com.br

Apache procura o PRIMEIRO VirtualHost que MATCH:

Arquivo de config lido:
1. vhost_001_ipaccontabilidade.com.br.conf
   ├─ ServerName: ipaccontabilidade.com.br ← MATCH! (uso este)
   └─ Processa este VirtualHost

2. vhost_002_api.ipaccontabilidade.com.br.conf
   ├─ ServerName: api.ipaccontabilidade.com.br ← NÃO MATCH
   └─ Ignora (já tem um match)

3. vhost_003_staging.ipaccontabilidade.com.br.conf
   └─ Não processa (já tem um match)


CONSEQUÊNCIA:
Se 2 vhosts tiverem mesmo ServerName:
├─ Só o PRIMEIRO é usado
└─ O segundo é IGNORADO (silenciosamente!)
```

---

# CAPÍTULO 4: ARQUIVO .htaccess

## 4.1 - O que é .htaccess?

```
ARQUIVO: .htaccess (no seu projeto)
NÍVEL: Pasta/Projeto
ACESSO: FTP, SSH, cPanel File Manager
MODIFICAÇÃO: NÃO requer restart (lido a cada requisição)
ESCOPO: Esta pasta + subpastas (se não sobrescrito)
PERIGO: Baixo (afeta só este projeto)
PERFORMANCE: Cuidado (lido a cada acesso)
```

## 4.2 - Por que .htaccess Existe?

```
PROBLEMA HISTÓRICO:
"Desenvolvedores não têm acesso SSH"
"Desenvolvedores não podem mexer em httpd.conf"
"Mas desenvolvedores PRECISAM de rewrite, cache, etc"

SOLUÇÃO:
Apache permite .htaccess em pastas de documentos
Arquivo lido a cada requisição
Permissões aplicadas localmente (não global)

RESULTADO:
Desenvolvedores podem fazer quase tudo em .htaccess
Sem risco de quebrar Apache inteiro
```

## 4.3 - Estrutura de .htaccess em Projeto

```
/home/ipacuser/public_html/
│
├─ .htaccess                    ← Config RAIZ (aplicada a tudo)
│  ├─ RewriteEngine On
│  ├─ RewriteCond %{HTTPS} off
│  ├─ RewriteRule (HTTPS)
│  ├─ Cache config (mod_expires)
│  └─ Compression (mod_deflate)
│
├─ index.php
├─ admin/
│  │
│  ├─ .htaccess                 ← Config ADMIN (sobrescreve raiz)
│  │  ├─ AuthType Basic         (autenticação)
│  │  ├─ Require valid-user
│  │  └─ [NÃO herda rewrite da raiz?]
│  │
│  └─ dashboard.php
│
├─ api/
│  │
│  ├─ .htaccess                 ← Config API
│  │  ├─ Header set X-API-Version
│  │  ├─ RewriteRule (API versioning)
│  │  └─ Herda HTTPS de raiz ✅
│  │
│  ├─ v1/
│  │  ├─ .htaccess              ← Config V1 (específico)
│  │  │  └─ RewriteRule (específico para v1)
│  │  │
│  │  └─ controller.php
│  │
│  └─ v2/
│     └─ .htaccess              ← Config V2 (específico)
│
└─ uploads/
   │
   ├─ .htaccess                 ← Config UPLOADS
   │  ├─ php_flag engine off    (bloqueia PHP)
   │  ├─ Deny from all          (bloqueia acesso direto)
   │  └─ Herda HTTPS? Depende!
   │
   └─ profile_pic_123.jpg
```

## 4.4 - Herança e Sobrescrita

```
REGRA: .htaccess em SUBFOLDER sobrescreve .htaccess em PARENT

EXEMPLO:

/public_html/.htaccess
├─ RewriteEngine On
├─ RewriteCond %{HTTPS} off
├─ RewriteRule ... (HTTPS)
└─ Cache config (imagens 1 ano)

Requisição: /usuarios/123
└─ .htaccess raiz aplica
   └─ HTTPS + Cache + outros


/public_html/admin/.htaccess
├─ AuthType Basic              ← NOVO (não em raiz)
├─ Require valid-user          ← NOVO
└─ [NÃO tem RewriteEngine On!] ← PROBLEMA?

Requisição: /admin/dashboard.php
└─ .htaccess admin aplica
   └─ ✅ Autenticação
   └─ ❓ HTTPS? (herda da raiz?)
   └─ ❓ Cache? (herda da raiz?)

REGRA DE HERANÇA:
├─ Se admin/.htaccess NOT tem RewriteEngine On
│  └─ Apache NÃO herda de raiz automaticamente
│  └─ Você precisa adicionar em admin/.htaccess também
│
└─ Se admin/.htaccess tem "RewriteEngine Off"
   └─ DESATIVA rewrite (sobrescreve raiz)
```

## 4.5 - Ordem de Processamento

```
REQUISIÇÃO: GET /admin/dashboard.php

PASSO 1: Apache processa .htaccess da RAIZ
/public_html/.htaccess
├─ RewriteEngine On
├─ RewriteCond %{HTTPS} off
└─ RewriteRule ... (aplica se match)

PASSO 2: Apache processa .htaccess do ADMIN
/public_html/admin/.htaccess
├─ Se existe: processa (sobrescreve raiz)
├─ Se não existe: continua com raiz
└─ Resultado final: fusão das duas configs

PASSO 3: PHP executa

EXEMPLO PRÁTICO:

/public_html/.htaccess:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
Header set Cache-Control "public, max-age=86400"
```

/public_html/admin/.htaccess:
```apache
AuthType Basic
AuthName "Admin"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

Requisição: /admin/dashboard.php

Resultado FINAL (fusão):
- ✅ RewriteEngine On (da raiz)
- ✅ HTTPS check (da raiz)
- ✅ Cache header (da raiz)
- ✅ Autenticação (do admin)

TODOS OS EFEITOS se aplicam!
```

---

# CAPÍTULO 5: HIERARQUIA E PRECEDÊNCIA

## 5.1 - Ordem de Aplicação

```
QUANDO REQUISIÇÃO CHEGA:

TEMPO 1: BOOT (Uma vez)
├─ Apache carrega httpd.conf
├─ Carrega todos os módulos
├─ Processa Include /etc/apache2/conf.d/*.conf
└─ Lê todos os vhost.conf

TEMPO 2: REQUISIÇÃO (A cada acesso)
├─ Apache identifica VirtualHost (pelo Host header)
├─ Aplica regras do vhost.conf específico
├─ Apache entra no DocumentRoot
├─ Processa .htaccess da raiz
├─ Navega para subfolder (ex: /admin)
├─ Processa .htaccess da subfolder (se existe)
└─ Navega para sub-subfolder (ex: /admin/users)
   └─ Processa .htaccess de sub-subfolder (se existe)
└─ PHP executa com configurações finais
```

## 5.2 - Precedência (Quem Vence?)

```
HIERARQUIA DE PRECEDÊNCIA (do mais fraco para o mais forte):

1. httpd.conf (GLOBAL)
   └─ Aplicado PRIMEIRA VEZ
   └─ Pode ser SOBRESCRITO por qualquer coisa abaixo

2. vhost.conf (DOMÍNIO)
   └─ Sobrescreve httpd.conf PARA ESTE DOMÍNIO
   └─ Pode ser SOBRESCRITO por .htaccess

3. .htaccess RAIZ (/public_html/.htaccess)
   └─ Sobrescreve vhost.conf
   └─ Pode ser SOBRESCRITO por .htaccess subfolder

4. .htaccess SUBFOLDER (/public_html/admin/.htaccess)
   └─ Sobrescreve .htaccess raiz
   └─ MAIS FORTE (winner!)

┌──────────────────────────────────────────────┐
│ RESUMO:                                       │
│ httpd.conf < vhost.conf < .htaccess < .htaccess subfolder │
│                                               │
│ .htaccess subfolder = mais específico = vence │
└──────────────────────────────────────────────┘
```

## 5.3 - Exemplo Prático de Precedência

```
CENÁRIO: Seu site ipaccontabilidade.com.br

httpd.conf (GLOBAL):
```apache
<Directory "/var/www/html">
    Options Indexes
    AllowOverride All
</Directory>
```

vhost.conf (DOMÍNIO):
```apache
<VirtualHost *:80>
    ServerName ipaccontabilidade.com.br
    DocumentRoot /home/ipacuser/public_html
    
    <Directory /home/ipacuser/public_html>
        Options FollowSymLinks          # ← Sobrescreve Indexes
        AllowOverride All
    </Directory>
</VirtualHost>
```

/public_html/.htaccess (RAIZ):
```apache
Options -Indexes                        # ← Remove listagem
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://... [L,R=301]
```

/public_html/admin/.htaccess (ADMIN):
```apache
# NÃO repete Options (herda da raiz)
# NÃO repete RewriteEngine (herda da raiz)

# SÓ adiciona específico de admin
AuthType Basic
AuthName "Admin"
AuthUserFile /home/ipacuser/public_html/.htpasswd
Require valid-user
```

RESULTADO FINAL para /admin/dashboard.php:
├─ Options: -Indexes (da raiz)
├─ RewriteEngine: On (da raiz)
├─ HTTPS redirect: Ativo (da raiz)
├─ AuthType: Basic (do admin)
└─ Autenticação: Necessária (do admin)
```

---

# CAPÍTULO 6: MÚLTIPLOS .htaccess

## 6.1 - Quando Usar Múltiplos .htaccess?

```
CASO 1: Diferentes políticas por área
├─ /admin → Autenticação + IP restriction
├─ /api → Headers específicos, CORS
├─ /uploads → Bloquear execução PHP
└─ / → Rewrite, cache, compressão

CASO 2: Diferentes projetos no mesmo servidor
├─ /projeto1/ → Project 1 settings
├─ /projeto2/ → Project 2 settings
└─ /projeto3/ → Project 3 settings

CASO 3: Produção + Staging
├─ /public_html/ → Produção (restritivo)
├─ /public_html_staging/ → Staging (permissivo)
└─ /public_html_dev/ → Desenvolvimento (debug)

CASO 4: Framework com sub-rotas
├─ / → Rewrite tudo para index.php
├─ /admin → Autenticação admin
├─ /api/v1 → Headers API v1
└─ /api/v2 → Headers API v2
```

## 6.2 - Estrutura Recomendada

```
/home/ipacuser/public_html/
│
├─ .htaccess                    ← RAIZ (setups básicos)
│  ├─ RewriteEngine On
│  ├─ HTTPS enforcement
│  ├─ Cache headers
│  └─ Compression
│
├─ index.php                    ← Framework entry point
│
├─ admin/
│  ├─ .htaccess                 ← ADMIN (segurança)
│  │  ├─ AuthType Basic         (autenticação)
│  │  └─ (herda rewrite da raiz)
│  │
│  ├─ index.php
│  ├─ dashboard/
│  │  └─ controller.php
│  └─ users/
│     └─ controller.php
│
├─ api/
│  ├─ .htaccess                 ← API (versioning)
│  │  ├─ Header set X-API-Version 1
│  │  ├─ RewriteRule (API routes)
│  │  └─ (herda HTTPS da raiz)
│  │
│  ├─ index.php
│  ├─ v1/
│  │  ├─ .htaccess              ← V1 (específico)
│  │  │  └─ RewriteRule ^(.*)$ v1/controller.php [L]
│  │  │
│  │  └─ controller.php
│  │
│  └─ v2/
│     ├─ .htaccess              ← V2 (específico)
│     │  └─ RewriteRule ^(.*)$ v2/controller.php [L]
│     │
│     └─ controller.php
│
├─ uploads/
│  ├─ .htaccess                 ← UPLOADS (segurança)
│  │  ├─ php_flag engine off    (bloqueia PHP)
│  │  ├─ Deny from all .htaccess (previne acesso)
│  │  └─ (herda HTTPS da raiz)
│  │
│  ├─ logo.png
│  └─ documents/
│     └─ contract.pdf
│
└─ includes/
   ├─ .htaccess                 ← INCLUDES (acesso bloqueado)
   │  └─ Deny from all
   │
   ├─ database.php
   └─ config.php
```

## 6.3 - Exemplo Real: 3 Domínios no Mesmo Servidor

```
CENÁRIO: VPS com 3 domínios

/home/ipacuser/
│
├─ public_html/                 ← ipaccontabilidade.com.br
│  │
│  ├─ .htaccess                 ← Produção (restritivo)
│  │  ├─ RewriteEngine On
│  │  ├─ HTTPS enforcement
│  │  ├─ Cache control
│  │  ├─ Security headers
│  │  └─ AllowOverride All
│  │
│  ├─ index.php (Laravel framework)
│  └─ admin/
│     └─ .htaccess (autenticação)
│
├─ public_html_api/            ← api.ipaccontabilidade.com.br
│  │
│  ├─ .htaccess                 ← API (diferente)
│  │  ├─ Header set X-API-Version 2
│  │  ├─ Header set Content-Type application/json
│  │  ├─ CORS headers
│  │  ├─ API rewrite rules
│  │  └─ AllowOverride All
│  │
│  ├─ index.php (API entry)
│  └─ v2/
│     └─ .htaccess (v2 específico)
│
└─ public_html_staging/        ← staging.ipaccontabilidade.com.br
   │
   ├─ .htaccess                 ← Staging (permissivo)
   │  ├─ Debug mode
   │  ├─ Error display
   │  ├─ Reduced cache
   │  └─ AllowOverride All
   │
   ├─ index.php (cópia raiz)
   └─ admin/
      └─ .htaccess (menos restritivo)


COMO FUNCIONA:

Requisição: https://api.ipaccontabilidade.com.br/v2/usuarios/123

1. Apache identifica VirtualHost
   └─ Procura: api.ipaccontabilidade.com.br
   └─ Encontra: vhost_002_api.ipaccontabilidade.com.br.conf
   └─ DocumentRoot: /home/ipacuser/public_html_api

2. Processa .htaccess
   └─ Lê: /home/ipacuser/public_html_api/.htaccess
   └─ Aplica: API headers, versioning
   └─ (NÃO aplica .htaccess do public_html produção!)

3. Navega para /v2
   └─ Lê: /home/ipacuser/public_html_api/v2/.htaccess
   └─ Sobrescreve: rewrite rules para v2

4. PHP executa
   └─ Arquivo: /home/ipacuser/public_html_api/v2/controller.php
   └─ Com todas as configs de: .htaccess raiz + /v2/.htaccess
```

---

# CAPÍTULO 7: ISOLAMENTO ENTRE DOMÍNIOS

## 7.1 - Como Apache Isola Domínios

```
IMPORTANTE: Cada VirtualHost é isolado

VirtualHost 1: ipaccontabilidade.com.br
└─ DocumentRoot: /home/ipacuser/public_html
└─ AllowOverride: All
└─ .htaccess é LIDO DAQUI

VirtualHost 2: api.ipaccontabilidade.com.br
└─ DocumentRoot: /home/ipacuser/public_html_api
└─ AllowOverride: All
└─ .htaccess é LIDO DAQUI (diferente!)

VirtualHost 3: staging.ipaccontabilidade.com.br
└─ DocumentRoot: /home/ipacuser/public_html_staging
└─ AllowOverride: All
└─ .htaccess é LIDO DAQUI (diferente!)

CONSEQUÊNCIA:
.htaccess do public_html NÃO afeta api.ipaccontabilidade.com.br
Cada domínio tem seu próprio .htaccess
```

## 7.2 - Exemplo: Evitar Contaminação Entre Sites

```
CENÁRIO PERIGOSO (SEM ISOLAMENTO):

/home/ipacuser/public_html/.htaccess:
```apache
RewriteRule ^(.*)$ client_site_A.php [L]
# Redireciona TUDO para client_site_A.php
```

/home/ipacuser/public_html_api/.htaccess:
# Não tem .htaccess, herda de pai?
# NÃO! Cada VirtualHost ignora de outro


RESULTADO:
✅ api.ipaccontabilidade.com.br: Funciona normalmente
✅ ipaccontabilidade.com.br: Redireciona para client_site_A.php

SEM CONTAMINAÇÃO! 🎉

RESUMO:
Apache cria "sandbox" para cada VirtualHost
.htaccess de A não afeta B
Isolamento é automático (desde que DocumentRoot diferente)
```

---

# CAPÍTULO 8: PERMISSION HIERARCHY

## 8.1 - AllowOverride Levels

```
DIRETIVA CRÍTICA: AllowOverride

Controla quais diretivas do .htaccess são permitidas

VALOR: All
└─ Permite TUDO (.htaccess é poderoso)
└─ Recomendado para sua aplicação

VALOR: FileInfo
└─ Permite apenas:
   ├─ AddType, AddEncoding
   ├─ DefaultLanguage, Error Document
   └─ Header set (mod_headers)
└─ Recomendado se quer restringir

VALOR: AuthConfig
└─ Permite apenas:
   ├─ AuthType, AuthName
   ├─ AuthUserFile, Require
   └─ Autenticação em geral
└─ Recomendado para áreas de admin

VALOR: Indexes
└─ Permite apenas Options (especificamente -Indexes)
└─ Recomendado para áreas de upload

VALOR: Limit
└─ Permite apenas Order, Deny, Allow
└─ Recomendado para IP restriction

VALOR: None
└─ BLOQUEIA .htaccess COMPLETAMENTE
└─ .htaccess é IGNORADO (silenciosamente)
└─ NÃO RECOMENDADO
```

## 8.2 - Exemplo: Configurar AllowOverride por Pasta

```apache
# /etc/apache2/conf.d/vhost_ipac.conf

<VirtualHost *:80>
    ServerName ipaccontabilidade.com.br
    DocumentRoot /home/ipacuser/public_html
    
    # ROOT: Permite tudo
    <Directory /home/ipacuser/public_html>
        AllowOverride All
    </Directory>
    
    # ADMIN: Apenas autenticação
    <Directory /home/ipacuser/public_html/admin>
        AllowOverride AuthConfig
        # .htaccess admin pode ter AuthType, Require
        # .htaccess admin NÃO pode ter RewriteRule
    </Directory>
    
    # UPLOADS: Bloqueia .htaccess
    <Directory /home/ipacuser/public_html/uploads>
        AllowOverride None
        # .htaccess nesta pasta é IGNORADO
        # Razão: segurança (impedir upload de .htaccess malicioso)
    </Directory>
    
    # API: Apenas headers
    <Directory /home/ipacuser/public_html/api>
        AllowOverride FileInfo
        # .htaccess api pode ter Header set
        # .htaccess api NÃO pode ter RewriteRule
    </Directory>
</VirtualHost>
```

---

# CAPÍTULO 9: FLUXO COMPLETO (VISÃO INTEGRADA)

## 9.1 - Exemplo Real Completo

```
PROJETO: IPAC Contabilidade

ESTRUTURA:
- Domínio: ipaccontabilidade.com.br
- Framework: Laravel
- VPS com cPanel/WHM
- 3 áreas: Pública, Admin, API


ARQUIVO 1: /etc/apache2/httpd.conf (GLOBAL)
```apache
# Carregamentos de módulos
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule php_module modules/mod_php.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule expires_module modules/mod_expires.so

# Include vhosts
Include /etc/apache2/conf.d/*.conf
```

ARQUIVO 2: /etc/apache2/conf.d/vhost_001_ipac.conf (DOMÍNIO)
```apache
<VirtualHost *:443>
    ServerName ipaccontabilidade.com.br
    ServerAlias www.ipaccontabilidade.com.br
    DocumentRoot /home/ipacuser/public_html
    
    <Directory /home/ipacuser/public_html>
        AllowOverride All        # ← PERMITE .htaccess
        Options FollowSymLinks
        Require all granted
    </Directory>
    
    # SSL
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/ipaccontabilidade.com.br.crt
    
    # Logs
    ErrorLog /home/ipacuser/logs/error_log
    CustomLog /home/ipacuser/logs/access_log combined
</VirtualHost>
```

ARQUIVO 3: /home/ipacuser/public_html/.htaccess (RAIZ)
```apache
# Rewrite para Laravel
RewriteEngine On
RewriteBase /

# HTTPS (se não tiver SSL forçado no vhost)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Cache para imagens/CSS/JS
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
</IfModule>

# Rewrite para Laravel: tudo vai para public/index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
```

ARQUIVO 4: /home/ipacuser/public_html/admin/.htaccess (ADMIN)
```apache
# Herda rewrite da raiz
# Adiciona autenticação admin

AuthType Basic
AuthName "Área Administrativa"
AuthUserFile /home/ipacuser/public_html/.htpasswd
Require valid-user

# Opcional: bloquear IPs não autorizados
# SetEnvIF REMOTE_ADDR ^192.168.1. trusted
# Order Deny,Allow
# Deny from all
# Allow from env=trusted
```

ARQUIVO 5: /home/ipacuser/public_html/api/.htaccess (API)
```apache
# Herda rewrite da raiz
# Adiciona headers específicos de API

# Headers API
<IfModule mod_headers.c>
    Header set X-API-Version 2.0
    Header set Content-Type application/json
    Header set Access-Control-Allow-Origin "*"
</IfModule>

# Rewrite específico para API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /api/index.php?endpoint=$1 [QSA,L]
```

ARQUIVO 6: /home/ipacuser/public_html/uploads/.htaccess (UPLOADS)
```apache
# Bloqueia execução de PHP
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>

# Bloqueia acesso direto a .htaccess
<Files ".htaccess">
    Deny from all
</Files>

# Bloqueia extensões perigosas
<FilesMatch "\.(php|php3|php4|php5|phtml|exe|com|bat)$">
    Deny from all
</FilesMatch>
```

REQUISIÇÃO: https://ipaccontabilidade.com.br/usuarios/123

FLUXO:
1. Cliente → HTTPS (port 443)
2. Apache identifica VirtualHost: ipaccontabilidade.com.br ✅
3. Aplica regras do vhost.conf
4. Entra em DocumentRoot: /home/ipacuser/public_html
5. Lê .htaccess raiz
   ├─ RewriteEngine On
   ├─ Já é HTTPS (não redireciona)
   ├─ Arquivo /usuarios/123 não existe (!-f)
   ├─ Diretório /usuarios não existe (!-d)
   └─ Reescreve para: index.php?rota=/usuarios/123
6. PHP executa:
   ├─ Laravel framework intercepta
   ├─ Router decodifica: rota = /usuarios/123
   ├─ Mapeia para: UsuarioController@show(123)
   └─ Retorna JSON/HTML
7. Headers de cache aplicados (imagem 1 ano)
8. Cliente recebe resposta


REQUISIÇÃO: https://ipaccontabilidade.com.br/admin/dashboard

FLUXO:
1. Cliente → HTTPS (port 443)
2. Apache identifica VirtualHost
3. Entra em DocumentRoot
4. Lê .htaccess raiz (rewrite, cache)
5. Navega para /admin
6. Lê .htaccess admin (NOVO)
   ├─ Exibe popup: "Admin Area"
   ├─ Solicita credenciais
   ├─ Valida contra .htpasswd
   └─ Se ok: continua
7. Apache aplica AMBAS configs:
   ├─ Rewrite da raiz ✅
   ├─ Cache da raiz ✅
   ├─ Autenticação do admin ✅
8. PHP executa com todas as configs
9. Cliente vê dashboard (após autenticação)


REQUISIÇÃO: https://ipaccontabilidade.com.br/api/v2/usuarios/123

FLUXO:
1. Cliente → HTTPS
2. Apache identifica VirtualHost
3. Entra em DocumentRoot
4. Lê .htaccess raiz (rewrite, cache)
5. Navega para /api
6. Lê .htaccess api (NOVO)
   ├─ Header set X-API-Version 2.0
   ├─ Header set Content-Type application/json
   ├─ Rewrite específico para API
7. Navega para /v2
   ├─ Se existe /api/v2/.htaccess: lê
   ├─ Se não existe: usa api/.htaccess
8. Apache aplica TODAS configs:
   ├─ Rewrite da raiz ✅
   ├─ Headers da raiz ✅
   ├─ Headers do api ✅
   ├─ Rewrite do api ✅
9. PHP retorna JSON com headers corretos
10. Cliente recebe: HTTP 200 + JSON


REQUISIÇÃO: GET /admin/uploads/logo.png

FLUXO:
1. Apache identifica VirtualHost
2. Entra em DocumentRoot
3. Lê .htaccess raiz
4. Navega para /admin
5. Lê .htaccess admin (autenticação)
   └─ Exige login
6. Navega para /uploads
7. Lê .htaccess uploads
   ├─ php_flag engine off (PHP bloqueado)
   ├─ FilesMatch .php: Deny (PHP bloqueado)
   └─ (Herança de admin? Depende)
8. Arquivo solicitado: logo.png
   ├─ É imagem (não PHP)
   ├─ AllowOverride não bloqueia imagens
   ├─ Apache serve arquivo
9. Cliente recebe: imagem PNG

NOTA: .htaccess uploads sobrescreve admin
```

---

# CAPÍTULO 10: BEST PRACTICES

## 10.1 - Quando Usar Cada Um

| Situação | Use | Razão |
|----------|-----|-------|
| **Módulos globais** | httpd.conf | Afeta Apache inteiro |
| **HTTPS obrigatório** | vhost.conf | Mais eficiente (boot) |
| **Rewrite de URLs** | .htaccess | Específico por site |
| **Autenticação local** | .htaccess | Pasta específica |
| **Cache por tipo MIME** | .htaccess | Granular |
| **Bloquear IPs** | vhost.conf ou .htaccess | Ambos funcionam |
| **Diferentes sites** | Vhosts separados | Isolamento |
| **Admin vs público** | .htaccess diferente | Segurança |

## 10.2 - Erros Comuns

```
❌ ERRO 1: Colocar config de site em httpd.conf
└─ Causa: afeta TODOS os sites
└─ Solução: Use vhost.conf

❌ ERRO 2: AllowOverride None em DocumentRoot
└─ Causa: .htaccess não funciona
└─ Solução: Use AllowOverride All

❌ ERRO 3: .htaccess em uploads/
└─ Causa: upload malicioso pode executar PHP
└─ Solução: php_flag engine off ou AllowOverride None

❌ ERRO 4: Múltiplos .htaccess sem organização
└─ Causa: difícil manter/debugar
└─ Solução: documente cada um

❌ ERRO 5: Herança assumida (RewriteEngine)
└─ Causa: subfolder não herda RewriteEngine
└─ Solução: declare em cada .htaccess que precisa

✅ BOM: AllowOverride All na raiz, restritivo em subfolders
✅ BOM: Documentar cada .htaccess
✅ BOM: .htaccess em /uploads com php_flag engine off
✅ BOM: Testes de isolamento entre vhosts
```

## 10.3 - Checklist para Múltiplos .htaccess

```
ANTES DE CRIAR:
[ ] Documentar objetivo de cada .htaccess
[ ] Identificar herança (o que herda de pai?)
[ ] Teste cada uma isoladamente
[ ] Teste combinações (raiz + subfolder)

DURANTE CRIAÇÃO:
[ ] Declare RewriteEngine em cada .htaccess que precisa
[ ] Não assuma herança de módulos
[ ] Use comentários (# Herda de raiz?)
[ ] Teste após cada adição

APÓS CRIAÇÃO:
[ ] Verifique error_log
[ ] Teste todas as rotas
[ ] Teste isolamento entre domínios
[ ] Documente em wiki/README
```

---

# CONCLUSÃO

## Resumo Visual

```
┌─ httpd.conf (GLOBAL)
│  └─ "Quando Apache inicia, carregue módulos"
│
├─ vhost.conf (DOMÍNIO)
│  └─ "Quando alguém acessa ipaccontabilidade.com, use esta config"
│
└─ .htaccess (PASTA)
   └─ "Quando alguém acessa /admin, aplique estas regras"

HIERARQUIA: httpd < vhost < .htaccess < .htaccess subfolder

ISOLAMENTO: Cada vhost tem seu próprio DocumentRoot e .htaccess
            Não há contaminação entre domínios
```

## Quando Entender Tudo Funciona

```
✅ HTTPS funciona: vhost.conf (SSL) + .htaccess (redirect)
✅ Admin restrito: .htaccess raiz (herança) + .htaccess admin (auth)
✅ API isolada: vhost API + .htaccess API (headers)
✅ Uploads seguro: php_flag engine off + Deny execução
✅ Cache eficiente: .htaccess + Cache-Control headers
✅ Sem conflitos: cada vhost + domínio próprio
```

---

**Você agora entende a hierarquia completa de Apache!** 🎯

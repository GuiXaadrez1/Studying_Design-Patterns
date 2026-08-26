# ARQUITETURA APACHE: COMPONENTES E RELAÇÕES

**Objetivo:** Entender de forma estruturada o que é cada componente, para que serve e como funcionam juntos.

---

# PARTE 1: O QUE É Apache?

Apache é um serviço (daemon) que executa no servidor VPS/Dedicado. Sua função é receber requisições HTTP de clientes (navegadores) e retornar respostas (páginas, arquivos, APIs).

```
[Cliente]  ←→  [Apache Daemon]  ←→  [PHP/Apps]
           HTTP/HTTPS               no servidor
```

Para que Apache funcione corretamente, ele precisa saber:
- Quais módulos carregar (mod_rewrite, mod_php, etc)
- Quais domínios aceitar
- Onde estão os arquivos de cada domínio
- Como reescrever URLs, cachear, autenticar, etc

Essas informações estão em **3 níveis de configuração** que se relacionam hierarquicamente.

---

# PARTE 2: httpd.conf (Configuração Global do Apache)

## O que é?

`httpd.conf` é o arquivo principal de configuração do Apache. Ele define como o Apache **funciona como serviço** no seu sistema operacional.

**Localização:** `/etc/apache2/httpd.conf`

## Para que serve?

Controla aspectos **globais** do Apache que afetam todo o servidor:

1. **Carregamento de módulos**
   - Módulos são extensões do Apache (como plugins)
   - Exemplo: mod_rewrite (reescrita de URL), mod_ssl (HTTPS), mod_php (suporte PHP)
   - Sem carregar módulos, funcionalidades não existem

2. **Definição de portas**
   - Apache escuta portas 80 (HTTP) e 443 (HTTPS)
   - Definido em httpd.conf via `Listen 80` e `Listen 443`

3. **Paths globais**
   - Onde estão os executáveis do Apache
   - Onde estão os módulos compilados
   - Onde estão os logs globais

4. **Includes (importação) de outros arquivos**
   - httpd.conf não define sites individuais
   - Ele **inclui** outros arquivos: `Include /etc/apache2/conf.d/*.conf`
   - Esses includes trazem as configurações de vhost.conf

## Quando é processado?

**Uma única vez:** Quando Apache inicia.

```
Você digita:      sudo systemctl start apache2
                            ↓
Apache lê:        httpd.conf
                  └─ Carrega módulos
                  └─ Define portas
                  └─ Processa Includes
                            ↓
Apache sobe       e aguarda requisições HTTP
```

Se você mudar httpd.conf, precisa fazer restart:
```bash
sudo systemctl restart apache2
```

## O que NÃO faz?

- ❌ Não define qual domínio responde
- ❌ Não define onde estão os arquivos de cada site
- ❌ Não define reescrita de URLs
- ❌ Não define autenticação
- ❌ Não define cache

Isso tudo é feito em **nível inferior** (vhost.conf e .htaccess).

## Exemplo de httpd.conf (simplificado)

```apache
# Define onde Apache está instalado
ServerRoot "/etc/apache2"

# Define portas que Apache escuta
Listen 80
Listen 443

# ===== CARREGAMENTO DE MÓDULOS =====
# Sem esses módulos, funcionalidades não existem

# Módulo de reescrita de URL (necessário para .htaccess com RewriteRule)
LoadModule rewrite_module modules/mod_rewrite.so

# Módulo de SSL/HTTPS (necessário para domínios HTTPS)
LoadModule ssl_module modules/mod_ssl.so

# Módulo de PHP (necessário para executar scripts .php)
LoadModule php7_module modules/libphp7.so

# Módulo de compressão (necessário para gzip)
LoadModule deflate_module modules/mod_deflate.so

# ===== INCLUDES (IMPORTA OUTROS ARQUIVOS) =====
# Inclui vhost.conf e outras configurações

Include /etc/apache2/conf.d/*.conf
# └─ Traz vhost_001_ipac.conf, vhost_002_api.conf, etc
```

---

# PARTE 3: vhost.conf (Configuração de Domínio)

## O que é?

`vhost.conf` é um arquivo de configuração específico de **um domínio**. Ele define como Apache deve responder quando alguém acessa esse domínio.

**Localização:** `/etc/apache2/conf.d/vhost_001_ipaccontabilidade.com.br.conf`

**Criação:** Gerado automaticamente pelo cPanel quando você adiciona um domínio.

## Para que serve?

Define aspectos específicos de **um domínio**:

1. **Qual domínio responde**
   - `ServerName ipaccontabilidade.com.br`
   - Quando cliente acessa ipaccontabilidade.com.br, este vhost responde

2. **Onde estão os arquivos do site**
   - `DocumentRoot /home/ipacuser/public_html`
   - Apache sabe onde procurar por index.php, css, js, etc

3. **Configuração SSL/HTTPS**
   - Qual certificado usar
   - Qual chave privada usar
   - Ativar ou não HTTPS

4. **Permissões para .htaccess**
   - `AllowOverride All` ou `AllowOverride None`
   - Controla se .htaccess funciona ou é ignorado

5. **Logs específicos do domínio**
   - Access log: registra todas as requisições
   - Error log: registra erros do site

6. **Configurações de diretório**
   - Quais opções estão permitidas
   - Permissões de execução, listagem, etc

## Quando é processado?

**Uma única vez:** Quando Apache inicia (via Include no httpd.conf).

Se você mudar vhost.conf, precisa fazer restart:
```bash
sudo systemctl restart apache2
```

## Como se relaciona com httpd.conf?

```
httpd.conf diz:
  "Inclua todos os arquivos em /etc/apache2/conf.d/*.conf"

Apache então lê:
  vhost_001_ipac.conf
  vhost_002_api.conf
  vhost_003_staging.conf
  ... (todos os vhosts)
```

## Como se relaciona com .htaccess?

vhost.conf **autoriza ou bloqueia** .htaccess através da diretiva `AllowOverride`:

```apache
<Directory /home/ipacuser/public_html>
    AllowOverride All    # ← .htaccess pode fazer tudo
</Directory>
```

Se `AllowOverride None`, .htaccess é completamente ignorado (bloqueado).

## Isolamento entre vhosts

Cada vhost é **independente e isolado**:

```
VirtualHost 1: ipaccontabilidade.com.br
├─ Arquivo: vhost_001_ipac.conf
├─ DocumentRoot: /home/ipacuser/public_html
└─ .htaccess: /home/ipacuser/public_html/.htaccess
   └─ Afeta APENAS este domínio

VirtualHost 2: api.ipaccontabilidade.com.br
├─ Arquivo: vhost_002_api.conf (ARQUIVO DIFERENTE!)
├─ DocumentRoot: /home/ipacuser/public_html_api (PASTA DIFERENTE!)
└─ .htaccess: /home/ipacuser/public_html_api/.htaccess (ARQUIVO DIFERENTE!)
   └─ Afeta APENAS este domínio
   └─ NÃO interfere com domínio 1
```

Se mudar .htaccess do domínio 1, domínio 2 não é afetado.

## Exemplo de vhost.conf (real)

```apache
# ============================================
# VirtualHost para ipaccontabilidade.com.br (port 443 = HTTPS)
# ============================================

<VirtualHost *:443>
    # Identificação do domínio
    ServerName ipaccontabilidade.com.br
    ServerAlias www.ipaccontabilidade.com.br
    
    # Onde estão os arquivos do site
    DocumentRoot /home/ipacuser/public_html
    
    # Qual usuário executa este VirtualHost
    User ipacuser
    Group ipacuser
    
    # ===== PERMISSÕES DE DIRETÓRIO =====
    # Define se .htaccess funciona e quais opções estão permitidas
    <Directory /home/ipacuser/public_html>
        # AllowOverride: controla se .htaccess é lido
        # All = .htaccess é processado
        # None = .htaccess é ignorado
        AllowOverride All
        
        # Options: permissões gerais
        # FollowSymLinks = permite links simbólicos
        # Indexes = permite listar diretório (removido com -Indexes)
        Options FollowSymLinks
        
        # Require: quem pode acessar?
        # all granted = todos podem
        Require all granted
    </Directory>
    
    # ===== CONFIGURAÇÃO SSL/HTTPS =====
    # Ativa HTTPS para este domínio
    SSLEngine on
    
    # Certificado do domínio (público)
    SSLCertificateFile /etc/ssl/certs/ipaccontabilidade.com.br.crt
    
    # Chave privada do domínio (secreto!)
    SSLCertificateKeyFile /etc/ssl/private/ipaccontabilidade.com.br.key
    
    # Certificado intermediário (se houver)
    SSLCertificateChainFile /etc/ssl/certs/ipaccontabilidade.com.br.ca
    
    # ===== LOGS =====
    # Onde registrar requisições deste domínio
    CustomLog /home/ipacuser/logs/access_log combined
    
    # Onde registrar erros deste domínio
    ErrorLog /home/ipacuser/logs/error_log
</VirtualHost>

# ============================================
# VirtualHost para ipaccontabilidade.com.br (port 80 = HTTP)
# Redireciona HTTP para HTTPS
# ============================================

<VirtualHost *:80>
    ServerName ipaccontabilidade.com.br
    ServerAlias www.ipaccontabilidade.com.br
    
    # Redireciona todas requisições para HTTPS
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>
```

---

# PARTE 4: .htaccess (Configuração de Pasta)

## O que é?

`.htaccess` é um arquivo que você cria em pastas específicas do seu site. Ele define regras para aquela pasta e suas subpastas.

**Localização:** `{DocumentRoot}/.htaccess` (você coloca onde quiser)

Exemplo: `/home/ipacuser/public_html/.htaccess`

## Para que serve?

Define aspectos **específicos de pastas**:

1. **Reescrita de URLs (mod_rewrite)**
   - Transforma `/usuarios/123` internamente em `/usuarios.php?id=123`
   - Cliente vê URL bonita, PHP recebe parâmetros

2. **Headers HTTP customizados (mod_headers)**
   - Define como cache funciona (Cache-Control)
   - Define segurança (X-Frame-Options, X-Content-Type-Options)
   - Define CORS para APIs

3. **Compressão (mod_deflate)**
   - Comprime HTML, CSS, JS com gzip
   - Reduz tamanho de arquivos (~70% menor)

4. **Cache do navegador (mod_expires)**
   - Define quanto tempo navegador guarda arquivo local
   - Imagens por 1 ano, CSS por 1 mês, HTML por 1 dia

5. **Autenticação (mod_auth)**
   - Exige username/password para acessar área
   - Usado em /admin, /painel, etc

6. **Bloqueio de acessos**
   - Bloqueia execução de PHP em /uploads
   - Bloqueia acesso direto a /includes
   - Bloqueia IPs específicos

## Quando é processado?

**A cada requisição HTTP:** Diferente de httpd.conf e vhost.conf que são lidos uma vez no boot.

```
Cliente faz requisição: GET /usuarios/123
                            ↓
Apache processa vhost.conf (já em memória, do boot)
                            ↓
Apache entra em DocumentRoot
                            ↓
Apache procura: .htaccess (LIDO AGORA, durante requisição)
                            ↓
Apache aplica: Regras do .htaccess
                            ↓
PHP executa com configurações aplicadas
```

**Consequência:** Você pode mudar .htaccess e não precisa fazer restart. Efeito imediato!

## Como se relaciona com vhost.conf?

vhost.conf decide **se .htaccess funciona**:

```apache
vhost.conf diz:
<Directory /home/ipacuser/public_html>
    AllowOverride All    # ← .htaccess FUNCIONA
</Directory>

ou

<Directory /home/ipacuser/public_html/uploads>
    AllowOverride None   # ← .htaccess NÃO FUNCIONA (ignorado)
</Directory>
```

Se AllowOverride for None, mesmo que você coloque .htaccess lá, é completamente ignorado.

## Herança entre .htaccess

Você pode colocar .htaccess em múltiplas pastas. A hierarquia é:

```
/public_html/.htaccess (raiz)
├─ Aplica a tudo

/public_html/admin/.htaccess (subfolder)
├─ Herda regras da raiz
├─ Adiciona suas próprias regras
└─ Se houver conflito, subfolder sobrescreve raiz

/public_html/admin/users/.htaccess (sub-subfolder)
├─ Herda de /admin
├─ Herda de raiz
├─ Adiciona suas próprias regras
└─ Sobrescreve ambas se houver conflito
```

## Exemplo de .htaccess (real)

```apache
# ============================================
# Configuração de raiz (/public_html/)
# ============================================

# Ativa módulo de reescrita de URL
RewriteEngine On
RewriteBase /

# ===== FORÇAR HTTPS =====
# Se cliente acessa HTTP, redireciona para HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ===== CACHE PARA IMAGENS =====
# Navegador guarda imagem por 1 ano (não baixa novamente)
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
</IfModule>

# ===== COMPRESSÃO COM GZIP =====
# Comprime HTML e CSS antes de enviar (70% menor)
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css
</IfModule>

# ===== REWRITE PARA FRAMEWORK =====
# Qualquer URL desconhecida vai para index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
```

```apache
# ============================================
# Configuração de /admin
# ============================================
# Nota: Herda RewriteEngine, HTTPS, cache de raiz
# Adiciona: Autenticação

AuthType Basic
AuthName "Área Administrativa"
AuthUserFile /home/ipacuser/public_html/.htpasswd
Require valid-user
# └─ Exige login para acessar /admin
```

```apache
# ============================================
# Configuração de /uploads
# ============================================
# Bloqueia execução de PHP nesta pasta

php_flag engine off
# └─ Se alguém fizer upload de .php, não executa

<FilesMatch "\.php$">
    Deny from all
</FilesMatch>
# └─ Também bloqueia diretamente
```

---

# PARTE 5: RELAÇÃO ENTRE OS TRÊS

## Fluxo de Funcionamento

```
1. INICIALIZAÇÃO (uma única vez)
   └─ Apache lê httpd.conf
      ├─ Carrega módulos (mod_rewrite, mod_php, etc)
      ├─ Define portas (80, 443)
      └─ Processa Include /etc/apache2/conf.d/*.conf
         └─ Lê vhost.conf de cada domínio
            └─ Define AllowOverride (autoriza .htaccess?)
            
2. REQUISIÇÃO CLIENTE (a cada requisição)
   └─ Cliente: GET ipaccontabilidade.com.br/usuarios
      ├─ Apache processa vhost.conf (já em memória)
      ├─ Entra em DocumentRoot
      ├─ Procura .htaccess (LIDO AGORA)
      ├─ Aplica regras: Rewrite, Cache, Autenticação
      ├─ Navega por subpastas (se houver)
      ├─ Lê .htaccess subpasta (se existir)
      ├─ Sobrescreve com regras mais específicas
      └─ PHP executa com todas as configurações aplicadas
```

## Hierarquia de Poder

Quem pode sobrescrever quem?

```
httpd.conf (fraco)
    ↓ pode ser sobrescrito por ↓
vhost.conf (médio)
    ↓ pode ser sobrescrito por ↓
.htaccess raiz (forte)
    ↓ pode ser sobrescrito por ↓
.htaccess subfolder (muito forte - mais específico)

Regra: Quanto mais específico, mais poder
```

## Exemplo Completo

### Cenário: 3 domínios no mesmo servidor

```
httpd.conf (GLOBAL)
├─ LoadModule rewrite_module
├─ LoadModule ssl_module
├─ LoadModule php_module
└─ Include /etc/apache2/conf.d/*.conf
   
   ├─ vhost_001_ipac.conf (DOMÍNIO 1)
   │  ├─ ServerName: ipaccontabilidade.com.br
   │  ├─ DocumentRoot: /home/ipacuser/public_html
   │  └─ AllowOverride All
   │     └─ /home/ipacuser/public_html/.htaccess
   │        ├─ RewriteEngine On
   │        ├─ HTTPS redirect
   │        ├─ Cache headers
   │        └─ Rewrite para framework
   │           └─ /home/ipacuser/public_html/admin/.htaccess
   │              ├─ Herda rewrite de raiz
   │              ├─ Adiciona: AuthType Basic
   │              └─ Sobrescreve se houver conflito
   │
   ├─ vhost_002_api.conf (DOMÍNIO 2 - COMPLETAMENTE SEPARADO)
   │  ├─ ServerName: api.ipaccontabilidade.com.br
   │  ├─ DocumentRoot: /home/ipacuser/public_html_api (DIFERENTE!)
   │  └─ AllowOverride All
   │     └─ /home/ipacuser/public_html_api/.htaccess (DIFERENTE!)
   │        ├─ API versioning
   │        ├─ JSON headers
   │        └─ Rewrite específico para API
   │
   └─ vhost_003_staging.conf (DOMÍNIO 3 - COMPLETAMENTE SEPARADO)
      ├─ ServerName: staging.ipaccontabilidade.com.br
      ├─ DocumentRoot: /home/ipacuser/public_html_staging (DIFERENTE!)
      └─ AllowOverride All
         └─ /home/ipacuser/public_html_staging/.htaccess (DIFERENTE!)
            ├─ Debug mode
            └─ Reduced cache
```

### Requisição: https://ipaccontabilidade.com.br/admin/dashboard

```
1. Apache processa httpd.conf (já feito no boot)
2. Apache processa vhost_001_ipac.conf
   └─ ServerName = ipaccontabilidade.com.br ✅ Match
   └─ DocumentRoot = /home/ipacuser/public_html ✅
   └─ AllowOverride All ✅ permite .htaccess
3. Apache lê /home/ipacuser/public_html/.htaccess
   ├─ RewriteEngine On ✅
   ├─ HTTPS redirect (já é HTTPS, pula) ✅
   ├─ Cache headers ✅ (aplica)
4. Apache navega para /admin
5. Apache lê /home/ipacuser/public_html/admin/.htaccess
   ├─ Herda: RewriteEngine, Cache, HTTPS de raiz ✅
   ├─ Novo: AuthType Basic ✅
   └─ Exige login ✅
6. PHP executa dashboard.php
   ├─ Com rewrite de raiz ✅
   ├─ Com cache de raiz ✅
   ├─ Com autenticação de /admin ✅
```

---

# PARTE 6: TABELA COMPARATIVA

| Aspecto | httpd.conf | vhost.conf | .htaccess |
|---------|-----------|-----------|-----------|
| **O quê?** | Configuração global do Apache | Config por domínio | Config por pasta |
| **Onde?** | `/etc/apache2/httpd.conf` | `/etc/apache2/conf.d/vhost_*.conf` | `{DocumentRoot}/.htaccess` |
| **Quando lido?** | Inicialização (boot) | Inicialização (boot) | Toda requisição HTTP |
| **Quem modifica?** | Administrador (SSH) | cPanel/WHM | Você (FTP/SSH/cPanel) |
| **Afeta?** | Todos os domínios | Um domínio | Uma pasta + subpastas |
| **Requer restart?** | Sim | Sim | Não (imediato) |
| **Precedência** | 1 (mais fraco) | 2 | 3/4 (mais forte) |
| **Carrega módulos?** | ✅ | ❌ | ❌ |
| **Define domínios?** | ❌ | ✅ | ❌ |
| **Permite rewrite?** | ❌ | Autoriza (AllowOverride) | ✅ |
| **Permite cache?** | Módulo apenas | Autoriza (AllowOverride) | ✅ |
| **Permite auth?** | Módulo apenas | Autoriza (AllowOverride) | ✅ |

---

# PARTE 7: CONCEITOS-CHAVE

## AllowOverride: A Porta de Entrada

```
vhost.conf tem uma "porta":
<Directory /home/ipacuser/public_html>
    AllowOverride All    ← ABRE a porta
</Directory>

Se AllowOverride All:
    └─ .htaccess pode fazer quase tudo

Se AllowOverride None:
    └─ .htaccess é IGNORADO (porta fechada)

Analogia: vhost.conf é o segurança que decide se .htaccess entra
```

## Isolamento: Domínios Não se Interferem

```
Domínio A usa DocumentRoot /home/userA/public_html
Domínio B usa DocumentRoot /home/userB/public_html

.htaccess de A não afeta B porque:
├─ São arquivos em caminhos diferentes
├─ São processados por VirtualHosts diferentes
├─ Não compartilham espaço de configuração
└─ Isolamento automático
```

## Herança: .htaccess Subfolder Herda de Raiz

```
/public_html/.htaccess
└─ RewriteEngine On
└─ Regra de HTTPS
└─ Cache headers

/public_html/admin/.htaccess
└─ Não redeclara RewriteEngine (herda)
└─ Não redeclara HTTPS (herda)
└─ Não redeclara Cache (herda)
└─ Adiciona: AuthType Basic (novo)

Resultado em /admin:
└─ Rewrite + HTTPS + Cache + Auth (tudo aplicado)
```

---

# PARTE 8: RESUMO ESTRUTURADO

### httpd.conf
- **É:** Configuração global do Apache (serviço do SO)
- **Faz:** Carrega módulos, define portas, importa outros arquivos
- **Quando:** Uma vez no boot
- **Afeta:** Todos os domínios
- **Muda:** Requer restart

### vhost.conf
- **É:** Configuração específica de um domínio
- **Faz:** Mapeia domínio → pasta, define SSL, autoriza .htaccess
- **Quando:** Uma vez no boot (para cada VirtualHost)
- **Afeta:** Um domínio apenas (isolado)
- **Muda:** Requer restart

### .htaccess
- **É:** Configuração específica de uma pasta
- **Faz:** Rewrite, cache, autenticação, compressão, segurança
- **Quando:** Toda requisição HTTP (lido no momento)
- **Afeta:** Pasta + subpastas (até sobrescrita)
- **Muda:** Imediato (sem restart)

### Relação
```
httpd.conf 
    ↓ (imports)
vhost.conf 
    ↓ (autoriza via AllowOverride)
.htaccess 
    ↓ (herança em subpastas)
.htaccess subfolder
```

---

**Pronto. Você agora compreende cada componente e como funcionam juntos.**

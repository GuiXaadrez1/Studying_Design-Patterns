# Configurando o httpd-vhosts.conf para Produção — Edição Expandida
## Múltiplos VirtualHosts em Portas Diferentes, com Módulos, Containers e Diretrizes Completas

*Guia prático da série de referência Apache 2.4. Versão expandida: cada nível traz vhosts completos e comentados, com uso progressivo de módulos (mod_headers, mod_deflate, mod_expires, mod_setenvif, mod_http2, mod_status, mod_proxy/balancer) e containers (`<Directory>`, `<Files>`, `<FilesMatch>`, `<Location>`, `<LocationMatch>`, `<DirectoryMatch>`, `<If>`, `<Proxy>`, `<RequireAll/Any/None>`, `<IfModule>`, `<Limit>`).*

---

## Capítulo 0 — Fundações

### 0.1 As três verdades

1. **Portas pertencem ao servidor.** Cada porta de qualquer vhost exige `Listen` no contexto global. Sem `Listen 8080`, um `<VirtualHost *:8080>` nunca recebe conexão.
2. **Um vhost por requisição.** Seleção: par IP:porta primeiro → `ServerName`/`ServerAlias` depois → sem casamento, vence o **primeiro vhost declarado** para aquele par (o *default*). Vhosts nunca se mesclam entre si.
3. **`AllowOverride None` em produção.** Regras vivem no vhost: validáveis com `httpd -t`, lidas uma vez, imunes a `.htaccess` malicioso.

### 0.2 Os containers — o vocabulário deste guia

| Container | Casa contra | Uso típico |
|---|---|---|
| `<Directory>` / `<DirectoryMatch>` | Caminho de **filesystem** (literal / regex) | Permissões, Options, rewrite de aplicação |
| `<Files>` / `<FilesMatch>` | **Nome de arquivo** (literal / regex) | Bloquear/tratar arquivos por extensão, handlers |
| `<Location>` / `<LocationMatch>` | Caminho de **URL** (literal / regex) | Proxy, handlers virtuais (server-status), URLs sem filesystem |
| `<If>` / `<ElseIf>` / `<Else>` | **Expressão** avaliada por requisição (ap_expr) | Lógica condicional: cabeçalhos, método, variáveis |
| `<Proxy>` | URLs **proxificadas** | Controle de acesso e parâmetros de backends |
| `<Limit>` / `<LimitExcept>` | **Métodos HTTP** | Restringir métodos (ver ressalva no §4.6) |
| `<RequireAll>` / `<RequireAny>` / `<RequireNone>` | Combinação lógica de autorizações | AND / OR / NOT de `Require` |
| `<IfModule>` / `<IfDefine>` | Presença de módulo / flag `-D` | Configuração defensiva/portável |

**Regra de precedência (merge)**: `<Directory>` (+.htaccess, do caminho mais curto ao mais longo) → `<DirectoryMatch>` → `<Files>`/`<FilesMatch>` → `<Location>`/`<LocationMatch>`. **`<Location>` sempre tem a palavra final** — por isso proxy e handlers virtuais vivem nele, e por isso não se usa `<Location>` para proteger filesystem (um alias/encoding alternativo da URL pode contorná-lo; proteção de arquivos é trabalho do `<Directory>`).

### 0.3 Preparação global (httpd.conf)

```apache
# ---------- Portas (uma por porta usada em qualquer vhost) ----------
Listen 80
Listen 443
Listen 8080
Listen 8443

# ---------- Módulos usados ao longo deste guia ----------
LoadModule rewrite_module        modules/mod_rewrite.so
LoadModule ssl_module            modules/mod_ssl.so
LoadModule socache_shmcb_module  modules/mod_socache_shmcb.so   # cache de sessão TLS
LoadModule headers_module        modules/mod_headers.so
LoadModule deflate_module        modules/mod_deflate.so
LoadModule filter_module         modules/mod_filter.so
LoadModule expires_module        modules/mod_expires.so
LoadModule setenvif_module       modules/mod_setenvif.so
LoadModule mime_module           modules/mod_mime.so
LoadModule dir_module            modules/mod_dir.so
LoadModule alias_module          modules/mod_alias.so
LoadModule authz_core_module     modules/mod_authz_core.so
LoadModule authz_host_module     modules/mod_authz_host.so
LoadModule auth_basic_module     modules/mod_auth_basic.so
LoadModule authn_file_module     modules/mod_authn_file.so
LoadModule authn_core_module     modules/mod_authn_core.so
LoadModule proxy_module          modules/mod_proxy.so
LoadModule proxy_http_module     modules/mod_proxy_http.so
LoadModule proxy_balancer_module modules/mod_proxy_balancer.so
LoadModule lbmethod_byrequests_module modules/mod_lbmethod_byrequests.so
LoadModule slotmem_shm_module    modules/mod_slotmem_shm.so     # exigido pelo balancer
LoadModule status_module         modules/mod_status.so
LoadModule http2_module          modules/mod_http2.so
LoadModule log_config_module     modules/mod_log_config.so

# ---------- Política defensiva de base ----------
<Directory "/">
    AllowOverride None
    Require all denied
</Directory>

# Nunca servir metadados de VCS/segredos, em NENHUM site (contexto global):
<DirectoryMatch "/\.(git|svn|hg)">
    Require all denied
</DirectoryMatch>
<FilesMatch "^\.(env|htaccess|htpasswd|user\.ini)|\.(bak|old|orig|sql|swp)$">
    Require all denied
</FilesMatch>

# ---------- Identidade e postura ----------
ServerName   servidor.empresa.com.br:80
ServerTokens Prod            # cabeçalho Server: "Apache", sem versão
ServerSignature Off
TraceEnable Off

# ---------- Formatos de log nomeados (usados pelos vhosts) ----------
LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
# formato estendido: tempo de resposta em microssegundos (%D) e protocolo (%H)
LogFormat "%h %l %u %t \"%r\" %>s %b %D %H \"%{Referer}i\" \"%{User-Agent}i\"" perf

# ---------- Vhosts ----------
IncludeOptional conf/sites/*.conf
```

---

## NÍVEL BÁSICO

### 1.1 Um vhost por porta — o padrão, com um degrau a mais

Além do mínimo (identidade, DocumentRoot, `<Directory>`, logs), este exemplo já incorpora: `DirectoryIndex`, `ErrorDocument`, charset, e o primeiro container condicional (`<IfModule>`).

```apache
# ============ conf/sites/10-site-basico.conf ============

<VirtualHost *:80>
    # ---------- Identidade ----------
    ServerName  www.exemplo.com.br
    ServerAlias exemplo.com.br
    ServerAdmin ti@exemplo.com.br          # aparece em páginas de erro padrão

    # ---------- Documentos ----------
    DocumentRoot "/var/www/site/public"

    # arquivo servido quando a URL aponta um diretório
    DirectoryIndex index.php index.html

    # charset padrão para text/html e text/plain sem charset declarado
    AddDefaultCharset UTF-8

    # ---------- Permissões da raiz pública ----------
    <Directory "/var/www/site/public">
        Options FollowSymLinks             # sem Indexes: nunca listar diretórios
        AllowOverride None                 # .htaccess ignorado
        Require all granted
    </Directory>

    # ---------- Páginas de erro do site ----------
    ErrorDocument 404 /erros/404.html
    ErrorDocument 500 /erros/500.html
    ErrorDocument 503 "Em manutenção programada. Retornamos em instantes."

    # ---------- Primeiro contato com <IfModule>: cabeçalhos, se disponível ----------
    <IfModule mod_headers.c>
        Header set X-Content-Type-Options "nosniff"
    </IfModule>

    # ---------- Logs dedicados ----------
    ErrorLog  "/var/log/httpd/site-error.log"
    LogLevel  warn
    CustomLog "/var/log/httpd/site-access.log" combined
</VirtualHost>


# ============ Painel em porta dedicada (seleção por PORTA) ============
<VirtualHost *:8080>
    ServerName   painel.exemplo.com.br
    DocumentRoot "/var/www/painel/public"
    DirectoryIndex index.php

    <Directory "/var/www/painel/public">
        Options FollowSymLinks
        AllowOverride None
        Require all granted                # restrição real chega no intermediário
    </Directory>

    ErrorLog  "/var/log/httpd/painel-error.log"
    CustomLog "/var/log/httpd/painel-access.log" combined
</VirtualHost>
```

### 1.2 Vários sites por nome + catch-all defensivo

```apache
# ============ conf/sites/00-default.conf ============
# ⚠ PRIMEIRO vhost de cada par IP:porta = default do par.
# Este catch-all captura acesso por IP direto, scanners e domínios órfãos.

<VirtualHost *:80>
    ServerName default.invalid             # nome impossível: nunca casa legitimamente
    DocumentRoot "/var/www/default"

    <Directory "/var/www/default">
        AllowOverride None
        Require all granted
    </Directory>

    # postura hostil a varredura: TODA URL responde 404
    RedirectMatch 404 "^"

    # log separado: aqui só aparece tráfego anômalo — vira sensor de reconhecimento
    ErrorLog  "/var/log/httpd/default-error.log"
    CustomLog "/var/log/httpd/default-access.log" combined
</VirtualHost>
```

```apache
# ============ conf/sites/20-blog.conf ============
# Segundo site na MESMA porta 80 — casamento por nome + front controller.

<VirtualHost *:80>
    ServerName   blog.exemplo.com.br
    DocumentRoot "/var/www/blog/public"
    DirectoryIndex index.php

    <Directory "/var/www/blog/public">
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        # Front controller (em <Directory>: padrão SEM barra inicial)
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    ErrorLog  "/var/log/httpd/blog-error.log"
    CustomLog "/var/log/httpd/blog-access.log" combined
</VirtualHost>
```

Validação do nível: `httpd -t` → `httpd -S` (confira o `default server` de cada porta e arquivo:linha de cada vhost).

---

## NÍVEL INTERMEDIÁRIO

Aqui os vhosts ganham corpo: módulos de conteúdo (compressão, cache, cabeçalhos), containers de arquivo (`<Files>`, `<FilesMatch>`), variáveis condicionais (`mod_setenvif`), logging condicional, diretórios externos e as primeiras proteções compostas.

### 2.1 Vhost completo de site institucional — módulos de conteúdo aplicados

```apache
# ============ conf/sites/10-institucional.conf ============

<VirtualHost *:80>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br
    ServerAdmin ti@empresa.com.br
    DocumentRoot "/var/www/institucional/public"
    DirectoryIndex index.php index.html
    AddDefaultCharset UTF-8

    # =====================================================
    # PERMISSÕES + FRONT CONTROLLER
    # =====================================================
    <Directory "/var/www/institucional/public">
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On
        # exceção de performance: estáticos nem entram no PHP
        RewriteCond %{REQUEST_URI} !\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$ [NC]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # =====================================================
    # CONTAINERS DE ARQUIVO — política por tipo
    # =====================================================
    # fontes web: CORS liberado (fontes falham cross-origin sem isso)
    <FilesMatch "\.(woff2?|ttf|eot)$">
        <IfModule mod_headers.c>
            Header set Access-Control-Allow-Origin "*"
        </IfModule>
    </FilesMatch>

    # um arquivo específico, container literal <Files>:
    <Files "composer.json">
        Require all denied                 # metadado de build não se serve
    </Files>

    # =====================================================
    # MOD_DEFLATE — compressão de texto
    # =====================================================
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
        AddOutputFilterByType DEFLATE application/javascript application/json
        AddOutputFilterByType DEFLATE image/svg+xml

        # clientes pré-históricos que quebram com gzip (mod_setenvif):
        BrowserMatch ^Mozilla/4\.0[678] no-gzip

        # registra a taxa de compressão numa nota, consumível no log
        DeflateFilterNote Ratio ratio
    </IfModule>

    # =====================================================
    # MOD_EXPIRES + MOD_HEADERS — política de cache HTTP
    # =====================================================
    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType image/webp        "access plus 30 days"
        ExpiresByType image/png         "access plus 30 days"
        ExpiresByType image/svg+xml     "access plus 30 days"
        ExpiresByType text/css          "access plus 7 days"
        ExpiresByType application/javascript "access plus 7 days"
        ExpiresByType text/html         "access plus 0 seconds"   # HTML nunca cacheia
    </IfModule>

    <IfModule mod_headers.c>
        # estáticos versionados por nome (app.a1b2c3.css): imutáveis por 1 ano
        <FilesMatch "\.[0-9a-f]{8,}\.(css|js)$">
            Header set Cache-Control "public, max-age=31536000, immutable"
        </FilesMatch>

        # cabeçalhos de segurança do site inteiro
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        # remove o vazamento de versão do PHP (mod_php)
        Header always unset X-Powered-By
    </IfModule>

    # =====================================================
    # MOD_SETENVIF — variáveis por requisição p/ logging condicional
    # =====================================================
    <IfModule mod_setenvif.c>
        SetEnvIf Request_URI "^/health$"           nolog     # health checks fora do log
        SetEnvIf Request_URI "\.(png|jpe?g|webp|ico)$" static
        SetEnvIf User-Agent  "(UptimeRobot|Pingdom)"   nolog
    </IfModule>

    # =====================================================
    # LOGS — separação por natureza do tráfego
    # =====================================================
    ErrorLog  "/var/log/httpd/institucional-error.log"
    LogLevel  warn

    # acessos de páginas: formato com tempo de resposta (%D via formato "perf")
    CustomLog "/var/log/httpd/institucional-access.log" perf   env=!nolog

    # estáticos em arquivo próprio (volume alto, valor analítico baixo)
    CustomLog "/var/log/httpd/institucional-static.log" common env=static
</VirtualHost>
```

### 2.2 Diretórios fora do DocumentRoot — três padrões completos

```apache
    # (dentro do vhost acima, antes dos logs)

    # -----------------------------------------------------
    # (a) DOWNLOADS em volume separado
    # -----------------------------------------------------
    Alias "/downloads" "/dados/arquivos-publicos"
    <Directory "/dados/arquivos-publicos">
        Options -Indexes
        AllowOverride None
        Require all granted

        <IfModule mod_headers.c>
            # força salvar em vez de renderizar
            <FilesMatch "\.(pdf|zip|xlsx|docx)$">
                Header set Content-Disposition "attachment"
            </FilesMatch>
        </IfModule>
    </Directory>

    # -----------------------------------------------------
    # (b) ASSETS COMPARTILHADOS entre sites (repetível em outros vhosts)
    # -----------------------------------------------------
    Alias "/assets-corp" "/var/www/_shared/assets"
    <Directory "/var/www/_shared/assets">
        Options -Indexes
        AllowOverride None
        Require all granted
        <IfModule mod_headers.c>
            Header set Cache-Control "public, max-age=2592000"
        </IfModule>
    </Directory>

    # -----------------------------------------------------
    # (c) UPLOADS: servidos, JAMAIS executados
    # -----------------------------------------------------
    Alias "/uploads" "/dados/uploads"
    <Directory "/dados/uploads">
        Options -Indexes -ExecCGI
        AllowOverride None
        Require all granted

        # desarma qualquer handler PHP e nega até o fonte (cinto E suspensório)
        <FilesMatch "\.ph(ar|p[0-9]?|tml)$">
            SetHandler none
            Require all denied
        </FilesMatch>
        # tipo forçado: navegador nunca "adivinha" executável
        <IfModule mod_headers.c>
            Header set X-Content-Type-Options "nosniff"
        </IfModule>
    </Directory>
```

*Armadilhas do Alias: coerência de barra final nos dois argumentos; usuário do Apache precisa de leitura no destino e travessia (+x) em todo o caminho; Alias intercepta a URL antes do DocumentRoot (uma pasta homônima sob a raiz fica inalcançável).*

### 2.3 Proteções compostas — autorização com lógica

```apache
# ============ conf/sites/30-painel.conf (evolução do painel do básico) ============

<VirtualHost *:8080>
    ServerName   painel.empresa.com.br
    DocumentRoot "/var/www/painel/public"
    DirectoryIndex index.php

    # ---------- Regra geral: rede interna OU credencial ----------
    <Directory "/var/www/painel/public">
        Options FollowSymLinks
        AllowOverride None

        AuthType Basic
        AuthName "Painel Interno"
        AuthUserFile "/etc/httpd/passwd/painel.passwd"   # SEMPRE fora do DocumentRoot

        <RequireAny>
            Require ip 192.168.10 127.0.0.1     # de dentro: entra direto
            Require valid-user                   # de fora: exige login
        </RequireAny>
    </Directory>

    # ---------- Sub-área crítica: rede interna E credencial E grupo ----------
    <Directory "/var/www/painel/public/config">
        <RequireAll>
            Require ip 192.168.10
            <RequireAny>
                Require user admin.silva
                Require user admin.souza
            </RequireAny>
            <RequireNone>
                Require env bloqueado            # variável setada por SetEnvIf, ex. UA banido
            </RequireNone>
        </RequireAll>
    </Directory>

    # UA de auditoria automatizada não acessa /config (alimenta o RequireNone acima)
    <IfModule mod_setenvif.c>
        SetEnvIf User-Agent "scanner-interno" bloqueado
    </IfModule>

    ErrorLog  "/var/log/httpd/painel-error.log"
    CustomLog "/var/log/httpd/painel-access.log" combined
</VirtualHost>
```

> **Basic Auth transmite credenciais em Base64 (reversível).** Este vhost em :8080 sem TLS só é aceitável dentro de rede interna confiável; a versão pública correta é a do nível avançado (8443 + TLS).

---

## NÍVEL AVANÇADO

Vhosts de produção completos: TLS endurecido com HTTP/2, o container `<If>` com expressões, `<Location>` para handlers virtuais (server-status) e proxy, `<Proxy>` com balanceamento de carga, manutenção com exceções, e RewriteMap.

### 3.1 O par 80→443 e o vhost TLS de produção completo

```apache
# ============ conf/sites/10-institucional-tls.conf ============

# ---------- :80 — SÓ redireciona; zero conteúdo ----------
<VirtualHost *:80>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br

    # exceção: desafio ACME (renovação Let's Encrypt) passa em HTTP puro
    Alias "/.well-known/acme-challenge" "/var/www/acme/.well-known/acme-challenge"
    <Directory "/var/www/acme/.well-known/acme-challenge">
        AllowOverride None
        Require all granted
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://www.empresa.com.br%{REQUEST_URI} [R=301,L]

    ErrorLog "/var/log/httpd/institucional-redirect-error.log"
</VirtualHost>

# ---------- :443 — o site real ----------
<VirtualHost *:443>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br
    ServerAdmin ti@empresa.com.br
    DocumentRoot "/var/www/institucional/public"
    DirectoryIndex index.php
    AddDefaultCharset UTF-8

    # =====================================================
    # TLS ENDURECIDO + HTTP/2
    # =====================================================
    SSLEngine On
    SSLCertificateFile      "/etc/letsencrypt/live/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile   "/etc/letsencrypt/live/empresa.com.br/privkey.pem"

    # protocolos: só TLS 1.2+ (1.3 negociado automaticamente quando disponível)
    SSLProtocol             -all +TLSv1.2 +TLSv1.3
    SSLCipherSuite          ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder     Off            # em TLS 1.2+ moderno, o cliente escolhe

    # OCSP stapling: o servidor entrega a prova de revogação (exige o
    # SSLStaplingCache no contexto GLOBAL: SSLStaplingCache shmcb:.../stapling(32768))
    SSLUseStapling On

    # HTTP/2 com fallback para 1.1 (mod_http2)
    <IfModule http2_module>
        Protocols h2 http/1.1
    </IfModule>

    # =====================================================
    # <If> — LÓGICA CONDICIONAL POR REQUISIÇÃO (ap_expr)
    # =====================================================
    # canonicalização: domínio nu → www (expressão em vez de RewriteCond)
    <If "%{HTTP_HOST} == 'empresa.com.br'">
        Redirect permanent "/" "https://www.empresa.com.br/"
    </If>

    # bloqueia métodos que a aplicação não usa (405), exceto na API interna
    <If "%{REQUEST_METHOD} !~ /^(GET|HEAD|POST)$/ && %{REQUEST_URI} !~ m#^/api/#">
        Require all denied
    </If>

    # cabeçalho de depuração visível SÓ para a rede interna
    <If "-R '192.168.10.0/24'">
        Header always set X-Backend-Debug "vhost=institucional-tls"
    </If>

    # =====================================================
    # PERMISSÕES + FRONT CONTROLLER (idem intermediário)
    # =====================================================
    <Directory "/var/www/institucional/public">
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_URI} !\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$ [NC]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # =====================================================
    # MODO MANUTENÇÃO COM EXCEÇÕES (controlado por um arquivo-flag)
    # =====================================================
    RewriteEngine On
    # liga a manutenção criando /var/www/institucional/MAINT (touch/rm — sem reload!)
    RewriteCond /var/www/institucional/MAINT -f
    RewriteCond %{REMOTE_ADDR} !^192\.168\.10\.        # equipe continua acessando
    RewriteCond %{REQUEST_URI} !^/manutencao\.html$
    RewriteCond %{REQUEST_URI} !\.(css|png|svg)$        # assets da própria página
    RewriteRule ^ /manutencao.html [R=503,L]
    ErrorDocument 503 /manutencao.html
    <IfModule mod_headers.c>
        Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
    </IfModule>

    # =====================================================
    # SEGURANÇA DE TRANSPORTE E CONTEÚDO
    # =====================================================
    <IfModule mod_headers.c>
        # HSTS: ative SÓ com o site 100% funcional em TLS
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always set Content-Security-Policy "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'"
        Header always unset X-Powered-By
    </IfModule>

    # anti-abuso básico: corpo de requisição limitado a 10 MB fora do /uploads
    <Directory "/var/www/institucional/public">
        LimitRequestBody 10485760
    </Directory>

    # compressão, cache e logging condicional: blocos idênticos ao §2.1
    # (omitidos aqui por brevidade — em produção, mantenha-os)

    ErrorLog  "/var/log/httpd/institucional-ssl-error.log"
    LogLevel  warn
    CustomLog "/var/log/httpd/institucional-ssl-access.log" perf env=!nolog
</VirtualHost>
```

### 3.2 `<Location>` para handlers virtuais — server-status protegido

```apache
    # (dentro de um vhost interno, ex.: painel :8443)
    # /status NÃO é um diretório: é um handler — por isso <Location>
    <Location "/status">
        SetHandler server-status
        <RequireAny>
            Require local
            Require ip 192.168.10
        </RequireAny>
    </Location>
    # métricas estendidas (contexto global): ExtendedStatus On
```

### 3.3 Reverse proxy com balanceamento — `<Proxy>` + balancer

```apache
# ============ conf/sites/40-api-8443.conf ============
# Apache em :8443 como fachada TLS de DUAS instâncias Laravel locais.

<VirtualHost *:8443>
    ServerName api.empresa.com.br

    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/api.empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/api.empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    <IfModule http2_module>
        Protocols h2 http/1.1
    </IfModule>

    ProxyRequests Off              # jamais proxy aberto de saída
    ProxyPreserveHost On           # Host original chega ao backend

    # ---------- Pool de backends: container <Proxy> ----------
    <Proxy "balancer://api-pool">
        BalancerMember "http://127.0.0.1:8000" route=api1 retry=30
        BalancerMember "http://127.0.0.1:8001" route=api2 retry=30
        ProxySet lbmethod=byrequests           # distribui por nº de requisições
        # afinidade de sessão via cookie, se a app precisar:
        # ProxySet stickysession=LARAVEL_SESSION
    </Proxy>

    # ---------- Roteamento por URL: <Location> manda no proxy ----------
    # health check respondido pelo PRÓPRIO Apache (backend nem é tocado)
    <Location "/health">
        ProxyPass "!"
        Require all granted
    </Location>
    <If "%{REQUEST_URI} == '/health'">
        Redirect 200 /health
        Header always set Content-Type "text/plain"
    </If>

    # console do balanceador — SÓ rede interna
    <Location "/balancer-manager">
        SetHandler balancer-manager
        ProxyPass "!"                          # não proxifica o próprio console
        Require ip 192.168.10
    </Location>

    # todo o resto → pool
    ProxyPass        "/" "balancer://api-pool/"
    ProxyPassReverse "/" "balancer://api-pool/"

    # cabeçalhos de contexto para o backend confiar na borda
    <IfModule mod_headers.c>
        RequestHeader set X-Forwarded-Proto "https"
        RequestHeader set X-Forwarded-Port  "8443"
    </IfModule>

    # timeouts do proxy: falhar rápido é melhor que enfileirar
    ProxyTimeout 30

    # ---------- Controle de acesso da API ----------
    <Location "/">
        <RequireAny>
            Require ip 192.168.10
            Require ip 127.0.0.1
            # parceiros externos por IP fixo:
            Require ip 200.200.200.200
        </RequireAny>
    </Location>

    ErrorLog  "/var/log/httpd/api-error.log"
    CustomLog "/var/log/httpd/api-access.log" perf
</VirtualHost>
```

### 3.4 RewriteMap — reescrita orientada a dados

Migrações grandes (centenas de URLs antigas → novas) não se fazem com cem `RewriteRule`: usa-se um mapa externo.

```apache
    # RewriteMap SÓ pode ser declarada em server config ou virtual host
    # (nunca em <Directory>/.htaccess) — mais um motivo para viver no vhost.

    # arquivo texto "chave valor" (gerar a versão .map exigida pelo dbm com httxt2dbm)
    RewriteMap redirmap "dbm:/etc/httpd/maps/redirects.map"

    RewriteEngine On
    # se a URL existir no mapa, redireciona 301 para o destino mapeado
    RewriteCond ${redirmap:%{REQUEST_URI}} !=""
    RewriteRule ^ ${redirmap:%{REQUEST_URI}} [R=301,L]
```

```
# /etc/httpd/maps/redirects.txt (fonte do dbm)
/promocao-2024        /ofertas
/antigo/contato.html  /contato
/blog/artigo-123      /blog/novo-slug-do-artigo
```

```bash
# compilar o mapa (releitura automática quando o .map muda — sem reload do Apache)
httxt2dbm -i redirects.txt -o redirects.map
```

---

## Capítulo 4 — Operação e Diagnóstico

### 4.1 Organização: um arquivo por site, ordem controlada

```
conf/sites/
├── 00-default.conf            # catch-all — prefixo garante que é o PRIMEIRO (= default)
├── 10-institucional-tls.conf  # :80 redirect + :443 site
├── 20-blog.conf
├── 30-painel-8443.conf
└── 40-api-8443.conf
```

`IncludeOptional conf/sites/*.conf` processa em ordem alfabética — o prefixo numérico **é** o mecanismo de controle do vhost default por porta.

### 4.2 Checklist de mudança

```bash
httpd -t                                   # 1. Syntax OK ou não prossegue
httpd -S                                   # 2. portas, defaults e nomes esperados
httpd -M | grep -E 'rewrite|ssl|proxy|http2'   # 3. módulos realmente carregados
apachectl graceful                         # 4. reload sem derrubar conexões
curl -I  https://www.empresa.com.br/       # 5. smoke test por vhost/porta
curl -I  http://www.empresa.com.br/        #    (301 esperado)
curl -Ik https://api.empresa.com.br:8443/health
tail -f /var/log/httpd/*-error.log         # 6. observação pós-mudança
```

### 4.3 Tabela de sintomas — multiporta/módulos/containers

| Sintoma | Causa | Correção |
|---|---|---|
| connection refused em porta nova | `Listen` ausente ou firewall | `Listen` global; `ss -tlnp \| grep httpd`; liberar firewall |
| Site errado responde | Ordem dos vhosts (primeiro = default do par) | `httpd -S`; prefixos numéricos |
| `Invalid command 'Header'` (ou Proxy/Expires…) na inicialização | Módulo não carregado | `LoadModule` no global; conferir com `httpd -M` |
| Diretiva "não faz nada" mas sem erro | Envolvida em `<IfModule>` de módulo ausente | O IfModule mascarou; carregue o módulo ou remova o envelope |
| 403 em Alias novo | `<Directory>` do destino ausente, ou permissão Unix de travessia | Bloco + `Require`; `ls -la` no caminho todo p/ usuário do Apache |
| `<Location>` "protegendo" arquivos foi contornado | Location casa URL, não filesystem | Proteção de arquivos = `<Directory>`/`<Files>`; Location só p/ proxy e handlers |
| Balancer 503 imediato | `slotmem_shm` não carregado, ou membros down | `httpd -M`; `/balancer-manager`; `retry=` reduz o tempo de quarentena |
| HTTP/2 não negocia | `Protocols` ausente, ou mod_php (prefork) presente | mod_http2 exige MPM com threads (event) — com PHP, use FPM |
| `<If>` não casa nunca | Sintaxe ap_expr (aspas, `=~ /re/` vs `== 'str'`) | Testar expressão isolada; ErrorLog acusa erro de parse na inicialização |
| Basic Auth em loop / sem prompt | `AuthUserFile` ilegível ou bloco no container errado | Caminho absoluto; ErrorLog do vhost nomeia o arquivo |
| Cert. errado em :8443 | SNI: nome não casa nenhum ServerName da porta → default | `httpd -S`; conferir ServerName/ordem |

### 4.4 Notas finais

- **`<Limit>`/`<LimitExcept>`:** prefira negar métodos com `<If "%{REQUEST_METHOD} …">` ou `Require` — `<Limit>` só se aplica aos métodos listados e esquece os demais, um alçapão clássico de segurança.
- **mod_php × HTTP/2 × MPM:** `mod_http2` requer MPM `event`/`worker`; `mod_php` clássico requer `prefork`. Em produção moderna: PHP-FPM via `proxy_fcgi` (`SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"`), liberando o MPM event e o HTTP/2.
- **2.2 → 2.4:** `Order/Allow/Deny/Satisfy` → `Require`; `NameVirtualHost` → remover; `RewriteLog` → `LogLevel rewrite:traceN` (contexto vhost/global, nunca .htaccess).

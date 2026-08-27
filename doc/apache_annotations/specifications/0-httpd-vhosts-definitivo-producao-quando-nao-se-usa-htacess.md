# httpd-vhosts.conf DEFINITIVO de Produção — Aplicando TODA a Série
## Servidor Dedicado/VPS: .htaccess DESATIVADO, Configuração Completa no VHost

*Versão máxima do template de produção — incorpora todos os aprendizados da série: variáveis e macros, PHP-FPM, páginas de erro completas, cache de cliente E de servidor, dossiê integral de headers, autorização em camadas, rastreamento ponta-a-ponta, proxy com fronteira de confiança. **Cada linha comentada.***

> **Postura deste arquivo:** em servidor dedicado/VPS, **`.htaccess` não se usa** — ele é a ferramenta de quem NÃO tem acesso ao vhost (hospedagem compartilhada). Aqui, `AllowOverride None` em tudo: cada regra vive neste arquivo, validável com `httpd -t` ANTES de entrar no ar, lida UMA vez no boot (zero I/O por requisição procurando .htaccess) e imune a arquivo plantado por invasor.

---

## BLOCO 0 — PRÉ-REQUISITOS NO httpd.conf (referência rápida)

```apache
# ============================================================
# O que o GLOBAL precisa ter ANTES deste arquivo (parse é
# sequencial: módulo/porta/define usados aqui têm que existir lá):
# ============================================================
# Listen 80
# Listen 443
#
# --- módulos exigidos por este template ---
# LoadModule rewrite_module         modules/mod_rewrite.so
# LoadModule ssl_module             modules/mod_ssl.so
# LoadModule socache_shmcb_module   modules/mod_socache_shmcb.so
# LoadModule headers_module         modules/mod_headers.so
# LoadModule deflate_module         modules/mod_deflate.so
# LoadModule expires_module         modules/mod_expires.so
# LoadModule setenvif_module        modules/mod_setenvif.so
# LoadModule env_module             modules/mod_env.so
# LoadModule unique_id_module       modules/mod_unique_id.so
# LoadModule macro_module           modules/mod_macro.so
# LoadModule cache_module           modules/mod_cache.so
# LoadModule cache_disk_module      modules/mod_cache_disk.so
# LoadModule authz_core_module      modules/mod_authz_core.so
# LoadModule authz_host_module      modules/mod_authz_host.so
# LoadModule authz_user_module      modules/mod_authz_user.so
# LoadModule authz_groupfile_module modules/mod_authz_groupfile.so
# LoadModule auth_basic_module      modules/mod_auth_basic.so
# LoadModule authn_file_module      modules/mod_authn_file.so
# LoadModule proxy_module           modules/mod_proxy.so
# LoadModule proxy_http_module      modules/mod_proxy_http.so
# LoadModule proxy_fcgi_module      modules/mod_proxy_fcgi.so   # PHP-FPM!
# LoadModule http2_module           modules/mod_http2.so
# LoadModule status_module          modules/mod_status.so
#
# --- postura global ---
# ServerName servidor.empresa.com.br:80   # mata o aviso AH00558
# ServerTokens Prod                       # "Server: Apache" sem versão
# ServerSignature Off                     # sem assinatura em erros
# TraceEnable Off                         # método TRACE desligado
#
# --- política defensiva: TUDO nasce negado, .htaccess NÃO EXISTE ---
# <Directory "/">
#     AllowOverride None                  # .htaccess nem é lido — em lugar nenhum
#     Require all denied                  # 403 por padrão; vhosts liberam por exceção
# </Directory>
#
# --- proteção transversal (vale p/ TODOS os sites de uma vez) ---
# <DirectoryMatch "/\.(git|svn|hg)">      # metadados de versionamento
#     Require all denied
# </DirectoryMatch>
# <FilesMatch "^\.(env|htaccess|htpasswd|user\.ini)|\.(bak|old|orig|sql|swp|log)$">
#     Require all denied                  # dotfiles, backups, dumps
# </FilesMatch>
#
# --- TLS global (parâmetros comuns a todos os vhosts :443) ---
# SSLSessionCache "shmcb:/run/httpd/sslcache(512000)"  # sessões TLS em memória
# SSLStaplingCache "shmcb:/run/httpd/stapling(65536)"  # exigido pelo OCSP stapling
#
# --- HTTP/2 + PHP exige MPM event + FPM (mod_php trava no prefork) ---
# (o handler proxy_fcgi está na Seção 4 de cada vhost)
#
# --- formatos de log nomeados ---
# LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
# LogFormat "%h %t \"%r\" %>s %b %D uid=%{UNIQUE_ID}e amb=%{APP_ENV}e \"%{User-Agent}i\"" rastreavel
#           # %D = duração µs; UNIQUE_ID = chave de rastreio ponta-a-ponta
#
# --- cache de SERVIDOR (defaults; o quê cachear é decidido por vhost) ---
# <IfModule mod_cache.c>
#     CacheQuickHandler off               # off = HITs respeitam authz/rewrite
#                                         # (on é mais rápido, mas PULA as fases —
#                                         #  proibido onde há conteúdo protegido)
#     CacheRoot "/var/cache/httpd/mod_cache"
#     CacheDirLevels 2
#     CacheDirLength 1
#     CacheDefaultExpire 3600             # sem cabeçalhos de frescor: 1h
#     CacheMaxExpire 86400                # teto absoluto: 24h
#     CacheIgnoreNoLastMod On             # cacheia mesmo sem Last-Modified
#     CacheLock On                        # anti-thundering-herd: 1 revalida,
#     CacheLockMaxAge 5                   # os demais usam o stale
# </IfModule>
# # manutenção do armazém: htcacheclean -d 30 -p /var/cache/httpd/mod_cache -l 512M
#
# --- este arquivo entra por último; ordem alfabética = prefixos numéricos ---
# IncludeOptional conf/sites/*.conf
```

---

## BLOCO 1 — VARIÁVEIS E MACRO (parametrização — nada hardcoded duas vezes)

```apache
# ============================================================
# Defines: variáveis de CONFIGURAÇÃO (existem só no parse; consumo ${VAR}).
# Prefixo CFG_ evita colisão com o ambiente do processo (o ${} faz
# fallback p/ ele quando não há Define!).
# ============================================================
Define CFG_LOG      "/var/log/httpd"
Define CFG_CERTS    "/etc/letsencrypt/live"
Define CFG_PASSWD   "/etc/httpd/passwd"
Define CFG_FPM      "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
#  └ o handler PHP-FPM inteiro numa variável: trocar o socket = 1 linha

# ============================================================
# MACRO: o "componente" ErroDocs — páginas de erro COMPLETAS,
# reutilizado por todos os vhosts com Use (expansão textual no parse).
# ============================================================
<Macro ErroDocs>
    # 4xx — erros do cliente (cada um com página própria: UX e diagnóstico)
    ErrorDocument 400 /erros/400.html    # requisição malformada
    ErrorDocument 401 /erros/401.html    # não autenticado (precisa ser LOCAL:
                                         # URL remota em 401 vira redirect e
                                         # quebra o fluxo de autenticação)
    ErrorDocument 403 /erros/403.html    # autenticado/identificado, mas negado
    ErrorDocument 404 /erros/404.html    # não encontrado
    ErrorDocument 405 /erros/405.html    # método não permitido (nossa Seção 5c)
    ErrorDocument 410 /erros/410.html    # removido de propósito (aposentadorias)
    ErrorDocument 429 /erros/429.html    # rate limit (se usar mod_ratelimit/evasive)
    # 5xx — erros do servidor
    ErrorDocument 500 /erros/500.html    # erro interno (inclui PHP fatal via FPM)
    ErrorDocument 502 /erros/502.html    # backend do proxy fora do ar
    ErrorDocument 503 /erros/503.html    # manutenção (nossa Seção 8)
    ErrorDocument 504 /erros/504.html    # backend estourou o ProxyTimeout
</Macro>

# ============================================================
# MACRO: bloco de headers de segurança — o dossiê COMPLETO,
# idêntico em todos os sites (uma correção = todos corrigidos).
# Tudo com "always": a tabela padrão abandona as páginas 4xx/5xx.
# ============================================================
<Macro HeadersSeguranca>
    <IfModule mod_headers.c>
        # HSTS: navegador exige HTTPS por 1 ano (condição: só emitir sobre TLS)
        Header always set Strict-Transport-Security \
            "max-age=31536000; includeSubDomains" "expr=%{HTTPS} == 'on'"
        Header always set X-Content-Type-Options "nosniff"     # sem sniffing de tipo
        Header always set X-Frame-Options "SAMEORIGIN"         # anti-clickjacking
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        # permissões de APIs do navegador: nega o que o site não usa
        Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
        # isolamento cross-origin
        Header always set Cross-Origin-Opener-Policy "same-origin"
        Header always set Cross-Origin-Resource-Policy "same-site"
        # CSP: NASCE em Report-Only; promova a Content-Security-Policy
        # só depois do relatório de violações vir limpo
        Header always set Content-Security-Policy-Report-Only \
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; report-uri /csp-report"
        # limpeza de carimbos (das DUAS tabelas — §always do guia de headers)
        Header always unset X-Powered-By
        Header unset X-Powered-By
    </IfModule>
</Macro>
```

---

## BLOCO 2 — O PORTEIRO (vhost default de cada porta)

```apache
# ============================================================
# conf/sites/00-default.conf — o PRIMEIRO vhost de cada par
# IP:porta é o default: captura IP direto, scanners e domínios
# órfãos. Sem ele, seu primeiro site real vira o alvo.
# ============================================================
<VirtualHost *:80>
    ServerName default.invalid           # nome impossível: nunca casa de verdade
    DocumentRoot "/var/www/default"
    <Directory "/var/www/default">
        AllowOverride None
        Require all granted              # libera SÓ p/ o RedirectMatch responder
    </Directory>
    RedirectMatch 404 "^"                # postura hostil: TODA URL = 404
    ErrorLog  "${CFG_LOG}/default-error.log"
    CustomLog "${CFG_LOG}/default-access.log" combined
    #  └ log separado = SENSOR: tudo que cai aqui é anômalo por definição
</VirtualHost>

<VirtualHost *:443>
    ServerName default.invalid
    DocumentRoot "/var/www/default"
    SSLEngine On
    # certificado "qualquer" válido do servidor — HTTPS por IP também cai aqui
    SSLCertificateFile    "${CFG_CERTS}/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "${CFG_CERTS}/empresa.com.br/privkey.pem"
    <Directory "/var/www/default">
        AllowOverride None
        Require all granted
    </Directory>
    RedirectMatch 404 "^"
    ErrorLog "${CFG_LOG}/default-ssl-error.log"
</VirtualHost>
```

---

## BLOCO 3 — O SITE PRINCIPAL (o vhost definitivo, seção a seção)

```apache
# ============================================================
# conf/sites/10-institucional.conf
# ============================================================

# ------------------------------------------------------------
# :80 — ZERO conteúdo: só o desafio ACME e o 301 canônico
# ------------------------------------------------------------
<VirtualHost *:80>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br

    # renovação Let's Encrypt valida buscando um arquivo em HTTP puro:
    Alias "/.well-known/acme-challenge" "/var/www/acme/.well-known/acme-challenge"
    <Directory "/var/www/acme/.well-known/acme-challenge">
        AllowOverride None
        Require all granted              # a ÚNICA coisa liberada sem TLS
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://www.empresa.com.br%{REQUEST_URI} [R=301,L]
    #  └ 301 = permanente (em TESTES use 302: o navegador não cacheia)

    ErrorLog "${CFG_LOG}/institucional-redirect-error.log"
</VirtualHost>

# ------------------------------------------------------------
# :443 — o site real
# ------------------------------------------------------------
<VirtualHost *:443>

    # ========================================================
    # SEÇÃO 1 — IDENTIDADE
    # ========================================================
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br
    ServerAdmin ti@empresa.com.br
    DocumentRoot "/var/www/institucional/public"
    #  └ SEMPRE a public/: código, vendor/ e configs ficam FORA da raiz
    #    web — inalcançáveis por URL, sem depender de regra nenhuma
    DirectoryIndex index.php index.html
    AddDefaultCharset UTF-8

    # ========================================================
    # SEÇÃO 2 — TLS ENDURECIDO + HTTP/2
    # ========================================================
    SSLEngine On
    SSLCertificateFile    "${CFG_CERTS}/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "${CFG_CERTS}/empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3   # nada de protocolos legados
    SSLHonorCipherOrder Off              # TLS moderno: o cliente escolhe
    SSLUseStapling On                    # servidor entrega a prova OCSP
                                         # (exige SSLStaplingCache no global)
    <IfModule http2_module>
        Protocols h2 http/1.1            # HTTP/2 com fallback
        # viável porque o PHP é FPM (Seção 4) — mod_php exigiria prefork
    </IfModule>

    # ========================================================
    # SEÇÃO 3 — VARIÁVEIS DE APLICAÇÃO (substituindo o .env)
    # Config por site AQUI; SEGREDOS vêm do ambiente do processo
    # (systemd EnvironmentFile 600) — nunca literais neste arquivo.
    # ========================================================
    SetEnv APP_ENV   "production"
    SetEnv APP_DEBUG "false"
    SetEnv APP_URL   "https://www.empresa.com.br"
    SetEnv DB_HOST   "10.0.0.5"
    SetEnv DB_DATABASE "institucional"
    PassEnv DB_PASSWORD APP_KEY
    #  └ PassEnv copia processo → requisição → FastCGI → $_SERVER no PHP.
    #    Com FPM, leia SEMPRE por $_SERVER (getenv() é loteria de clear_env).

    # ========================================================
    # SEÇÃO 4 — PHP VIA FPM (a boa prática; mod_php é legado)
    # ========================================================
    <FilesMatch "\.php$">
        SetHandler "${CFG_FPM}"
        #  └ expande p/ proxy:unix:/run/php-fpm/www.sock|fcgi://localhost
        #    FPM = processo separado → MPM event → HTTP/2 → escala
    </FilesMatch>

    # ========================================================
    # SEÇÃO 5 — MARCAS (classificar na ENTRADA; dirigem tudo abaixo)
    # ========================================================
    SetEnvIf Request_URI "\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$" estatico
    SetEnvIf Request_URI "\.[0-9a-f]{8,}\.(css|js|woff2)$" imutavel
    #  └ asset com hash no nome (app.3f9a1c.css): mudou? o NOME muda
    SetEnvIf Request_URI "^/(health|ping)$" sonda nolog
    SetEnvIf Cookie "app_session=" tem_sessao no-cache
    #  └ tem_sessao dirige o Cache-Control; no-cache é CONTRATO com o
    #    mod_cache: logado NUNCA alimenta nem come o cache compartilhado
    SetEnvIf Remote_Addr "^192\.168\.10\." equipe
    SetEnvIf Request_URI "\.(png|jpe?g|webp|zip|gz|woff2)$" no-gzip
    #  └ CONTRATO com o mod_deflate: pré-comprimido não gasta CPU

    # ========================================================
    # SEÇÃO 6 — PERMISSÕES + FRONT CONTROLLER
    # ========================================================
    <Directory "/var/www/institucional/public">
        Options FollowSymLinks           # SEM Indexes: jamais listar pastas
        AllowOverride None               # .htaccess: NÃO EXISTE em produção
        Require all granted              # a única liberação — o resto do
                                         # disco segue negado pelo global

        RewriteEngine On                 # ligar em CADA contexto (rewrite
                                         # não herda entre contextos)
        RewriteCond %{ENV:estatico} !=1          # estático NEM entra no PHP
        RewriteCond %{REQUEST_FILENAME} !-f      # arquivo real? serve direto
        RewriteCond %{REQUEST_FILENAME} !-d      # pasta real? serve direto
        RewriteRule ^(.*)$ index.php [QSA,L]     # resto → ponto único
        #  └ padrão SEM barra inicial (contexto <Directory>);
        #    a app lê a rota de $_SERVER['REQUEST_URI']
    </Directory>

    # health check respondido pela BORDA (o FPM nem acorda):
    RewriteEngine On
    RewriteCond %{ENV:sonda} =1
    RewriteRule ^ - [R=204,L]            # 204: vivo, sem corpo

    # ========================================================
    # SEÇÃO 7 — ARQUIVOS FORA DO DocumentRoot (Alias + Directory:
    # o Alias mapeia ONDE; o Directory autoriza QUEM — Location
    # não substitui nenhum dos dois)
    # ========================================================
    Alias "/manuais" "/dados/manuais"    # PDFs em outro volume
    <Directory "/dados/manuais">
        Options -Indexes                 # servir arquivos ≠ listar a pasta
        AllowOverride None
        Require all granted
        <IfModule mod_headers.c>
            <FilesMatch "\.pdf$">
                Header set Content-Disposition "attachment"   # força download
            </FilesMatch>
        </IfModule>
    </Directory>

    # ========================================================
    # SEÇÃO 8 — PROTEÇÕES (autorização em camadas)
    # ========================================================
    # 8a. Admin: perímetro por origem E identidade E grupo
    <Directory "/var/www/institucional/public/admin">
        AuthType Basic                   # esquema (frontend da autenticação)
        AuthName "Administração"         # texto do prompt + chave de cache
        AuthBasicProvider file           # backend: arquivo de senhas
        AuthUserFile  "${CFG_PASSWD}/institucional.passwd"
        AuthGroupFile "${CFG_PASSWD}/grupos"
        #  └ criados com: htpasswd -B (bcrypt); SEMPRE fora do DocumentRoot

        <RequireAll>                     # AND: todas simultâneas
            <RequireAny>                 # OR: escritório OU VPN
                Require ip 192.168.10
                Require ip 10.8.0
            </RequireAny>
            Require group admins         # identidade + grupo (escala melhor
                                         # que listar usuários na config)
        </RequireAll>
        # Basic = Base64 (reversível): só aceitável porque o vhost é 100% TLS
    </Directory>

    # 8b. Sub-área crítica: TUDO acima E horário comercial (Require expr)
    <Directory "/var/www/institucional/public/admin/config">
        AuthMerging And                  # SEM isto, o Require daqui APAGARIA
                                         # a proteção herdada do pai (merge de
                                         # authz SUBSTITUI, não acumula!)
        Require expr "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"
        #  └ -ge/-lt = comparação NUMÉRICA (>/< seriam lexicográficas!)
    </Directory>

    # 8c. Uploads: servidos, JAMAIS executados
    <Directory "/var/www/institucional/public/uploads">
        Options -Indexes -ExecCGI
        <FilesMatch "\.ph(ar|p[0-9]?|tml)$">
            SetHandler none              # desarma o FPM p/ o que gravarem aqui
            Require all denied           # e nem o fonte é servido
        </FilesMatch>
    </Directory>

    # 8d. Métodos: só o que o site usa (via <If> — <Limit> só cobre
    #     os métodos LISTADOS; os exóticos escapariam)
    <If "%{REQUEST_METHOD} !in { 'GET', 'HEAD', 'POST' }">
        Require all denied
    </If>

    # 8e. Anti-abuso barato: corpo limitado (ajuste ao upload real)
    <Directory "/var/www/institucional/public">
        LimitRequestBody 10485760        # 10 MB
    </Directory>

    # 8f. server-status: handler VIRTUAL (não há arquivo) → <Location>
    <Location "/status">
        SetHandler server-status
        Require local                    # só a própria máquina
        #  └ Location é PARA ISTO (URL sem filesystem); jamais p/
        #    "proteger" arquivos — URL alternativa o contorna
    </Location>

    # ========================================================
    # SEÇÃO 9 — CACHE DE SERVIDOR (mod_cache: poupa o FPM)
    # ========================================================
    <IfModule mod_cache.c>
        CacheEnable disk "/api/publica"  # SÓ o comprovadamente compartilhável
        CacheEnable disk "/manuais"
        CacheDisable "/admin"            # jamais área protegida
        # a marca no-cache (Seção 5) já exclui usuários logados;
        # o backend GOVERNA via Cache-Control da resposta (s-maxage p/
        # o TTL do servidor, max-age p/ o do navegador)
        CacheHeader On                   # X-Cache: HIT/MISS — diagnóstico
        CacheDetailHeader On             # X-Cache-Detail: o MOTIVO
    </IfModule>

    # ========================================================
    # SEÇÃO 10 — CONTEÚDO: compressão, cache do cliente, cookies
    # ========================================================
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
        AddOutputFilterByType DEFLATE application/javascript application/json
        AddOutputFilterByType DEFLATE image/svg+xml
        # (mod_deflate emite Vary: Accept-Encoding sozinho — se algum
        #  Header mexer em Vary, use MERGE, nunca set)
    </IfModule>

    FileETag MTime Size                  # validador estável entre servidores
                                         # (INode quebraria o 304 no balancer)

    <IfModule mod_headers.c>
        # a TABELA DE OURO do cache, dirigida pelas marcas da Seção 5:
        Header set Cache-Control "public, max-age=31536000, immutable" env=imutavel
        #  └ versionado por hash: um ano, sem nem revalidar no F5
        Header set Cache-Control "public, max-age=86400" \
            "expr=reqenv('estatico') == '1' && reqenv('imutavel') != '1'"
        #  └ estático sem versão: 1 dia de frescor + 304 depois
        Header set Cache-Control "private, no-cache" env=tem_sessao
        #  └ logado: só cache privado, revalidando sempre
        #    (no-cache ≠ no-store: no-cache mantém o 304 barato;
        #     no-store re-baixa TUDO — só p/ dados sensíveis)

        # estático SEM cookies — requisições menores nas duas direções:
        RequestHeader unset Cookie env=estatico      # cliente→servidor
        Header unset Set-Cookie env=estatico         # servidor→cliente

        # preload: navegador baixa os assets ANTES de parsear o HTML
        Header add Link "</css/app.3f9a1c.css>; rel=preload; as=style" \
            "expr=resp('Content-Type') =~ m#text/html#"
        Header add Link "</js/app.8b2d4e.js>; rel=preload; as=script" \
            "expr=resp('Content-Type') =~ m#text/html#"

        # telemetria SÓ para a equipe (invisível ao público):
        Header set X-Request-Id  "%{UNIQUE_ID}e" env=equipe
        Header set X-Tempo-Borda "%D us"         env=equipe
    </IfModule>

    # ========================================================
    # SEÇÃO 11 — SEGURANÇA DE CONTEÚDO + PÁGINAS DE ERRO (macros!)
    # ========================================================
    Use HeadersSeguranca                 # o dossiê completo do Bloco 1
    Use ErroDocs                         # as 11 páginas de erro do Bloco 1
    <Directory "/var/www/institucional/public/erros">
        Require all granted              # as páginas de erro precisam estar
        <IfModule mod_headers.c>         # acessíveis a QUALQUER um…
            Header set Cache-Control "no-store"   # …e nunca cacheadas
        </IfModule>
    </Directory>

    # ========================================================
    # SEÇÃO 12 — MODO MANUTENÇÃO (arquivo-flag: touch/rm, sem reload)
    # ========================================================
    RewriteEngine On
    RewriteCond /var/www/institucional/MAINT -f     # a flag existe?
    RewriteCond %{ENV:equipe} !=1                   # equipe segue navegando
    RewriteCond %{REQUEST_URI} !^/manutencao\.html$ # a página passa
    RewriteCond %{REQUEST_URI} !\.(css|png|svg)$    # e os assets dela (anti-loop)
    RewriteRule ^ /manutencao.html [R=503,L]        # 503 = temporário p/ bots
    <IfModule mod_headers.c>
        Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
    </IfModule>

    # ========================================================
    # SEÇÃO 13 — LOGS (dedicados, condicionais, rastreáveis)
    # ========================================================
    ErrorLog  "${CFG_LOG}/institucional-error.log"
    LogLevel  warn
    #  └ depurar rewrite: "warn rewrite:trace3" — ligar, reproduzir, DESLIGAR
    CustomLog "${CFG_LOG}/institucional-access.log" rastreavel env=!nolog
    #  └ com %D + UNIQUE_ID + APP_ENV; sondas de health fora (nolog)
    CustomLog "${CFG_LOG}/institucional-static.log" common env=estatico
    #  └ estáticos separados: volume alto, valor analítico baixo
</VirtualHost>
```

---

## BLOCO 4 — API/BACKEND (proxy com fronteira de confiança)

```apache
# ============================================================
# conf/sites/40-api.conf — Laravel/Node em 127.0.0.1:8000;
# o Apache é a borda TLS, o porteiro, o cache e o rastreador.
# ============================================================
<VirtualHost *:443>
    ServerName api.empresa.com.br

    SSLEngine On
    SSLCertificateFile    "${CFG_CERTS}/api.empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "${CFG_CERTS}/api.empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    <IfModule http2_module>
        Protocols h2 http/1.1
    </IfModule>

    # ---------- PROXY (webspace → backend: não há filesystem,
    # por isso o controle é <Location>, nunca <Directory>) ----------
    ProxyRequests Off                    # JAMAIS proxy aberto de saída
    ProxyPreserveHost On                 # Host original → URLs corretas na app
    ProxyTimeout 30                      # falhar rápido > enfileirar

    ProxyPass        "/" "http://127.0.0.1:8000/"
    ProxyPassReverse "/" "http://127.0.0.1:8000/"
    #  └ ProxyPassReverse reescreve Location/cookies das RESPOSTAS:
    #    sem ele, um redirect da app vazaria http://127.0.0.1:8000

    <Location "/health">
        ProxyPass "!"                    # "!" = esta URL NÃO vai ao backend
        Require all granted
    </Location>

    # ---------- FRONTEIRA DE CONFIANÇA: sanitizar ANTES (early),
    # escrever DEPOIS — nunca aceite identidade de rede do cliente ----------
    <IfModule mod_headers.c>
        RequestHeader unset X-Request-Id      early
        RequestHeader unset X-Forwarded-Proto early
        #  └ early = primeira fase: apaga ANTES de qualquer decisão
        RequestHeader set X-Request-Id      "%{UNIQUE_ID}e"
        #  └ a MESMA chave no access log da borda e no log da aplicação:
        #    uma string costura o caminho inteiro de cada requisição
        RequestHeader set X-Forwarded-Proto "https"
        #  └ a app sabe que a borda é TLS (evita links http:// gerados)
    </IfModule>

    # ---------- CORS (API consumida por frontends em OUTROS domínios) ----------
    SetEnvIf Origin "^https://(app|painel)\.empresa\.com\.br$" origem_ok=$0
    #  └ whitelist ECOADA (=$0 captura a origem casada) — nunca "*"
    #    quando há credenciais (proibido pela spec)
    <IfModule mod_headers.c>
        Header always set Access-Control-Allow-Origin      "%{origem_ok}e" env=origem_ok
        Header always set Access-Control-Allow-Credentials "true"          env=origem_ok
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" env=origem_ok
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization" env=origem_ok
        Header always set Access-Control-Max-Age "600" env=origem_ok
        Header merge Vary "Origin"       # caches PRECISAM saber da variação
    </IfModule>
    # preflight respondido pela BORDA (backend nem é tocado):
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} =OPTIONS
    RewriteCond %{ENV:origem_ok} !=""
    RewriteRule ^ - [R=204,L]

    # ---------- controle de acesso da URL proxificada ----------
    <Location "/">
        <RequireAny>
            Require ip 192.168.10        # rede interna
            Require ip 200.200.200.200   # parceiro por IP fixo
        </RequireAny>
    </Location>

    Use HeadersSeguranca
    Use ErroDocs

    ErrorLog  "${CFG_LOG}/api-error.log"
    CustomLog "${CFG_LOG}/api-access.log" rastreavel
</VirtualHost>

# higiene de parse: Defines são GLOBAIS — limpar evita vazamento
# p/ o próximo arquivo incluído (se este for o último, é opcional)
# UnDefine CFG_LOG
# UnDefine CFG_CERTS
# UnDefine CFG_PASSWD
# UnDefine CFG_FPM
```

---

## BLOCO 5 — OPERAÇÃO

```bash
# ---------- o ritual de TODA mudança (inegociável) ----------
httpd -t                     # 1. sintaxe (inclui as macros EXPANDIDAS)
httpd -S                     # 2. mapa: defaults, nomes, arquivo:linha
httpd -M | grep -E 'proxy_fcgi|http2|cache|macro'   # 3. módulos lá?
apachectl graceful           # 4. reload sem derrubar conexões
curl -IL http://empresa.com.br/           # 5. cadeia 301 → 200?
curl -I  https://www.empresa.com.br/      #    headers de segurança presentes?
curl -I  https://www.empresa.com.br/erros/404.html   # páginas de erro servem?
curl -sI https://api.empresa.com.br/api/publica/x | grep -i x-cache  # HIT/MISS?
curl -I -u user:senha https://www.empresa.com.br/admin/   # 200 autenticado?
tail -f /var/log/httpd/*-error.log        # 6. observar o pós-mudança
```

**O que esta versão tem a mais que o template anterior:** PHP-FPM via `proxy_fcgi` (habilitando MPM event + HTTP/2), variáveis substituindo o `.env` (Seção 3), **macros** eliminando duplicação (headers e as **11 páginas de erro** com o detalhe do 401 local e do bloco `<Directory>` das próprias páginas), cache de **servidor** com governança pelo backend, `AuthMerging` na sub-área (o erro silencioso do merge de authz), `Require expr` por horário, OCSP stapling, CORS completo com preflight na borda, preload de assets, e a fronteira de confiança com `early`. E a postura explícita do título: **`.htaccess` desativado — em servidor próprio, ele não participa.**

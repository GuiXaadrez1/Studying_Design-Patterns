# Manual Consolidado: httpd-vhosts.conf de Produção — Boas Práticas
## Estrutura, Ordem, Front Controller, Proteção, Containers e Otimização — Tudo Comentado

*Documento-síntese da série de referência Apache 2.4. Consolida os guias anteriores (httpd.conf, vhosts, .htaccess, autorização, containers, redirecionamento/reescrita, cache, variáveis e headers) num manual único de produção. **Todos os blocos são comentados explicando o que cada coisa faz** — este arquivo foi escrito para ser copiado, adaptado e entendido seis meses depois.*

---

## PARTE 1 — OS PRINCÍPIOS (o porquê antes do como)

### 1.1 As dez regras que governam tudo

```
 1. Porta pertence ao SERVIDOR (Listen global), não ao site.
 2. UM vhost é selecionado por requisição: porta primeiro, nome depois;
    sem casamento de nome, vence o PRIMEIRO vhost declarado do par (default).
 3. AllowOverride None em produção: toda regra no vhost — validável com
    httpd -t, lida uma vez no boot, imune a .htaccess plantado.
 4. Tudo nasce NEGADO (política defensiva global) e cada vhost LIBERA
    explicitamente só o que precisa.
 5. <Directory>/<Files> enxergam DISCO; <Location> enxerga URL.
    Arquivo se protege onde mora (Directory); Location é para o que
    só existe como URL (proxy, handler).
 6. A ordem importa DUAS vezes: no PARSE (sequencial — LoadModule/Define/
    Include/1º vhost/regras do mesmo módulo) e no MERGE (constante fixa:
    Directory → Files → Location → If — imune à posição no arquivo).
 7. Reescrever NÃO é autorizar: merge e Require agem sobre o alvo FINAL.
 8. Classifique na ENTRADA (SetEnvIf), decida em TODO lugar (rewrite,
    cache, headers, logs, authz) — um vocabulário de marcas por vhost.
 9. Segredos no ambiente do PROCESSO; config por site no vhost;
    nunca segredo literal na configuração.
10. Nenhuma mudança sem: httpd -t → httpd -S → graceful → curl → tail no log.
```

### 1.2 A ordem de estruturação — o que vem primeiro

**No httpd.conf (global) — pré-requisitos, nesta sequência:**

```apache
# ---------------------------------------------------------------
# 1º) PORTAS — uma linha Listen por porta usada em QUALQUER vhost.
#     Sem o Listen, o vhost existe mas nunca recebe conexão.
# ---------------------------------------------------------------
Listen 80
Listen 443

# ---------------------------------------------------------------
# 2º) MÓDULOS — LoadModule ANTES de qualquer diretiva que os use
#     (o parse é sequencial: diretiva de módulo não carregado = erro).
# ---------------------------------------------------------------
LoadModule rewrite_module        modules/mod_rewrite.so   # reescrita de URL
LoadModule ssl_module            modules/mod_ssl.so       # TLS
LoadModule socache_shmcb_module  modules/mod_socache_shmcb.so  # cache de sessão TLS
LoadModule headers_module        modules/mod_headers.so   # manipular cabeçalhos
LoadModule deflate_module        modules/mod_deflate.so   # compressão gzip
LoadModule expires_module        modules/mod_expires.so   # cabeçalhos de cache
LoadModule setenvif_module       modules/mod_setenvif.so  # marcas condicionais
LoadModule unique_id_module      modules/mod_unique_id.so # id único p/ rastreio
LoadModule authz_core_module     modules/mod_authz_core.so    # Require all/env/expr
LoadModule authz_host_module     modules/mod_authz_host.so    # Require ip/local
LoadModule authz_user_module     modules/mod_authz_user.so    # Require user/valid-user
LoadModule auth_basic_module     modules/mod_auth_basic.so    # esquema Basic
LoadModule authn_file_module     modules/mod_authn_file.so    # senhas em arquivo
LoadModule proxy_module          modules/mod_proxy.so         # base do proxy
LoadModule proxy_http_module     modules/mod_proxy_http.so    # proxy p/ backends HTTP
LoadModule http2_module          modules/mod_http2.so         # HTTP/2

# ---------------------------------------------------------------
# 3º) IDENTIDADE E POSTURA do servidor
# ---------------------------------------------------------------
ServerName servidor.empresa.com.br:80   # evita o aviso AH00558 no boot
ServerTokens Prod          # header "Server: Apache" sem versão (menos recon)
ServerSignature Off        # sem rodapé identificando o servidor em erros
TraceEnable Off            # desliga método TRACE (mitiga cross-site tracing)

# ---------------------------------------------------------------
# 4º) POLÍTICA DEFENSIVA DE BASE — tudo nasce negado (regra nº 4).
#     Cada vhost liberará SÓ o seu DocumentRoot.
# ---------------------------------------------------------------
<Directory "/">
    AllowOverride None     # nenhum .htaccess é sequer lido (regra nº 3)
    Require all denied     # veredito padrão: 403 para tudo
</Directory>

# Metadados/segredos NUNCA servidos, em NENHUM site (proteção transversal):
<DirectoryMatch "/\.(git|svn|hg)">     # pastas de controle de versão
    Require all denied
</DirectoryMatch>
<FilesMatch "^\.(env|htaccess|htpasswd|user\.ini)|\.(bak|old|orig|sql|swp|log)$">
    Require all denied     # dotfiles e artefatos de edição/backup/dump
</FilesMatch>

# ---------------------------------------------------------------
# 5º) FORMATOS DE LOG nomeados (os vhosts referenciam pelo apelido)
# ---------------------------------------------------------------
LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
# formato de produção: + %D (duração em µs) e o UNIQUE_ID p/ rastreio ponta-a-ponta
LogFormat "%h %t \"%r\" %>s %b %D uid=%{UNIQUE_ID}e \"%{User-Agent}i\"" rastreavel

# ---------------------------------------------------------------
# 6º) VHOSTS POR ÚLTIMO — um arquivo por site, ordem controlada
#     por prefixo numérico (Include processa em ordem alfabética,
#     e o 1º vhost de cada porta é o default — regra nº 2).
# ---------------------------------------------------------------
IncludeOptional conf/sites/*.conf
# IncludeOptional (não Include): não quebra o boot se a pasta estiver vazia
```

**A árvore de arquivos resultante:**

```
conf/
├── httpd.conf                 # o bootstrap acima
└── sites/
    ├── 00-default.conf        # catch-all — o prefixo 00 GARANTE que é o default
    ├── 10-institucional.conf  # par :80 (redirect) + :443 (site)
    ├── 20-blog.conf
    └── 40-api.conf            # vhost proxy p/ backend
```

---

## PARTE 2 — O VHOST DEFAULT (o porteiro)

```apache
# ================= conf/sites/00-default.conf =================
# POR QUE EXISTE: sem ele, o primeiro site "de verdade" vira o default
# e passa a atender acesso por IP direto, scanners e domínios órfãos
# apontados ao servidor. Este bloco captura esse tráfego anômalo.

<VirtualHost *:80>
    ServerName default.invalid          # nome impossível: nunca casa legitimamente
    DocumentRoot "/var/www/default"     # pasta mínima (pode até ficar vazia)

    <Directory "/var/www/default">
        AllowOverride None
        Require all granted             # precisa liberar p/ o RedirectMatch responder
    </Directory>

    RedirectMatch 404 "^"               # postura hostil: TODA URL responde 404
                                        # (scanner não descobre nada por aqui)

    # log separado = sensor de reconhecimento: tudo que cair aqui é anômalo
    ErrorLog  "/var/log/httpd/default-error.log"
    CustomLog "/var/log/httpd/default-access.log" combined
</VirtualHost>

# espelho na :443 (com um certificado qualquer válido do servidor),
# para que HTTPS por IP direto também caia no porteiro:
<VirtualHost *:443>
    ServerName default.invalid
    DocumentRoot "/var/www/default"
    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/empresa.com.br/privkey.pem"
    <Directory "/var/www/default">
        AllowOverride None
        Require all granted
    </Directory>
    RedirectMatch 404 "^"
    ErrorLog "/var/log/httpd/default-ssl-error.log"
</VirtualHost>
```

---

## PARTE 3 — O VHOST DE PRODUÇÃO COMPLETO (o modelo)

Este é o template consolidado — cada seção numerada, cada linha explicada. A **ordem interna** segue a lógica: identidade → TLS → marcas → permissões/roteador → proteções → conteúdo (cache/compressão/headers) → integrações → logs.

```apache
# ================= conf/sites/10-institucional.conf =================

# ---------------------------------------------------------------
# BLOCO :80 — NÃO serve conteúdo. Existe SÓ para: (a) o desafio ACME
# da renovação de certificado, que precisa passar em HTTP puro; e
# (b) empurrar todo o resto para HTTPS com UM único 301.
# ---------------------------------------------------------------
<VirtualHost *:80>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br

    # exceção ACME: o Let's Encrypt valida o domínio buscando um arquivo aqui
    Alias "/.well-known/acme-challenge" "/var/www/acme/.well-known/acme-challenge"
    <Directory "/var/www/acme/.well-known/acme-challenge">
        AllowOverride None
        Require all granted             # única coisa liberada em HTTP puro
    </Directory>

    RewriteEngine On                    # o motor nasce desligado — ligar sempre
    # tudo que NÃO é o desafio ACME → HTTPS canônico (301 = permanente;
    # durante testes use 302, que o navegador não cacheia)
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://www.empresa.com.br%{REQUEST_URI} [R=301,L]

    ErrorLog "/var/log/httpd/institucional-redirect-error.log"
</VirtualHost>

# ---------------------------------------------------------------
# BLOCO :443 — o site real
# ---------------------------------------------------------------
<VirtualHost *:443>

    # ============================================================
    # SEÇÃO 1 — IDENTIDADE (quem sou, o que sirvo)
    # ============================================================
    ServerName  www.empresa.com.br      # nome canônico (casamento por nome)
    ServerAlias empresa.com.br          # também atendo o domínio nu
    ServerAdmin ti@empresa.com.br       # contato exibido em erros padrão
    DocumentRoot "/var/www/institucional/public"
    # ↑ SEMPRE a pasta public/ da aplicação — código, .env e vendor/
    #   ficam FORA da raiz web, inalcançáveis por URL (arquitetura segura)

    DirectoryIndex index.php index.html # o que servir quando a URL é um diretório
    AddDefaultCharset UTF-8             # charset padrão p/ text/html sem declaração

    # ============================================================
    # SEÇÃO 2 — TLS ENDURECIDO + HTTP/2
    # ============================================================
    SSLEngine On                        # liga TLS neste vhost
    SSLCertificateFile    "/etc/letsencrypt/live/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3  # remove protocolos legados inseguros
    SSLHonorCipherOrder Off             # em TLS moderno, o cliente escolhe a cifra

    <IfModule http2_module>             # <IfModule>: só compila se o módulo existe
        Protocols h2 http/1.1           # HTTP/2 com fallback p/ 1.1
        # ⚠ h2 exige MPM event/worker — com PHP, use PHP-FPM (não mod_php)
    </IfModule>

    # ============================================================
    # SEÇÃO 3 — MARCAS (classificar na ENTRADA — regra nº 8)
    # Estas variáveis dirigem rewrite, cache, cookies, headers e logs
    # nas seções seguintes. SetEnvIf roda cedo: as marcas valem p/ tudo.
    # ============================================================
    SetEnvIf Request_URI "\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$" estatico
    #  └ marca todo asset estático (decide roteador, cookie, cache, log)
    SetEnvIf Request_URI "\.[0-9a-f]{8,}\.(css|js|woff2)$" imutavel
    #  └ asset com hash de versão no nome (app.3f9a1c.css) → cache eterno
    SetEnvIf Request_URI "^/(health|ping)$" sonda nolog
    #  └ health checks: respondidos na borda e fora dos logs
    SetEnvIf Cookie "app_session=" tem_sessao
    #  └ usuário logado: nunca cacheado de forma compartilhada
    SetEnvIf Remote_Addr "^192\.168\.10\." equipe
    #  └ rede interna: recebe telemetria de debug invisível ao público
    SetEnvIf Request_URI "\.(png|jpe?g|webp|zip|gz|woff2)$" no-gzip
    #  └ CONTRATO com mod_deflate: já-comprimidos não gastam CPU de gzip

    # ============================================================
    # SEÇÃO 4 — PERMISSÕES + FRONT CONTROLLER (o roteador)
    # ============================================================
    <Directory "/var/www/institucional/public">
        Options FollowSymLinks          # SEM Indexes: jamais listar diretórios
        AllowOverride None              # .htaccess ignorado (regra nº 3)
        Require all granted             # a ÚNICA liberação — o resto do disco
                                        # continua negado pela política global

        RewriteEngine On                # ligar em CADA contexto que reescreve
                                        # (config de rewrite não é herdada)

        # O FRONT CONTROLLER, na forma canônica:
        RewriteCond %{ENV:estatico} !=1         # 1) estático NEM entra no PHP
        RewriteCond %{REQUEST_FILENAME} !-f     # 2) arquivo real? serve direto
        RewriteCond %{REQUEST_FILENAME} !-d     # 3) pasta real? serve direto
        RewriteRule ^(.*)$ index.php [QSA,L]    # 4) o resto → ponto único
        #  └ padrão SEM barra inicial (contexto <Directory>);
        #    [QSA] preserva a query string; [L] encerra a rodada.
        #    A aplicação lê a rota de $_SERVER['REQUEST_URI'].
    </Directory>

    # sonda de health-check respondida PELA BORDA (o PHP nem acorda):
    RewriteEngine On                    # (contexto do vhost: ligar de novo)
    RewriteCond %{ENV:sonda} =1
    RewriteRule ^ - [R=204,L]           # 204 No Content: vivo, sem corpo

    # ============================================================
    # SEÇÃO 5 — PROTEÇÕES (autorização em camadas)
    # ============================================================
    # 5a. Área administrativa: perímetro por origem + identidade
    <Directory "/var/www/institucional/public/admin">
        AuthType Basic                  # esquema: prompt nativo do navegador
        AuthName "Administração"        # texto do prompt (e chave de cache)
        AuthBasicProvider file          # backend: arquivo de senhas
        AuthUserFile "/etc/httpd/passwd/institucional.passwd"
        # ↑ arquivo de senhas SEMPRE fora do DocumentRoot
        #   (criado com: htpasswd -B -c <arquivo> <usuario>)

        <RequireAll>                    # AND lógico: TODAS as condições
            <RequireAny>                # OR interno: escritório OU VPN…
                Require ip 192.168.10
                Require ip 10.8.0
            </RequireAny>
            Require valid-user          # …E credencial válida
        </RequireAll>
        # Basic transmite em Base64 (reversível) — só é aceitável porque
        # este vhost é 100% TLS. Basic sem HTTPS = senha em texto claro.
    </Directory>

    # 5b. Uploads: servidos, JAMAIS executados (mesmo que gravem um .php)
    <Directory "/var/www/institucional/public/uploads">
        Options -Indexes -ExecCGI       # sem listagem, sem CGI
        <FilesMatch "\.ph(ar|p[0-9]?|tml)$">
            SetHandler none             # desarma o handler PHP aqui
            Require all denied          # e nem o código-fonte é servido
        </FilesMatch>
    </Directory>

    # 5c. Métodos HTTP: só o que a aplicação usa (o resto = 403)
    <If "%{REQUEST_METHOD} !in { 'GET', 'HEAD', 'POST' }">
        Require all denied
        # <If> em vez de <Limit>: Limit só cobre métodos LISTADOS —
        # os não-listados escapam (alçapão clássico). A expressão não.
    </If>

    # 5d. Corpo de requisição limitado (anti-abuso barato)
    <Directory "/var/www/institucional/public">
        LimitRequestBody 10485760       # 10 MB — ajuste ao upload real do site
    </Directory>

    # ============================================================
    # SEÇÃO 6 — OTIMIZAÇÃO DE CONTEÚDO (cache, compressão, cookies)
    # ============================================================
    # 6a. Compressão — só texto (o contrato no-gzip da Seção 3 já
    #     poupou os pré-comprimidos)
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
        AddOutputFilterByType DEFLATE application/javascript application/json
        AddOutputFilterByType DEFLATE image/svg+xml
    </IfModule>

    # 6b. Política de cache do CLIENTE — a "tabela de ouro", por marca:
    <IfModule mod_headers.c>
        # asset versionado por hash: imutável por 1 ano (mudou? o NOME muda)
        Header set Cache-Control "public, max-age=31536000, immutable" env=imutavel
        # estático sem versão: 1 dia de frescor + revalidação por ETag/304
        Header set Cache-Control "public, max-age=86400" \
            "expr=reqenv('estatico') == '1' && reqenv('imutavel') != '1'"
        # usuário logado: cache só privado, sempre revalidando
        Header set Cache-Control "private, no-cache" env=tem_sessao
        # (no-cache ≠ no-store: no-cache ainda permite o 304 barato;
        #  no-store re-baixa TUDO sempre — reserve p/ dados sensíveis)
    </IfModule>
    FileETag MTime Size                 # validador estável entre servidores
                                        # (NUNCA INode atrás de balanceador)

    # 6c. Estático sem cookies — requisições menores nas DUAS direções:
    <IfModule mod_headers.c>
        RequestHeader unset Cookie env=estatico     # cliente→servidor: sem quilos
                                                    # de cookie p/ buscar um .png
        Header unset Set-Cookie env=estatico        # servidor→cliente: estático
                                                    # jamais seta cookie
    </IfModule>

    # ============================================================
    # SEÇÃO 7 — HEADERS DE SEGURANÇA (sempre "always": valem até nos
    # erros 4xx/5xx — a tabela padrão abandona as páginas de erro)
    # ============================================================
    <IfModule mod_headers.c>
        # HSTS: navegador exige HTTPS por 1 ano (só emitir sobre TLS!)
        Header always set Strict-Transport-Security \
            "max-age=31536000; includeSubDomains" "expr=%{HTTPS} == 'on'"
        Header always set X-Content-Type-Options "nosniff"   # sem sniffing de tipo
        Header always set X-Frame-Options "SAMEORIGIN"       # anti-clickjacking
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always unset X-Powered-By      # apaga o carimbo de versão do PHP

        # telemetria SÓ para a equipe (marca da Seção 3 — invisível ao público):
        Header set X-Request-Id  "%{UNIQUE_ID}e" env=equipe   # rastreio ponta-a-ponta
        Header set X-Tempo-Borda "%D us"         env=equipe   # duração na borda
    </IfModule>

    # ============================================================
    # SEÇÃO 8 — MODO MANUTENÇÃO (liga/desliga por arquivo, sem reload)
    # ============================================================
    RewriteEngine On
    RewriteCond /var/www/institucional/MAINT -f    # existe o arquivo-flag?
                                                   # (touch MAINT / rm MAINT)
    RewriteCond %{ENV:equipe} !=1                  # a equipe continua navegando
    RewriteCond %{REQUEST_URI} !^/manutencao\.html$   # a própria página passa
    RewriteCond %{REQUEST_URI} !\.(css|png|svg)$      # e os assets dela também
    RewriteRule ^ /manutencao.html [R=503,L]       # 503 = "temporário" p/ buscadores
    ErrorDocument 503 /manutencao.html
    <IfModule mod_headers.c>
        Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
        # ↑ instrui clientes/bots a voltarem em 1h
    </IfModule>

    # ============================================================
    # SEÇÃO 9 — PÁGINAS DE ERRO do site
    # ============================================================
    ErrorDocument 404 /erros/404.html
    ErrorDocument 500 /erros/500.html

    # ============================================================
    # SEÇÃO 10 — LOGS (dedicados, condicionais, rastreáveis)
    # ============================================================
    ErrorLog  "/var/log/httpd/institucional-error.log"
    LogLevel  warn                      # p/ depurar rewrite: warn rewrite:trace3
                                        # (ligar, reproduzir, DESLIGAR — pesa muito)
    CustomLog "/var/log/httpd/institucional-access.log" rastreavel env=!nolog
    #  └ formato com %D e UNIQUE_ID (Parte 1); sondas de health fora (nolog)
    CustomLog "/var/log/httpd/institucional-static.log" common env=estatico
    #  └ estáticos em arquivo próprio: volume alto, valor analítico baixo
</VirtualHost>
```

---

## PARTE 4 — O VHOST DE BACKEND (proxy para aplicação)

```apache
# ================= conf/sites/40-api.conf =================
# CENÁRIO: aplicação (Laravel/Node) rodando em 127.0.0.1:8000;
# o Apache é a borda TLS, o porteiro e o otimizador.

<VirtualHost *:443>
    ServerName api.empresa.com.br

    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/api.empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/api.empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3

    # ============================================================
    # PROXY — a tradução webspace → backend (não há filesystem aqui,
    # por isso o controle usa <Location>, nunca <Directory>)
    # ============================================================
    ProxyRequests Off               # JAMAIS proxy aberto de saída (segurança)
    ProxyPreserveHost On            # o Host original chega ao backend
                                    # (Laravel usa p/ gerar URLs corretas)
    ProxyTimeout 30                 # falhar rápido > enfileirar requisições

    ProxyPass        "/" "http://127.0.0.1:8000/"   # ida: tudo → backend
    ProxyPassReverse "/" "http://127.0.0.1:8000/"   # volta: reescreve Location/
                                                    # cookies das RESPOSTAS p/ a
                                                    # URL pública (sem isto, um
                                                    # redirect do Laravel vazaria
                                                    # http://127.0.0.1:8000)

    # health check respondido pela borda — o backend nem é tocado:
    <Location "/health">
        ProxyPass "!"               # "!": esta URL NÃO vai ao proxy
        Require all granted
    </Location>

    # ============================================================
    # FRONTEIRA DE CONFIANÇA — sanitizar ANTES, escrever DEPOIS
    # ============================================================
    <IfModule mod_headers.c>
        # 1) EARLY: apaga headers de contexto vindos DO CLIENTE — um atacante
        #    pode enviá-los prontos p/ se passar por "interno":
        RequestHeader unset X-Request-Id     early
        RequestHeader unset X-Forwarded-Proto early
        # 2) a borda escreve os valores em que ELA confia:
        RequestHeader set X-Request-Id     "%{UNIQUE_ID}e"  # rastreio p/ o backend
        RequestHeader set X-Forwarded-Proto "https"         # backend sabe que a
                                                            # borda é TLS (evita
                                                            # links http:// gerados)
        # (X-Forwarded-For com o IP real é injetado pelo mod_proxy sozinho)
    </IfModule>

    # ============================================================
    # CONTROLE DE ACESSO da URL proxificada (<Location> — regra nº 5)
    # ============================================================
    <Location "/">
        <RequireAny>
            Require ip 192.168.10           # rede interna
            Require ip 200.200.200.200      # parceiro externo por IP fixo
        </RequireAny>
    </Location>

    ErrorLog  "/var/log/httpd/api-error.log"
    CustomLog "/var/log/httpd/api-access.log" rastreavel
</VirtualHost>
```

---

## PARTE 5 — RESUMO OPERACIONAL

### 5.1 O checklist de toda mudança (regra nº 10 — inegociável)

```bash
httpd -t                        # 1. type-checker: Syntax OK ou não prossegue
httpd -S                        # 2. mapa: portas, defaults e nomes esperados?
                                #    (arquivo:linha de cada vhost — confere a
                                #     ordem dos prefixos numéricos)
httpd -M | grep -E 'rewrite|ssl|headers|proxy'   # 3. módulos realmente lá?
apachectl graceful              # 4. reload SEM derrubar conexões ativas
curl -I  https://www.empresa.com.br/             # 5. smoke test por vhost:
curl -IL http://www.empresa.com.br/              #    (cadeia: 301 → 200?)
curl -I  https://api.empresa.com.br/health       #    (204/200 da borda?)
tail -f /var/log/httpd/*-error.log               # 6. observar o pós-mudança
```

### 5.2 Onde cada decisão mora — a tabela de responsabilidades

| Decisão | Ferramenta | Onde no template |
|---|---|---|
| Que porta escuta | `Listen` | httpd.conf (Parte 1) |
| Quem é o default da porta | ordem dos includes (`00-`) | Parte 2 |
| Roteamento p/ a aplicação | RewriteRule em `<Directory>` | Seção 4 |
| Roteamento p/ backend externo | `ProxyPass` + `<Location>` | Parte 4 |
| Arquivos fora do DocumentRoot | `Alias` + `<Directory>` do destino | (guia de containers) |
| Quem pode acessar arquivos | `Require` em `<Directory>`/`<Files>` | Seções 5a-5b |
| Quem pode acessar URLs virtuais | `Require` em `<Location>` | Parte 4 |
| Lógica de autorização | `<RequireAll/Any/None>` | Seção 5a |
| Condicional por requisição | `<If "expr">` / `expr=` / `env=` | Seções 5c, 6b, 7 |
| Condicional por ambiente/build | `<IfModule>` / `<IfDefine>` | transversal |
| Classificação de requisições | `SetEnvIf` (marcas) | Seção 3 |
| Cache do cliente | `Header set Cache-Control` + `FileETag` | Seção 6b |
| Segurança de conteúdo | `Header always set …` | Seção 7 |
| Rastreabilidade | `UNIQUE_ID` → header + log | Seções 7, 10, Parte 4 |

### 5.3 Os erros que este template já previne

```
✔ 403 em vhost novo            → cada DocumentRoot tem seu <Directory> granted
✔ site errado atendendo         → 00-default.conf + prefixos numéricos
✔ .htaccess fantasma            → AllowOverride None em tudo
✔ loop de redirect              → exceção ACME + condições no modo manutenção
✔ HSTS sumindo no 404           → todos os headers de segurança com "always"
✔ upload .php executado         → SetHandler none + denied em /uploads
✔ senha Basic em texto claro    → Basic só existe no vhost :443
✔ Location "protegendo" arquivo → proteção de disco sempre em <Directory>
✔ cache envenenado por sessão   → marca tem_sessao → private, no-cache
✔ 304 quebrado no balanceador   → FileETag MTime Size (sem INode)
✔ backend gerando links http:// → X-Forwarded-Proto escrito pela borda (early)
✔ cliente forjando identidade   → sanitização early antes de todo set
✔ log inflado por health checks → contrato nolog + env=!nolog
✔ mudança que derruba o boot    → httpd -t antes de qualquer graceful
```

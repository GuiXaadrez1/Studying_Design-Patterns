# Roteiro de Construção: httpd-vhosts.conf Passo a Passo
## O Guia de Boas Práticas em Ordem de Escrita — do Passo 0 à Validação

*Formato rascunho/roteiro: a sequência exata em que um vhost de produção é construído, passo a passo. Cada passo tem **objetivo**, **o que fazer** e o **bloco comentado**. Siga na ordem — ela não é estética: é a ordem de dependência (parse sequencial) e de lógica (classificar antes de decidir, proteger antes de otimizar).*

---

## PASSO 0 — Pré-requisitos no Global (antes de abrir o vhosts.conf)

**Objetivo:** garantir que tudo que o vhost vai usar já existe quando ele for lido — o parse é sequencial: porta, módulo ou variável usados antes de existir = erro de boot.

**O que fazer:** conferir no `httpd.conf` as portas, os módulos, a política defensiva e o include.

```apache
Listen 80                                # uma linha por porta usada em QUALQUER vhost
Listen 443

# módulos ANTES de qualquer diretiva deles (checar depois com httpd -M):
LoadModule rewrite_module    modules/mod_rewrite.so
LoadModule ssl_module        modules/mod_ssl.so
LoadModule headers_module    modules/mod_headers.so
LoadModule deflate_module    modules/mod_deflate.so
LoadModule expires_module    modules/mod_expires.so
LoadModule setenvif_module   modules/mod_setenvif.so
LoadModule unique_id_module  modules/mod_unique_id.so
LoadModule proxy_module      modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so    # PHP-FPM
LoadModule http2_module      modules/mod_http2.so
# + a família auth/authz (basic, authn_file, authz_core/host/user/groupfile)

ServerTokens Prod                        # sem versão no header Server
ServerSignature Off
TraceEnable Off

<Directory "/">                          # POLÍTICA DEFENSIVA: tudo nasce negado
    AllowOverride None                   # .htaccess NÃO EXISTE em produção —
    Require all denied                   # é ferramenta de quem não tem vhost
</Directory>

IncludeOptional conf/sites/*.conf        # vhosts por último; ordem alfabética
                                         # → prefixos numéricos controlam quem
                                         # é o DEFAULT de cada porta
```

**✔ Checklist do passo:** `httpd -t` limpo · portas no `Listen` · `httpd -M` mostra os módulos · existe `conf/sites/00-default.conf` (o porteiro que responde 404 a IP direto e scanners).

---

## PASSO 1 — Identificação do Servidor (quem sou, o que atendo)

**Objetivo:** dar ao vhost sua identidade — o nome que casa as requisições e a raiz que ele serve. Sem `ServerName` correto, o casamento por nome falha e o tráfego cai no default.

**O que fazer:** abrir o bloco `<VirtualHost>` do par IP:porta e declarar nome, aliases, admin e DocumentRoot apontando para a **public/** (código, vendor e configs ficam FORA da raiz web).

```apache
<VirtualHost *:443>
    ServerName  www.empresa.com.br       # nome CANÔNICO (o casamento por nome)
    ServerAlias empresa.com.br           # também atendo o domínio nu
    ServerAdmin ti@empresa.com.br        # contato exibido nos erros padrão

    DocumentRoot "/var/www/institucional/public"
    #  └ SEMPRE a public/: o que não está sob ela é inalcançável por URL
    #    sem depender de regra nenhuma — segurança por arquitetura

    DirectoryIndex index.php index.html  # o que servir quando a URL é diretório
    AddDefaultCharset UTF-8              # charset padrão p/ text/html
```

**✔ Checklist:** `httpd -S` lista o vhost com arquivo:linha · o DNS (ou hosts, em dev) aponta o nome para o servidor · DocumentRoot é a public/, não a raiz do projeto.

---

## PASSO 2 — Criação de Variáveis (parametrizar antes de repetir)

**Objetivo:** eliminar valores hardcoded repetidos (variáveis de **configuração**, `Define`) e entregar a configuração da aplicação sem `.env` (variáveis de **requisição**, `SetEnv`/`PassEnv`). São dois sistemas sem ponte: `Define` existe só no parse (`${VAR}`); `SetEnv` viaja na requisição até o `$_SERVER`.

**O que fazer:** Defines com prefixo próprio (o `${}` faz fallback pro ambiente do processo — prefixo evita colisão) no topo do arquivo; SetEnv/PassEnv dentro do vhost.

```apache
# ---- topo do arquivo (ANTES do primeiro uso — parse é sequencial): ----
Define CFG_LOG    "/var/log/httpd"
Define CFG_CERTS  "/etc/letsencrypt/live"
Define CFG_FPM    "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"

# ---- dentro do <VirtualHost> — o "arquivo .env" agora é o vhost: ----
    SetEnv APP_ENV     "production"      # config por site: versionável, auditável
    SetEnv APP_URL     "https://www.empresa.com.br"
    SetEnv DB_HOST     "10.0.0.5"
    SetEnv DB_DATABASE "institucional"
    PassEnv DB_PASSWORD APP_KEY
    #  └ SEGREDOS nunca literais aqui: vêm do ambiente do PROCESSO
    #    (systemd EnvironmentFile com permissão 600) via PassEnv.
    #    Com PHP-FPM, a aplicação lê por $_SERVER (getenv() é loteria).
```

**✔ Checklist:** nenhum caminho/host repetido em dois lugares · zero senha literal no arquivo · `print_r($_SERVER)` temporário confirma a chegada das variáveis.

---

## PASSO 3 — Configuração Inicial Básica (TLS + protocolo + handler PHP)

**Objetivo:** a fundação técnica do vhost — criptografia endurecida, HTTP/2 e o PHP como FPM (a boa prática: processo separado → MPM event → HTTP/2 possível; mod_php é legado que trava no prefork).

**O que fazer:**

```apache
    # ---- TLS endurecido ----
    SSLEngine On
    SSLCertificateFile    "${CFG_CERTS}/empresa.com.br/fullchain.pem"
    SSLCertificateKeyFile "${CFG_CERTS}/empresa.com.br/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3   # remove legados inseguros
    SSLHonorCipherOrder Off              # TLS moderno: o cliente escolhe

    # ---- HTTP/2 com fallback ----
    <IfModule http2_module>              # <IfModule>: compila só se existir
        Protocols h2 http/1.1
    </IfModule>

    # ---- PHP via FPM ----
    <FilesMatch "\.php$">
        SetHandler "${CFG_FPM}"          # o .php vai ao socket do FPM
    </FilesMatch>
```

*Não esquecer o par `:80` correspondente: um vhost que **só** redireciona para HTTPS (301) com a única exceção do desafio ACME (`/.well-known/acme-challenge` liberado em HTTP puro para a renovação do certificado).*

**✔ Checklist:** `curl -I https://…` responde · `curl -IL http://…` mostra 301→200 · protocolo `h2` no navegador (aba Network) · um `<?php phpinfo()` temporário mostra FPM/FastCGI.

---

## PASSO 4 — Classificação na Entrada (as marcas que dirigem tudo)

**Objetivo:** o princípio central da série — *classifique na entrada, decida em todo lugar*. As marcas do `SetEnvIf` rodam cedo e viram o vocabulário que o roteador, o cache, a compressão, os cookies e os logs vão consumir nos passos seguintes.

**O que fazer:** definir o vocabulário de marcas do site, uma vez, num bloco só:

```apache
    SetEnvIf Request_URI "\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$" estatico
    #  └ asset estático: sai do PHP, perde cookies, ganha cache
    SetEnvIf Request_URI "\.[0-9a-f]{8,}\.(css|js|woff2)$" imutavel
    #  └ versionado por hash no nome → cache de 1 ano
    SetEnvIf Request_URI "^/(health|ping)$" sonda nolog
    #  └ health checks: resposta na borda + fora dos logs
    SetEnvIf Cookie "app_session=" tem_sessao no-cache
    #  └ logado: cache só privado; no-cache é CONTRATO com mod_cache
    SetEnvIf Remote_Addr "^192\.168\.10\." equipe
    #  └ rede interna: telemetria de debug invisível ao público
    SetEnvIf Request_URI "\.(png|jpe?g|webp|zip|gz|woff2)$" no-gzip
    #  └ CONTRATO com mod_deflate: pré-comprimido não gasta CPU
```

**✔ Checklist:** cada marca tem consumidor nos passos seguintes (marca sem consumidor é ruído) · nomes de contrato (`no-cache`, `no-gzip`, `nolog`) grafados exatos.

---

## PASSO 5 — Liberação de Recursos (abrir por exceção o que o global negou)

**Objetivo:** o Passo 0 negou tudo (`Require all denied` na raiz). Agora, cada recurso servível é liberado **explicitamente**: a public/ do site e, se houver, diretórios de fora do DocumentRoot via `Alias`. Regra dos papéis: **Alias responde ONDE está; `<Directory>` responde QUEM pode; `<Location>` não responde nenhum dos dois** (é só para URLs sem filesystem — proxy, handlers).

**O que fazer:**

```apache
    # ---- a liberação principal: a raiz pública ----
    <Directory "/var/www/institucional/public">
        Options FollowSymLinks           # SEM Indexes: jamais listar pastas
        AllowOverride None               # reforço local: .htaccess não existe
        Require all granted              # a exceção à negação global
    </Directory>

    # ---- recurso FORA do DocumentRoot: Alias (mapeia) + Directory (autoriza) ----
    Alias "/manuais" "/dados/manuais"    # PDFs em outro volume
    <Directory "/dados/manuais">
        Options -Indexes                 # servir arquivos ≠ listar a pasta
        AllowOverride None
        Require all granted              # sem este bloco: 403 (o global nega)
    </Directory>

    # ---- URL sem filesystem (handler): aqui SIM é <Location> ----
    <Location "/status">
        SetHandler server-status         # gerado em memória — não há arquivo
        Require local                    # só a própria máquina
    </Location>
```

**✔ Checklist:** todo `Alias` tem seu `<Directory>` de destino · nenhum `<Location>` "protegendo" arquivo (URL alternativa o contorna) · usuário do Apache tem leitura + travessia (+x) no caminho dos Alias.

---

## PASSO 6 — Front Controller (o roteador, do jeito certo)

**Objetivo:** todo pedido que não é arquivo/pasta real entra pelo ponto único da aplicação — com a exceção de performance: **estático nem entra na cadeia**. Em contexto `<Directory>`, padrão **sem** barra inicial; `RewriteEngine On` em cada contexto (rewrite não herda); `[QSA]` preserva a query; `[L]` encerra a rodada.

**O que fazer:** dentro do `<Directory>` da public/ (as regras específicas — como as do Passo 8 — vêm ANTES; a genérica engole tudo):

```apache
    <Directory "/var/www/institucional/public">
        # …(as diretivas do Passo 5 continuam aqui)…

        RewriteEngine On                 # ligar NESTE contexto (não herda)

        RewriteCond %{ENV:estatico} !=1          # 1) estático: fora do PHP
        RewriteCond %{REQUEST_FILENAME} !-f      # 2) arquivo real? serve direto
        RewriteCond %{REQUEST_FILENAME} !-d      # 3) pasta real? serve direto
        RewriteRule ^(.*)$ index.php [QSA,L]     # 4) o resto → ponto único
        #  └ a condição olha o DISCO; o padrão olha a URL — os dois mundos.
        #    A aplicação lê a rota de $_SERVER['REQUEST_URI'].
    </Directory>

    # bônus da borda: a sonda de health nem acorda o FPM
    RewriteEngine On                     # contexto do vhost: ligar de novo
    RewriteCond %{ENV:sonda} =1
    RewriteRule ^ - [R=204,L]            # 204: vivo, sem corpo
```

**✔ Checklist:** `curl -I /rota-inexistente` → resposta da aplicação (não 404 do Apache) · `curl -I /css/app.css` → servido direto (confira ausência de cookie de sessão) · loop? o antídoto é condição de exclusão ou `[END]`.

---

## PASSO 7 — Proteções (autorização em camadas)

**Objetivo:** aplicar o modelo authn/authz da série: perímetro por origem (barato, silencioso), identidade onde há gente, lógica com os containers `<RequireAll/Any/None>` — e as blindagens estruturais (uploads inertes, métodos restritos).

**O que fazer:**

```apache
    # ---- área admin: origem E identidade (as 5 peças da autenticação) ----
    <Directory "/var/www/institucional/public/admin">
        AuthType Basic                   # 1) esquema
        AuthName "Administração"         # 2) realm (texto do prompt)
        AuthBasicProvider file           # 3) provedor
        AuthUserFile "/etc/httpd/passwd/site.passwd"   # 4) base (htpasswd -B;
                                         #    SEMPRE fora do DocumentRoot)
        <RequireAll>                     # 5) a autorização que consome tudo
            <RequireAny>
                Require ip 192.168.10    # escritório OU
                Require ip 10.8.0        # VPN…
            </RequireAny>
            Require valid-user           # …E credencial (Basic só é ok
        </RequireAll>                    #    porque o vhost é 100% TLS)
    </Directory>
    # ⚠ sub-área refinando a proteção? AuthMerging And — sem ele, o
    #   Require do filho APAGA a herança do pai (merge substitui!)

    # ---- uploads: servidos, jamais executados ----
    <Directory "/var/www/institucional/public/uploads">
        Options -Indexes -ExecCGI
        <FilesMatch "\.ph(ar|p[0-9]?|tml)$">
            SetHandler none              # desarma o handler PHP aqui
            Require all denied           # e nem o fonte é servido
        </FilesMatch>
    </Directory>

    # ---- métodos: só o que o site usa (<If>, não <Limit> — Limit só
    #      cobre os LISTADOS; os exóticos escapariam) ----
    <If "%{REQUEST_METHOD} !in { 'GET', 'HEAD', 'POST' }">
        Require all denied
    </If>

    LimitRequestBody 10485760            # anti-abuso: corpo máx. 10 MB
```

**✔ Checklist:** `curl -I /admin/` de fora → 401 · com `-u user:senha` → 200 · `curl -X DELETE /` → 403 · upload de `.php` em /uploads não executa (nem serve).

---

## PASSO 8 — Otimização (cache, compressão, cookies — dirigidos pelas marcas)

**Objetivo:** implementar a "tabela de ouro" do cache do cliente, ligar a compressão só onde rende, e tirar cookies dos estáticos — tudo consumindo as marcas do Passo 4.

**O que fazer:**

```apache
    # ---- compressão: só texto (o no-gzip do Passo 4 já poupou o resto) ----
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
    </IfModule>

    FileETag MTime Size                  # validador estável (NUNCA INode
                                         # atrás de balanceador — mata o 304)

    <IfModule mod_headers.c>
        # a TABELA DE OURO, por marca:
        Header set Cache-Control "public, max-age=31536000, immutable" env=imutavel
        Header set Cache-Control "public, max-age=86400" \
            "expr=reqenv('estatico') == '1' && reqenv('imutavel') != '1'"
        Header set Cache-Control "private, no-cache" env=tem_sessao
        #  └ no-cache ≠ no-store: no-cache mantém o 304 barato;
        #    no-store re-baixa tudo (só p/ dados sensíveis)

        # estático sem cookies — nas DUAS direções:
        RequestHeader unset Cookie env=estatico
        Header unset Set-Cookie env=estatico
    </IfModule>
```

**✔ Checklist:** `curl -sI /app.<hash>.css | grep -i cache-control` → immutable · `curl -sI -H 'If-None-Match: "<etag>"' /logo.png` → 304 · `Content-Encoding: gzip` no HTML e AUSENTE no .png.

---

## PASSO 9 — Headers de Segurança + Páginas de Erro

**Objetivo:** o dossiê de segurança (**sempre `always`** — a tabela padrão abandona os 4xx/5xx) e as páginas de erro customizadas com o `<Directory>` delas liberado.

**O que fazer:**

```apache
    <IfModule mod_headers.c>
        Header always set Strict-Transport-Security \
            "max-age=31536000; includeSubDomains" "expr=%{HTTPS} == 'on'"
        #  └ HSTS só sobre TLS (emitir em http viola a spec)
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always unset X-Powered-By # das duas tabelas:
        Header unset X-Powered-By
    </IfModule>

    # páginas de erro (401 precisa ser LOCAL — remota vira redirect
    # e quebra o fluxo de autenticação):
    ErrorDocument 401 /erros/401.html
    ErrorDocument 403 /erros/403.html
    ErrorDocument 404 /erros/404.html
    ErrorDocument 500 /erros/500.html
    ErrorDocument 503 /erros/503.html

    <Directory "/var/www/institucional/public/erros">
        Require all granted              # acessíveis a QUALQUER um (senão a
                                         # própria página de erro dá 403)…
        <IfModule mod_headers.c>
            Header set Cache-Control "no-store"   # …e nunca cacheadas
        </IfModule>
    </Directory>
```

**✔ Checklist:** `curl -sI /pagina-inexistente | grep -i strict-transport` → presente NO 404 (prova do `always`) · `curl -I /erros/404.html` → 200.

---

## PASSO 10 — Modo Manutenção + Logs (operação embutida)

**Objetivo:** manutenção liga/desliga por arquivo-flag (sem reload) e logs dedicados, condicionais e rastreáveis com `UNIQUE_ID`.

**O que fazer:**

```apache
    # ---- manutenção por flag: touch MAINT liga / rm MAINT desliga ----
    RewriteEngine On
    RewriteCond /var/www/institucional/MAINT -f
    RewriteCond %{ENV:equipe} !=1                   # equipe segue navegando
    RewriteCond %{REQUEST_URI} !^/manutencao\.html$ # a página passa
    RewriteCond %{REQUEST_URI} !\.(css|png|svg)$    # e os assets (anti-loop)
    RewriteRule ^ /manutencao.html [R=503,L]        # 503 = temporário p/ bots
    <IfModule mod_headers.c>
        Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
    </IfModule>

    # ---- logs: dedicados, com duração e chave de rastreio ----
    ErrorLog  "${CFG_LOG}/institucional-error.log"
    LogLevel  warn                       # p/ depurar rewrite: warn rewrite:trace3
                                         # (ligar, reproduzir, DESLIGAR — pesa)
    CustomLog "${CFG_LOG}/institucional-access.log" rastreavel env=!nolog
    #  └ o formato "rastreavel" (global) tem %D + %{UNIQUE_ID}e — a mesma
    #    chave costura borda → aplicação; sondas de health fora (nolog)
    CustomLog "${CFG_LOG}/institucional-static.log" common env=estatico
</VirtualHost>                           # ← fecha o vhost
```

**✔ Checklist:** `touch MAINT` → 503 de fora, 200 da rede interna → `rm MAINT` volta · `/health` não aparece no access log · `uid=` presente nas linhas do log.

---

## PASSO 11 — Validação e Deploy (o ritual inegociável)

**Objetivo:** nenhuma mudança entra sem passar pela esteira — este é o motivo de tudo viver no vhost: dá para validar ANTES.

```bash
httpd -t                     # 1. type-checker: Syntax OK ou não prossegue
httpd -S                     # 2. mapa: default de cada porta, nomes, arquivo:linha
httpd -M | grep -E 'rewrite|ssl|headers|proxy_fcgi|http2'   # 3. módulos lá?
apachectl graceful           # 4. reload SEM derrubar conexões ativas
# 5. smoke tests (curl = fonte da verdade, imune a cache de navegador):
curl -IL http://empresa.com.br/                 # cadeia 301 → 200
curl -I  https://www.empresa.com.br/            # headers de segurança
curl -I  https://www.empresa.com.br/rota-app    # front controller respondendo
curl -I  https://www.empresa.com.br/erros/404.html   # páginas de erro servem
curl -I -u user:senha https://www.empresa.com.br/admin/   # auth ok
tail -f /var/log/httpd/institucional-error.log  # 6. observar o pós-mudança
```

---

## RESUMO — O Mapa do Roteiro

| # | Passo | Entrega | Ferramenta-chave |
|---|---|---|---|
| 0 | Pré-requisitos globais | portas, módulos, negação padrão | Listen, LoadModule, `Require all denied` |
| 1 | Identificação | quem sou, o que sirvo | ServerName/Alias, DocumentRoot (public/) |
| 2 | Variáveis | zero hardcode, .env substituído | Define `${}` / SetEnv+PassEnv |
| 3 | Config básica | TLS + h2 + PHP-FPM (+ par :80/ACME) | SSLEngine, Protocols, SetHandler fcgi |
| 4 | Classificação | o vocabulário de marcas | SetEnvIf (+ contratos no-gzip/no-cache) |
| 5 | Liberação de recursos | exceções à negação global | `<Directory>` granted, Alias+Directory, Location p/ handlers |
| 6 | Front controller | roteador com exceção de estáticos | RewriteCond/Rule em `<Directory>` |
| 7 | Proteções | authz em camadas + blindagens | RequireAll/Any, AuthType, SetHandler none, `<If>` de métodos |
| 8 | Otimização | tabela de ouro + gzip + sem cookies | Header por marca, FileETag, deflate |
| 9 | Segurança + erros | dossiê `always` + ErrorDocument | Header always, `<Directory>` de /erros |
| 10 | Manutenção + logs | flag MAINT + rastreio | RewriteCond -f, UNIQUE_ID, env=!nolog |
| 11 | Validação | a esteira de deploy | httpd -t/-S/-M, graceful, curl, tail |

*A ordem 4→5→6→7→8 não é negociável na lógica: marcas antes de quem as consome; liberação antes do roteador (que pressupõe o Directory aberto); regras específicas de proteção convivendo com o front controller genérico por último na cadeia de rewrite.*

# Cabeçalhos HTTP e o mod_headers — Edição Expandida
## Header, RequestHeader e a Manipulação de Metadados: o Veículo do Cache, das Variáveis e da Segurança

*Guia prático da série de referência Apache 2.4. Conexão direta com o guia anterior (Cache e Variáveis): os cabeçalhos são o **meio de transporte** de tudo aquilo — o cache viaja em `Cache-Control`/`ETag`, as variáveis são consumidas via `env=` e impressas via `%{VAR}e`, e a postura de segurança do site é inteiramente declarada em headers. Este guia é o manual da ferramenta que manipula esse tráfego: o mod_headers.*

---

## Capítulo 0 — O Modelo: O Que São Headers e Quem Mexe Neles

### 0.1 Os dois sentidos do tráfego

Todo par requisição/resposta HTTP carrega duas cargas de metadados:

```
CLIENTE ──── REQUEST HEADERS ────► APACHE ────► BACKEND
             Host, User-Agent,       │
             Accept-Encoding,        │  manipuláveis com RequestHeader
             Cookie, Authorization   │  (antes de entregar ao backend/handler)
                                     │
CLIENTE ◄─── RESPONSE HEADERS ───── APACHE ◄──── BACKEND
             Content-Type, Cache-    │
             Control, ETag, Set-     │  manipuláveis com Header
             Cookie, Location…       │  (antes de devolver ao cliente)
```

| Diretiva | Atua sobre | Momento | Uso típico |
|---|---|---|---|
| **`Header`** | Cabeçalhos de **RESPOSTA** | Pouco antes de enviar ao cliente | Cache-Control, segurança, CORS, debug |
| **`RequestHeader`** | Cabeçalhos de **REQUISIÇÃO** | Pouco antes de entregar ao handler/backend | Contexto p/ proxy (X-Forwarded-*), sanitização de entrada |

**Contexto e classe:** ambas valem em server config, virtual host, directory e `.htaccess` (classe `FileInfo`) — e participam do merge normal dos containers do guia de Directory/Files/Location.

### 0.2 Quem mais emite headers (e por que isso importa)

O mod_headers não é o único autor — ele é o **editor final**:

| Fonte | Headers que emite |
|---|---|
| **Núcleo/handlers** | `Content-Type`, `Content-Length`, `Last-Modified`, `ETag` (via FileETag), `Date`, `Server` |
| **mod_expires** | `Expires` + `Cache-Control: max-age=` (guia anterior) |
| **mod_deflate** | `Content-Encoding: gzip` + `Vary: Accept-Encoding` |
| **A aplicação (PHP)** | Qualquer um — `header()` no PHP vira response header |
| **mod_headers** | O que VOCÊ mandar — inclusive editar/apagar os acima |

Consequência: **conflitos são resolvidos por ordem de processamento**, e o mod_headers costuma ter a palavra final sobre a resposta — o que o torna a ferramenta certa para *corrigir* o que módulos e aplicação emitiram (ex.: `Header unset X-Powered-By` apaga o vazamento do PHP).

### 0.3 `always` vs `onsuccess` — a condição que ninguém aprende e todos sofrem

O `Header` opera sobre **duas tabelas internas separadas**:

```apache
Header          set X-Debug "a"     # tabela ONSUCCESS: só respostas 2xx (e 3xx locais)
Header always   set X-Debug "b"     # tabela ALWAYS: TODAS, inclusive 4xx/5xx e ErrorDocument
```

- Headers de **segurança** (HSTS, CSP, nosniff…) devem valer até em páginas de erro → **sempre `always`**.
- Um header definido só na tabela padrão **desaparece nos 404/500** — origem do clássico "o scanner de segurança reprova minha página de erro".
- Corolário chato: `Header unset X` remove da tabela onsuccess; se alguém setou com `always`, é preciso `Header always unset X` também. Na dúvida em remoções, faça **as duas**.

---

## NÍVEL BÁSICO

### 1.1 As cinco ações fundamentais

```apache
<IfModule mod_headers.c>
    # SET — define, SOBRESCREVENDO qualquer valor anterior do mesmo nome
    Header set X-Content-Type-Options "nosniff"

    # UNSET — remove todas as ocorrências do header
    Header unset X-Powered-By              # apaga o carimbo do PHP
    Header always unset X-Powered-By       # …das duas tabelas (§0.3)

    # ADD — ADICIONA nova linha, mesmo que o header já exista (linhas duplicadas!)
    # raramente o que você quer; use p/ headers que aceitam múltiplas linhas
    Header add Link "</css/app.css>; rel=preload; as=style"
    Header add Link "</js/app.js>; rel=preload; as=script"

    # APPEND — concatena ao valor EXISTENTE na mesma linha (separado por vírgula)
    Header append Vary "X-Device"          # Vary existente vira "Accept-Encoding, X-Device"

    # MERGE — como append, mas SÓ se o valor ainda não estiver lá (idempotente)
    Header merge Vary "Accept-Encoding"    # não duplica se o mod_deflate já pôs

    # ECHO — copia um header da REQUISIÇÃO para a resposta (debug/rastreio)
    Header echo X-Request-Id
</IfModule>
```

### 1.2 O kit de segurança mínimo — sempre `always`

```apache
<IfModule mod_headers.c>
    # o navegador não "adivinha" tipos (bloqueia sniffing de uploads maliciosos)
    Header always set X-Content-Type-Options "nosniff"

    # a página não pode ser embutida em iframe de terceiros (clickjacking)
    Header always set X-Frame-Options "SAMEORIGIN"

    # quanto do referer vaza ao navegar para fora
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # apagar carimbos de versão (defesa por obscuridade barata, mas válida)
    Header always unset X-Powered-By
    # (o header "Server:" não se remove pelo mod_headers — controla-se com
    #  ServerTokens Prod no global, como no guia do httpd.conf)
</IfModule>
```

### 1.3 A conexão com o cache (Parte A do guia anterior, agora pela ótica do veículo)

```apache
# Toda a "tabela de ouro" do cache é IMPLEMENTADA com Header set:
<IfModule mod_headers.c>
    <FilesMatch "\.[0-9a-f]{8,}\.(css|js|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.html?$">
        Header set Cache-Control "no-cache"
    </FilesMatch>
</IfModule>
# mod_expires e Header podem coexistir: o expires calcula o max-age;
# um "Header set Cache-Control" DEPOIS o sobrescreve por completo.
# Escolha UM dono por classe de conteúdo para não caçar fantasma.
```

---

## NÍVEL INTERMEDIÁRIO

### 2.1 Condições `env=` — headers guiados por variáveis (a ponte com a Parte B)

```apache
# as marcas do mod_setenvif decidem QUEM recebe cada header:
SetEnvIf Remote_Addr "^192\.168\.10\." rede_interna
SetEnvIf Request_URI "^/(css|js|img)/" estatico

<IfModule mod_headers.c>
    # header de debug SÓ para a equipe (invisível ao público):
    Header set X-Vhost-Debug "institucional-tls" env=rede_interna

    # política de cache por marca (o padrão do guia anterior):
    Header set Cache-Control "public, max-age=86400" env=estatico
    Header set Cache-Control "no-cache"              env=!estatico
    #                                                     └ negação: quem NÃO tem a marca
</IfModule>
```

### 2.2 Interpolação de valores — `%{...}e` e a família de especificadores

O VALOR de um header aceita especificadores de formato (os mesmos espíritos do LogFormat):

```apache
<IfModule mod_headers.c>
    # %{VAR}e — variável de AMBIENTE (SetEnv/SetEnvIf/[E=] do rewrite):
    Header set X-Ambiente "%{APP_ENV}e"

    # %{VAR}s — variável SSL (mod_ssl):
    Header set X-TLS-Protocolo "%{SSL_PROTOCOL}s" env=rede_interna

    # %t — timestamp da requisição; %D — duração em microssegundos:
    Header set X-Tempo-Resposta "%D us" env=rede_interna
    # (transformar performance em header legível: profiling sem APM)

    # e a ponte com o REWRITE do guia de reescrita — [E=] alimenta headers:
    RewriteEngine On
    RewriteRule ^promo/([a-z]+)$ /index.php [E=CAMPANHA:$1,L]
    Header set X-Campanha "%{CAMPANHA}e" env=CAMPANHA
    #  └ env=CAMPANHA: emite só quando a variável existe (evita "(null)")
    #  ⚠ lembrete do guia anterior: após internal redirect, vira REDIRECT_CAMPANHA
</IfModule>
```

### 2.3 `RequestHeader` — falando com o backend

```apache
# O caso nobre: reverse proxy (guia de vhosts, Nível 6). O backend (Laravel na
# :8000) não vê o cliente — vê o Apache. RequestHeader repõe o contexto:
<VirtualHost *:443>
    ServerName app.empresa.com.br
    # …TLS…
    ProxyPass        "/" "http://127.0.0.1:8000/"
    ProxyPassReverse "/" "http://127.0.0.1:8000/"
    ProxyPreserveHost On

    <IfModule mod_headers.c>
        # o backend precisa saber que a borda é HTTPS (senão gera URLs http://)
        RequestHeader set X-Forwarded-Proto "https"
        RequestHeader set X-Forwarded-Port  "443"
        # mod_proxy já injeta X-Forwarded-For (IP real do cliente) sozinho

        # SANITIZAÇÃO: nunca confie em headers de contexto vindos DO CLIENTE —
        # um atacante pode enviá-los prontos para se passar por "interno":
        RequestHeader unset X-Forwarded-Proto early
        RequestHeader unset X-Interno        early
        #                                    └ ver §3.3 sobre "early"
        RequestHeader set  X-Forwarded-Proto "https"
        # padrão: apaga o que veio de fora, define o valor confiável da borda
    </IfModule>
</VirtualHost>
```

### 2.4 `Vary` — o header que conversa com TODOS os caches

```apache
# Recapitulando do guia de cache, agora com a mecânica de edição:
# Vary declara quais REQUEST headers mudam a RESPOSTA — e todo cache
# (navegador, CDN, mod_cache) guarda uma variação por combinação.

<IfModule mod_headers.c>
    # merge, nunca set: o mod_deflate já emite "Vary: Accept-Encoding";
    # um "set" apagaria essa informação e corromperia caches intermediários
    Header merge Vary "X-Device"
</IfModule>

# O anti-padrão fatal (repetindo porque custa caro):
#   Header set Vary "User-Agent"   → uma cópia por navegador = hit rate ~zero
```

---

## NÍVEL AVANÇADO

### 3.1 Condições `expr=` — a lógica completa inline (ap_expr)

```apache
<IfModule mod_headers.c>
    # HSTS só quando a conexão JÁ é TLS (emitir em http é violação da spec):
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" \
        "expr=%{HTTPS} == 'on'"

    # Retry-After apenas nos 503 (modo manutenção do guia de vhosts):
    Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"

    # CSP mais rígida só nas rotas do admin:
    Header always set Content-Security-Policy "default-src 'self'" \
        "expr=%{REQUEST_URI} =~ m#^/admin#"

    # emitir header SÓ quando a resposta é HTML (não poluir imagens/JSON):
    Header set X-UA-Compatible "IE=edge" \
        "expr=resp('Content-Type') =~ m#text/html#"
    # funções ap_expr úteis aqui: req('Nome') lê request header,
    # resp('Nome') lê response header, -R 'CIDR' testa origem
</IfModule>
```

### 3.2 `Header edit` — reescrevendo valores com regex

```apache
<IfModule mod_headers.c>
    # o caso clássico: backend na porta interna emite Location/cookies "errados"

    # 1. cookies sem flags de segurança vindos de aplicação legada:
    Header always edit Set-Cookie "^((?!;\s*[Ss]ecure).*)$" "$1; Secure"
    Header always edit Set-Cookie "^((?!;\s*HttpOnly).*)$"  "$1; HttpOnly"
    # (edit* = todas as ocorrências; edit = só a primeira — com vários
    #  Set-Cookie na resposta, use "Header always edit*")

    # 2. reescrever domínio em Location que o ProxyPassReverse não cobriu:
    Header edit Location "^http://interno\.local:8000/" "https://app.empresa.com.br/"

    # 3. redação de dado sensível que a aplicação insiste em ecoar:
    Header edit* X-Debug-Query "senha=[^&]+" "senha=***"
</IfModule>
```

### 3.3 `early` — agindo antes de todo o pipeline

```apache
# O modificador "early" (só em contextos server/vhost/directory — não .htaccess)
# processa o header na PRIMEIRA fase da requisição, ANTES de setenvif tardios,
# rewrite, authz e proxy. É o lugar da SANITIZAÇÃO de entrada:

RequestHeader unset X-Forwarded-For   early    # nunca aceitar do cliente…
RequestHeader unset X-Real-IP         early    # …identidade de rede pronta
RequestHeader unset X-Interno         early

# …porque TUDO que decide com base nesses headers (SetEnvIf, Require expr,
# a aplicação) roda DEPOIS do early — e agora só vê valores que a SUA borda
# escreveu. Sem o early, um cliente malicioso "chega antes" das suas regras.
```

### 3.4 CORS completo — o dossiê de headers de origem cruzada

```apache
# API pública consumida por frontends em OUTROS domínios:
<VirtualHost *:443>
    ServerName api.empresa.com.br
    # …TLS + proxy…

    SetEnvIf Origin "^https://(app|painel)\.empresa\.com\.br$" origem_ok=$0
    #        └ captura a própria origem casada na variável (=$0)

    <IfModule mod_headers.c>
        # ecoa APENAS origens da whitelist (nunca "*" com credenciais!):
        Header always set Access-Control-Allow-Origin "%{origem_ok}e" env=origem_ok
        Header always set Access-Control-Allow-Credentials "true"     env=origem_ok
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" env=origem_ok
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" env=origem_ok
        Header always set Access-Control-Max-Age "600" env=origem_ok
        # variação por origem → caches PRECISAM saber:
        Header merge Vary "Origin"
    </IfModule>

    # o preflight (OPTIONS) respondido pela borda, sem incomodar o backend:
    RewriteEngine On
    RewriteCond %{REQUEST_METHOD} =OPTIONS
    RewriteCond %{ENV:origem_ok} !=""
    RewriteRule ^ - [R=204,L]
    # 204 No Content + os headers acima (tabela always) = preflight completo
</VirtualHost>
```

### 3.5 O dossiê de segurança completo (produção)

```apache
<IfModule mod_headers.c>
    # transporte (só sobre TLS — §3.1):
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" \
        "expr=%{HTTPS} == 'on'"

    # conteúdo:
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # CSP — a mais poderosa e a mais delicada: comece em Report-Only,
    # colha violações, e só então promova:
    Header always set Content-Security-Policy-Report-Only \
        "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; report-uri /csp-report"
    # depois de estável:
    # Header always set Content-Security-Policy "default-src 'self'; …"

    # permissões de APIs do navegador:
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    # isolamento cross-origin (apps sensíveis):
    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set Cross-Origin-Resource-Policy "same-site"

    # limpeza de carimbos:
    Header always unset X-Powered-By
</IfModule>
```

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Kit de teste

```bash
# a resposta completa, sem cache de navegador:
curl -sI https://site.com.br/ 

# um header específico em várias rotas (o header "sumiu" onde?):
for u in / /admin/ /erro-forcado-404; do
  echo "== $u"; curl -sI "https://site.com.br$u" | grep -i strict-transport
done
# se o HSTS some no 404 → faltou "always" (§0.3)

# request headers como o BACKEND os vê (sonda temporária):
#   Header echo X-Forwarded-Proto   → aparece na resposta p/ conferência

# CORS: simular o preflight
curl -sI -X OPTIONS -H "Origin: https://app.empresa.com.br" \
     -H "Access-Control-Request-Method: POST" https://api.empresa.com.br/
```

### 4.2 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| Header some nas páginas de erro | Tabela `onsuccess` (faltou `always`) | §0.3 — segurança sempre `always` |
| `unset` "não remove" | Setado na OUTRA tabela | `Header unset` **e** `Header always unset` |
| Dois valores duplicados no mesmo header | `add` onde era `set`/`merge` | §1.1 — add cria linhas novas sempre |
| `Vary: Accept-Encoding` sumiu (cache corrompido em proxies) | `Header set Vary` apagou o do mod_deflate | `merge`/`append`, nunca `set` em Vary |
| Valor `(null)` no header | `%{VAR}e` sem a variável existir | Condicionar com `env=VAR`; conferir prefixo `REDIRECT_` |
| Cliente forja "X-Interno" e vira VIP | Falta sanitização de entrada | `RequestHeader unset … early` (§3.3) |
| Laravel gera links `http://` atrás do proxy | Backend sem saber que a borda é TLS | `RequestHeader set X-Forwarded-Proto "https"` (+ TrustedProxies na app) |
| CORS falha só com cookies/Authorization | `Allow-Origin: *` com `Allow-Credentials: true` (proibido pela spec) | Ecoar a origem da whitelist (§3.4) |
| Set-Cookie sem Secure/HttpOnly | Aplicação legada | `Header always edit* Set-Cookie …` (§3.2) |
| Header configurado no vhost não aparece | Um contexto mais específico (Directory/Location/If) sobrepôs no merge | Revisar ordem de merge (guia de containers, §3.1) |
| HSTS emitido em HTTP puro | Faltou a condição de TLS | `"expr=%{HTTPS} == 'on'"` (§3.1) |

### 4.3 Cola de bolso — os oito mandamentos

1. **`Header` fala com o cliente; `RequestHeader` fala com o backend.** Dois sentidos, duas diretivas.
2. **Segurança sempre `always`** — a tabela padrão abandona os 4xx/5xx. E remoções nas **duas** tabelas.
3. **`set` sobrescreve, `add` duplica, `append` concatena, `merge` concatena sem repetir.** Em `Vary`: só merge.
4. **Todo header condicional tem dois gatilhos:** `env=VAR` (marcas do setenvif/rewrite — a ponte com o guia de variáveis) e `"expr=…"` (lógica ap_expr inline).
5. **`early` é o segurança da porta:** sanitize `X-Forwarded-*`/headers de confiança ANTES de qualquer decisão — nunca aceite identidade de rede vinda do cliente.
6. **`Header edit`** conserta o que backend/aplicação emitem errado (cookies sem flags, Location interno) — `edit*` para múltiplas ocorrências.
7. **CORS com credenciais = whitelist ecoada + `Vary: Origin`.** Nunca `*`.
8. **CSP nasce Report-Only** e só se promove com o relatório limpo.

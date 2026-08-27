# Cache e Variáveis no Apache 2.4 — Edição Expandida
## Os Dois Mundos do Cache (Navegador e Servidor) e os Dois Sistemas de Variáveis (Configuração e Requisição)

*Guia prático da série de referência Apache 2.4. Dois temas entrelaçados: **cache** — como fazer navegadores, proxies e o próprio servidor pouparem trabalho com máxima eficiência — e **variáveis** — como o Apache define, propaga e consome valores, o mecanismo que amarra cache, log, compressão, autorização e aplicação.*

---

# PARTE A — CACHE

## Capítulo 0 — O Mapa: Três Caches, Duas Estratégias

### 0.1 Onde o cache acontece

```
NAVEGADOR ──────── PROXIES/CDN ──────── APACHE ──────── APLICAÇÃO/DISCO
   │                    │                  │
   ▼                    ▼                  ▼
CACHE PRIVADO      CACHE COMPARTILHADO   CACHE DE SERVIDOR
(por usuário)      (entre usuários)      (mod_cache: guarda a
                                          resposta pronta e poupa
Controlados pelos MESMOS cabeçalhos       o backend/filesystem)
HTTP: Cache-Control, ETag, Expires…
```

| Camada | Quem controla | Ferramentas Apache | Ganho |
|---|---|---|---|
| **Cache do cliente** (navegador/proxy/CDN) | Cabeçalhos HTTP que VOCÊ emite | `mod_expires`, `mod_headers`, `FileETag` | A requisição **nem chega** ao servidor (ou vira um 304 baratíssimo) |
| **Cache do servidor** | O próprio Apache | `mod_cache` + `mod_cache_disk`/`mod_cache_socache` | A requisição chega, mas é respondida **sem tocar** o backend (PHP/proxy) |

### 0.2 As duas estratégias de cabeçalho — frescor vs validação

Todo recurso cacheável responde a duas perguntas, e a eficiência máxima vem de escolher a resposta certa para cada tipo de conteúdo:

**1. FRESCOR (freshness):** *"por quanto tempo o cliente pode usar a cópia SEM me perguntar nada?"*
→ `Cache-Control: max-age=N` (e o legado `Expires`). Durante N segundos, **zero requisições** chegam — o ganho máximo possível.

**2. VALIDAÇÃO (validation):** *"quando o prazo vencer (ou for zero), como o cliente confere se a cópia ainda vale?"*
→ Validadores `ETag` (impressão digital) e `Last-Modified` (data). O cliente manda `If-None-Match`/`If-Modified-Since`; se nada mudou, o servidor responde **`304 Not Modified` sem corpo** — custa uma ida, mas não a transferência.

```
Requisição de algo já visto:
├─ dentro do max-age?  → NEM SAI do navegador (custo: zero)
├─ vencido, com validador? → GET condicional → 304 (custo: 1 RTT, sem corpo)
└─ vencido, sem validador? → GET completo → 200 (custo: tudo de novo)
```

**A tabela de ouro da eficiência** — cada classe de conteúdo com sua política:

| Conteúdo | Política | Cabeçalho resultante |
|---|---|---|
| Estáticos **versionados** no nome (`app.3f9a1c.css`) | Frescor máximo: mudou? o NOME muda | `Cache-Control: public, max-age=31536000, immutable` |
| Estáticos **sem versão** (logo.png, foto.jpg) | Frescor moderado + validação | `Cache-Control: public, max-age=86400` (+ ETag) |
| **HTML** de páginas dinâmicas | Só validação — nunca frescor | `Cache-Control: no-cache` (≠ no-store! valida sempre, mas cacheia) |
| Respostas **personalizadas** (painel logado) | Cache só privado | `Cache-Control: private, no-cache` |
| **Sensível** (dados bancários, tokens) | Nada em lugar nenhum | `Cache-Control: no-store` |
| **API** consumida por sistemas | Curto + compartilhado | `Cache-Control: public, max-age=60, s-maxage=300` |

*Vocabulário: `public` = proxies podem guardar; `private` = só o navegador; `no-cache` = pode guardar mas DEVE revalidar; `no-store` = não guardar; `immutable` = nem revalide ao recarregar; `s-maxage` = max-age específico p/ caches compartilhados.*

---

## NÍVEL BÁSICO — Cache do Cliente

### A1.1 mod_expires — o frescor por tipo

```apache
# módulos no global: LoadModule expires_module / headers_module
<IfModule mod_expires.c>
    ExpiresActive On
    # sintaxe legível: "access plus N períodos" (base "access" = do acesso;
    # "modification" = da data do arquivo — útil p/ conteúdo publicado em lote)
    ExpiresByType image/webp              "access plus 30 days"
    ExpiresByType image/png               "access plus 30 days"
    ExpiresByType image/jpeg              "access plus 30 days"
    ExpiresByType image/svg+xml           "access plus 30 days"
    ExpiresByType font/woff2              "access plus 365 days"
    ExpiresByType text/css                "access plus 7 days"
    ExpiresByType application/javascript  "access plus 7 days"
    ExpiresByType text/html               "access plus 0 seconds"
    ExpiresDefault                        "access plus 1 hour"   # o resto
</IfModule>
# mod_expires emite AMBOS: "Expires:" (legado) e "Cache-Control: max-age=" —
# clientes modernos ignoram o Expires quando o max-age existe.
```

### A1.2 FileETag e Last-Modified — os validadores

```apache
# Para arquivos estáticos, o Apache gera os validadores SOZINHO:
# Last-Modified vem do mtime; ETag vem do FileETag:
FileETag MTime Size          # padrão do 2.4 (sem INode — e mantenha assim:
                             # inode difere entre servidores → atrás de um
                             # balanceador, ETags divergem e o 304 nunca acontece)

# Fluxo automático que você ganha de graça (nada a configurar além do acima):
#   1ª visita: 200 + ETag: "abc-123" + Last-Modified: …
#   revisita:  GET + If-None-Match: "abc-123" → 304 (sem corpo)
```

### A1.3 mod_headers — o ajuste fino cirúrgico

```apache
<IfModule mod_headers.c>
    # estáticos versionados: o pacote completo de imutabilidade
    <FilesMatch "\.[0-9a-f]{8,}\.(css|js|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
        # immutable: nem o F5 dispara revalidação — economia real em recargas
    </FilesMatch>

    # HTML dinâmico: sempre revalidar (no-cache), nunca proibir o cache (no-store)
    <FilesMatch "\.html?$">
        Header set Cache-Control "no-cache"
    </FilesMatch>

    # área logada: só cache privado
    # (melhor ainda: a APLICAÇÃO emitir — ela sabe quem está logado)
</IfModule>
```

> **Erro clássico de eficiência:** usar `no-store` onde bastava `no-cache`. O `no-store` joga fora até o 304 — todo acesso volta a transferir o corpo inteiro. Reserve-o para dados realmente sensíveis.

---

## NÍVEL INTERMEDIÁRIO — Cache do Servidor (mod_cache)

### A2.1 Quando ele vale a pena — e a arquitetura

O cache do cliente poupa a **rede**; o do servidor poupa o **backend**. Ele brilha quando gerar a resposta é caro: páginas de proxy reverso, respostas de aplicação lentas, conteúdo semi-estático de alto tráfego.

```apache
# ---------- global (httpd.conf) ----------
LoadModule cache_module       modules/mod_cache.so        # a lógica
LoadModule cache_disk_module  modules/mod_cache_disk.so   # o armazém em disco
# alternativa: mod_cache_socache (memória compartilhada — menor e mais rápido,
# ideal p/ muitos objetos pequenos; disk p/ objetos grandes e persistência)

<IfModule mod_cache.c>
    # liga o atalho: o cache responde ANTES das fases pesadas da requisição
    CacheQuickHandler on
    # ⚠ trade-off: "on" = máxima velocidade, MAS pula autorização/rewrite p/
    # os HITs. Conteúdo protegido por Require NUNCA deve ser cacheável com
    # QuickHandler on — ou use "off" (cache roda como handler normal, mais
    # lento, porém respeitando as fases).

    <IfModule mod_cache_disk.c>
        CacheRoot   "/var/cache/httpd/mod_cache"
        CacheEnable disk "/"              # o quê cachear é refinado nos vhosts
        CacheDirLevels 2
        CacheDirLength 1
    </IfModule>

    # sane defaults
    CacheDefaultExpire 3600               # sem cabeçalhos de frescor: 1h
    CacheMaxExpire     86400              # teto: nada vive mais de 24h
    CacheIgnoreNoLastMod On               # cacheia mesmo sem Last-Modified
    CacheLock On                          # anti-estouro: 1 requisição revalida,
    CacheLockMaxAge 5                     # as demais usam o stale (thundering herd)
</IfModule>
```

### A2.2 Aplicando por vhost — o proxy cacheado

```apache
<VirtualHost *:443>
    ServerName api.empresa.com.br
    # …TLS…

    ProxyPass        "/" "http://127.0.0.1:8000/"
    ProxyPassReverse "/" "http://127.0.0.1:8000/"

    <IfModule mod_cache.c>
        CacheEnable disk "/catalogo"      # SÓ o que é seguro cachear
        CacheDisable "/carrinho"          # jamais o que é por usuário
        CacheDisable "/auth"

        # o backend manda o cache via cabeçalhos — o mod_cache OBEDECE
        # Cache-Control da resposta (max-age, s-maxage, no-store, private).
        # Logo: a aplicação Laravel controla o cache do Apache com:
        #   ->header('Cache-Control', 'public, s-maxage=300, max-age=60')
        #   (s-maxage: 5 min no Apache/CDN; max-age: 1 min no navegador)

        # diagnóstico: de onde veio a resposta?
        CacheHeader On                    # adiciona "X-Cache: HIT/MISS/REVALIDATE"
        CacheDetailHeader On              # + "X-Cache-Detail: …motivo…"
    </IfModule>
</VirtualHost>
```

```bash
# manutenção do armazém em disco (daemon ou cron):
htcacheclean -d 30 -p /var/cache/httpd/mod_cache -l 512M
#             └ a cada 30min, limita o cache a 512 MB (LRU)
```

### A2.3 Vary — a dimensão esquecida

```apache
# O cabeçalho Vary declara QUAIS cabeçalhos da requisição mudam a resposta —
# e o cache guarda UMA CÓPIA POR VARIAÇÃO:
#   Vary: Accept-Encoding   → uma cópia gzip, uma sem (mod_deflate já emite!)
#   Vary: User-Agent        → ⚠ praticamente UMA CÓPIA POR NAVEGADOR = cache morto
# Regra: Vary mínimo. Se precisar variar por algo, varie por um cabeçalho
# de baixa cardinalidade (ex.: um X-Device: mobile/desktop normalizado
# na borda), nunca pelo User-Agent bruto.
```

---

# PARTE B — VARIÁVEIS

## Capítulo 0 — Os Dois Sistemas (a distinção que evita 90% da confusão)

| Sistema | Diretiva | Quando existe | Para quê | Exemplo de consumo |
|---|---|---|---|---|
| **Variável de CONFIGURAÇÃO** | `Define` | Na **leitura do config** (inicialização) | Parametrizar a própria configuração — caminhos, nomes, chaves de ambiente | `${VAR}` interpolada em QUALQUER diretiva |
| **Variável de AMBIENTE de requisição** | `SetEnv`, `SetEnvIf`, `[E=]` do rewrite | Durante **uma requisição** | Marcar/decidir por requisição: log, headers, authz, PHP | `%{ENV:VAR}`, `env=VAR`, `Require env`, `$_SERVER` |

São mundos separados: `Define` não aparece no PHP; `SetEnv` não parametriza um `DocumentRoot`. Confundi-los é a fonte clássica de "a variável não funciona".

## NÍVEL BÁSICO

### B1.1 `Define` — parametrizando a configuração

```apache
# ---------- httpd.conf ou topo do arquivo de vhosts ----------
Define APP_ROOT  "/var/www/institucional"
Define LOG_DIR   "/var/log/httpd"
Define DOMINIO   "empresa.com.br"

<VirtualHost *:443>
    ServerName   www.${DOMINIO}
    ServerAlias  ${DOMINIO}
    DocumentRoot "${APP_ROOT}/public"

    <Directory "${APP_ROOT}/public">
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog  "${LOG_DIR}/institucional-error.log"
    CustomLog "${LOG_DIR}/institucional-access.log" combined
</VirtualHost>
# mude o caminho do app em UMA linha; dez vhosts padronizados viram um template.

# Define também vem da LINHA DE COMANDO (-D) e casa com <IfDefine>:
#   httpd -D MANUTENCAO  →
<IfDefine MANUTENCAO>
    RedirectMatch 503 "^(?!/manutencao\.html)"
    ErrorDocument 503 /manutencao.html
</IfDefine>
# (no Linux, ajuste OPTIONS/APACHE_ARGUMENTS do serviço; XAMPP: httpd.exe -D X)
```

### B1.2 `SetEnv` e `PassEnv` — o ambiente fixo da requisição (mod_env)

```apache
# valor fixo entregue a TODA requisição do escopo:
SetEnv APP_ENV "production"
SetEnv API_INTERNA "https://api.interna:8443"

# repassa uma variável do AMBIENTE DO PROCESSO Apache (shell/systemd) p/ dentro:
PassEnv TZ

# remove:
UnsetEnv API_INTERNA
```

```php
// consumo na aplicação (mod_php/CGI/FCGI — chegam como variáveis de servidor):
$env = $_SERVER['APP_ENV'] ?? getenv('APP_ENV');   // "production"
```

### B1.3 `SetEnvIf` — a variável condicional (mod_setenvif)

```apache
# sintaxe: SetEnvIf ATRIBUTO regex VAR[=valor] [!VAR] …
# atributos: Remote_Addr, Request_URI, Request_Method, Request_Protocol,
#            Server_Addr, qualquer CABEÇALHO da requisição (User-Agent, Referer…)

SetEnvIf Request_URI "^/health$"                 nolog
SetEnvIfNoCase User-Agent "(UptimeRobot|Pingdom)" nolog monitor=1
SetEnvIf Remote_Addr "^192\.168\.10\."            rede_interna
SetEnvIf Referer "^https://www\.empresa\.com\.br" ref_interno
# BrowserMatch = atalho p/ SetEnvIfNoCase User-Agent:
BrowserMatch "MSIE [6-9]" navegador_legado

# SetEnvIf roda CEDO (antes do rewrite e da authz) — por isso suas marcas
# estão disponíveis para praticamente tudo que vem depois.
```

---

## NÍVEL INTERMEDIÁRIO — Consumindo Variáveis (onde a mágica acontece)

### B2.1 Os cinco consumidores

```apache
# ---------- 1. LOG condicional (CustomLog env=) ----------
CustomLog "${LOG_DIR}/site-access.log" combined  env=!nolog
CustomLog "${LOG_DIR}/site-monitor.log" common   env=monitor
# e DENTRO do formato: %{VAR}e imprime o valor da variável
LogFormat "%h %t \"%r\" %>s %b amb=%{APP_ENV}e" com_ambiente

# ---------- 2. AUTORIZAÇÃO (Require env / RequireNone) ----------
SetEnvIfNoCase User-Agent "(AhrefsBot|MJ12bot)" bot_ruim
<Directory "${APP_ROOT}/public">
    <RequireAll>
        Require all granted
        <RequireNone>
            Require env bot_ruim
        </RequireNone>
    </RequireAll>
</Directory>

# ---------- 3. CABEÇALHOS (Header env= e %{VAR}e) ----------
<IfModule mod_headers.c>
    Header set X-Ambiente "%{APP_ENV}e"  env=rede_interna
    # o header SÓ é emitido p/ quem tem a marca — debug invisível ao público
</IfModule>

# ---------- 4. REWRITE (RewriteCond %{ENV:…} e [E=…]) ----------
RewriteEngine On
RewriteCond %{ENV:navegador_legado} =1
RewriteRule ^app/(.*)$ /app-legado/$1 [L]
# e o rewrite também ESCREVE variáveis:
RewriteRule ^promo/([a-z]+)$ /index.php [E=CAMPANHA:$1,L]

# ---------- 5. APLICAÇÃO ($_SERVER no PHP) ----------
#   $_SERVER['CAMPANHA'], $_SERVER['rede_interna'] ?? null, etc.
```

### B2.2 As variáveis ESPECIAIS — nomes que o próprio Apache consome

Algumas variáveis não são suas: **o Apache muda de comportamento quando as vê**:

```apache
# no-gzip       → mod_deflate NÃO comprime esta resposta
BrowserMatch ^Mozilla/4\.0[678]  no-gzip
SetEnvIf Request_URI "\.(png|jpe?g|zip|woff2)$" no-gzip   # já comprimidos

# no-cache      → mod_cache NÃO guarda esta resposta (cache de SERVIDOR)
SetEnvIf Request_URI "^/relatorios/tempo-real" no-cache

# force-no-vary / downgrade-1.0 / force-response-1.0 → curativos p/ clientes
# quebrados (proxies antigos) — raros hoje, mas aparecem em configs herdadas

# prefixo REDIRECT_ : após um internal redirect (ErrorDocument, rewrite em
# per-directory), as variáveis da requisição ORIGINAL são renomeadas:
#   CAMPANHA → REDIRECT_CAMPANHA
# consumo pós-redirect: %{ENV:REDIRECT_CAMPANHA} / $_SERVER['REDIRECT_CAMPANHA']
# — o detalhe que explica "minha variável sumiu depois do ErrorDocument".
```

### B2.3 Cache + variáveis — o ajuste condicional (juntando as partes A e B)

```apache
# cache diferenciado por marca — o padrão de máxima eficiência:
<IfModule mod_headers.c>
    # parceiros de integração (IP marcado) recebem TTL maior na API:
    SetEnvIf Remote_Addr "^200\.100\.50\." parceiro
    Header set Cache-Control "public, max-age=300" env=parceiro
    Header set Cache-Control "public, max-age=60"  env=!parceiro
</IfModule>

# e o inverso: variável decidindo o que o mod_cache NÃO guarda:
SetEnvIf Cookie "sessao_admin=" no-cache
# usuários logados no admin nunca recebem (nem alimentam) o cache de servidor
```

---

## NÍVEL AVANÇADO

### B3.1 `<If>` + expressões — variáveis e lógica de runtime unificadas

```apache
# ap_expr lê TUDO: variáveis de requisição, ENV, cabeçalhos, funções
<If "%{ENV:rede_interna} == '1' && %{TIME_HOUR} -ge 18">
    Header always set X-Aviso "Fora do horário comercial"
</If>

# Header/RequestHeader aceitam expr= inline (sem <If> em volta):
Header always set Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == 'on'"
Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"

# setando variável VIA expressão (sem SetEnvIf) — o "SetEnvIfExpr":
SetEnvIfExpr "req('X-Parceiro-Token') =~ /^tok-[0-9a-f]{32}$/" parceiro_ok
```

### B3.2 Interpolação avançada do `Define` — templates de vhost

```apache
# padrão "macro manual": um arquivo-template parametrizado por Defines
# conf/sites/_template-site.conf.inc:
#     <VirtualHost *:443>
#         ServerName   ${SITE_HOST}
#         DocumentRoot "${SITE_ROOT}/public"
#         ErrorLog     "${LOG_DIR}/${SITE_NOME}-error.log"
#         CustomLog    "${LOG_DIR}/${SITE_NOME}-access.log" combined
#         …bloco padrão TLS/Directory/headers…
#     </VirtualHost>

# conf/sites/10-blog.conf:
Define SITE_NOME "blog"
Define SITE_HOST "blog.empresa.com.br"
Define SITE_ROOT "/var/www/blog"
Include conf/sites/_template-site.conf.inc
UnDefine SITE_NOME
UnDefine SITE_HOST
UnDefine SITE_ROOT
# (UnDefine evita vazamento p/ o próximo arquivo incluído — Defines são globais!)
# alternativa nativa mais elegante: mod_macro (<Macro>/Use), quando disponível.
```

### B3.3 Cache de servidor sob variáveis — o quadro completo

```apache
# política em três camadas p/ um proxy de aplicação:
<VirtualHost *:443>
    ServerName app.empresa.com.br
    # …TLS + ProxyPass p/ 127.0.0.1:8000…

    # camada 1: marcas na entrada
    SetEnvIf Cookie  "laravel_session="        tem_sessao
    SetEnvIf Request_URI "^/(css|js|img)/"     estatico
    SetEnvIf Request_URI "^/api/publica/"      api_publica

    # camada 2: o que o mod_cache pode guardar
    <IfModule mod_cache.c>
        CacheEnable disk "/api/publica"
        CacheEnable disk "/css"
        CacheEnable disk "/js"
        CacheEnable disk "/img"
        # com sessão: nunca (variável especial no-cache)
        SetEnvIf Cookie "laravel_session=" no-cache
        CacheHeader On
    </IfModule>

    # camada 3: o que o CLIENTE pode guardar
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=86400, immutable" env=estatico
        Header set Cache-Control "public, max-age=60, s-maxage=300" env=api_publica
        Header set Cache-Control "private, no-cache"                env=tem_sessao
    </IfModule>
</VirtualHost>
```

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Kit de teste de cache

```bash
# o que o servidor está prometendo?
curl -sI https://site.com.br/app.3f9a1c.css | grep -iE 'cache-control|expires|etag|last-modified|vary'

# o 304 funciona? (pegue o ETag da chamada acima)
curl -sI -H 'If-None-Match: "abc-123"' https://site.com.br/logo.png
#   → HTTP/1.1 304 Not Modified  = validação OK

# o mod_cache está acertando?
curl -sI https://api.empresa.com.br/api/publica/tabela | grep -i x-cache
#   1ª: X-Cache: MISS   2ª: X-Cache: HIT   (CacheHeader On)

# compressão + Vary coerentes?
curl -sI -H 'Accept-Encoding: gzip' https://site.com.br/ | grep -iE 'content-encoding|vary'
```

### 4.2 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| Navegador re-baixa tudo sempre | `no-store` onde era `no-cache`; ou sem max-age E sem validadores | Tabela de ouro (§A0.2); conferir com `curl -sI` |
| 304 nunca acontece atrás do balanceador | `FileETag` com INode (divergente entre nós) | `FileETag MTime Size` em todos os nós |
| Usuário vê página de OUTRO usuário | Conteúdo com sessão entrou no cache compartilhado | `Cache-Control: private`/`no-cache` na resposta; `SetEnvIf Cookie … no-cache` p/ o mod_cache |
| mod_cache sempre MISS | Resposta com `no-store/private`, sem frescor e `CacheIgnoreNoLastMod Off`, ou URL fora do `CacheEnable` | `CacheDetailHeader On` diz o MOTIVO no X-Cache-Detail |
| HIT servindo conteúdo protegido | `CacheQuickHandler on` pulando a authz | `off` no escopo protegido, ou nunca cachear área com `Require` |
| Cache de disco crescendo sem limite | `htcacheclean` não roda | Daemon/cron (§A2.2) |
| Hit rate péssimo com Vary | `Vary: User-Agent` (ou Cookie) explodindo as variações | Vary mínimo; normalizar na borda |
| `${VAR}` aparece LITERAL no log/erro | `Define` ausente (ou definido DEPOIS do uso) | Define antes do uso; `httpd -t` acusa "undefined variable" |
| Variável não chega ao PHP | Confusão Define×SetEnv, ou sumiu no internal redirect | §B0 (dois sistemas); prefixo `REDIRECT_` (§B2.2) |
| `env=VAR` no CustomLog nunca casa | SetEnvIf depois do consumo, ou regex errada | SetEnvIf roda cedo, mas a ORDEM no arquivo importa p/ legibilidade; testar imprimindo `%{VAR}e` no formato |

### 4.3 Cola de bolso — os oito mandamentos

1. **Frescor poupa a rede inteira; validação poupa só o corpo.** Maximize frescor onde o nome versiona (`immutable`); use validação (`no-cache` + ETag) onde a URL é estável e o conteúdo muda.
2. **`no-cache` ≠ `no-store`.** O primeiro é eficiência (304); o segundo é sigilo (tudo de novo, sempre).
3. **`FileETag MTime Size`** — nunca INode em múltiplos servidores.
4. **mod_cache obedece aos cabeçalhos do backend** — a aplicação governa o cache do servidor via `Cache-Control` (e `s-maxage` separa o TTL do servidor do TTL do navegador).
5. **`CacheQuickHandler on` = velocidade sem authz nos HITs.** Nunca sobre conteúdo protegido.
6. **`Define` parametriza a configuração (${VAR}); `SetEnv*` marca a requisição (%{ENV:VAR}).** Dois sistemas, sem ponte automática.
7. **Variáveis especiais são contratos:** `no-gzip`, `no-cache`, `nolog` (sua convenção) — e o prefixo `REDIRECT_` após internal redirects.
8. **`CacheHeader`/`CacheDetailHeader` + `curl -sI`** são a verdade sobre o cache — nunca o F5 do navegador.

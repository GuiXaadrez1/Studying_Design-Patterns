# Variáveis no Apache 2.4, a Fundo — Edição Expandida
## Taxonomia Completa, Substituindo o Arquivo .env e Otimizando Backend e Frontend com Variáveis

*Guia prático da série de referência Apache 2.4. Aprofundamento do capítulo de variáveis do guia de Cache: agora com a **taxonomia completa das cinco origens**, o padrão realista para **substituir arquivos `.env`** (com as diferenças cruciais entre mod_php e PHP-FPM e os limites de segurança), e um receituário de **otimização via variáveis** nas duas direções — poupando o backend e acelerando o frontend.*

---

## Capítulo 0 — A Taxonomia Completa: As Cinco Origens de uma Variável

Toda variável que "existe" numa requisição nasceu em um destes cinco lugares — e saber a origem determina onde ela pode ser lida:

```
┌─ 1. AMBIENTE DO PROCESSO ──────────────────────────────────────────┐
│  Definida FORA do Apache: shell, systemd (Environment=), Docker    │
│  (ENV), painel do provedor. O httpd HERDA ao subir.                │
│  Entra no Apache via: PassEnv | osenv() | ${VAR} em alguns casos*  │
└────────────────────────────────────────────────────────────────────┘
┌─ 2. VARIÁVEL DE CONFIGURAÇÃO ──────────────────────────────────────┐
│  Define NOME "valor"  — existe SÓ durante o parse do config.       │
│  Consumo: ${NOME} interpolado em qualquer diretiva. NÃO chega       │
│  à requisição nem ao PHP. (*${VAR} também lê o ambiente do          │
│  processo se não houver Define — atenção à sobreposição!)           │
└────────────────────────────────────────────────────────────────────┘
┌─ 3. AMBIENTE DA REQUISIÇÃO (o "env" propriamente) ─────────────────┐
│  SetEnv (fixa) | SetEnvIf/BrowserMatch (condicional por atributo)  │
│  SetEnvIfExpr (condicional por expressão) | RewriteRule [E=…]      │
│  Vive UMA requisição. Consumo: %{ENV:X}, env=X, reqenv('X'),        │
│  %{X}e (headers/logs), Require env X, $_SERVER no PHP.              │
└────────────────────────────────────────────────────────────────────┘
┌─ 4. VARIÁVEIS NATIVAS DA REQUISIÇÃO ───────────────────────────────┐
│  O Apache preenche sozinho: REQUEST_URI, HTTP_HOST, REMOTE_ADDR,    │
│  HTTPS, TIME_*, REQUEST_METHOD… e as SSL_* (mod_ssl com             │
│  StdEnvVars) e UNIQUE_ID (mod_unique_id). Só leitura.               │
└────────────────────────────────────────────────────────────────────┘
┌─ 5. VARIÁVEIS DE CONTRATO (especiais) ─────────────────────────────┐
│  Nomes que MUDAM o comportamento de módulos ao existirem:           │
│  no-gzip, no-cache, nolog*, dont-vary, downgrade-1.0…               │
│  (*nolog é contrato SEU com o CustomLog env=; as demais são          │
│  contratos com mod_deflate/mod_cache/núcleo.)                        │
│  + o prefixo REDIRECT_: após internal redirect, as vars da           │
│  requisição original são renomeadas (X → REDIRECT_X).                │
└────────────────────────────────────────────────────────────────────┘
```

**A tabela de alcance** (a pergunta "onde consigo ler?"):

| Origem | Config (`${}`) | Rewrite/If (`%{ENV:}`) | Header/Log (`%{}e`) | PHP (`$_SERVER`) |
|---|---|---|---|---|
| 1. Processo | ✔ (fallback do `${}`) | via PassEnv → ✔ | via PassEnv → ✔ | mod_php: `getenv()` ✔ / FPM: depende do pool |
| 2. Define | ✔ | ✘ | ✘ | ✘ |
| 3. SetEnv* / [E=] | ✘ | ✔ | ✔ | ✔ |
| 4. Nativas | ✘ | ✔ (`%{VAR}`) | especificadores próprios | ✔ (CGI vars) |
| 5. Contrato | ✘ | ✔ | ✔ | ✔ (as suas) |

---

## NÍVEL BÁSICO — Definindo Cada Tipo (recap dirigido + o que faltava)

### 1.1 O ambiente do processo — a origem que o guia anterior não cobriu

```bash
# ---------- Linux com systemd (produção): ----------
# /etc/systemd/system/httpd.service.d/env.conf
[Service]
Environment="APP_ENV=production"
Environment="DB_HOST=10.0.0.5"
# ou, melhor para segredos (arquivo com permissão 600, fora do repo):
EnvironmentFile=/etc/httpd/secrets.env
#   → systemctl daemon-reload && systemctl restart httpd

# ---------- Windows/XAMPP: ----------
#   variáveis de sistema (Painel > Sistema > Variáveis de Ambiente)
#   ou setar antes de subir: set APP_ENV=dev && httpd.exe
```

```apache
# Dentro do Apache, o ambiente do processo NÃO entra sozinho na requisição:
PassEnv APP_ENV DB_HOST          # copia processo → ambiente da requisição
# a partir daqui: %{ENV:APP_ENV}, %{APP_ENV}e, $_SERVER['APP_ENV']

# leitura pontual SEM copiar (só em expressões):
<If "osenv('APP_ENV') == 'production'"> … </If>
```

### 1.2 Os quatro definidores de requisição, lado a lado

```apache
SetEnv     CANAL "web"                                   # fixa, incondicional
SetEnvIf   Request_URI "^/api/" api=1                    # condicional por ATRIBUTO
SetEnvIfExpr "req('X-Device') == 'mobile'" mobile=1      # condicional por EXPRESSÃO
RewriteRule ^campanha/([a-z]+)$ /index.php [E=UTM:$1,L]  # setada PELA reescrita

# remoções e valores capturados:
UnsetEnv CANAL
SetEnvIf Origin "^https://(app|painel)\.empresa\.com\.br$" origem_ok=$0
#                                                          └ =$0/$1… capturas do regex
```

### 1.3 As nativas que valem ouro (e como ativar as que não vêm de graça)

```apache
# UNIQUE_ID — identificador único POR REQUISIÇÃO (mod_unique_id):
LoadModule unique_id_module modules/mod_unique_id.so
# nada a configurar: %{UNIQUE_ID}e em headers/logs, $_SERVER['UNIQUE_ID'] no PHP.
# É a espinha dorsal do rastreamento distribuído do §3.1.

# SSL_* — desligadas por padrão (custam CPU); ligue SÓ onde consome:
<Directory "/var/www/app/public/area-cert">
    SSLOptions +StdEnvVars           # popula SSL_PROTOCOL, SSL_CLIENT_S_DN, etc.
</Directory>
```

---

## NÍVEL INTERMEDIÁRIO — Substituindo o Arquivo .env

### 2.1 A proposta e o mapa de decisão

Um `.env` faz três coisas: (a) configura a aplicação por ambiente, (b) guarda segredos, (c) vive fora do código. O Apache cobre (a) e (c) com elegância — e (b) **com ressalvas sérias** (§2.4). O desenho:

```
                    ┌──────────────────────────────────────────┐
  systemd/secrets → │ PROCESSO (EnvironmentFile 600)           │  segredos reais
                    └───────────────┬──────────────────────────┘
                                    │ PassEnv (só o necessário)
                    ┌───────────────▼──────────────────────────┐
  vhost por site  → │ SetEnv APP_ENV/URLS/FLAGS por VirtualHost│  config por ambiente
                    └───────────────┬──────────────────────────┘
                                    │ $_SERVER / getenv()
                    ┌───────────────▼──────────────────────────┐
                    │ APLICAÇÃO (sem .env no disco)            │
                    └──────────────────────────────────────────┘
```

### 2.2 Caminho A — mod_php (o caso XAMPP/dev)

```apache
# Com mod_php, SetEnv do vhost chega DIRETO ao PHP — substituição 1:1 do .env:
<VirtualHost *:80>
    ServerName app.local
    DocumentRoot "C:/xampp/htdocs/app/public"

    # o "arquivo .env" agora É o vhost — versionável como infraestrutura:
    SetEnv APP_ENV       "local"
    SetEnv APP_DEBUG     "true"
    SetEnv APP_URL       "http://app.local"
    SetEnv DB_CONNECTION "mysql"
    SetEnv DB_HOST       "127.0.0.1"
    SetEnv DB_DATABASE   "app_dev"
    SetEnv DB_USERNAME   "app"
    PassEnv DB_PASSWORD              # o SEGREDO vem do processo, não do arquivo!

    <Directory "C:/xampp/htdocs/app/public">
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

```php
// Laravel: env() já lê getenv()/$_SERVER — funciona SEM o arquivo .env,
// desde que config:cache não tenha sido gerado com valores antigos.
// PHP puro:
$dbHost = $_SERVER['DB_HOST'] ?? getenv('DB_HOST');
```

*Ganhos imediatos: dev/staging/prod = três vhosts, zero `.env` esquecido no deploy, config auditável junto da infra. E o mesmo código roda em todos.*

### 2.3 Caminho B — PHP-FPM (o caso produção moderna) — ATENÇÃO às diferenças

Com FPM, o PHP roda **noutro processo** — e o transporte muda:

```apache
# O Apache fala com o FPM via FastCGI (proxy_fcgi):
<FilesMatch "\.php$">
    SetHandler "proxy:unix:/run/php-fpm/app.sock|fcgi://localhost"
</FilesMatch>

# BOA NOTÍCIA: SetEnv/PassEnv do vhost SÃO transmitidos como parâmetros
# FastCGI → aparecem em $_SERVER normalmente. MAS:
#  • getenv() pode NÃO vê-los (dependendo de variables_order/clear_env) —
#    padronize a leitura por $_SERVER na aplicação;
#  • o pool do FPM tem clear_env = yes por padrão: o ambiente do PROCESSO
#    FPM é limpo — PassEnv no Apache resolve o transporte, mas segredos
#    também podem (e muitas vezes DEVEM) ser definidos direto no pool:
```

```ini
; /etc/php-fpm.d/app.conf — a alternativa/complemento do lado do PHP:
[app]
clear_env = yes                          ; mantenha: higiene de ambiente
env[APP_ENV]     = production
env[DB_PASSWORD] = $DB_PASSWORD          ; puxa do ambiente do serviço FPM
; (o systemd unit do PHP-FPM tem seu próprio EnvironmentFile)
```

**Regra de arquitetura resultante:** *config não-sensível por site → `SetEnv` no vhost (perto do site, versionável); segredos → ambiente do processo (systemd `EnvironmentFile` 600) do serviço que os consome (httpd p/ mod_php, php-fpm p/ FPM), nunca literais no vhost.*

### 2.4 Os limites de segurança — leia antes de migrar segredos

1. **Config do Apache costuma ser legível por mais gente que um `.env` 600.** Segredo literal em `SetEnv DB_PASSWORD "..."` no vhost pode ser um RETROCESSO. Daí o padrão `PassEnv` + `EnvironmentFile` com permissão restrita.
2. **`phpinfo()` e páginas de debug vazam `$_SERVER` inteiro** — com suas variáveis. Bloqueie `phpinfo` em produção (já era regra; agora é crítica).
3. **Variáveis de requisição aparecem em ferramentas de diagnóstico** (dump de request, APM). Nomeie sem revelar (`DB_DSN_REF` apontando p/ um alias, se o time for grande).
4. **Nunca transporte segredo em RequestHeader** para o backend — headers atravessam logs de proxies. Ambiente FastCGI/processo é o canal certo.
5. **`.env` continua legítimo** quando o deploy é gerenciado pela aplicação (CI escreve o arquivo). O ganho do padrão Apache é quando a INFRA é a dona da configuração — como no seu servidor dedicado.

---

## NÍVEL AVANÇADO — Otimização com Variáveis

### 3.1 Otimizando o BACKEND (poupar quem custa caro)

```apache
<VirtualHost *:443>
    ServerName app.empresa.com.br
    DocumentRoot "/var/www/app/public"
    # …TLS…

    # ============================================================
    # 1. CLASSIFICAR NA ENTRADA — as marcas que dirigem tudo
    # ============================================================
    SetEnvIf Request_URI "\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$" estatico no-gzip-check
    SetEnvIf Request_URI "^/(health|ping)$"                          sonda nolog
    SetEnvIf Cookie      "laravel_session="                          tem_sessao
    SetEnvIfExpr "req('X-Requested-With') == 'XMLHttpRequest'"       ajax

    # ============================================================
    # 2. ESTÁTICO NUNCA TOCA O PHP (a maior economia isolada)
    # ============================================================
    <Directory "/var/www/app/public">
        AllowOverride None
        Require all granted
        RewriteEngine On
        RewriteCond %{ENV:estatico} !=1          # a marca decide a rota
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # ============================================================
    # 3. ESTÁTICO SEM COOKIES — requisições menores, cache limpo
    # ============================================================
    <IfModule mod_headers.c>
        # cliente→servidor: não arrastar quilos de cookie p/ buscar um .png
        RequestHeader unset Cookie env=estatico
        # servidor→cliente: estático jamais seta cookie (e caches agradecem)
        Header unset Set-Cookie env=estatico
    </IfModule>

    # ============================================================
    # 4. COMPRESSÃO SÓ ONDE RENDE (contrato no-gzip)
    # ============================================================
    SetEnvIf Request_URI "\.(png|jpe?g|webp|zip|gz|woff2)$" no-gzip
    # já-comprimidos: gzip em cima = CPU gasta p/ crescer o arquivo

    # ============================================================
    # 5. CACHE DE SERVIDOR GUIADO POR MARCA (contrato no-cache)
    # ============================================================
    <IfModule mod_cache.c>
        CacheEnable disk "/api/publica"
        SetEnvIf Cookie "laravel_session=" no-cache    # logado nunca alimenta/come cache
        CacheHeader On
    </IfModule>

    # ============================================================
    # 6. CONTEXTO PRONTO PARA O BACKEND (poupar recomputação)
    # ============================================================
    <IfModule mod_headers.c>
        # sanitiza a fronteira (early — ver guia de Headers §3.3)…
        RequestHeader unset X-Request-Id  early
        RequestHeader unset X-Device      early
        # …e entrega contexto computado UMA vez na borda:
        RequestHeader set X-Request-Id "%{UNIQUE_ID}e"
        RequestHeader set X-Device "mobile" "expr=req('User-Agent') =~ /Mobile|Android/"
        RequestHeader set X-Device "desktop" "expr=!(req('User-Agent') =~ /Mobile|Android/)"
        # o Laravel lê X-Request-Id p/ correlacionar logs e X-Device p/ variar
        # layout — sem re-parsear User-Agent em cada request PHP.
    </IfModule>

    # ============================================================
    # 7. LOG SEM DESPERDÍCIO — e com as variáveis certas DENTRO
    # ============================================================
    LogFormat "%h %t \"%r\" %>s %b %D uid=%{UNIQUE_ID}e dev=%{X-Device}i" rastreavel
    CustomLog "/var/log/httpd/app-access.log"  rastreavel env=!nolog
    CustomLog "/var/log/httpd/app-static.log"  common     env=estatico
    # sondas de health-check: fora de tudo (nolog) — menos I/O, logs limpos
</VirtualHost>
```

*O item 6 é o padrão de ouro do rastreamento: `UNIQUE_ID` gerado pela borda viaja como `X-Request-Id` ao backend, aparece no access log (`%{UNIQUE_ID}e`), no log da aplicação e pode voltar na resposta — UMA chave costura o caminho inteiro de cada requisição, do curl ao stack trace.*

### 3.2 Otimizando o FRONTEND (respostas que aceleram o navegador)

```apache
    # ============================================================
    # 1. POLÍTICA DE CACHE POR CLASSE (a tabela de ouro, dirigida por marcas)
    # ============================================================
    SetEnvIf Request_URI "\.[0-9a-f]{8,}\.(css|js|woff2)$" imutavel
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=31536000, immutable" env=imutavel
        Header set Cache-Control "public, max-age=86400" "expr=reqenv('estatico') == '1' && reqenv('imutavel') != '1'"
        Header set Cache-Control "private, no-cache" env=tem_sessao
    </IfModule>

    # ============================================================
    # 2. PRELOAD CONDICIONAL — só onde o HTML principal é servido
    # ============================================================
    <IfModule mod_headers.c>
        Header add Link "</css/app.3f9a1c.css>; rel=preload; as=style" \
            "expr=resp('Content-Type') =~ m#text/html#"
        Header add Link "</js/app.8b2d4e.js>; rel=preload; as=script" \
            "expr=resp('Content-Type') =~ m#text/html#"
        # o navegador começa a baixar os assets ANTES de parsear o HTML
    </IfModule>

    # ============================================================
    # 3. VARY NORMALIZADO — variação barata em vez de User-Agent
    # ============================================================
    <IfModule mod_headers.c>
        Header merge Vary "X-Device" env=ajax_ou_html
        # a borda normalizou UA → X-Device (mobile|desktop): 2 variações
        # de cache em vez de milhares (guia de Headers, §2.4)
    </IfModule>

    # ============================================================
    # 4. RESPOSTA DIFERENCIADA SEM CUSTO DE BACKEND
    # ============================================================
    # AJAX de sonda/polling respondido pela borda (nem chega ao PHP):
    RewriteEngine On
    RewriteCond %{ENV:sonda} =1
    RewriteRule ^ - [R=204,L]

    # ============================================================
    # 5. DEBUG SÓ PARA QUEM DEVE VER (variável como chave do olho mágico)
    # ============================================================
    SetEnvIf Remote_Addr "^192\.168\.10\." equipe
    <IfModule mod_headers.c>
        Header set X-Request-Id "%{UNIQUE_ID}e"          env=equipe
        Header set X-Tempo-Borda "%D us"                 env=equipe
        Header set X-Cache-Estado "%{cache-status}e"     env=equipe
        # a equipe vê a telemetria em qualquer aba; o público, nada.
    </IfModule>
```

### 3.3 O quadro-síntese — cada variável no seu papel

| Objetivo | Variável/mecanismo | Efeito |
|---|---|---|
| Backend não vê estáticos | `SetEnvIf … estatico` + RewriteCond `%{ENV:}` | PHP só p/ conteúdo dinâmico |
| Requisições menores | `RequestHeader unset Cookie env=estatico` | KBs de cookie fora de cada asset |
| CPU de compressão só onde rende | contrato `no-gzip` | deflate pula pré-comprimidos |
| Cache de servidor seguro | contrato `no-cache` p/ sessão | logado nunca envenena o cache |
| Backend sem recomputar contexto | `RequestHeader set X-Device/X-Request-Id` | borda computa uma vez |
| Rastreabilidade ponta-a-ponta | `UNIQUE_ID` → header + log + app | uma chave, todo o caminho |
| Logs enxutos | contrato próprio `nolog` + `env=!nolog` | I/O e ruído reduzidos |
| Frontend com cache máximo | marcas → Cache-Control por classe | tabela de ouro automatizada |
| Menos variações de cache | UA normalizado → `Vary: X-Device` | hit rate alto |
| Config sem .env | processo (segredos) + SetEnv vhost (config) | §2.1–2.4 |

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Kit de inspeção de variáveis

```bash
# 1. o que chega ao PHP? (página temporária, REMOVER depois)
#    <?php header('Content-Type: text/plain'); print_r($_SERVER);

# 2. imprimir uma variável no header p/ conferir da própria máquina:
#    Header set X-Probe "%{MINHA_VAR}e" env=equipe
curl -sI https://app.empresa.com.br/ | grep -i x-probe

# 3. imprimir no log (a prova definitiva de que a marca disparou):
#    LogFormat "… flag=%{MINHA_VAR}e" teste

# 4. Define funcionou? o parse acusa na hora:
httpd -t          # "undefined variable" aponta arquivo e linha

# 5. ambiente do processo chegou? 
systemctl show httpd -p Environment      # (ou o unit do php-fpm)
```

### 4.2 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| `$_SERVER['X']` vazio com FPM, ok no XAMPP | Transporte FastCGI/`clear_env`/`variables_order` | §2.3 — padronizar leitura por `$_SERVER`; `env[]` no pool p/ o que faltar |
| `getenv()` ok, `$_SERVER` vazio (ou o inverso) | `variables_order` do php.ini sem "E"/"S" | Padronizar UMA forma de leitura na aplicação |
| Variável some após ErrorDocument/rewrite | Prefixo `REDIRECT_` no internal redirect | Ler `REDIRECT_X` (ou re-setar após o redirect) |
| `${VAR}` pegou valor "fantasma" | `${}` faz fallback p/ o ambiente do processo | Nomear Defines com prefixo próprio (ex.: `CFG_`) |
| Segredo apareceu no phpinfo/APM | Variável de requisição é visível ao diagnóstico | §2.4 — segredos no processo, phpinfo banido |
| `PassEnv` "não passa" | Variável não existe no ambiente DO SERVIÇO (systemd ≠ seu shell) | `systemctl show -p Environment`; EnvironmentFile |
| Marca não dispara p/ Header/Log | Nome errado, regex, ou consumo antes da definição em contexto exótico | Sonda `%{X}e` no log; SetEnvIf roda cedo — confie, mas verifique |
| `no-gzip`/`no-cache` "ignorados" | Grafia (são nomes EXATOS de contrato) ou módulo ausente | Conferir nome literal; `httpd -M` |
| X-Request-Id duplicado/forjado | Falta sanitização `early` na borda | `RequestHeader unset … early` antes do set |

### 4.3 Cola de bolso — os oito mandamentos

1. **Cinco origens, cinco alcances:** processo (PassEnv/osenv), Define (${} — parse only), requisição (SetEnv*/[E=] — %{ENV:}/$_SERVER), nativas (só leitura), contratos (nomes mágicos). Confusão de origem = variável "que não funciona".
2. **`${}` lê Define E o ambiente do processo** — prefixe seus Defines (`CFG_`) para nunca colidir.
3. **Substituindo o .env:** config por site no `SetEnv` do vhost; **segredos no ambiente do processo** (systemd `EnvironmentFile` 600 do serviço certo — httpd p/ mod_php, php-fpm p/ FPM) via `PassEnv`/`env[]`. Nunca segredo literal no vhost, nunca segredo em RequestHeader.
4. **Com FPM, leia por `$_SERVER`** — `getenv()` é loteria de `clear_env`/`variables_order`.
5. **Classifique na entrada, decida em todo lugar:** marcas do SetEnvIf dirigem rewrite, compressão, cache, cookies, headers e logs — um vocabulário de marcas por vhost.
6. **As três economias de backend:** estático fora do PHP, estático sem Cookie (nas duas direções), contexto computado uma vez na borda (X-Device, X-Request-Id).
7. **`UNIQUE_ID` é a chave-mestra da observabilidade** — header + access log + log da aplicação, a mesma string do início ao fim.
8. **Toda variável de confiança nasce com sanitização `early`** — a borda apaga o que veio de fora antes de escrever o valor em que ela mesma vai acreditar.

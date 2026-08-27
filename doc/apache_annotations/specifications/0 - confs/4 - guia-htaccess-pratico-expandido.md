# O Arquivo .htaccess na Prática — Edição Expandida
## Do Básico ao Avançado: Exemplos Completos, Módulos e Containers em Contexto de Diretório

*Guia prático da série de referência Apache 2.4 (complementa o Guia de Referência do .htaccess e a Edição Expandida de VirtualHosts). Todos os exemplos são comentados e prontos para o cenário legítimo do `.htaccess`: hospedagem compartilhada/cPanel, ou delegação controlada via `AllowOverride`. Em servidores sob seu controle, as mesmas regras pertencem ao vhost — ver guia correspondente.*

---

## Capítulo 0 — Fundações do Contexto .htaccess

### 0.1 As quatro leis do .htaccess

1. **Ele só existe se o `AllowOverride` permitir.** Com `AllowOverride None` (padrão do 2.4), o Apache **nem lê** o arquivo. Cada diretiva pertence a uma *classe de override* (`FileInfo`, `AuthConfig`, `Limit`, `Indexes`, `Options`); usar uma diretiva de classe bloqueada = **500** com `not allowed here` no ErrorLog.
2. **É runtime e hierárquico.** Lido a cada requisição, em cada diretório do caminho, do mais raso ao mais profundo — o mais profundo sobrepõe. Mudanças valem na hora (sem reload), e erros de sintaxe derrubam na hora (sem `httpd -t` prévio possível).
3. **Padrões de URL são relativos ao diretório.** O prefixo do caminho até o `.htaccess` é removido antes do casamento: `RewriteRule ^produtos/(.*)$ …` — **sem barra inicial**, sempre.
4. **Containers permitidos: só três famílias.** `<Files>`/`<FilesMatch>`, `<IfModule>`/`<IfDefine>`, `<Limit>`/`<LimitExcept>` — e os combinadores `<RequireAll/Any/None>`. **Nunca** `<Directory>`, `<Location>` ou `<VirtualHost>` (erro imediato).

### 0.2 Kit de sobrevivência em hospedagem compartilhada

Você não controla módulos, versões nem `AllowOverride` — então:

```apache
# Convenção defensiva nº 1: módulo opcional SEMPRE em <IfModule>.
# Sem o envelope, módulo ausente no provedor = 500 no site inteiro.
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>

# Convenção nº 2: teste INCREMENTAL. Adicione um bloco, teste com curl, prossiga.
# Não existe validador prévio de .htaccess.

# Convenção nº 3: mantenha backup da última versão funcional
# (ex.: .htaccess.ok no mesmo diretório — inacessível via web pelo bloqueio do §1.2).
```

---

## NÍVEL BÁSICO

### 1.1 O esqueleto essencial — todo site começa aqui

```apache
# ==================================================================
# .htaccess — raiz pública (public_html/)
# ==================================================================

# ---------- Índice de diretório ----------
# arquivo servido quando a URL aponta um diretório (classe: Indexes)
DirectoryIndex index.php index.html

# ---------- Higiene mínima ----------
# nunca listar conteúdo de diretórios sem índice (classe: Options)
Options -Indexes

# charset padrão para HTML/texto sem declaração (classe: FileInfo)
AddDefaultCharset UTF-8

# ---------- Páginas de erro do site (classe: FileInfo) ----------
ErrorDocument 404 /erros/404.html
ErrorDocument 403 /erros/403.html
ErrorDocument 500 "Erro interno. Tente novamente em instantes."
```

### 1.2 Bloqueio de arquivos sensíveis — obrigatório em qualquer site

```apache
# ---------- Dotfiles e segredos: jamais servidos ----------
# <FilesMatch> casa NOMES de arquivo por regex (classe do Require: Limit)
<FilesMatch "^\.(env|git.*|htaccess|htpasswd|user\.ini)">
    Require all denied
</FilesMatch>

# artefatos de edição/backup/dump que vazam código e dados
<FilesMatch "\.(bak|old|orig|save|swp|sql|log|ini)$">
    Require all denied
</FilesMatch>

# um arquivo específico: container literal <Files>
<Files "composer.json">
    Require all denied
</Files>
```

> **2.2 → 2.4:** tutoriais antigos usam `Order deny,allow` + `Deny from all`. Converta para `Require all denied` — misturar as gerações no mesmo escopo produz comportamento contraintuitivo (`AH01797`).

### 1.3 Redirecionamentos simples — mod_alias antes de mod_rewrite

```apache
# Sem condição a testar? Redirect basta — mais simples e mais barato.
Redirect permanent "/catalogo-2024" "/catalogo"
Redirect 302       "/promo"         "/ofertas/black-friday"

# por regex: RedirectMatch
RedirectMatch 410 "\.(bak|old)$"        # 410 Gone: recurso removido de vez
```

*Regra de convivência: não misture `Redirect` e `RewriteRule` sobre as mesmas URLs — módulos diferentes, ordem de execução não intuitiva.*

### 1.4 O primeiro rewrite: front controller

```apache
# ---------- Front controller (classe: FileInfo) ----------
RewriteEngine On
RewriteBase /
# ↑ RewriteBase: prefixo de URL para alvos relativos. Na raiz do domínio, "/".
#   Em subpasta (ex.: site em /loja), seria "RewriteBase /loja/".

# arquivos e diretórios reais são servidos diretamente
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
# todo o resto entra pelo ponto único (padrão SEM barra inicial — lei nº 3)
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
# [QSA] preserva a query string original; [L] encerra esta rodada de reescrita
```

---

## NÍVEL INTERMEDIÁRIO

Aqui entram os módulos de conteúdo (compressão, cache, cabeçalhos), autenticação por diretório, herança entre `.htaccess` e as primeiras condições compostas.

### 2.1 O .htaccess completo de um site em produção compartilhada

```apache
# ==================================================================
# public_html/.htaccess — site completo em cPanel
# ==================================================================

# ---------- Base (nível 1) ----------
DirectoryIndex index.php index.html
Options -Indexes
AddDefaultCharset UTF-8
ErrorDocument 404 /erros/404.html

<FilesMatch "^\.(env|git.*|htaccess|htpasswd|user\.ini)|\.(bak|old|sql|log)$">
    Require all denied
</FilesMatch>

# ==================================================================
# CANONICALIZAÇÃO: HTTPS + www — UMA cadeia, UM redirect
# ==================================================================
RewriteEngine On
RewriteBase /

# Duas condições em OR ([OR] explícito!) → um único 301 combinado.
# (Encadear "http→https" e depois "nu→www" separados gera DOIS redirects
#  por acesso — desperdício e penalidade de SEO.)
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteCond %{HTTP_HOST} ^(?:www\.)?(.+)$ [NC]
RewriteRule ^(.*)$ https://www.%1/$1 [R=301,L]
# %1 = captura da ÚLTIMA RewriteCond casada; $1 = captura do RewriteRule

# ==================================================================
# COMPRESSÃO — mod_deflate (envelope defensivo: lei do <IfModule>)
# ==================================================================
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE image/svg+xml
    # imagens raster e fontes woff2 JÁ são comprimidas — não gaste CPU nelas
</IfModule>

# ==================================================================
# CACHE HTTP — mod_expires (classe: Indexes — peculiaridade documentada)
# ==================================================================
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp             "access plus 30 days"
    ExpiresByType image/png              "access plus 30 days"
    ExpiresByType image/jpeg             "access plus 30 days"
    ExpiresByType image/svg+xml          "access plus 30 days"
    ExpiresByType font/woff2             "access plus 365 days"
    ExpiresByType text/css               "access plus 7 days"
    ExpiresByType application/javascript "access plus 7 days"
    ExpiresByType text/html              "access plus 0 seconds"  # HTML: nunca
</IfModule>

# ==================================================================
# CABEÇALHOS — mod_headers: segurança + cache fino
# ==================================================================
<IfModule mod_headers.c>
    # segurança de conteúdo do site inteiro
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always unset X-Powered-By

    # estáticos versionados por hash no nome (app.3f9a1c2b.css): imutáveis
    <FilesMatch "\.[0-9a-f]{8,}\.(css|js)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # fontes: CORS liberado (falham cross-origin sem isso)
    <FilesMatch "\.(woff2?|ttf)$">
        Header set Access-Control-Allow-Origin "*"
    </FilesMatch>
</IfModule>

# ==================================================================
# FRONT CONTROLLER — por último: regras específicas acima, genérica abaixo
# ==================================================================
# performance: estáticos nem entram na cadeia PHP
RewriteCond %{REQUEST_URI} !\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$ [NC]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

*A ordem interna importa: canonicalização primeiro (para que tudo já chegue em https+www), depois módulos de conteúdo (não dependem de ordem entre si), front controller por último.*

### 2.2 Diretório protegido por senha — herança em ação

```apache
# ==================================================================
# public_html/admin/.htaccess — SÓ este arquivo; o pai continua valendo
# ==================================================================

# Autenticação HTTP Basic (classe: AuthConfig)
AuthType Basic
AuthName "Administração"                    # texto do prompt do navegador
# arquivo de senhas SEMPRE fora da raiz pública:
AuthUserFile "/home/conta/.htpasswds/admin/passwd"
Require valid-user

# As regras do public_html/.htaccess (compressão, cache, bloqueios)
# CONTINUAM valendo aqui — herança é cumulativa; este arquivo ADICIONA
# a autenticação. Só o que for redefinido aqui sobrepõe o pai.
```

```bash
# criação do arquivo de senhas (shell/terminal do cPanel):
htpasswd -c /home/conta/.htpasswds/admin/passwd  maria    # -c SÓ na 1ª vez
htpasswd    /home/conta/.htpasswds/admin/passwd  joao
```

> **Basic transmite credenciais em Base64 (reversível).** Só é aceitável porque o §2.1 já força HTTPS em tudo — sem TLS, é senha em texto claro.

### 2.3 Autorização composta — rede OU senha, e exclusões

```apache
# admin/.htaccess — versão avançada da proteção:
AuthType Basic
AuthName "Administração"
AuthUserFile "/home/conta/.htpasswds/admin/passwd"

# do escritório (IP fixo) entra direto; de fora, exige credencial
<RequireAny>
    Require ip 200.100.50.25
    Require valid-user
</RequireAny>

# variante E-lógico (VPN E senha, simultaneamente):
# <RequireAll>
#     Require ip 10.8.0
#     Require valid-user
# </RequireAll>
```

### 2.4 Subdiretório de uploads — servir sem nunca executar

```apache
# ==================================================================
# public_html/uploads/.htaccess
# ==================================================================
Options -Indexes -ExecCGI

# desarma o PHP para qualquer coisa gravada aqui (classe: FileInfo)
<FilesMatch "\.ph(ar|p[0-9]?|tml)$">
    SetHandler none
    Require all denied          # cinto E suspensório: nem o fonte é servido
</FilesMatch>

# nunca deixar o navegador "adivinhar" tipo executável
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>
```

> **Ressalva de hospedagem:** muitos provedores bloqueiam `SetHandler`/`AddHandler` no `.htaccess` (vetor histórico de execução de uploads). Se der 500 `not allowed here`, mantenha apenas o `Require all denied` do FilesMatch — que sozinho já impede a execução via web.

---

## NÍVEL AVANÇADO

Condições encadeadas, variáveis de ambiente, anti-hotlink, multi-domínio numa conta, modo manutenção, controle por método e depuração sem acesso ao servidor.

### 3.1 Anti-hotlinking — suas imagens só no seu site

```apache
RewriteEngine On

# Referer vazio é permitido (acessos diretos, apps, alguns proxies legítimos)
RewriteCond %{HTTP_REFERER} !^$
# permitido: o próprio site (http/https, com/sem www)
RewriteCond %{HTTP_REFERER} !^https?://(www\.)?empresa\.com\.br/ [NC]
# só intercepta os tipos que interessam
RewriteRule \.(png|jpe?g|webp|gif)$ - [F,NC,L]
# [F] = 403 Forbidden; alvo "-" = não reescreve, só aplica a flag
# alternativa simpática: servir uma imagem-aviso em vez de 403:
# RewriteRule \.(png|jpe?g|webp)$ /assets/hotlink.png [R=302,NC,L]
```

### 3.2 Multi-domínio na mesma conta — roteando por host

```apache
# Cenário cPanel clássico: domínio adicional aponta para a MESMA public_html;
# o conteúdo dele vive numa subpasta. Rewrite interno (URL não muda p/ visitante):
RewriteEngine On

# ⚠ %{HTTP_HOST} contém APENAS o hostname — nunca "http://" nem o path.
RewriteCond %{HTTP_HOST} ^(www\.)?segundodominio\.com\.br$ [NC]
# anti-loop: obrigatório — sem isto, a reescrita re-casa consigo mesma
RewriteCond %{REQUEST_URI} !^/segundodominio/
RewriteRule ^(.*)$ /segundodominio/$1 [L]

# a subpasta pode ter o PRÓPRIO .htaccess com o front controller dela:
# public_html/segundodominio/.htaccess →
#     RewriteEngine On            (herança de rewrite NÃO é automática!)
#     RewriteBase /
#     RewriteCond %{REQUEST_FILENAME} !-f
#     RewriteCond %{REQUEST_FILENAME} !-d
#     RewriteRule ^(.*)$ index.php [QSA,L]
```

### 3.3 Variáveis de ambiente — marcar na entrada, decidir depois

```apache
# mod_setenvif marca requisições; outras diretivas consomem a marca (classe: FileInfo)
<IfModule mod_setenvif.c>
    # bots de scraping conhecidos
    SetEnvIfNoCase User-Agent "(AhrefsBot|SemrushBot|MJ12bot)" bot_indesejado
    # requisições internas de monitoramento
    SetEnvIfNoCase User-Agent "(UptimeRobot|Pingdom)" monitoramento
</IfModule>

# consumo nº 1: bloquear os marcados (RequireNone = NOT lógico)
<RequireAll>
    Require all granted
    <RequireNone>
        Require env bot_indesejado
    </RequireNone>
</RequireAll>

# consumo nº 2 (quando o provedor permite CustomLog em conf própria):
# excluir monitoramento do log — em .htaccess puro, CustomLog NÃO é permitido;
# a marca ainda serve para o bloqueio acima e para leitura via PHP ($_SERVER).
```

### 3.4 Modo manutenção com exceções — sem tocar no servidor

```apache
RewriteEngine On

# liga-se criando o arquivo MAINT na pasta (touch MAINT / rm MAINT via FTP ou cPanel)
RewriteCond %{DOCUMENT_ROOT}/MAINT -f
# a equipe (IP fixo do escritório) continua navegando normalmente
RewriteCond %{REMOTE_ADDR} !^200\.100\.50\.25$
# a própria página de manutenção e seus assets não podem ser bloqueados (loop!)
RewriteCond %{REQUEST_URI} !^/manutencao\.html$
RewriteCond %{REQUEST_URI} !\.(css|png|svg)$
# R=503: código correto de manutenção — buscadores entendem "temporário"
RewriteRule ^ /manutencao.html [R=503,L]

ErrorDocument 503 /manutencao.html
<IfModule mod_headers.c>
    # instrui clientes a voltar em 1h (expressão condicionada ao status)
    Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
</IfModule>
```

### 3.5 Controle por método HTTP — do jeito certo

```apache
# ✔ RECOMENDADO: negar o que NÃO é usado, via rewrite (lista explícita de permitidos)
RewriteEngine On
RewriteCond %{REQUEST_METHOD} !^(GET|HEAD|POST)$
RewriteRule ^ - [F,L]

# ✘ CUIDADO com <Limit>: só se aplica aos métodos LISTADOS — os não listados
#   ficam SEM a restrição (alçapão clássico). Se insistir na família, use
#   <LimitExcept>, que protege "tudo exceto os listados":
# <LimitExcept GET HEAD POST>
#     Require all denied
# </LimitExcept>
```

### 3.6 Reescritas encadeadas — flags que controlam o fluxo

```apache
RewriteEngine On
RewriteBase /

# [S=n] (skip): pula as próximas n regras se esta casar — um "if/else" de rewrite
# exemplo: arquivos reais pulam o bloco de pretty-urls inteiro (2 regras)
RewriteRule ^(.+)\.(css|js|png|jpe?g|webp|svg|woff2?)$ - [S=2,NC]

# pretty URLs de duas formas distintas:
RewriteRule ^produto/([0-9]+)/?$        index.php?rota=produto&id=$1   [QSA,L]
RewriteRule ^categoria/([a-z0-9-]+)/?$  index.php?rota=categoria&slug=$1 [QSA,L]

# [END] vs [L]: em .htaccess, [L] encerra a RODADA — o Apache reprocessa a
# URL reescrita do zero (novo internal redirect), e o arquivo é lido de novo.
# [END] encerra DEFINITIVAMENTE (2.4): use-o quando o [L] estiver causando
# loop porque a URL reescrita volta a casar as próprias regras:
# RewriteRule ^loop-sensivel$ index.php?x=1 [QSA,END]
```

### 3.7 Depuração sem acesso ao servidor

`LogLevel rewrite:traceN` **não é permitido em `.htaccess`** — em hospedagem compartilhada, o arsenal é outro:

```bash
# 1. curl é a fonte da verdade (sem cache de navegador):
curl -I  https://site.com.br/rota            # status + headers de UMA resposta
curl -IL https://site.com.br/rota            # segue e exibe a CADEIA de redirects

# 2. Cabeçalho-sonda: marque a regra suspeita e veja se ela disparou
```
```apache
<IfModule mod_headers.c>
    # some com isto depois do diagnóstico:
    Header always set X-Debug-Regra "canonicalizacao-v3"
</IfModule>
```
```bash
# 3. Variável-sonda via rewrite: prove qual regra casou
#    RewriteRule ^teste$ - [E=REGRA_X:1,L]
#    → PHP: var_dump($_SERVER['REGRA_X'] ?? null);

# 4. Bisseção: comente metade do arquivo, teste, repita — O(log n) até o culpado.

# 5. ErrorLog do cPanel (Metrics → Errors): "not allowed here" = classe bloqueada;
#    "Invalid command" = módulo ausente/typo → envelope <IfModule> ou remoção.
```

---

## Capítulo 4 — Referência Rápida

### 4.1 Tabela de sintomas

| Sintoma | Causa provável | Correção |
|---|---|---|
| Arquivo totalmente ignorado | `AllowOverride None` acima; nome errado (falta o ponto!); diretório errado | Em cPanel já vem liberado — conferir nome/local; em servidor próprio, ajustar o vhost |
| **500** imediato ao salvar | Sintaxe; diretiva de classe bloqueada; módulo ausente; `php_value` com FPM | ErrorLog: `not allowed here` / `Invalid command`; envelopar em `<IfModule>`; trocar `php_value` por `.user.ini` |
| `ERR_TOO_MANY_REDIRECTS` | Destino do redirect re-casa a regra; canonicalização dupla http/www | `RewriteCond` de exclusão anti-loop; consolidar num único 301 (§2.1); testar com `curl -IL` |
| Página abre sem CSS/JS | Assets absolutos engolidos pelo front controller | Exceção de estáticos na cadeia (§2.1) |
| Regra funciona na raiz, falha na subpasta | `RewriteBase` errado; `.htaccess` filho sem `RewriteEngine On` | Lei nº 3 + herança de rewrite não é automática (§3.2) |
| Loop "invisível" com [L] | URL reescrita re-entra e casa de novo | Trocar por `[END]` (§3.6) ou condição de exclusão |
| Auth não pede senha / 401 em loop | `AuthUserFile` com caminho errado/permissão | Caminho **absoluto**; ErrorLog nomeia o arquivo |
| Cache/compressão "não funciona" | Módulo ausente no provedor (silenciado pelo `<IfModule>`) | `curl -I` conferindo `Content-Encoding`/`Cache-Control`; abrir chamado no provedor |
| `Options` dá 500 | `AllowOverride Options` restrito no provedor | Usar somente formas `+`/`-` permitidas, ou remover |

### 4.2 Classes de override × diretivas deste guia

| Classe | Diretivas usadas aqui |
|---|---|
| `FileInfo` | `RewriteEngine/Cond/Rule/Base`, `Redirect*`, `ErrorDocument`, `Header`, `RequestHeader`, `AddType`, `SetHandler`, `SetEnvIf`, `AddDefaultCharset`, mod_deflate |
| `AuthConfig` | `AuthType`, `AuthName`, `AuthUserFile`, `Require valid-user/user` |
| `Limit` | `Require all/ip/host/env` (variantes de origem) |
| `Indexes` | `DirectoryIndex`, mod_expires (`ExpiresActive/ByType`) |
| `Options` | `Options ±…` |

### 4.3 Checklist do .htaccess bem escrito

1. Confirme que o `.htaccess` é a ferramenta certa — controlando o vhost, a regra pertence ao vhost.
2. Bloqueio de dotfiles/artefatos e `Options -Indexes` em qualquer raiz pública (§1.2).
3. Canonicalização https+www em **um** redirect combinado (§2.1).
4. Módulos opcionais sempre em `<IfModule>`; nada de `php_value` sem certeza de mod_php.
5. Um `RewriteEngine On` por arquivo que reescreve; padrões sem barra inicial; anti-loop em todo redirect; `[END]` quando `[L]` reentrar.
6. Regras específicas antes, front controller por último.
7. Teste incremental com `curl`; backup da última versão funcional; sondas de depuração removidas ao final.

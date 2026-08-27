# Manual Consolidado: .htaccess de Produção — Boas Práticas
## Estrutura, Ordem, Front Controller, Proteção, Containers e Otimização — Tudo Comentado

*Documento-síntese da série de referência Apache 2.4 — o espelho do Manual de vhosts, para o contexto onde o `.htaccess` é a ferramenta legítima: **hospedagem compartilhada/cPanel**, onde você não tem acesso ao vhost. **Todos os blocos são comentados explicando o que cada coisa faz** — escrito para ser copiado, adaptado e entendido seis meses depois.*

> **Antes de tudo:** se você controla o servidor (vhost acessível), use o Manual de vhosts — regra viva no vhost é validável com `httpd -t`, lida uma vez no boot e imune a `.htaccess` malicioso. Este manual é para quando essa opção **não existe**.

---

## PARTE 1 — OS PRINCÍPIOS (o porquê antes do como)

### 1.1 As dez regras que governam o .htaccess

```
 1. Ele só existe se o AllowOverride do servidor permitir — e cada diretiva
    pertence a uma CLASSE (FileInfo, AuthConfig, Limit, Indexes, Options).
    Diretiva de classe bloqueada = 500 com "not allowed here" no ErrorLog.
 2. É RUNTIME: lido a cada requisição, em cada diretório do caminho.
    Mudança vale na hora — e erro de sintaxe derruba na hora (não existe
    httpd -t prévio para .htaccess).
 3. É HIERÁRQUICO e CUMULATIVO: o arquivo do diretório mais profundo
    sobrepõe o do mais raso; o que não for redefinido, HERDA.
 4. Padrões de URL são RELATIVOS ao diretório: RewriteRule SEM barra
    inicial, sempre. RewriteBase repõe o prefixo quando necessário.
 5. Containers permitidos: SÓ <Files>/<FilesMatch>, <IfModule>/<IfDefine>,
    <Limit>/<LimitExcept>, <If> e <RequireAll/Any/None>.
    NUNCA <Directory>, <Location> ou <VirtualHost> (erro imediato).
 6. Módulo opcional SEMPRE dentro de <IfModule> — você não controla o que
    o provedor carregou, e diretiva de módulo ausente = 500 no site inteiro.
 7. Reescrita per-directory roda em RODADAS: [L] encerra a rodada (a URL
    nova RE-ENTRA e o arquivo é lido de novo); [END] encerra de vez.
    Todo redirect precisa de condição anti-loop.
 8. Classifique na ENTRADA (SetEnvIf), decida em todo lugar — o mesmo
    vocabulário de marcas do manual de vhosts funciona aqui.
 9. A ORDEM DENTRO DO ARQUIVO importa: canonicalização primeiro, regras
    específicas depois, front controller POR ÚLTIMO (regra genérica engole).
10. Teste INCREMENTAL com curl (nunca só o navegador — 301 cacheia) e
    mantenha backup da última versão funcional (.htaccess.ok).
```

### 1.2 A estrutura do arquivo — o que vem primeiro

A ordem interna canônica de um `.htaccess` de raiz pública:

```
┌ 1. BASE ──────────────── DirectoryIndex, Options, charset, ErrorDocument
├ 2. BLOQUEIOS ─────────── FilesMatch de dotfiles/segredos/artefatos
├ 3. MARCAS ────────────── SetEnvIf (classificação que dirige o resto)
├ 4. CANONICALIZAÇÃO ───── https+www em UM 301 (antes de tudo que roteia)
├ 5. PROTEÇÕES ─────────── métodos, hotlink, limites
├ 6. CONTEÚDO ──────────── compressão, cache, headers (módulos em IfModule)
└ 7. FRONT CONTROLLER ──── a regra genérica, POR ÚLTIMO (regra nº 9)
```

*Por que esta ordem: a canonicalização precisa rodar antes do roteador (senão você roteia a URL errada e redireciona depois — dois saltos); os bloqueios vêm cedo porque negar barato primeiro poupa todo o resto; e o front controller é um "pega-tudo" — qualquer coisa depois dele nunca executa.*

---

## PARTE 2 — O .HTACCESS DE PRODUÇÃO COMPLETO (o modelo)

```apache
# ==================================================================
# public_html/.htaccess — TEMPLATE DE PRODUÇÃO (cPanel/compartilhada)
# Cada seção numerada segue a ordem da Parte 1.
# ==================================================================

# ==================================================================
# SEÇÃO 1 — BASE (identidade do diretório)
# ==================================================================
DirectoryIndex index.php index.html
#  └ o que servir quando a URL aponta um diretório (classe: Indexes)

Options -Indexes
#  └ NUNCA listar o conteúdo de pastas sem índice (classe: Options)
#    ⚠ sempre com -/+ : a forma absoluta (Options Indexes) substitui
#    todo o conjunto herdado e exige AllowOverride Options pleno

AddDefaultCharset UTF-8
#  └ charset padrão p/ text/html sem declaração (classe: FileInfo)

ErrorDocument 404 /erros/404.html
ErrorDocument 403 /erros/403.html
ErrorDocument 500 "Erro interno. Tente novamente em instantes."
#  └ páginas de erro do site; a string literal é o fallback à prova
#    de "a própria página de erro também quebrou"

# ==================================================================
# SEÇÃO 2 — BLOQUEIOS (negar barato, negar cedo)
# ==================================================================
<FilesMatch "^\.(env|git.*|htaccess|htpasswd|user\.ini)">
    Require all denied
</FilesMatch>
#  └ dotfiles e segredos: .env com credenciais, o PRÓPRIO .htaccess,
#    o .htpasswd, o .user.ini — nada disso jamais é servido
#    (classe do Require: Limit)

<FilesMatch "\.(bak|old|orig|save|swp|sql|log|ini)$">
    Require all denied
</FilesMatch>
#  └ artefatos de edição/backup/dump que vazam código e dados
#    (o clássico config.php.bak que entrega o banco inteiro)

<Files "composer.json">
    Require all denied
</Files>
#  └ container LITERAL <Files> p/ um arquivo específico:
#    metadado de build não se serve

# ==================================================================
# SEÇÃO 3 — MARCAS (classificar na entrada — regra nº 8)
# Estas variáveis dirigem cache, compressão, log e proteções abaixo.
# ==================================================================
<IfModule mod_setenvif.c>
    SetEnvIf Request_URI "\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$" estatico
    #  └ todo asset estático (decide cookie, cache e a exceção do roteador)
    SetEnvIf Request_URI "\.[0-9a-f]{8,}\.(css|js|woff2)$" imutavel
    #  └ asset versionado por hash no nome → cache eterno
    SetEnvIf Request_URI "\.(png|jpe?g|webp|zip|gz|woff2)$" no-gzip
    #  └ CONTRATO com mod_deflate: já-comprimidos não gastam CPU
    SetEnvIfNoCase User-Agent "(UptimeRobot|Pingdom)" monitor
    #  └ monitoramento externo marcado (uso: exclusões e diagnóstico)
</IfModule>

# ==================================================================
# SEÇÃO 4 — CANONICALIZAÇÃO (https + www em UM único 301)
# ==================================================================
RewriteEngine On
#  └ o motor nasce DESLIGADO; ligar uma vez por arquivo que reescreve
RewriteBase /
#  └ prefixo de URL p/ alvos relativos; na raiz do domínio é "/"
#    (em subpasta seria /subpasta/ — regra nº 4)

# Duas condições em OU ([OR] explícito!) → um só redirect combinado.
# Encadear "http→https" E DEPOIS "nu→www" separados = DOIS saltos por
# acesso: latência dobrada e diluição de SEO.
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteCond %{HTTP_HOST} ^(?:www\.)?(.+)$ [NC]
RewriteRule ^(.*)$ https://www.%1/$1 [R=301,L]
#  └ %1 = captura da ÚLTIMA RewriteCond casada (o domínio nu);
#    $1 = captura do RewriteRule (o caminho) — dois espaços de captura!
#  ⚠ durante testes use R=302 (não cacheia); promova a 301 só estável.

# ==================================================================
# SEÇÃO 5 — PROTEÇÕES
# ==================================================================
# 5a. Métodos HTTP: só o que o site usa (o resto = 403)
RewriteCond %{REQUEST_METHOD} !^(GET|HEAD|POST)$
RewriteRule ^ - [F,L]
#  └ alvo "-" = não reescreve nada, só aplica a flag; [F] = 403.
#    Via rewrite (e não <Limit>) porque Limit só cobre os métodos
#    LISTADOS — os não-listados escapam (alçapão clássico).

# 5b. Anti-hotlinking: suas imagens só no seu site
RewriteCond %{HTTP_REFERER} !^$
#  └ Referer VAZIO é permitido (acessos diretos, apps, proxies legítimos)
RewriteCond %{HTTP_REFERER} !^https?://(www\.)?empresa\.com\.br/ [NC]
#  └ permitido: o próprio site, http/https, com/sem www
RewriteRule \.(png|jpe?g|webp|gif)$ - [F,NC,L]
#  └ só intercepta os tipos que interessam; o resto passa

# ==================================================================
# SEÇÃO 6 — CONTEÚDO (compressão, cache, headers)
# Módulos opcionais SEMPRE em <IfModule> — regra nº 6: sem o envelope,
# módulo ausente no provedor = 500 no site inteiro.
# ==================================================================
# 6a. Compressão — só texto (a marca no-gzip da Seção 3 já poupou o resto)
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# 6b. Cache do cliente — frescor por tipo (mod_expires; classe: Indexes)
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp              "access plus 30 days"
    ExpiresByType image/png               "access plus 30 days"
    ExpiresByType image/jpeg              "access plus 30 days"
    ExpiresByType image/svg+xml           "access plus 30 days"
    ExpiresByType font/woff2              "access plus 365 days"
    ExpiresByType text/css                "access plus 7 days"
    ExpiresByType application/javascript  "access plus 7 days"
    ExpiresByType text/html               "access plus 0 seconds"
    #  └ HTML dinâmico NUNCA ganha frescor — só validação (304)
</IfModule>

# 6c. Ajuste fino por marca (mod_headers)
<IfModule mod_headers.c>
    # asset versionado: imutável por 1 ano — mudou? o NOME muda
    Header set Cache-Control "public, max-age=31536000, immutable" env=imutavel

    # fontes precisam de CORS liberado (falham cross-origin sem isto)
    <FilesMatch "\.(woff2?|ttf)$">
        Header set Access-Control-Allow-Origin "*"
    </FilesMatch>

    # ---- Segurança de conteúdo: SEMPRE "always" ----
    # (a tabela padrão abandona as páginas de erro 4xx/5xx — sem o
    #  always, o scanner de segurança reprova seu 404)
    Header always set X-Content-Type-Options "nosniff"    # sem sniffing de tipo
    Header always set X-Frame-Options "SAMEORIGIN"        # anti-clickjacking
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always unset X-Powered-By                      # apaga carimbo do PHP
    #  └ unset nas DUAS tabelas quando em dúvida:
    Header unset X-Powered-By
</IfModule>

# ==================================================================
# SEÇÃO 7 — FRONT CONTROLLER (o roteador — POR ÚLTIMO, regra nº 9)
# ==================================================================
# performance: estáticos nem entram na cadeia PHP
RewriteCond %{ENV:estatico} !=1
#  └ a marca da Seção 3 decidindo a rota (mais legível que repetir regex)
RewriteCond %{REQUEST_FILENAME} !-f
#  └ é um ARQUIVO real no disco? serve direto (a condição olha o
#    FILESYSTEM; o padrão da regra olha a URL — os dois mundos)
RewriteCond %{REQUEST_FILENAME} !-d
#  └ é uma PASTA real? idem
RewriteRule ^(.*)$ index.php [QSA,L]
#  └ todo o resto entra pelo ponto único; a aplicação lê a rota de
#    $_SERVER['REQUEST_URI']. Padrão SEM barra inicial (regra nº 4);
#    [QSA] preserva a query string; [L] encerra a rodada.
#  ⚠ se a URL reescrita voltar a casar regras e gerar loop, o
#    antídoto é [END] no lugar de [L] (regra nº 7).
```

---

## PARTE 3 — OS .HTACCESS SATÉLITES (herança em ação)

A hierarquia trabalha a seu favor: o arquivo da raiz vale para tudo; cada subdiretório **adiciona** só a sua diferença (regra nº 3).

### 3.1 Área administrativa — autenticação por cima da herança

```apache
# ============ public_html/admin/.htaccess ============
# SÓ a autenticação: compressão, cache, bloqueios e canonicalização
# da raiz CONTINUAM valendo aqui — herança é cumulativa.

AuthType Basic
#  └ esquema: prompt nativo do navegador (classe: AuthConfig)
AuthName "Administração"
#  └ texto do prompt E chave do cache de credenciais do navegador
AuthUserFile "/home/conta/.htpasswds/admin/passwd"
#  └ caminho ABSOLUTO, SEMPRE fora da public_html — dentro dela,
#    é um download público esperando acontecer.
#    Criação: htpasswd -B -c <arquivo> <usuario>  (-B = bcrypt;
#    -c SÓ na primeira vez — depois ele APAGA os demais usuários!)
Require valid-user
#  └ a peça de autorização que CONSOME a identidade — sem ela,
#    o Apache acusa erro (autenticar sem autorizar não faz sentido)

# variante composta — do escritório entra direto, de fora exige senha:
# <RequireAny>
#     Require ip 200.100.50.25
#     Require valid-user
# </RequireAny>

# ⚠ Basic transmite em Base64 (REVERSÍVEL). Só é aceitável porque a
#   Seção 4 da raiz já força HTTPS em tudo. Sem TLS = senha em claro.
```

### 3.2 Uploads — servidos, jamais executados

```apache
# ============ public_html/uploads/.htaccess ============
Options -Indexes -ExecCGI
#  └ sem listagem, sem CGI (formas +/- : ajuste incremental à herança)

<FilesMatch "\.ph(ar|p[0-9]?|tml)$">
    SetHandler none
    #  └ desarma o handler PHP: mesmo que um .php seja gravado aqui
    #    (falha de upload), ele NÃO executa
    Require all denied
    #  └ cinto E suspensório: nem o código-fonte é servido
</FilesMatch>
#  ⚠ alguns provedores bloqueiam SetHandler no .htaccess (500
#    "not allowed here"): mantenha só o Require all denied — que
#    sozinho já impede a execução via web.

<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    #  └ navegador nunca "adivinha" tipo executável p/ o que servir daqui
</IfModule>
```

### 3.3 Subpasta com aplicação própria — o roteador local

```apache
# ============ public_html/segundodominio/.htaccess ============
# CENÁRIO cPanel: domínio adicional apontando p/ subpasta da mesma conta.

RewriteEngine On
#  └ OBRIGATÓRIO repetir: herança de rewrite NÃO é automática —
#    cada .htaccess que reescreve liga o próprio motor
RewriteBase /
#  └ "/" porque o domínio adicional enxerga ESTA pasta como raiz;
#    se fosse acessada como site.com/segundodominio, seria
#    RewriteBase /segundodominio/

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

E no `.htaccess` da **raiz**, o roteamento por host (quando o provedor aponta o domínio adicional para a mesma `public_html`):

```apache
# (na raiz, ANTES do front controller — regra específica antes da genérica)
RewriteCond %{HTTP_HOST} ^(www\.)?segundodominio\.com\.br$ [NC]
#  └ ⚠ HTTP_HOST contém APENAS o hostname — nunca "http://" nem o path
RewriteCond %{REQUEST_URI} !^/segundodominio/
#  └ ANTI-LOOP obrigatório: sem isto, a reescrita re-casa consigo mesma
RewriteRule ^(.*)$ /segundodominio/$1 [L]
#  └ reescrita INTERNA (sem [R]): a URL no navegador não muda
```

### 3.4 Modo manutenção — liga/desliga por FTP, sem tocar em nada

```apache
# (na raiz, logo após a canonicalização)
RewriteCond %{DOCUMENT_ROOT}/MAINT -f
#  └ liga-se CRIANDO o arquivo MAINT na pasta (touch/upload);
#    desliga-se apagando — zero edição de configuração
RewriteCond %{REMOTE_ADDR} !^200\.100\.50\.25$
#  └ o IP da equipe continua navegando normalmente
RewriteCond %{REQUEST_URI} !^/manutencao\.html$
RewriteCond %{REQUEST_URI} !\.(css|png|svg)$
#  └ a própria página de manutenção e seus assets passam (senão: LOOP)
RewriteRule ^ /manutencao.html [R=503,L]
#  └ 503 = "temporário" p/ buscadores (não desindexa o site)
ErrorDocument 503 /manutencao.html
<IfModule mod_headers.c>
    Header always set Retry-After "3600" "expr=%{REQUEST_STATUS} == 503"
    #  └ instrui clientes/bots a voltarem em 1h
</IfModule>
```

---

## PARTE 4 — RESUMO OPERACIONAL

### 4.1 O fluxo de mudança (regra nº 10 — sem httpd -t, a disciplina substitui)

```bash
# 1. backup da versão funcional ANTES de mexer:
cp .htaccess .htaccess.ok        # (o bloqueio da Seção 2 impede servi-lo)

# 2. mudança em BLOCO PEQUENO (uma seção por vez — não existe validador
#    prévio: um typo = 500 imediato em produção)

# 3. teste com curl — a fonte da verdade, imune ao cache do navegador:
curl -I  https://www.empresa.com.br/            # status + headers de UMA resposta
curl -IL http://empresa.com.br/pagina           # a CADEIA de redirects inteira
curl -I  https://www.empresa.com.br/uploads/x.php   # 403 esperado
curl -I -u usuario:senha https://www.empresa.com.br/admin/   # 200 com credencial

# 4. deu 500? o ErrorLog do cPanel (Metrics → Errors) diz qual linha:
#    "not allowed here"  = classe de override bloqueada pelo provedor
#    "Invalid command"   = módulo ausente/typo → envelopar em <IfModule>

# 5. rewrite se comportando estranho? Sem acesso a rewrite:trace, use sondas:
#    RewriteRule ^teste$ - [E=REGRA_X:1,L]      → conferir em $_SERVER no PHP
#    Header always set X-Debug "bloco-4"        → conferir no curl -I
#    …e bisseção: comente metade, teste, repita — O(log n) até o culpado.
```

### 4.2 Onde cada decisão mora — a tabela de responsabilidades

| Decisão | Ferramenta | Onde no template |
|---|---|---|
| Arquivo-índice do diretório | `DirectoryIndex` | Seção 1 |
| Nunca listar pastas | `Options -Indexes` | Seção 1 |
| Arquivos que jamais servem | `<FilesMatch>` + `Require all denied` | Seção 2 |
| Classificação de requisições | `SetEnvIf` (marcas) | Seção 3 |
| https+www canônicos | RewriteCond `[OR]` + um 301 | Seção 4 |
| Métodos permitidos | RewriteCond `REQUEST_METHOD` + `[F]` | Seção 5a |
| Imagens só no seu site | condições de Referer + `[F]` | Seção 5b |
| Compressão | `mod_deflate` em `<IfModule>` | Seção 6a |
| Cache por tipo | `mod_expires` | Seção 6b |
| Cache por marca + segurança | `mod_headers` (`always`!) | Seção 6c |
| Roteamento p/ a aplicação | front controller (último!) | Seção 7 |
| Senha num diretório | AuthType/AuthUserFile/Require | Satélite 3.1 |
| Uploads inertes | `SetHandler none` + denied | Satélite 3.2 |
| App em subpasta | `.htaccess` próprio + RewriteBase | Satélite 3.3 |
| Manutenção sem deploy | arquivo-flag MAINT | Satélite 3.4 |

### 4.3 Os erros que este template já previne

```
✔ 500 por módulo ausente        → todo módulo opcional em <IfModule>
✔ .env/.htpasswd baixáveis       → Seção 2 nega dotfiles e artefatos
✔ redirect duplo http→https→www  → canonicalização em UM 301 (Seção 4)
✔ loop de redirect               → anti-loop no multi-domínio e na manutenção
✔ regra que "nunca roda"         → front controller por último (regra nº 9)
✔ headers sumindo no 404         → segurança sempre com "always"
✔ upload .php executado          → SetHandler none + denied em /uploads
✔ senha Basic em texto claro     → Basic só existe porque a Seção 4 força TLS
✔ CPU queimada em gzip inútil    → contrato no-gzip nos pré-comprimidos
✔ regra some ao mover p/ subpasta→ RewriteBase + RewriteEngine On locais
✔ 301 fantasma pós-correção      → desenvolver com 302 + testar com curl
✔ mudança destruindo o site      → backup .htaccess.ok + blocos pequenos
✔ php_value dando 500 no FPM     → ausente do template (use .user.ini)
✔ <Directory>/<Location> no arquivo → só os containers da regra nº 5
```

### 4.4 O lembrete final

Este manual existe para o cenário compartilhado. No dia em que este site migrar para um servidor sob seu controle (VPS/dedicado), a migração é mecânica: Seções 1–7 viram o `<Directory>` e o corpo do vhost do Manual de vhosts — removendo o `RewriteBase`, conferindo as barras iniciais dos padrões, e ganhando de volta o `httpd -t`, o `rewrite:trace3` e a leitura única no boot.

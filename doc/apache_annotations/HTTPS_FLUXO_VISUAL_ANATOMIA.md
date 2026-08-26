# FLUXO VISUAL E TÉCNICO DO HTTPS NO .htaccess

**Objetivo:** Entender COMO e POR QUE cada parte do .htaccess funciona quando cliente acessa seu site

---

## PARTE 1: FLUXO GERAL DE UMA REQUISIÇÃO

```
┌─────────────────────────────────────────────────────────────────┐
│  CLIENTE (Navegador)                                             │
│  Digita: http://ipaccontabilidade.com.br/usuarios/123           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTP Request
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  INTERNET / PROTOCOLO HTTP                                       │
│  Camada de transporte (inseguro, sem criptografia)               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Chega ao servidor
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  APACHE (Servidor Web - seu VPS)                                 │
│  Port 80 (HTTP) ou 443 (HTTPS)                                   │
│                                                                   │
│  PASSO 1: Lê requisição HTTP                                     │
│  GET /usuarios/123 HTTP/1.1                                      │
│  Host: ipaccontabilidade.com.br                                  │
│                                                                   │
│  PASSO 2: Verifica .htaccess na pasta                            │
│  Arquivo: /public_html/.htaccess                                 │
│                                                                   │
│  PASSO 3: Processa regras sequencialmente                         │
│  ├─ RewriteEngine On      (ativa mod_rewrite)                    │
│  ├─ RewriteCond ...       (verifica condições)                   │
│  ├─ RewriteRule ...       (executa reescrita)                    │
│  └─ (próxima regra, se houver)                                   │
│                                                                   │
│  PASSO 4: Retorna resposta com headers                           │
│  HTTP/1.1 301 Moved Permanently                                  │
│  Location: https://ipaccontabilidade.com.br/usuarios/123         │
│  Cache-Control: public, max-age=...                              │
│  X-Frame-Options: SAMEORIGIN                                     │
│  Content-Encoding: gzip                                          │
│                                                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTP Response
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  CLIENTE (Navegador)                                             │
│  Recebe: Status 301 + novo Location                              │
│  "Vou redirecionar para HTTPS..."                                │
│                                                                   │
│  Faz nova requisição:                                            │
│  https://ipaccontabilidade.com.br/usuarios/123                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTPS Request (criptografado!)
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  INTERNET / PROTOCOLO HTTPS                                      │
│  Camada de criptografia TLS/SSL                                  │
│  Dados criptografados = seguro                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ Chega ao servidor (443)
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  APACHE (Port 443 - HTTPS)                                       │
│                                                                   │
│  PASSO 1: Descriptografa requisição (TLS)                        │
│  [Certificado SSL descriptografa dados]                          │
│                                                                   │
│  PASSO 2: Processa .htaccess novamente                           │
│  RewriteCond %{HTTPS} off  ← FALSO (é HTTPS!)                   │
│  ↓                         ← Regra NÃO executa (pula adiante)    │
│  [Próximas regras]                                               │
│                                                                   │
│  PASSO 3: Aplica cache, compressão, headers                      │
│  - Cache-Control: max-age=... (mod_expires)                      │
│  - Content-Encoding: gzip (mod_deflate)                          │
│  - X-Frame-Options: SAMEORIGIN (mod_headers)                     │
│                                                                   │
│  PASSO 4: Processa rewrite de URL                                │
│  /usuarios/123 → index.php?rota=/usuarios/123                    │
│  [PHP Framework roteia para Controller apropriado]               │
│                                                                   │
│  PASSO 5: Envia resposta (criptografada)                         │
│  HTTP/1.1 200 OK                                                 │
│  [Conteúdo da página]                                            │
│  [Comprimido com gzip]                                           │
│  [Headers de segurança]                                          │
│                                                                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         │ HTTPS Response (criptografado!)
                         ↓
┌─────────────────────────────────────────────────────────────────┐
│  CLIENTE (Navegador)                                             │
│  Recebe: Resposta criptografada                                  │
│  - Descriptografa (TLS)                                          │
│  - Descompacta (gzip)                                            │
│  - Aplica cache em disco                                         │
│  - Renderiza página                                              │
│                                                                   │
│  Resultado: Página carregada de forma SEGURA ✅                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## PARTE 2: QUEBRA ANATÔMICA DE CADA REGRA

### REGRA 1: Forçar HTTPS

#### Código:
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### Quebra linha por linha:

```
LINHA 1: RewriteCond %{HTTPS} off
│
├─ RewriteCond
│   └─ Tipo: Condicional (IF statement)
│
├─ %{HTTPS}
│   └─ Variável Apache: estado do protocolo
│      ├─ Valor: "off"  = HTTP (conexão insegura)
│      └─ Valor: "on"   = HTTPS (conexão segura)
│
├─ off
│   └─ Valor a comparar
│      └─ "Se %{HTTPS} é igual a 'off'..."
│
└─ Resultado: VERDADEIRO se cliente usou HTTP

LINHA 2: RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
│
├─ RewriteRule
│   └─ Tipo: Regra de reescrita
│
├─ ^(.*)$
│   └─ Pattern (regex)
│      ├─ ^ = início da string
│      ├─ (.*) = captura TUDO = grupo 1
│      └─ $ = fim da string
│      └─ Resultado: $1 = URI original inteira
│
├─ https://%{HTTP_HOST}%{REQUEST_URI}
│   └─ Substituição (novo URL)
│      ├─ https:// = força protocolo HTTPS
│      ├─ %{HTTP_HOST} = mantém nome do host
│      │                 (ex: ipaccontabilidade.com.br)
│      └─ %{REQUEST_URI} = mantém URI original
│                          (ex: /usuarios/123)
│      └─ Resultado: https://ipaccontabilidade.com.br/usuarios/123
│
├─ [L,R=301]
│   └─ Flags (configuração da regra)
│      ├─ L = Last (parar de processar regras subsequentes)
│      └─ R=301 = Redirect com status 301 (permanente)
│                 ├─ Status 301 = redirecionamento permanente
│                 ├─ Status 302 = redirecionamento temporário
│                 └─ 301 é melhor para SEO (Google segue)
│
└─ Resultado: HTTP → HTTPS com 301
```

#### Fluxo de execução:

```
[Cliente solicita] http://ipaccontabilidade.com.br/usuarios/123
│
├─ Apache analisa
│  └─ RewriteCond %{HTTPS} off
│     ├─ %{HTTPS} = "off" (é HTTP)
│     └─ off == off? SIM ✅ (condição verdadeira)
│
├─ Como condição é verdadeira
│  └─ RewriteRule executa
│     ├─ ^(.*)$ = captura "/usuarios/123" em $1
│     └─ Reescreve para: https://ipaccontabilidade.com.br/usuarios/123
│
├─ Flag [L] = para outras regras
│  └─ Não processa mais regras (pula para resposta)
│
├─ Flag [R=301] = redireciona com 301
│  └─ Envia ao cliente: "Vá para https://..."
│
└─ [Cliente recebe] HTTP/1.1 301 Moved Permanently
   └─ Location: https://ipaccontabilidade.com.br/usuarios/123
```

---

### REGRA 2: Remover WWW (Canonicalização)

#### Código:
```apache
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^(.*)$ https://%1/$1 [L,R=301]
```

#### Quebra linha por linha:

```
LINHA 1: RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
│
├─ %{HTTP_HOST}
│   └─ Variável: nome do host da requisição
│      ├─ Exemplo: www.ipaccontabilidade.com.br
│      └─ Exemplo: ipaccontabilidade.com.br (sem www)
│
├─ ^www\.(.+)$
│   └─ Pattern (regex para match)
│      ├─ ^ = início
│      ├─ www\. = literalmente "www." (\ escapa o ponto)
│      ├─ (.+) = captura 1+ caracteres = grupo 1 (%1)
│      │         (tudo APÓS www. = domínio sem www)
│      └─ $ = fim
│      └─ Exemplo match: "www.ipaccontabilidade.com.br"
│         └─ %1 = "ipaccontabilidade.com.br" (sem www)
│
├─ [NC]
│   └─ Flag: NoCase (ignora maiúscula/minúscula)
│      └─ www = WWW = Www (todos funcionam)
│
└─ Resultado: Verdadeiro se %{HTTP_HOST} tem www

LINHA 2: RewriteRule ^(.*)$ https://%1/$1 [L,R=301]
│
├─ ^(.*)$
│   └─ Captura TUDO da URI em $1
│      ├─ Exemplo: /usuarios/123?param=valor
│      └─ $1 = /usuarios/123?param=valor
│
├─ https://%1/$1
│   └─ Substituição
│      ├─ https:// = força HTTPS
│      ├─ %1 = domínio SEM www (de RewriteCond)
│      │        (ipaccontabilidade.com.br)
│      └─ /$1 = URI original (/usuarios/123?param=valor)
│      └─ Resultado: https://ipaccontabilidade.com.br/usuarios/123?param=valor
│
└─ [L,R=301] = Last, Redirect 301
```

#### Fluxo de execução:

```
[Cliente solicita] https://www.ipaccontabilidade.com.br/usuarios/123
│
├─ Apache analisa
│  └─ RewriteCond %{HTTP_HOST} ^www\.(.+)$
│     ├─ %{HTTP_HOST} = "www.ipaccontabilidade.com.br"
│     ├─ Match com padrão? SIM ✅
│     └─ %1 = "ipaccontabilidade.com.br"
│
├─ Como condição é verdadeira
│  └─ RewriteRule executa
│     ├─ ^(.*)$ = captura "/usuarios/123" em $1
│     ├─ Reescreve para: https://ipaccontabilidade.com.br/usuarios/123
│     │ (nota: usa %1 = domínio sem www, não %{HTTP_HOST})
│     └─ Result: "domínio sem www" + "URI original"
│
└─ [Cliente recebe] HTTP/1.1 301 Moved Permanently
   └─ Location: https://ipaccontabilidade.com.br/usuarios/123
```

#### Por que %1 ao invés de %{HTTP_HOST}?

```
ALTERNATIVA ERRADA:
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [L,R=301]
│
└─ Problema: %{HTTP_HOST} ainda é "www.ipaccontabilidade.com.br"
   └─ Resultado: https://www.ipaccontabilidade.com.br/usuarios/123
   └─ NÃO remove www! ❌ Loop infinito!

ALTERNATIVA CORRETA:
RewriteRule ^(.*)$ https://%1/$1 [L,R=301]
│
└─ Correto: %1 vem da capture group da RewriteCond
   └─ %1 = "ipaccontabilidade.com.br" (sem www)
   └─ Resultado: https://ipaccontabilidade.com.br/usuarios/123
   └─ Remove www! ✅
```

---

## PARTE 3: MÓDULOS E COMO FUNCIONAM

### Módulo 1: mod_rewrite

```
NOME: mod_rewrite
FUNÇÃO: Reescrever URLs internamente ou redirecionar
DIRETIVAS: RewriteEngine, RewriteCond, RewriteRule
VERIFICAÇÃO: cPanel → Apache Modules → search "rewrite"

COMO FUNCIONA:
┌─────────────────────────────────────┐
│ 1. Cliente solicita URL              │
│    http://site.com/pagina.html       │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 2. Apache lê .htaccess               │
│    RewriteEngine On                  │
│    RewriteCond %{REQUEST_FILENAME}... │
│    RewriteRule ^(.*)$ target.php     │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 3. Apache verifica condições         │
│    - Se arquivo existe?              │
│    - Se é diretório?                 │
│    - Se URL match pattern?           │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 4a. Se TODAS condições verdadeiras   │
│    → Executa rule (reescreve URL)    │
│                                      │
│ 4b. Se alguma falsa                  │
│    → Pula para próxima regra         │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 5. PHP processa URL reescrita        │
│    (ou redireciona se R=301)         │
└─────────────────────────────────────┘
```

### Módulo 2: mod_deflate

```
NOME: mod_deflate
FUNÇÃO: Comprimir conteúdo com GZIP antes de enviar
DIRETIVA: AddOutputFilterByType DEFLATE {tipo_mime}
VERIFICAÇÃO: cPanel → Apache Modules → search "deflate"

COMO FUNCIONA:
┌─────────────────────────────────────┐
│ 1. PHP gera conteúdo                 │
│    <!DOCTYPE html><body>...          │
│    Tamanho: 100 KB                   │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 2. Apache vê Content-Type: text/html │
│    Verifica: text/html comprime?     │
│    Resposta: SIM (mod_deflate ativo) │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 3. Apache aplica GZIP compression    │
│    Algoritmo: DEFLATE (sem-perda)    │
│    Tamanho após: 30 KB (70% menor!)  │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 4. Apache envia header:              │
│    Content-Encoding: gzip           │
│    [Dados comprimidos]              │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 5. Cliente recebe e descompacta      │
│    Browser vê: conteúdo normal      │
│    (descompressão automática)        │
│                                      │
│ RESULTADO: 70% menos dados → 3x mais │
│            rápido carregar! ✅        │
└─────────────────────────────────────┘
```

### Módulo 3: mod_expires

```
NOME: mod_expires
FUNÇÃO: Definir quando navegador deve reusar arquivo em cache local
DIRETIVAS: ExpiresActive, ExpiresByType, ExpiresDefault
VERIFICAÇÃO: cPanel → Apache Modules → search "expires"

COMO FUNCIONA:
┌─────────────────────────────────────┐
│ PRIMEIRA VISITA:                     │
│                                      │
│ 1. Cliente acessa site.com/logo.png  │
│    [Arquivo não tem em cache]        │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 2. Apache envia arquivo:             │
│    Content-Type: image/png           │
│    Content-Length: 100000            │
│    [Dados do arquivo]                │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 3. Apache envia header Expires:      │
│    Expires: Mon, 24 Aug 2027 12:... │
│    (data atual + 1 ano)              │
│                                      │
│    OU header Cache-Control:          │
│    Cache-Control: max-age=31536000   │
│    (31536000 segundos = 1 ano)       │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 4. Browser guarda localmente:        │
│    Disco: /cache/logo.png            │
│    Data expiração: Aug 2027          │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ SEGUNDA VISITA (1 dia depois):       │
│                                      │
│ 1. Cliente acessa site.com/logo.png  │
│    novamente                         │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 2. Browser verifica cache:           │
│    - Existe /cache/logo.png?         │
│    - SIM ✅                          │
│    - Está expirado?                  │
│    - NÃO ✅ (expira em Aug 2027)    │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 3. Browser NÃO requisita servidor    │
│    Carrega de disco local            │
│    Tempo de carregamento: <10ms ✅   │
│                                      │
│    (Vs 1º acesso que baixou: 500ms)  │
└─────────────────────────────────────┘

REGRA DE TEMPO:
├─ Imagens: 1 ano (mudam raramente)
├─ CSS/JS: 1 mês (podem atualizar)
├─ HTML: 1 dia (muda frequente)
└─ API/JSON: 1 hora (dados dinâmicos)
```

### Módulo 4: mod_headers

```
NOME: mod_headers
FUNÇÃO: Enviar headers HTTP customizados
DIRETIVA: Header set {Nome} "{Valor}"
VERIFICAÇÃO: Geralmente ativo por padrão

COMO FUNCIONA:
┌─────────────────────────────────────┐
│ 1. Apache processa requisição        │
│    GET /index.html HTTP/1.1          │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 2. Apache vê "Header set ..."        │
│    em .htaccess                      │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 3. Apache prepara response:          │
│    HTTP/1.1 200 OK                   │
│                                      │
│    [Todos headers de resposta]       │
│    + Header set cache-Control ...    │
│    + Header set X-Frame-Options ...  │
│    + Header set X-Content-Type ...   │
│    [Conteúdo do arquivo]             │
└────────────┬────────────────────────┘
             │
┌────────────▼────────────────────────┐
│ 4. Browser recebe headers:           │
│    Cache-Control: instrui cache      │
│    X-Frame-Options: previne ataque   │
│    X-Content-Type: previne MIME      │
│    ... (outros)                      │
│                                      │
│    Browser aplica regras de          │
│    segurança e cache                 │
└─────────────────────────────────────┘
```

---

## PARTE 4: EXEMPLO REAL - FLUXO COMPLETO

### Cenário: Usuário acessa seu site pela primeira vez

```
TEMPO 0:00 - Cliente digitacesso pela primeira vez
┌─────────────────────────────────────────────────────┐
│ Browser: Usuário digita URL na barra de endereço    │
│ URL digitada: http://ipaccontabilidade.com.br      │
│ Pressiona: ENTER                                    │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:05 - Requisição HTTP chega no servidor
┌────────────▼────────────────────────────────────────┐
│ Apache recebe:                                       │
│ GET / HTTP/1.1                                      │
│ Host: ipaccontabilidade.com.br                      │
│ Connection: keep-alive                              │
│ User-Agent: Mozilla/5.0...                          │
│                                                      │
│ Porto: 80 (HTTP)                                    │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:06 - Apache lê .htaccess
┌────────────▼────────────────────────────────────────┐
│ RewriteEngine On                 ← Ativa mod_rewrite│
│ RewriteCond %{HTTPS} off         ← Verifica HTTPS   │
│                                                      │
│ %{HTTPS} = "off" (é HTTP)        ← VERDADEIRO       │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:07 - Executa regra de HTTPS
┌────────────▼────────────────────────────────────────┐
│ RewriteRule ^(.*)$               ← Match!           │
│  https://%{HTTP_HOST}...         ← Reescreve        │
│  [L,R=301]                       ← Redireciona 301  │
│                                                      │
│ Reescrita:                                          │
│ / → https://ipaccontabilidade.com.br/              │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:08 - Apache verifica WWW
┌────────────▼────────────────────────────────────────┐
│ RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]           │
│                                                      │
│ %{HTTP_HOST} = "ipaccontabilidade.com.br"          │
│ Pattern ^www\. não match (NÃO tem www) ← FALSO      │
│                                                      │
│ Regra NÃO executa (pula adiante)                    │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:09 - Próximas regras
┌────────────▼────────────────────────────────────────┐
│ RewriteCond %{REQUEST_FILENAME} !-f                 │
│ REQUEST_FILENAME = /var/www/html/  ← Existe?       │
│ !-f (NÃO é arquivo) ← SIM, é diretório ← VERDADEIRO│
│                                                      │
│ RewriteCond %{REQUEST_FILENAME} !-d                 │
│ !-d (NÃO é diretório) ← FALSO, IS diretório        │
│                                                      │
│ Como uma condição é FALSA, regra não executa       │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:10 - Apache envia resposta 301
┌────────────▼────────────────────────────────────────┐
│ HTTP/1.1 301 Moved Permanently                      │
│ Location: https://ipaccontabilidade.com.br/        │
│ Cache-Control: public, max-age=31536000             │
│ Content-Encoding: gzip                              │
│ X-Frame-Options: SAMEORIGIN                         │
│ X-Content-Type-Options: nosniff                     │
│ X-XSS-Protection: 1; mode=block                     │
│                                                      │
│ Content-Length: 0 (redireciona, sem conteúdo)      │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:15 - Cliente recebe 301
┌────────────▼────────────────────────────────────────┐
│ Browser vê: Status 301                              │
│ Lê header: Location: https://...                    │
│ Interpreta: "Vá para https://ipaccontabilidade..." │
│ Faz nova requisição AUTOMATICAMENTE                 │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:20 - Nova requisição HTTPS
┌────────────▼────────────────────────────────────────┐
│ Browser envia:                                       │
│ GET / HTTP/1.1                                      │
│ Host: ipaccontabilidade.com.br                      │
│ Connection: keep-alive                              │
│ [Headers com Upgrade-Insecure-Requests: 1]         │
│                                                      │
│ Porto: 443 (HTTPS - TLS/SSL)                        │
│ [Conexão criptografada]                             │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:25 - Apache recebe HTTPS
┌────────────▼────────────────────────────────────────┐
│ Apache (port 443) recebe requisição                 │
│ Descriptografa usando certificado SSL              │
│ GET / HTTP/1.1                                      │
│ Host: ipaccontabilidade.com.br                      │
│                                                      │
│ %{HTTPS} = "on" (é HTTPS!) ← MUDOU                  │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:26 - Apache lê .htaccess novamente
┌────────────▼────────────────────────────────────────┐
│ RewriteCond %{HTTPS} off                            │
│ %{HTTPS} = "on" (HTTPS) ← FALSO!                    │
│                                                      │
│ Regra de HTTPS NÃO executa (já está HTTPS)         │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:27 - Verifica cache, compressão, headers
┌────────────▼────────────────────────────────────────┐
│ Módulo mod_expires:                                 │
│  Content-Type: text/html                            │
│  Expiration: access plus 1 day                      │
│  → Envia header Cache-Control: max-age=86400       │
│                                                      │
│ Módulo mod_deflate:                                │
│  Conteúdo: texto puro (HTML)                        │
│  Comprime com GZIP                                  │
│  → Envia header Content-Encoding: gzip             │
│                                                      │
│ Módulo mod_headers:                                │
│  Envia headers de segurança                         │
│  → X-Frame-Options: SAMEORIGIN                      │
│  → X-Content-Type-Options: nosniff                  │
│  → Etc...                                           │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:28 - Processa index.php (framework)
┌────────────▼────────────────────────────────────────┐
│ Arquivo solicitado: /                               │
│ É diretório? SIM                                    │
│ Apache busca index.php/index.html                   │
│ Encontra: /var/www/html/index.php                   │
│                                                      │
│ PHP Framework processa:                             │
│ ├─ Carrega config                                   │
│ ├─ Conecta banco de dados                           │
│ ├─ Executa lógica                                   │
│ └─ Gera HTML                                        │
│                                                      │
│ Output: <!DOCTYPE html>...                          │
│ Tamanho: 150 KB                                      │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:35 - Apache comprime e envia resposta
┌────────────▼────────────────────────────────────────┐
│ Apache aplica GZIP:                                 │
│ Antes: 150 KB                                        │
│ Depois: 45 KB (70% menor!)                           │
│                                                      │
│ HTTP/1.1 200 OK                                     │
│ Content-Type: text/html; charset=utf-8              │
│ Content-Encoding: gzip                              │
│ Cache-Control: public, max-age=86400                │
│ Content-Length: 45000 (comprimido!)                │
│ X-Frame-Options: SAMEORIGIN                         │
│ X-Content-Type-Options: nosniff                     │
│ X-XSS-Protection: 1; mode=block                     │
│ [HTML comprimido com GZIP]                          │
│ [Transmissão: criptografada com HTTPS]             │
└────────────┬────────────────────────────────────────┘
             │
TEMPO 0:40 - Browser recebe resposta HTTPS
┌────────────▼────────────────────────────────────────┐
│ Browser (port 443):                                 │
│ ├─ Recebe dados criptografados                      │
│ ├─ Descriptografa usando certificado TLS            │
│ ├─ Descompacta GZIP → HTML original (150 KB)        │
│ ├─ Armazena em cache local (/cache/index.html)      │
│ │  Validade: 1 dia (86400 segundos)                 │
│ ├─ Aplica headers de segurança                      │
│ │  - X-Frame-Options: NÃO pode iframe               │
│ │  - X-XSS: Bloqueia scripts maliciosos             │
│ ├─ Renderiza página                                 │
│ │  - Parser HTML                                    │
│ │  - Carrega CSS, JS, imagens                       │
│ └─ Exibe ao usuário ✅                               │
│                                                      │
│ Browser mostra: Página carregada com cadeado 🔒    │
│                                                      │
│ Cadeado = HTTPS seguro ✅                            │
└─────────────────────────────────────────────────────┘

TEMPO 0:50 - Segunda visita (1 dia depois)
┌─────────────────────────────────────────────────────┐
│ Usuário volta ao site                               │
│ Browser verifica cache:                             │
│ - Arquivo /cache/index.html existe? SIM             │
│ - Está expirado? NÃO (expirou em 1 dia)             │
│ - Carrega de DISCO LOCAL                            │
│                                                      │
│ Tempo de carregamento: <100ms (vs 500ms primeira)  │
│                                                      │
│ SEM requisição ao servidor ✅                        │
└─────────────────────────────────────────────────────┘
```

---

## PARTE 5: CHECKLIST DE IMPLEMENTAÇÃO

### Antes de colocar em produção:

```
VERIFICAÇÃO TÉCNICA:
┌─────────────────────────────────────────────────────┐
│ [ ] Certificado SSL instalado?                      │
│     cPanel → SSL/TLS → Certificate Status           │
│     Deve mostrar: ✅ Active                          │
│                                                      │
│ [ ] mod_rewrite ativo?                              │
│     cPanel → Apache Modules → search "rewrite"      │
│     Status: must be Green (ativo)                    │
│                                                      │
│ [ ] mod_deflate ativo?                              │
│     cPanel → Apache Modules → search "deflate"      │
│     Status: must be Green (ativo)                    │
│                                                      │
│ [ ] mod_expires ativo?                              │
│     cPanel → Apache Modules → search "expires"      │
│     Status: must be Green (ativo)                    │
│                                                      │
│ [ ] mod_headers ativo?                              │
│     cPanel → Apache Modules → search "headers"      │
│     Status: must be Green (ativo)                    │
│                                                      │
│ [ ] .htaccess em /public_html/?                     │
│     Arquivo: /home/usuario/public_html/.htaccess    │
│     Permissão: 644 (via cPanel File Manager)        │
│                                                      │
│ [ ] Backup do .htaccess original?                   │
│     cp .htaccess .htaccess.backup                   │
│     Essencial antes de mudanças!                    │
└─────────────────────────────────────────────────────┘

TESTE FUNCIONAL:
┌─────────────────────────────────────────────────────┐
│ 1. HTTP → HTTPS:                                    │
│    curl -I http://seu-dominio.com.br                │
│    Esperado: HTTP/1.1 301 Moved Permanently         │
│              Location: https://seu-dominio.com.br   │
│                                                      │
│ 2. WWW → SEM WWW:                                   │
│    curl -I https://www.seu-dominio.com.br           │
│    Esperado: HTTP/1.1 301 Moved Permanently         │
│              Location: https://seu-dominio.com.br   │
│                                                      │
│ 3. Cache headers:                                   │
│    curl -I https://seu-dominio.com.br/logo.png      │
│    Esperado: Cache-Control: public, max-age=31536000│
│                                                      │
│ 4. Compressão gzip:                                 │
│    curl -I https://seu-dominio.com.br/style.css     │
│    Esperado: Content-Encoding: gzip                 │
│                                                      │
│ 5. Headers de segurança:                            │
│    curl -I https://seu-dominio.com.br/              │
│    Esperado:                                        │
│    X-Frame-Options: SAMEORIGIN                      │
│    X-Content-Type-Options: nosniff                  │
│    X-XSS-Protection: 1; mode=block                  │
│                                                      │
│ 6. Certificado válido:                              │
│    Abra no navegador: https://seu-dominio.com.br    │
│    Procure: 🔒 (cadeado verde)                      │
│    Clique: Informações sobre conexão segura ✅      │
└─────────────────────────────────────────────────────┘

MONITORAMENTO PÓS-IMPLEMENTAÇÃO:
┌─────────────────────────────────────────────────────┐
│ [ ] Erros em cPanel → Error Log?                    │
│     Se SIM: Procure por "htaccess" ou "rewrite"     │
│                                                      │
│ [ ] Site funcionando normalmente?                   │
│     Login, formulários, APIs - tudo OK?             │
│                                                      │
│ [ ] Redirecionamentos funcionando?                  │
│     Teste: http://site.com → https://site.com      │
│                                                      │
│ [ ] Performance melhorou?                           │
│     Ferramentas: PageSpeed, GTmetrix                │
│     Procure: Tamanho menor (compressão ativa)       │
│              Cache aproveitado                      │
│                                                      │
│ [ ] SEO não afetado?                                │
│     Status codes 301 corretos = bom para SEO        │
│     Google entende migração                         │
└─────────────────────────────────────────────────────┘
```

---

## RESUMO VISUAL - MÓDULOS E FLUXO

```
REQUISIÇÃO TÍPICA COMPLETA:

HTTP:// (porta 80)
   ↓
[.htaccess lido]
   ├─ RewriteEngine On
   ├─ RewriteCond %{HTTPS} off    ← Verifica HTTPS
   ├─ RewriteRule ...             ← Redireciona 301
   └─ [L] para aqui

Redireciona para HTTPS (301)
   ↓
HTTPS:// (porta 443, TLS/SSL)
   ↓
[.htaccess lido NOVAMENTE]
   ├─ RewriteCond %{HTTPS} off    ← FALSO (é HTTPS!)
   ├─ Próximas regras...
   └─ WWW check, etc.

Processa requisição
   ├─ mod_expires: envia cache
   ├─ mod_deflate: comprime gzip
   ├─ mod_headers: envia headers segurança
   └─ PHP/Framework: processa lógica

Resposta completa
   ├─ HTTP/1.1 200 OK
   ├─ Content-Encoding: gzip ✅
   ├─ Cache-Control: max-age=... ✅
   ├─ X-Frame-Options: SAMEORIGIN ✅
   └─ [Conteúdo comprimido, criptografado]

Browser recebe
   ├─ Descriptografa (TLS)
   ├─ Descompacta (gzip)
   ├─ Guarda em cache local
   └─ Renderiza página ✅
```

---

**Fim do documento de fluxo visual**

Use este documento como referência visual para entender cada parte do .htaccess em ação! 🎯

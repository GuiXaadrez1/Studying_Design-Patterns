# .htaccess — Fundamentos, Implementação e Otimização
## Um Estudo Científico para Desenvolvimento Web Profissional

**Nível Intermediário (Level 2)**

**Autor:** Estruturado conforme metodologia pedagógica de Pierluigi Piazzi (Ensino Ativo Individual)

**Versão:** 1.0  
**Data:** Agosto 2026  
**Público-alvo:** Desenvolvedores PHP com experiência em servidor Apache, cPanel/WHM

---

# PREFÁCIO

## Por que este livro existe

O arquivo `.htaccess` é frequentemente tratado como "coisas mágicas que você copia da internet". Profissionais copiam regras, aplicam, e se funcionar, seguem adiante. Se quebrar, deletam tudo.

Este não é um livro sobre "receitas de .htaccess". É um livro sobre **por que** cada linha funciona, **como** o Apache a interpreta, e **quando** ela pode quebrar seu sistema.

## Estrutura pedagógica

Este livro segue a metodologia de **ensino ativo individual** de Pierluigi Piazzi:

1. **Teoria**: Você compreende os fundamentos
2. **Anatomia**: Você disseca cada comando
3. **Contexto Real**: Você aplica em site PHP, API REST e frameworks simultaneamente
4. **Armadilhas**: Você aprende o que quebra
5. **Prática**: Você resolve problemas reais

Não há "cópias e colas". Há **compreensão científica**.

## O que você saberá após este livro

- ✅ Como Apache lê e interpreta `.htaccess`
- ✅ Implementar segurança profissional em produção
- ✅ Reescrever URLs para site, API REST e frameworks
- ✅ Otimizar cache e compressão com base em teoria HTTP
- ✅ Diagnosticar problemas usando metodologia científica
- ✅ Evitar armadilhas que quebram aplicações

---

# CAPÍTULO 1: FUNDAÇÕES TEÓRICAS

## 1.1 - O Modelo Cliente-Servidor HTTP

Toda requisição web segue este fluxo:

```
[Navegador/Cliente] 
        ↓ (HTTP Request)
[Apache no Servidor]
        ↓ (Processa)
[Aplicação PHP/Script]
        ↓ (Gera resposta)
[Apache no Servidor]
        ↓ (HTTP Response)
[Navegador/Cliente]
```

### Camadas de Decisão

O Apache toma decisões em **3 pontos críticos**:

1. **Antes de processar** (mod_rewrite, mod_security)
2. **Durante o processamento** (permissões, autenticação)
3. **Depois de processar** (headers, cache, compressão)

`.htaccess` atua principalmente na **camada 1 (antes)**.

### Hierarquia de Configuração

```
┌─────────────────────────────────────┐
│   httpd.conf (global - crítico)     │
│   Não mexa sem saber                │
└────────────┬────────────────────────┘
             │ (Apache lê durante boot)
┌────────────▼────────────────────────┐
│   .htaccess (por pasta - seguro)    │
│   Lido a CADA requisição            │
└─────────────────────────────────────┘
```

**Consequência:** Se `httpd.conf` proíbe algo, `.htaccess` não consegue permitir.

---

## 1.2 - Arquitetura de Requisição HTTP

Quando você digita `https://ipaccontabilidade.com.br/usuarios/123`, o Apache recebe:

```http
GET /usuarios/123 HTTP/1.1
Host: ipaccontabilidade.com.br
User-Agent: Mozilla/5.0
Accept: text/html
Accept-Encoding: gzip, deflate
Connection: keep-alive
```

Apache então verifica:

1. Existe o arquivo `/usuarios/123`? (arquivo real)
2. Existe o diretório `/usuarios`? (diretório real)
3. Se não, qual regra de `.htaccess` se aplica?
4. Deve reescrever para outro arquivo?
5. Deve bloquear o acesso?
6. Deve redirecionar para outra URL?

Essa sequência é **determinística** (sempre igual).

---

## 1.3 - Estados HTTP e Significado

Seu `.htaccess` pode retornar diferentes **status HTTP**:

| Código | Significado | Causa Comum |
|--------|------------|-------------|
| **200** | OK | Requisição bem-sucedida |
| **301** | Moved Permanently | Redirecionamento permanente |
| **302** | Found | Redirecionamento temporário |
| **403** | Forbidden | Acesso negado (bloqueado por `.htaccess`) |
| **404** | Not Found | Arquivo não existe |
| **500** | Internal Server Error | Erro em `.htaccess` (sintaxe errada) |

**Importante:** Você **deve usar 301** para redirecionamentos permanentes (afeta SEO).

---

## 1.4 - Módulos Apache Necessários

`.htaccess` funciona porque Apache tem **módulos** habilitados:

| Módulo | Função | Verificação |
|--------|--------|-------------|
| **mod_rewrite** | Reescreve URLs | `a2enmod rewrite` (Linux) ou cPanel |
| **mod_deflate** | Comprime (gzip) | `a2enmod deflate` |
| **mod_expires** | Cache HTTP | `a2enmod expires` |
| **mod_auth** | Autenticação básica | Geralmente padrão |

**No seu cPanel/WHM:**
```
Home → Apache Modules → Verificar status
```

Se algum estiver vermelho 🔴, abra ticket com suporte.

---

# CAPÍTULO 2: ANATOMIA DO .htaccess

## 2.1 - Estrutura e Sintaxe Formal

Um `.htaccess` é um **arquivo de texto puro** (sem extensão) com diretivas Apache.

```apache
# Comentário (linha ignorada)
Diretiva Argumento1 Argumento2 ... ArgumentoN
```

### Tipos de Diretivas

**1. Simples** (um só argumento)
```apache
RewriteEngine On
# Ativa o módulo de reescrita
```

**2. Com argumentos** (múltiplos parâmetros)
```apache
RewriteRule ^usuarios/(.*)$ usuarios.php?id=$1 [QSA,L]
#           ↑ padrão         ↑ substituição   ↑ flags
```

**3. Com condições** (cláusulas IF)
```apache
RewriteCond %{REQUEST_FILENAME} !-f
# Se: REQUEST_FILENAME NÃO é arquivo
```

**4. Blocos** (seções aninhadas)
```apache
<Files "config.php">
    Deny from all
</Files>
```

---

## 2.2 - Variáveis do Apache (Servidor)

Apache fornece **variáveis especiais** que você pode usar:

```apache
%{HTTP_HOST}          # Nome do host (ex: ipaccontabilidade.com.br)
%{HTTPS}              # "on" se HTTPS, "off" se HTTP
%{REQUEST_FILENAME}   # Caminho completo do arquivo solicitado
%{REQUEST_METHOD}     # GET, POST, PUT, DELETE, etc
%{REQUEST_URI}        # URI completa (ex: /usuarios/123?id=5)
%{QUERY_STRING}       # Tudo após ? (ex: id=5&name=jose)
%{REMOTE_ADDR}        # IP do cliente
%{HTTP_USER_AGENT}    # Browser/aplicação do cliente
%{SERVER_PORT}        # Porta (80=HTTP, 443=HTTPS)
%{TIME_YEAR}          # Ano (2026)
```

### Exemplo Prático

```apache
# Bloquear tráfego de um agente específico
RewriteCond %{HTTP_USER_AGENT} ^Googlebot
RewriteRule ^.*$ - [F]
# Se user-agent for Googlebot, nega com [F] (forbidden)
```

---

## 2.3 - Operadores de Comparação

```apache
=         # Igualdade
!=        # Desigualdade
-f        # É um arquivo real?
-d        # É um diretório real?
-l        # É um link simbólico?
-s        # É arquivo com conteúdo? (>0 bytes)
!-f       # NÃO é arquivo real
!-d       # NÃO é diretório real
```

### Exemplo Contexto

```apache
# Se arquivo NÃO existe E não é diretório
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
```

**Tradução científica:**
- Condição 1: `REQUEST_FILENAME` (!-f) = Não é um arquivo físico
- Condição 2: `REQUEST_FILENAME` (!-d) = Não é um diretório físico
- Se ambas verdadeiras: Reescreva para `index.php`

---

## 2.4 - Flags de RewriteRule

```apache
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L,R=301]
#                                    ↑
#                                  Flags
```

| Flag | Nome | Efeito |
|------|------|--------|
| **L** | Last | "Última regra" - para de processar se match |
| **R=301** | Redirect | Redireciona (302 é padrão, 301=permanente) |
| **QSA** | QueryStringAppend | Mantém query string original |
| **NE** | NoEscape | Não escapa caracteres especiais |
| **NC** | NoCase | Case-insensitive (ignora maiúscula/minúscula) |
| **F** | Forbidden | Retorna 403 (acesso negado) |
| **P** | Proxy | Processa como proxy (cuidado!) |

### Combinação de Flags

```apache
RewriteRule ^old\.html$ new.html [L,R=301,NC]
# Combina: Last, Redirect 301, Case-insensitive
```

---

## 2.5 - Ordem de Processamento

Apache processa `.htaccess` **sequencialmente**:

```apache
RewriteEngine On          # 1º: Ativa mod_rewrite

RewriteCond %{HTTPS} off  # 2º: Verifica condição
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# 3º: Se condição verdadeira, executa rule

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
# 4º: Próxima regra (se anterior tiver [L], pula)
```

**Crítico:** Uma regra pode **reescrever a saída de outra**. Ordem importa!

---

# CAPÍTULO 3: REGEX — FUNDAMENTOS PARA .htaccess

## 3.1 - Por que Regex?

RewriteRule usa **Regular Expressions (Regex)** para pattern matching:

```apache
RewriteRule ^usuarios/([0-9]+)/?$ usuarios.php?id=$1 [L]
#           ↑ Regex pattern
```

Sem entender Regex, você copia regras sem saber o que faz.

---

## 3.2 - Sintaxe Básica

### Âncoras

```regex
^           # Início da string
$           # Fim da string
^usuarios$  # Exatamente "usuarios" (nada antes, nada depois)
```

### Quantificadores

```regex
*          # 0 ou mais ocorrências
+          # 1 ou mais ocorrências
?          # 0 ou 1 ocorrência
{n}        # Exatamente n ocorrências
{n,}       # n ou mais ocorrências
{n,m}      # Entre n e m ocorrências
```

### Classes de Caracteres

```regex
[0-9]      # Qualquer dígito (0 até 9)
[a-z]      # Qualquer letra minúscula
[A-Z]      # Qualquer letra maiúscula
[a-zA-Z]   # Qualquer letra
\w         # Qualquer palavra (\w = [a-zA-Z0-9_])
\d         # Qualquer dígito (\d = [0-9])
.          # Qualquer caractere (exceto quebra de linha)
```

### Grupos de Captura

```regex
(.*)       # Captura tudo ( ) = grupo, . = qualquer, * = 0 ou mais
([0-9]+)   # Captura 1+ dígitos
([a-z]+)   # Captura 1+ letras minúsculas
```

---

## 3.3 - Exemplos Práticos de Regex no .htaccess

### Exemplo 1: ID Numérico

```apache
RewriteRule ^usuarios/([0-9]+)/?$ usuarios.php?id=$1 [L]
```

**Regex breakdown:**
- `^usuarios/` = Começa com "usuarios/"
- `([0-9]+)` = Captura 1+ dígitos → salvo em `$1`
- `/?` = Barra final opcional
- `$` = Fim da string

**Matches:**
- ✅ `usuarios/123`
- ✅ `usuarios/123/`
- ❌ `usuarios/abc` (não é dígito)
- ❌ `usuarios/123/456` (tem barra extra)

### Exemplo 2: Slug (Texto-Amigável)

```apache
RewriteRule ^blog/([a-z0-9-]+)/?$ blog.php?slug=$1 [L]
```

**Regex breakdown:**
- `([a-z0-9-]+)` = Captura letras minúsculas, dígitos ou hífens

**Matches:**
- ✅ `blog/meu-artigo`
- ✅ `blog/artigo-123`
- ❌ `blog/Artigo` (maiúscula não permitida)

### Exemplo 3: Múltiplos Grupos

```apache
RewriteRule ^api/v([0-9]+)/([a-z]+)/([0-9]+)$ api.php?version=$1&resource=$2&id=$3 [L]
```

**Captura:**
- `$1` = versão (ex: 2)
- `$2` = recurso (ex: usuarios)
- `$3` = id (ex: 123)

**Match:**
- `api/v2/usuarios/123` → `api.php?version=2&resource=usuarios&id=123`

---

## 3.4 - Armadilha: Regex Ganancioso vs Não-Ganancioso

```apache
# GANANCIOSO (pega o máximo)
RewriteRule ^([a-z]+)(.*)$ script.php?base=$1&resto=$2 [L]

# URL: /abcdef123
# $1 = abcdef (pega tudo que é letra)
# $2 = 123 (o resto)
```

```apache
# NÃO-GANANCIOSO (pega o mínimo)
RewriteRule ^([a-z]+?)(.*)$ script.php?base=$1&resto=$2 [L]

# URL: /abcdef123
# $1 = a (pega APENAS 1 letra)
# $2 = bcdef123 (o resto)
```

**Em .htaccess:** Apache usa Perl Regex (ganancioso por padrão).

---

# CAPÍTULO 4: SEGURANÇA PROFISSIONAL

## 4.1 - Princípio Zero-Trust

Segurança começa com **negar tudo, depois permitir o necessário**:

```apache
# Negar tudo por padrão
<Directory /var/www/html>
    Order Deny,Allow
    Deny from all
</Directory>

# Depois permitir específico
Allow from 192.168.1.100
```

---

## 4.2 - Proteção de Arquivos Sensíveis

### 4.2.1 - Bloquear Diretamente

```apache
# ============================================
# BLOQUEAR ARQUIVOS DE CONFIGURAÇÃO
# ============================================

# Bloquear config.php
<Files "config.php">
    # Tipo de autenticação (aqui: nenhuma)
    Deny from all
</Files>

# Bloquear .env (variáveis de ambiente)
<FilesMatch "\.env">
    Deny from all
</FilesMatch>

# Bloquear backup e arquivos temporários
<FilesMatch "\.(bak|tmp|log)$">
    Deny from all
</FilesMatch>
```

**Explicação linha a linha:**
- `<Files "config.php">` = Aplica regra ao arquivo config.php
- `Deny from all` = Nega acesso de qualquer IP
- Resultado: HTTP 403 ao acessar config.php

### 4.2.2 - Bloquear Diretórios Inteiros

```apache
# ============================================
# BLOQUEAR DIRETÓRIOS ADMINISTRATIVOS
# ============================================

<Directory "/home/seu-usuario/public_html/admin">
    # Type: Ordem de processamento
    Order Allow,Deny
    
    # Nega tudo por padrão
    Deny from all
    
    # Depois permite IP específico (matriz)
    Allow from 192.168.1.100
    Allow from 203.0.113.45
</Directory>
```

**Explicação:**
- `Order Allow,Deny` = Processa Allow rules primeiro
- Qualquer IP não listado = Acesso negado
- Uso: Proteger `/admin` de acesso externo

---

## 4.3 - Proteção contra Acesso Indevido via URL

### 4.3.1 - Bloquear Acesso Direto a Includes

Em PHP, você pode ter:

```php
// /includes/database.php
// Este arquivo SÓ deve ser incluído, nunca acessado diretamente
```

Problema: Alguém acessa `ipac.com.br/includes/database.php`

Solução:

```apache
# ============================================
# PROTEGER PASTA /includes
# ============================================

<Directory "/home/seu-usuario/public_html/includes">
    # Ordem de avaliação
    Order Deny,Allow
    
    # Padrão: nega tudo
    Deny from all
</Directory>
```

**Efeito:** Qualquer requisição direta a `/includes/*` retorna 403.

### 4.3.2 - Bloquear Extensões Perigosas

```apache
# ============================================
# BLOQUEAR UPLOAD DE EXECUTÁVEIS
# ============================================

<Directory "/home/seu-usuario/public_html/uploads">
    # Nega execução de scripts
    php_flag engine off
    
    # Ou mais restritivo: bloqueia estas extensões
    <FilesMatch "\.(php|php3|php4|php5|phtml|exe|com|bat|cmd)$">
        Deny from all
    </FilesMatch>
</Directory>
```

**Por quê:** Usuário malicioso faz upload de `shell.php` → sem esta regra, pode executar.

---

## 4.4 - Proteção contra Força Bruta e Bot Malicioso

### 4.4.1 - Bloquear IPs Específicos

```apache
# ============================================
# BLOQUEAR IP ATACANTE
# ============================================

SetEnvIF REMOTE_ADDR "203.0.113.10" BlockBot
SetEnvIF REMOTE_ADDR "198.51.100.20" BlockBot
SetEnvIF REMOTE_ADDR "192.0.2.5" BlockBot

<Directory "/home/seu-usuario/public_html">
    Order Allow,Deny
    Allow from all
    Deny from env=BlockBot
</Directory>
```

**Explicação:**
- `SetEnvIF` = Define variável de ambiente se condição verdadeira
- `REMOTE_ADDR` = IP do cliente
- Qualquer IP em BlockBot = Negado

### 4.4.2 - Bloquear User-Agents Suspeitos

```apache
# ============================================
# BLOQUEAR BOTS E SCRAPERS
# ============================================

# Define ambiente se user-agent malicioso
SetEnvIF Request_URI ".*" Deny_Bot
SetEnvIF HTTP_USER_AGENT "^Googlebot" Deny_Bot=0
SetEnvIF HTTP_USER_AGENT "^Bingbot" Deny_Bot=0
SetEnvIF HTTP_USER_AGENT "curl" Deny_Bot
SetEnvIF HTTP_USER_AGENT "wget" Deny_Bot

<Directory "/home/seu-usuario/public_html">
    Order Allow,Deny
    Allow from all
    Deny from env=Deny_Bot
</Directory>
```

**Explicação:**
- Denies curl/wget por padrão
- Permite Googlebot e Bingbot especificamente
- Resultado: Scrapers bloqueados, indexadores permitidos

---

## 4.5 - Forçar HTTPS (SSL/TLS Obrigatório)

```apache
# ============================================
# FORÇAR HTTPS GLOBALMENTE
# ============================================

# Verifica se HTTPS está DESATIVADO
RewriteCond %{HTTPS} off

# Se verdadeiro, reescreve para HTTPS
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Quebra anatômica:**
- `%{HTTPS}` = Variável (on/off)
- `off` = HTTP (não seguro)
- `^(.*)$` = Pega TODA requisição
- `https://%{HTTP_HOST}%{REQUEST_URI}` = Reescreve para HTTPS
- `[L,R=301]` = Último, redireção permanente

**Resultado:** 
- `http://ipac.com.br/usuarios` → `https://ipac.com.br/usuarios` (301)

### Variação: Apenas Áreas Sensíveis em HTTPS

```apache
# ============================================
# FORÇAR HTTPS APENAS EM /admin
# ============================================

RewriteCond %{HTTPS} off
RewriteCond %{REQUEST_URI} ^/admin
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 4.6 - Desabilitar Listagem de Diretórios

**Problema:** Sem isso, `ipac.com.br/uploads/` mostra lista de arquivos.

**Solução:**

```apache
# ============================================
# DESABILITAR DIRECTORY LISTING
# ============================================

# Opção global
Options -Indexes

# Ou específica por diretório
<Directory "/home/seu-usuario/public_html/uploads">
    Options -Indexes
</Directory>
```

**Explicação:**
- `Options` = Diretiva de opções Apache
- `-Indexes` = Remove permissão de listagem
- Resultado: Acesso direto ao diretório retorna 403

---

## 4.7 - Proteção contra SQL Injection via URL

```apache
# ============================================
# BLOQUEAR PADRÕES SUSPEITOS EM QUERY STRING
# ============================================

# Se query string contém SQL suspeito
RewriteCond %{QUERY_STRING} union.*select|select.*from|insert.*into|delete.*from [NC]

# Nega a requisição
RewriteRule ^.*$ - [F]
```

**Explicação:**
- `%{QUERY_STRING}` = Parte após ? (ex: id=5&name=jose)
- `union.*select|select.*from` = Padrões SQL maliciosos
- `[NC]` = Case-insensitive (Union ou union)
- `[F]` = Forbidden (403)

**Exemplo:**
- ❌ `ipac.com.br/usuarios?id=1 UNION SELECT * FROM users`
- ❌ Bloqueado com 403

---

# CAPÍTULO 5: REDIRECIONAMENTO PROFISSIONAL

## 5.1 - Teoria de Redirecionamento

Redirecionamentos ocorrem em **3 níveis**:

| Nível | Ferramenta | Exemplo |
|-------|-----------|---------|
| **HTTP** | `.htaccess` | 301/302 redirect |
| **PHP** | `header('Location:')` | Lógica condicional |
| **HTML** | `<meta refresh>` | Última opção (SEO ruim) |

`.htaccess` é **mais eficiente** que PHP (executa antes do PHP).

---

## 5.2 - Diretiva Redirect vs Rewrite

### Redirect (Simples)

```apache
Redirect 301 /old-page.html /new-page.html
```

**Anatomia:**
- `Redirect` = Tipo de operação
- `301` = Status HTTP (permanente)
- `/old-page.html` = Origem
- `/new-page.html` = Destino

**Efeito:** Cliente vê mudança de URL.

### Rewrite (Invisível)

```apache
RewriteRule ^old-page\.html$ new-page.html [L]
```

**Efeito:** Cliente NÃO vê mudança (URL invisível).

**Quando usar cada:**
- **Redirect 301**: Página deletada, mudança permanente de domínio
- **Redirect 302**: Manutenção temporária
- **Rewrite**: URL bonita (usuário vê `/usuarios/123`, mas roda `/usuarios.php?id=123`)

---

## 5.3 - Redirecionamento HTTP → HTTPS

**Requisito:** Certificado SSL instalado (deve ter no cPanel).

```apache
# ============================================
# REDIRECIONAR HTTP PARA HTTPS
# ============================================

# Condição: Se protocolo é HTTP
RewriteCond %{HTTPS} off

# Reescreve para HTTPS
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Anatomia completa:**
- `RewriteCond %{HTTPS} off` = Se HTTPS está desativado
- `%{HTTP_HOST}` = Nome do host (ex: ipac.com.br)
- `%{REQUEST_URI}` = Caminho completo (ex: /usuarios/123)
- `[L,R=301]` = Último comando, redir. permanente

**Teste:**
```bash
# Deve redirecionar com 301
curl -I http://ipaccontabilidade.com.br/usuarios
# Será redirecionado para HTTPS
```

---

## 5.4 - Redirecionamento de Domínio Antigo para Novo

**Cenário:** `velhositio.com.br` → `novositio.com.br`

```apache
# ============================================
# REDIRECIONAR DOMÍNIO INTEIRO
# ============================================

# Condição: Se host é velhositio.com.br
RewriteCond %{HTTP_HOST} ^velhositio\.com\.br$ [NC]

# Captura tudo e redireciona
RewriteRule ^(.*)$ https://novositio.com.br/$1 [L,R=301]
```

**Quebra anatômica:**
- `%{HTTP_HOST}` = Domínio solicitado
- `^velhositio\.com\.br$` = Domínio exato (nota: escapa ponto com `\.`)
- `[NC]` = Case-insensitive
- `$1` = Captura tudo da URL original
- Resultado: `velhositio.com.br/usuarios/123` → `novositio.com.br/usuarios/123`

---

## 5.5 - Redirecionar WWW e Sem WWW

### Forçar SEM WWW (canonical)

```apache
# ============================================
# REMOVER WWW
# ============================================

# Se host contém www
RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]

# Remove www
RewriteRule ^(.*)$ https://%1/$1 [L,R=301]
```

**Nota:** `%1` = Primeira captura da RewriteCond (sem www).

### Forçar COM WWW

```apache
# Se host NÃO contém www
RewriteCond %{HTTP_HOST} !^www\. [NC]

# Adiciona www
RewriteRule ^(.*)$ https://www.%{HTTP_HOST}/$1 [L,R=301]
```

---

## 5.6 - Redirecionar com Preservação de Query String

**Problema:** Query string pode ser perdida.

```apache
# ============================================
# REDIRECIONAR PRESERVANDO PARÂMETROS
# ============================================

# Captura query string (tudo após ?)
RewriteCond %{QUERY_STRING} ^(.*)$

# Redireciona preservando com [QSA]
RewriteRule ^usuarios\.html$ usuarios.php [L,R=301,QSA]
```

**Resultado:**
- `usuarios.html?id=5&name=jose` → `usuarios.php?id=5&name=jose` ✅
- Query string preservada!

---

# CAPÍTULO 6: REESCRITA DE URLS (MOD_REWRITE PROFISSIONAL)

## 6.1 - Conceito de URL Rewriting

**Antes (feia):**
```
ipac.com.br/usuarios.php?id=123&action=view
```

**Depois (bonita):**
```
ipac.com.br/usuarios/123/view
```

**Apache faz:**
1. Cliente vê URL bonita
2. Apache reescreve internamente para `.php?id=123`
3. PHP processa normalmente
4. Cliente nunca descobre a verdade

**Vantagens:**
- SEO (URLs estruturadas)
- Segurança (esconde tecnologia)
- Legibilidade

---

## 6.2 - Rewrite para Site PHP Simples

### Cenário: Blog com URLs bonitas

**Estrutura esperada:**
```
/blog/meu-artigo-123
/blog/outro-artigo-456
```

**Implementação:**

```apache
# ============================================
# REWRITE PARA BLOG (SITE SIMPLES)
# ============================================

# Ativa mod_rewrite
RewriteEngine On

# Pré-requisito: BASE URLs
RewriteBase /

# Condição 1: Se não é arquivo real
RewriteCond %{REQUEST_FILENAME} !-f

# Condição 2: Se não é diretório real
RewriteCond %{REQUEST_FILENAME} !-d

# Se ambas: Reescreve /blog/slug para blog.php?slug=
RewriteRule ^blog/([a-z0-9-]+)/?$ blog.php?slug=$1 [QSA,L]
```

**Anatomia linha por linha:**
```apache
RewriteEngine On
# Ativa o módulo mod_rewrite

RewriteBase /
# Define base da reescrita (se em subpasta, usar /subpasta/)

RewriteCond %{REQUEST_FILENAME} !-f
# CONDIÇÃO: REQUEST_FILENAME (arquivo solicitado)
# !-f = negação (-f = é arquivo)
# Resultado: Se arquivo NÃO existe, condição = verdadeira

RewriteCond %{REQUEST_FILENAME} !-d
# CONDIÇÃO: Se diretório NÃO existe

RewriteRule ^blog/([a-z0-9-]+)/?$ blog.php?slug=$1 [QSA,L]
# REGRA: Se ambas condições verdadeiras
# ^blog/ = começa com blog/
# ([a-z0-9-]+) = captura 1+ caracteres (letras, números, hífen) = $1
# /?$ = barra final opcional, fim
# blog.php?slug=$1 = reescreve para blog.php com slug como parâmetro
# [QSA,L] = QueryStringAppend (mantém params extras), Last (para)
```

**Teste real:**
```bash
# Cliente solicita
/blog/meu-artigo-123

# Apache reescreve internamente para
blog.php?slug=meu-artigo-123

# PHP recebe em $_GET['slug']
echo $_GET['slug']; // "meu-artigo-123"
```

---

## 6.3 - Rewrite para API REST

### Cenário: API com estrutura RESTful

**Estrutura desejada:**
```
GET /api/v1/usuarios         → lista usuarios
GET /api/v1/usuarios/123     → usuario 123
POST /api/v1/usuarios        → cria usuario
PUT /api/v1/usuarios/123     → atualiza usuario 123
DELETE /api/v1/usuarios/123  → deleta usuario 123
```

**Implementação:**

```apache
# ============================================
# REWRITE PARA API REST
# ============================================

RewriteEngine On
RewriteBase /

# Pré-condições
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Rotas API: /api/v{versao}/{recurso} ou /api/v{versao}/{recurso}/{id}
RewriteRule ^api/v([0-9]+)/([a-z]+)(?:/([0-9]+))?/?$ api.php?version=$1&resource=$2&id=$3 [QSA,L]
```

**Anatomia do Regex:**
```regex
^api/v([0-9]+)/([a-z]+)(?:/([0-9]+))?/?$

^api/v              # Começa com api/v
([0-9]+)            # Captura versão (1+dígitos) → $1
/([a-z]+)           # Captura recurso (1+letras) → $2
(?:/([0-9]+))?      # Opcionalmente: /ID → $3 (? = opcional)
/?$                 # Barra final opcional, fim
```

**Teste real:**
```bash
# Requisição
GET /api/v1/usuarios

# Reescrita
api.php?version=1&resource=usuarios&id=

# PHP recebe
$_GET['version'] = 1
$_GET['resource'] = 'usuarios'
$_GET['id'] = '' (vazio)

# Seu código detecta:
if (empty($_GET['id'])) {
    // Listar todos
} else {
    // Buscar por ID
}
```

```bash
# Requisição
GET /api/v2/usuarios/123

# Reescrita
api.php?version=2&resource=usuarios&id=123

# PHP recebe
$_GET['version'] = 2
$_GET['resource'] = 'usuarios'
$_GET['id'] = '123'
```

---

## 6.4 - Rewrite para Frameworks (Laravel, Slim, Symfony)

### Cenário: Framework que rota tudo via index.php

**Padrão universal de frameworks:**

```apache
# ============================================
# REWRITE PARA FRAMEWORK (Laravel, Slim, etc)
# ============================================

RewriteEngine On
RewriteBase /

# Se arquivo não existe
RewriteCond %{REQUEST_FILENAME} !-f

# Se diretório não existe
RewriteCond %{REQUEST_FILENAME} !-d

# Reescreve TUDO para index.php
RewriteRule ^(.*)$ index.php?request=$1 [QSA,L]
```

**Por que funciona:**
- Qualquer requisição desconhecida → `index.php`
- Framework recebe em `$_GET['request']`
- Framework usa biblioteca de router (ex: Slim router) para mapear
- Framework executa controller apropriado

**Exemplo Laravel:**

```php
// routes/api.php
Route::get('/usuarios/{id}', 'UsuarioController@show');

// Requisição
GET /usuarios/123

// .htaccess reescreve para
index.php?request=usuarios/123

// Laravel router intercepta
// Mapeia /usuarios/123 → UsuarioController::show(123)
```

---

## 6.5 - Rewrite Condicional (Diferentes Regras por Domínio)

```apache
# ============================================
# REWRITE DIFERENCIADO POR DOMÍNIO
# ============================================

# Se domínio é api.ipac.com.br
RewriteCond %{HTTP_HOST} ^api\.ipac\.com\.br$ [NC]
RewriteRule ^(.*)$ api.php?endpoint=$1 [QSA,L]

# Se domínio é www.ipac.com.br
RewriteCond %{HTTP_HOST} ^www\.ipac\.com\.br$ [NC]
RewriteRule ^(.*)$ site.php?page=$1 [QSA,L]
```

**Anatomia:**
- Cada `RewriteCond` + `RewriteRule` é uma "rota"
- Primeira que match é executada
- Ordem importa!

---

## 6.6 - Evitar Loops de Reescrita

**Armadilha:**

```apache
# ERRADO - Causa loop infinito!
RewriteRule ^(.*)$ index.php?rota=$1 [L]
```

**Por quê:** 
1. Requisição `/usuarios` → reescreve para `index.php?rota=usuarios`
2. Apache processa novamente (nova requisição)
3. `index.php` não match `/.*` (é arquivo real)... WAIT
4. Sem condição `!-f`, reescreve novamente
5. Loop infinito = erro 500

**Correto:**

```apache
# CERTO - Previne loop
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [L]
```

**Explicação:**
- Se `index.php` é arquivo real (existe)
- Primeira `RewriteCond` falha (`!-f` = NÃO é arquivo)
- Regra não executa
- Sem reescrita = sem loop ✅

---

# CAPÍTULO 7: CACHE E COMPRESSÃO (OTIMIZAÇÃO HTTP)

## 7.1 - Teoria de Cache HTTP

Cache reduz **tráfego de rede** e **tempo de carregamento**:

```
[Primeira visita]
Browser solicita: /logo.png
Servidor: "Aqui está" (envia 100KB)
Browser: Guarda localmente + lê header de cache

[Segunda visita (mesmo dia)]
Browser solicita: /logo.png
Browser: "Tenho em cache, não preciso do servidor"
Carregamento: INSTANTÂNEO ✅
```

### Header Cache-Control

```
Cache-Control: max-age=2592000
# "Guarde por 30 dias (2.592.000 segundos)"
```

### Diretiva Expires (Legado)

```
Expires: Mon, 24 Sep 2026 12:00:00 GMT
# "Válido até 24 de setembro de 2026"
```

**Moderna:** Use `Cache-Control` (mais flexível).

---

## 7.2 - Configurar Cache no .htaccess

**Requisito:** Módulo `mod_expires` ativo.

```apache
# ============================================
# CONFIGURAR CACHE DO NAVEGADOR
# ============================================

# Ativa sistema de expiração
<IfModule mod_expires.c>
    ExpiresActive On
    
    # Define cache por tipo de arquivo
    
    # Imagens (1 ano = mudam raramente)
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    
    # CSS (1 mês = podem atualizar)
    ExpiresByType text/css "access plus 1 month"
    
    # JavaScript (1 mês = podem atualizar)
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    
    # HTML (1 dia = muda frequente)
    ExpiresByType text/html "access plus 1 day"
    
    # Fontes (1 ano = mudam raramente)
    ExpiresByType font/truetype "access plus 1 year"
    ExpiresByType application/x-font-woff "access plus 1 year"
    
    # Padrão para tudo mais (8 horas)
    ExpiresDefault "access plus 8 hours"
</IfModule>
```

**Anatomia linha por linha:**
```apache
<IfModule mod_expires.c>
# Bloco condicional: se mod_expires existe

ExpiresActive On
# Ativa sistema de cache

ExpiresByType image/jpeg "access plus 1 year"
# Para tipo MIME image/jpeg
# Defina expiração: access (último acesso) + 1 ano

ExpiresDefault "access plus 8 hours"
# Qualquer tipo não especificado: 8 horas
```

**Efeito prático:**
1. Cliente baixa `logo.png`
2. Servidor envia header: `Cache-Control: max-age=31536000` (1 ano em segundos)
3. Browser: "Logo vai valer por 1 ano"
4. Próximas visitas: Usa cache local

---

## 7.3 - Compressão com Gzip

**Teoria:** Comprime arquivo antes de enviar (~70% menor).

```
Arquivo original:   100 KB
Após gzip:          30 KB (70% redução)
Navegador:          Descompacta automaticamente
```

### Configurar Gzip

```apache
# ============================================
# HABILITAR COMPRESSÃO GZIP
# ============================================

<IfModule mod_deflate.c>
    # Ativa compressão
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>
```

**Anatomia:**
```apache
AddOutputFilterByType DEFLATE text/css
# AddOutputFilterByType = Aplica filtro de saída
# DEFLATE = Algoritmo de compressão (gzip)
# text/css = Tipo MIME alvo
```

**Teste:**
```bash
# Baixar arquivo e verificar header
curl -I https://ipaccontabilidade.com.br/style.css

# Se comprimido, verá:
# Content-Encoding: gzip
```

### Excluir Tipos que já são Comprimidos

```apache
# Não recomprimir (já são comprimidos)
SetEnvIfNoCase Request_URI \.(?:gif|jpe?g|png|rar|zip|7z|exe|gz)$ no-gzip dont-vary
```

**Por quê:** JPEG, PNG já são comprimidos. Recomprimir é desperdício.

---

## 7.4 - Headers Personalizados para Performance

```apache
# ============================================
# HEADERS DE PERFORMANCE
# ============================================

<IfModule mod_headers.c>
    # Permite cache em navegadores
    Header set Cache-Control "public, max-age=31536000"
    
    # Add Vary header (para cache de proxy)
    Header append Vary Accept-Encoding
    
    # Segurança: Force MIME-type
    Header set X-Content-Type-Options "nosniff"
    
    # Segurança: Prevent click-jacking
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
```

---

# CAPÍTULO 8: AUTENTICAÇÃO PROFISSIONAL

## 8.1 - HTTP Basic Authentication

### 8.1.1 - Teoria

Quando você acessa `/admin`, aparece popup:

```
┌─────────────────────────────┐
│ Autenticação Necessária     │
│ Usuário: [____]             │
│ Senha:   [____]             │
│ [Cancel] [OK]               │
└─────────────────────────────┘
```

Credenciais são **codificadas em Base64** e enviadas a cada requisição:

```
Authorization: Basic dXN1YXJpbzpzZW5oYQ==
# Base64("usuario:senha")
```

### 8.1.2 - Criar Arquivo .htpasswd

**Via cPanel:**
1. Home → Password Protect Directories
2. Selecione pasta (`/admin`)
3. Define usuário/senha
4. cPanel cria `.htpasswd` automaticamente

**Via SSH:**
```bash
htpasswd -c /home/seu-usuario/public_html/.htpasswd usuario1
# Pede senha interativa
# -c = criar novo arquivo
```

**Arquivo .htpasswd:**
```
usuario1:$apr1$r31....0:veryencrypted
usuario2:$apr1$2e5....1:alsoencrypted
```

### 8.1.3 - Configurar .htaccess

```apache
# ============================================
# PROTEGER /admin COM AUTENTICAÇÃO
# ============================================

<Directory "/home/seu-usuario/public_html/admin">
    # Tipo de autenticação
    AuthType Basic
    
    # Texto do popup
    AuthName "Área Administrativa - IPAC Contabilidade"
    
    # Caminho do arquivo .htpasswd
    AuthUserFile /home/seu-usuario/public_html/.htpasswd
    
    # Exigir usuário válido
    Require valid-user
</Directory>
```

**Anatomia:**
```apache
AuthType Basic
# Tipo: Basic (username:password codificado em Base64)

AuthName "..."
# Texto exibido no popup

AuthUserFile /caminho/.htpasswd
# Caminho absoluto do arquivo de senhas

Require valid-user
# Qualquer usuário em .htpasswd é aceito
```

**Teste:**
```bash
# Sem credenciais = 401 Unauthorized
curl https://ipac.com.br/admin
# Retorna: 401

# Com credenciais
curl -u usuario1:senha https://ipac.com.br/admin
# Retorna: 200 OK
```

---

## 8.2 - Autenticação Seletiva (Apenas Arquivo Específico)

```apache
# ============================================
# PROTEGER ARQUIVO ESPECÍFICO
# ============================================

<Files "relatorio-financeiro.php">
    AuthType Basic
    AuthName "Relatório Financeiro - Restrito"
    AuthUserFile /home/seu-usuario/public_html/.htpasswd
    Require valid-user
</Files>
```

**Efeito:** Apenas `relatorio-financeiro.php` exige senha. Outros arquivos livres.

---

## 8.3 - Autenticação por IP (Sem Senha para Rede Interna)

```apache
# ============================================
# LIBERAR POR IP (bypass de senha)
# ============================================

<Directory "/home/seu-usuario/public_html/admin">
    # Ordem de processamento
    Order Deny,Allow
    
    # Permite acesso da rede interna sem senha
    Allow from 192.168.1.0/24
    
    # Para outros IPs: exigir autenticação
    <LimitExcept GET HEAD>
        AuthType Basic
        AuthName "Autenticação Necessária"
        AuthUserFile /home/seu-usuario/public_html/.htpasswd
        Require valid-user
    </LimitExcept>
</Directory>
```

**Resultado:**
- IP 192.168.1.x: Acesso direto ✅
- Outros IPs: Popup de login ✅

---

# CAPÍTULO 9: TROUBLESHOOTING CIENTÍFICO

## 9.1 - Metodologia de Diagnóstico

Quando algo quebra, siga **em ordem**:

### Passo 1: Verifique Sintaxe do .htaccess

```bash
# SSH (se tiver acesso)
apachectl configtest

# Ou via PHP
shell_exec('apachectl configtest');
```

**Resultado esperado:**
```
Syntax OK
```

Se erro, Apache mostra linha exata do problema.

### Passo 2: Verifique Logs

**Via cPanel:**
```
Home → Error Logs
```

**Via SSH:**
```bash
tail -50 /var/log/apache2/error_log
```

Procure por padrões:

```
[rewrite:error] [pid 1234:tid 4567] 
RewriteRule: No matching CondPattern
# Significa: Regex não fez match
```

### Passo 3: Teste Incrementalmente

Não coloque tudo de uma vez. Faça assim:

```apache
# TESTE 1: Apenas HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Teste:** Funciona? ✅ Continue.

```apache
# TESTE 2: Adicionar rewrite de URL
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^usuarios/([0-9]+)/?$ usuarios.php?id=$1 [QSA,L]
```

**Teste:** Funciona? ✅ Continue.

---

## 9.2 - Erros Comuns e Soluções

### ❌ Erro 500 Internal Server Error

**Causa:** Sintaxe inválida em `.htaccess`.

**Diagnóstico:**
```bash
# Verifique logs
tail /var/log/apache2/error_log | grep htaccess

# Procure por "syntax error", "invalid regex"
```

**Solução:** Revise a regra com problema.

### ❌ Erro 404 Após Adicionar Rewrite

**Causa:** Regex não faz match ou `RewriteEngine` não ativado.

**Diagnóstico:**
1. Verifique `RewriteEngine On` está presente
2. Teste o regex com `regex101.com`
3. Verifique condições `RewriteCond`

**Exemplo:**

```apache
# ERRADO
RewriteRule ^usuarios/(.*)$ usuarios.php?id=$1 [L]

# Falta condição - causará loop se usuários.php existir
```

**Correto:**
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^usuarios/(.*)$ usuarios.php?id=$1 [L]
```

### ❌ Loop Infinito (Apache fica lento)

**Causa:** Regra reescreve infinitamente.

**Diagnóstico:**
```bash
# Verifique se requisição reescrita múltiplas vezes
curl -v http://localhost/usuarios

# Logs mostram múltiplas reescrita do mesmo arquivo
```

**Solução:** Adicione condições `!-f` e `!-d`.

### ❌ Query String Perdida

**Causa:** Falta `[QSA]` flag.

```apache
# ERRADO - perde query string
RewriteRule ^usuarios\.html$ usuarios.php [L]

# usuarios.html?id=5 → usuarios.php (id perdido)
```

**Correto:**
```apache
# COM [QSA]
RewriteRule ^usuarios\.html$ usuarios.php [L,QSA]

# usuarios.html?id=5 → usuarios.php?id=5 (preservado)
```

---

## 9.3 - Ativar Modo Debug

**Via .htaccess:**

```apache
# ============================================
# ATIVAR LOGS DE REWRITE (DEBUG)
# ============================================

# Define nível de verbosidade
RewriteLogLevel 9

# Define arquivo de log
RewriteLog /tmp/rewrite.log
```

**Resultado:** Apache escreve **cada reescrita** no log.

```bash
# Leia o log em tempo real
tail -f /tmp/rewrite.log

# Você vê:
# [perdir/unknown] ... RewriteRule at /home/.../public_html/.htaccess:5 [uri /usuarios/123]
```

**Desativa depois de resolver:**
```apache
RewriteLogLevel 0
# RewriteLog /tmp/rewrite.log
```

---

# EXERCÍCIOS RESOLVIDOS

## Exercício 1: Proteger Pasta Admin

**Requisito:** Pasta `/admin` deve exigir senha. Usuário: `admin_ipac`. Senha: `senha123`.

**Solução:**

```apache
# 1. Criar .htpasswd (via cPanel ou SSH)
# Arquivo: /home/seu-usuario/public_html/.htpasswd
# Conteúdo será criado automaticamente

# 2. Adicionar ao .htaccess
<Directory "/home/seu-usuario/public_html/admin">
    AuthType Basic
    AuthName "IPAC - Área Administrativa"
    AuthUserFile /home/seu-usuario/public_html/.htpasswd
    Require valid-user
</Directory>
```

**Teste:**
```bash
# Sem senha = 401
curl https://ipac.com.br/admin

# Com senha = 200
curl -u admin_ipac:senha123 https://ipac.com.br/admin
```

---

## Exercício 2: Reescrever URLs para Blog

**Requisito:** URLs como `/blog/meu-artigo-123` devem rodar `blog.php?slug=meu-artigo-123`.

**Solução:**

```apache
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^blog/([a-z0-9-]+)/?$ blog.php?slug=$1 [QSA,L]
```

**Teste:**
```bash
curl https://ipac.com.br/blog/meu-artigo-123

# Internamente executa
curl https://ipac.com.br/blog.php?slug=meu-artigo-123
```

---

## Exercício 3: Forçar HTTPS + WWW

**Requisito:** Todas as requisições devem ir para HTTPS com WWW.

**Solução:**

```apache
RewriteEngine On
RewriteBase /

# Força HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Força WWW (em domínio sem www)
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteRule ^(.*)$ https://www.%{HTTP_HOST}/$1 [L,R=301]
```

**Teste:**
```bash
# http://ipac.com.br/usuarios
# → https://www.ipac.com.br/usuarios [301]

# https://ipac.com.br/usuarios
# → https://www.ipac.com.br/usuarios [301]

# https://www.ipac.com.br/usuarios
# → https://www.ipac.com.br/usuarios [200] ✅
```

---

## Exercício 4: API REST com Versões

**Requisito:** API com padrão `/api/v{versao}/{recurso}/{id}`.

**Solução:**

```apache
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Rota: /api/v1/usuarios → api.php?v=1&r=usuarios
# Rota: /api/v1/usuarios/123 → api.php?v=1&r=usuarios&id=123
RewriteRule ^api/v([0-9]+)/([a-z]+)(?:/([0-9]+))?/?$ api.php?v=$1&r=$2&id=$3 [QSA,L]
```

**Teste PHP:**
```php
<?php
$version = $_GET['v']; // 1
$resource = $_GET['r']; // usuarios
$id = $_GET['id'] ?? null; // 123 ou null

// Seu código API aqui
?>
```

---

## Exercício 5: Bloquear Hotlink de Imagens

**Requisito:** Não permitir que outros sites façam hotlink de suas imagens.

**Solução:**

```apache
# ============================================
# PREVENIR HOTLINK DE IMAGENS
# ============================================

RewriteCond %{HTTP_REFERER} !^$
# Permite se Referer vazio (acesso direto)

RewriteCond %{HTTP_REFERER} !^https?://ipaccontabilidade\.com\.br [NC]
# Permite se Referer é seu domínio

RewriteCond %{REQUEST_FILENAME} \.(jpg|jpeg|png|gif|webp)$ [NC]
# Se requisição é imagem

RewriteRule ^.*$ - [F]
# Nega acesso (403)
```

**Resultado:**
- Acesso direto: `https://ipac.com.br/logo.png` ✅ Funciona
- Hotlink (outro site): Bloqueado 403 ❌

---

## Exercício 6: Cache Inteligente por Tipo

**Requisito:** Imagens por 1 ano, CSS/JS por 1 mês, HTML por 1 dia.

**Solução:**

```apache
<IfModule mod_expires.c>
    ExpiresActive On
    
    # 1 ano para imagens
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    
    # 1 mês para CSS/JS
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    
    # 1 dia para HTML
    ExpiresByType text/html "access plus 1 day"
    
    # Default: 8 horas
    ExpiresDefault "access plus 8 hours"
</IfModule>

# Além disso: Ativa compressão
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
```

---

## Exercício 7: Redirecionar Site Antigo

**Requisito:** Domínio `velhosistema.com.br` → `ipaccontabilidade.com.br`.

**Solução:**

```apache
RewriteCond %{HTTP_HOST} ^velhosistema\.com\.br$ [NC]
RewriteRule ^(.*)$ https://ipaccontabilidade.com.br/$1 [L,R=301]
```

**Teste:**
```bash
# velhosistema.com.br/usuarios/123
# → ipaccontabilidade.com.br/usuarios/123 [301]
```

---

## Exercício 8: Autenticação por IP + Fallback Senha

**Requisito:** Rede interna (192.168.1.0/24) acessa sem senha. Outros precisam senha.

**Solução:**

```apache
<Directory "/home/seu-usuario/public_html/admin">
    Order Deny,Allow
    Deny from all
    
    # Libera rede interna
    Allow from 192.168.1.0/24
    
    # Para outros IPs: exigir autenticação
    AuthType Basic
    AuthName "Acesso Restrito"
    AuthUserFile /home/seu-usuario/public_html/.htpasswd
    
    # Requer autenticação se não for IP permitido
    Require valid-user
</Directory>
```

---

# GABARITO COMENTADO - QUIZ

## Questão 1: Qual a diferença entre `httpd.conf` e `.htaccess`?

**Resposta Correta:**
- `httpd.conf` = Configuração global do Apache, lida no boot, aplicável a todos os sites
- `.htaccess` = Configuração por pasta, lida a cada requisição, específica por site

**Comentário:** Se você mexer em `httpd.conf` errado, Apache não inicia. Se errar `.htaccess`, reinicia automaticamente.

---

## Questão 2: O que faz `RewriteEngine On`?

**Resposta Correta:**
Ativa o módulo mod_rewrite para aquele diretório. Sem isso, nenhuma `RewriteRule` funciona.

**Comentário:** Deve estar NO INÍCIO do `.htaccess`.

---

## Questão 3: Como você bloqueia acesso a `config.php`?

**Resposta Correta:**
```apache
<Files "config.php">
    Deny from all
</Files>
```

**Comentário:** Também funciona com `FilesMatch` para padrões:
```apache
<FilesMatch "^config">
    Deny from all
</FilesMatch>
```

---

## Questão 4: O que significa `[L,R=301]` em uma RewriteRule?

**Resposta Correta:**
- `L` = Last (última regra, para de processar)
- `R=301` = Redireciona com status HTTP 301 (permanente)

**Comentário:** 301 é para SEO (buscadores seguem). 302 é para temporário.

---

## Questão 5: Qual a função de `RewriteCond %{REQUEST_FILENAME} !-f`?

**Resposta Correta:**
Verifica se o arquivo solicitado **NÃO** é um arquivo real:
- `REQUEST_FILENAME` = Arquivo/caminho solicitado
- `!-f` = Negação de "é arquivo" (! = não, -f = é arquivo)

**Comentário:** Essencial para evitar loops. Se arquivo existe, a regra não executa.

---

## Questão 6: Como forçar HTTPS automaticamente?

**Resposta Correta:**
```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Comentário:** Primeiro verifica se HTTPS está off. Se sim, reescreve para HTTPS.

---

## Questão 7: O que faz `Options -Indexes`?

**Resposta Correta:**
Desabilita listagem de diretórios. Sem isso, acesso a `/uploads/` mostra lista de arquivos.

**Comentário:** Sempre use em produção por segurança.

---

## Questão 8: Como transformar `usuarios.php?id=123` em `usuarios/123`?

**Resposta Correta:**
```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^usuarios/([0-9]+)/?$ usuarios.php?id=$1 [QSA,L]
```

**Comentário:** Client vê URL bonita, Apache reescreve internamente.

---

## Questão 9: Qual modulo Apache precisa estar ativo para Rewrite?

**Resposta Correta:**
`mod_rewrite`

**Comentário:** Verificar no cPanel: Apache Modules → search "rewrite"

---

## Questão 10: Como proteger uma pasta com senha via .htaccess?

**Resposta Correta:**
```apache
<Directory "/path/to/admin">
    AuthType Basic
    AuthName "Área Restrita"
    AuthUserFile /path/to/.htpasswd
    Require valid-user
</Directory>
```

**Comentário:** Arquivo `.htpasswd` criado via cPanel Password Protect Directories.

---

# REFERÊNCIAS CIENTÍFICAS

## Documentação Oficial

1. **Apache HTTP Server Documentation**
   - https://httpd.apache.org/docs/2.4/
   - Reference: mod_rewrite, mod_deflate, mod_expires

2. **RFC 7231 - HTTP/1.1 Semantics and Semantics**
   - Status codes, headers, caching
   - https://tools.ietf.org/html/rfc7231

3. **RFC 7234 - HTTP/1.1 Caching**
   - Cache-Control, Expires, ETag
   - https://tools.ietf.org/html/rfc7234

## Ferramentas Recomendadas

1. **regex101.com** - Teste suas regex expressions
2. **Regex Pattern Matching in RewriteRule** - Apache docs
3. **cPanel Documentation** - Password Protect, Apache Modules
4. **Browser DevTools** - Inspecione headers HTTP (Network tab)

## Leitura Complementar

1. Watt, Andrew. "Understanding Regular Expressions". Apress, 2001.
2. Reblitz-Richardson, Scott et al. "Apache Security". O'Reilly, 2005.
3. Pokeabot, Youri. ".htaccess Snippets". GitHub (compilação de soluções)

---

# CONCLUSÃO

Você agora entende:

✅ Como Apache funciona e processa `.htaccess`  
✅ Sintaxe formal e variáveis do servidor  
✅ Regex como linguagem de padrões  
✅ Segurança profissional (bloqueios, autenticação)  
✅ Redirecionamento e URL rewriting  
✅ Cache e otimização HTTP  
✅ Troubleshooting científico  

Não é "cópia e cola". É **compreensão profunda**.

Use esse conhecimento com responsabilidade. Um `.htaccess` errado pode derrubar seu site. Sempre **teste incrementalmente** e **guarde backup** antes de mudanças em produção.

---

**Versão:** 1.0  
**Data:** Agosto 2026  
**Nível:** Intermediário (2)  
**Status:** Completo para produção

---

*Livro estruturado conforme Metodologia Ativa de Pierluigi Piazzi: Fundações Teóricas → Anatomia → Exemplos Práticos → Armadilhas → Exercícios Resolvidos → Gabarito Comentado.*

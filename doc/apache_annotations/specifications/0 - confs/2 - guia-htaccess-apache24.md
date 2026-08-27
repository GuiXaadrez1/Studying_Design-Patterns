# Guia de Referência Técnica: .htaccess — Configuração por Diretório no Apache HTTP Server 2.4

*Terceiro documento da série de referência (httpd.conf → httpd-vhosts.conf → .htaccess). Fonte primária: documentação oficial do Apache HTTP Server 2.4 (httpd.apache.org/docs/2.4/howto/htaccess.html e páginas de módulos).*

---

## Parte I — Fundamentação Conceitual

### 1.1 O que é o .htaccess

O `.htaccess` (hypertext access) é um **arquivo de configuração distribuído**: em vez de viver na configuração central do servidor, ele reside dentro de um diretório da árvore de documentos e aplica diretivas **àquele diretório e a todos os seus subdiretórios**, em tempo de requisição.

Três propriedades o definem — e delas derivam tanto sua utilidade quanto seus problemas:

1. **É avaliado em runtime, a cada requisição.** Mudanças têm efeito imediato, sem reiniciar o Apache. É o único dos três arquivos da série com essa característica (o `httpd.conf` e os vhosts são lidos apenas na inicialização).
2. **É hierárquico e cumulativo.** Para servir `/site/public/img/logo.png`, o Apache lê (ou verifica a ausência de) `.htaccess` em **cada** diretório do caminho, do mais raso ao mais profundo, mesclando as diretivas — as mais profundas sobrepõem as mais rasas.
3. **É subordinado ao `AllowOverride`.** O `.htaccess` só pode conter as classes de diretivas que o `<Directory>` correspondente (no vhost ou global) autorizar. Com `AllowOverride None`, o Apache **nem lê** o arquivo.

O nome do arquivo é configurável pela diretiva `AccessFileName` (padrão: `.htaccess`), definida no contexto global.

### 1.2 Quando o .htaccess é a ferramenta certa — e quando não é

A documentação oficial do Apache é categórica: *arquivos `.htaccess` devem ser usados somente quando não se tem acesso à configuração principal do servidor*. A regra de decisão:

| Cenário | Ferramenta correta |
|---|---|
| Servidor próprio (dev local XAMPP, VPS, dedicado) | Vhost com `AllowOverride None` — ver guia do `httpd-vhosts.conf` |
| Hospedagem compartilhada / conta cPanel sem acesso ao vhost | **`.htaccess`** — é para isso que ele existe |
| Administrador delegando poder limitado a usuários (WHM) | `AllowOverride` restrito (ex.: só `FileInfo`) + `.htaccess` do usuário |
| Aplicação distribuída para instalação em qualquer host (WordPress, Laravel) | `.htaccess` incluído no pacote — o desenvolvedor não controla o servidor de destino |

Os custos de usar `.htaccess` quando havia alternativa (detalhados no guia de vhosts, recapitulados aqui): I/O de filesystem por requisição em cada nível de diretório; superfície de segurança (quem escreve no diretório reconfigura o servidor); erro de sintaxe derruba o site com **500 Internal Server Error** em produção, sem validação prévia possível (`httpd -t` não valida `.htaccess`); e depuração difícil, pois o efeito final é a mescla de N arquivos espalhados.

**Contexto prático desta série:** nas contas de clientes do servidor cPanel/WHM, o `.htaccess` é o instrumento legítimo e este guia é a referência para escrevê-lo bem. No ambiente de desenvolvimento XAMPP e nas aplicações sob controle direto, prefira o vhost.

### 1.3 A mecânica: AllowOverride e as classes de diretivas

Cada diretiva do Apache pertence a uma **classe de override**. O `.htaccess` só pode usar diretivas cujas classes o `AllowOverride` do `<Directory>` pai liberou:

| Classe | O que libera (exemplos) |
|---|---|
| `AuthConfig` | Autenticação: `AuthType`, `AuthName`, `AuthUserFile`, `Require valid-user` |
| `FileInfo` | Metadados e fluxo do documento: **mod_rewrite** (`RewriteRule` etc.), `Redirect`, `ErrorDocument`, `Header`, `AddType`, `SetHandler` |
| `Indexes` | Indexação de diretório: `DirectoryIndex`, `IndexOptions` |
| `Limit` | Controle de acesso por host: `Require all granted/denied`, `Require ip …` |
| `Options[=…]` | A diretiva `Options` (opcionalmente restrita a valores: `AllowOverride Options=Indexes,MultiViews`) |
| `All` | Todas as classes acima |
| `None` | Nada — o arquivo não é sequer lido |

> **Padrão no 2.4:** `AllowOverride None`. Se o seu `.htaccess` "não faz nada", esta é a primeira suspeita — o servidor está ignorando o arquivo por decisão do `<Directory>` pai. Diretiva fora da classe permitida gera **500** com `… not allowed here` no ErrorLog.

**Herança e sobreposição:** as diretivas de um `.htaccess` valem para o diretório onde ele está **e descem** para os subdiretórios, até que outro `.htaccess` mais profundo as redefina. O merge segue a ordem documentada em `sections.html`: os `.htaccess` são aplicados junto com os `<Directory>` correspondentes, do caminho mais curto para o mais longo — por isso o arquivo mais próximo do recurso "ganha".

### 1.4 Diferenças de contexto que causam bugs

O `.htaccess` opera **relativo ao seu diretório**, e isso muda a semântica de algumas diretivas em relação ao vhost:

- **Padrões do `RewriteRule` não têm barra inicial.** Em `.htaccess`, o Apache remove o prefixo do diretório antes de casar: a URL `/public/produtos` num `.htaccess` em `public/` casa como `produtos`. No corpo de um vhost, casaria como `/public/produtos`.
- **`RewriteBase`** existe *apenas* para `.htaccess`: informa o prefixo de URL a repor quando o alvo da reescrita é relativo. Necessária quando o diretório físico não corresponde trivialmente à URL (Alias, userdir). Em raiz simples, `RewriteBase /` basta.
- **Reescrita é reiniciada por rodada.** Reescritas internas em `.htaccess` disparam uma nova passada de processamento (internal redirect); flags como `[L]` encerram a rodada atual, não o processo todo — origem clássica de loops que no vhost usariam a flag `[END]` (que encerra de vez, disponível desde 2.3.9 e utilizável também em `.htaccess`).
- **Sem containers `<Directory>`/`<Location>`/`<VirtualHost>`** dentro de `.htaccess` — apenas `<Files>`/`<FilesMatch>`, `<IfModule>` e `<Limit>` são válidos.

---

## Parte II — Diretivas de Referência

Formato: **(a)** nome · **(b)** função · **(c)** classe de override exigida · **(d)** padrão · **(e)** exemplo. Todas pressupõem que a classe correspondente esteja liberada no `AllowOverride` do servidor.

### 2.1 Reescrita e redirecionamento

#### RewriteEngine / RewriteCond / RewriteRule
- **Função:** Motor de reescrita de URLs (mod_rewrite). `RewriteCond` condiciona a regra imediatamente seguinte (não é global); `RewriteRule` casa o padrão e reescreve/redireciona.
- **Classe:** `FileInfo`.
- **Padrão:** `RewriteEngine Off` — e a configuração **não é herdada** de `.htaccess` pai: cada arquivo que reescreve precisa do seu próprio `RewriteEngine On`.
- **Flags essenciais:** `[L]` última da rodada · `[END]` encerra definitivamente · `[R=301|302]` redirect externo · `[QSA]` preserva query string · `[NC]` case-insensitive · `[F]` 403 · `[G]` 410.
- **Exemplo (front controller):**
  ```apache
  RewriteEngine On
  RewriteBase /
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
  ```

#### RewriteBase
- **Função:** Define o prefixo de URL usado para reescritas relativas — compensa o fato de o `.htaccess` desconhecer "onde" está na URL.
- **Classe:** `FileInfo`. **Padrão:** o caminho físico do diretório (frequentemente errado sob Alias).
- **Exemplo:** `RewriteBase /minha-app/`

#### Redirect / RedirectMatch / RedirectPermanent
- **Função:** Redirecionamentos por prefixo (`Redirect`) ou regex (`RedirectMatch`), via mod_alias — mais simples e barato que mod_rewrite quando não há condições a testar.
- **Classe:** `FileInfo`. **Padrão:** status 302.
- **Exemplo:**
  ```apache
  Redirect permanent "/catalogo-2024" "/catalogo"
  RedirectMatch 410 "\.(bak|old)$"
  ```
- **Cuidado:** não misture `Redirect` e `RewriteRule` para as mesmas URLs — módulos diferentes, ordem de execução não intuitiva.

### 2.2 Controle de acesso e autenticação

#### Require
- **Função:** Autorização (2.4): concede/nega por origem ou usuário. Substitui `Order/Allow/Deny` do 2.2 — sintaxe legada em tutoriais antigos deve ser convertida.
- **Classe:** `Limit` (variantes de host) / `AuthConfig` (variantes de usuário).
- **Exemplo:**
  ```apache
  Require all denied            # bloqueia o diretório inteiro
  Require ip 192.168.0          # exceto a rede interna
  ```

#### AuthType / AuthName / AuthUserFile + Require valid-user
- **Função:** Autenticação HTTP Basic por diretório — o clássico "diretório protegido por senha" do cPanel.
- **Classe:** `AuthConfig`.
- **Exemplo:**
  ```apache
  AuthType Basic
  AuthName "Área restrita"
  AuthUserFile "/home/conta/.htpasswds/admin/passwd"   # SEMPRE fora do DocumentRoot
  Require valid-user
  ```
  *(o arquivo de senhas é gerado com o utilitário `htpasswd`)*

#### `<Files>` / `<FilesMatch>`
- **Função:** Únicos containers de escopo válidos em `.htaccess`; aplicam diretivas a arquivos por nome/regex.
- **Classe:** a das diretivas internas.
- **Exemplo (padrão de segurança):**
  ```apache
  <FilesMatch "^\.(env|git|htaccess|htpasswd)">
      Require all denied
  </FilesMatch>
  ```

### 2.3 Documento e diretório

#### DirectoryIndex
- **Função:** Arquivo(s) servido(s) quando a URL aponta para o diretório.
- **Classe:** `Indexes`. **Padrão:** `index.html`.
- **Exemplo:** `DirectoryIndex index.php index.html`

#### Options
- **Função:** Recursos do diretório. Em `.htaccess` **sempre** com `+`/`-` (forma absoluta exige `AllowOverride Options` pleno e substitui todo o conjunto herdado).
- **Classe:** `Options`. 
- **Exemplo:** `Options -Indexes` *(desliga listagem de diretório — item nº 1 de hardening em hospedagem compartilhada)*

#### ErrorDocument
- **Função:** Página/mensagem de erro customizada no escopo do diretório.
- **Classe:** `FileInfo`.
- **Exemplo:** `ErrorDocument 404 /erros/404.html`

### 2.4 Tipos, handlers e codificação

#### AddType / AddHandler / SetHandler
- **Função:** Mapeiam extensão→media type, extensão→handler, ou forçam handler para tudo que o container casar.
- **Classe:** `FileInfo`.
- **Exemplo:**
  ```apache
  AddType application/manifest+json .webmanifest
  ```
- **Alerta de segurança:** em hospedagem compartilhada, provedores frequentemente **bloqueiam** `AddHandler`/`SetHandler` para PHP — historicamente vetor de execução de uploads maliciosos (`arquivo.php.jpg`).

#### AddDefaultCharset
- **Função:** Charset padrão para respostas text/html e text/plain.
- **Classe:** `FileInfo`. **Padrão:** `Off`.
- **Exemplo:** `AddDefaultCharset UTF-8`

### 2.5 Cabeçalhos, cache e compressão

#### Header (mod_headers)
- **Função:** Define/altera cabeçalhos HTTP de resposta — segurança e cache.
- **Classe:** `FileInfo`.
- **Exemplo (hardening mínimo):**
  ```apache
  <IfModule mod_headers.c>
      Header set X-Content-Type-Options "nosniff"
      Header set X-Frame-Options "SAMEORIGIN"
      Header set Referrer-Policy "strict-origin-when-cross-origin"
  </IfModule>
  ```

#### ExpiresActive / ExpiresByType (mod_expires)
- **Função:** Cache HTTP por tipo de conteúdo (`Cache-Control`/`Expires`).
- **Classe:** `Indexes` (sim — peculiaridade documentada do mod_expires).
- **Exemplo:**
  ```apache
  <IfModule mod_expires.c>
      ExpiresActive On
      ExpiresByType image/webp  "access plus 1 month"
      ExpiresByType text/css    "access plus 1 week"
      ExpiresByType text/html   "access plus 0 seconds"
  </IfModule>
  ```

#### mod_deflate (compressão)
- **Função:** Compressão gzip das respostas por tipo.
- **Classe:** `FileInfo`.
- **Exemplo:**
  ```apache
  <IfModule mod_deflate.c>
      AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
  </IfModule>
  ```

> O envelope `<IfModule>` nesses três blocos é convenção defensiva de hospedagem compartilhada: você não controla quais módulos o provedor carregou, e sem o envelope uma diretiva de módulo ausente derruba o site com 500.

### 2.6 Ambiente e PHP

#### SetEnv / SetEnvIf
- **Função:** Variáveis de ambiente por requisição (fixas ou condicionais).
- **Classe:** `FileInfo`.
- **Exemplo:** `SetEnv APP_ENV "production"`

#### php_value / php_flag — nota de compatibilidade
- **Função:** Ajustes de `php.ini` por diretório — **somente com mod_php**. Em cPanel moderno o PHP roda como FPM/CGI, onde essas diretivas causam **500**; o equivalente é o arquivo **`.user.ini`** no mesmo diretório:
  ```ini
  ; .user.ini (PHP-FPM/CGI)
  upload_max_filesize = 32M
  post_max_size = 32M
  ```
- **Classe (quando mod_php):** definida pelo `AllowOverride` de `Options`/`FileInfo` conforme a variante (`php_admin_*` nunca é permitida em `.htaccess`).

---

## Parte III — Exemplos Completos Comentados

### 3.1 Front controller de aplicação PHP (o caso clássico)

```apache
# .htaccess na raiz pública da aplicação
RewriteEngine On
RewriteBase /

# arquivos e diretórios reais são servidos diretamente
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
# todo o resto vai ao ponto de entrada único
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

### 3.2 Conta cPanel endurecida (hardening de hospedagem compartilhada)

```apache
# .htaccess em public_html/

# ---- Higiene básica ----
Options -Indexes                          # sem listagem de diretórios
ServerSignature Off

# ---- Arquivos que jamais devem ser servidos ----
<FilesMatch "^\.(env|git|htaccess|htpasswd|user\.ini)">
    Require all denied
</FilesMatch>
<FilesMatch "\.(bak|old|sql|log)$">
    Require all denied
</FilesMatch>

# ---- HTTPS obrigatório ----
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

# ---- Cabeçalhos de segurança ----
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
```

### 3.3 Subdiretório com política própria (herança em ação)

```apache
# public_html/.htaccess       → regras gerais do site (ex.: 3.2)
# public_html/admin/.htaccess → sobrepõe SÓ para /admin:

AuthType Basic
AuthName "Administração"
AuthUserFile "/home/conta/.htpasswds/admin/passwd"
Require valid-user
```

*As regras do arquivo pai continuam valendo em `/admin` — o filho **adiciona** a autenticação. Se o filho redefinisse, por exemplo, `Options`, a redefinição venceria apenas ali.*

### 3.4 Redirecionamento condicional por host (multi-domínio na mesma conta)

```apache
# Cenário cPanel: domínio adicional apontando para a mesma public_html,
# conteúdo do segundo domínio numa subpasta.
RewriteEngine On

RewriteCond %{HTTP_HOST} ^(www\.)?segundodominio\.com\.br$ [NC]
RewriteCond %{REQUEST_URI} !^/segundodominio/
RewriteRule ^(.*)$ /segundodominio/$1 [L]
```

*Lembre: `%{HTTP_HOST}` contém apenas o hostname (nunca `http://` nem o path) — o path é testado no padrão do `RewriteRule` ou em `%{REQUEST_URI}`.*

---

## Parte IV — Diagnóstico

### 4.1 Sintomas e causas

| Sintoma | Causa provável | Verificação/correção |
|---|---|---|
| `.htaccess` totalmente ignorado | `AllowOverride None` no `<Directory>` pai (padrão do 2.4) | No servidor próprio: ajustar o vhost. Em cPanel: já vem liberado; conferir se o arquivo está no diretório certo e se o nome está correto (ponto inicial!) |
| **500 Internal Server Error** imediato | Erro de sintaxe, diretiva de módulo ausente, diretiva fora da classe permitida, ou `php_value` com PHP-FPM | Ler o ErrorLog: `not allowed here` = classe bloqueada; `Invalid command` = módulo ausente/typo → envolver em `<IfModule>` ou remover |
| `ERR_TOO_MANY_REDIRECTS` | Regra de redirect cujo destino casa a própria regra | Adicionar `RewriteCond %{REQUEST_URI} !^/destino` de exclusão; testar com `curl -IL` (navegador cacheia redirects 301) |
| Rewrite parcial: página abre sem CSS/JS | Assets com caminho absoluto caindo no front controller | Excluir estáticos: `RewriteCond %{REQUEST_URI} !\.(css|js|png|jpg|svg|woff2)$` ou usar caminhos corretos |
| Regra funciona na raiz mas não em subpasta | `RewriteBase` ausente/errado sob Alias, ou `.htaccess` filho sem `RewriteEngine On` | Revisar §1.4 — herança de rewrite não é automática |
| 401 em loop / autenticação não pede senha | `AuthUserFile` com caminho errado ou classe `AuthConfig` bloqueada | Caminho **absoluto** para o passwd; conferir ErrorLog |

### 4.2 Ferramentas

- **`curl -I` / `curl -IL`** — a fonte da verdade para redirects: sem cache, mostra status e `Location` de cada salto.
- **ErrorLog** — em cPanel: *Metrics → Errors* ou `~/logs/`; toda falha de `.htaccess` deixa rastro ali.
- **`LogLevel rewrite:trace3`** — depuração de reescrita, **mas não pode ser definida em `.htaccess`** (contexto directory/vhost/global). No servidor próprio, ative no vhost; em cPanel, o método prático é bisseção: comente metade das regras, teste, repita.
- **Teste incremental** — como não existe validador prévio (`httpd -t` não lê `.htaccess`), adicione regras em blocos pequenos e teste a cada mudança; um arquivo grande colado de uma vez transforma qualquer erro em caça às cegas.

### 4.3 Notas 2.2 → 2.4

- `Order` / `Allow from` / `Deny from` / `Satisfy` → substituir por `Require`. Tutoriais antigos de `.htaccess` estão repletos da sintaxe legada; misturá-la com `Require` no mesmo escopo produz comportamento contraintuitivo (`AH01797`).
- `RewriteLog`/`RewriteLogLevel` → removidas; o mecanismo atual é `LogLevel rewrite:traceN` (fora do `.htaccess`).

---

## Apêndice — Checklist do .htaccess bem escrito

1. Confirme que o `.htaccess` é a ferramenta certa (§1.2) — se você controla o vhost, a regra pertence ao vhost.
2. Um `RewriteEngine On` por arquivo que reescreve; `RewriteBase` só quando necessário.
3. Padrões **sem** barra inicial; condições anti-loop em todo redirect.
4. Diretivas de módulos opcionais dentro de `<IfModule>`.
5. `Options -Indexes` e bloqueio de dotfiles/artefatos (`.env`, `.git`, `.sql`, `.bak`) em qualquer diretório público.
6. Nada de `php_value` sem certeza de mod_php — em FPM, use `.user.ini`.
7. Teste cada bloco com `curl` antes de adicionar o próximo; mantenha uma cópia da última versão funcional.

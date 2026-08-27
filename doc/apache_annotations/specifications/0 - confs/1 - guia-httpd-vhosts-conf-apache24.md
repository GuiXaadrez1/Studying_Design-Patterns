# Guia de Referência Técnica: httpd-vhosts.conf — Virtual Hosts no Apache HTTP Server 2.4

*Documento complementar ao Guia de Referência do httpd.conf. Fonte primária: documentação oficial do Apache HTTP Server 2.4 (httpd.apache.org/docs/2.4/).*

---

## Parte I — Fundamentação Conceitual

### 1.1 O que é o httpd-vhosts.conf e por que usá-lo

O `httpd-vhosts.conf` é o arquivo de configuração dedicado aos **Virtual Hosts** — o mecanismo pelo qual uma única instância do Apache serve múltiplos sites, cada um com identidade, raiz de documentos, logs e políticas próprias.

Do ponto de vista de engenharia de configuração, sua razão de existir é a **separação de responsabilidades**:

| Camada | Arquivo | Responsabilidade |
|---|---|---|
| Servidor (global) | `httpd.conf` | Infraestrutura: portas (`Listen`), módulos (`LoadModule`), MPM, políticas padrão, segurança de base |
| Site (por host) | `httpd-vhosts.conf` | Identidade e comportamento de cada aplicação: `ServerName`, `DocumentRoot`, logs, reescrita, acesso |

Essa separação traz três ganhos concretos:

1. **Modularidade** — cada site é um bloco `<VirtualHost>` autocontido; adicionar/remover um site não toca a configuração global.
2. **Manutenibilidade** — diagnóstico por site (logs dedicados) e mudanças com escopo delimitado.
3. **Isolamento de risco** — um erro num vhost não contamina a política dos demais (embora um erro de *sintaxe* ainda impeça o servidor de subir; ver §5).

Existem duas modalidades de virtual hosting:

- **Baseado em nome** (name-based): vários sites compartilham o mesmo par IP:porta; o Apache seleciona o vhost pelo cabeçalho HTTP `Host` comparado ao `ServerName`/`ServerAlias`. É o modelo dominante.
- **Baseado em IP** (IP-based): cada site tem seu próprio endereço IP. Hoje é reservado a cenários específicos (ex.: protocolos que não transportam o nome do host).

> **Nota 2.2 → 2.4:** a diretiva `NameVirtualHost` foi **descontinuada** — no 2.4 ela não tem efeito. A simples existência de múltiplos `<VirtualHost>` no mesmo IP:porta ativa o casamento por nome automaticamente.

### 1.2 Como usá-lo: a mecânica de ativação

O `httpd-vhosts.conf` não é lido magicamente — ele entra na configuração via `Include` no `httpd.conf`:

```apache
# httpd.conf — no XAMPP esta linha vem COMENTADA por padrão; descomente-a
Include conf/extra/httpd-vhosts.conf
```

Caminhos típicos no XAMPP/Windows:

```
C:\xampp\apache\conf\httpd.conf                  ← configuração global
C:\xampp\apache\conf\extra\httpd-vhosts.conf     ← seus VirtualHosts
```

Fluxo completo para um host de desenvolvimento (ex.: `desingpartten.local`):

1. **Descomentar o Include** no `httpd.conf` (se ainda comentado).
2. **Declarar o `<VirtualHost>`** no `httpd-vhosts.conf` (ver exemplos na Parte III).
3. **Mapear o nome no DNS local** — no Windows, editar como Administrador:
   ```
   C:\Windows\System32\drivers\etc\hosts
   ```
   adicionando:
   ```
   127.0.0.1    desingpartten.local
   ```
4. **Validar sintaxe:** `C:\xampp\apache\bin\httpd.exe -t` → deve retornar `Syntax OK`.
5. **Conferir os vhosts registrados:** `httpd.exe -S` → lista cada vhost com arquivo e linha de origem.
6. **Reiniciar o Apache** pelo painel do XAMPP.

> **Efeito colateral importante:** ao ativar o primeiro `<VirtualHost>` para `*:80`, o "servidor principal" do `httpd.conf` **deixa de atender** requisições nessa porta — quem não casar com nenhum `ServerName`/`ServerAlias` cai no **primeiro vhost do arquivo** (o *default*). Por isso, a prática recomendada é declarar como primeiro bloco um vhost explícito para o `localhost`/htdocs padrão (ver §3.4).

### 1.3 Como o VirtualHost sobrescreve o httpd.conf: o modelo de merge

Este é o ponto que mais gera confusão, então vale precisão. O Apache 2.4 resolve a configuração efetiva de cada requisição em duas etapas:

**Etapa 1 — Seleção do vhost.** Com base no IP:porta da conexão e no cabeçalho `Host`, o Apache seleciona **exatamente UM** `<VirtualHost>`. Vhosts **nunca são mesclados entre si** — ou o bloco inteiro se aplica, ou não se aplica. Se nenhum `ServerName`/`ServerAlias` casar, vence o **primeiro** `<VirtualHost>` declarado para aquele IP:porta (host padrão / catch-all).

**Etapa 2 — Merge global → vhost.** As diretivas do vhost selecionado são aplicadas **depois** das diretivas globais correspondentes, **sobrepondo-as** para aquela requisição. Na prática:

- `DocumentRoot` no vhost **substitui** o `DocumentRoot` global.
- `ErrorLog`/`CustomLog` no vhost **substituem** os logs globais — as requisições daquele site param de aparecer no log geral e passam para os logs dedicados.
- Seções `<Directory>` dentro do vhost são mescladas **depois** das seções `<Directory>` globais equivalentes — por isso um `AllowOverride`/`Require` no vhost prevalece sobre o global para o mesmo caminho.
- Diretivas não redefinidas no vhost são **herdadas** do global (ex.: `Timeout`, `DirectoryIndex`, `ServerAdmin`).

**O que NÃO pode viver num vhost** (contexto exclusivamente *server config*):

- `Listen` — portas são propriedade do servidor, não do site.
- `LoadModule` — módulos são carregados uma única vez, globalmente.
- Diretivas de MPM (`ThreadsPerChild`, `MaxRequestWorkers` etc.).
- `ServerRoot`, `PidFile`, `User`/`Group` (Unix).

Colocar qualquer uma delas dentro de `<VirtualHost>` gera erro de sintaxe (`httpd -t` acusa) ou é silenciosamente inválido, dependendo da diretiva.

### 1.4 Produção: configuração no vhost, não em .htaccess

**Tese deste guia:** em qualquer servidor em que você tenha acesso à configuração principal — o que inclui produção sob seu controle — **toda a configuração deve viver no vhost/httpd.conf, e o `.htaccess` deve ser desativado com `AllowOverride None`**. Esta não é uma opinião estilística: é a recomendação explícita da documentação oficial do Apache (`howto/htaccess.html`), que afirma que arquivos `.htaccess` devem ser usados somente quando não há acesso à configuração do servidor.

Os quatro fundamentos técnicos:

**1. Performance.** Com `AllowOverride` habilitado, o Apache precisa procurar e ler arquivos `.htaccess` **em cada diretório do caminho até o arquivo servido, em cada requisição** — mesmo que os arquivos não existam (a *ausência* precisa ser verificada no filesystem toda vez). Para `/var/www/site/public/img/logo.png`, isso significa testar `.htaccess` em `/`, `/var`, `/var/www`, `/var/www/site`, `/var/www/site/public` e `/var/www/site/public/img` — seis operações de I/O por requisição, multiplicadas por cada asset da página. A configuração no vhost é lida **uma única vez**, na inicialização, e fica em memória.

**2. Segurança e governança.** `.htaccess` delega poder de reconfiguração do servidor a qualquer processo/usuário com permissão de escrita no diretório — incluindo a própria aplicação web. Um invasor que consiga gravar um arquivo via falha de upload pode alterar handlers, redirecionar tráfego ou expor diretórios. Centralizar no vhost restringe mudanças a quem tem acesso administrativo, com trilha de auditoria (controle de versão da configuração).

**3. Depuração e previsibilidade.** Configuração espalhada em N arquivos `.htaccess` pela árvore de diretórios, com regras de herança e sobreposição entre eles, é notoriamente difícil de diagnosticar — o efeito final depende de arquivos que você pode nem saber que existem. No vhost, a configuração do site é um bloco único e legível, verificável com `httpd -t` **antes** de entrar em vigor (o `.htaccess`, em contraste, é avaliado em runtime: um erro de sintaxe nele derruba o site com erro 500 na hora, sem validação prévia).

**4. Desativação verificável.** `AllowOverride None` faz o Apache **nem tentar ler** os arquivos `.htaccess` — eliminando o custo de I/O e o vetor de segurança de uma vez.

#### Migrando regras de .htaccess para o vhost

A migração típica de um front controller:

```apache
# ANTES — .htaccess na raiz pública:
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
```

```apache
# DEPOIS — dentro do <VirtualHost>, no bloco <Directory> da raiz pública:
<Directory "C:/xampp/htdocs/Studying_Design-Patterns/public">
    AllowOverride None
    Require all granted

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
</Directory>
```

Diferenças de contexto do mod_rewrite que importam na migração:

- **`RewriteBase` torna-se irrelevante** — ela só existe para compensar o fato de o `.htaccess` operar sobre caminhos relativos ao seu diretório. Em `<Directory>` de vhost, remova-a.
- **Em contexto `<Directory>`**, o padrão do `RewriteRule` casa contra o caminho relativo ao diretório, **sem barra inicial** (igual ao `.htaccess`) — a regra acima migra sem alteração no padrão.
- **Em contexto de vhost direto** (fora de `<Directory>`), o padrão casa contra a **URL-path completa, com barra inicial** — `^/(.*)$` em vez de `^(.*)$`. Além disso, rewrite declarado no corpo do vhost roda em fase diferente (antes do mapeamento para o filesystem) e **não é herdado** automaticamente por seções `<Directory>`; cada contexto precisa do seu próprio `RewriteEngine On`.

#### A exceção legítima

Hospedagem **compartilhada** — como contas cPanel em servidores que você administra via WHM — é o cenário para o qual o `.htaccess` existe: o usuário final não tem acesso ao vhost, e o `AllowOverride` limitado é o mecanismo de delegação controlada. Nesse contexto (ex.: contas de clientes no servidor HostGator/cPanel), o `.htaccess` é apropriado — o administrador define via WHM **o quê** pode ser sobrescrito, e o cliente opera dentro desse perímetro. A recomendação deste guia aplica-se aos servidores e aplicações sob seu controle direto.

---

## Parte II — Diretivas de Referência

Formato: **(a)** nome · **(b)** função · **(c)** contexto · **(d)** padrão · **(e)** exemplo.

### 2.1 `<VirtualHost>`

- **Função:** Container que delimita as diretivas de um host virtual. O argumento é o(s) endereço(s) IP:porta que o vhost atende; `*` casa qualquer endereço.
- **Contexto:** server config.
- **Padrão:** n/a.
- **Sintaxes aceitas:**
  ```apache
  <VirtualHost *:80>                    # qualquer IP, porta 80 (o mais comum)
  <VirtualHost 192.168.0.10:80>         # IP específico
  <VirtualHost *:80 *:8080>             # múltiplos pares IP:porta
  <VirtualHost _default_:80>            # catch-all explícito para conexões sem vhost casado
  ```
- **Regras de seleção:** apenas um vhost por requisição; primeiro declarado = padrão do par IP:porta; vhosts não se mesclam entre si.

### 2.2 ServerName

- **Função:** Nome canônico do vhost, usado no casamento por nome e em URLs autorreferenciais.
- **Contexto:** server config, virtual host.
- **Padrão:** derivado do hostname do sistema (desencorajado; gera aviso `AH00558`).
- **Exemplo:** `ServerName desingpartten.local`

### 2.3 ServerAlias

- **Função:** Nomes alternativos (aceita curingas `*` e `?`) que também casam com o vhost.
- **Contexto:** virtual host.
- **Padrão:** nenhum.
- **Exemplo:** `ServerAlias www.desingpartten.local *.desingpartten.local`

### 2.4 DocumentRoot

- **Função:** Diretório raiz servido pelo vhost; o caminho da URL é anexado a ele para localizar o recurso.
- **Contexto:** server config, virtual host.
- **Padrão:** o global do `httpd.conf` (herdado se omitido — omitir é quase sempre um erro num vhost).
- **Cuidados:** sem barra final; barras normais `/` mesmo no Windows; **sempre** acompanhado de um `<Directory>` correspondente concedendo acesso — sem ele, o padrão restritivo global pode negar tudo (403).
- **Exemplo:** `DocumentRoot "C:/xampp/htdocs/Studying_Design-Patterns/public"`

### 2.5 `<Directory>` (dentro do vhost)

- **Função:** Escopo de políticas de filesystem do site: `Options`, `AllowOverride`, `Require`, regras de rewrite.
- **Contexto:** server config, virtual host.
- **Padrão:** n/a. Merge: aplicado **depois** dos `<Directory>` globais para o mesmo caminho.
- **Exemplo (produção):**
  ```apache
  <Directory "/var/www/app/public">
      Options FollowSymLinks          # sem Indexes em produção
      AllowOverride None              # .htaccess ignorado por completo
      Require all granted
  </Directory>
  ```

### 2.6 ErrorLog / CustomLog / LogLevel (por vhost)

- **Função:** Logs dedicados do site. `ErrorLog` recebe erros; `CustomLog` registra acessos com um formato nomeado (`common`, `combined`); `LogLevel` ajusta a verbosidade **daquele vhost**, inclusive por módulo.
- **Contexto:** server config, virtual host (`LogLevel` também em directory).
- **Padrão:** herdam os globais se omitidos.
- **Exemplo:**
  ```apache
  ErrorLog  "logs/desingpartten-error.log"
  CustomLog "logs/desingpartten-access.log" combined
  LogLevel warn rewrite:trace3     # depuração de rewrite SÓ neste site
  ```
  *(caminhos relativos resolvem contra `ServerRoot` — no XAMPP, `C:/xampp/apache/logs/`)*

### 2.7 ErrorDocument (por vhost)

- **Função:** Página/mensagem customizada por código de status, com escopo do site.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** mensagens internas do Apache.
- **Exemplo:**
  ```apache
  ErrorDocument 404 /erros/404.html
  ErrorDocument 503 "Em manutenção. Volte em instantes."
  ```

### 2.8 Alias / ScriptAlias (por vhost)

- **Função:** Mapeiam uma URL do site para um diretório fora do `DocumentRoot` (`ScriptAlias` adicionalmente marca o destino como CGI).
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum.
- **Exemplo:**
  ```apache
  Alias "/downloads" "C:/xampp/dados/arquivos-publicos"
  <Directory "C:/xampp/dados/arquivos-publicos">
      Require all granted
  </Directory>
  ```
  *(o `<Directory>` do destino é obrigatório — Alias mapeia a URL, mas o acesso ainda precisa ser concedido)*

### 2.9 RewriteEngine / RewriteCond / RewriteRule (contexto de vhost)

- **Função:** Motor de reescrita de URLs no escopo do site.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `RewriteEngine Off`.
- **Particularidades no vhost (recapitulando §1.4):**
  - Configuração de rewrite **não é herdada** entre contextos — cada `<Directory>` que reescreve precisa do seu `RewriteEngine On`.
  - Corpo do vhost: padrão casa a URL-path **com** barra inicial (`^/(.*)$`); dentro de `<Directory>`: **sem** barra (`^(.*)$`).
  - `RewriteBase`: não usar fora de `.htaccess`.
- **Exemplo (front controller no vhost, sem .htaccess):**
  ```apache
  <Directory "/var/www/app/public">
      RewriteEngine On
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteCond %{REQUEST_FILENAME} !-d
      RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
  </Directory>
  ```

### 2.10 Redirect / RedirectMatch (mod_alias)

- **Função:** Redirecionamentos HTTP simples, **sem** o custo e a complexidade do mod_rewrite. `Redirect` casa por prefixo de URL; `RedirectMatch` por expressão regular. Regra prática: se não precisa de condições (`RewriteCond`), prefira `Redirect`.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** status `302` se não especificado.
- **Exemplo:**
  ```apache
  Redirect permanent "/antigo" "https://desingpartten.local/novo"
  RedirectMatch 302 "^/w(.*)$" "/with_php/w$1"
  ```

### 2.11 SSL/TLS básico: `<VirtualHost *:443>`

- **Diretivas:** `SSLEngine` (liga TLS no vhost), `SSLCertificateFile` (certificado), `SSLCertificateKeyFile` (chave privada). Requerem `LoadModule ssl_module` e `Listen 443` **no httpd.conf** (globais — ver §1.3).
- **Contexto:** server config, virtual host.
- **Padrão:** `SSLEngine Off`.
- **Exemplo:**
  ```apache
  <VirtualHost *:443>
      ServerName app.exemplo.com.br
      DocumentRoot "/var/www/app/public"
      SSLEngine On
      SSLCertificateFile    "/etc/ssl/certs/app.crt"
      SSLCertificateKeyFile "/etc/ssl/private/app.key"
  </VirtualHost>
  ```

### 2.12 ProxyPass / ProxyPassReverse (reverse proxy)

- **Função:** Encaminham requisições do vhost para um backend HTTP e reescrevem os cabeçalhos de resposta (`Location` etc.) no caminho de volta. Cenário típico: Apache como fachada de uma aplicação rodando localmente — por exemplo, um Laravel em `php artisan serve` na `127.0.0.1:8000`.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum. Requerem `mod_proxy` + `mod_proxy_http` carregados globalmente.
- **Exemplo:**
  ```apache
  <VirtualHost *:80>
      ServerName api.myipac.local
      ProxyPreserveHost On
      ProxyPass        "/" "http://127.0.0.1:8000/"
      ProxyPassReverse "/" "http://127.0.0.1:8000/"
  </VirtualHost>
  ```

### 2.13 SetEnv / SetEnvIf

- **Função:** `SetEnv` define uma variável de ambiente fixa para a requisição (visível ao PHP via `getenv()`/`$_SERVER`); `SetEnvIf` define condicionalmente com base em atributos da requisição. Uso típico: sinalizar o ambiente (`dev`/`prod`) ou excluir health-checks do log.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** nenhum.
- **Exemplo:**
  ```apache
  SetEnv APP_ENV "production"
  SetEnvIf Request_URI "^/health$" nolog
  CustomLog "logs/app-access.log" combined env=!nolog
  ```

### 2.14 php_value / php_admin_value — nota de compatibilidade

- **Função:** Ajustam diretivas do `php.ini` por vhost/diretório (`php_admin_value` não pode ser sobrescrito por `.htaccess`/`ini_set`).
- **Restrição crucial:** só existem quando o PHP roda como **mod_php** (módulo Apache) — o caso do XAMPP. Em produção moderna com **PHP-FPM** (FastCGI), essas diretivas **não existem**; o equivalente é configurar no pool do FPM (`php_admin_value[...]` no `www.conf`) ou via arquivo `.user.ini`.
- **Exemplo (apenas mod_php/XAMPP):**
  ```apache
  php_admin_value upload_max_filesize "32M"
  php_value error_reporting "E_ALL"
  ```

---

## Parte III — Exemplos Completos Comentados

### 3.1 Desenvolvimento — XAMPP/Windows, front controller sem .htaccess

```apache
# C:\xampp\apache\conf\extra\httpd-vhosts.conf

<VirtualHost *:80>
    ServerName desingpartten.local
    DocumentRoot "C:/xampp/htdocs/Studying_Design-Patterns/public"

    <Directory "C:/xampp/htdocs/Studying_Design-Patterns/public">
        Options FollowSymLinks
        AllowOverride None                 # .htaccess ignorado — tudo vive aqui
        Require all granted

        # Front controller: tudo que não é arquivo/pasta real vai ao index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php?url=$1 [QSA,L]
    </Directory>

    # Logs dedicados: cada site depura no seu próprio arquivo
    ErrorLog  "logs/desingpartten-error.log"
    CustomLog "logs/desingpartten-access.log" combined
</VirtualHost>
```

*Pré-requisitos: `mod_rewrite` descomentado no `httpd.conf`; entrada `127.0.0.1 desingpartten.local` no arquivo `hosts` do Windows; Apache reiniciado após a edição (vhost não é relido em runtime, ao contrário do .htaccess).*

### 3.2 Produção — Linux, endurecido

```apache
# /etc/httpd/conf.d/app.conf  (ou sites-available/ em Debian/Ubuntu)

<VirtualHost *:443>
    ServerName  app.exemplo.com.br
    ServerAlias www.app.exemplo.com.br
    DocumentRoot "/var/www/app/public"

    SSLEngine On
    SSLCertificateFile    "/etc/ssl/certs/app.crt"
    SSLCertificateKeyFile "/etc/ssl/private/app.key"

    <Directory "/var/www/app/public">
        Options FollowSymLinks             # SEM Indexes: nada de listagem de diretórios
        AllowOverride None                 # performance + segurança + previsibilidade
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # Bloqueia acesso a arquivos sensíveis por padrão de nome
    <FilesMatch "^\.(env|git|htaccess)">
        Require all denied
    </FilesMatch>

    ErrorLog  "/var/log/httpd/app-error.log"
    CustomLog "/var/log/httpd/app-access.log" combined
    ServerSignature Off
</VirtualHost>
```

*(`ServerTokens Prod` fica no global — contexto server config apenas.)*

### 3.3 Par HTTP → HTTPS

```apache
# vhost :80 — existe SÓ para redirecionar; nenhum conteúdo é servido aqui
<VirtualHost *:80>
    ServerName  app.exemplo.com.br
    ServerAlias www.app.exemplo.com.br
    Redirect permanent "/" "https://app.exemplo.com.br/"
</VirtualHost>

# vhost :443 — o site real (bloco do §3.2)
```

*`Redirect` do mod_alias basta aqui — não há condição a testar, logo mod_rewrite seria complexidade gratuita.*

### 3.4 Múltiplos vhosts e o comportamento do "primeiro = default"

```apache
# 1º vhost do *:80 → recebe TODA requisição que não casar com nenhum ServerName
# (acesso por IP direto, nomes desconhecidos apontados ao servidor, etc.)
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs"          # painel padrão do XAMPP
</VirtualHost>

# 2º vhost — só atende quando Host: desingpartten.local
<VirtualHost *:80>
    ServerName desingpartten.local
    DocumentRoot "C:/xampp/htdocs/Studying_Design-Patterns/public"
</VirtualHost>

# 3º vhost — outro projeto, mesma porta
<VirtualHost *:80>
    ServerName outro-projeto.local
    DocumentRoot "C:/xampp/htdocs/outro-projeto/public"
</VirtualHost>
```

*Se o bloco do `localhost` não existisse, quem acessasse `http://127.0.0.1` cairia no `desingpartten.local` — sintoma clássico de "o site errado abriu": não é bug, é a regra do primeiro vhost.*

---

## Parte IV — Diagnóstico

### 4.1 `httpd -S` — o mapa dos vhosts

```
VirtualHost configuration:
*:80    is a NameVirtualHost
        default server localhost (C:/xampp/apache/conf/extra/httpd-vhosts.conf:1)
        port 80 namevhost localhost (…httpd-vhosts.conf:1)
        port 80 namevhost desingpartten.local (…httpd-vhosts.conf:7)
                alias www.desingpartten.local
```

Leitura: `default server` = quem pega o tráfego não casado; cada `namevhost` mostra **arquivo:linha** de origem — confirmação imediata de que o `Include` funcionou e de qual bloco atende qual nome.

### 4.2 `httpd -t` — validação prévia

Retorna `Syntax OK` ou o arquivo/linha do erro. **Sempre** antes de reiniciar: diferentemente do `.htaccess` (avaliado em runtime), um erro no vhost impede o servidor inteiro de subir.

### 4.3 Erros comuns

| Sintoma | Causa provável | Correção |
|---|---|---|
| Aviso `AH00558: Could not reliably determine the server's fully qualified domain name` | `ServerName` ausente no global | Definir `ServerName localhost:80` no `httpd.conf` |
| Site errado atende o domínio | Ordem dos vhosts (primeiro = default) ou `ServerName` errado/duplicado | Verificar com `httpd -S`; hostnames duplicados: o primeiro vence, os demais são ignorados com aviso |
| Vhost simplesmente ignorado | `Include conf/extra/httpd-vhosts.conf` ainda comentado no `httpd.conf` | Descomentar e reiniciar; confirmar no `httpd -S` |
| Navegador não resolve o `.local` | Falta a entrada no `hosts` do Windows | Adicionar `127.0.0.1 nome.local` (editor como Administrador) |
| 403 Forbidden no vhost novo | `DocumentRoot` sem `<Directory>` com `Require all granted` (política restritiva global prevalece) | Adicionar o bloco `<Directory>` correspondente |
| Rewrite "não funciona" após migrar do .htaccess | `RewriteBase` residual, barra inicial no padrão, ou `RewriteEngine On` faltando no novo contexto | Revisar §1.4 e §2.9 |
| Mudança no vhost sem efeito | Apache não reiniciado (vhost só é lido na inicialização) | `httpd -t` → reiniciar |

### 4.4 Notas 2.2 → 2.4 (recapitulação)

- `NameVirtualHost`: **removida do fluxo** — apague-a de configs herdadas.
- `Order` / `Allow` / `Deny` / `Satisfy`: substituídas por `Require` (`Require all granted` / `Require all denied` / `Require ip …`). Misturar as duas gerações no mesmo escopo produz o erro `AH01797` — elimine as legadas.

---

## Apêndice — Checklist de mudança em vhost

1. Editar o `httpd-vhosts.conf`.
2. `httpd -t` → exigir `Syntax OK`.
3. `httpd -S` → confirmar vhost/arquivo/linha/default esperados.
4. Reiniciar o Apache.
5. Testar com `curl -I http://nome.local/rota` (imune a cache de navegador).
6. Em caso de anomalia: ler o **ErrorLog do vhost** (não o global) — e, para rewrite, ativar `LogLevel warn rewrite:trace3` no vhost, reproduzir, ler, **desligar**.

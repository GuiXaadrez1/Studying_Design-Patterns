# Guia de Referência Técnica: Diretivas do httpd.conf do Apache HTTP Server 2.4

## TL;DR
- Este guia cobre as diretivas centrais do `httpd.conf` do Apache 2.4 em formato consistente (função, contexto, valor padrão, exemplo), com foco em VirtualHosts, DocumentRoot, AllowOverride, Require e mod_rewrite — as áreas que mais confundem desenvolvedores vindos de tutoriais 2.2.
- A mudança mais importante do 2.2 para o 2.4 é o controle de acesso: `Order/Allow/Deny` (mod_access_compat, legado) foi substituído por `Require` (mod_authz_core); use `Require all granted` / `Require all denied`. Além disso, `AllowOverride` agora tem padrão `None` (era `All` no 2.2), o que quebra `.htaccess` silenciosamente se não for ajustado.
- No Windows/XAMPP o MPM é o `mpm_winnt` (um processo de controle que lança um único processo filho com múltiplas threads, capacidade regida por `ThreadsPerChild`), os caminhos ficam em `C:\xampp\apache\conf\httpd.conf` e `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, e todos os caminhos devem usar barras normais (`/`). Sempre valide com `httpd -t` e `httpd -S`.

## Key Findings
- **Controle de acesso mudou de paradigma.** Em 2.4, `Require` (mod_authz_core/mod_authz_host) substitui `Order`, `Allow`, `Deny` e `Satisfy`. Misturar sintaxes é tecnicamente possível (via mod_access_compat) mas desencorajado e produz resultados contraintuitivos: conforme a documentação oficial de upgrade, "mod_access_compat directives take precedence over the mod_authz_host one in this configuration merge scenario", gerando o erro `AH01797: client denied by server configuration` mesmo quando um `Require` "parece" liberar o acesso.
- **AllowOverride passou a ser `None` por padrão.** A documentação oficial (guia de upgrade) afirma: "AllowOverride now defaults to None." Consequência (core.html): "When this directive is set to None and AllowOverrideList is set to None, .htaccess files are completely ignored. In this case, the server will not even attempt to read .htaccess files in the filesystem." Se você não definir `AllowOverride` no `<Directory>` correto, seus `.htaccess` são ignorados por completo.
- **NameVirtualHost foi descontinuada.** Em 2.4 ela não é mais necessária nem tem efeito; o simples fato de existirem múltiplos `<VirtualHost>` no mesmo par IP:porta ativa o virtual hosting baseado em nome.
- **O log de reescrita mudou.** `RewriteLog` e `RewriteLogLevel` foram removidos no 2.4. Para depurar reescrita usa-se `LogLevel alert rewrite:trace3` (níveis `trace1`–`trace8`).
- **A ordem de merge é definida e documentada.** `<Directory>` (e `.htaccess`) → `<DirectoryMatch>`/`<Directory>` regex → `<Files>`/`<FilesMatch>` → `<Location>`/`<LocationMatch>`, com `<Directory>` do mais curto para o mais longo, e seções dentro de `<VirtualHost>` aplicadas depois das globais.
- **No Windows a concorrência é regida só por `ThreadsPerChild`.** A doc do `mpm_winnt` diz: "It uses a single control process which launches a single child process which in turn creates threads to handle requests. Capacity is configured using the ThreadsPerChild directive, which sets the maximum number of concurrent client connections." `MaxRequestWorkers` não se aplica no Windows.

## Details

### Como usar este guia
Cada diretiva é descrita em cinco campos: **(a) nome**, **(b) função técnica**, **(c) contexto** (server config, virtual host, directory, .htaccess), **(d) valor padrão** e **(e) exemplo**. Os contextos vêm diretamente da documentação oficial (`httpd.apache.org/docs/2.4/`), que é a fonte primária deste material. Onde relevante, há notas de diferença 2.2→2.4 e observações de Windows/XAMPP.

Convenção fundamental (válida para todo o arquivo): **use sempre barras normais `/` nos caminhos, mesmo no Windows.** `DocumentRoot "C:/xampp/htdocs/site"` é correto; barras invertidas devem ser evitadas.

---

### Capítulo 1 — Identidade e ambiente do servidor

#### ServerRoot
- **Função:** Diretório-base onde ficam os arquivos de configuração e (por convenção) subpastas como `logs/` e `modules/`. Caminhos relativos em outras diretivas são resolvidos em relação a ela.
- **Contexto:** server config.
- **Padrão:** `/usr/local/apache2` (compilado). No XAMPP Windows, tipicamente `"C:/xampp/apache"`.
- **Exemplo:**
  ```apache
  ServerRoot "C:/xampp/apache"
  ```

#### ServerName
- **Função:** Define o nome e (opcionalmente) a porta que o servidor usa para se identificar, essencial para URLs autorreferenciais (redirecionamentos) e para o casamento de VirtualHosts baseados em nome.
- **Contexto:** server config, virtual host.
- **Padrão:** nenhum; se omitido, o servidor tenta derivar um FQDN do hostname do sistema (comportamento desencorajado, gera o aviso AH00558).
- **Exemplo:**
  ```apache
  ServerName desingpartten.local:80
  ```

#### ServerAdmin
- **Função:** E-mail do administrador exibido em algumas páginas de erro geradas pelo servidor.
- **Contexto:** server config, virtual host.
- **Padrão:** nenhum valor útil (placeholder de instalação).
- **Exemplo:**
  ```apache
  ServerAdmin webmaster@desingpartten.local
  ```

#### ServerTokens
- **Função:** Controla o quanto o cabeçalho HTTP de resposta `Server` revela (versão do Apache, SO, módulos).
- **Contexto:** server config.
- **Padrão:** `Full` (revela o máximo, ex.: `Apache/2.4.58 (Win64) PHP/8.2.12`).
- **Diferença/segurança:** para reduzir superfície de ataque, use `ServerTokens Prod` (retorna apenas `Apache`).
- **Exemplo:**
  ```apache
  ServerTokens Prod
  ```

#### ServerSignature
- **Função:** Controla a linha de rodapé com identificação do servidor em documentos gerados por ele (páginas de erro, listagens de diretório).
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `Off`.
- **Exemplo:**
  ```apache
  ServerSignature Off
  ```

#### Listen
- **Função:** Define o endereço IP e/ou porta TCP em que o servidor aceita conexões. Obrigatória no 2.4 (não há porta compilada por padrão).
- **Contexto:** server config.
- **Padrão:** nenhum (precisa ser especificada).
- **Exemplo:**
  ```apache
  Listen 80
  Listen 443
  ```

#### PidFile
- **Função:** Arquivo onde o processo de controle grava seu PID.
- **Contexto:** server config.
- **Padrão:** `logs/httpd.pid` (relativo a ServerRoot), variando por plataforma/pacote.
- **Exemplo:**
  ```apache
  PidFile "logs/httpd.pid"
  ```

---

### Capítulo 2 — Módulos

#### LoadModule
- **Função:** Vincula (carrega dinamicamente) um módulo compartilhado e o adiciona à lista de módulos ativos. Fornecida por `mod_so`.
- **Contexto:** server config.
- **Padrão:** nenhum.
- **Exemplo (Windows/XAMPP):**
  ```apache
  LoadModule rewrite_module modules/mod_rewrite.so
  ```
  Observação: no XAMPP, ativar mod_rewrite é apenas remover o `#` desta linha.

#### `<IfModule>`
- **Função:** Container condicional avaliado na inicialização; aplica as diretivas internas apenas se o módulo indicado estiver presente (ou ausente, com `!`). O argumento pode ser o identificador (`rewrite_module`) ou o nome do arquivo (`mod_rewrite.c`).
- **Contexto:** todos.
- **Padrão:** não aplicável.
- **Cuidado:** não use `<IfModule>` para envolver diretivas que você *quer* que sempre funcionem — ele mascara erros de módulo ausente.
- **Exemplo:**
  ```apache
  <IfModule mpm_winnt_module>
      ThreadsPerChild 150
      MaxConnectionsPerChild 0
  </IfModule>
  ```

---

### Capítulo 3 — Documentos e diretórios

#### DocumentRoot
- **Função:** Diretório raiz a partir do qual os arquivos são servidos; o caminho da URL é anexado a ele para localizar o arquivo no disco.
- **Contexto:** server config, virtual host.
- **Padrão:** `/usr/local/apache2/htdocs` (compilado). No XAMPP, `"C:/xampp/htdocs"`.
- **Cuidado:** não coloque barra final; combine sempre com um `<Directory>` correspondente concedendo acesso.
- **Exemplo:**
  ```apache
  DocumentRoot "C:/xampp/htdocs/desingpartten"
  ```

#### `<Directory>`
- **Função:** Aplica diretivas a um diretório do sistema de arquivos e a todos os seus subdiretórios. É onde se define `Options`, `AllowOverride` e `Require`.
- **Contexto:** server config, virtual host.
- **Padrão:** não aplicável.
- **Nota:** `AllowOverride`, `FollowSymLinks` e `SymLinksIfOwnerMatch` só fazem sentido em `<Directory>` (não em `<Location>`).
- **Exemplo:**
  ```apache
  <Directory "C:/xampp/htdocs/desingpartten">
      Options Indexes FollowSymLinks
      AllowOverride All
      Require all granted
  </Directory>
  ```

#### DirectoryIndex
- **Função:** Lista de arquivos a servir quando o cliente pede um diretório (URL terminada em `/`). Fornecida por `mod_dir`.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `index.html`.
- **Exemplo:**
  ```apache
  DirectoryIndex index.php index.html
  ```

#### Alias
- **Função:** Mapeia uma URL para um caminho do sistema de arquivos fora do DocumentRoot. Fornecida por `mod_alias`.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum.
- **Exemplo:**
  ```apache
  Alias "/imagens" "C:/xampp/dados/imagens"
  ```

#### ScriptAlias
- **Função:** Como `Alias`, mas marca o diretório de destino como contendo scripts CGI executados pelo handler `cgi-script`. Por segurança, evite colocar scripts CGI dentro do DocumentRoot.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum.
- **Exemplo:**
  ```apache
  ScriptAlias "/cgi-bin/" "C:/xampp/cgi-bin/"
  ```

#### Options
- **Função:** Controla recursos disponíveis num diretório: `Indexes` (listagem automática), `FollowSymLinks`, `ExecCGI`, `Includes`, `MultiViews`.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `FollowSymLinks` (efetivo na configuração distribuída).
- **Cuidado:** sem prefixo `+`/`-`, um `Options` substitui todo o conjunto herdado; com `+`/`-` ele ajusta incrementalmente. `MultiViews` nunca é dado por `Options All` — precisa ser nomeado explicitamente.
- **Exemplo:**
  ```apache
  Options +Indexes +FollowSymLinks
  ```

#### AllowOverride
- **Função:** Define quais classes de diretivas podem ser sobrescritas por arquivos `.htaccess` naquele diretório (`All`, `None`, `AuthConfig`, `FileInfo`, `Indexes`, `Limit`, `Options`).
- **Contexto:** directory (somente, sem regex).
- **Padrão:** `None` (mudou de `All` no 2.2 — a doc oficial afirma "AllowOverride now defaults to None").
- **Cuidado crítico:** com `None`, "the server will not even attempt to read .htaccess files". As diretivas de mod_rewrite (`RewriteEngine`, `RewriteRule` etc.) em `.htaccess` exigem `AllowOverride FileInfo` (ou `All`).
- **Exemplo:**
  ```apache
  AllowOverride All
  ```

#### Require
- **Função:** Diretiva de autorização do 2.4 (mod_authz_core). Concede/nega acesso por host, IP, usuário ou regra. Substitui `Order/Allow/Deny`.
- **Contexto:** directory, .htaccess (e dentro de `<Files>`, `<Location>` etc.).
- **Padrão:** nenhum (sem `Require` que conceda, o acesso é negado nas configurações modernas).
- **Providers comuns (mod_authz_core/host):** `Require all granted`, `Require all denied`, `Require local`, `Require ip 192.168.1`, `Require host example.org`, `Require valid-user`.
- **Combinadores:** `<RequireAll>`, `<RequireAny>`, `<RequireNone>`.
- **Exemplo (equivalências 2.2 → 2.4):**
  ```apache
  # 2.2:  Order allow,deny / Allow from all
  Require all granted
  # 2.2:  Order deny,allow / Deny from all
  Require all denied
  ```

---

### Capítulo 4 — VirtualHosts

#### `<VirtualHost>`
- **Função:** Container que agrupa diretivas para um host virtual específico, casadas por par IP:porta e depois por `ServerName`/`ServerAlias`. Diretivas dentro dele são aplicadas *depois* das globais, permitindo override.
- **Contexto:** server config.
- **Padrão:** não aplicável.
- **Nota 2.4:** apenas UM `<VirtualHost>` é selecionado por requisição — diretivas de vhosts diferentes nunca são mescladas. O primeiro `<VirtualHost>` listado para um par IP:porta é o host padrão daquele par.
- **Exemplo (XAMPP, em `conf/extra/httpd-vhosts.conf`):**
  ```apache
  <VirtualHost *:80>
      ServerName desingpartten.local
      ServerAlias www.desingpartten.local
      DocumentRoot "C:/xampp/htdocs/desingpartten"
      <Directory "C:/xampp/htdocs/desingpartten">
          Options Indexes FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>
      ErrorLog "logs/desingpartten-error.log"
      CustomLog "logs/desingpartten-access.log" combined
  </VirtualHost>
  ```
  Não esqueça de mapear `desingpartten.local` para `127.0.0.1` no arquivo `C:\Windows\System32\drivers\etc\hosts`.

#### ServerName (em vhost)
- **Função:** Nome canônico do host virtual usado no casamento por nome.
- **Contexto:** virtual host.
- **Padrão:** FQDN derivado do sistema (desencorajado).
- **Exemplo:** `ServerName desingpartten.local`

#### ServerAlias
- **Função:** Nomes alternativos (incluindo curingas) que também casam com aquele `<VirtualHost>`.
- **Contexto:** virtual host.
- **Padrão:** nenhum.
- **Exemplo:**
  ```apache
  ServerAlias desingpartten.local *.desingpartten.local
  ```

#### NameVirtualHost (descontinuada)
- **Função (histórica):** No 2.2, declarava qual IP:porta receberia vhosts por nome.
- **Status 2.4:** **não é mais necessária e não tem efeito.** Mantida apenas para não quebrar configs antigas. Tutoriais que insistem nela são pré-2.4.
- **Ação:** remova-a; basta ter múltiplos `<VirtualHost *:80>`.

---

### Capítulo 5 — Logs

#### ErrorLog
- **Função:** Arquivo onde o servidor registra erros.
- **Contexto:** server config, virtual host.
- **Padrão:** `logs/error_log` (Unix), `logs/error.log` (Windows/OS2).
- **Exemplo:** `ErrorLog "logs/error.log"`

#### LogLevel
- **Função:** Define a severidade mínima registrada no ErrorLog; no 2.4 pode ser ajustada **por módulo**.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** `warn`.
- **Nota:** mensagens "File does not exist" (404) ficam em nível `info`; para vê-las use `LogLevel warn core:info`.
- **Exemplo:** `LogLevel warn`

#### LogFormat
- **Função:** Define um formato de log nomeado (apelido) usando diretivas `%`. Fornecida por `mod_log_config`.
- **Contexto:** server config, virtual host.
- **Padrão:** Common Log Format (`"%h %l %u %t \"%r\" %>s %b"`) se nada for especificado.
- **Exemplo:**
  ```apache
  LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
  ```

#### CustomLog
- **Função:** Cria um log de acesso associando arquivo + formato (apelido ou string) em um passo.
- **Contexto:** server config, virtual host.
- **Padrão:** nenhum.
- **Exemplo:** `CustomLog "logs/access.log" combined`

#### TransferLog
- **Função:** Cria um log de acesso usando o formato definido pelo `LogFormat` anterior (sem apelido explícito).
- **Contexto:** server config, virtual host.
- **Padrão:** Common Log Format.
- **Exemplo:**
  ```apache
  LogFormat "%h %l %u %t \"%r\" %>s %b"
  TransferLog "logs/access.log"
  ```

---

### Capítulo 6 — Performance e processos (MPMs)

O modelo de processos do Apache é escolhido por um MPM (Multi-Processing Module). No **Windows** o MPM padrão é o `mpm_winnt`; no **Linux** os principais são `prefork`, `worker` e `event`.

#### Timeout
- **Função:** Segundos que o servidor espera por certos eventos de E/S antes de encerrar a conexão.
- **Contexto:** server config, virtual host.
- **Padrão:** `60` (mudou de `300` no 2.2).
- **Exemplo:** `Timeout 60`

#### KeepAlive
- **Função:** Habilita conexões persistentes (várias requisições por conexão TCP).
- **Contexto:** server config, virtual host.
- **Padrão:** `On`. No 2.4 só aceita `On`/`Off`.
- **Exemplo:** `KeepAlive On`

#### MaxKeepAliveRequests
- **Função:** Máximo de requisições por conexão persistente; `0` = ilimitado.
- **Contexto:** server config, virtual host.
- **Padrão:** `100`.
- **Exemplo:** `MaxKeepAliveRequests 100`

#### KeepAliveTimeout
- **Função:** Segundos que o servidor aguarda a próxima requisição na mesma conexão antes de fechá-la.
- **Contexto:** server config, virtual host.
- **Padrão:** `5`.
- **Exemplo:** `KeepAliveTimeout 5`

#### ThreadsPerChild
- **Função:** Número de threads que cada processo filho cria. No `mpm_winnt` define o número máximo de conexões concorrentes (é a diretiva de capacidade principal no Windows).
- **Contexto:** server config (MPM).
- **Padrão:** conforme a doc oficial (mpm_common), "The default value for ThreadsPerChild is 64 when used with mpm_winnt and 25 when used with the others." O template de configuração distribuído no Windows/XAMPP costuma trazer `150`.
- **Exemplo:**
  ```apache
  <IfModule mpm_winnt_module>
      ThreadsPerChild 150
      MaxConnectionsPerChild 0
  </IfModule>
  ```

#### MaxConnectionsPerChild
- **Função:** Número de conexões que um processo filho atende antes de ser reciclado (`0` = sem limite). Nome antigo (pré-2.3.9): `MaxRequestsPerChild` — ainda aceito.
- **Contexto:** server config (MPM).
- **Padrão:** `0`.
- **Exemplo:** `MaxConnectionsPerChild 0`

#### MaxRequestWorkers
- **Função:** Limite de requisições simultâneas atendidas (nome antigo: `MaxClients`, pré-2.3.13).
- **Contexto:** server config (MPM).
- **Padrão:** `256` no prefork; `400` (16 × 25) no worker/event.
- **Nota Windows:** **não se aplica ao `mpm_winnt`**, que usa um único processo filho; a capacidade no Windows é dada só por `ThreadsPerChild`.
- **Exemplo (Linux):** `MaxRequestWorkers 400`

#### MPMs Unix (prefork / worker / event)
- **prefork:** processos sem threads, um por conexão — máxima compatibilidade (ex.: mod_php não thread-safe), maior uso de memória.
- **worker:** híbrido processos+threads.
- **event:** como worker, mas gerencia conexões keep-alive em thread dedicada — melhor para alta concorrência (padrão nas distribuições modernas).

---

### Capítulo 7 — Segurança

- **ServerTokens Prod** e **ServerSignature Off** — reduzem exposição da versão (ver Cap. 1).
- **TraceEnable**
  - **Função:** Habilita/desabilita o método HTTP TRACE (mitiga Cross-Site Tracing).
  - **Contexto:** server config, virtual host.
  - **Padrão:** `On`. `TraceEnable Off` faz o servidor recusar o TRACE.
  - **Exemplo:** `TraceEnable Off`
- **FileETag**
  - **Função:** Define quais atributos do arquivo compõem o cabeçalho `ETag` de arquivos estáticos.
  - **Contexto:** server config, virtual host, directory, .htaccess.
  - **Padrão:** `MTime Size`. A doc oficial de upgrade confirma: "FileETag now defaults to \"MTime Size\" (without INode)" — o inode era incluído por padrão até a versão 2.3.14. Remover o INode evita vazar número de inode e problemas atrás de balanceadores.
  - **Exemplo:** `FileETag MTime Size`
- **Bloqueio da raiz do sistema de arquivos** — padrão defensivo recomendado:
  ```apache
  <Directory "/">
      AllowOverride None
      Require all denied
  </Directory>
  ```

---

### Capítulo 8 — Tipos MIME e handlers

#### TypesConfig
- **Função:** Aponta o arquivo que mapeia extensões para media types (o `mime.types`). Fornecida por `mod_mime`.
- **Contexto:** server config.
- **Padrão:** `conf/mime.types`.
- **Exemplo:** `TypesConfig "conf/mime.types"`

#### AddType
- **Função:** Adiciona um mapeamento extensão → media type (Content-Type), sobrepondo o `mime.types`.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** nenhum.
- **Exemplo:** `AddType application/json .json`

#### AddHandler
- **Função:** Associa uma extensão a um handler nomeado (ex.: processar CGI).
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** nenhum.
- **Exemplo:** `AddHandler cgi-script .cgi`

#### SetHandler
- **Função:** Força *todos* os arquivos casados por um container (`<Location>`, `<Directory>`, `<Files>`) a usarem um handler, independentemente de extensão. É uma diretiva `core` que sobrepõe mapeamentos de extensão do mod_mime.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** nenhum.
- **Exemplo (moderno, PHP via FilesMatch):**
  ```apache
  <FilesMatch "\.php$">
      SetHandler application/x-httpd-php
  </FilesMatch>
  ```
  Observação: em builds modernos evita-se `AddType application/x-httpd-php .php`; o casamento por `FilesMatch` + `SetHandler` é mais seguro (evita executar `arquivo.php.jpg`).

---

### Capítulo 9 — Includes e organização

#### Include
- **Função:** Insere outros arquivos de configuração no ponto da diretiva; aceita curingas. Falha se o padrão não casar nenhum arquivo.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum.
- **Exemplo:** `Include "conf/extra/httpd-vhosts.conf"`

#### IncludeOptional
- **Função:** Igual a `Include`, mas **não falha** se o curinga não casar nenhum arquivo — ideal para diretórios `sites-enabled/` que podem estar vazios.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** nenhum.
- **Exemplo:** `IncludeOptional "conf/sites/*.conf"`

---

### Capítulo 10 — mod_rewrite no contexto global

#### RewriteEngine
- **Função:** Liga/desliga o motor de reescrita no contexto atual. A configuração de rewrite **não é herdada** automaticamente por vhosts/diretórios filhos — normalmente é preciso repetir `RewriteEngine On` em cada contexto.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `Off`.
- **Exemplo:**
  ```apache
  <IfModule rewrite_module>
      RewriteEngine On
      RewriteCond %{REQUEST_FILENAME} !-f
      RewriteRule ^(.*)$ index.php [L]
  </IfModule>
  ```

#### LogLevel para depurar reescrita (`rewrite:traceN`)
- **Função:** No 2.4, `RewriteLog`/`RewriteLogLevel` foram **removidos**. A depuração usa o log por módulo: `LogLevel [nível-base] rewrite:traceN`, com `N` de 1 a 8.
- **Contexto:** server config, virtual host, directory.
- **Padrão:** herda o `LogLevel` global (`warn`); reescrita não é logada por padrão.
- **Cuidado:** níveis altos degradam o desempenho drasticamente — use `trace3` para diagnóstico e desligue depois. A doc adverte: não use acima de `trace2` em produção.
- **Exemplo:**
  ```apache
  LogLevel warn rewrite:trace3
  ```
  As linhas saem no ErrorLog marcadas com `[rewrite:...]`.

---

### Capítulo 11 — Codificação

#### AddDefaultCharset
- **Função:** Adiciona um parâmetro de charset ao `Content-Type` de respostas `text/plain` e `text/html` que não o declaram. Ajuda a mitigar XSS.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** `Off`. `AddDefaultCharset On` usa `iso-8859-1`; qualquer outro valor é o charset a usar.
- **Nota:** avaliada em tempo de configuração, não em runtime — não pode ser condicionada por `<If>`.
- **Exemplo:** `AddDefaultCharset UTF-8`

---

### Capítulo 12 — Páginas de erro customizadas

#### ErrorDocument
- **Função:** Define a resposta para um código de status: mensagem literal, caminho local ou URL externa. A partir do 2.4.13, aceita sintaxe de expressão.
- **Contexto:** server config, virtual host, directory, .htaccess.
- **Padrão:** mensagens internas do servidor.
- **Cuidado:** `ErrorDocument 401` deve apontar para documento **local**; URLs remotas fazem o cliente receber um redirect (não o código de erro original).
- **Exemplo:**
  ```apache
  ErrorDocument 404 /erros/404.html
  ErrorDocument 500 "Erro interno. Tente novamente mais tarde."
  ```

---

### Capítulo 13 — Ordem de processamento e mesclagem (merging order)

O Apache combina seções numa ordem definida. Entender isso resolve a maioria dos "por que meu `.htaccess`/`Require` não teve efeito".

Ordem oficial de merge:
1. `<Directory>` (exceto regex) **e** `.htaccess` simultaneamente — com o `.htaccess`, se permitido, sobrepondo o `<Directory>`.
2. `<DirectoryMatch>` e `<Directory>` com expressões regulares.
3. `<Files>` e `<FilesMatch>` simultaneamente.
4. `<Location>` e `<LocationMatch>` simultaneamente.

Regras adicionais:
- Fora de `<Directory>`, cada grupo é processado na ordem em que aparece no arquivo.
- `<Directory>` é processado do **componente de diretório mais curto para o mais longo** (ex.: `/var/web` antes de `/var/web/sub`), intercalado com os `.htaccess` correspondentes.
- Expressões regulares só são avaliadas depois das seções normais.
- Configurações trazidas por `Include` são tratadas como se estivessem no ponto do `Include`.
- Seções dentro de `<VirtualHost>` são aplicadas **depois** das seções correspondentes fora do vhost — por isso o vhost sobrepõe o servidor principal.
- Apenas UM `<VirtualHost>` é escolhido por requisição (vhosts não são mesclados entre si).

**Armadilha clássica 2.2→2.4:** misturar `Order/Deny` (mod_access_compat) com `Require` (mod_authz_host) leva a resultados inesperados. A doc oficial explica: "Because mod_access_compat directives take precedence over the mod_authz_host one in this configuration merge scenario" — daí o erro `AH01797: client denied by server configuration` mesmo quando o `Require local` "parece" liberar o acesso. Solução: elimine as diretivas legadas do escopo.

---

### Capítulo 14 — Boas práticas de organização e teste

**Organização do `httpd.conf`:**
- Mantenha o núcleo (`ServerRoot`, `Listen`, `LoadModule`, MPM) no `httpd.conf` e delegue vhosts a `conf/extra/httpd-vhosts.conf` via `Include`.
- Um `<VirtualHost>` por site, cada um com seu próprio `ErrorLog`/`CustomLog` — facilita confirmar qual vhost atendeu a requisição.
- Prefira `IncludeOptional conf/sites/*.conf` para modularizar sem quebrar quando o diretório estiver vazio.
- Bloqueie a raiz (`<Directory "/"> Require all denied </Directory>`) e libere só o necessário.
- Ajuste `AllowOverride` no `<Directory>` específico do site, nunca em `<Directory "/">`.

**Teste da configuração:**
- **Sintaxe:** `httpd -t` (ou `apachectl configtest` / `apachectl -t`). Retorna `Syntax OK` ou o arquivo e a linha do erro. No XAMPP: `C:\xampp\apache\bin\httpd.exe -t`.
- **VirtualHosts:** `httpd -S` lista todos os vhosts, o par IP:porta, o arquivo e a linha de cada definição, o DocumentRoot principal e o ErrorLog — indispensável para depurar casamento de vhost.
- **Módulos carregados:** `httpd -M` lista os módulos ativos (ex.: confirmar `rewrite_module (shared)`).
- **Fluxo recomendado:** edite → `httpd -t` → `httpd -S` → reinicie o serviço → verifique o ErrorLog.
- **Windows:** após alterar a configuração, reinicie via Painel de Controle do XAMPP ou `httpd -k restart`. Lembre que o `mpm_winnt` relê a configuração num novo processo filho; **não altere a config no meio de um restart**, pois uma config parcialmente inválida pode impedir o filho substituto de subir e derrubar o servidor.

## Recommendations
1. **Ao migrar/copiar configs de tutoriais antigos, troque imediatamente todo `Order/Allow/Deny` por `Require`.** Use `Require all granted`/`Require all denied`/`Require local`. Se aparecer `AH01797` no ErrorLog com um `Require` que "deveria" liberar, procure diretivas `Order/Deny` residuais no mesmo escopo — elas têm precedência e devem ser removidas.
2. **Para fazer `.htaccess` e mod_rewrite funcionarem, defina `AllowOverride All` (ou ao menos `FileInfo`) no `<Directory>` exato do seu DocumentRoot** — não no `<Directory "/">`. Se o `.htaccess` "não faz nada", esta é a causa nº 1 no 2.4 (o padrão passou a ser `None`, e com `None` o servidor sequer lê o arquivo).
3. **Estruture o VirtualHost do `desingpartten.local` exatamente como no exemplo do Cap. 4**, com `ServerName`, bloco `<Directory>` correspondente, e entrada no arquivo `hosts` do Windows apontando para `127.0.0.1`. Rode `httpd -S` para confirmar que o vhost aparece com o arquivo/linha corretos.
4. **Para depurar reescrita, ligue `LogLevel warn rewrite:trace3` temporariamente** (de preferência dentro do `<VirtualHost>` do site, não global), reproduza o problema, leia as linhas `[rewrite:...]` no ErrorLog e **desligue** ao terminar.
5. **Endureça a segurança básica:** `ServerTokens Prod`, `ServerSignature Off`, `TraceEnable Off`, `FileETag MTime Size` e bloqueio da raiz do FS. Em produção real, considere HTTPS (`Listen 443` + mod_ssl) e `AllowOverride None` com regras diretas no `httpd.conf` (mais rápido que `.htaccess`, pois evita leitura por requisição).
6. **Sempre valide antes de reiniciar:** `httpd -t` seguido de `httpd -S`. Automatize esse par como passo obrigatório de qualquer mudança.

**Limiares que mudam a recomendação:** se você migrar do XAMPP (Windows/`mpm_winnt`) para produção Linux, reavalie o MPM (`event` é o alvo), troque `ThreadsPerChild` por `MaxRequestWorkers`/`ServerLimit`, e reveja `KeepAliveTimeout` para baixo em servidores muito carregados. Se o mod_php não for thread-safe, use `prefork` ou PHP-FPM.

## Caveats
- **Valores padrão dependem de compilação e empacotamento.** Padrões como `ServerRoot`, `PidFile` e caminhos de log variam entre a build oficial, o XAMPP e distribuições Linux. Os padrões citados são os documentados pelo Apache; confirme no seu ambiente com `httpd -S`/`httpd -V`.
- **O bloco `mpm_winnt` com `ThreadsPerChild 150` e `MaxConnectionsPerChild 0` reflete o template padrão das builds Windows (winlibs/Apache Lounge, base do XAMPP)**, não uma leitura byte-a-byte do seu `httpd.conf` 2.4.58 específico — verifique o arquivo local, pois o bloco pode estar em `conf/extra/httpd-mpm.conf` incluído via `Include`. O padrão *compilado* do Apache para `ThreadsPerChild` no `mpm_winnt` é 64.
- Este guia cobre as diretivas do núcleo e dos módulos mais usados no `httpd.conf` global; não é exaustivo quanto a todos os módulos (mod_ssl, mod_proxy, mod_deflate etc.), que têm suas próprias diretivas.
- Alguns exemplos de terceiros (blogs, fóruns) foram usados apenas para corroborar comportamento; em caso de dúvida, a documentação oficial `httpd.apache.org/docs/2.4/` prevalece.
- A sintaxe de expressão em `ErrorDocument`/`AddDefaultCharset` e detalhes finos de merge de `<If>` podem variar entre versões de correção do 2.4; teste no seu patch level (2.4.58).
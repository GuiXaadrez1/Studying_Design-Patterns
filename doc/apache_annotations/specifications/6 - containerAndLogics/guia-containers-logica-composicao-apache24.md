# Containers, Lógica e Composição no Apache 2.4 — Edição Expandida
## Configuração como Componentes: A Lente JSX/TypeScript, os Operadores ap_expr e a Verdade sobre a Ordem

*Guia prático da série de referência Apache 2.4. Este documento trata a configuração como você trataria uma árvore de componentes: **containers são componentes**, seus argumentos são **props**, o aninhamento tem **regras de composição**, existem **dois tempos de avaliação** (parse-time ≈ compile-time; request-time ≈ runtime), e a pergunta "a ordem importa?" tem uma resposta precisa — e dupla.*

---

## Capítulo 0 — O Modelo de Componentes

### 0.1 A analogia, calibrada

```tsx
// Se a configuração do Apache fosse TSX, um vhost seria algo assim:
<VirtualHost port="*:443">                      {/* componente raiz do site   */}
  <ServerName value="app.empresa.com.br" />     {/* diretivas = props/folhas  */}
  <Directory path="/var/www/app/public">        {/* componente de escopo      */}
    <RequireAny>                                 {/* componente de LÓGICA      */}
      <Require rule="ip 192.168.10" />
      <Require rule="valid-user" />
    </RequireAny>
  </Directory>
</VirtualHost>
```

A analogia é útil porque acerta quatro coisas:

| Conceito de componente | Equivalente Apache |
|---|---|
| **Componente com props** | Container com argumento: `<Directory "/caminho">`, `<VirtualHost *:443>` |
| **Children** | Diretivas e containers aninhados |
| **Regras de composição** (o que aninha onde) | A matriz de aninhamento (§1.2) |
| **Compile-time vs runtime** | Containers de **parse** (`<IfModule>`, `<IfDefine>`…) vs containers de **requisição** (`<If>`, `<Directory>`…) — §2.1 |
| **Componentes reutilizáveis** | `<Macro>` do mod_macro (§3.1) e templates via `Define`+`Include` |

E onde a analogia **quebra** (importante saber):

- Não há "render tree" única: para cada requisição o Apache **funde** (merge) os containers aplicáveis numa ordem **fixa por tipo** — não na ordem do arquivo (§2.3). É mais parecido com **cascata de CSS** (especificidade) do que com composição de JSX.
- Containers do mesmo tipo **não se sobrepõem por posição**, e sim por regras próprias (Directory: caminho mais curto → mais longo).

### 0.2 O catálogo completo — todos os "componentes" e sua natureza

| Container | Prop (argumento) | Tempo de avaliação | Papel |
|---|---|---|---|
| `<VirtualHost>` | IP:porta | Requisição (seleção) | Raiz do site |
| `<Directory>` / `<DirectoryMatch>` | caminho FS / regex | Requisição (merge) | Escopo de filesystem |
| `<Files>` / `<FilesMatch>` | nome / regex | Requisição (merge) | Escopo por nome de arquivo |
| `<Location>` / `<LocationMatch>` | URL-path / regex | Requisição (merge) | Escopo de webspace |
| `<Proxy>` / `<ProxyMatch>` | URL proxificada | Requisição (merge) | Escopo de backend |
| `<Limit>` / `<LimitExcept>` | métodos HTTP | Requisição | Escopo por método (cautela — §1.3) |
| `<If>` / `<ElseIf>` / `<Else>` | expressão ap_expr | **Requisição** (por request!) | Lógica de runtime |
| `<RequireAll>` / `<RequireAny>` / `<RequireNone>` | — | Requisição (authz) | Lógica booleana de autorização |
| `<IfModule>` | módulo | **Parse** (inicialização) | Compilação condicional |
| `<IfDefine>` | flag `-D`/`Define` | **Parse** | Compilação condicional |
| `<IfFile>` (2.4.34+) | existência de arquivo | **Parse** | Compilação condicional |
| `<IfDirective>` / `<IfSection>` (2.4.34+) | diretiva/seção disponível | **Parse** | Compilação condicional |
| `<Macro>` (mod_macro) | nome + parâmetros | **Parse** (expansão) | Componente reutilizável |

---

## NÍVEL BÁSICO

### 1.1 Props e variantes: literal, regex e a forma `~`

```apache
# prop literal (casamento exato/prefixo):
<Directory "/var/www/app">                 …</Directory>
<Location "/api">                          …</Location>
<Files "composer.json">                    …</Files>

# prop regex — duas grafias equivalentes:
<DirectoryMatch "/\.git">                  …</DirectoryMatch>
<Directory ~ "/\.git">                     …</Directory>       # forma "~", mesmo efeito

<FilesMatch "\.(env|bak)$">                …</FilesMatch>
<LocationMatch "^/(api|webhooks)/v[0-9]+"> …</LocationMatch>

# regex com GRUPOS NOMEADOS viram variáveis (2.4.29+) — "props que exportam":
<LocationMatch "^/cliente/(?<CLIENTEID>[0-9]+)">
    Header set X-Cliente "%{MATCH_CLIENTEID}e"
</LocationMatch>
```

### 1.2 A matriz de composição — o que aninha onde

Como em qualquer sistema de componentes, nem tudo compõe com tudo:

| Container pai ↓ / filho → | Directory | Files | Location | If | Require* | IfModule | Limit |
|---|---|---|---|---|---|---|---|
| **server config (raiz)** | ✔ | ✔ | ✔ | ✔ | (dentro de escopo) | ✔ | ✔ |
| **VirtualHost** | ✔ | ✔ | ✔ | ✔ | (dentro de escopo) | ✔ | ✔ |
| **Directory** | ✘¹ | ✔ | ✘ | ✔ | ✔ | ✔ | ✔ |
| **Files** | ✘ | ✘ | ✘ | ✔ | ✔ | ✔ | ✔ |
| **Location** | ✘ | ✘² | ✘ | ✔ | ✔ | ✔ | ✔ |
| **.htaccess** | ✘ | ✔ | ✘ | ✔ | ✔ | ✔ | ✔ |

*¹ `<Directory>` não aninha em `<Directory>` — a "descendência" é pelo caminho (um bloco para `/a` e outro para `/a/b` são IRMÃOS no arquivo, relacionados pelo merge). ² `<Files>` dentro de `<Location>` não é permitido.*

**Regra prática memorizável:** containers de ESCOPO (Directory/Files/Location) não se aninham entre si — eles se **combinam pelo merge**; containers de LÓGICA (`If`, `Require*`, `IfModule`, `Limit`) aninham em quase tudo, inclusive uns nos outros.

### 1.3 Os componentes de lógica booleana — RequireAll/Any/None

```apache
# O "AND / OR / NOT" da autorização (detalhado no guia de authz — aqui, a álgebra):

<RequireAny>            # OR: satisfeito se UM autorizar (default de Requires soltos)
<RequireAll>            # AND: nenhum pode negar E ao menos um deve autorizar
<RequireNone>           # NOT: nega se qualquer membro casar (nunca autoriza sozinho)

# composição arbitrária — (A ∨ B) ∧ (C ∨ D) ∧ ¬E:
<RequireAll>
    <RequireAny>
        Require ip 10.8.0                 # A: VPN
        Require ip 192.168.10             # B: escritório
    </RequireAny>
    <RequireAny>
        Require group ti                  # C
        Require user maria.silva          # D
    </RequireAny>
    <RequireNone>
        Require env bloqueado             # E
    </RequireNone>
</RequireAll>

# E o alerta de sempre sobre <Limit>: ele só cobre os métodos LISTADOS —
# métodos fora da lista ficam SEM a regra (alçapão). Prefira <LimitExcept>
# ou uma expressão: <If "%{REQUEST_METHOD} !in { 'GET','HEAD','POST' }">.
```

---

## NÍVEL INTERMEDIÁRIO

### 2.1 Os DOIS tempos de avaliação — o "compile-time vs runtime" do Apache

Esta é a distinção estrutural que a analogia TypeScript ilumina perfeitamente:

```
PARSE-TIME (inicialização/reload)          REQUEST-TIME (cada requisição)
≈ compile-time / #ifdef                    ≈ runtime / if()
─────────────────────────────────         ─────────────────────────────────
<IfModule>  módulo carregado?              <If "expr">  avaliada POR REQUISIÇÃO
<IfDefine>  flag -D/Define existe?         <Directory>/<Files>/<Location>
<IfFile>    arquivo existe no disco?         casados contra o alvo da requisição
<IfDirective>/<IfSection> disponível?      <RequireAll/Any/None> na fase authz
<Macro>     expandida textualmente         seleção de <VirtualHost>
Define/Include processados na leitura
```

Consequências práticas (onde a distinção morde):

```apache
# 1. <IfModule> NÃO reage a nada da requisição — decide UMA vez, no boot:
<IfModule mod_headers.c>          # "o binário tem headers? então compile isto"
    Header set X-A "1"
</IfModule>

# 2. <If> é o ÚNICO condicional de runtime genérico:
<If "%{HTTP_HOST} == 'empresa.com.br'">   # avaliado a CADA requisição
    Redirect permanent "/" "https://www.empresa.com.br/"
</If>
<ElseIf "%{HTTP_HOST} =~ /\.local$/">
    Header set X-Ambiente "dev"
</ElseIf>
<Else>
    Header set X-Ambiente "prod"
</Else>

# 3. <IfDefine> = "build profiles" (dev/prod pelo jeito de subir o servidor):
#    httpd -D DEV
<IfDefine DEV>
    LogLevel debug
    Define TTL "0"
</IfDefine>
<IfDefine !DEV>
    LogLevel warn
    Define TTL "86400"
</IfDefine>

# 4. <IfFile> (2.4.34+) — config que se adapta ao deploy:
<IfFile "/etc/letsencrypt/live/site/fullchain.pem">
    Include conf/sites/10-site-tls.conf
</IfFile>
# (sem o certificado ainda, o vhost TLS nem é lido — nada de boot quebrado)
```

### 2.2 A linguagem ap_expr — a referência de operadores

O `<If>`, o `Require expr`, o `expr=` de Header e o `SetEnvIfExpr` compartilham UMA linguagem. Domine-a uma vez, use em quatro lugares:

```apache
# ---------- COMPARAÇÃO ----------
# strings (aspas simples nos literais):
==   !=                       "%{REQUEST_METHOD} == 'POST'"
<    >    <=   >=             (lexicográficas em strings!)
# inteiros (família com hífen — NÃO confundir com as de string):
-eq  -ne  -lt  -le  -gt  -ge  "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"

# ---------- REGEX ----------
=~   !~                       "%{REQUEST_URI} =~ m#^/admin#"
#   delimitadores: /…/ ou m#…# (o # evita escapar barras de URL)
#   flags: =~ /padrao/i  (case-insensitive)
#   capturas do último match: $0..$9 → "%{HTTP_HOST} =~ /^([^.]+)\./ && $1 == 'api'"

# ---------- LÓGICOS ----------
&&   ||   !                   "!(-R '192.168.10.0/24') || %{HTTPS} != 'on'"
# parênteses agrupam normalmente

# ---------- PERTINÊNCIA ----------
in                            "%{REQUEST_METHOD} in { 'GET', 'HEAD' }"
-strmatch                     "%{REQUEST_URI} -strmatch '/api/*'"   # curinga simples

# ---------- UNÁRIOS DE AMBIENTE/ARQUIVO ----------
-f  arquivo existe            "-f '%{DOCUMENT_ROOT}/MAINT'"     # o modo manutenção!
-d  diretório existe          -s  arquivo não-vazio
-n  string não-vazia          -z  string vazia
-R  origem em CIDR            "-R '10.8.0.0/16'"
-T  "true" textual            (aceita 1/yes/on/true)

# ---------- FUNÇÕES ----------
req('Nome')      # request header      "req('X-Token') =~ /^tok-/"
resp('Nome')     # response header     "resp('Content-Type') =~ m#text/html#"
reqenv('VAR')    # variável de ambiente da requisição (as marcas do setenvif!)
osenv('VAR')     # ambiente do PROCESSO
tolower(s) toupper(s) escape(s) unescape(s) file(caminho) filesize(caminho)
md5(s) sha1(s) base64(s) unbase64(s)

# ---------- VARIÁVEIS (as mesmas do rewrite, com %{}) ----------
%{REQUEST_URI} %{QUERY_STRING} %{HTTP_HOST} %{HTTPS} %{REMOTE_ADDR}
%{REQUEST_METHOD} %{REQUEST_STATUS} %{TIME_HOUR} %{DOCUMENT_ROOT} …
%{HTTP:Nome-Do-Header}   %{ENV:VAR}   %{SSL:SSL_PROTOCOL}
```

**Os três erros de sintaxe que travam iniciantes:**

```apache
# ✘ comparar número com operador de string:
<If "%{TIME_HOUR} > 8">        # lexicográfico! "9" > "10" é VERDADEIRO
# ✔ <If "%{TIME_HOUR} -gt 8">

# ✘ literal sem aspas simples:
<If "%{REQUEST_METHOD} == POST">      # erro de parse
# ✔ <If "%{REQUEST_METHOD} == 'POST'">

# ✘ esquecer que o erro só aparece no LUGAR certo: em server/vhost o parse
#   quebra NA INICIALIZAÇÃO (httpd -t acusa); em .htaccess, quebra a REQUISIÇÃO (500).
```

### 2.3 A pergunta central: A ORDEM IMPORTA? — a resposta dupla

**SIM, no parse (a leitura do arquivo é sequencial, como um script):**

| O que | Por quê |
|---|---|
| `LoadModule` antes do uso | Diretiva de módulo não carregado = erro de parse |
| `Define` antes do `${VAR}` | Interpolação é textual, na leitura |
| Ordem dos `Include` | Conteúdo entra NO PONTO do Include — e `IncludeOptional conf/sites/*.conf` processa em ordem alfabética (por isso os prefixos `00-`, `10-`…) |
| **Primeiro `<VirtualHost>` do par IP:porta** | É o **default** do par — a regra de ouro do guia de vhosts |
| Regras do **mesmo módulo** no **mesmo escopo** | mod_alias: primeiro `Alias`/`Redirect` que casa vence. mod_rewrite: `RewriteRule` avaliadas de cima p/ baixo; `RewriteCond` gruda na regra IMEDIATAMENTE seguinte |
| `<Macro>` antes do `Use` | Expansão textual no parse |

**NÃO, no merge de requisição (a ordem entre TIPOS de container é FIXA, independente do arquivo):**

```
1. <Directory> literais (caminho mais CURTO → mais LONGO) + .htaccess de cada nível
2. <DirectoryMatch> / <Directory ~>
3. <Files> / <FilesMatch>
4. <Location> / <LocationMatch>
5. <If> / <ElseIf> / <Else>          ← SEMPRE por último — sobrepõe tudo
   (e: seções dentro de <VirtualHost> aplicam DEPOIS das globais equivalentes)
```

O experimento que prova os dois regimes de uma vez:

```apache
# no ARQUIVO, o <Location> vem ANTES do <Directory>…
<Location "/relatorios">
    Require all granted
</Location>
<Directory "/var/www/app/public/relatorios">
    Require all denied
</Directory>
# …e mesmo assim o acesso é LIBERADO: no merge, Location funde DEPOIS
# de Directory — a posição no arquivo foi irrelevante ENTRE TIPOS.

# Mas DENTRO do mesmo tipo, a posição volta a valer:
<Location "/api">      Header set X-Origem "regra-1" </Location>
<Location "/api/v2">   Header set X-Origem "regra-2" </Location>
# ambos casam /api/v2/usuarios; Locations fundem NA ORDEM DO ARQUIVO
# → a regra-2 (posterior) vence. Inverta os blocos e o resultado inverte.
```

**Síntese memorizável:** *a ordem no arquivo é a ordem do PARSE e desempata IRMÃOS do mesmo tipo; a ordem do MERGE entre tipos é uma constante do Apache — Directory → Files → Location → If — e nenhuma reorganização do arquivo a altera.*

---

## NÍVEL AVANÇADO

### 3.1 mod_macro — componentes de verdade, com props

```apache
# LoadModule macro_module modules/mod_macro.so   (no global)

# ---------- DEFINIÇÃO: um componente <VHostTLS> com 3 props ----------
<Macro VHostTLS $host $root $nome>
    <VirtualHost *:443>
        ServerName   $host
        DocumentRoot "$root/public"

        SSLEngine On
        SSLCertificateFile    "/etc/letsencrypt/live/$host/fullchain.pem"
        SSLCertificateKeyFile "/etc/letsencrypt/live/$host/privkey.pem"

        <Directory "$root/public">
            Options FollowSymLinks
            AllowOverride None
            Require all granted
            RewriteEngine On
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ index.php [QSA,L]
        </Directory>

        <IfModule mod_headers.c>
            Header always set X-Content-Type-Options "nosniff"
            Header always set X-Frame-Options "SAMEORIGIN"
        </IfModule>

        ErrorLog  "/var/log/httpd/$nome-error.log"
        CustomLog "/var/log/httpd/$nome-access.log" combined
    </VirtualHost>
</Macro>

# ---------- USO: "instanciar o componente" — três sites em três linhas ----------
Use VHostTLS www.empresa.com.br  /var/www/institucional  institucional
Use VHostTLS blog.empresa.com.br /var/www/blog           blog
Use VHostTLS loja.empresa.com.br /var/www/loja           loja

UndefMacro VHostTLS      # higiene: apaga a definição depois das instâncias
```

*Semântica: expansão TEXTUAL no parse (como um template, não uma função). `httpd -t` valida o resultado expandido; um erro dentro da macro aparece multiplicado por instância. Macros aninham (`Use` dentro de `<Macro>`) — dá para compor `VHostTLS` a partir de `BlocoSeguranca` + `BlocoFrontController`.*

### 3.2 Padrão de organização — a "árvore de componentes" do servidor

```
conf/
├── httpd.conf                    # bootstrap: Listen, LoadModule, políticas raiz
├── macros/
│   ├── 00-seguranca.conf         # <Macro BlocoSeguranca> (headers, TLS params)
│   ├── 10-vhost-tls.conf         # <Macro VHostTLS …> (usa BlocoSeguranca)
│   └── 20-vhost-proxy.conf       # <Macro VHostProxy $host $backend $nome>
└── sites/
    ├── 00-default.conf           # catch-all (PRIMEIRO = default — ordem de parse!)
    ├── 10-institucional.conf     # Use VHostTLS …
    ├── 20-blog.conf
    └── 40-api.conf               # Use VHostProxy api.empresa.com.br http://127.0.0.1:8000 api
```

```apache
# httpd.conf:
Include        conf/macros/*.conf        # definições ANTES…
IncludeOptional conf/sites/*.conf        # …das instâncias (ordem de parse!)
```

As convenções que fazem isso escalar (as mesmas de uma codebase):

1. **Um componente = uma responsabilidade** (macro de segurança ≠ macro de vhost).
2. **Props explícitas, nunca estado global implícito** — prefira parâmetros de macro a `Define` globais; quando usar Define em templates, `UnDefine` ao final do arquivo (escopo manual).
3. **Prefixos numéricos = ordem de parse declarada** (dependências e o vhost default).
4. **Parse-time para variação de AMBIENTE** (`<IfDefine DEV>`), **request-time para variação de REQUISIÇÃO** (`<If>`) — nunca trocar um pelo outro.
5. **`httpd -t` é o type-checker; `httpd -S`/`-M` são a introspecção; `httpd -t -D DUMP_CONFIG`** (quando disponível) mostra a configuração EXPANDIDA — o "código gerado" das macros.

### 3.3 `<If>` a fundo — poderes e efeitos colaterais do "componente de runtime"

```apache
# 1. <If> aninha em (quase) tudo — inclusive .htaccess — e aceita <ElseIf>/<Else>:
<Directory "/var/www/app/public">
    <If "-f '%{DOCUMENT_ROOT}/MAINT' && !(-R '192.168.10.0/24')">
        Require all denied
    </If>
    <Else>
        Require all granted
    </Else>
</Directory>

# 2. MAS: <If> funde POR ÚLTIMO no merge — depois até de <Location>.
#    Um <If> global "genérico" pode atropelar silenciosamente regras
#    específicas de Directory/Location. Regra de estilo: <If> o mais
#    PRÓXIMO possível do escopo a que pertence (dentro do Directory,
#    não solto no vhost), como um hook local em vez de estado global.

# 3. Nem toda diretiva funciona dentro de <If> — diretivas avaliadas
#    no parse (Options, AllowOverride…) ou de fases muito cedo não
#    pertencem a um container de runtime. Sintoma: "not allowed here".
#    Dentro de <If>, o feijão-com-arroz seguro: Require, Header,
#    Redirect, SetEnv (via SetEnvIfExpr fora), RewriteRule (2.4.8+).
```

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| Regra específica de `<Directory>` "não pega" | Um `<Location>` ou `<If>` posterior no MERGE sobrepôs | Ordem fixa entre tipos (§2.3); aproximar o `<If>` do escopo |
| Reordenei o arquivo e nada mudou | A mudança foi ENTRE tipos (merge fixo) | Só a ordem entre IRMÃOS do mesmo tipo responde à posição |
| Reordenei e o site padrão trocou | Primeiro `<VirtualHost>` do par = default (parse!) | Prefixos numéricos nos includes |
| `Invalid command` na inicialização | Diretiva antes do `LoadModule`, ou fora de `<IfModule>` em módulo ausente | Ordem de parse (§2.3); `httpd -M` |
| `${VAR}` literal na config | `Define` depois do uso (parse é sequencial) | Mover o Define p/ antes; `httpd -t` acusa |
| `<If>` sempre falso | Operador de string p/ número, literal sem aspas, regex sem delimitador | §2.2 — os três erros clássicos |
| `<If>` com "not allowed here" dentro | Diretiva de parse-time num container de runtime | §3.3 — mover p/ o escopo estático |
| Macro: erro apontando linha estranha | Expansão textual — o erro está NO TEMPLATE, reportado na instância | Corrigir a `<Macro>`; revalidar com `httpd -t` |
| `<Files>` dentro de `<Location>` recusado | Composição proibida | Matriz §1.2 — usar LocationMatch com regex de extensão, ou mover p/ Directory |
| `<Limit>` "protegeu" e método exótico passou | Limit só cobre os métodos listados | `<LimitExcept>` ou `%{REQUEST_METHOD} in {…}` |

### 4.2 Cola de bolso — os oito mandamentos

1. **Containers são componentes:** escopo (`Directory`/`Files`/`Location`) casa alvos; lógica (`If`/`Require*`/`IfModule`) decide; e escopo não aninha em escopo — combina-se pelo merge.
2. **Dois tempos:** `<IfModule>`/`<IfDefine>`/`<IfFile>`/`<Macro>` são compile-time (uma vez, no boot); `<If>`/seleção de vhost/merge/authz são runtime (por requisição). Nunca peça a um o trabalho do outro.
3. **A ordem importa DUAS vezes e de formas diferentes:** no parse (LoadModule/Define/Include/1º vhost/regras do mesmo módulo — sequencial como script) e no merge (constante do Apache: Directory → Files → Location → If — imune à posição no arquivo; posição só desempata irmãos do mesmo tipo).
4. **ap_expr é UMA linguagem para quatro lugares** (`<If>`, `Require expr`, `expr=` de Header, `SetEnvIfExpr`): strings com `==` e aspas simples; inteiros com `-eq/-gt`; regex com `=~ m#…#`; origem com `-R 'CIDR'`; arquivo com `-f`.
5. **`<If>` funde por último** — use-o local (dentro do escopo), nunca como estado global que atropela regras específicas.
6. **mod_macro = componente com props** (expansão textual): uma responsabilidade por macro, `Use` para instanciar, `UndefMacro` para higiene — e vhosts inteiros viram uma linha.
7. **Prefixos numéricos nos includes são a declaração explícita da ordem de parse** — e a garantia do vhost default certo.
8. **`httpd -t` = type-checker; `httpd -S`/`-M` = introspecção.** Nenhuma refatoração de configuração sem os três.

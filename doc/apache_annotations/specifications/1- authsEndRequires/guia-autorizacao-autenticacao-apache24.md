# Autorização e Autenticação no Apache 2.4 — Edição Expandida
## Desfazendo o Nó: Require, os Módulos authn_* / authz_*, AuthType e Toda a Cadeia de Segurança

*Guia prático da série de referência Apache 2.4. Este documento existe para desfazer uma confusão específica e universal: o que exatamente o `Require` faz, de onde vem cada variante dele, o que o `AuthType`/`AuthName`/`AuthUserFile` têm a ver com isso, e o que acontece quando você não configura nada. Todos os exemplos comentados linha a linha.*

---

## Capítulo 0 — O Mapa Mental Que Desfaz a Confusão

### 0.1 Duas perguntas diferentes = duas famílias de módulos

Todo o sistema se resume a duas perguntas que o servidor faz, **nesta ordem**, para cada requisição a um recurso:

| Pergunta | Nome técnico | Família de módulos | Diretivas típicas |
|---|---|---|---|
| **"Quem é você?"** | **Autenticação** (authn) | `mod_authn_*` + frontends `mod_auth_*` | `AuthType`, `AuthName`, `AuthUserFile`, `AuthBasicProvider` |
| **"Você pode entrar?"** | **Autorização** (authz) | `mod_authz_*` | `Require …`, `<RequireAll/Any/None>` |

A confusão nasce porque **as duas famílias trabalham juntas na mesma configuração**, mas são engrenagens separadas:

- **Autenticação** estabelece uma *identidade* (um usuário validado contra uma base de senhas). Ela **sozinha não bloqueia nada** — só descobre quem é a pessoa.
- **Autorização** decide o *acesso*. Ela pode usar a identidade descoberta pela autenticação (`Require valid-user`), **ou pode nem precisar de identidade nenhuma** (`Require ip 192.168.10` decide pelo endereço de origem — ninguém digitou senha).

**Consequência prática nº 1:** dá para ter autorização **sem** autenticação (bloqueio por IP). 
**Consequência prática nº 2:** autenticação **sem** autorização é inútil e o Apache inclusive acusa — configurar `AuthType` sem nenhum `Require` que consuma a identidade gera erro (`configuration error: couldn't check user`).

### 0.2 "É usado automaticamente?" — a resposta precisa

**Não existe segurança automática — mas existe negação automática.** No Apache 2.4:

1. **Autorização sempre roda** para cada requisição — a pergunta "pode entrar?" é sempre feita. O que muda é a resposta padrão:
   - Um `<Directory>` **sem nenhum `Require`** herda a política do contexto pai. Se seu `httpd.conf` tem o bloco defensivo `<Directory "/"> Require all denied </Directory>` (como toda a nossa série recomenda), **tudo nasce negado** e cada vhost precisa liberar explicitamente com `Require all granted`. É por isso que um vhost novo sem o `<Directory>` correspondente dá **403**.
   - Sem o bloco defensivo (configurações antigas/incompletas), o acesso pode nascer liberado — o que é exatamente o risco.
2. **Autenticação NUNCA roda sozinha.** O Apache só pede usuário/senha se você declarar a cadeia completa (`AuthType` + `AuthName` + provedor + um `Require` que use identidade). Não existe "login automático do Apache".

> **Resumo em uma linha:** autorização é obrigatória e sempre avaliada (você decide o veredito com `Require`); autenticação é opcional e só existe se você montar a cadeia.

### 0.3 O mapa dos módulos — de onde vem cada palavra

Esta tabela é o antídoto da confusão. Cada "provider" (a palavra depois de `Require`) vem de UM módulo específico — se o módulo não estiver carregado, aquela variante gera erro `Unknown Authz provider`:

| Você escreve… | Módulo que fornece | O que avalia |
|---|---|---|
| `Require all granted` / `all denied` | **mod_authz_core** | Veredito incondicional |
| `Require env VAR` | **mod_authz_core** | Variável de ambiente setada (ex.: por SetEnvIf) |
| `Require expr "…"` | **mod_authz_core** | Expressão ap_expr por requisição |
| `Require method GET HEAD` | **mod_authz_core** | Método HTTP |
| `<RequireAll/Any/None>` | **mod_authz_core** | Combinação lógica (AND/OR/NOT) |
| `Require ip 192.168.10` | **mod_authz_host** | Endereço/rede de origem |
| `Require host exemplo.com.br` | **mod_authz_host** | DNS reverso da origem (lento — evite) |
| `Require local` | **mod_authz_host** | 127.0.0.1/::1/mesmo host |
| `Require user maria joao` | **mod_authz_user** | Usuário autenticado específico |
| `Require valid-user` | **mod_authz_user** | Qualquer usuário autenticado |
| `Require group diretoria` | **mod_authz_groupfile** | Grupo do arquivo AuthGroupFile |
| `Require ldap-group …` | **mod_authnz_ldap** | Grupo no diretório LDAP/AD |

E do lado da **autenticação**, a arquitetura é frontend + backend:

| Peça | Módulos | Papel |
|---|---|---|
| **Frontend (esquema HTTP)** | `mod_auth_basic`, `mod_auth_digest`, `mod_auth_form` | COMO as credenciais chegam (prompt do navegador, formulário) |
| **Backend (base de dados)** | `mod_authn_file`, `mod_authn_dbm`, `mod_authn_dbd`, `mod_authnz_ldap` | ONDE as senhas são verificadas (arquivo, DBM, SQL, LDAP) |
| **Núcleo** | `mod_authn_core`, `mod_authz_core` | Cola: `AuthType None`, aliases de provedores, os containers |

Carga típica no `httpd.conf` (o kit completo dos exemplos deste guia):

```apache
# --- autorização ---
LoadModule authz_core_module      modules/mod_authz_core.so    # obrigatório sempre
LoadModule authz_host_module      modules/mod_authz_host.so    # Require ip/local/host
LoadModule authz_user_module      modules/mod_authz_user.so    # Require user/valid-user
LoadModule authz_groupfile_module modules/mod_authz_groupfile.so # Require group

# --- autenticação ---
LoadModule authn_core_module      modules/mod_authn_core.so    # núcleo authn
LoadModule auth_basic_module      modules/mod_auth_basic.so    # esquema Basic
LoadModule authn_file_module      modules/mod_authn_file.so    # senhas em arquivo
```

---

## NÍVEL BÁSICO

### 1.1 Só autorização, sem autenticação — o feijão com arroz

```apache
# ---------- Liberar tudo (o "public" explícito) ----------
<Directory "/var/www/site/public">
    Require all granted            # mod_authz_core: veredito incondicional SIM
</Directory>

# ---------- Negar tudo (segredos, metadados) ----------
<Directory "/var/www/site/storage">
    Require all denied             # veredito incondicional NÃO → 403
</Directory>

# ---------- Por origem: só a rede interna ----------
<Directory "/var/www/painel/public">
    # mod_authz_host — três formatos aceitos:
    Require ip 192.168.10          # prefixo de rede (toda a 192.168.10.0/24)
    Require ip 10.0.0.0/8          # notação CIDR
    Require ip 200.100.50.25       # IP exato
    # ⚠ VÁRIOS Require soltos no MESMO nível = OR implícito (RequireAny):
    #   basta casar UM deles para entrar. Isto é regra central do modelo.
</Directory>

# ---------- Só a própria máquina ----------
<Location "/status">
    SetHandler server-status
    Require local                  # 127.0.0.1, ::1 e conexões do próprio host
</Location>
```

**Nenhuma senha foi pedida em nenhum caso acima** — é autorização pura, decidida por atributos da conexão. Aqui já dá para ver que `Require` não tem nada de "automático": cada bloco declara seu veredito.

### 1.2 A cadeia completa de autenticação — as 5 peças do quebra-cabeça

Para o navegador pedir usuário/senha, **todas** as peças precisam existir. Faltou uma → erro ou comportamento inesperado:

```apache
<Directory "/var/www/site/public/restrito">
    # PEÇA 1 — o ESQUEMA: como as credenciais trafegam (frontend)
    AuthType Basic                 # mod_auth_basic: prompt nativo do navegador

    # PEÇA 2 — o REALM: rótulo do prompt E chave de cache de credenciais
    AuthName "Área Restrita"       # navegador reusa credenciais no mesmo realm

    # PEÇA 3 — o PROVEDOR: onde verificar (backend). "file" é o padrão,
    # então esta linha é opcional aqui — mas escrevê-la elimina ambiguidade:
    AuthBasicProvider file         # mod_authn_file

    # PEÇA 4 — a BASE: o arquivo de senhas (SEMPRE fora do DocumentRoot!)
    AuthUserFile "/etc/httpd/passwd/site.passwd"

    # PEÇA 5 — a AUTORIZAÇÃO que CONSOME a identidade:
    Require valid-user             # mod_authz_user: qualquer usuário da base
    # sem esta peça, o Apache acusa erro — autenticar sem autorizar não faz sentido
</Directory>
```

Criação e manutenção da base de senhas:

```bash
htpasswd -c /etc/httpd/passwd/site.passwd maria    # -c CRIA o arquivo (SÓ na 1ª vez!)
htpasswd    /etc/httpd/passwd/site.passwd joao     # adiciona/atualiza usuário
htpasswd -D /etc/httpd/passwd/site.passwd joao     # remove usuário
# formato interno: usuario:hash-bcrypt (htpasswd moderno usa bcrypt com -B)
htpasswd -B /etc/httpd/passwd/site.passwd ana      # força bcrypt (recomendado)
```

> **Regra de ouro nº 1:** Basic transmite `usuario:senha` em **Base64 — que é codificação, não criptografia** (reversível em um comando). Basic **só sobre HTTPS**, sem exceção.
> **Regra de ouro nº 2:** o arquivo `.passwd` fora do DocumentRoot. Dentro dele, é um download público esperando acontecer.

### 1.3 Fluxo completo de uma requisição autenticada (para fixar o modelo)

```
1. GET /restrito/relatorio.pdf              → sem credenciais
2. Apache: authz avalia "Require valid-user" → exige identidade que não existe
3. Apache responde 401 + WWW-Authenticate: Basic realm="Área Restrita"
4. Navegador mostra o prompt (o texto é o AuthName)
5. Novo GET com header Authorization: Basic bWFyaWE6c2VuaGE=
6. mod_auth_basic decodifica → mod_authn_file confere no AuthUserFile
   ├─ senha errada  → 401 de novo (prompt reaparece)
   └─ senha certa   → identidade "maria" estabelecida
7. mod_authz_user reavalia "Require valid-user" → satisfeito → 200, arquivo servido
8. Navegador CACHEIA as credenciais para o realm e as reenvia sozinho
   (por isso "deslogar" de Basic Auth é fechar o navegador — não há logout)
```

---

## NÍVEL INTERMEDIÁRIO

### 2.1 Usuários específicos e grupos

```apache
<Directory "/var/www/site/public/financeiro">
    AuthType Basic
    AuthName "Financeiro"
    AuthBasicProvider file
    AuthUserFile "/etc/httpd/passwd/site.passwd"

    # variante 1 — usuários nomeados (mod_authz_user):
    Require user maria.silva joao.souza
    # só estes dois entram, mesmo que outros existam na base
</Directory>

<Directory "/var/www/site/public/diretoria">
    AuthType Basic
    AuthName "Diretoria"
    AuthBasicProvider file
    AuthUserFile  "/etc/httpd/passwd/site.passwd"
    AuthGroupFile "/etc/httpd/passwd/grupos"       # mod_authz_groupfile

    # variante 2 — por grupo:
    Require group diretoria
</Directory>
```

```
# /etc/httpd/passwd/grupos — formato: nome-do-grupo: membro1 membro2 …
diretoria: maria.silva ana.costa
ti: joao.souza pedro.lima carlos.m
```

*Gestão de acesso por grupo escala melhor: entrada/saída de pessoa = editar UMA linha do arquivo de grupos, sem tocar na configuração do Apache (nem reload — os arquivos passwd/grupos são lidos por requisição).*

### 2.2 A lógica dos containers — AND, OR e NOT explícitos

O modelo mental: `Require` soltos no mesmo nível = **OR** (basta um). Para qualquer outra lógica, containers do `mod_authz_core`:

```apache
# ============ OR explícito (idêntico a Requires soltos, mas legível) ============
# "da rede interna entra direto; de fora, exige senha"
<Directory "/var/www/painel/public">
    AuthType Basic
    AuthName "Painel"
    AuthBasicProvider file
    AuthUserFile "/etc/httpd/passwd/painel.passwd"

    <RequireAny>
        Require ip 192.168.10          # dentro do escritório: passa sem prompt
        Require valid-user             # fora: o navegador pede credencial
    </RequireAny>
    # nota fina: o prompt só aparece para quem NÃO casou o ip — o Apache tenta
    # satisfazer o RequireAny sem autenticação primeiro; falhando, dispara o 401
</Directory>

# ============ AND — TODAS as condições simultâneas ============
# "só da VPN E só com credencial E só o grupo ti"
<Directory "/var/www/painel/public/config">
    AuthType Basic
    AuthName "Configuração"
    AuthBasicProvider file
    AuthUserFile  "/etc/httpd/passwd/painel.passwd"
    AuthGroupFile "/etc/httpd/passwd/grupos"

    <RequireAll>
        Require ip 10.8.0              # rede da VPN
        Require group ti               # identidade + grupo
    </RequireAll>
</Directory>

# ============ NOT — exclusões ============
# "todo mundo entra, EXCETO quem foi marcado como bot indesejado"
<IfModule mod_setenvif.c>
    SetEnvIfNoCase User-Agent "(AhrefsBot|MJ12bot)" bot_ruim
</IfModule>

<Directory "/var/www/site/public">
    <RequireAll>
        Require all granted            # base: liberado…
        <RequireNone>
            Require env bot_ruim       # …menos os marcados (mod_authz_core: env)
        </RequireNone>
    </RequireAll>
</Directory>

# ============ Aninhamento — lógica composta real ============
# "(VPN OU escritório) E (grupo ti OU usuária maria.silva)"
<RequireAll>
    <RequireAny>
        Require ip 10.8.0
        Require ip 200.100.50.25
    </RequireAny>
    <RequireAny>
        Require group ti
        Require user maria.silva
    </RequireAny>
</RequireAll>
```

**Semântica precisa dos containers** (o detalhe que evita surpresas):

| Container | Resultado | Detalhe |
|---|---|---|
| `<RequireAny>` | Satisfeito se **um** membro autorizar | É o comportamento default de Requires soltos |
| `<RequireAll>` | Satisfeito se **nenhum** membro negar e **ao menos um** autorizar | Membros "neutros" não derrubam |
| `<RequireNone>` | **Nega** se qualquer membro casar | Só nega — nunca autoriza sozinho; sempre par com uma base positiva num RequireAll |

### 2.3 Herança entre níveis — AuthMerging

Por padrão, um `Require` num diretório filho **substitui completamente** a configuração de autorização herdada do pai (o merge de authz não é cumulativo!). Quem controla isso é o `AuthMerging` (mod_authz_core):

```apache
<Directory "/var/www/app">
    Require ip 192.168.10                  # pai: só rede interna
</Directory>

<Directory "/var/www/app/relatorios">
    # SEM AuthMerging: a linha abaixo APAGA a regra do pai —
    # relatórios ficariam abertos a qualquer origem com senha!
    # Require valid-user

    # COM AuthMerging: combina com a herança
    AuthMerging And                        # herdado E o daqui (rede E senha)
    AuthType Basic
    AuthName "Relatórios"
    AuthBasicProvider file
    AuthUserFile "/etc/httpd/passwd/app.passwd"
    Require valid-user
</Directory>
# valores: Off (padrão — substitui) | And | Or
```

> Este é um dos erros silenciosos mais perigosos do 2.4: **refinar a proteção de um subdiretório e, sem querer, apagar a do pai**. Na dúvida, repita as condições do pai dentro de um `<RequireAll>` explícito no filho — auto-contido e à prova de releitura.

### 2.4 Onde cada coisa PODE ser declarada (contexto e classe)

| Diretiva | Contextos | Classe p/ .htaccess |
|---|---|---|
| `Require`, containers | directory, .htaccess (e dentro de Files/Location) | `Limit` (origem) / `AuthConfig` (identidade) |
| `AuthType/Name/BasicProvider` | directory, .htaccess | `AuthConfig` |
| `AuthUserFile/GroupFile` | directory, .htaccess | `AuthConfig` |
| `AuthMerging` | directory, .htaccess | `AuthConfig` |
| `SetEnvIf` | server, vhost, directory, .htaccess | `FileInfo` |

*Em produção própria: tudo no vhost. Em cPanel: `.htaccess` com `AllowOverride AuthConfig,Limit` (que o provedor normalmente já libera).*

---

## NÍVEL AVANÇADO

### 3.1 `Require expr` — autorização por expressão

O provider `expr` (mod_authz_core) avalia uma expressão **ap_expr** por requisição — a ferramenta para regras que não cabem nos providers fixos:

```apache
# só HTTPS (nega o vhost :80 espelhado, se existir)
Require expr "%{HTTPS} == 'on'"

# horário comercial (útil p/ painéis administrativos)
Require expr "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"

# origem por CIDR dentro de expressão (função -R):
Require expr "-R '192.168.10.0/24' || -R '10.8.0.0/16'"

# User-Agent obrigatório contendo um token interno (API entre sistemas):
Require expr "%{HTTP_USER_AGENT} =~ /integrador-ipac/"

# combinação com o resto do modelo — expr é um Require como outro qualquer:
<RequireAll>
    Require expr "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"
    Require group ti
</RequireAll>
```

*Sintaxe ap_expr: strings comparam com `==`/`!=` e aspas simples; números com `-eq/-ge/-lt`; regex com `=~ /…/`; erro de sintaxe aparece no ErrorLog **na inicialização** (server/vhost) ou na requisição (.htaccess).*

### 3.2 Múltiplos provedores de autenticação — fallback em cadeia

`AuthBasicProvider` aceita uma **lista**: o Apache consulta na ordem, parando no primeiro que **conhecer** o usuário:

```apache
<Directory "/var/www/intranet">
    AuthType Basic
    AuthName "Intranet"

    # 1º tenta o arquivo local (contas de serviço/emergência),
    # 2º cai para o LDAP corporativo (mod_authnz_ldap carregado):
    AuthBasicProvider file ldap
    AuthUserFile "/etc/httpd/passwd/emergencia.passwd"

    AuthLDAPURL "ldap://ldap.empresa.com.br/ou=Pessoas,dc=empresa,dc=com,dc=br?uid"
    AuthLDAPBindDN       "cn=leitor,dc=empresa,dc=com,dc=br"
    AuthLDAPBindPassword "exec:/etc/httpd/bin/ldap-pass.sh"   # nunca em claro no conf

    <RequireAny>
        Require user admin.emergencia          # do arquivo
        Require ldap-group cn=ti,ou=Grupos,dc=empresa,dc=com,dc=br
    </RequireAny>
</Directory>
```

*Nuance importante: "conhecer o usuário" ≠ "senha correta". Se o `file` conhece `maria` mas a senha não bate, a cadeia **para ali** com falha — não tenta o LDAP. O fallback é por usuário desconhecido, não por senha errada.*

### 3.3 Digest, Form e o estado da arte

```apache
# --- AuthType Digest (mod_auth_digest) ---
# Não transmite a senha (troca hash com nonce), MAS: usa MD5, tem suporte
# irregular em clientes e a base (htdigest) guarda hash reversível por realm.
# Veredito moderno: Basic+HTTPS é mais simples E mais seguro que Digest+HTTP.
# Use Digest apenas em legado sem TLS possível.

# --- AuthType Form (mod_auth_form + mod_session) ---
# Login por formulário HTML com sessão — visual de aplicação, sem prompt nativo.
# Exige mod_session/mod_session_cookie e cuidado extra (CSRF, expiração).
# Na prática: se a aplicação (Laravel etc.) já tem login próprio, deixe a
# autenticação NA APLICAÇÃO e use o Apache para autorização de perímetro
# (ip/local/expr) — cada camada no que faz melhor.
```

### 3.4 Padrão de perímetro completo — um vhost de produção anotado

```apache
<VirtualHost *:443>
    ServerName painel.empresa.com.br
    DocumentRoot "/var/www/painel/public"

    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/painel/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/painel/privkey.pem"

    # ---------- Camada 1: perímetro geral (authz pura, sem prompt) ----------
    <Directory "/var/www/painel/public">
        AllowOverride None
        Options FollowSymLinks

        # rede interna OU VPN — quem está fora nem descobre que o painel existe
        <RequireAny>
            Require ip 192.168.10
            Require ip 10.8.0
        </RequireAny>
    </Directory>

    # ---------- Camada 2: área operacional (identidade + grupo) ----------
    <Directory "/var/www/painel/public/operacao">
        AuthType Basic
        AuthName "Operação"
        AuthBasicProvider file
        AuthUserFile  "/etc/httpd/passwd/painel.passwd"
        AuthGroupFile "/etc/httpd/passwd/grupos"

        AuthMerging And                     # NÃO apagar a camada 1!
        Require group operacao
    </Directory>

    # ---------- Camada 3: configuração (tudo junto + horário) ----------
    <Directory "/var/www/painel/public/config">
        AuthType Basic
        AuthName "Configuração"
        AuthBasicProvider file
        AuthUserFile  "/etc/httpd/passwd/painel.passwd"
        AuthGroupFile "/etc/httpd/passwd/grupos"

        AuthMerging And
        <RequireAll>
            Require group ti
            Require expr "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"
        </RequireAll>
    </Directory>

    # ---------- Handlers virtuais: <Location> com Require próprio ----------
    <Location "/status">
        SetHandler server-status
        Require local                       # só a própria máquina
    </Location>

    ErrorLog  "/var/log/httpd/painel-error.log"
    CustomLog "/var/log/httpd/painel-access.log" combined
</VirtualHost>
```

---

## Capítulo 4 — Diagnóstico

### 4.1 Decodificando os erros clássicos

| Erro no ErrorLog / sintoma | Tradução | Correção |
|---|---|---|
| `AH01630: client denied by server configuration` | authz negou (Require não satisfeito ou herança `denied`) | Conferir qual `<Directory>` casa o caminho e o que ele exige; lembrar do bloco defensivo global |
| `AH01797: client denied by server configuration` (com Require aparentemente ok) | Mistura 2.2 (`Order/Deny`) + 2.4 (`Require`) no escopo — as legadas têm precedência | Remover TODAS as `Order/Allow/Deny/Satisfy` do escopo |
| `configuration error: couldn't check user: /caminho` | `AuthType` declarado **sem** `Require` de identidade (valid-user/user/group) | Adicionar a peça 5 da cadeia (§1.2) |
| `Unknown Authz provider: user` (ou ip, group…) | Módulo do provider não carregado | `LoadModule` correspondente (tabela §0.3); conferir com `httpd -M` |
| `AuthType Basic configured without corresponding module` | `mod_auth_basic` ausente | `LoadModule auth_basic_module …` |
| Prompt reaparece eternamente (401 em loop) | Senha realmente errada, OU `AuthUserFile` ilegível/caminho errado | ErrorLog nomeia o arquivo; caminho **absoluto**; permissão de leitura p/ usuário do Apache |
| Prompt nem aparece, direto 403 | Um `Require` de origem nega ANTES (RequireAll com ip que não casa) | Rever a lógica: origem falhou → nem adianta autenticar |
| Subdiretório "perdeu" a proteção do pai | Merge de authz substitui, não acumula | `AuthMerging And` ou RequireAll auto-contido (§2.3) |
| Basic "não desloga" | Comportamento do protocolo: navegador cacheia por realm | Não há logout em Basic; trocar `AuthName` invalida o cache (gambiarra), ou usar auth de aplicação |

### 4.2 Kit de teste

```bash
# sem credencial: espera-se 401 (área autenticada) ou 403 (negado por origem)
curl -I https://painel.empresa.com.br/operacao/

# com credencial (o curl monta o header Authorization):
curl -I -u maria.silva:senha https://painel.empresa.com.br/operacao/

# ver o desafio que o servidor manda (realm/esquema):
curl -sI https://painel.empresa.com.br/operacao/ | grep -i www-authenticate

# conferir módulos authn/authz carregados:
httpd -M | grep -E 'auth|access'

# validar o hash de um usuário direto na base:
htpasswd -v /etc/httpd/passwd/painel.passwd maria.silva
```

### 4.3 Princípios de fechamento

1. **Autorização é sempre avaliada; o veredito é seu.** Configure o padrão global como negado e libere por exceção — nunca o contrário.
2. **Autenticação é uma cadeia de 5 peças** (esquema → realm → provedor → base → Require de identidade). Erro em qualquer peça quebra o conjunto, e o ErrorLog diz qual.
3. **Requires soltos = OR.** Toda lógica além disso é container explícito — e aninhável.
4. **Herança de authz SUBSTITUI.** `AuthMerging` ou RequireAll auto-contido no filho.
5. **Basic só sobre HTTPS; bases de senha fora do DocumentRoot; bcrypt (`htpasswd -B`).**
6. **Camadas:** origem (ip/local) como perímetro barato e silencioso; identidade (user/group) onde há gente; expressão (`expr`) para o resto; autenticação de aplicação para experiência de login real.

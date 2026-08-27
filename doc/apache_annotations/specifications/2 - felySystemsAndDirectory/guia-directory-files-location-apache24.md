# Filesystem, Webspace e os Containers de Escopo — Edição Expandida
## `<Directory>`, `<Files>`, `<Location>`: O Que Cada Um Enxerga, e Quando Usar Alias

*Guia prático da série de referência Apache 2.4. Este documento responde a uma dúvida estrutural: o que é o `<Location>`, como ele difere de `<Directory>` e `<Files>`, e — a pergunta central — **para acessar conteúdo fora do DocumentRoot, usa-se `<Location>` ou `Alias`?** (Spoiler fundamentado: depende do que é o "conteúdo" — e a resposta muda tudo.)*

---

## Capítulo 0 — Os Dois Mundos do Apache

### 0.1 Filesystem vs. Webspace: a distinção que organiza tudo

O Apache vive entre dois espaços distintos, e cada container de escopo pertence a um deles:

| Mundo | O que é | Exemplo | Containers que operam nele |
|---|---|---|---|
| **Filesystem** | A árvore de diretórios e arquivos do sistema operacional | `/var/www/site/public/index.php`, `C:/xampp/htdocs/...` | `<Directory>`, `<DirectoryMatch>`, `<Files>`, `<FilesMatch>` |
| **Webspace** | A árvore de URLs vista pelo cliente | `https://site.com.br/produtos/123` | `<Location>`, `<LocationMatch>` |

O trabalho central do servidor é **traduzir** um endereço do webspace para algo servível — e essa tradução tem dois destinos possíveis:

1. **Webspace → Filesystem** (o caso comum): a URL vira um caminho de arquivo, via `DocumentRoot` (regra geral) ou `Alias` (exceções pontuais).
2. **Webspace → nada de filesystem**: a URL é atendida por um **handler** (ex.: `server-status`, gerado em memória) ou repassada a um **backend** (`ProxyPass`). Nenhum arquivo local existe.

**Desta distinção nasce a resposta à sua pergunta:**

> - Se o "serviço fora do DocumentRoot" são **arquivos no disco** → você precisa do **`Alias`** (que cria a tradução URL→filesystem) + um **`<Directory>`** (que concede o acesso). `<Location>` sozinho **não serve arquivos** — ele não mapeia nada.
> - Se o "serviço fora" é um **processo/handler** (uma API Laravel na porta 8000, o server-status) → aí sim o **`<Location>`** é a ferramenta, porque não há filesystem envolvido — há apenas uma URL a rotear/configurar.

### 0.2 O que o `<Location>` realmente é

`<Location>` é um container que diz: *"aplique estas diretivas a qualquer requisição cuja **URL** comece com este caminho"* — **independentemente de onde (ou se) essa URL toca o disco**. Ele opera **antes/acima** da tradução para filesystem:

```apache
<Location "/api">
    # vale para /api, /api/, /api/usuarios, /api/qualquer/coisa…
    # o Apache NÃO olhou para o disco para decidir isto — só para a URL
</Location>
```

Três consequências práticas:

1. **`<Location>` não cria conteúdo.** Declarar `<Location "/downloads">` não faz `/downloads` existir — se nada mapear essa URL (DocumentRoot, Alias, ProxyPass, SetHandler), o resultado continua sendo 404.
2. **`<Location>` casa múltiplos caminhos físicos.** Se `/site/img/foto.png` e um `Alias` `/img2` apontarem para o mesmo arquivo, um `<Location "/img">` protege um acesso e ignora o outro — **por isso nunca se protege filesystem com `<Location>`** (a doc oficial é explícita nisso): o mesmo arquivo pode ser alcançável por outra URL que escapa do container.
3. **`<Location>` tem a palavra final no merge.** A ordem de fusão é `<Directory>`(+.htaccess) → `<DirectoryMatch>` → `<Files>` → `<Location>` — o Location sobrepõe todos. Ideal para configurar URLs virtuais; perigoso para "consertar" permissões de arquivos.

### 0.3 Tabela de decisão — qual container para qual problema

| Você quer… | Use | Por quê |
|---|---|---|
| Proteger/configurar **arquivos e pastas reais** | `<Directory>` (+ `<Files>` p/ nomes) | Casa o caminho físico — imune a URLs alternativas |
| Política por **extensão/nome de arquivo** em qualquer pasta | `<Files>`/`<FilesMatch>` | Casa o nome, onde quer que o arquivo esteja |
| Servir **arquivos de fora** do DocumentRoot | **`Alias` + `<Directory>`** | Alias cria o mapeamento; Directory concede o acesso |
| Configurar **URL sem filesystem** (proxy, handler) | `<Location>`/`<LocationMatch>` | Não há disco a casar — só a URL existe |
| Rotear URL para **backend** (Laravel, Node) | `ProxyPass` (+ `<Location>` p/ ajustes) | Tradução webspace→backend |
| Regra por **padrão de URL** independente do físico | `<LocationMatch>` | Regex sobre a URL |

---

## NÍVEL BÁSICO

### 1.1 `<Directory>` — o mundo físico

```apache
# Casa o CAMINHO FÍSICO e tudo abaixo dele (recursivo):
<Directory "/var/www/site/public">
    Options FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>

# Também recursivo: o bloco acima cobre /var/www/site/public/img,
# /var/www/site/public/css, etc. Um <Directory> mais profundo REFINA:
<Directory "/var/www/site/public/privado">
    Require ip 192.168.10           # sobrepõe o granted do pai SÓ aqui
</Directory>

# Por regex — <DirectoryMatch> (não recursivo da mesma forma; casa o padrão):
<DirectoryMatch "/\.git">
    Require all denied              # qualquer .git em qualquer profundidade
</DirectoryMatch>
```

*Merge dentro do mundo Directory: do caminho mais **curto** para o mais **longo** (`/var` antes de `/var/www` antes de `/var/www/site`) — o mais específico vence. `<DirectoryMatch>` é avaliado **depois** de todos os `<Directory>` literais.*

### 1.2 `<Files>` — o nome do arquivo, onde quer que esteja

```apache
# Solto no vhost/global: vale para o nome em QUALQUER diretório
<FilesMatch "\.(env|bak|sql)$">
    Require all denied
</FilesMatch>

# Aninhado num <Directory>: vale só para os arquivos DAQUELE ramo
<Directory "/var/www/site/public/uploads">
    <FilesMatch "\.ph(p[0-9]?|tml)$">
        Require all denied          # PHP em uploads: nem servido, nem executado
    </FilesMatch>
</Directory>
```

### 1.3 `<Location>` — o mundo das URLs (primeiro contato)

```apache
# O caso de uso CANÔNICO: um handler que não tem arquivo por trás.
# "/status" não existe no disco — é gerado pelo mod_status em memória:
<Location "/status">
    SetHandler server-status
    Require local
</Location>

# Segundo caso canônico: configurar uma URL proxificada (nada no disco local):
<Location "/api">
    ProxyPass        "http://127.0.0.1:8000/api"
    ProxyPassReverse "http://127.0.0.1:8000/api"
    Require ip 192.168.10
</Location>
```

*Note o padrão: nos dois casos, **não há filesystem** — por isso `<Directory>` seria impossível (que caminho físico você colocaria?). É exatamente este vazio que o `<Location>` preenche.*

---

## NÍVEL INTERMEDIÁRIO

### 2.1 A pergunta central, respondida com código: conteúdo fora do DocumentRoot

**Cenário:** DocumentRoot é `/var/www/site/public`; os manuais em PDF vivem em `/dados/manuais` (outro disco). Você quer `https://site.com.br/manuais/instalacao.pdf`.

#### ✘ Tentativa com `<Location>` sozinho — NÃO funciona

```apache
# ISTO NÃO SERVE ARQUIVOS:
<Location "/manuais">
    Require all granted
</Location>
# Resultado: 404. O <Location> configurou a URL /manuais…
# …mas NINGUÉM mapeou /manuais para lugar algum. O Apache traduz
# /manuais/instalacao.pdf via DocumentRoot → /var/www/site/public/manuais/
# instalacao.pdf → não existe → 404. O Location é config, não mapeamento.
```

#### ✔ A forma correta — `Alias` (mapeia) + `<Directory>` (autoriza)

```apache
# PASSO 1 — o MAPEAMENTO (mod_alias): URL → caminho físico externo
Alias "/manuais" "/dados/manuais"
# coerência de barra final: ambos sem, ou ambos com — nunca misturado

# PASSO 2 — a AUTORIZAÇÃO: sem isto, o bloco defensivo global
# (<Directory "/"> Require all denied) nega com 403
<Directory "/dados/manuais">
    Options -Indexes                # servir arquivos ≠ listar a pasta
    AllowOverride None
    Require all granted

    # refinamentos opcionais, como em qualquer <Directory>:
    <FilesMatch "\.pdf$">
        Header set Content-Disposition "attachment"   # força download
    </FilesMatch>
</Directory>
```

**A divisão de trabalho, memorizável em uma linha:**

> **`Alias` responde "ONDE está"; `<Directory>` responde "QUEM pode"; `<Location>` não responde nenhuma das duas — ele configura a URL depois que alguém já respondeu o "onde".**

#### E quando o "fora do DocumentRoot" não é arquivo?

```apache
# O serviço externo é um PROCESSO (Laravel local na :8000)?
# Não há "onde no disco" — há um backend. Aí o par certo é ProxyPass + <Location>:
ProxyPass        "/api" "http://127.0.0.1:8000"
ProxyPassReverse "/api" "http://127.0.0.1:8000"

<Location "/api">
    Require ip 192.168.10           # controle de acesso da URL proxificada
    <IfModule mod_headers.c>
        RequestHeader set X-Forwarded-Prefix "/api"
    </IfModule>
</Location>
```

Resumo da decisão em fluxograma textual:

```
O conteúdo fora do DocumentRoot é…
├─ ARQUIVOS no disco?        → Alias + <Directory>        (Location é inútil aqui)
├─ um BACKEND HTTP?          → ProxyPass + <Location>     (Directory é impossível aqui)
└─ um HANDLER interno?       → SetHandler + <Location>    (idem)
```

### 2.2 Os três containers cooperando num vhost real

```apache
<VirtualHost *:443>
    ServerName   www.empresa.com.br
    DocumentRoot "/var/www/site/public"

    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/empresa/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/empresa/privkey.pem"

    # ========== MUNDO FILESYSTEM ==========

    # a raiz pública — <Directory> literal, recursivo
    <Directory "/var/www/site/public">
        Options FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>

    # arquivos externos: Alias mapeia, <Directory> autoriza
    Alias "/manuais" "/dados/manuais"
    <Directory "/dados/manuais">
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    # política por NOME, em qualquer pasta do vhost — <FilesMatch> solto
    <FilesMatch "\.(env|bak|sql|log)$">
        Require all denied
    </FilesMatch>

    # ========== MUNDO WEBSPACE ==========

    # handler virtual: URL sem arquivo
    <Location "/status">
        SetHandler server-status
        Require local
    </Location>

    # backend proxificado: URL sem arquivo local
    ProxyPass        "/api" "http://127.0.0.1:8000"
    ProxyPassReverse "/api" "http://127.0.0.1:8000"
    <Location "/api">
        Require ip 192.168.10
    </Location>

    ErrorLog  "/var/log/httpd/site-error.log"
    CustomLog "/var/log/httpd/site-access.log" combined
</VirtualHost>
```

### 2.3 Por que NUNCA proteger arquivos com `<Location>` — a prova

```apache
# Configuração INGÊNUA: "proteger" a pasta admin pela URL
<Location "/admin">
    Require ip 192.168.10
</Location>

# O furo nº 1 — URL alternativa via Alias:
Alias "/gestao" "/var/www/site/public/admin"
# → https://site.com.br/gestao/ serve OS MESMOS ARQUIVOS
#   e o <Location "/admin"> nunca é consultado. Furo aberto.

# O furo nº 2 — variações de encoding/case (conforme plataforma):
# em Windows, /Admin e /admin apontam ao MESMO diretório físico,
# mas "/Admin" NÃO casa <Location "/admin"> (Location é case-sensitive).

# A forma ROBUSTA: proteger o que se quer proteger — o FILESYSTEM:
<Directory "/var/www/site/public/admin">
    Require ip 192.168.10
</Directory>
# agora tanto /admin quanto /gestao quanto /Admin (Windows) caem
# na mesma regra, porque todos resolvem para o mesmo caminho físico.
```

*Regra derivada: **`<Location>` protege URLs que só existem como URLs** (proxy, handler). **Arquivo se protege onde ele mora: no `<Directory>`.***

---

## NÍVEL AVANÇADO

### 3.1 Ordem de merge completa — quem sobrepõe quem

Para cada requisição, o Apache funde as seções nesta ordem (a última palavra vence):

```
1. <Directory> literais (do caminho MAIS CURTO ao MAIS LONGO)
   └─ intercalados com os .htaccess de cada nível (se AllowOverride permitir)
2. <DirectoryMatch> e <Directory ~ "regex">
3. <Files> e <FilesMatch>          (simultâneos, na ordem do arquivo)
4. <Location> e <LocationMatch>    (simultâneos, na ordem do arquivo)
```

Mais duas camadas transversais:

- Seções **dentro de `<VirtualHost>`** aplicam **depois** das globais equivalentes (vhost sobrepõe global, nível a nível).
- `<If>` é fundido por último de todos — sobrepõe até `<Location>`.

**Experimento mental que fixa o modelo:**

```apache
<Directory "/var/www/site/public/relatorios">
    Require all denied              # filesystem diz: NÃO
</Directory>

<Location "/relatorios">
    Require all granted             # webspace diz: SIM
</Location>
# Resultado: ACESSO LIBERADO — Location funde por último e sobrepõe.
# É exatamente por isso que Location "consertando" permissões é uma
# armadilha: ele vence sem você perceber o que atropelou.
```

### 3.2 `<LocationMatch>` e `<Location ~>` — regex sobre URLs

```apache
# padrões de URL que não têm relação com estrutura física:
<LocationMatch "^/(api|webhooks)/v[0-9]+/">
    # todas as versões de API e webhooks, de uma vez
    Require ip 192.168.10
    <IfModule mod_headers.c>
        Header always set Cache-Control "no-store"
    </IfModule>
</LocationMatch>

# grupos nomeados viram variáveis de ambiente (recurso 2.4.29+):
<LocationMatch "^/cliente/(?<CLIENTEID>[0-9]+)">
    # disponível como %{env:MATCH_CLIENTEID} em expressões/headers
    Header set X-Cliente-Id "%{MATCH_CLIENTEID}e"
</LocationMatch>
```

### 3.3 `AliasMatch` e `ScriptAlias` — mapeamentos avançados

```apache
# AliasMatch: mapeamento por regex, com captura ($1) no destino
# ex.: /avatar/1234.png → /dados/avatares/34/1234.png (shard por final do id)
AliasMatch "^/avatar/([0-9]+([0-9]{2}))\.png$" "/dados/avatares/$2/$1.png"
<Directory "/dados/avatares">
    Options -Indexes
    Require all granted
</Directory>

# ScriptAlias = Alias + "tudo aqui é CGI executável":
ScriptAlias "/cgi-bin/" "/var/www/cgi/"
<Directory "/var/www/cgi">
    Options +ExecCGI
    Require all granted
</Directory>
# nota de época: CGI clássico é legado — mantido aqui pela completude do mod_alias
```

*Precedência dentro do mod_alias: `Alias`/`AliasMatch`/`Redirect` são processados **na ordem em que aparecem**, e o primeiro casamento vence — declare os mais específicos antes dos mais genéricos (`Alias /icons/small` antes de `Alias /icons`).*

### 3.4 O caminho completo de uma requisição (juntando tudo)

```
GET https://site.com.br/manuais/instalacao.pdf
│
├─ 1. Seleção do vhost (IP:porta → ServerName)
├─ 2. TRADUÇÃO webspace→filesystem:
│     • existe Alias/AliasMatch p/ /manuais?  SIM → /dados/manuais/instalacao.pdf
│     • (não houvesse: DocumentRoot + URL-path)
│     • (houvesse ProxyPass/SetHandler via Location: sem filesystem — pula p/ handler)
├─ 3. MERGE de configuração para o alvo:
│     <Directory "/"> (denied) → <Directory "/dados/manuais"> (granted)
│     → <FilesMatch ".pdf$"> (attachment) → <Location>s que casem /manuais
├─ 4. Fases: authz (Require do resultado do merge) → handler → filtros (deflate…)
└─ 5. Resposta (+ CustomLog)
```

*Dominado este pipeline, todo 403/404 "misterioso" vira uma pergunta objetiva: falhou na tradução (passo 2 → 404) ou no merge/authz (passo 3-4 → 403)? O ErrorLog distingue: `File does not exist` vs `client denied by server configuration`.*

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| 404 numa URL "configurada" com `<Location>` | Location não mapeia — nada traduziu a URL p/ conteúdo | Adicionar `Alias` (arquivos), `ProxyPass` (backend) ou `SetHandler` (handler) |
| 403 num `Alias` novo | Falta o `<Directory>` do destino (bloco defensivo global nega) | `<Directory "destino"> Require all granted` |
| 403 persiste com `<Directory>` correto | Permissão Unix: usuário do Apache sem leitura no arquivo ou sem +x (travessia) no caminho | `ls -la` de cada nível; ajustar dono/permissões |
| Proteção por `<Location>` contornada | Location casa URL, não arquivo — URL alternativa escapou | Migrar a proteção para `<Directory>`/`<Files>` (§2.3) |
| Regra de `<Directory>` "não pega" | Um `<Location>` (ou `<If>`) posterior sobrepõe no merge | Revisar §3.1; procurar Locations que casem a URL |
| Alias ignora e serve do DocumentRoot | Alias declarado DEPOIS de outro que casou antes, ou typo na URL | mod_alias processa em ordem; específico antes do genérico |
| Pasta homônima sob o DocumentRoot inacessível | O Alias intercepta a URL antes do mapeamento normal | Comportamento esperado — renomear a URL do Alias ou a pasta |
| `/Admin` passa onde `/admin` bloqueia (Windows) | Filesystem case-insensitive + `<Location>` case-sensitive | Mais um motivo do §2.3: proteger via `<Directory>` |

### 4.2 Cola de bolso — os cinco mandamentos

1. **`<Directory>`/`<Files>` enxergam disco; `<Location>` enxerga URL.** Nunca troque os mundos.
2. **Arquivos fora do DocumentRoot = `Alias` + `<Directory>`.** O Alias mapeia (ONDE); o Directory autoriza (QUEM). `<Location>` não substitui nenhum dos dois.
3. **`<Location>` é para o que só existe como URL:** handlers (`server-status`, `balancer-manager`) e backends (`ProxyPass`).
4. **Proteção de arquivo mora no `<Directory>`** — Location é contornável por URLs alternativas e vence o merge sem avisar.
5. **No 404 vs 403:** 404 = a tradução falhou (falta mapeamento); 403 = a tradução funcionou e a autorização negou (falta `Require`... ou permissão Unix).

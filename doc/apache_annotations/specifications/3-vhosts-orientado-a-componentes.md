# httpd-vhosts.conf Orientado a Componentes
## Programando a Configuração: Máximo de Containers, Mínimo de Diretivas Soltas

*Reescrita do roteiro de construção sob o paradigma que a série estabeleceu no guia de Containers: **configuração como árvore de componentes**. Cada preocupação vive num bloco nomeado e delimitado; macros são componentes reutilizáveis com props; condições viram `<If>/<Else>` estruturados em vez de `env=` espalhados. O objetivo: um programador abre o arquivo e lê uma árvore — não uma lista de diretivas soltas.*

---

## PARTE 1 — A FILOSOFIA (as regras do paradigma)

### 1.1 O contrato de estilo

```
REGRA 1 — TODA preocupação vive num container:
          permissão → <Directory> · lógica → <If>/<Require*> ·
          módulo → <IfModule> · reuso → <Macro> · arquivo → <FilesMatch>

REGRA 2 — O corpo do vhost é um SUMÁRIO: só identidade + instâncias
          de componentes (Use). Quem lê o vhost lê a COMPOSIÇÃO;
          quem lê as macros lê a IMPLEMENTAÇÃO.

REGRA 3 — Condição pareada vira <If>/<Else> (um bloco, dois ramos
          visíveis) em vez de duas diretivas soltas com env=X e env=!X.

REGRA 4 — Diretivas que NÃO PODEM ser containerizadas (existem — §4.1)
          ficam agrupadas em "seções de folha" comentadas, nunca
          espalhadas entre blocos.

REGRA 5 — Indentação = profundidade da árvore. Container abre,
          filhos indentam, container fecha. Sem exceção.
```

### 1.2 O antes/depois que define tudo

```apache
# ══════════════ ESTILO "SOLTO" (o que estamos abandonando) ══════════════
SetEnvIf Request_URI "\.(css|js)$" estatico
Header set Cache-Control "public, max-age=86400" env=estatico
Header set Cache-Control "private, no-cache" env=!estatico
RequestHeader unset Cookie env=estatico
# └ quatro linhas soltas, três preocupações misturadas, condição implícita
#   duplicada em três lugares — para entender, o leitor reconstrói o if na cabeça

# ══════════════ ESTILO "COMPONENTE" (o que estamos adotando) ══════════════
<If "%{REQUEST_URI} =~ /\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$/">
    # ── ramo: requisição de ASSET ESTÁTICO ──
    <IfModule mod_headers.c>
        Header set Cache-Control "public, max-age=86400"
        RequestHeader unset Cookie      # asset não precisa de sessão
        Header unset Set-Cookie         # e jamais cria uma
    </IfModule>
</If>
<Else>
    # ── ramo: conteúdo DINÂMICO ──
    <IfModule mod_headers.c>
        Header set Cache-Control "private, no-cache"
    </IfModule>
</Else>
# └ UM bloco, DOIS ramos explícitos, a condição escrita UMA vez,
#   tudo que pertence ao ramo DENTRO do ramo. Lê-se como um if/else.
```

*Custo honesto do estilo: `<If>` avalia a expressão por requisição (ínfimo) e funde por último no merge (§4.2 — o efeito colateral a conhecer). O ganho: legibilidade de código.*

---

## PARTE 2 — A BIBLIOTECA DE COMPONENTES (as macros)

*Arquivo: `conf/macros/componentes.conf` — carregado ANTES dos sites (`Include conf/macros/*.conf` antes de `IncludeOptional conf/sites/*.conf`). Cada macro = um componente com UMA responsabilidade e props explícitas.*

```apache
# ============================================================
# <TLSPadrao $dominio> — fundação de criptografia + HTTP/2
# props: $dominio (pasta do certificado em letsencrypt/live)
# ============================================================
<Macro TLSPadrao $dominio>
    SSLEngine On
    SSLCertificateFile    "/etc/letsencrypt/live/$dominio/fullchain.pem"
    SSLCertificateKeyFile "/etc/letsencrypt/live/$dominio/privkey.pem"
    SSLProtocol -all +TLSv1.2 +TLSv1.3
    SSLHonorCipherOrder Off
    <IfModule http2_module>
        Protocols h2 http/1.1
    </IfModule>
</Macro>

# ============================================================
# <PHPFPM> — handler PHP como componente (trocar o socket = 1 lugar)
# ============================================================
<Macro PHPFPM>
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"
    </FilesMatch>
</Macro>

# ============================================================
# <RaizPublica $caminho> — a liberação + front controller,
# TUDO dentro do próprio <Directory> (nada vaza pro corpo do vhost)
# props: $caminho (a pasta public/ da aplicação)
# ============================================================
<Macro RaizPublica $caminho>
    <Directory "$caminho">
        Options FollowSymLinks              # sem Indexes: nunca listar
        AllowOverride None                  # .htaccess não existe em produção
        Require all granted                 # a exceção à negação global

        # ── o roteador, encapsulado no escopo a que pertence ──
        RewriteEngine On
        RewriteCond %{REQUEST_URI} !\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$ [NC]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
</Macro>

# ============================================================
# <AreaProtegida $caminho $realm $arqSenhas> — autenticação completa
# props: caminho físico, texto do prompt, arquivo htpasswd
# (as 5 peças da cadeia authn/authz num componente só)
# ============================================================
<Macro AreaProtegida $caminho $realm $arqSenhas>
    <Directory "$caminho">
        AuthType Basic
        AuthName "$realm"
        AuthBasicProvider file
        AuthUserFile "$arqSenhas"
        <RequireAll>
            <RequireAny>
                Require ip 192.168.10       # escritório OU
                Require ip 10.8.0           # VPN…
            </RequireAny>
            Require valid-user              # …E credencial
        </RequireAll>
    </Directory>
</Macro>

# ============================================================
# <UploadsInertes $caminho> — servidos, jamais executados
# ============================================================
<Macro UploadsInertes $caminho>
    <Directory "$caminho">
        Options -Indexes -ExecCGI
        <FilesMatch "\.ph(ar|p[0-9]?|tml)$">
            SetHandler none                 # desarma o FPM aqui
            Require all denied              # e nem o fonte é servido
        </FilesMatch>
    </Directory>
</Macro>

# ============================================================
# <ArquivosExternos $url $caminho> — Alias + Directory num componente
# (o par inseparável: ONDE + QUEM, nunca um sem o outro)
# ============================================================
<Macro ArquivosExternos $url $caminho>
    Alias "$url" "$caminho"
    <Directory "$caminho">
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
</Macro>

# ============================================================
# <HeadersSeguranca> — o dossiê completo, sempre "always"
# ============================================================
<Macro HeadersSeguranca>
    <IfModule mod_headers.c>
        <If "%{HTTPS} == 'on'">
            Header always set Strict-Transport-Security \
                "max-age=31536000; includeSubDomains"
        </If>
        Header always set X-Content-Type-Options "nosniff"
        Header always set X-Frame-Options "SAMEORIGIN"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
        Header always unset X-Powered-By
        Header unset X-Powered-By
    </IfModule>
</Macro>

# ============================================================
# <PoliticaCache> — a tabela de ouro como árvore de decisão explícita
# (if/elseif/else legível em vez de três Headers soltos com env=)
# ============================================================
<Macro PoliticaCache>
    <IfModule mod_headers.c>
        <If "%{REQUEST_URI} =~ /\.[0-9a-f]{8,}\.(css|js|woff2)$/">
            # ── ramo 1: asset VERSIONADO por hash → imutável 1 ano ──
            Header set Cache-Control "public, max-age=31536000, immutable"
        </If>
        <ElseIf "%{REQUEST_URI} =~ /\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$/">
            # ── ramo 2: estático SEM versão → 1 dia + 304 depois ──
            Header set Cache-Control "public, max-age=86400"
            RequestHeader unset Cookie          # estático sem cookies,
            Header unset Set-Cookie             # nas duas direções
        </ElseIf>
        <ElseIf "req('Cookie') =~ /app_session=/">
            # ── ramo 3: usuário LOGADO → só cache privado ──
            Header set Cache-Control "private, no-cache"
        </ElseIf>
        <Else>
            # ── ramo 4: dinâmico anônimo → revalidação sempre ──
            Header set Cache-Control "no-cache"
        </Else>
    </IfModule>
</Macro>

# ============================================================
# <ErroDocs $raizErros> — páginas de erro + a liberação delas
# (o ErrorDocument sem o <Directory> liberado = 403 na própria
#  página de erro; o componente entrega o par completo)
# ============================================================
<Macro ErroDocs $raizErros>
    ErrorDocument 401 /erros/401.html       # 401 SEMPRE local (remoto
    ErrorDocument 403 /erros/403.html       # vira redirect e quebra auth)
    ErrorDocument 404 /erros/404.html
    ErrorDocument 500 /erros/500.html
    ErrorDocument 503 /erros/503.html
    <Directory "$raizErros">
        Require all granted                 # acessíveis a qualquer um…
        <IfModule mod_headers.c>
            Header set Cache-Control "no-store"   # …e nunca cacheadas
        </IfModule>
    </Directory>
</Macro>

# ============================================================
# <ModoManutencao $raizApp> — flag MAINT com exceções, encapsulado
# ============================================================
<Macro ModoManutencao $raizApp>
    RewriteEngine On
    RewriteCond $raizApp/MAINT -f                    # a flag existe?
    RewriteCond %{REMOTE_ADDR} !^192\.168\.10\.      # equipe passa
    RewriteCond %{REQUEST_URI} !^/manutencao\.html$  # a página passa
    RewriteCond %{REQUEST_URI} !\.(css|png|svg)$     # e os assets (anti-loop)
    RewriteRule ^ /manutencao.html [R=503,L]
    <IfModule mod_headers.c>
        <If "%{REQUEST_STATUS} == 503">
            Header always set Retry-After "3600"
        </If>
    </IfModule>
</Macro>

# ============================================================
# <MetodosPermitidos> — whitelist de métodos como componente
# (<If>, não <Limit>: Limit só cobre os LISTADOS — alçapão)
# ============================================================
<Macro MetodosPermitidos>
    <If "%{REQUEST_METHOD} !in { 'GET', 'HEAD', 'POST' }">
        Require all denied
    </If>
</Macro>

# ============================================================
# <LogsPadrao $nome> — logs dedicados e condicionais
# ============================================================
<Macro LogsPadrao $nome>
    ErrorLog  "/var/log/httpd/$nome-error.log"
    LogLevel  warn
    CustomLog "/var/log/httpd/$nome-access.log" rastreavel "expr=!(%{REQUEST_URI} =~ m#^/(health|ping)$#)"
    #  └ condição por expr direto no CustomLog (2.4): sondas fora do log
    #    sem depender de marca solta no corpo do vhost
</Macro>
```

---

## PARTE 3 — O VHOST COMO COMPOSIÇÃO (o corpo vira um sumário)

*Com a biblioteca pronta, o vhost inteiro é uma árvore de instâncias — a Regra 2: quem lê o vhost lê a COMPOSIÇÃO. Compare o tamanho disto com o template "solto" equivalente.*

```apache
# ============================================================
# conf/sites/10-institucional.conf
# ============================================================

# ── :80 — só ACME + redirect canônico (o par mínimo) ──
<VirtualHost *:80>
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br

    Use ArquivosExternos /.well-known/acme-challenge /var/www/acme/.well-known/acme-challenge

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://www.empresa.com.br%{REQUEST_URI} [R=301,L]

    ErrorLog "/var/log/httpd/institucional-redirect-error.log"
</VirtualHost>

# ── :443 — o site como ÁRVORE DE COMPONENTES ──
<VirtualHost *:443>

    # ═══ identidade (as únicas "folhas" legítimas do corpo — §4.1) ═══
    ServerName  www.empresa.com.br
    ServerAlias empresa.com.br
    ServerAdmin ti@empresa.com.br
    DocumentRoot "/var/www/institucional/public"
    DirectoryIndex index.php index.html
    AddDefaultCharset UTF-8

    # ═══ variáveis de aplicação (folhas agrupadas — Regra 4) ═══
    SetEnv  APP_ENV "production"
    SetEnv  APP_URL "https://www.empresa.com.br"
    SetEnv  DB_HOST "10.0.0.5"
    PassEnv DB_PASSWORD APP_KEY          # segredos: do processo, nunca literais

    # ═══ a composição — o site inteiro em componentes ═══
    Use TLSPadrao        empresa.com.br
    Use PHPFPM
    Use RaizPublica      /var/www/institucional/public
    Use ArquivosExternos /manuais /dados/manuais
    Use AreaProtegida    /var/www/institucional/public/admin "Administração" /etc/httpd/passwd/site.passwd
    Use UploadsInertes   /var/www/institucional/public/uploads
    Use MetodosPermitidos
    Use PoliticaCache
    Use HeadersSeguranca
    Use ErroDocs         /var/www/institucional/public/erros
    Use ModoManutencao   /var/www/institucional
    Use LogsPadrao       institucional

    # ═══ o que é ESPECÍFICO deste site (e só isto fica inline) ═══
    <Location "/status">
        SetHandler server-status
        Require local
    </Location>

    <Directory "/var/www/institucional/public/admin/config">
        AuthMerging And                  # herda a AreaProtegida E adiciona:
        Require expr "%{TIME_HOUR} -ge 8 && %{TIME_HOUR} -lt 19"
    </Directory>
</VirtualHost>
```

*Leia o bloco `Use` de cima a baixo: é literalmente o SUMÁRIO das capacidades do site. Um site novo = copiar 15 linhas e trocar props. Uma correção de segurança = editar UMA macro, todos os sites corrigidos no próximo `graceful`.*

### 3.1 Segundo site — o paradigma pagando o investimento

```apache
# conf/sites/20-blog.conf — um site COMPLETO em ~15 linhas:
<VirtualHost *:443>
    ServerName   blog.empresa.com.br
    ServerAdmin  ti@empresa.com.br
    DocumentRoot "/var/www/blog/public"
    DirectoryIndex index.php
    AddDefaultCharset UTF-8

    SetEnv APP_ENV "production"
    SetEnv APP_URL "https://blog.empresa.com.br"
    PassEnv DB_PASSWORD

    Use TLSPadrao      blog.empresa.com.br
    Use PHPFPM
    Use RaizPublica    /var/www/blog/public
    Use UploadsInertes /var/www/blog/public/uploads
    Use MetodosPermitidos
    Use PoliticaCache
    Use HeadersSeguranca
    Use ErroDocs       /var/www/blog/public/erros
    Use LogsPadrao     blog
</VirtualHost>
```

---

## PARTE 4 — OS LIMITES DO PARADIGMA (honestidade técnica)

### 4.1 As folhas legítimas — o que NÃO containeriza (e como conviver)

Nem tudo aceita container — estas diretivas são folhas por natureza, e a Regra 4 manda **agrupá-las com cabeçalho**, nunca espalhá-las:

| Diretiva | Por que é folha | Onde agrupá-la |
|---|---|---|
| `ServerName/Alias/Admin`, `DocumentRoot` | Identidade do vhost — contexto direto | Bloco "identidade" no topo |
| `SetEnv`/`PassEnv` | mod_env não avalia dentro de `<If>` de forma confiável | Bloco "variáveis" após a identidade |
| `ProxyPass`/`ProxyPassReverse` | Mapeamento de tradução (podem ir em `<Location>`, mas a forma inline com path é canônica) | Bloco "proxy" (ou dentro de `<Location>` quando por rota) |
| `Listen`, `LoadModule`, MPM | Exclusivamente server config | httpd.conf, nunca no vhost |
| `RewriteMap` | Só server config / vhost (nunca em Directory) | Junto do bloco de rewrite do vhost |

### 4.2 Os dois efeitos colaterais do `<If>` (conheça antes de abusar)

```
1. <If> FUNDE POR ÚLTIMO no merge — depois de Directory, Files e Location.
   Um <If> "genérico" no corpo do vhost atropela silenciosamente regras
   específicas dos containers de escopo.
   → Regra de estilo: <If> o mais PRÓXIMO possível do escopo a que
     pertence (dentro do Directory/da macro), como um hook local —
     nunca como estado global.

2. Nem toda diretiva roda dentro de <If>: as de parse-time (Options,
   AllowOverride, AuthType…) pertencem a containers de escopo, não de
   runtime. Sintoma: "not allowed here".
   → Feijão-com-arroz seguro dentro de <If>: Require, Header,
     RequestHeader, Redirect, RewriteRule (2.4.8+).
```

### 4.3 Macro: as regras de higiene

```
• Uma responsabilidade por macro (TLS ≠ cache ≠ auth) — como funções.
• Props explícitas; evitar Define global como "estado escondido".
• Expansão é TEXTUAL no parse: erro na macro aparece MULTIPLICADO
  por instância — httpd -t valida o resultado expandido.
• Definições ANTES das instâncias (Include conf/macros/ antes de sites/).
• UndefMacro ao fim do arquivo de biblioteca se quiser escopo estrito.
```

---

## PARTE 5 — O RITUAL (inalterado, com um passo novo)

```bash
httpd -t                     # 1. valida INCLUSIVE as macros expandidas
httpd -S                     # 2. mapa de vhosts (defaults, arquivo:linha)
httpd -M | grep -E 'macro|http2|proxy_fcgi'    # 3. mod_macro carregado?
apachectl graceful           # 4. reload sem derrubar conexões
curl -IL http://empresa.com.br/                # 5. smoke tests de sempre
curl -sI https://blog.empresa.com.br/ | grep -i x-frame   # ← o passo novo:
#  └ testar UMA macro em DOIS sites: se HeadersSeguranca responde igual
#    no institucional e no blog, a biblioteca está íntegra
tail -f /var/log/httpd/*-error.log             # 6. observação pós-mudança
```

---

## SÍNTESE — O Mapa Mental do Paradigma

```
conf/macros/componentes.conf        ← a BIBLIOTECA (implementação)
│   <Macro TLSPadrao $dominio>         componente = 1 responsabilidade
│   <Macro RaizPublica $caminho>       props = argumentos explícitos
│   <Macro PoliticaCache>              <If>/<ElseIf>/<Else> = árvore de decisão
│   …                                  <IfModule> = compilação condicional
│
conf/sites/10-institucional.conf    ← a COMPOSIÇÃO (o sumário)
│   <VirtualHost *:443>
│       [identidade]  ← folhas agrupadas (Regra 4)
│       [variáveis]   ← folhas agrupadas
│       Use … × 12    ← a árvore de componentes
│       [específicos] ← só o que é único deste site fica inline
│   </VirtualHost>
│
conf/sites/20-blog.conf             ← REUSO: site completo em 15 linhas
```

**A frase que resume:** *macro é a função, `Use` é a chamada, props são os argumentos, `<If>/<Else>` é o fluxo de controle, `<Directory>`/`<FilesMatch>` são os escopos, `httpd -t` é o compilador — e o corpo do vhost é o `main()` que só orquestra.*

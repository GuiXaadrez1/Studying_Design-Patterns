# Redirecionamento e Reescrita de URL — Edição Expandida
## mod_alias, mod_rewrite e Como Eles Habitam a Tradução Webspace → Filesystem

*Guia prático da série de referência Apache 2.4. Este documento conecta-se diretamente ao anterior (Filesystem, Webspace e Containers): redirecionamentos e reescritas são **intervenções na fase de tradução** de URL — o passo 2 daquele pipeline. Entender ONDE eles atuam explica POR QUE se comportam diferente em cada contexto.*

---

## Capítulo 0 — O Modelo: Duas Operações, Um Lugar no Pipeline

### 0.1 Recapitulando o pipeline (do guia anterior) e localizando a reescrita

```
GET https://site.com.br/promo
│
├─ 1. Seleção do vhost
├─ 2. TRADUÇÃO webspace → filesystem        ◄── REDIRECT E REWRITE VIVEM AQUI
│     (Alias? Rewrite? Redirect? ProxyPass? senão: DocumentRoot + URL-path)
├─ 3. Merge de configuração p/ o alvo (Directory → Files → Location)
├─ 4. Fases: authz → handler → filtros
└─ 5. Resposta
```

Redirecionar e reescrever são **duas formas de intervir na tradução** — e a diferença entre elas é a distinção mais importante deste guia:

| Operação | O que acontece | Quem percebe | Round-trips |
|---|---|---|---|
| **Redirecionamento EXTERNO** | Servidor responde `3xx` + header `Location:`; o **cliente** faz NOVA requisição para a nova URL | O navegador (barra de endereço muda) e os buscadores | 2 (ida dupla) |
| **Reescrita INTERNA** | Servidor **silenciosamente** troca o alvo da tradução e segue servindo na mesma requisição | Ninguém de fora — a URL no navegador não muda | 1 |

**Conexão direta com o guia anterior:**

- O redirect externo devolve o cliente ao **webspace**: a nova URL passa pelo pipeline INTEIRO de novo (vhost, tradução, merge) — inclusive pelo mesmo arquivo de regras, origem dos loops.
- A reescrita interna troca o destino da tradução: pode apontar outro caminho no **filesystem** (como um Alias dinâmico) ou outra URL interna. O merge (passo 3) acontece sobre o alvo **final**.
- `Redirect` (mod_alias) e `RewriteRule` (mod_rewrite) **competem pela mesma fase** — regra de convivência no §2.4.

### 0.2 Os códigos de status — o vocabulário do redirect externo

| Código | Nome | Semântica | Uso |
|---|---|---|---|
| **301** | Moved Permanently | Mudou para sempre; buscadores transferem a indexação; **navegadores cacheiam agressivamente** | Canonicalização (https, www), URLs migradas em definitivo |
| **302** | Found (temporário) | Mudou por ora; nada é cacheado/transferido | Testes, promoções, redirecionamentos em desenvolvimento |
| **303** | See Other | "A resposta está ali" — força GET no destino | Padrão POST→redirect→GET |
| **307/308** | Temporary/Permanent Redirect | Como 302/301, mas **preservam o método** (um POST continua POST) | APIs e formulários que não podem virar GET |
| **410** | Gone | Não é redirect: "existiu e foi removido de propósito" | Aposentar URLs (buscadores removem mais rápido que com 404) |

> **Regra de laboratório:** desenvolva com **302**; promova a **301** só quando a regra estiver comprovadamente estável. O cache agressivo de 301 no navegador faz regras erradas "assombrarem" depois de corrigidas — teste sempre com `curl -IL` (imune a cache), nunca só pelo navegador.

### 0.3 As duas ferramentas

| | **mod_alias** (`Redirect`, `RedirectMatch`) | **mod_rewrite** (`RewriteRule`, `RewriteCond`) |
|---|---|---|
| Natureza | Declarativa, simples | Motor programável (condições, variáveis, mapas) |
| Só faz | Redirect **externo** (e mapeamento, via Alias) | Externo `[R]` **e** interno (padrão) |
| Condições | Nenhuma | `RewriteCond` sobre ~40 variáveis |
| Custo cognitivo | Mínimo | O suficiente para merecer esta série |
| Quando usar | Sem condição a testar? **Sempre ele** | Qualquer coisa condicional, interna ou dinâmica |

---

## NÍVEL BÁSICO

### 1.1 mod_alias — o redirect declarativo

```apache
# ---------- Redirect: casa por PREFIXO de URL ----------
# sintaxe: Redirect [status] prefixo-URL destino
Redirect permanent "/catalogo-2024" "/catalogo"
#        └─ "permanent" = 301; "temp" = 302 (default se omitido)

# ⚠ prefixo é PREFIXO mesmo: a regra acima também redireciona
#   /catalogo-2024/qualquer/coisa → /catalogo/qualquer/coisa
#   (o sufixo é preservado e anexado ao destino)

# destino absoluto quando muda host/esquema:
Redirect permanent "/loja" "https://loja.empresa.com.br/"

# ---------- RedirectMatch: casa por REGEX ----------
RedirectMatch 301 "^/blog/([0-9]{4})/(.*)$" "/artigos/$1/$2"
#                                              └─ capturas $1 $2 utilizáveis

# aposentando recursos (não é redirect — é lápide):
RedirectMatch 410 "\.(bak|old)$"
Redirect gone "/produto-descontinuado"
```

### 1.2 mod_rewrite — a primeira regra, dissecada

```apache
RewriteEngine On                    # o motor nasce DESLIGADO em todo contexto

#            ┌ PADRÃO (regex sobre a URL)   ┌ SUBSTITUIÇÃO        ┌ FLAGS
RewriteRule  ^promo$                        /ofertas.php          [L]
#
# SEM flag [R]: reescrita INTERNA — o navegador pediu /promo, continua
# vendo /promo, mas quem foi servido foi /ofertas.php. Um "Alias dinâmico".

# COM [R]: vira redirect EXTERNO — o navegador é mandado à nova URL:
RewriteRule  ^promo$   /ofertas   [R=301,L]
```

**O que o padrão casa em cada contexto** (a ponte com o guia anterior — o mundo em que o container vive muda o que a regra enxerga):

| Contexto da regra | O padrão casa contra | Barra inicial? |
|---|---|---|
| server config / corpo do `<VirtualHost>` | URL-path completa | **COM**: `^/promo$` |
| `<Directory>` / `.htaccess` | Caminho **relativo ao diretório** (prefixo removido) | **SEM**: `^promo$` |

*Este é o erro nº 1 ao mover regras entre contextos: a mesma regex casa num lugar e falha no outro por causa de uma barra.*

### 1.3 RewriteCond — condições (o que o mod_alias não tem)

```apache
# RewriteCond condiciona APENAS a RewriteRule imediatamente seguinte —
# condições NÃO são globais nem "vazam" para outras regras.

#            ┌ STRING DE TESTE (variáveis) ┌ PADRÃO/OPERADOR
RewriteCond  %{REQUEST_FILENAME}           !-f
RewriteCond  %{REQUEST_FILENAME}           !-d
RewriteRule  ^(.*)$  index.php?url=$1  [QSA,L]
# ↑ o front controller: "se o pedido NÃO é arquivo (-f) nem diretório (-d)
#   real no filesystem, entregue ao ponto único de entrada"
# note a ponte filesystem↔webspace: a CONDIÇÃO olha o disco,
# o PADRÃO olha a URL — os dois mundos do guia anterior na mesma regra.

# operadores de condição:
#   regex normal:      RewriteCond %{HTTP_HOST} ^www\.
#   negação:           RewriteCond %{HTTPS} !=on
#   comparação literal: =valor  >valor  <valor
#   testes de arquivo:  -f (arquivo) -d (diretório) -s (arquivo não-vazio)
#   flags: [NC] case-insensitive · [OR] liga à próxima em OU (default é E)
```

Variáveis mais usadas: `%{HTTP_HOST}` (só o hostname — **nunca** contém `http://` nem o path), `%{REQUEST_URI}` (o path com barra inicial), `%{QUERY_STRING}`, `%{HTTPS}`, `%{REQUEST_FILENAME}`, `%{REMOTE_ADDR}`, `%{HTTP_USER_AGENT}`, `%{REQUEST_METHOD}`, `%{DOCUMENT_ROOT}`.

---

## NÍVEL INTERMEDIÁRIO

### 2.1 As flags — o painel de controle

```apache
# [L]    last: encerra ESTA RODADA de reescrita (não necessariamente o processo!)
# [END]  encerra DEFINITIVAMENTE — nenhuma rodada nova (2.4; antídoto de loop)
# [R=n]  redirect externo com status n (R sozinho = 302)
# [QSA]  query string append: preserva a query original ao anexar nova
# [NC]   nocase no PADRÃO da regra
# [F]    forbidden: responde 403 (alvo "-" = não reescreve nada)
# [G]    gone: responde 410
# [PT]   passthrough: devolve o resultado ao mapeamento de URL —
#        OBRIGATÓRIA quando a substituição deve passar por Alias depois
# [P]    proxy: entrega o alvo ao mod_proxy (reverse proxy por regra!)
# [NE]   noescape: não re-escapar caracteres já codificados no destino
# [E=VAR:val] seta variável de ambiente (consumível por Header, log, PHP)
# [S=n]  skip: pula as próximas n regras se esta casar (if/else de rewrite)
# [C]    chain: encadeia com a próxima (se esta falha, a próxima é pulada)
```

**`[L]` vs `[END]` — o detalhe que explica loops "impossíveis":** em contexto per-directory (`<Directory>`/`.htaccess`), uma reescrita interna dispara **nova rodada**: a URL resultante re-entra no processamento (novo internal redirect) e o arquivo de regras é avaliado DE NOVO. `[L]` só encerra a rodada atual — se a URL reescrita casa de novo o padrão, roda de novo, ad infinitum. Soluções: condição de exclusão (`RewriteCond %{REQUEST_URI} !^/destino`), ou `[END]`, que proíbe novas rodadas.

### 2.2 Receitas canônicas comentadas

```apache
# ============ Canonicalização https + www em UM redirect ============
# (dois redirects encadeados = latência dupla + diluição de SEO)
RewriteEngine On
RewriteCond %{HTTPS} !=on [OR]
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteCond %{HTTP_HOST} ^(?:www\.)?(.+)$ [NC]
RewriteRule ^(.*)$ https://www.%1/$1 [R=301,L]
# %N = captura da ÚLTIMA RewriteCond casada; $N = captura do RewriteRule
# — dois espaços de captura DIFERENTES, outra fonte clássica de confusão.

# ============ Migração de estrutura com preservação de query ============
# /produto.php?id=123&ref=email  →  /produto/123?ref=email
RewriteCond %{QUERY_STRING} (?:^|&)id=([0-9]+)(?:&(.*))?$
RewriteRule ^produto\.php$ /produto/%1?%2 [R=301,L,QSA]

# ============ Descartar a query no redirect (o "?" vazio) ============
# /busca?q=lixo → /busca-nova   (sem arrastar a query)
RewriteRule ^busca$ /busca-nova? [R=301,L]
#                              └─ "?" no fim do destino APAGA a query

# ============ Barra final canônica (trailing slash) ============
# diretórios com /, conteúdo sem / — escolha UM padrão e force-o:
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} (.+)/$
RewriteRule ^ %1 [R=301,L]        # remove a barra final de não-diretórios
```

### 2.3 Interna vs externa — a decisão, com o guia anterior como lente

```apache
# INTERNA: o cliente não deve saber/perceber (URL é interface estável)
#  → pretty URLs, front controller, servir de caminho alternativo
RewriteRule ^manual/([a-z-]+)$ /docs/render.php?doc=$1 [L]

# EXTERNA: o cliente DEVE ir a outro lugar (a URL antiga está errada/morta)
#  → canonicalização, migrações, http→https
RewriteRule ^docs-antigos/(.*)$ /manual/$1 [R=301,L]

# A conexão com Alias (guia anterior): uma reescrita interna cujo alvo
# é um CAMINHO DE FILESYSTEM faz papel de Alias dinâmico:
RewriteRule ^avatar/([0-9]+)\.png$ /dados/avatares/$1.png [L]
# …mas o <Directory "/dados/avatares"> com Require continua OBRIGATÓRIO —
# a reescrita muda a TRADUÇÃO (passo 2); o MERGE e a AUTHZ (passos 3-4)
# acontecem sobre o alvo FINAL. Reescrever não é autorizar.

# E se o alvo interno precisa passar por um Alias EXISTENTE? Flag [PT]:
Alias "/manuais" "/dados/manuais"
RewriteRule ^docs/(.*)$ /manuais/$1 [PT,L]
# sem [PT], em contexto server/vhost, o mod_rewrite trataria "/manuais/…"
# como caminho de arquivo literal e o Alias nunca seria consultado.
```

### 2.4 mod_alias × mod_rewrite no mesmo vhost — regras de convivência

Ambos atuam na fase de tradução, mas **em ganchos diferentes e sem se coordenarem**:

1. **Nunca os dois para as mesmas URLs.** `Redirect /x` + `RewriteRule ^x` sobre o mesmo caminho = comportamento dependente de ordem interna de módulos — aleatório na prática.
2. **Critério de escolha por bloco de URLs:** há condição a testar? mod_rewrite. Não há? mod_alias.
3. **Sintoma da mistura:** redirect "fantasma" que persiste após remover a RewriteRule (era o Redirect esquecido — ou o cache de um 301; `curl` desempata).
4. Dentro do mod_alias, `Redirect`/`Alias` processam **na ordem do arquivo**, primeiro casamento vence: específicos antes de genéricos.

---

## NÍVEL AVANÇADO

### 3.1 Reescrita orientada a dados — RewriteMap

```apache
# Centenas de URLs migradas não viram centenas de regras — viram UM mapa.
# RewriteMap SÓ em server config ou <VirtualHost> (nunca Directory/.htaccess):
RewriteMap redir "dbm:/etc/httpd/maps/redirects.map"

RewriteEngine On
RewriteCond ${redir:%{REQUEST_URI}} !=""
RewriteRule ^ ${redir:%{REQUEST_URI}} [R=301,L]
```

```bash
# fonte texto "chave valor" → compilar p/ dbm (releitura automática, sem reload):
#   /promocao-2024   /ofertas
#   /blog/artigo-12  /blog/novo-slug
httxt2dbm -i redirects.txt -o redirects.map
```

```apache
# outros tipos de mapa:
RewriteMap minusc "int:tolower"                    # função interna
RewriteRule ^dir/([A-Z].*)$ /dir/${minusc:$1} [R=301,L]

RewriteMap rota "prg:/etc/httpd/bin/roteador.py"   # programa externo persistente
# (prg: roda como daemon — cuidado com concorrência; prefira dbm quando possível)
```

### 3.2 Proxy por regra — a flag [P]

```apache
# ProxyPass roteia por PREFIXO; [P] roteia por REGRA — cirúrgico:
RewriteEngine On
# só os webhooks de UM parceiro vão ao backend local; o resto do site é estático
RewriteRule ^webhooks/whatsapp/(.*)$ http://127.0.0.1:8000/webhooks/whatsapp/$1 [P,L]
ProxyPassReverse "/webhooks/whatsapp/" "http://127.0.0.1:8000/webhooks/whatsapp/"
# [P] exige mod_proxy + mod_proxy_http; ProxyPassReverse continua manual.
# ⚠ JAMAIS interpolar input do cliente no host do alvo ([P] com $1 no hostname
#   = open proxy/SSRF). O host do destino deve ser SEMPRE literal.
```

### 3.3 Reescrita condicionada a ambiente — [E=] e a integração com authz

```apache
# O elo com o guia de Autorização: rewrite marca, Require decide.
RewriteEngine On
# marca requisições de um parceiro por assinatura própria:
RewriteCond %{HTTP:X-Parceiro-Token} ^tok-[0-9a-f]{32}$
RewriteRule ^ - [E=PARCEIRO_OK:1]

<Directory "/var/www/site/public/api-parceiros">
    <RequireAny>
        Require ip 192.168.10
        Require env PARCEIRO_OK          # consumindo a marca do rewrite
    </RequireAny>
</Directory>
```

### 3.4 Contexto per-directory a fundo — por que .htaccess/Directory se comportam "estranho"

```
No contexto server/vhost, o mod_rewrite roda UMA vez, cedo, sobre a URL crua.
No contexto <Directory>/.htaccess, ele roda TARDE — a tradução p/ filesystem
JÁ aconteceu — e o Apache precisa "des-traduzir" para reescrever:

1. remove o prefixo do diretório do caminho (por isso padrões SEM barra);
2. aplica suas regras;
3. se algo mudou → INTERNAL REDIRECT: a URL nova re-entra no pipeline
   INTEIRO (tradução, merge, e o próprio arquivo de regras DE NOVO);
4. o RewriteBase diz que prefixo de URL repor no passo 3 quando o
   diretório físico não corresponde trivialmente à URL (Alias, userdir).

Corolários:
• loops são o estado natural — [END] e condições de exclusão são o freio;
• regras per-directory custam mais (rodadas extras) — mais um motivo
  para regras viverem no vhost (série: guia de vhosts, §1.4);
• REDIRECT_* : após um internal redirect, as variáveis originais são
  preservadas com prefixo REDIRECT_ (ex.: %{ENV:REDIRECT_PARCEIRO_OK}) —
  detalhe que quebra [E=] ingênuos entre rodadas.
```

### 3.5 Depuração profissional

```apache
# no VHOST (nunca em .htaccess): o rastro completo do motor
LogLevel warn rewrite:trace3
# trace1..trace8; acima de trace2 degrada MUITO — ligar, reproduzir, DESLIGAR.
# As linhas saem no ErrorLog do vhost marcadas [rewrite:trace…], mostrando
# cada padrão testado, cada condição avaliada e cada substituição aplicada.
```

```bash
# a tríade de teste externa:
curl -I  "https://site.com.br/promo"          # UMA resposta: status + Location
curl -IL "https://site.com.br/promo"          # a CADEIA inteira de redirects
curl -I  -H "Host: outro.com.br" http://IP/   # simular hosts sem tocar DNS

# sondas quando não há acesso ao ErrorLog (hospedagem compartilhada):
#   RewriteRule ^teste$ - [E=REGRA_X:1,L]   → conferir em $_SERVER no PHP
#   Header always set X-Debug "regra-y"     → conferir no curl -I
```

---

## Capítulo 4 — Diagnóstico e Referência Rápida

### 4.1 Tabela de sintomas

| Sintoma | Causa | Correção |
|---|---|---|
| `ERR_TOO_MANY_REDIRECTS` | Destino re-casa a regra (rodadas per-directory) ou canonicalização dupla | Condição de exclusão; `[END]`; consolidar https+www num só 301; `curl -IL` p/ ver a cadeia |
| Regra ignorada ao migrar vhost↔.htaccess | Barra inicial do padrão (contextos casam strings diferentes) | Tabela §1.2 |
| Redirect certo, query string perdida | Falta `[QSA]` (ou `?` indevido no destino) | §2.2 |
| Redirect "fantasma" após remover a regra | Cache de 301 no navegador, ou `Redirect` do mod_alias esquecido | `curl` (sem cache); auditar mod_alias; desenvolver com 302 |
| Reescrita interna p/ Alias não funciona | Falta `[PT]` (contexto server/vhost) | §2.3 |
| 403 após reescrita interna p/ caminho externo | Reescrever ≠ autorizar: falta o `<Directory>` do alvo | §2.3 — merge/authz agem sobre o alvo FINAL |
| `%1` vazio na substituição | Captura da RewriteCond errada (%N = ÚLTIMA cond casada) ou cond com [OR] não casada | Reordenar conds; testar com trace3 |
| Variável [E=] some entre regras | Internal redirect renomeou p/ `REDIRECT_*` | §3.4 |
| [P] devolve 502/aberto demais | Backend fora, ou input do cliente no host do alvo | Host literal sempre; conferir mod_proxy carregado |
| RewriteMap "não existe" | Declarada em Directory/.htaccess (contexto proibido) | Mover p/ vhost/global |

### 4.2 Cola de bolso — os sete mandamentos

1. **Externo muda o navegador de lugar (3xx + 2ª requisição); interno troca a tradução em silêncio (1 requisição).** Escolha pela pergunta: "o cliente DEVE saber?"
2. **Sem condição → mod_alias (`Redirect`). Com condição → mod_rewrite.** Nunca os dois nas mesmas URLs.
3. **302 no desenvolvimento, 301 só consolidado; teste com `curl -IL`,** nunca pelo cache do navegador.
4. **Contexto muda o padrão:** server/vhost casa `/com-barra`; Directory/.htaccess casa `sem-barra` — e per-directory roda em RODADAS (`[L]` encerra a rodada, `[END]` encerra tudo).
5. **Reescrever não é autorizar:** o merge e o `Require` valem para o alvo final — todo destino de reescrita precisa do seu `<Directory>`.
6. **`%N` vem da última RewriteCond; `$N` vem do RewriteRule.** Dois espaços de captura.
7. **Muitas URLs → RewriteMap (dados), não muitas regras (código).** E `[P]` sempre com host literal.

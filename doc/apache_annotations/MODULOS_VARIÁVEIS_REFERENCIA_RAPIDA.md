# REFERÊNCIA RÁPIDA - MÓDULOS, VARIÁVEIS E DIRETIVAS

## TABELA 1: MÓDULOS APACHE

| Módulo | Função | Diretiva Principal | Verificação | Status |
|--------|--------|-------------------|------------|--------|
| **mod_rewrite** | Reescrever URLs | RewriteEngine, RewriteRule | cPanel → Apache Modules → search "rewrite" | Essential |
| **mod_deflate** | Comprimir (gzip) | AddOutputFilterByType | cPanel → Apache Modules → search "deflate" | Recomendado |
| **mod_expires** | Cache HTTP | ExpiresActive, ExpiresByType | cPanel → Apache Modules → search "expires" | Recomendado |
| **mod_headers** | Headers custom | Header set, Header append | cPanel → Apache Modules → search "headers" | Recomendado |
| **mod_access_compat** | Bloquear/permitir | Deny from all, Allow from | Apache padrão | Sempre ativo |
| **mod_dir** | Diretório padrão | Options -Indexes | Apache padrão | Sempre ativo |

---

## TABELA 2: VARIÁVEIS DO APACHE (mod_rewrite)

| Variável | Valor Exemplo | Descrição | Uso |
|----------|---------------|-----------|-----|
| **%{HTTPS}** | "on" ou "off" | Status do protocolo | Verificar se HTTPS ativo |
| **%{HTTP_HOST}** | ipac.com.br | Nome do host solicitado | Canonical, redirecionamento |
| **%{REQUEST_URI}** | /usuarios/123 | URI completa da requisição | Manter caminho após rewrite |
| **%{REQUEST_FILENAME}** | /var/www/html/index.php | Caminho completo do arquivo | Verificar se arquivo/diretório existe |
| **%{QUERY_STRING}** | id=5&name=jose | Parâmetros após ? | Validar/processar query string |
| **%{REQUEST_METHOD}** | GET, POST, PUT, DELETE | Método HTTP | Permitir/bloquear métodos |
| **%{REMOTE_ADDR}** | 192.168.1.100 | IP do cliente | Bloquear/permitir IPs |
| **%{HTTP_USER_AGENT}** | Mozilla/5.0... | Browser/aplicação do cliente | Bloquear bots/scrapers |
| **%{SERVER_PORT}** | 80 ou 443 | Porta HTTP ou HTTPS | Verificar protocolo |
| **%{TIME_YEAR}** | 2026 | Ano atual | Condicional por data |

---

## TABELA 3: OPERADORES DE COMPARAÇÃO

| Operador | Significado | Exemplo | Resultado |
|----------|------------|---------|-----------|
| **=** | Igualdade | %{HTTPS} = off | Verdadeiro se HTTP |
| **!=** | Desigualdade | %{HTTPS} != off | Verdadeiro se HTTPS |
| **-f** | É arquivo? | %{REQUEST_FILENAME} -f | Verdadeiro se arquivo existe |
| **!-f** | Não é arquivo? | %{REQUEST_FILENAME} !-f | Verdadeiro se arquivo não existe |
| **-d** | É diretório? | %{REQUEST_FILENAME} -d | Verdadeiro se diretório existe |
| **!-d** | Não é diretório? | %{REQUEST_FILENAME} !-d | Verdadeiro se diretório não existe |
| **-l** | É link simbólico? | %{REQUEST_FILENAME} -l | Verdadeiro se é link |
| **-s** | Arquivo com conteúdo? | %{REQUEST_FILENAME} -s | Verdadeiro se arquivo tem bytes |
| **>** | Maior que | String comparison (avançado) | Comparação lexical |
| **<** | Menor que | String comparison (avançado) | Comparação lexical |

---

## TABELA 4: FLAGS DE REWRITE

| Flag | Nome | Função | Exemplo |
|------|------|--------|---------|
| **L** | Last | Para de processar regras seguintes | [L] - para aqui |
| **R=301** | Redirect 301 | Redir. permanente (SEO bom) | [R=301] - redir. com 301 |
| **R=302** | Redirect 302 | Redir. temporária | [R=302] - redir. temporária |
| **QSA** | QueryStringAppend | Mantém query string original | [QSA] - preserva ?param=valor |
| **NE** | NoEscape | Não escapa caracteres especiais | [NE] - keep & in URL |
| **NC** | NoCase | Case-insensitive | [NC] - WWW = www = Www |
| **F** | Forbidden | Retorna 403 | [F] - acesso negado |
| **G** | Gone | Retorna 410 | [G] - gone/deleted |
| **P** | Proxy | Proxy (cuidado!) | [P] - processa como proxy |
| **PT** | PassThrough | Passa para próximo handler | [PT] - para handler seguinte |
| **S=n** | Skip | Pula n regras | [S=3] - pula 3 regras |
| **T=type** | Type | Define MIME type | [T=text/html] - force tipo |
| **E=var:value** | Environ | Define variável ambiente | [E=blocked:1] - set var |

---

## TABELA 5: DIRETIVAS PRINCIPAIS

| Diretiva | Sintaxe | Função | Exemplo |
|----------|---------|--------|---------|
| **RewriteEngine** | On/Off | Ativa/desativa mod_rewrite | RewriteEngine On |
| **RewriteBase** | / ou /subdir/ | Define base para reescrita | RewriteBase / |
| **RewriteCond** | Var Operador Valor [Flags] | Condicional (IF) | RewriteCond %{HTTPS} off |
| **RewriteRule** | Pattern Substitution [Flags] | Regra de reescrita | RewriteRule ^(.*)$ ... [L] |
| **Options** | +/-Diretiva | Opções de pasta | Options -Indexes |
| **Deny from** | IP ou all | Nega acesso | Deny from all |
| **Allow from** | IP ou all | Permite acesso | Allow from 192.168.1.100 |
| **Header set** | Nome Valor | Define header HTTP | Header set X-Frame-Options "..." |
| **Header append** | Nome Valor | Adiciona a header | Header append Vary "..." |
| **ExpiresActive** | On/Off | Ativa expiração | ExpiresActive On |
| **ExpiresByType** | MIME-Type Valor | Cache por tipo | ExpiresByType image/png "..." |
| **ExpiresDefault** | Valor | Cache padrão | ExpiresDefault "access plus..." |
| **AddOutputFilterByType** | Filtro MIME-Type | Compressão | AddOutputFilterByType DEFLATE ... |
| **AuthType** | Basic/Digest | Tipo autenticação | AuthType Basic |
| **Require** | valid-user ou IP | Exigir autenticação | Require valid-user |

---

## TABELA 6: REGEX QUICK REFERENCE

| Padrão | Significa | Exemplo | Match |
|--------|-----------|---------|-------|
| **^** | Início | ^usuarios | usuarios123 ✅, meusuarios ❌ |
| **$** | Fim | 123$ | usuarios123 ✅, 123usuarios ❌ |
| **.** | Qualquer char | a.b | aXb ✅, ab ❌ |
| **\\.** | Ponto literal | php\. | config.php ✅, config php ❌ |
| **\*** | 0+ ocorrências | ab*c | ac, abc, abbc ✅ |
| **+** | 1+ ocorrências | ab+c | abc, abbc ✅, ac ❌ |
| **?** | 0 ou 1 | colou? | color, colour ✅ |
| **[abc]** | Classe a,b,c | [aeiou] | a, e, i, o, u ✅ |
| **[^abc]** | Negação classe | [^0-9] | letra ✅, 5 ❌ |
| **[0-9]** | Dígito | [0-9]+ | 123 ✅, abc ❌ |
| **[a-z]** | Letra minúscula | [a-z]+ | abc ✅, ABC ❌ |
| **[A-Z]** | Letra maiúscula | [A-Z]+ | ABC ✅, abc ❌ |
| **\w** | Palavra (\w=[a-zA-Z0-9_]) | \w+ | abc123_ ✅, @ ❌ |
| **\d** | Dígito (\d=[0-9]) | \d+ | 123 ✅, abc ❌ |
| **\s** | Whitespace | \s+ | espaço, tab ✅ |
| **(...)** | Grupo/captura | (abc)+ | abc, abcabc ✅ |
| **\|** | OU | a\|b | a ✅, b ✅, c ❌ |
| **{n}** | Exatamente n | a{3} | aaa ✅, aa ❌ |
| **{n,m}** | Entre n e m | a{1,3} | a, aa, aaa ✅ |

---

## TABELA 7: STATUS HTTP

| Código | Descrição | Motivo | Solução |
|--------|-----------|--------|---------|
| **200** | OK | Sucesso | ✅ Funciona |
| **301** | Moved Permanently | Redir. permanente | Esperado (SEO ok) |
| **302** | Found | Redir. temporária | Use 301 para SEO |
| **304** | Not Modified | Cache válido | Cliente usa versão local |
| **400** | Bad Request | Requisição inválida | Verifique URL |
| **401** | Unauthorized | Autenticação necessária | Forneça credenciais |
| **403** | Forbidden | Acesso negado | Bloqueado por .htaccess |
| **404** | Not Found | Arquivo não existe | Arquivo/caminho errado |
| **500** | Internal Server Error | Erro no .htaccess | Verifique sintaxe |
| **503** | Service Unavailable | Servidor down | Aguarde |

---

## TABELA 8: TIPOS MIME COMUNS

| MIME Type | Extensão | Descrição |
|-----------|----------|-----------|
| text/html | .html | Página HTML |
| text/plain | .txt | Texto puro |
| text/css | .css | Stylesheet |
| text/javascript | .js | JavaScript |
| application/javascript | .js | JavaScript (moderno) |
| application/json | .json | JSON data |
| image/jpeg | .jpg, .jpeg | Imagem JPEG |
| image/png | .png | Imagem PNG |
| image/gif | .gif | Imagem GIF |
| image/webp | .webp | Imagem WebP |
| image/svg+xml | .svg | Imagem SVG |
| application/pdf | .pdf | Documento PDF |
| application/zip | .zip | Arquivo ZIP |
| font/truetype | .ttf | Fonte TrueType |
| font/opentype | .otf | Fonte OpenType |
| application/x-font-woff | .woff | Fonte WOFF |

---

## TABELA 9: CACHE TEMPOS RECOMENDADOS

| Tipo | Tempo | Motivo |
|------|-------|--------|
| **Imagens** | 1 ano | Raramente mudam |
| **Fontes** | 1 ano | Praticamente nunca mudam |
| **CSS** | 1 mês | Podem ter updates |
| **JavaScript** | 1 mês | Podem ter bug fixes |
| **HTML** | 1 dia | Muda frequente |
| **JSON/API** | 1 hora | Dados dinâmicos |
| **Padrão** | 8 horas | Fallback genérico |

---

## TABELA 10: EQUAÇÕES DE TEMPO

| Expressão | Segundos | Descrição |
|-----------|----------|-----------|
| access plus 1 hour | 3.600 | 1 hora |
| access plus 8 hours | 28.800 | 8 horas |
| access plus 1 day | 86.400 | 1 dia |
| access plus 7 days | 604.800 | 1 semana |
| access plus 30 days | 2.592.000 | ~1 mês |
| access plus 1 month | 2.592.000 | ~1 mês (30 dias) |
| access plus 90 days | 7.776.000 | ~3 meses |
| access plus 1 year | 31.536.000 | 1 ano (365 dias) |

---

## SNIPPETS COMUNS

### 1. Forçar HTTPS

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 2. Remover WWW

```apache
RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]
RewriteRule ^(.*)$ https://%1/$1 [L,R=301]
```

### 3. Adicionar WWW

```apache
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteRule ^(.*)$ https://www.%{HTTP_HOST}/$1 [L,R=301]
```

### 4. Bloquear IP

```apache
<Directory "/var/www/html">
    Order Allow,Deny
    Allow from all
    Deny from 203.0.113.10
</Directory>
```

### 5. Permitir Apenas IP

```apache
<Directory "/var/www/html/admin">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.100
</Directory>
```

### 6. Proteger com Senha

```apache
<Directory "/var/www/html/admin">
    AuthType Basic
    AuthName "Restrito"
    AuthUserFile /var/www/html/.htpasswd
    Require valid-user
</Directory>
```

### 7. Redirecionar para HTTPS e WWW

```apache
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteRule ^(.*)$ https://www.%{HTTP_HOST}/$1 [L,R=301]
```

### 8. URL Amigável para Framework

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
```

### 9. Bloquear Bot

```apache
RewriteCond %{HTTP_USER_AGENT} curl|wget|wget2 [NC]
RewriteRule ^.*$ - [F]
```

### 10. Bloquear Hotlink de Imagens

```apache
RewriteCond %{HTTP_REFERER} !^$
RewriteCond %{HTTP_REFERER} !^https?://seu-dominio\.com [NC]
RewriteCond %{REQUEST_FILENAME} \.(jpg|jpeg|png|gif)$ [NC]
RewriteRule ^.*$ - [F]
```

---

## CHECKLIST RÁPIDO

```
ANTES DE IMPLEMENTAR:
[ ] Certificado SSL instalado? (cPanel → SSL/TLS)
[ ] Módulos ativados? (cPanel → Apache Modules)
    [ ] mod_rewrite
    [ ] mod_deflate
    [ ] mod_expires
    [ ] mod_headers

DEPOIS DE IMPLEMENTAR:
[ ] HTTP → HTTPS? (curl -I http://seu-dominio.com)
[ ] WWW correto? (curl -I https://seu-dominio.com)
[ ] Cache headers? (curl -I https://seu-dominio.com/logo.png)
[ ] Compressão? (curl -I https://seu-dominio.com/style.css)
[ ] Segurança headers? (curl -I https://seu-dominio.com)
[ ] Site funciona? (testar login, formulários, APIs)

ERROS COMUNS:
❌ Redirecionamento infinito → Verifique canonical
❌ 500 error → cPanel Error Logs → procure "htaccess"
❌ Arquivo não encontrado → Verifique !-f e !-d
❌ Compressão não ativa → Verifique mod_deflate
```

---

## FÓRMULA DE REGEX PARA CASOS COMUNS

### ID numérico
```regex
^usuarios/([0-9]+)/?$
Captura: /usuarios/123 ou /usuarios/123/
Salva em: $1 = 123
```

### Slug (texto-amigável)
```regex
^blog/([a-z0-9-]+)/?$
Captura: /blog/meu-artigo-123 ou /blog/meu-artigo-123/
Salva em: $1 = meu-artigo-123
```

### API versioning
```regex
^api/v([0-9]+)/([a-z]+)/([0-9]+)/?$
Captura: /api/v1/usuarios/123
Salva em: $1 = 1, $2 = usuarios, $3 = 123
```

### Tudo (para frameworks)
```regex
^(.*)$
Captura: qualquer URL
Salva em: $1 = URL completa
```

### Excluir extensões específicas
```regex
\.(jpg|jpeg|png|gif|pdf)$
Captura: arquivo.jpg, arquivo.png, etc
Não captura: arquivo.html
```

---

**Fim da referência rápida**

Use este documento como "cola" durante implementação e troubleshooting! 📋

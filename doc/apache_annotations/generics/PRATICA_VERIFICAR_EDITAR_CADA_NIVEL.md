# GUIA PRÁTICO: VERIFICAR, EDITAR E DEBUGAR

Como fazer na vida real com VPS/cPanel

---

# PARTE 1: VER ARQUIVO httpd.conf

## Via SSH (Recomendado)

```bash
# Ver arquivo completo
cat /etc/apache2/httpd.conf

# Ver apenas linhas específicas
grep -n "LoadModule" /etc/apache2/httpd.conf

# Contar total de linhas
wc -l /etc/apache2/httpd.conf

# Procurar por "Include"
grep -n "^Include" /etc/apache2/httpd.conf

# Exemplo de saída:
# 100:Include /etc/apache2/mods-enabled/*.load
# 101:Include /etc/apache2/mods-enabled/*.conf
# 102:Include /etc/apache2/conf.d/*.conf
# 105:Include /etc/apache2/sites-enabled/*.conf
```

## Via Editor SSH

```bash
# Abrir com nano (fácil)
nano /etc/apache2/httpd.conf

# Abrir com vim (avançado)
vim /etc/apache2/httpd.conf

# Dica: Use Ctrl+X para sair (nano) ou :q (vim)
```

## Verificar Sintaxe

```bash
# Testar sintaxe de httpd.conf
apachectl configtest

# Saída esperada:
# Syntax OK

# Se erro:
# [core:error] ... Syntax error on line 123 of ...
# └─ Procure linha 123, vê o erro
```

## Via cPanel (Limited)

```
cPanel Home 
  → Apache Configuration 
    → View Default Configuration 
      → Ver conteúdo (read-only)
```

---

# PARTE 2: VER E EDITAR vhost.conf

## Via cPanel (Recomendado)

```
MÉTODO 1: Ver vhost.conf
┌──────────────────────────────────┐
│ cPanel Home                       │
│ → Apache Configuration            │
│ → View/Edit Vhost Configuration   │
│ → Selecione seu domínio           │
│ → Vê conteúdo completo            │
└──────────────────────────────────┘

MÉTODO 2: Editar vhost.conf
┌──────────────────────────────────┐
│ WHM (WebHost Manager - VPS)       │
│ → Service Configuration           │
│ → Apache Configuration            │
│ → Edit Apache Configuration       │
│ → Clique "Edit" ao lado do domain │
│ → Edite as linhas necessárias     │
│ → Salve                           │
│ → Apache recarrega automaticamente│
└──────────────────────────────────┘

⚠️ NUNCA edite vhost.conf manualmente via SSH!
   cPanel gera automaticamente - edições podem ser perdidas
```

## Via SSH (Se precisar ver)

```bash
# Listar todos os vhost.conf
ls -la /etc/apache2/conf.d/ | grep vhost

# Exemplo saída:
# -rw-r--r-- 1 root root 2480 Aug 25 12:34 vhost_001_ipac.com.br.conf
# -rw-r--r-- 1 root root 2512 Aug 25 12:35 vhost_002_api.ipac.com.br.conf
# -rw-r--r-- 1 root root 2450 Aug 25 12:36 vhost_003_staging.ipac.com.br.conf

# Ver conteúdo de um vhost.conf específico
cat /etc/apache2/conf.d/vhost_001_ipac.com.br.conf

# Ver apenas ServerName
grep -n "ServerName" /etc/apache2/conf.d/vhost_001_ipac.com.br.conf
```

## Verificar Qual VirtualHost Está Ativo

```bash
# Listar todos os vhosts carregados
apache2ctl -S

# Exemplo saída:
# VirtualHost configuration:
# *:80    ipaccontabilidade.com.br (/etc/apache2/conf.d/vhost_001_ipac.conf:2)
# *:443   ipaccontabilidade.com.br (/etc/apache2/conf.d/vhost_001_ipac.conf:25)
# *:443   api.ipaccontabilidade.com.br (/etc/apache2/conf.d/vhost_002_api.conf:25)

# Verificar qual vhost responde
curl -I https://ipaccontabilidade.com.br | head -5
# Header Server mostra qual vhost respondeu
```

---

# PARTE 3: VER, EDITAR E DEBUGAR .htaccess

## Via cPanel File Manager (Fácil)

```
PASSO 1: Abrir File Manager
┌──────────────────────────────┐
│ cPanel Home                   │
│ → File Manager                │
│ → Selecione: public_html      │
│ → Clique na pasta             │
└──────────────────────────────┘

PASSO 2: Ver arquivo .htaccess
┌──────────────────────────────┐
│ Procure: .htaccess            │
│ Clique: Edit (ícone lápis)    │
│ Vê conteúdo no editor         │
└──────────────────────────────┘

PASSO 3: Editar
┌──────────────────────────────┐
│ Faça mudanças                 │
│ Clique: Save Changes          │
│ Apache recarrega automaticamente
│                               │
│ ✅ Sem restart necessário!    │
└──────────────────────────────┘

PASSO 4: Criar .htaccess em subfolder
┌──────────────────────────────┐
│ Navegue para: /admin          │
│ Clique: + File                │
│ Nome: .htaccess               │
│ Conteúdo: cole regras         │
│ Salve                         │
└──────────────────────────────┘
```

## Via SSH (Completo)

```bash
# Ver .htaccess raiz
cat /home/ipacuser/public_html/.htaccess

# Ver .htaccess subfolder
cat /home/ipacuser/public_html/admin/.htaccess

# Editar com nano
nano /home/ipacuser/public_html/.htaccess

# Editar com vim
vim /home/ipacuser/public_html/.htaccess

# Criar novo .htaccess
echo "RewriteEngine On" > /home/ipacuser/public_html/admin/.htaccess

# Adicionar mais linhas (sem sobrescrever)
echo "AuthType Basic" >> /home/ipacuser/public_html/admin/.htaccess

# Ver permissões
ls -la /home/ipacuser/public_html/.htaccess
# Esperado: -rw-r--r-- 1 ipacuser ipacuser

# Mudar permissões se necessário
chmod 644 /home/ipacuser/public_html/.htaccess
```

## Testar .htaccess

```bash
# Testar se regra funciona (rewrite)
curl -I http://seu-dominio.com.br/usuarios/123
# Procure: Location: https:// (se redireciona)

# Testar compressão
curl -I https://seu-dominio.com.br/style.css | grep "Content-Encoding"
# Esperado: Content-Encoding: gzip

# Testar cache headers
curl -I https://seu-dominio.com.br/logo.png | grep "Cache-Control"
# Esperado: Cache-Control: public, max-age=31536000

# Testar autenticação (admin)
curl -I https://seu-dominio.com.br/admin/dashboard
# Esperado: HTTP/1.1 401 Unauthorized (sem credenciais)

# Com credenciais
curl -I -u usuario:senha https://seu-dominio.com.br/admin/dashboard
# Esperado: HTTP/1.1 200 OK
```

---

# PARTE 4: DEBUGAR PROBLEMAS

## Verificar Error Log

```bash
# Ver últimas 50 linhas de erro
tail -50 /home/ipacuser/logs/error_log

# Ver erros em tempo real
tail -f /home/ipacuser/logs/error_log
# (Ctrl+C para parar)

# Procurar por "htaccess"
grep "htaccess" /home/ipacuser/logs/error_log

# Procurar por "RewriteRule"
grep "RewriteRule" /home/ipacuser/logs/error_log

# Ver últimas 24 horas
find /home/ipacuser/logs -name "error_log" -mtime -1 -exec tail -100 {} \;
```

## Verificar Access Log

```bash
# Ver últimas requisições
tail -20 /home/ipacuser/logs/access_log

# Ver status codes 500 (erro)
grep " 500 " /home/ipacuser/logs/access_log

# Ver status codes 301/302 (redirecionamento)
grep " 30[12] " /home/ipacuser/logs/access_log

# Contar por status code
cat /home/ipacuser/logs/access_log | cut -d' ' -f9 | sort | uniq -c | sort -rn
```

## Verificar Módulos Carregados

```bash
# Ver módulos carregados
apache2ctl -M

# Exemplo saída:
# core_module (static)
# mpm_prefork_module (static)
# http_module (static)
# ssl_module (shared)
# rewrite_module (shared)
# deflate_module (shared)
# expires_module (shared)
# headers_module (shared)

# Verificar módulo específico
apache2ctl -M | grep rewrite
# Saída: rewrite_module (shared)

# Se não aparecer: módulo não está ativo!
```

## Testar Configuração Antes de Aplicar

```bash
# Método 1: Testar sintaxe
apachectl configtest
# Esperado: Syntax OK

# Método 2: Testar com debug
apachectl -t

# Método 3: Fazer reload (sem restart)
apachectl reload
# Recarrega Apache sem derrubar conexões ativas

# Método 4: Reload com debug
apachectl graceful
# Graceful reload (espera requisições terminarem)
```

---

# PARTE 5: ESTRUTURA DE PASTAS - LOCAIS REAIS

## Estrutura Completa VPS

```bash
# Ver estrutura completa
tree /home/ipacuser/public_html -L 3

# Se tree não existe, use:
find /home/ipacuser/public_html -type f -name ".htaccess" | sort
find /home/ipacuser/public_html -type f -name "*.php" | head -20 | sort

# Ver permissões de todos .htaccess
find /home/ipacuser/public_html -name ".htaccess" -exec ls -l {} \;
```

## Criar Estrutura com .htaccess Múltiplos

```bash
# Criar pastas
mkdir -p /home/ipacuser/public_html/{admin,api/v1,api/v2,uploads,includes}

# Criar .htaccess raiz
cat > /home/ipacuser/public_html/.htaccess << 'EOF'
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
EOF

# Criar .htaccess admin
cat > /home/ipacuser/public_html/admin/.htaccess << 'EOF'
AuthType Basic
AuthName "Admin Area"
AuthUserFile /home/ipacuser/public_html/.htpasswd
Require valid-user
EOF

# Criar .htaccess api
cat > /home/ipacuser/public_html/api/.htaccess << 'EOF'
Header set X-API-Version 2.0
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?endpoint=$1 [QSA,L]
EOF

# Criar .htaccess uploads
cat > /home/ipacuser/public_html/uploads/.htaccess << 'EOF'
php_flag engine off
<Files ".htaccess">
    Deny from all
</Files>
EOF

# Criar .htaccess includes (bloqueia acesso)
cat > /home/ipacuser/public_html/includes/.htaccess << 'EOF'
Deny from all
EOF

# Verificar criação
find /home/ipacuser/public_html -name ".htaccess" -type f
```

---

# PARTE 6: PROBLEMAS COMUNS E SOLUÇÕES

## Problema 1: 500 Internal Server Error com .htaccess

```
CAUSA: Erro de sintaxe em .htaccess

DIAGNÓSTICO:
1. Verifique error_log
   tail -50 /home/ipacuser/logs/error_log
   
2. Procure por linha exata do erro
   [core:error] ... .htaccess line 15

3. Verifique linha 15 no arquivo
   sed -n '15p' /home/ipacuser/public_html/.htaccess

SOLUÇÃO RÁPIDA:
# Renomear .htaccess (desativar)
mv /home/ipacuser/public_html/.htaccess /home/ipacuser/public_html/.htaccess.old

# Recarregar Apache
apachectl reload

# Se 500 desaparece: problema estava em .htaccess
# Se 500 continua: problema está em PHP/app
```

## Problema 2: Redirecionamento Infinito

```
CAUSA: .htaccess está redirecionando para si mesmo

SINTOMA:
curl -i http://seu-dominio.com.br
# HTTP/1.1 301 Moved Permanently
# Location: https://seu-dominio.com.br
# HTTP/1.1 301 Moved Permanently
# Location: https://seu-dominio.com.br (INFINITO!)

DIAGNÓSTICO:
1. Verifique regra de HTTPS
   grep "RewriteCond.*HTTPS" /home/ipacuser/public_html/.htaccess
   
2. Verifique regra de WWW
   grep "RewriteCond.*HTTP_HOST" /home/ipacuser/public_html/.htaccess

SOLUÇÃO:
# Comentar regra suspeita
sed -i 's/^RewriteCond/#RewriteCond/' /home/ipacuser/public_html/.htaccess

# Testar
curl -I http://seu-dominio.com.br

# Se funciona: issue identificada, refaça regra
```

## Problema 3: .htaccess Ignorado

```
CAUSA: AllowOverride None no vhost.conf

DIAGNÓSTICO:
1. Verifique vhost.conf
   grep "AllowOverride" /etc/apache2/conf.d/vhost_*.conf
   
2. Se mostrar "AllowOverride None": aí está o problema

SOLUÇÃO:
# Via cPanel: Apache Configuration → Edit
# Procure: AllowOverride None
# Substitua: AllowOverride All
# Salve

# Via SSH (cuidado!):
sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/conf.d/vhost_*.conf
apachectl reload
```

## Problema 4: Compressão Não Ativa

```
CAUSA: mod_deflate não ativo ou .htaccess errado

DIAGNÓSTICO:
1. Verificar módulo
   apache2ctl -M | grep deflate
   # Esperado: deflate_module (shared)
   
2. Testar compressão
   curl -I https://seu-dominio.com.br/style.css | grep "Content-Encoding"
   # Esperado: Content-Encoding: gzip

SOLUÇÃO:
# Ativar mod_deflate
a2enmod deflate
apachectl reload

# Ou adicionar em .htaccess
cat >> /home/ipacuser/public_html/.htaccess << 'EOF'
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript
</IfModule>
EOF
```

## Problema 5: Cache Não Funciona

```
CAUSA: mod_expires não ativo ou headers errados

DIAGNÓSTICO:
curl -I https://seu-dominio.com.br/logo.png | grep "Cache-Control"
# Se vazio: cache não configurado

SOLUÇÃO:
# Adicionar em .htaccess
cat >> /home/ipacuser/public_html/.htaccess << 'EOF'
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
</IfModule>
EOF

# Ou headers
cat >> /home/ipacuser/public_html/.htaccess << 'EOF'
<IfModule mod_headers.c>
    Header set Cache-Control "public, max-age=31536000"
</IfModule>
EOF
```

---

# PARTE 7: CHECKLIST DE IMPLEMENTAÇÃO REAL

## Antes de Levar para Produção

```bash
# 1. Backup do .htaccess original
cp /home/ipacuser/public_html/.htaccess /home/ipacuser/public_html/.htaccess.backup

# 2. Criar .htaccess novo
nano /home/ipacuser/public_html/.htaccess
# Cole conteúdo novo

# 3. Verificar sintaxe
apachectl configtest
# Esperado: Syntax OK

# 4. Recarregar Apache
apachectl reload

# 5. Testar cada funcionalidade
echo "=== Testando HTTPS ==="
curl -I http://seu-dominio.com.br 2>&1 | head -3

echo "=== Testando compressão ==="
curl -I https://seu-dominio.com.br/style.css | grep "Content-Encoding"

echo "=== Testando cache ==="
curl -I https://seu-dominio.com.br/logo.png | grep "Cache-Control"

echo "=== Testando autenticação ==="
curl -I https://seu-dominio.com.br/admin 2>&1 | head -3

# 6. Monitorar erro_log por 5 min
echo "Monitorando error_log por 5 segundos..."
timeout 5 tail -f /home/ipacuser/logs/error_log

# 7. Se algum erro: reverter backup
# cp /home/ipacuser/public_html/.htaccess.backup /home/ipacuser/public_html/.htaccess
# apachectl reload
```

---

# PARTE 8: REFERÊNCIA RÁPIDA DE COMANDOS

```bash
# Ver arquivo
cat /caminho/arquivo

# Editar arquivo
nano /caminho/arquivo
# Ctrl+O para salvar, Ctrl+X para sair

# Verificar sintaxe Apache
apachectl configtest

# Recarregar Apache
apachectl reload

# Restart Apache (mata conexões)
apachectl restart

# Ver módulos ativos
apache2ctl -M

# Ver VirtualHosts
apache2ctl -S

# Ver últimas linhas de log
tail -50 /caminho/error_log

# Ver em tempo real
tail -f /caminho/error_log

# Procurar em arquivo
grep "texto" /caminho/arquivo

# Procurar linha por número
sed -n '15p' /caminho/arquivo

# Mudar permissões
chmod 644 /caminho/.htaccess

# Criar arquivo
cat > /caminho/arquivo << 'EOF'
conteúdo aqui
EOF

# Adicionar ao arquivo
cat >> /caminho/arquivo << 'EOF'
mais conteúdo
EOF

# Copiar arquivo
cp /caminho/original /caminho/backup

# Renomear arquivo
mv /caminho/arquivo /caminho/novo_nome
```

---

**Use este guia como referência durante implementação real!** 🛠️

# 📘 APACHE HTTP SERVER — LIVRO DIDÁTICO E CIENTÍFICO

## Configuração Completa: httpd.conf, httpd-vhosts.conf e .htaccess
### PHP, Laravel e Outras Linguagens | Desenvolvimento e Produção | Windows e Linux

---

**Metodologia:** Ensino Individual e Ativo (Prof. Pierluigi Piazzi)
**Estrutura de cada capítulo:** Teoria → Anatomia → Exemplo Comentado → Armadilha → Exercício
**Nível:** Intermediário (calibrado para profissional de TI com base prévia em .htaccess Nível 2)
**Idioma:** Português (Brasil)

---

## 📑 SUMÁRIO

1. [Fundamentos Científicos: Como o Apache Funciona](#capítulo-1)
2. [Instalação e Localização dos Arquivos (Windows e Linux)](#capítulo-2)
3. [httpd.conf — Anatomia Completa](#capítulo-3)
4. [MPMs e Módulos — O Motor do Servidor](#capítulo-4)
5. [httpd-vhosts.conf — Virtual Hosts](#capítulo-5)
6. [.htaccess — Papel, Limites e Quando NÃO Usar](#capítulo-6)
7. [PHP no Apache — mod_php vs PHP-FPM](#capítulo-7)
8. [Laravel — Configuração Completa (Dev e Produção)](#capítulo-8)
9. [Outras Linguagens — Python, Node.js e Proxy Reverso](#capítulo-9)
10. [Ambiente de Desenvolvimento vs Produção — Hardening](#capítulo-10)
11. [SSL/TLS — HTTPS em Dev e Produção](#capítulo-11)
12. [Troubleshooting Científico — Diagnóstico de Erros](#capítulo-12)
13. [Quiz de Retenção — 10 Perguntas](#quiz)
14. [Exercícios Resolvidos](#exercícios)

---

<a name="capítulo-1"></a>
# CAPÍTULO 1 — FUNDAMENTOS CIENTÍFICOS: COMO O APACHE FUNCIONA

## 1.1 Teoria

O **Apache HTTP Server** (httpd) é um servidor web modular baseado em **processos e threads**. Para configurá-lo corretamente, você precisa entender **3 conceitos fundamentais**:

### Conceito 1 — O ciclo requisição → resposta

```
Cliente (navegador)
   │  1. Requisição HTTP (GET /index.php HTTP/1.1)
   ▼
Apache (porta 80/443)
   │  2. Resolve qual VirtualHost atende (pelo Host: header)
   │  3. Mapeia a URL para um arquivo físico (DocumentRoot)
   │  4. Aplica regras (.htaccess, Rewrite, Auth, etc.)
   │  5. Se for PHP → entrega ao interpretador (mod_php ou PHP-FPM)
   ▼
Resposta HTTP (HTML, JSON, etc.)
```

### Conceito 2 — Hierarquia de configuração (ordem de precedência)

O Apache lê configurações em **camadas**, da mais ampla para a mais específica:

```
1º  httpd.conf            → configuração GLOBAL do servidor
2º  arquivos Include      → httpd-vhosts.conf, httpd-ssl.conf, conf.d/*, etc.
3º  <VirtualHost>         → configuração POR SITE
4º  <Directory>           → configuração POR DIRETÓRIO
5º  .htaccess             → configuração POR DIRETÓRIO em tempo de execução
```

📌 **Regra científica:** quanto mais específico o escopo, maior a precedência. Uma diretiva em `.htaccess` sobrescreve a mesma diretiva no `<Directory>` do vhost (desde que o `AllowOverride` permita).

### Conceito 3 — Configuração estática vs dinâmica

| Característica | httpd.conf / vhosts | .htaccess |
|---|---|---|
| Momento da leitura | Na inicialização (startup/reload) | **A cada requisição** |
| Custo de performance | Zero após o boot | Alto (I/O de disco por request) |
| Requer reiniciar Apache? | Sim (`reload`/`restart`) | Não |
| Quem usa | Administrador do servidor | Desenvolvedor/hospedagem compartilhada |

## 1.2 Anatomia — Sintaxe das diretivas

Toda configuração Apache segue o padrão:

```apache
Diretiva valor1 [valor2 ...]        # Diretiva simples
<Contexto parâmetro>                # Bloco de contexto (container)
    Diretiva valor
</Contexto>
```

Contextos (containers) principais:

| Container | O que delimita |
|---|---|
| `<VirtualHost>` | Um site/domínio |
| `<Directory>` | Um diretório do **sistema de arquivos** (caminho físico) |
| `<Location>` | Um caminho de **URL** (caminho lógico) |
| `<Files>` | Arquivos por nome/padrão |
| `<FilesMatch>` | Arquivos por expressão regular |
| `<IfModule>` | Executa só se o módulo estiver carregado |

## 1.3 ⚠️ Armadilha

**`<Directory>` usa caminho FÍSICO, `<Location>` usa caminho de URL.** Confundir os dois é o erro conceitual mais comum:

```apache
<Directory "/var/www/site/admin">   # ✅ Caminho no disco
<Location "/admin">                 # ✅ Caminho na URL
<Directory "/admin">                # ❌ ERRO: /admin não existe no disco
```

## 1.4 Exercício mental

Sem olhar a resposta: se uma diretiva `Options -Indexes` está no httpd.conf global e um `.htaccess` contém `Options +Indexes`, qual vence? *(Resposta: o .htaccess, se AllowOverride Options estiver habilitado — escopo mais específico vence.)*

---

<a name="capítulo-2"></a>
# CAPÍTULO 2 — INSTALAÇÃO E LOCALIZAÇÃO DOS ARQUIVOS

## 2.1 Teoria

A **localização dos arquivos muda conforme o SO e a distribuição**. Este é o mapa mental que você deve memorizar:

## 2.2 Anatomia — Mapa de arquivos por ambiente

### 🐧 Linux — Debian/Ubuntu (pacote `apache2`)

```
/etc/apache2/
├── apache2.conf              # ← Equivale ao httpd.conf (arquivo principal)
├── ports.conf                # Diretivas Listen (portas)
├── mods-available/           # Módulos disponíveis (.load e .conf)
├── mods-enabled/             # Módulos ATIVOS (links simbólicos)
├── sites-available/          # Virtual hosts disponíveis
│   ├── 000-default.conf      # Vhost padrão porta 80
│   └── default-ssl.conf      # Vhost padrão porta 443
├── sites-enabled/            # Vhosts ATIVOS (links simbólicos)
└── conf-available|enabled/   # Configurações extras
```

Comandos de gerenciamento (exclusivos Debian/Ubuntu):

```bash
sudo a2enmod rewrite          # Ativa o módulo mod_rewrite (cria link em mods-enabled)
sudo a2dismod rewrite         # Desativa o módulo
sudo a2ensite meusite.conf    # Ativa um vhost de sites-available
sudo a2dissite 000-default    # Desativa o vhost padrão
sudo apachectl configtest     # Valida sintaxe ANTES de reiniciar (Syntax OK)
sudo systemctl reload apache2 # Recarrega configs SEM derrubar conexões ativas
sudo systemctl restart apache2 # Reinicia completamente (derruba conexões)
```

### 🐧 Linux — RHEL/CentOS/AlmaLinux/Rocky (pacote `httpd`)

```
/etc/httpd/
├── conf/
│   └── httpd.conf            # Arquivo principal (nome clássico)
├── conf.d/                   # Vhosts e configs extras (*.conf são incluídos)
│   ├── ssl.conf
│   └── meusite.conf          # ← Vhosts criados aqui (não existe a2ensite)
└── conf.modules.d/           # Carregamento de módulos
```

```bash
sudo httpd -t                          # Testa sintaxe
sudo systemctl reload httpd            # Recarrega
sudo apachectl -M                      # Lista módulos carregados
```

### 🪟 Windows — XAMPP (ambiente de desenvolvimento)

```
C:\xampp\apache\
├── conf\
│   ├── httpd.conf                     # Arquivo principal
│   └── extra\
│       ├── httpd-vhosts.conf          # ← Virtual hosts
│       └── httpd-ssl.conf             # SSL
├── logs\                              # error.log e access.log
└── bin\httpd.exe                      # Binário
C:\xampp\htdocs\                       # DocumentRoot padrão
C:\Windows\System32\drivers\etc\hosts  # ← "DNS local" p/ domínios de dev
```

Validação e serviço no Windows:

```powershell
C:\xampp\apache\bin\httpd.exe -t       # Testa sintaxe (Syntax OK)
C:\xampp\apache\bin\httpd.exe -M       # Lista módulos
# Reiniciar: pelo XAMPP Control Panel (Stop → Start) ou:
net stop Apache2.4 && net start Apache2.4   # Se instalado como serviço
```

### 🪟 Windows — Apache Lounge (binário oficial p/ produção Windows)

```
C:\Apache24\
├── conf\httpd.conf
├── conf\extra\httpd-vhosts.conf
└── bin\httpd.exe
```

```powershell
# Instalar como serviço do Windows (executar como Administrador):
C:\Apache24\bin\httpd.exe -k install -n "Apache2.4"
C:\Apache24\bin\httpd.exe -k start
C:\Apache24\bin\httpd.exe -k restart
```

## 2.3 ⚠️ Armadilha

No **Debian/Ubuntu**, editar diretamente arquivos em `sites-enabled/` ou `mods-enabled/` é um erro grave de manutenção: esses diretórios contêm **links simbólicos**. Sempre edite em `*-available/` e ative com `a2ensite`/`a2enmod`. Isso preserva a rastreabilidade e permite desativar sem apagar.

## 2.4 Exercício

Em qual arquivo você configuraria a porta 8080 no Ubuntu? *(Resposta: `/etc/apache2/ports.conf`, adicionando `Listen 8080` — e no vhost, `<VirtualHost *:8080>`.)*

---

<a name="capítulo-3"></a>
# CAPÍTULO 3 — httpd.conf: ANATOMIA COMPLETA

## 3.1 Teoria

O `httpd.conf` define o **comportamento global**: onde o servidor mora, quem ele é, o que carrega, e as **permissões padrão de segurança**. A filosofia correta de produção é: **negar tudo globalmente e liberar apenas o necessário por vhost** (princípio do menor privilégio).

## 3.2 Anatomia — httpd.conf comentado linha a linha

Abaixo, um `httpd.conf` de referência (sintaxe Apache 2.4). Cada linha comentada:

```apache
# ============================================================
# SEÇÃO 1 — IDENTIDADE E LOCALIZAÇÃO DO SERVIDOR
# ============================================================

ServerRoot "/etc/httpd"
# ↑ Diretório-base onde o Apache procura arquivos relativos
#   (módulos, includes, logs relativos). No Windows: "C:/Apache24"
#   Windows aceita barra normal "/" — prefira-a a "\" nas configs.

Listen 80
# ↑ Porta TCP em que o Apache escuta. Pode repetir para múltiplas portas:
#   Listen 8080
#   Listen 192.168.0.10:80  → escuta só nesse IP

ServerAdmin ti@ipaconline.com.br
# ↑ E-mail exibido em páginas de erro padrão do servidor.

ServerName servidor01.ipaconline.com.br:80
# ↑ Nome canônico do servidor. Se omitido, o Apache tenta DNS reverso
#   e emite o warning "Could not reliably determine the server's FQDN".
#   Em dev, "ServerName localhost" resolve o aviso.

# ============================================================
# SEÇÃO 2 — CARREGAMENTO DE MÓDULOS
# ============================================================

LoadModule rewrite_module modules/mod_rewrite.so
# ↑ Carrega o mod_rewrite (reescrita de URLs — essencial p/ Laravel).
#   No Windows a extensão continua .so (não .dll) desde o Apache 2.4.
#   No Debian/Ubuntu NÃO se usa LoadModule direto: usa-se "a2enmod".

LoadModule deflate_module modules/mod_deflate.so
# ↑ Compressão gzip das respostas (economia de banda).

LoadModule headers_module modules/mod_headers.so
# ↑ Manipulação de cabeçalhos HTTP (segurança: X-Frame-Options etc.).

LoadModule ssl_module modules/mod_ssl.so
# ↑ Suporte a HTTPS/TLS.

LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
# ↑ Dupla necessária para conectar o Apache ao PHP-FPM via FastCGI.

# ============================================================
# SEÇÃO 3 — USUÁRIO DE EXECUÇÃO (APENAS LINUX)
# ============================================================

User apache
Group apache
# ↑ Processos-filhos rodam com este usuário SEM privilégios.
#   Debian/Ubuntu usa "www-data". NUNCA use root.
#   No Windows não existe este conceito — o serviço roda como
#   LocalSystem (padrão) ou conta de serviço configurada no services.msc.

# ============================================================
# SEÇÃO 4 — SEGURANÇA GLOBAL (PRINCÍPIO DO MENOR PRIVILÉGIO)
# ============================================================

<Directory />
    AllowOverride none
    Require all denied
</Directory>
# ↑ BLOQUEIA todo o sistema de arquivos por padrão.
#   "AllowOverride none" → nenhum .htaccess é lido fora do que liberarmos.
#   "Require all denied" → nega acesso HTTP a qualquer caminho.
#   Cada DocumentRoot precisará liberar explicitamente (ver vhosts).

ServerTokens Prod
# ↑ Cabeçalho "Server:" mostra apenas "Apache" (esconde versão/SO).
#   Valores: Full > OS > Minor > Minimal > Major > Prod (mais seguro).

ServerSignature Off
# ↑ Remove a assinatura (versão + host) do rodapé das páginas de erro.

TraceEnable Off
# ↑ Desativa o método HTTP TRACE (mitiga ataques XST).

# ============================================================
# SEÇÃO 5 — DOCUMENTROOT PADRÃO E ARQUIVOS DE ÍNDICE
# ============================================================

DocumentRoot "/var/www/html"
# ↑ Diretório servido quando nenhum vhost específico casa.

<Directory "/var/www/html">
    Options FollowSymLinks
    # ↑ Permite seguir links simbólicos. NÃO incluir "Indexes" em
    #   produção (evita listagem de diretórios sem index).

    AllowOverride None
    # ↑ Em produção: None (ignora .htaccess → +performance).
    #   Em hospedagem compartilhada/dev: All ou lista específica.

    Require all granted
    # ↑ Libera acesso HTTP a este diretório (sintaxe Apache 2.4).
    #   ⚠️ Sintaxe 2.2 ("Order allow,deny / Allow from all") é OBSOLETA.
</Directory>

<IfModule dir_module>
    DirectoryIndex index.php index.html
    # ↑ Ordem de busca do arquivo de índice. index.php PRIMEIRO
    #   em servidores PHP — senão um index.html esquecido "rouba" a home.
</IfModule>

<Files ".ht*">
    Require all denied
</Files>
# ↑ Impede download de .htaccess e .htpasswd via navegador.

# ============================================================
# SEÇÃO 6 — LOGS
# ============================================================

ErrorLog "logs/error_log"
# ↑ Caminho relativo ao ServerRoot. Windows: "logs/error.log".

LogLevel warn
# ↑ Verbosidade: debug > info > notice > warn > error > crit.
#   Produção: warn. Diagnóstico de rewrite: "LogLevel warn rewrite:trace3".

<IfModule log_config_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
    # ↑ Define o formato "combined": IP, data, requisição, status,
    #   bytes, referer e user-agent.
    CustomLog "logs/access_log" combined
    # ↑ Log de acessos usando o formato definido acima.
</IfModule>

# ============================================================
# SEÇÃO 7 — INCLUDES (MODULARIZAÇÃO)
# ============================================================

Include conf/extra/httpd-vhosts.conf
# ↑ Importa o arquivo de virtual hosts (padrão XAMPP/Apache Lounge).
#   No XAMPP esta linha já existe COMENTADA — basta descomentar.

IncludeOptional conf.d/*.conf
# ↑ Inclui todos os .conf do diretório (RHEL). "Optional" não falha
#   se o diretório estiver vazio.
```

## 3.3 ⚠️ Armadilhas

1. **Misturar sintaxe 2.2 com 2.4**: `Order allow,deny` + `Require all granted` no mesmo bloco gera comportamento imprevisível. Use **somente** `Require` (2.4).
2. **Esquecer o `configtest`**: um erro de sintaxe em produção derruba o servidor no restart. Sempre: `apachectl configtest` (ou `httpd -t`) **antes** de `reload`.
3. **`AllowOverride All` global**: faz o Apache procurar `.htaccess` em **cada nível de diretório** de **cada requisição** — degradação de performance mensurável.

## 3.4 Exercício

Qual a diferença prática entre `systemctl reload` e `systemctl restart`? *(Resposta: reload aplica a nova config sem derrubar conexões em andamento (graceful); restart mata os processos e reinicia — causa indisponibilidade momentânea.)*

---

<a name="capítulo-4"></a>
# CAPÍTULO 4 — MPMs E MÓDULOS: O MOTOR DO SERVIDOR

## 4.1 Teoria

O **MPM (Multi-Processing Module)** define **como** o Apache atende conexões simultâneas. A escolha do MPM determina qual modelo de PHP você poderá usar.

| MPM | Modelo | Uso típico | Compatível com mod_php? |
|---|---|---|---|
| **prefork** | 1 processo por conexão (sem threads) | Legado com mod_php | ✅ Sim |
| **worker** | Processos com múltiplas threads | Intermediário | ❌ Não (PHP não é thread-safe*) |
| **event** | Threads + gerência assíncrona de keep-alive | **Padrão moderno** | ❌ Não → use PHP-FPM |
| **winnt** | 1 processo, multithread | **Único do Windows** | ✅ (com PHP Thread Safe) |

\* O PHP possui build ZTS (thread-safe), mas o consenso de engenharia é: **MPM event + PHP-FPM** em Linux.

## 4.2 Anatomia — Verificando e configurando o MPM

```bash
# Linux — descobrir o MPM ativo:
apachectl -V | grep -i mpm
# Saída esperada: Server MPM: event

# Ubuntu — trocar de prefork para event (necessário desativar mod_php antes):
sudo a2dismod php8.3          # Desativa mod_php (incompatível com event)
sudo a2dismod mpm_prefork     # Desativa o MPM antigo
sudo a2enmod mpm_event        # Ativa o MPM moderno
sudo systemctl restart apache2
```

Tuning do MPM event (arquivo `mods-available/mpm_event.conf` no Ubuntu):

```apache
<IfModule mpm_event_module>
    StartServers             2
    # ↑ Processos criados no boot.
    MinSpareThreads         25
    # ↑ Threads ociosas mínimas mantidas prontas.
    MaxSpareThreads         75
    # ↑ Acima disso, threads ociosas são encerradas.
    ThreadsPerChild         25
    # ↑ Threads por processo-filho.
    MaxRequestWorkers      150
    # ↑ TETO de requisições simultâneas. Fórmula de dimensionamento:
    #   MaxRequestWorkers ≈ (RAM disponível p/ Apache) / (RAM por processo)
    MaxConnectionsPerChild   0
    # ↑ 0 = processo nunca é reciclado. Use ex. 10000 se suspeitar
    #   de memory leak em módulos.
</IfModule>
```

## 4.3 Módulos essenciais — checklist

| Módulo | Função | Ativação Ubuntu |
|---|---|---|
| `mod_rewrite` | Reescrita de URL (Laravel, WordPress) | `a2enmod rewrite` |
| `mod_ssl` | HTTPS | `a2enmod ssl` |
| `mod_headers` | Cabeçalhos de segurança | `a2enmod headers` |
| `mod_deflate` | Compressão gzip | `a2enmod deflate` |
| `mod_expires` | Cache de estáticos | `a2enmod expires` |
| `mod_proxy` + `mod_proxy_fcgi` | Ponte p/ PHP-FPM | `a2enmod proxy proxy_fcgi` |
| `mod_http2` | Protocolo HTTP/2 | `a2enmod http2` |

## 4.4 ⚠️ Armadilha

Instalar `libapache2-mod-php` no Ubuntu **força a troca silenciosa para mpm_prefork**, derrubando a performance do event. Se seu objetivo é PHP-FPM, instale apenas `php8.3-fpm` e **nunca** o pacote mod-php.

## 4.5 Exercício

Um servidor com 2 GB de RAM livres para o Apache, onde cada processo consome ~50 MB. Qual valor razoável de MaxRequestWorkers? *(Resposta: ≈ 2048/50 ≈ 40 workers, com margem de segurança.)*

---

<a name="capítulo-5"></a>
# CAPÍTULO 5 — httpd-vhosts.conf: VIRTUAL HOSTS

## 5.1 Teoria

**Virtual Host** é o mecanismo que permite **um único servidor Apache hospedar N sites**. O Apache decide qual vhost atende cada requisição por:

1. **Name-based** (padrão): compara o cabeçalho `Host:` da requisição com `ServerName`/`ServerAlias`;
2. **IP-based**: por endereço IP distinto;
3. **Port-based**: por porta distinta.

📌 **Lei fundamental:** se nenhum vhost casa com o `Host:` solicitado, o Apache serve o **PRIMEIRO vhost definido** para aquele IP:porta. Por isso o primeiro vhost deve ser um "catch-all" seguro.

## 5.2 Anatomia — vhost de DESENVOLVIMENTO (Windows/XAMPP)

**Passo 1** — Descomentar no `httpd.conf`:

```apache
Include conf/extra/httpd-vhosts.conf
```

**Passo 2** — Editar `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
# ============================================================
# VHOST 1 — CATCH-ALL (mantém o localhost padrão funcionando)
# ============================================================
<VirtualHost *:80>
    # ↑ "*:80" = qualquer IP desta máquina, porta 80.
    ServerName localhost
    # ↑ Nome que casa com http://localhost
    DocumentRoot "C:/xampp/htdocs"
    # ↑ Pasta raiz. Windows: use "/" e SEMPRE aspas se houver espaços.
</VirtualHost>

# ============================================================
# VHOST 2 — PROJETO IPACONT (domínio fictício de desenvolvimento)
# ============================================================
<VirtualHost *:80>
    ServerName ipacont.local
    # ↑ Domínio de dev. Convenção: sufixo .local ou .test
    #   (⚠️ NUNCA use .dev — é TLD real do Google com HSTS forçado).

    ServerAlias www.ipacont.local
    # ↑ Nomes adicionais que também casam com este vhost.

    DocumentRoot "C:/projetos/ipacont/public"
    # ↑ Aponta para /public se for Laravel (ver Capítulo 8).

    <Directory "C:/projetos/ipacont/public">
        Options -Indexes +FollowSymLinks
        # ↑ -Indexes: proíbe listagem de diretório.
        AllowOverride All
        # ↑ Em DEV, All facilita testar .htaccess do framework.
        Require all granted
        # ↑ Libera o acesso (lembre: o global nega tudo).
    </Directory>

    ErrorLog "logs/ipacont-error.log"
    CustomLog "logs/ipacont-access.log" combined
    # ↑ Logs SEPARADOS por projeto = diagnóstico 10x mais rápido.
</VirtualHost>
```

**Passo 3** — Registrar o domínio no "DNS local" do Windows.
Edite **como Administrador** o arquivo `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1    ipacont.local
127.0.0.1    www.ipacont.local
```

**Passo 4** — Validar e reiniciar:

```powershell
C:\xampp\apache\bin\httpd.exe -t     # Syntax OK?
# Reiniciar Apache pelo XAMPP Control Panel
```

## 5.3 Anatomia — vhost de PRODUÇÃO (Linux/Ubuntu)

Arquivo `/etc/apache2/sites-available/ipacont.conf`:

```apache
<VirtualHost *:80>
    ServerName sistema.ipaconline.com.br
    # ↑ Em produção usa-se domínio REAL com DNS público apontado.

    # Redireciona TODO o tráfego HTTP para HTTPS (única função deste vhost):
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
    # ↑ %{HTTP_HOST} preserva o domínio; R=301 = redirect permanente;
    #   L = última regra.
</VirtualHost>

<VirtualHost *:443>
    ServerName sistema.ipaconline.com.br
    DocumentRoot /var/www/ipacont/public

    # ---------- TLS ----------
    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/sistema.ipaconline.com.br/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/sistema.ipaconline.com.br/privkey.pem
    # ↑ Caminhos padrão do Certbot/Let's Encrypt.

    # ---------- Diretório ----------
    <Directory /var/www/ipacont/public>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        # ↑ PRODUÇÃO: None. As regras do .htaccess do Laravel são
        #   MOVIDAS para cá (performance — ver Capítulo 6).
        Require all granted

        # Regras do Laravel embutidas no vhost (substituem o .htaccess):
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [L]
    </Directory>

    # ---------- PHP-FPM ----------
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
        # ↑ Encaminha .php ao PHP-FPM via socket Unix (mais rápido que TCP).
    </FilesMatch>

    # ---------- Cabeçalhos de segurança ----------
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    ErrorLog  ${APACHE_LOG_DIR}/ipacont-error.log
    CustomLog ${APACHE_LOG_DIR}/ipacont-access.log combined
</VirtualHost>
```

Ativação:

```bash
sudo a2ensite ipacont.conf
sudo apachectl configtest && sudo systemctl reload apache2
```

## 5.4 ⚠️ Armadilhas

1. **Descomentou o Include de vhosts no XAMPP e "perdeu" o localhost**: ao ativar vhosts, o comportamento padrão muda — sempre inclua o vhost catch-all `localhost` como **primeiro** bloco.
2. **Esquecer o arquivo hosts**: sem a entrada em `hosts` (Windows) ou `/etc/hosts` (Linux), o domínio `.local` não resolve e o navegador tenta buscar na internet.
3. **`NameVirtualHost`**: diretiva **obsoleta** desde o Apache 2.4. Se encontrar em tutoriais antigos, ignore.

## 5.5 Exercício

Você tem 3 vhosts porta 80: `siteA.com`, `siteB.com`, `siteC.com` (nesta ordem). Um cliente acessa pelo IP puro (sem Host). Qual site responde? *(Resposta: siteA.com — primeiro vhost definido é o padrão.)*

---

<a name="capítulo-6"></a>
# CAPÍTULO 6 — .htaccess: PAPEL, LIMITES E QUANDO NÃO USAR

## 6.1 Teoria

O `.htaccess` é um **fragmento de configuração por diretório, lido a cada requisição**. Sua existência se justifica em **dois cenários**:

1. **Hospedagem compartilhada** (cPanel/WHM): você não tem acesso ao httpd.conf;
2. **Desenvolvimento**: mudanças aplicadas sem reiniciar o servidor.

Em **produção com acesso root**, a documentação oficial do Apache é taxativa: *evite .htaccess; mova tudo para o vhost e use `AllowOverride None`*.

**Custo científico do .htaccess:** para servir `/var/www/site/public/css/app.css`, o Apache verifica a existência de `.htaccess` em `/`, `/var`, `/var/www`, `/var/www/site`, `/var/www/site/public` **e** `/var/www/site/public/css` — 6 operações de I/O extras **por requisição**.

## 6.2 Anatomia — AllowOverride: a chave-mestra

O `.htaccess` **só funciona** se o `<Directory>` correspondente permitir:

```apache
AllowOverride None
# ↑ .htaccess totalmente ignorado (produção otimizada).

AllowOverride All
# ↑ Aceita todas as categorias de diretivas (dev/hosting).

AllowOverride FileInfo Options=Indexes,FollowSymLinks AuthConfig
# ↑ Controle granular por categoria:
#   FileInfo   → mod_rewrite, tipos, handlers
#   AuthConfig → autenticação (AuthType, Require valid-user)
#   Indexes    → DirectoryIndex, listagem
#   Limit      → controle de acesso por host
#   Options    → apenas as opções listadas após "="
```

## 6.3 Exemplo — .htaccess universal para PHP puro (sem framework)

```apache
# ---------- Segurança básica ----------
Options -Indexes
# ↑ Sem listagem de diretórios.

<FilesMatch "\.(env|ini|log|sql|bak|config)$">
    Require all denied
</FilesMatch>
# ↑ Bloqueia download de arquivos sensíveis por extensão.

# ---------- URLs amigáveis simples ----------
RewriteEngine On
# ↑ Liga o motor de reescrita NESTE diretório.

RewriteCond %{REQUEST_FILENAME} !-f
# ↑ Condição: a requisição NÃO é um arquivo real...
RewriteCond %{REQUEST_FILENAME} !-d
# ↑ ...E NÃO é um diretório real...
RewriteRule ^(.*)$ index.php?rota=$1 [QSA,L]
# ↑ ...então envia tudo ao index.php passando o caminho como parâmetro.
#   QSA = preserva a query string original; L = para de processar regras.

# ---------- Compressão ----------
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
    # ↑ Comprime respostas desses MIME types com gzip.
</IfModule>

# ---------- Cache de estáticos ----------
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp "access plus 1 month"
    ExpiresByType text/css   "access plus 1 week"
    # ↑ Instruem o navegador a cachear por período definido.
</IfModule>
```

## 6.4 ⚠️ Armadilhas

1. **`.htaccess` invisível no Windows**: o Explorer esconde arquivos iniciados por ponto. Use o editor/IDE ou `dir /a` no CMD.
2. **Erro 500 imediato ao criar .htaccess**: diretiva não permitida pelo `AllowOverride` ou módulo (`mod_rewrite`) não carregado. Diagnóstico: `error.log` mostrará "*... not allowed here*".
3. **RewriteRule em .htaccess NÃO vê a barra inicial**: em `.htaccess`, o padrão casa contra o caminho **relativo** (`admin/lista`), enquanto no vhost casa contra `/admin/lista`. Regras copiadas entre contextos quebram por isso.

## 6.5 Exercício

Por que `AllowOverride All` + `.htaccess` com 30 regras é pior para performance do que as mesmas 30 regras no vhost? *(Resposta: o vhost é compilado 1x no boot; o .htaccess é lido, parseado e aplicado a CADA requisição, além da varredura de diretórios ancestrais.)*

---

<a name="capítulo-7"></a>
# CAPÍTULO 7 — PHP NO APACHE: mod_php vs PHP-FPM

## 7.1 Teoria

Existem **duas arquiteturas** para o Apache executar PHP:

```
ARQUITETURA A — mod_php (embutido)
┌─────────────────────────────┐
│  Processo Apache            │
│  ┌───────────────────────┐  │   PHP vive DENTRO do Apache.
│  │ Interpretador PHP     │  │   Todo processo Apache carrega o PHP
│  └───────────────────────┘  │   (mesmo servindo só uma imagem!).
└─────────────────────────────┘

ARQUITETURA B — PHP-FPM (FastCGI Process Manager)
┌──────────────┐   socket    ┌──────────────────┐
│   Apache     │────────────►│  Pool PHP-FPM    │   PHP é um SERVIÇO
│  (mpm_event) │  unix/tcp   │  (processos PHP) │   separado, com pool
└──────────────┘             └──────────────────┘   próprio e reciclagem.
```

| Critério | mod_php | PHP-FPM |
|---|---|---|
| MPM compatível | prefork (Linux) / winnt (Windows) | event/worker |
| Memória p/ estáticos | Desperdiça (PHP carregado sempre) | Zero (Apache puro serve) |
| Isolamento por site | Não (mesmo usuário) | Sim (1 pool = 1 usuário) |
| php.ini via .htaccess | `php_value`/`php_flag` funcionam | ❌ **Geram erro 500** |
| Recomendação | Dev Windows (XAMPP) | **Produção Linux** |

## 7.2 Anatomia — Setup A: mod_php no Windows (XAMPP)

O XAMPP já vem pronto. O que ele configura por baixo dos panos (no httpd-xampp.conf):

```apache
LoadModule php_module "C:/xampp/php/php8_module.dll"
# ↑ No Windows o mod_php é uma DLL (única exceção à regra do .so).
#   Requer build PHP "Thread Safe" (TS) — pois o MPM winnt usa threads.

AddHandler application/x-httpd-php .php
# ↑ Associa a extensão .php ao interpretador.

PHPIniDir "C:/xampp/php"
# ↑ Onde o mod_php procura o php.ini.
```

## 7.3 Anatomia — Setup B: PHP-FPM no Linux (produção)

**Passo 1** — Instalar:

```bash
# Ubuntu:
sudo apt install php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl
sudo systemctl enable --now php8.3-fpm

# RHEL/Alma:
sudo dnf install php-fpm php-mysqlnd php-mbstring php-xml
sudo systemctl enable --now php-fpm
```

**Passo 2** — Ativar módulos e configuração no Apache (Ubuntu):

```bash
sudo a2enmod proxy_fcgi setenvif       # Módulos da ponte FastCGI
sudo a2enconf php8.3-fpm               # Config pronta do pacote Ubuntu
sudo systemctl reload apache2
```

O que o `php8.3-fpm.conf` ativado faz (comentado):

```apache
<FilesMatch ".+\.ph(ar|p|tml)$">
    SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    # ↑ Todo arquivo .php/.phar/.phtml é encaminhado ao socket Unix
    #   do FPM. "fcgi://localhost" é apenas identificador de backend.
</FilesMatch>
```

**Passo 3** — Pool do FPM (isolamento por aplicação) — `/etc/php/8.3/fpm/pool.d/ipacont.conf`:

```ini
[ipacont]                          ; Nome do pool
user = ipacont                     ; Usuário do SO que executa o PHP
group = ipacont                    ; (isolamento: cada app um usuário)
listen = /run/php/ipacont.sock     ; Socket exclusivo deste pool
listen.owner = www-data            ; Dono do socket = usuário do Apache
listen.group = www-data
pm = dynamic                       ; Gerência dinâmica de processos
pm.max_children = 20               ; Teto de processos PHP simultâneos
pm.start_servers = 4               ; Processos criados no boot
pm.min_spare_servers = 2           ; Mínimo ocioso
pm.max_spare_servers = 6           ; Máximo ocioso
pm.max_requests = 500              ; Recicla processo após N requests
php_admin_value[memory_limit] = 256M   ; Sobrescreve php.ini SÓ neste pool
```

## 7.4 ⚠️ Armadilha crítica

Ao migrar de mod_php para PHP-FPM, qualquer `.htaccess` contendo `php_value upload_max_filesize 64M` passa a gerar **erro 500** ("Invalid command php_value"). Correção: mover a configuração para o pool do FPM (`php_admin_value[...]`) ou para um arquivo `.user.ini` na raiz da aplicação.

## 7.5 Exercício

Por que servir uma imagem .png com mod_php+prefork desperdiça memória? *(Resposta: cada processo prefork carrega o interpretador PHP inteiro (~30-80 MB) mesmo para conteúdo estático que não precisa de PHP.)*

---

<a name="capítulo-8"></a>
# CAPÍTULO 8 — LARAVEL: CONFIGURAÇÃO COMPLETA (DEV E PRODUÇÃO)

## 8.1 Teoria

O Laravel impõe **duas exigências arquiteturais** ao servidor web:

1. **DocumentRoot deve apontar para `/public`** — nunca para a raiz do projeto. Motivo científico de segurança: fora de `/public` ficam `.env` (credenciais do banco!), `storage/` (logs, sessões) e `vendor/`. Se o DocumentRoot for a raiz, `http://site.com/.env` **expõe suas senhas**.
2. **Front Controller Pattern**: toda requisição que não seja arquivo físico deve cair em `public/index.php`, que despacha para o roteador do framework.

## 8.2 O .htaccess nativo do Laravel — dissecado linha a linha

Arquivo `public/.htaccess` (vem com o framework):

```apache
<IfModule mod_rewrite.c>
    # ↑ Só executa se mod_rewrite existir (evita 500 em servidor sem ele).

    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
        # ↑ -MultiViews: desliga a "adivinhação" de arquivos do Apache
        #   (ex.: /api encontrando api.php sozinho — conflita com rotas).
        #   -Indexes: sem listagem de diretórios.
    </IfModule>

    RewriteEngine On
    # ↑ Liga o motor de reescrita.

    # ---------- Cabeçalho Authorization ----------
    RewriteCond %{HTTP:Authorization} .
    # ↑ Se existe o header Authorization (tokens Bearer de APIs)...
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    # ↑ ...repassa-o como variável de ambiente ao PHP.
    #   Sem isto, autenticação por token (Sanctum/Passport) FALHA
    #   em alguns setups FastCGI, pois o header é descartado.

    # ---------- Remove barra final de URLs que não são pastas ----------
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)/$ /$1 [L,R=301]
    # ↑ /produtos/ → /produtos (canonicalização p/ SEO).

    # ---------- Front Controller ----------
    RewriteCond %{REQUEST_FILENAME} !-d
    # ↑ Não é diretório real...
    RewriteCond %{REQUEST_FILENAME} !-f
    # ↑ ...nem arquivo real (css/js/img passam direto SEM tocar o PHP)...
    RewriteRule ^ index.php [L]
    # ↑ ...então TUDO vai para index.php. O padrão "^" (vazio) casa
    #   qualquer coisa sem capturar — o Laravel lê a URL via $_SERVER.
</IfModule>
```

## 8.3 Exemplo — DEV no Windows (XAMPP + vhost)

`httpd-vhosts.conf`:

```apache
<VirtualHost *:80>
    ServerName finanipac.test
    DocumentRoot "C:/projetos/finanipac/public"
    # ↑ SEMPRE /public. Nunca C:/projetos/finanipac.

    <Directory "C:/projetos/finanipac/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        # ↑ Dev: deixa o .htaccess do Laravel trabalhar.
        Require all granted
    </Directory>

    ErrorLog "logs/finanipac-error.log"
</VirtualHost>
```

`C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1    finanipac.test
```

Verificação do mod_rewrite no XAMPP (httpd.conf — deve estar DEScomentada):

```apache
LoadModule rewrite_module modules/mod_rewrite.so
```

## 8.4 Exemplo — PRODUÇÃO no Linux (Ubuntu + PHP-FPM + regras no vhost)

`/etc/apache2/sites-available/finanipac.conf`:

```apache
<VirtualHost *:443>
    ServerName finanipac.ipaconline.com.br
    DocumentRoot /var/www/finanipac/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/finanipac.ipaconline.com.br/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/finanipac.ipaconline.com.br/privkey.pem

    <Directory /var/www/finanipac/public>
        Options -Indexes -MultiViews +FollowSymLinks
        AllowOverride None
        # ↑ Produção: .htaccess IGNORADO. Regras replicadas abaixo:
        Require all granted

        RewriteEngine On
        RewriteCond %{HTTP:Authorization} .
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
        # ↑ Mesmas regras do .htaccess, agora compiladas 1x no boot.
    </Directory>

    # Bloqueio explícito de acesso a pastas sensíveis (defesa em camadas):
    <DirectoryMatch "^/var/www/finanipac/(storage|bootstrap/cache)">
        Require all denied
    </DirectoryMatch>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"

    ErrorLog  ${APACHE_LOG_DIR}/finanipac-error.log
    CustomLog ${APACHE_LOG_DIR}/finanipac-access.log combined
</VirtualHost>
```

**Permissões de arquivos (Linux) — parte que mais gera erro 500 no Laravel:**

```bash
cd /var/www/finanipac
sudo chown -R deploy:www-data .
# ↑ Dono = usuário de deploy; grupo = usuário do Apache/FPM.
sudo find . -type f -exec chmod 644 {} \;
sudo find . -type d -exec chmod 755 {} \;
# ↑ Padrão: arquivos 644, pastas 755.
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwx  storage bootstrap/cache
# ↑ SOMENTE storage e bootstrap/cache precisam de ESCRITA pelo PHP
#   (logs, sessões, views compiladas). Jamais 777 no projeto todo.
```

**Otimizações pós-deploy:**

```bash
php artisan config:cache    # Compila configs em arquivo único
php artisan route:cache     # Compila rotas
php artisan view:cache      # Pré-compila Blade
composer install --no-dev --optimize-autoloader
```

## 8.5 ⚠️ Armadilhas

1. **DocumentRoot na raiz do projeto**: expõe `.env`. É a vulnerabilidade nº 1 de deploys Laravel amadores.
2. **Erro "The stream or file .../laravel.log could not be opened"**: permissões erradas em `storage/` — ver bloco de permissões acima.
3. **Rotas retornam 404 mas `/` funciona**: mod_rewrite desativado ou `AllowOverride None` sem replicar as regras no vhost.
4. **API retorna 401 sempre**: faltou a regra do `HTTP_AUTHORIZATION` (comum quando as regras foram copiadas parcialmente para o vhost).

## 8.6 Exercício

Explique por que colocar as regras de rewrite no vhost com `AllowOverride None` é simultaneamente mais **rápido** e mais **seguro** que manter o `.htaccess`. *(Resposta: rápido — regras compiladas 1x no boot, sem I/O por request; seguro — nenhum arquivo gravável pelo deploy pode alterar o comportamento do servidor.)*

---

<a name="capítulo-9"></a>
# CAPÍTULO 9 — OUTRAS LINGUAGENS: PYTHON, NODE.JS E PROXY REVERSO

## 9.1 Teoria

Fora do PHP, o padrão moderno é o Apache atuar como **proxy reverso**: a aplicação roda em seu próprio servidor de aplicação (Gunicorn, Uvicorn, Node), e o Apache fica na frente cuidando de TLS, logs, compressão e estáticos.

```
Internet ──► Apache :443 (TLS, gzip, estáticos) ──► App :8000 (Gunicorn/Node)
```

## 9.2 Exemplo — Python (Flask/FastAPI/Django via proxy reverso)

```bash
sudo a2enmod proxy proxy_http headers   # Módulos do proxy HTTP
```

```apache
<VirtualHost *:443>
    ServerName api.ipaconline.com.br
    # (bloco SSL igual aos anteriores...)

    ProxyPreserveHost On
    # ↑ Repassa o Host: original à aplicação (essencial p/ frameworks
    #   que geram URLs absolutas, como Django).

    ProxyPass        /static/ !
    # ↑ "!" = NÃO faz proxy de /static/ — o Apache serve direto.
    Alias /static/ /var/www/api/static/
    <Directory /var/www/api/static>
        Require all granted
    </Directory>

    ProxyPass        / http://127.0.0.1:8000/
    # ↑ Todo o resto vai para o Gunicorn/Uvicorn na porta 8000.
    ProxyPassReverse / http://127.0.0.1:8000/
    # ↑ Reescreve headers Location dos redirects vindos do backend
    #   (sem isto, um redirect da app exporia http://127.0.0.1:8000).

    RequestHeader set X-Forwarded-Proto "https"
    # ↑ Informa à app que a conexão original era HTTPS.
</VirtualHost>
```

📌 Alternativa legada: `mod_wsgi` (embute o Python no Apache, análogo ao mod_php). Funciona, mas o proxy reverso é hoje o padrão da indústria por desacoplar deploy da app do servidor web.

## 9.3 Exemplo — Node.js (Express/Next) com WebSocket

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel rewrite
```

```apache
<VirtualHost *:443>
    ServerName app.ipaconline.com.br
    # (bloco SSL...)

    ProxyPreserveHost On

    # WebSockets (socket.io, Next.js HMR não se aplica em prod):
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    # ↑ Se o cliente pediu upgrade para WebSocket...
    RewriteRule ^/(.*)$ ws://127.0.0.1:3000/$1 [P,L]
    # ↑ ...proxy com esquema ws:// ([P] = proxy).

    ProxyPass        / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/
</VirtualHost>
```

## 9.4 ⚠️ Armadilha

`ProxyPass / http://127.0.0.1:8000` **sem a barra final** e `ProxyPass /app/ http://host/` com barras inconsistentes causam URLs quebradas. Regra: **ou as duas pontas terminam com barra, ou nenhuma**.

## 9.5 Exercício

Por que o Apache deve servir `/static/` diretamente em vez de repassar ao Gunicorn? *(Resposta: servir estáticos é a especialidade do Apache (sendfile, cache, zero Python envolvido); passar pelo Gunicorn desperdiça workers da aplicação com tarefas triviais.)*

---

<a name="capítulo-10"></a>
# CAPÍTULO 10 — DESENVOLVIMENTO vs PRODUÇÃO: TABELA-MESTRA E HARDENING

## 10.1 Teoria

Ambientes têm **objetivos opostos**: dev maximiza *feedback rápido e visibilidade de erros*; produção maximiza *segurança, performance e estabilidade*. Cada diretiva deve ser decidida sob essa ótica.

## 10.2 Tabela-mestra de decisão

| Diretiva/Aspecto | 🔧 Desenvolvimento | 🏭 Produção |
|---|---|---|
| `AllowOverride` | `All` (agilidade) | `None` (regras no vhost) |
| `Options Indexes` | Opcional | **Sempre `-Indexes`** |
| `ServerTokens` | Full (debug) | **`Prod`** |
| `ServerSignature` | On | **`Off`** |
| `LogLevel` | `debug` ou `info` | `warn` |
| PHP `display_errors` | `On` | **`Off`** (log_errors On) |
| PHP no Apache | mod_php (XAMPP) | **PHP-FPM + mpm_event** |
| HTTPS | Opcional (mkcert) | **Obrigatório + HSTS** |
| Domínio | `.test` / `.local` + hosts | DNS real |
| `TraceEnable` | On/Off | **`Off`** |
| Cabeçalhos de segurança | Opcionais | **Obrigatórios** |
| `Timeout` | 300 (padrão) | 60 (mitiga slow-loris) |
| Usuário do processo | padrão | dedicado por app (pools FPM) |

## 10.3 Bloco de hardening pronto para produção (comentado)

Arquivo sugerido: `/etc/apache2/conf-available/hardening.conf` → `a2enconf hardening`:

```apache
# ---------- Ocultação de identidade ----------
ServerTokens Prod            # Header Server: apenas "Apache"
ServerSignature Off          # Sem versão nas páginas de erro
TraceEnable Off              # Bloqueia HTTP TRACE

# ---------- Timeouts anti-DoS ----------
Timeout 60                   # Máx. de espera por I/O de request
KeepAlive On                 # Reusa conexões TCP (performance)
MaxKeepAliveRequests 100     # Requests por conexão persistente
KeepAliveTimeout 5           # Segundos aguardando novo request

<IfModule reqtimeout_module>
    RequestReadTimeout header=20-40,minrate=500 body=20,minrate=500
    # ↑ Mitiga slow-loris: cliente deve enviar headers em 20-40s
    #   mantendo taxa mínima de 500 bytes/s.
</IfModule>

# ---------- Limites de requisição ----------
LimitRequestBody 20971520    # Corpo máx. 20 MB (ajuste p/ uploads)
LimitRequestFields 100       # Máx. de headers por request
LimitRequestLine 8190        # Tamanho máx. da linha de requisição

# ---------- Cabeçalhos de segurança globais ----------
<IfModule headers_module>
    Header always set X-Content-Type-Options "nosniff"
    # ↑ Impede o navegador de "adivinhar" MIME types (anti-XSS).
    Header always set X-Frame-Options "SAMEORIGIN"
    # ↑ Anti-clickjacking: só o próprio site pode enquadrar em iframe.
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always unset X-Powered-By
    # ↑ Remove o header que denuncia a versão do PHP.
</IfModule>

# ---------- Bloqueios de arquivos sensíveis ----------
<FilesMatch "^\.">
    Require all denied       # Qualquer arquivo iniciado por ponto (.env, .git*)
</FilesMatch>
<DirectoryMatch "/\.git">
    Require all denied       # Diretório .git exposto = código-fonte vazado
</DirectoryMatch>
```

## 10.4 Compressão e cache de produção

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml
    AddOutputFilterByType DEFLATE application/javascript application/json
    AddOutputFilterByType DEFLATE image/svg+xml
    # ↑ NÃO comprimir jpg/png/webp/zip — já são comprimidos (CPU à toa).
</IfModule>

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/webp        "access plus 1 year"
    ExpiresByType image/png         "access plus 1 year"
    ExpiresByType text/css          "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    # ↑ Com assets versionados (Vite/Mix gera hash no nome),
    #   1 ano é seguro: o nome muda a cada build.
</IfModule>
```

## 10.5 ⚠️ Armadilha

Ativar HSTS (`Strict-Transport-Security`) **antes** de garantir que TODO o site funciona em HTTPS trava o domínio no navegador dos usuários por `max-age` segundos. Comece com `max-age=300`, valide, depois suba para 31536000.

## 10.6 Exercício

Por que não comprimimos imagens JPEG com mod_deflate? *(Resposta: JPEG já é comprimido; gzip acrescenta CPU e pode até AUMENTAR o tamanho.)*

---

<a name="capítulo-11"></a>
# CAPÍTULO 11 — SSL/TLS: HTTPS EM DEV E PRODUÇÃO

## 11.1 Teoria

TLS exige um **certificado** (chave pública assinada) + **chave privada**. A diferença entre dev e produção está em **quem assina**:

- **Produção**: uma CA pública (Let's Encrypt — gratuito) → navegadores confiam automaticamente;
- **Dev**: você mesmo assina (self-signed ou mkcert) → é preciso instruir a máquina a confiar.

## 11.2 Produção (Linux) — Let's Encrypt com Certbot

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d sistema.ipaconline.com.br
# ↑ O plugin --apache: valida o domínio, emite o certificado,
#   EDITA o vhost automaticamente e configura o redirect 80→443.
sudo certbot renew --dry-run
# ↑ Testa a renovação automática (o timer do systemd renova sozinho).
```

Pré-requisitos: DNS do domínio apontando para o servidor; portas 80 e 443 abertas no firewall.

## 11.3 Desenvolvimento — mkcert (Windows e Linux)

O `mkcert` cria uma **CA local confiável** — sem avisos de segurança no navegador:

```powershell
# Windows (PowerShell como Admin, via Chocolatey):
choco install mkcert
mkcert -install                      # Instala a CA local no Windows
mkcert ipacont.local "*.ipacont.local"
# ↑ Gera: ipacont.local+1.pem (cert) e ipacont.local+1-key.pem (chave)
```

Vhost SSL de dev (XAMPP — httpd-vhosts.conf):

```apache
<VirtualHost *:443>
    ServerName ipacont.local
    DocumentRoot "C:/projetos/ipacont/public"

    SSLEngine on
    SSLCertificateFile    "C:/certs/ipacont.local+1.pem"
    SSLCertificateKeyFile "C:/certs/ipacont.local+1-key.pem"

    <Directory "C:/projetos/ipacont/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Garanta no httpd.conf do XAMPP (normalmente já ativos):

```apache
LoadModule ssl_module modules/mod_ssl.so
Listen 443
```

## 11.4 ⚠️ Armadilha

Certificado self-signed "na unha" (openssl) gera o aviso vermelho do navegador E quebra chamadas AJAX/APIs internas. Para dev, **mkcert é sempre superior**: a CA é instalada no repositório de confiança do SO.

## 11.5 Exercício

Por que o Certbot precisa da porta 80 aberta mesmo que o site só sirva 443? *(Resposta: o desafio HTTP-01 de validação de domínio do Let's Encrypt ocorre via porta 80.)*

---

<a name="capítulo-12"></a>
# CAPÍTULO 12 — TROUBLESHOOTING CIENTÍFICO

## 12.1 Método de diagnóstico (sempre nesta ordem)

```
1. Sintaxe      → apachectl configtest / httpd -t
2. Serviço      → systemctl status apache2 | XAMPP Control Panel
3. Porta        → ss -tlnp | grep :80   (Linux)
                  netstat -ano | findstr :80   (Windows)
4. Logs         → tail -f /var/log/apache2/error.log
5. Módulos      → apachectl -M | grep rewrite
6. Vhosts       → apachectl -S   (mostra QUAL vhost casa com cada nome!)
```

## 12.2 Tabela de erros × causas × soluções

| Sintoma | Causa provável | Solução |
|---|---|---|
| Apache não inicia (Windows) | Porta 80 ocupada (Skype, IIS, World Wide Web Publishing) | `netstat -ano \| findstr :80` → matar processo ou `Listen 8080` |
| **403 Forbidden** | `Require all denied` herdado do global; permissões do SO; SELinux | Adicionar `Require all granted` no `<Directory>`; no RHEL: `chcon -R -t httpd_sys_content_t /var/www/site` |
| **500 imediato com .htaccess** | Diretiva bloqueada por AllowOverride; módulo ausente; `php_value` com FPM | Ler error.log ("not allowed here"); `a2enmod rewrite`; mover p/ pool FPM |
| **404 em todas as rotas Laravel** | mod_rewrite off ou AllowOverride None sem regras no vhost | `a2enmod rewrite` + conferir vhost |
| Baixa o arquivo .php em vez de executar | Handler PHP não configurado | Conferir SetHandler/AddHandler (Cap. 7) |
| Vhost errado respondendo | Host não casa com nenhum ServerName | `apachectl -S` para ver o mapeamento real |
| Mudou config e nada aconteceu | Editou arquivo não incluído; esqueceu reload | Conferir Includes; `systemctl reload` |
| **502/503 com PHP-FPM** | FPM parado ou socket com caminho errado | `systemctl status php8.3-fpm`; conferir caminho do .sock |
| Site abre por IP mas não por domínio (dev) | Falta entrada no arquivo hosts | Editar hosts como Admin/root |

## 12.3 Debug avançado de rewrite (Apache 2.4)

```apache
# No vhost (NUNCA em produção por muito tempo — log gigante):
LogLevel warn rewrite:trace4
# ↑ Grava no error.log cada passo de avaliação das RewriteRules.
```

## 12.4 SELinux (RHEL/Alma) — o "403 fantasma"

```bash
getenforce                    # Enforcing = SELinux ativo
sudo setsebool -P httpd_can_network_connect 1
# ↑ Permite ao Apache abrir conexões de rede (proxy reverso, FPM via TCP).
sudo restorecon -Rv /var/www/site
# ↑ Restaura contextos de segurança corretos após copiar arquivos.
```

---

<a name="quiz"></a>
# 🧠 QUIZ DE RETENÇÃO — 10 PERGUNTAS

Responda antes de conferir o gabarito (ao final da lista).

**1.** Qual a diferença fundamental entre o momento em que o Apache lê o `httpd.conf` e o `.htaccess`, e qual o impacto disso na performance?

**2.** No Ubuntu, qual é a sequência correta de comandos para criar e ativar um novo virtual host chamado `ipacont.conf`?

**3.** Por que o DocumentRoot de um projeto Laravel deve apontar obrigatoriamente para a pasta `/public`? Cite o risco concreto de não fazer isso.

**4.** Um servidor usa MPM `event`. É possível usar `mod_php` nele? Justifique e apresente a alternativa correta.

**5.** Após migrar de mod_php para PHP-FPM, um `.htaccess` com `php_value memory_limit 256M` começou a gerar erro 500. Explique a causa e dê duas soluções.

**6.** O que faz a regra `RewriteCond %{HTTP:Authorization} .` seguida de `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]` no .htaccess do Laravel, e o que quebra sem ela?

**7.** Em um servidor com 3 vhosts na porta 80, uma requisição chega com um `Host:` que não casa com nenhum `ServerName`. O que acontece?

**8.** Qual a diferença entre `<Directory>` e `<Location>` e por que `<Directory "/admin">` provavelmente está errado?

**9.** Em produção, qual valor de `AllowOverride` é recomendado e o que deve ser feito com as regras que estavam no `.htaccess`?

**10.** No Windows/XAMPP, quais são os DOIS arquivos que precisam ser editados para que `http://meusite.test` funcione localmente?

---

## ✅ GABARITO COMENTADO

**1.** O `httpd.conf` é lido **uma única vez** na inicialização/reload (compilado em memória); o `.htaccess` é lido **a cada requisição**, incluindo a varredura de todos os diretórios ancestrais. Impacto: `.htaccess` adiciona múltiplas operações de I/O por request — por isso produção usa `AllowOverride None`.

**2.** Criar `/etc/apache2/sites-available/ipacont.conf` → `sudo a2ensite ipacont.conf` → `sudo apachectl configtest` → `sudo systemctl reload apache2`. (O configtest antes do reload é disciplina obrigatória.)

**3.** Porque fora de `/public` ficam `.env` (credenciais de banco e APP_KEY), `storage/` e o código-fonte. Com DocumentRoot na raiz, `http://site.com/.env` fica acessível publicamente — vazamento direto de senhas.

**4.** Não (na prática). O mod_php exige prefork pois o PHP padrão não é thread-safe, e o event usa threads. Alternativa correta: **PHP-FPM** conectado via `mod_proxy_fcgi` (SetHandler `proxy:unix:...`).

**5.** Causa: as diretivas `php_value`/`php_flag` pertencem ao mod_php; sem ele, o Apache não reconhece o comando → 500 "Invalid command". Soluções: (a) configurar no pool do FPM com `php_admin_value[memory_limit]=256M`; (b) usar `.user.ini` na raiz da aplicação.

**6.** Ela captura o cabeçalho `Authorization` e o repassa ao PHP como variável de ambiente. Sem ela, em configurações FastCGI o header é descartado e toda autenticação por token Bearer (Sanctum, Passport, JWT) retorna 401.

**7.** O Apache serve o **primeiro vhost definido** para aquele IP:porta (vhost padrão). Por isso a boa prática é o primeiro bloco ser um catch-all controlado.

**8.** `<Directory>` referencia caminho **físico** no disco; `<Location>` referencia caminho de **URL**. `<Directory "/admin">` aponta para uma pasta `/admin` na raiz do sistema de arquivos — que provavelmente não existe; o correto seria `<Location "/admin">` ou o caminho físico completo.

**9.** `AllowOverride None`. As regras do `.htaccess` devem ser **movidas para dentro do bloco `<Directory>` do vhost**, ganhando performance (compilação única) e segurança (config imutável por arquivos da aplicação).

**10.** (a) `C:\xampp\apache\conf\extra\httpd-vhosts.conf` (criar o `<VirtualHost>` — e garantir que o Include dele está descomentado no httpd.conf); (b) `C:\Windows\System32\drivers\etc\hosts` (mapear `127.0.0.1 meusite.test`), editado como Administrador.

---

<a name="exercícios"></a>
# 🏋️ EXERCÍCIOS RESOLVIDOS

## Exercício 1 — Vhost duplo dev (HTTP + HTTPS) no Windows

**Enunciado:** Crie os vhosts para o projeto `ipacont` em `C:/projetos/ipacont` (Laravel), respondendo em `http://ipacont.test` e `https://ipacont.test` com certificado mkcert.

**Solução:**

```apache
<VirtualHost *:80>
    ServerName ipacont.test
    DocumentRoot "C:/projetos/ipacont/public"
    <Directory "C:/projetos/ipacont/public">
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

<VirtualHost *:443>
    ServerName ipacont.test
    DocumentRoot "C:/projetos/ipacont/public"
    SSLEngine on
    SSLCertificateFile    "C:/certs/ipacont.test.pem"
    SSLCertificateKeyFile "C:/certs/ipacont.test-key.pem"
    <Directory "C:/projetos/ipacont/public">
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

+ hosts: `127.0.0.1 ipacont.test` + `mkcert -install && mkcert ipacont.test`.

## Exercício 2 — Migração .htaccess → vhost em produção

**Enunciado:** Um site PHP puro em `/var/www/portal` usa este `.htaccess`:

```apache
Options -Indexes
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*)$ index.php?r=$1 [QSA,L]
```

Migre para o vhost com `AllowOverride None`.

**Solução:**

```apache
<Directory /var/www/portal>
    Options -Indexes +FollowSymLinks
    AllowOverride None
    Require all granted

    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^/?(.*)$ index.php?r=$1 [QSA,L]
    # ↑ Atenção ao "^/?": no contexto de Directory/vhost o caminho
    #   pode incluir a barra inicial — "/?": torna-a opcional,
    #   mantendo compatível com o padrão que vinha do .htaccess.
</Directory>
```

Depois: `sudo apachectl configtest && sudo systemctl reload apache2` e **apagar** o `.htaccess` (evita confusão futura — ele seria ignorado de qualquer forma).

## Exercício 3 — Diagnóstico

**Enunciado:** Após deploy Laravel no Ubuntu, `https://sistema.exemplo.com.br` mostra a home, mas qualquer rota (`/login`) devolve **404 do Apache** (não do Laravel). error.log limpo. Diagnostique.

**Solução (raciocínio):**
1. Home funciona → DocumentRoot correto e PHP executando;
2. 404 **do Apache** (não página 404 do Laravel) → a requisição nunca chegou ao `index.php` → **rewrite não atuou**;
3. Verificações: `apachectl -M | grep rewrite` (módulo carregado?) e conferir `AllowOverride` do vhost;
4. Causa clássica: vhost com `AllowOverride None` **sem** as regras de rewrite replicadas. Correção: adicionar o bloco RewriteEngine/Cond/Rule no `<Directory>` (Cap. 8.4) e `reload`.

---

# 📎 APÊNDICE — CHECKLIST DE DEPLOY (imprima e cole na parede)

```
[ ] apachectl configtest → Syntax OK
[ ] DocumentRoot aponta para /public (frameworks)
[ ] AllowOverride None + regras no vhost
[ ] Options -Indexes em todos os <Directory>
[ ] ServerTokens Prod / ServerSignature Off / TraceEnable Off
[ ] HTTPS ativo + redirect 301 do :80 + HSTS (após validar)
[ ] PHP-FPM: pool dedicado, socket correto no SetHandler
[ ] Permissões: 644/755; escrita só em storage/ e bootstrap/cache
[ ] Logs separados por vhost
[ ] .env, .git e dotfiles bloqueados (FilesMatch "^\.")
[ ] display_errors=Off, log_errors=On (php.ini de produção)
[ ] Certbot renew --dry-run OK
[ ] Backup da configuração anterior antes do reload
```

---

**FIM DO LIVRO** — Próximos passos sugeridos: aprofundamento em mod_security (WAF), HTTP/2 e HTTP/3, e balanceamento de carga com mod_proxy_balancer.

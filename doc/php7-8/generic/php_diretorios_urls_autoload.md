# PHP na Prática: Diretórios, URLs, Querystrings e Autoloading (PSR-4)

> *"Entender como o PHP resolve caminhos, monta URLs e carrega classes é o que separa quem copia código de quem constrói arquitetura."*

---

## Capítulo 1 — Fundamentos

### 1.1 Introdução ao Capítulo

Este livro trata de quatro pilares interligados do desenvolvimento PHP profissional:

1. **Manipulação de diretórios** — como o PHP localiza, lista, cria e navega pelo sistema de arquivos.
2. **Manipulação de URLs** — como decompor, montar e validar endereços web.
3. **Manipulação de querystring** — como ler e construir os parâmetros que trafegam em uma URL.
4. **Importação de código** (`include`, `require`, `include_once`, `require_once`) e sua evolução moderna: **namespaces** e **autoloading PSR-4 via Composer**.

Esses quatro temas parecem distintos à primeira vista, mas compartilham uma mesma preocupação de fundo: **como o PHP resolve referências** — sejam elas referências a arquivos no disco, a recursos na web, a parâmetros de uma requisição, ou a classes dentro de um projeto. Compreender essa lógica de resolução é o fio condutor de todo o capítulo.

### 1.2 Definições Formais

**Diretório (ou pasta):** estrutura hierárquica do sistema de arquivos que agrupa arquivos e outros diretórios. No PHP, diretórios são acessados através de *caminhos* (*paths*), que podem ser **absolutos** (partem da raiz do sistema, ex.: `/var/www/html/app`) ou **relativos** (partem do diretório de execução atual, ex.: `../config`).

**URL (Uniform Resource Locator):** string estruturada que identifica um recurso na web e o mecanismo para acessá-lo. Formalmente, segue a especificação **RFC 3986**, com a estrutura genérica:

```
esquema://usuário:senha@host:porta/caminho?querystring#fragmento
```

**Querystring:** a porção de uma URL que segue o caractere `?`, composta por pares `chave=valor` separados por `&`. É o mecanismo padrão de transmissão de parâmetros em requisições HTTP `GET`.

**Include/Require:** construções de linguagem (não funções, tecnicamente — são *language constructs*) que inserem e avaliam o conteúdo de um arquivo PHP externo no ponto em que são chamadas, permitindo a modularização do código-fonte.

**Namespace:** mecanismo de encapsulamento que evita colisões de nomes entre classes, funções e constantes, organizando o código em um espaço de nomes hierárquico — análogo a diretórios, mas em nível de código, não de sistema de arquivos.

**Autoloading:** mecanismo pelo qual o PHP carrega automaticamente o arquivo que contém a definição de uma classe, no exato momento em que ela é referenciada pela primeira vez, eliminando a necessidade de `include`/`require` manuais.

**PSR-4:** um dos *PHP Standard Recommendations*, publicado pelo **PHP-FIG** (*Framework Interop Group*), que define uma convenção formal de mapeamento entre namespaces e a estrutura de diretórios do projeto, permitindo autoloading previsível e interoperável entre bibliotecas.

### 1.3 Contexto Teórico

Antes do PHP 5.3 (2009), não existiam namespaces nativos. Bibliotecas usavam prefixos manuais em nomes de classes (ex.: `Zend_Db_Adapter`) para simular escopo. A introdução dos namespaces resolveu isso em nível de linguagem. Pouco depois, a comunidade PHP — via PHP-FIG — formalizou convenções de interoperabilidade (as PSRs), das quais a **PSR-4** (2014, substituindo a antiga PSR-0) tornou-se o padrão de fato para autoloading.

Paralelamente, o **Composer** (lançado em 2012) tornou-se o gerenciador de dependências padrão do ecossistema PHP, e sua implementação de autoloading segue rigorosamente a PSR-4 — o que faz da dupla **Composer + PSR-4** a espinha dorsal de praticamente todo projeto PHP moderno (Laravel, Symfony, WordPress moderno, etc.).

Entender diretórios, URLs e querystrings é o pré-requisito prático para entender autoloading: o autoloader nada mais é do que um sistema que **traduz um namespace em um caminho de diretório**, e um roteador web nada mais é do que um sistema que **traduz uma URL/querystring em uma chamada de código**. São o mesmo problema — resolução de referências — aplicado a dois domínios diferentes.

---

## Capítulo 2 — Estrutura Interna

### 2.1 Manipulação de Diretórios

#### 2.1.1 Constantes e Funções Fundamentais de Caminho

O PHP oferece um conjunto rico de funções para trabalhar com caminhos e diretórios:

| Função/Constante | Finalidade |
|---|---|
| `__DIR__` | Constante mágica: diretório absoluto do arquivo atual |
| `__FILE__` | Constante mágica: caminho absoluto completo do arquivo atual |
| `dirname($path)` | Retorna o diretório pai de um caminho |
| `basename($path)` | Retorna o último componente do caminho (arquivo ou pasta) |
| `pathinfo($path)` | Retorna array associativo com `dirname`, `basename`, `extension`, `filename` |
| `realpath($path)` | Resolve o caminho absoluto real, expandindo `..`, `.` e links simbólicos |
| `getcwd()` | Retorna o diretório de trabalho atual (*current working directory*) |
| `DIRECTORY_SEPARATOR` | Constante com o separador de diretório do SO (`/` ou `\`) |

**Por que `__DIR__` é preferível a caminhos relativos?**

Um erro comum de iniciantes é usar `include 'config/database.php';` assumindo que o caminho é relativo ao arquivo que faz o include. **Isso está incorreto**: caminhos relativos em `include`/`require` são resolvidos em relação ao **diretório de trabalho atual do processo** (`getcwd()`), que pode variar dependendo de como o script foi invocado — via CLI de um diretório diferente, via servidor web com `DocumentRoot` distinto, etc.

```php
<?php
// Arquivo: /var/www/app/src/Services/Mailer.php

// ERRADO — depende de onde o PHP foi executado
require 'config/mail.php';

// CORRETO — sempre resolve a partir do diretório deste arquivo
require __DIR__ . '/../../config/mail.php';
```

`__DIR__` é uma constante **mágica**, resolvida em tempo de compilação com base na localização física do arquivo-fonte, tornando o caminho **determinístico** independentemente do contexto de execução.

#### 2.1.2 Navegação e Inspeção de Diretórios

```php
<?php
// Verifica existência
if (is_dir(__DIR__ . '/uploads')) {
    echo "Diretório existe.\n";
}

// Cria diretório (recursivamente, com permissão 0755)
if (!is_dir(__DIR__ . '/uploads/2026')) {
    mkdir(__DIR__ . '/uploads/2026', 0755, true);
}

// Lista conteúdo de um diretório (abordagem clássica)
$handle = opendir(__DIR__ . '/uploads');
while (false !== ($entrada = readdir($handle))) {
    if ($entrada === '.' || $entrada === '..') {
        continue;
    }
    echo $entrada . "\n";
}
closedir($handle);

// Abordagem moderna: scandir()
$arquivos = array_diff(scandir(__DIR__ . '/uploads'), ['.', '..']);
foreach ($arquivos as $arquivo) {
    echo $arquivo . "\n";
}

// Abordagem orientada a objetos: DirectoryIterator / RecursiveDirectoryIterator
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/uploads', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $arquivo) {
    echo $arquivo->getPathname() . "\n";
}
```

**Nuance técnica:** `opendir`/`readdir` operam em nível de *handle* de sistema (mais próximo da chamada de sistema `opendir(3)` do POSIX), enquanto `scandir` retorna um array já materializado — mais simples, porém menos eficiente em memória para diretórios com milhares de arquivos. `RecursiveDirectoryIterator` é a abordagem idiomática para varreduras recursivas em código orientado a objetos, integrando-se ao padrão *Iterator* do SPL (*Standard PHP Library*).

#### 2.1.3 Manipulação de Componentes de Caminho

```php
<?php
$caminho = '/var/www/app/public/images/logo.png';

echo dirname($caminho);      // /var/www/app/public/images
echo basename($caminho);     // logo.png
echo basename($caminho, '.png'); // logo (remove sufixo)

$info = pathinfo($caminho);
/*
[
  'dirname'   => '/var/www/app/public/images',
  'basename'  => 'logo.png',
  'extension' => 'png',
  'filename'  => 'logo',
]
*/

// Construção segura de caminhos multiplataforma
$caminhoConstruido = implode(DIRECTORY_SEPARATOR, ['var', 'www', 'app', 'config.php']);

// realpath resolve links simbólicos e segmentos '..'
$absoluto = realpath(__DIR__ . '/../config/../config/app.php');
// retorna false se o caminho não existir fisicamente
```

**Ponto crítico de segurança — Path Traversal:** quando um caminho é construído a partir de entrada do usuário (por exemplo, um parâmetro de URL indicando qual arquivo servir), é **obrigatório** validar que o caminho resolvido permanece dentro do diretório permitido. Isso é feito comparando o `realpath()` resultante com o `realpath()` do diretório-base:

```php
<?php
function caminhoSeguro(string $base, string $entradaUsuario): string|false
{
    $baseReal = realpath($base);
    $alvoReal = realpath($base . '/' . $entradaUsuario);

    if ($alvoReal === false || !str_starts_with($alvoReal, $baseReal . DIRECTORY_SEPARATOR)) {
        return false; // tentativa de path traversal (ex.: ../../etc/passwd)
    }

    return $alvoReal;
}
```

Sem essa validação, uma entrada como `../../../../etc/passwd` poderia escapar do diretório pretendido — uma vulnerabilidade clássica catalogada no OWASP como **Path Traversal / Directory Traversal**.

---

### 2.2 Manipulação de URLs

#### 2.2.1 Anatomia de uma URL

A RFC 3986 define os componentes formais de uma URI:

```
   https://usuario:senha@www.exemplo.com:8443/produtos/123?cor=azul&tam=M#avaliacoes
   \___/   \_____________________________/\___________/\_________________/\________/
 esquema              autoridade              caminho        querystring    fragmento
```

O PHP expõe essa decomposição através da função `parse_url()`:

```php
<?php
$url = 'https://usuario:senha@www.exemplo.com:8443/produtos/123?cor=azul&tam=M#avaliacoes';

$partes = parse_url($url);
/*
[
  'scheme'   => 'https',
  'host'     => 'www.exemplo.com',
  'port'     => 8443,
  'user'     => 'usuario',
  'pass'     => 'senha',
  'path'     => '/produtos/123',
  'query'    => 'cor=azul&tam=M',
  'fragment' => 'avaliacoes',
]
*/

// Extrair apenas um componente
$host = parse_url($url, PHP_URL_HOST);   // 'www.exemplo.com'
$path = parse_url($url, PHP_URL_PATH);   // '/produtos/123'
```

**Nuance importante:** `parse_url()` é um *parser sintático*, não semântico — ele não valida se a URL é bem formada de acordo com a RFC nem se o host realmente existe. Para validação, usa-se `filter_var()`:

```php
<?php
$valida = filter_var($url, FILTER_VALIDATE_URL);
if ($valida === false) {
    throw new InvalidArgumentException('URL malformada.');
}
```

#### 2.2.2 Construindo URLs Programaticamente

A função inversa de `parse_url()` não existe nativamente no PHP (não há `unparse_url()` embutida), então é comum implementá-la:

```php
<?php
function montarUrl(array $partes): string
{
    $url  = ($partes['scheme'] ?? 'https') . '://';
    $url .= ($partes['user'] ?? '') !== '' 
        ? $partes['user'] . (isset($partes['pass']) ? ':' . $partes['pass'] : '') . '@' 
        : '';
    $url .= $partes['host'] ?? '';
    $url .= isset($partes['port']) ? ':' . $partes['port'] : '';
    $url .= $partes['path'] ?? '';
    $url .= isset($partes['query']) ? '?' . $partes['query'] : '';
    $url .= isset($partes['fragment']) ? '#' . $partes['fragment'] : '';
    return $url;
}
```

#### 2.2.3 Codificação de URLs: `urlencode` vs `rawurlencode`

Um dos pontos de maior confusão entre desenvolvedores PHP é a diferença entre essas duas funções:

| Aspecto | `urlencode()` | `rawurlencode()` |
|---|---|---|
| Espaço vira | `+` | `%20` |
| Baseado em | `application/x-www-form-urlencoded` (formulários HTML) | RFC 3986 (URIs em geral) |
| Uso recomendado | Valores de querystring vindos de formulários `POST`/`GET` | Segmentos de caminho (`path`) e componentes gerais de URI |

```php
<?php
$termo = 'café & pão';

echo urlencode($termo);     // caf%C3%A9+%26+p%C3%A3o
echo rawurlencode($termo);  // caf%C3%A9%20%26%20p%C3%A3o

// Decodificação correspondente
echo urldecode('caf%C3%A9+%26+p%C3%A3o');    // 'café & pão'
echo rawurldecode('caf%C3%A9%20%26%20p%C3%A3o'); // 'café & pão'
```

**Regra prática:** use `rawurlencode()` ao construir um segmento de **caminho** de URL (ex.: `/busca/` . rawurlencode($termo)); use `urlencode()` ao construir uma **querystring** manualmente, pois é o formato que `http_build_query()` também produz por padrão.

---

### 2.3 Manipulação de Querystring

#### 2.3.1 Lendo a Querystring de uma URL

```php
<?php
$url = 'https://loja.com/produtos?categoria=eletronicos&pagina=2&ordenar=preco_asc';

// Passo 1: extrair a string de query
$queryString = parse_url($url, PHP_URL_QUERY);
// 'categoria=eletronicos&pagina=2&ordenar=preco_asc'

// Passo 2: transformar em array associativo
parse_str($queryString, $parametros);
/*
[
  'categoria' => 'eletronicos',
  'pagina'    => '2',
  'ordenar'   => 'preco_asc',
]
*/

echo $parametros['categoria']; // 'eletronicos'
```

**Nuance sobre `parse_str()` e arrays em querystring:** o PHP suporta nativamente notação de array na querystring, seguindo a convenção `chave[]=valor` ou `chave[subchave]=valor`:

```php
<?php
$qs = 'tags[]=php&tags[]=composer&filtro[preco][min]=10&filtro[preco][max]=100';
parse_str($qs, $resultado);
/*
[
  'tags'   => ['php', 'composer'],
  'filtro' => ['preco' => ['min' => '10', 'max' => '100']],
]
*/
```

Isso é o que permite que formulários HTML complexos (com campos de nome `produto[cor][]`) cheguem ao PHP já estruturados em `$_GET` ou `$_POST` como arrays multidimensionais.

**Atenção:** ao usar `parse_str()` com apenas um argumento (`parse_str($qs)`), o PHP cria variáveis soltas no escopo atual (comportamento equivalente ao antigo `register_globals`) — uma prática **fortemente desaconselhada** por razões de segurança e legibilidade. **Sempre** forneça o segundo argumento para capturar o resultado em um array isolado.

#### 2.3.2 Construindo Querystrings

```php
<?php
$parametros = [
    'categoria' => 'eletronicos',
    'pagina'    => 2,
    'tags'      => ['oferta', 'novo'],
];

$qs = http_build_query($parametros);
// 'categoria=eletronicos&pagina=2&tags%5B0%5D=oferta&tags%5B1%5D=novo'

$urlFinal = 'https://loja.com/produtos?' . $qs;
```

`http_build_query()` aceita um terceiro parâmetro para o separador (por padrão `&`, mas pode-se usar `&amp;` para HTML válido em contextos antigos) e um quarto parâmetro para o tipo de encoding (`PHP_QUERY_RFC3986` usa `%20` para espaços; `PHP_QUERY_RFC1738`, o padrão, usa `+`).

```php
<?php
// Encoding RFC 3986 (espaços viram %20, compatível com rawurlencode)
$qs = http_build_query(['q' => 'PHP avançado'], '', '&', PHP_QUERY_RFC3986);
// 'q=PHP%20avan%C3%A7ado'
```

#### 2.3.3 Manipulando `$_GET`, `$_SERVER` e a URL Atual

Em contexto de requisição HTTP real (não CLI isolado), o PHP popula automaticamente `$_GET` a partir da querystring recebida:

```php
<?php
// Requisição: GET /produtos?categoria=eletronicos&pagina=2

$categoria = $_GET['categoria'] ?? 'todas';
$pagina    = (int) ($_GET['pagina'] ?? 1);

// Reconstruindo a URL completa da requisição atual
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$uri       = $_SERVER['REQUEST_URI']; // já inclui path + querystring
$urlAtual  = "{$protocolo}://{$host}{$uri}";
```

**Sempre valide e sanitize** dados de `$_GET`: eles vêm diretamente do usuário e são um dos vetores mais comuns de ataques de **SQL Injection**, **XSS refletido** e **Path Traversal**, como mencionado na seção 2.1.3.

---

### 2.4 Include, Require e a Evolução para Autoloading

#### 2.4.1 As Quatro Construções Clássicas

| Construção | Se o arquivo não existir | Se já foi incluído antes |
|---|---|---|
| `include` | Emite **Warning**, script continua | Inclui novamente |
| `require` | Emite **Fatal Error**, script para | Inclui novamente |
| `include_once` | Emite **Warning**, script continua | **Ignora** (já incluído) |
| `require_once` | Emite **Fatal Error**, script para | **Ignora** (já incluído) |

```php
<?php
// include: falha "suave" — útil para arquivos opcionais (ex.: config de ambiente local)
include __DIR__ . '/config.local.php';

// require: falha "dura" — usado quando o arquivo é indispensável
require __DIR__ . '/config/database.php';

// require_once: evita redefinição de classes/funções ao incluir o mesmo arquivo
// múltiplas vezes através de diferentes cadeias de include
require_once __DIR__ . '/helpers/functions.php';
```

**Por que `*_once` existe?** Em projetos com múltiplos arquivos que se referenciam mutuamente (A inclui B, B inclui C, e A também inclui C diretamente), sem a variante `_once` o PHP tentaria **redeclarar** a mesma classe ou função duas vezes, resultando em `Fatal error: Cannot redeclare function/class`. `require_once`/`include_once` mantêm um registro interno (uma tabela hash de caminhos já resolvidos) e ignoram inclusões repetidas do mesmo arquivo físico.

**Custo de performance:** `_once` tem um overhead de verificação (resolução do caminho real + consulta à tabela interna) ligeiramente maior que a versão simples. Em código legado com centenas de `require_once` espalhados, esse custo é uma das razões históricas que motivaram a adoção de autoloading — que centraliza essa resolução em um único ponto otimizado.

#### 2.4.2 O Problema de Escala do Include Manual

Considere um projeto de médio porte, sem autoloading:

```php
<?php
require_once __DIR__ . '/src/Model/Usuario.php';
require_once __DIR__ . '/src/Model/Produto.php';
require_once __DIR__ . '/src/Repository/UsuarioRepository.php';
require_once __DIR__ . '/src/Repository/ProdutoRepository.php';
require_once __DIR__ . '/src/Service/AutenticacaoService.php';
require_once __DIR__ . '/src/Service/CarrinhoService.php';
// ... dezenas de linhas mais, e cada nova classe exige uma nova linha aqui
```

Esse padrão é **frágil** (a ordem às vezes importa, devido a dependências entre classes), **não escalável** (todo novo arquivo exige edição manual desta lista) e **redundante** entre projetos. Foi exatamente para resolver esse problema que a comunidade PHP desenvolveu o autoloading.

#### 2.4.3 Autoloading Manual com `spl_autoload_register()`

Antes de chegar ao Composer, é pedagogicamente importante entender o mecanismo subjacente. O PHP expõe a função `spl_autoload_register()`, que registra uma função de *callback* a ser chamada **automaticamente** sempre que o interpretador encontra uma classe ainda não definida:

```php
<?php
spl_autoload_register(function (string $nomeClasse) {
    // Convenção: App\Model\Usuario -> src/Model/Usuario.php
    $prefixo = 'App\\';
    $diretorioBase = __DIR__ . '/src/';

    if (!str_starts_with($nomeClasse, $prefixo)) {
        return; // não é responsabilidade deste autoloader
    }

    $nomeRelativo = substr($nomeClasse, strlen($prefixo));
    $arquivo = $diretorioBase . str_replace('\\', '/', $nomeRelativo) . '.php';

    if (file_exists($arquivo)) {
        require $arquivo;
    }
});

// A partir daqui, basta USAR a classe — sem require manual:
$usuario = new App\Model\Usuario();
```

Esse trecho é, em essência, uma **implementação simplificada e educativa da própria PSR-4**: transforma um namespace em um caminho de arquivo através de uma regra de mapeamento consistente. `spl_autoload_register()` permite registrar **múltiplos** autoloaders simultaneamente (formando uma "pilha"), que são consultados em ordem até que um deles consiga localizar e carregar a classe.

#### 2.4.4 Namespaces em Detalhe

```php
<?php
// Arquivo: src/Service/AutenticacaoService.php

namespace App\Service;

use App\Model\Usuario;
use App\Repository\UsuarioRepository as UsuarioRepo;

class AutenticacaoService
{
    public function __construct(
        private UsuarioRepo $repositorio
    ) {}

    public function autenticar(string $email, string $senha): ?Usuario
    {
        $usuario = $this->repositorio->buscarPorEmail($email);
        // ... lógica de verificação de senha (password_verify) ...
        return $usuario;
    }
}
```

Elementos-chave:

- **`namespace App\Service;`** deve ser a **primeira instrução** do arquivo (exceto por `declare(strict_types=1)`, que pode precedê-la). Ela declara que todas as classes/funções/constantes definidas neste arquivo pertencem ao espaço de nomes `App\Service`.
- **`use App\Model\Usuario;`** importa uma classe de outro namespace, permitindo referenciá-la pelo nome curto (`Usuario`) em vez do **nome totalmente qualificado** (*Fully Qualified Class Name*, FQCN) `\App\Model\Usuario`.
- **`as UsuarioRepo`** cria um **apelido** (*alias*), útil para evitar colisões de nomes entre classes de namespaces diferentes que compartilham o mesmo nome curto.
- A **barra invertida** (`\`) é o separador hierárquico de namespaces — analogamente à barra `/` em diretórios.

**Distinção fundamental — Namespace ≠ Diretório automaticamente:** o PHP, por si só, **não** exige que a estrutura de namespaces corresponda à estrutura de diretórios físicos. Essa correspondência é uma **convenção**, não uma regra da linguagem — e é exatamente essa convenção que a PSR-4 formaliza e que o Composer implementa.

---

## Capítulo 3 — Aplicação Prática

### 3.1 Instalando e Configurando o Composer

O Composer é instalado globalmente (via instalador oficial ou gerenciador de pacotes do SO) e operado dentro da raiz do projeto através do arquivo `composer.json`:

```json
{
    "name": "minhaempresa/meu-projeto",
    "description": "Sistema de exemplo com autoloading PSR-4",
    "type": "project",
    "require": {
        "php": ">=8.2"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    }
}
```

A chave `"autoload"."psr-4"` define **mapeamentos** entre **prefixos de namespace** e **diretórios base**. A leitura correta é: *"toda classe cujo namespace começa com `App\` deve ser procurada dentro do diretório `src/`, substituindo o prefixo pelo caminho e as barras invertidas por barras normais"*.

### 3.2 A Regra de Mapeamento PSR-4 em Detalhe

Dado o mapeamento `"App\\": "src/"`, uma classe declarada como:

```php
namespace App\Repository;

class UsuarioRepository { /* ... */ }
```

**deve obrigatoriamente** residir no arquivo:

```
src/Repository/UsuarioRepository.php
```

A regra de tradução é mecânica e determinística:

```
FQCN:      App\Repository\UsuarioRepository
Prefixo:   App\        →  mapeado para  →  src/
Restante:  Repository\UsuarioRepository
Substitui '\' por '/': Repository/UsuarioRepository
Adiciona '.php':        Repository/UsuarioRepository.php
Caminho final: src/Repository/UsuarioRepository.php
```

Essa correspondência **1:1 entre namespace e caminho físico** é o que torna o autoloading do Composer extremamente eficiente: ele não precisa varrer o disco à procura da classe — ele **calcula** o caminho exato a partir do nome da classe.

**Múltiplos mapeamentos** são comuns em projetos que seguem múltiplas convenções (ex.: separar domínio de infraestrutura):

```json
"autoload": {
    "psr-4": {
        "App\\Domain\\": "src/Domain/",
        "App\\Infrastructure\\": "src/Infrastructure/",
        "App\\Http\\": "src/Http/"
    }
}
```

### 3.3 Gerando e Utilizando o Autoloader

Após editar `composer.json`, é necessário regenerar o mapa de classes:

```bash
composer dump-autoload
```

Isso gera (ou atualiza) os arquivos dentro de `vendor/composer/`, incluindo o ponto de entrada universal:

```
vendor/autoload.php
```

Esse único arquivo, quando incluído no início da aplicação, registra **automaticamente** um autoloader compatível com PSR-4 (e também PSR-0, `classmap` e `files`, se configurados) via `spl_autoload_register()` internamente:

```php
<?php
// public/index.php — ponto de entrada da aplicação

require __DIR__ . '/../vendor/autoload.php';

use App\Service\AutenticacaoService;
use App\Repository\UsuarioRepository;

$repositorio = new UsuarioRepository();
$servicoAuth = new AutenticacaoService($repositorio);

$usuario = $servicoAuth->autenticar('ana@exemplo.com', 'senha123');
```

Observe que **não há um único `require` ou `include` manual** para as classes `AutenticacaoService` ou `UsuarioRepository` — o autoloader gerado pelo Composer resolve tudo em tempo de execução, na primeira vez que cada classe é referenciada.

### 3.4 Exemplo Completo — Router HTTP com Diretórios, URL, Querystring e Autoload Integrados

Este exemplo integra **todos** os temas do livro em uma aplicação mínima e funcional:

```
projeto/
├── composer.json
├── public/
│   └── index.php
├── src/
│   ├── Http/
│   │   └── Router.php
│   └── Controller/
│       └── ProdutoController.php
└── vendor/
    └── autoload.php (gerado pelo Composer)
```

**`composer.json`:**
```json
{
    "autoload": {
        "psr-4": { "App\\": "src/" }
    }
}
```

**`src/Http/Router.php`:**
```php
<?php

namespace App\Http;

class Router
{
    /** @var array<string, callable> */
    private array $rotas = [];

    public function get(string $caminho, callable $acao): void
    {
        $this->rotas[$caminho] = $acao;
    }

    public function despachar(string $uriCompleta): void
    {
        // Separa caminho e querystring usando parse_url
        $caminho = parse_url($uriCompleta, PHP_URL_PATH) ?? '/';
        $queryString = parse_url($uriCompleta, PHP_URL_QUERY) ?? '';

        // Transforma querystring em array associativo
        parse_str($queryString, $parametros);

        if (!isset($this->rotas[$caminho])) {
            http_response_code(404);
            echo "Rota não encontrada: {$caminho}";
            return;
        }

        // Invoca a ação da rota, passando os parâmetros da querystring
        ($this->rotas[$caminho])($parametros);
    }
}
```

**`src/Controller/ProdutoController.php`:**
```php
<?php

namespace App\Controller;

class ProdutoController
{
    public function listar(array $parametros): void
    {
        $categoria = $parametros['categoria'] ?? 'todas';
        $pagina    = (int) ($parametros['pagina'] ?? 1);

        header('Content-Type: application/json');
        echo json_encode([
            'categoria' => $categoria,
            'pagina'    => $pagina,
            'mensagem'  => "Listando produtos da categoria '{$categoria}', página {$pagina}",
        ]);
    }
}
```

**`public/index.php`:**
```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Http\Router;
use App\Controller\ProdutoController;

$router = new Router();
$controller = new ProdutoController();

$router->get('/produtos', [$controller, 'listar']);

// Simula uma requisição real:
// GET /produtos?categoria=eletronicos&pagina=3
$router->despachar($_SERVER['REQUEST_URI']);
```

Ao acessar `/produtos?categoria=eletronicos&pagina=3`, o fluxo completo é:

1. O servidor web (ou o servidor embutido do PHP) invoca `public/index.php`.
2. `vendor/autoload.php` registra o autoloader PSR-4.
3. `new Router()` e `new ProdutoController()` disparam o autoload — o Composer localiza `src/Http/Router.php` e `src/Controller/ProdutoController.php` sem nenhum `require` manual.
4. `Router::despachar()` usa `parse_url()` para separar `/produtos` da querystring `categoria=eletronicos&pagina=3`.
5. `parse_str()` transforma a querystring em `['categoria' => 'eletronicos', 'pagina' => '3']`.
6. O array é passado ao método `listar()` do controller, que responde em JSON.

---

## Capítulo 4 — Análise Avançada

### 4.1 Otimizações do Autoloader do Composer

Em ambiente de produção, é recomendável gerar um **mapa de classes otimizado**, que elimina a necessidade de cálculo dinâmico de caminho a cada requisição:

```bash
composer dump-autoload --optimize
# ou, mais agressivo (recomendado para produção):
composer install --no-dev --optimize-autoloader
```

Com `--optimize`, o Composer gera um `vendor/composer/autoload_classmap.php` contendo um array estático `NomeClasse => caminhoAbsoluto`, convertendo a resolução de **O(prefixos testados)** para **O(1)** — uma simples consulta de hash. Isso é especialmente relevante em aplicações com centenas de classes, onde o custo cumulativo de resolução dinâmica de PSR-4 (percorrer prefixos e testar `file_exists()`) se torna mensurável sob alta concorrência.

Uma otimização ainda mais agressiva, disponível a partir do Composer 2.x, é o **autoloader com verificação de integridade via APCu** (`--apcu-autoloader`), que armazena o mapa de classes em cache de memória compartilhada, eliminando I/O de disco na resolução.

### 4.2 Complexidade e Trade-offs

| Estratégia | Complexidade de busca | Manutenibilidade | Uso recomendado |
|---|---|---|---|
| `require` manual | O(1) por chamada, mas O(n) linhas de manutenção | Baixa | Scripts pequenos, protótipos |
| `spl_autoload_register` customizado | Depende da implementação | Média | Frameworks legados, aprendizado |
| PSR-4 dinâmico (Composer padrão) | O(k), k = nº de prefixos registrados | Alta | Desenvolvimento |
| PSR-4 com classmap otimizado | O(1) | Alta | Produção |

**Trade-off central:** autoloading dinâmico (sem `--optimize`) recalcula o caminho a cada requisição, o que é ligeiramente mais lento, mas permite adicionar novas classes **sem regenerar o mapa** — ideal em desenvolvimento, onde arquivos são criados/movidos constantemente. Já o classmap otimizado exige `composer dump-autoload` a cada nova classe, mas oferece desempenho máximo — por isso é reservado à implantação em produção, onde a estrutura de arquivos é estável entre deploys.

### 4.3 Limitações do PSR-4

- **Uma classe por arquivo é obrigatório na prática**, ainda que não seja uma regra da linguagem — o autoloader só sabe onde procurar se houver correspondência 1:1 entre nome de classe e nome de arquivo.
- **Case sensitivity:** em sistemas de arquivos *case-insensitive* (Windows, macOS por padrão), erros de capitalização entre o nome da classe e o nome do arquivo podem passar despercebidos localmente, mas **falham em produção** (tipicamente Linux, *case-sensitive*) — uma fonte clássica de bugs "funciona na minha máquina".
- **Não substitui completamente o `require`:** arquivos que não definem classes (scripts de bootstrap, arquivos de configuração que retornam arrays, helpers com funções soltas) continuam exigindo `require`/`include` explícitos, pois o autoloading é acionado **apenas** pela referência a uma classe/interface/trait não definida.

### 4.4 Segurança na Resolução de Caminhos e URLs — Revisão Aprofundada

Retomando o tema da seção 2.1.3 sob uma ótica mais avançada: qualquer sistema que combine **entrada do usuário** (via querystring, corpo de requisição ou nome de rota) com **resolução de caminho no disco** deve tratar essa entrada como potencialmente hostil. Os vetores mais relevantes são:

1. **Path Traversal:** mitigado com `realpath()` + verificação de prefixo, como demonstrado.
2. **Null Byte Injection** (histórico, mitigado desde o PHP 5.3.4): tentativa de truncar validações de extensão de arquivo inserindo `\0` na string. O PHP moderno já rejeita bytes nulos em funções de sistema de arquivos, mas a defesa em profundidade permanece uma boa prática.
3. **Open Redirect:** ao redirecionar com base em um parâmetro de querystring (`?redirect=URL`), validar que o host de destino pertence a uma lista de permissões (*allowlist*), evitando que a aplicação seja usada para redirecionar usuários a sites maliciosos.

```php
<?php
function redirecionamentoSeguro(string $urlDestino, array $hostsPermitidos): string
{
    $host = parse_url($urlDestino, PHP_URL_HOST);

    // URL relativa (sem host) é considerada segura por padrão
    if ($host === null) {
        return $urlDestino;
    }

    if (!in_array($host, $hostsPermitidos, true)) {
        return '/'; // fallback seguro
    }

    return $urlDestino;
}
```

---

## Capítulo 5 — Conexões com Outras Áreas

### 5.1 Relação com Sistemas de Roteamento de Frameworks

O exemplo do Capítulo 3 é, em essência, uma versão minimalista do que frameworks como **Laravel** (`Illuminate\Routing`) e **Symfony** (`Symfony\Component\Routing`) implementam em escala industrial: eles combinam parsing de URL, extração de querystring e parâmetros de rota, e resolução de controllers via autoloading PSR-4 — tudo automatizado, mas fundamentado exatamente nos mecanismos discutidos neste livro.

### 5.2 Relação com Segurança da Informação (AppSec)

Path Traversal, Open Redirect e injeção via querystring são itens recorrentes no **OWASP Top 10** e no **OWASP Testing Guide**. O domínio técnico de `realpath()`, `parse_url()` e validação de entrada não é apenas uma questão de boas práticas de código — é um requisito de segurança de aplicação.

### 5.3 Relação com Sistemas Operacionais

A distinção entre caminhos absolutos e relativos, a existência de `DIRECTORY_SEPARATOR`, e o comportamento *case-sensitive*/*case-insensitive* do sistema de arquivos são, em última instância, conceitos de **Sistemas Operacionais** — o PHP apenas expõe uma API sobre essas primitivas do SO subjacente (chamadas de sistema POSIX ou Win32).

### 5.4 Relação com Redes de Computadores e o Protocolo HTTP

A querystring é parte da **linha de requisição HTTP** (*request line*) em requisições `GET`. Compreender sua estrutura é indissociável de compreender o protocolo HTTP/1.1 (RFC 7230-7235) e sua evolução para HTTP/2 e HTTP/3, onde a semântica de URL e querystring permanece inalterada, ainda que o transporte subjacente mude.

### 5.5 Relação com Engenharia de Software e Padrões de Projeto

A PSR-4, o autoloading e a organização em namespaces são pré-requisitos práticos para a aplicação de padrões de projeto clássicos (*Repository*, *Service Layer*, *Dependency Injection*) e princípios como **SOLID** — em particular o **Princípio da Responsabilidade Única** (*Single Responsibility Principle*), que se manifesta naturalmente quando cada classe reside em seu próprio arquivo, dentro de um namespace coerente com sua responsabilidade.

---

## Capítulo 6 — Exercícios Progressivos

### 6.1 Nível Iniciante

**Exercício 1.** Escreva uma função `caminhoAbsoluto(string $relativo): string` que receba um caminho relativo ao diretório do script atual e retorne o caminho absoluto, usando `__DIR__`.

**Exercício 2.** Dada a URL `http://blog.exemplo.com/artigos/123?comentarios=10#topo`, use `parse_url()` para extrair separadamente o host, o caminho, a querystring e o fragmento, imprimindo cada um em uma linha.

**Exercício 3.** Dada a string de querystring `nome=Maria+Silva&idade=30`, use `parse_str()` para convertê-la em um array associativo e imprima o valor de `nome` decodificado corretamente (sem o `+` literal).

### 6.2 Nível Intermediário

**Exercício 4.** Implemente uma função `listarArquivosPorExtensao(string $diretorio, string $extensao): array` que retorne todos os arquivos de um diretório (não recursivo) que possuam determinada extensão, usando `scandir()` e `pathinfo()`.

**Exercício 5.** Construa uma função `adicionarParametroUrl(string $url, string $chave, string $valor): string` que receba uma URL (com ou sem querystring existente) e retorne uma nova URL com o parâmetro adicionado ou substituído, sem duplicar `?`. Use `parse_url()`, `parse_str()` e `http_build_query()` em conjunto.

**Exercício 6.** Escreva um autoloader manual com `spl_autoload_register()` para um projeto fictício onde o prefixo `Loja\` mapeia para o diretório `app/`, sem usar Composer.

### 6.3 Nível Avançado

**Exercício 7.** Implemente uma função `caminhoSeguro(string $base, string $entradaUsuario): string|false` (baseada na seção 2.1.3) e escreva **três casos de teste** que tentem explorar Path Traversal (`../`, `..%2F`, caminho absoluto `/etc/passwd`), verificando que todos são corretamente bloqueados.

**Exercício 8.** Configure um `composer.json` com dois mapeamentos PSR-4 (`App\Domain\` → `src/Domain/` e `App\Infrastructure\` → `src/Infrastructure/`), crie ao menos uma classe em cada namespace, gere o autoloader com `composer dump-autoload --optimize`, e inspecione o conteúdo gerado em `vendor/composer/autoload_classmap.php` para confirmar o mapeamento estático.

**Exercício 9 (síntese).** Estenda o `Router` do Capítulo 3 para suportar parâmetros de rota dinâmicos (ex.: `/produtos/{id}`), extraindo tanto os parâmetros de caminho quanto os de querystring, e despache a requisição para o método correto de um controller resolvido via autoload PSR-4 — sem nenhum `require` manual de classes.

---

## Capítulo 7 — Conclusão Técnica

### 7.1 Síntese do Conhecimento

Este livro percorreu quatro domínios que, à primeira vista, pareciam independentes, mas que convergem em um único princípio: **PHP é, em sua essência, uma linguagem que resolve referências textuais para recursos concretos** — seja um arquivo no disco (diretórios), um recurso na web (URLs), um parâmetro de entrada (querystring), ou uma unidade de código (classes via namespace e autoload).

- **Diretórios:** `__DIR__`, `dirname()`, `realpath()` e `RecursiveDirectoryIterator` formam o ferramental para navegação segura e determinística do sistema de arquivos, com `realpath()` desempenhando papel central na prevenção de Path Traversal.
- **URLs:** `parse_url()` decompõe, `filter_var(..., FILTER_VALIDATE_URL)` valida, e a distinção entre `urlencode()`/`rawurlencode()` evita corrupção de dados na codificação.
- **Querystring:** `parse_str()` e `http_build_query()` formam o par simétrico de leitura e escrita, com suporte nativo a estruturas de array multidimensionais.
- **Include/Require/Namespace/Autoload:** a evolução histórica de `require` manual → `spl_autoload_register()` customizado → PSR-4 via Composer reflete a maturação do ecossistema PHP rumo à interoperabilidade e escalabilidade, com o Composer automatizando exatamente o mecanismo que, em sua essência, o desenvolvedor já entende ao dominar `spl_autoload_register()`.

### 7.2 Boas Práticas Consolidadas

1. **Sempre** use `__DIR__` (nunca caminhos relativos "nus") ao referenciar arquivos dentro de `include`/`require`.
2. **Sempre** valide caminhos derivados de entrada do usuário com `realpath()` e verificação de prefixo, antes de qualquer operação de leitura/escrita em disco.
3. **Prefira** `require_once`/`include_once` apenas quando a definição de classes/funções estiver em jogo; para arquivos de configuração que retornam valores, avalie se `require` simples (sem `_once`) não é mais apropriado ao contexto.
4. **Nunca** chame `parse_str()` com um único argumento em código de produção — sempre capture o resultado em uma variável isolada.
5. **Prefira** PSR-4 via Composer a qualquer sistema de autoload artesanal em projetos além do escopo educacional — a interoperabilidade com o ecossistema (milhares de pacotes no Packagist) depende disso.
6. **Em produção**, sempre gere o autoloader com `--optimize-autoloader` (ou `--classmap-authoritative` para máxima performance, aceitando o trade-off de exigir regeneração a cada nova classe).
7. **Trate toda entrada de querystring e todo parâmetro usado para montar caminhos de arquivo como potencialmente hostil** — aplique o princípio de *never trust user input* de forma consistente.

### 7.3 Encerramento

O domínio combinado de manipulação de diretórios, URLs, querystrings e do sistema de autoloading PSR-4 não é conhecimento periférico — é a base sobre a qual praticamente todo framework PHP moderno constrói suas abstrações de roteamento, injeção de dependência e organização de código. Entender esses mecanismos em seu nível fundamental — como este livro se propôs a fazer — capacita o desenvolvedor não apenas a *usar* frameworks com mais competência, mas a **avaliar criticamente**, **depurar com precisão** e, quando necessário, **construir suas próprias ferramentas** a partir dos mesmos princípios.

---

## Referências e Leitura Complementar

- PHP Manual. *Filesystem Functions*. Disponível em: https://www.php.net/manual/en/book.filesystem.php
- PHP Manual. *URL Functions*. Disponível em: https://www.php.net/manual/en/ref.url.php
- PHP-FIG. *PSR-4: Autoloader*. Disponível em: https://www.php-fig.org/psr/psr-4/
- Composer Documentation. *Autoloading*. Disponível em: https://getcomposer.org/doc/04-schema.md#autoload
- OWASP Foundation. *Path Traversal*. OWASP Testing Guide.
- Fielding, R. et al. (2005). *RFC 3986 — Uniform Resource Identifier (URI): Generic Syntax*. IETF.

*Nota: como assistente de IA, não tenho acesso à internet em tempo real para verificar essas referências no momento da escrita — recomenda-se conferir os links e citações antes de utilizá-los formalmente.*

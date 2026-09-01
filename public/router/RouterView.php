<?php

/**
 * ================================================================
 * RouterView
 * ================================================================
 *
 * Responsabilidade:
 *
 * Receber a rota enviada pelo index.php
 * e encontrar a View correspondente.
 *
 * Exemplo:
 *
 * URL:
 * /Studying_Design-Patterns/public/login
 *
 * Apache transforma para:
 *
 * index.php?rota=login
 *
 * O index.php cria:
 *
 * new RouterView("login")
 *
 * E o RouterView procura:
 *
 * view/Login.php
 *
 * ================================================================
 */

class RouterView
{
    
    /**
     * ------------------------------------------------------------
     * 1. PROPRIEDADE DA ROTA
     * ------------------------------------------------------------
     *
     * Guarda o nome da rota recebida.
     *
     * Exemplo:
     *
     *     login
     *     home
     *     contato
     *
     */
    private string $rota;


    /**
     * ------------------------------------------------------------
     * 2. CONSTRUTOR
     * ------------------------------------------------------------
     *
     * Recebe a rota enviada pelo index.php.
     *
     * Exemplo:
     *
     *     new RouterView("login");
     *
     * Então:
     *
     *     $this->rota = "login";
     *
     */
    public function __construct(string $rota)
    {
        $this->rota = $rota;
    }


    /**
     * ------------------------------------------------------------
     * 3. MÉTODO RESPONSÁVEL PELO ROTEAMENTO
     * ------------------------------------------------------------
     *
     * Aqui acontece a decisão de qual View será carregada.
     *
     */
    public function redirect(): void
    {

        /**
         * --------------------------------------------------------
         * 4. LOCALIZA A PASTA "view"
         * --------------------------------------------------------
         *
         * dirname(__DIR__)
         *
         * sobe um nível no diretório.
         *
         * Depois acrescentamos:
         *
         *     /view
         *
         * Isso gera o caminho FÍSICO da pasta das Views.
         *
         * Exemplo:
         *
         * C:/xampp/htdocs/
         * Studying_Design-Patterns/view
         *
         */
        $uri = dirname(__DIR__)
            . DIRECTORY_SEPARATOR
            . "view";


        /**
         * --------------------------------------------------------
         * 5. VERIFICA SE A PASTA VIEW EXISTE
         * --------------------------------------------------------
         *
         * Se a pasta não existir, não temos onde procurar
         * as páginas.
         *
         * Nesse caso retornamos erro 500.
         */
        if (!is_dir($uri)) {

            http_response_code(500);

            echo "Erro interno no servidor.";

            return;
        }


        /**
         * --------------------------------------------------------
         * 6. VERIFICA SE NÃO FOI INFORMADA NENHUMA ROTA
         * --------------------------------------------------------
         *
         * Quando o usuário acessa:
         *
         *     /public/
         *
         * o index.php pode receber:
         *
         *     rota = ''
         *
         * Nesse caso queremos mandar o navegador para:
         *
         *     /public/home
         *
         */
        if ($this->rota === '' or $this->rota === null) {


            /**
             * ----------------------------------------------------
             * 7. REDIRECIONAMENTO HTTP
             * ----------------------------------------------------
             *
             * IMPORTANTE:
             *
             * Aqui NÃO estamos passando o caminho físico:
             *
             *     C:/xampp/htdocs/.../Home.php
             *
             * Estamos passando uma URL.
             *
             * O navegador recebe:
             *
             *     Location: /Studying_Design-Patterns/public/home
             *
             * e faz uma NOVA requisição.
             *
             */
            header(
                'Location: /Studying_Design-Patterns/public/home'
            );


            /**
             * Depois de enviar o Location, encerramos o PHP
             * para não continuar executando o restante do router.
             */
            exit;
        }


        /**
         * --------------------------------------------------------
         * 8. PROCURA OS ARQUIVOS DENTRO DE /view
         * --------------------------------------------------------
         *
         * scandir() lista os arquivos existentes.
         *
         * Exemplo:
         *
         *     Home.php
         *     Login.php
         *     Contato.php
         *
         */
        foreach (scandir($uri) as $file) {


            /**
             * ----------------------------------------------------
             * 9. IGNORA "." E ".."
             * ----------------------------------------------------
             *
             * São referências especiais do sistema de arquivos.
             */
            if ($file === '.' || $file === '..') {
                continue;
            }


            /**
             * ----------------------------------------------------
             * 10. COMPARA A ROTA COM O ARQUIVO
             * ----------------------------------------------------
             *
             * Se a rota for:
             *
             *     home
             *
             * procuramos:
             *
             *     home.php
             *
             * Se existir:
             *
             *     Home.php
             *
             * também será encontrado porque usamos strtolower().
             *
             */
            if (
                strtolower($file)
                ===
                strtolower($this->rota) . '.php'
            ) {


                /**
                 * ------------------------------------------------
                 * 11. VIEW ENCONTRADA
                 * ------------------------------------------------
                 *
                 * Agora o PHP carrega a View.
                 *
                 * Exemplo:
                 *
                 *     rota = home
                 *
                 * encontra:
                 *
                 *     Home.php
                 *
                 * e executa:
                 *
                 *     require_once Home.php
                 *
                 */
                require_once $uri
                    . DIRECTORY_SEPARATOR
                    . $file;


                /**
                 * ------------------------------------------------
                 * 12. ENCERRA O ROTEAMENTO
                 * ------------------------------------------------
                 *
                 * A View já foi encontrada.
                 *
                 * Não precisamos continuar procurando.
                 */
                return;
            }
        }


        /**
         * --------------------------------------------------------
         * 13. ROTA NÃO ENCONTRADA
         * --------------------------------------------------------
         *
         * Se chegar aqui significa que nenhuma View correspondeu
         * à rota recebida.
         *
         * Exemplo:
         *
         *     /public/banana
         *
         * e não existe:
         *
         *     view/Banana.php
         *
         */
        http_response_code(404);

        echo "Recurso não encontrado 404";
    }
}

<?php

    /**
     *  Essa classe vai resceber um caminho de rota do public/index.php?rota=$1
     * Do apache via requisição Get e vai redirecionar para view certa 
     */
    class RouterView{
        
        # apartir da versão 8 o php aceita typehits em propriedades
        private string $rota;

        public function __construct(string $rota)
        {
            $this->rota = $rota;
        }

        # vai retorna nada, apenas rotear para a página correta!
        # retornar void se nao exisitir e nao faz nada
        # se exisitir o recurso retorna requite_once
        public function redirect(): void
        {
            $uri = dirname(__DIR__) . DIRECTORY_SEPARATOR . "view";

            if (!is_dir($uri)) {
                http_response_code(500);
                echo "Erro interno no servidor.";
                return;
            }

            // $this->rota é string, nunca será null — cheque se está vazia
            if ($this->rota === '') {
                header('Location: ' . $uri . 'Home.php', true, 302);
                exit;
            }

            foreach (scandir($uri) as $file) {
                if ($file === '.' || $file === '..') continue;

                if (strtolower($file) === strtolower($this->rota) . '.php') {
                    require_once $uri . DIRECTORY_SEPARATOR . $file;
                    return; // arquivo encontrado, pronto
                }
            }

            // Chegou aqui = não achou
            http_response_code(404);
            echo "Recurso não encontrado 404";
        }

    }

?>
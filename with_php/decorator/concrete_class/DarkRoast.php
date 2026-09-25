<?php

    require_once __DIR__ . '/../abstract_class/Beverage.php';    
    
    /*
    
        Aqui vamos implementar (através de especificação -> herança de classes) 
        as classes que representam o tipo de bebida em específico com 
        ações (methods) e propriedades (atributes) próprias.
        
        Esse Padrão não está sendo usado para herdar comportamento, 
        mas sim para compatibilidade de tipo. Facilitando o decoramento
        do objeto para que é para quem extende!... ou seja é um e tem um.
    
    */

    class DarkRoast extends Bevarage{

        #[Override]
        public function __construct(string $description = "Dark Roast Coffe")
        {
            return parent::__construct($description);
        }


        #[Override]
        public function const(): float
        {
            return 4.85;
        }


    }




?>
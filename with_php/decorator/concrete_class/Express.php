<?php


    require_once __DIR__ . '/../abstract_class/Beverage.php';  

    class Express extends Bevarage{

        #[Override] # indica que estamos sobrescreveendo o construct da class pai pela filha
        public function __construct(string $description = "Expresso")
        {
            return parent::__construct($description);
        }

        #[Override] # implementando o valor da bebida expresssa... Vista que já estamos decorando
        public function const():float{
            return 1.99;
        }
    }





?>
<?php

    require_once __DIR__ . '/../abstract_class/Beverage.php';    

    class Decaf extends Bevarage{

        #[Override]
        public function __construct(string $description = "Decaf Coffe")
        {
            return parent::__construct($description);
        }


        #[Override]
        public function const(): float
        {
            return 5.53;
        }

    }


?>
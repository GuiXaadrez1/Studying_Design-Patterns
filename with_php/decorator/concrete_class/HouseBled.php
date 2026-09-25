<?php

    require_once __DIR__ . "/../abstract_class/Beverage.php";

    class HouseBled extends Bevarage{

        #[Override]
        public function __construct(string $description = "House Bled Coffee")
        {
            return parent::__construct($description);
        }

        #[Override]
        public function const():float{
            return 0.89;
        }

    }


?>
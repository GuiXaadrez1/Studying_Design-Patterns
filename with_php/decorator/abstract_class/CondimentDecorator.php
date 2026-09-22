<?php

    /*
        Aqui esta a class abastrata que vai herdar outra classe abstrata
    
    */

    require_once __DIR__ . '/../abstract_class/Beverage.php'; 


    /*
        Primeiro , precisamos ser INTERCAMBIÁVEIS (PARTTEN STRATEGY)
        com Beverage, então extendemos (herdamos) a classe Beverage
    */
    abstract class CondimentDecorator extends Bevarage{

        // vamos precisar que todos os decoradores
        // que de condimento implementem
        // de novo o método getDescription()
        // basicamente nesta classe filha estamos 
        // sobrescreveendo o metodo publico da classe pai
        
        abstract public function getDescription():string;

    }

?>
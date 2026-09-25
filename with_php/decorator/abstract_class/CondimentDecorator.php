<?php

    /*
        Aqui esta a class abastrata que vai herdar outra classe abstrata
    
    */

    require_once __DIR__ . '/../abstract_class/Beverage.php'; 


    /*
        Primeiro , precisamos ser INTERCAMBIÁVEIS (PARTTEN STRATEGY)
        com Beverage, então extendemos (herdamos) a classe Beverage
        se fosse uma interface iriamos implementar a interface:

            -> "Princípio 1: Separa o que é volátil daquilo que é fixo."
            -> "Princípio 2: Dê prioridade para Interface não para implementação de herança."
            -> "Princípio 3: Prefira DI e IOC no lugar de Herança."
            -> "Princípio 4: Busque Desing levemente ligados entre objetos que interagem."
            -> "Princípio 5:  As classes devem estar abertas para extensões 
            e fechadas para modificações."

        Nesta caso a classe abastrata está servindo como uma interface.
        A única diferença é que estamos realizando herança e quebrando alguns
        princípios, mas tudo bem... Característica do padrão de projeto.

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
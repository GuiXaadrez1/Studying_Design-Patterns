<?php

    /*
        Aqui vamos aplicar nossa class abstrata

            - Uma classe abstrata pode ter métodos abstratos, mas não é obrigatório que tenha.

            - Uma classe abstrata pode existir mesmo sem nenhum método abstrato.

            - Uma classe abstrata não pode ser instanciada diretamente.

            - Uma classe que possui pelo menos um método abstrato precisa ser declarada como abstrata.    
    
    */

    // classe abstrata que possui dois methods
    abstract class Bevarage{

        # definido atributo privado da classe
        private string $description;

        public function __construct(string $description = "Unknown Beverage"){

            $this->description = $description;
        }     


        # Getter e Setter

        // lembrando que classes abastratas podem ter metodos abstrados
        // mas para ela ser declarada como abastrata, ela deve ter um metodo abstrato
    
        
        public function getDescription():string{
            return $this->description;
        }

        // DEFINIDO METODO ABASTRATO PARA ESSE CLASSE SER ABASTRATA
        abstract public function const():float;
        
    }            

?>
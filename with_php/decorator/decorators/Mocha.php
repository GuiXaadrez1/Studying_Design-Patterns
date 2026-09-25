<?php


    require_once __DIR__ . '/../abstract_class/CondimentDecorator.php';

    /*
    
        Como a classe Abstrata CondimentDecorator (superclasse ) 
        se tornou nosso supertipo em comum vamos agorar decorar as subclasses 
        (que vao ser nossos objetos) com isso estamos trabalhando com Extensões.
        
        Lembrando que essa subclass herda propriedades da class pai
        que é a classe Beverage 

        Segundo o Livro Head First "Use a cabeça":

            -> As classes devem estar abertas para extensões (extender de uma superclass/interface generica, ambas vão se tornar o supertipo em comum)
            -> fechadas para modificações
    
    */


    /**
     *  Como Mocha é um decorador podemos estender/herdar de CondimentDecorator
     */
    class Mocha extends CondimentDecorator{

        # Definido Atributos de Class, como CondimentDecorator Herda de Bevarage
        # é possivel tipar variáveis e armazenar objetos diferentes que também
        # herdam do mesmo supertipo (superclass/classe pai) comum
        # essa classe abastrata esta fazendo o papel de interface "Abastrata"
        # então vai funcionar igual 
        public Bevarage $beverage; 

        # Aqui estamos aplicando injeção de depedência por construct
        # e realizando um relação de objetos por composição TEM UM
        #[Override] 
        public function __construct(Bevarage $beverage)
        {   
            $this->beverage = $beverage; 

            /*
                Ao criar uma instância do objeto Mocha com referência a Beverage
                esta acontecendo:
                
                1 - variável de instância que engloba (empacota/envelopa - wrapping)
                para conter a bebida que estamos englobando
                
                2 - uma maneira de definir essa variável de instância para o objeto
                que estamos englobando é por DI por composição, vamos passar o objeto
                bebida que estamos englobando para o construtor do decorador. 

            */

        }

        #[Override]
        public function getDescription(): string
        {
            /**
             *  Como queremos que a nossa descrição inclua a bebida 
             * - por exemplo: Dark Roast - e também inclua o item que 
             * decora a bebida, como Dark Roast, Mocha. Assim, primeiro
             * delegamos ao obeto qu eestamos decorando para obter sua
             * descrição, depois anexamos Mocha a essa descrição
             */

            return $this->getDescription() . " , Mocha";
        }

        public function const():float{
            return 0.20 + $this->beverage->const();
        }

    }









?>
<?php

    /*
        Vamos definir o conceito do Padrão Observer:

            - Observer: é um padrão de projeto comportamental usado quando um objeto
            precisa avisar automaticamente vários outros objetos sobre mudanças
            no seu estado.

            - Define uma dependência um-para-muitos (1..*) entre os objetos,
            de forma que, quando um objeto muda de estado, TODOS OS SEUS
            DEPENDENTES SÃO AVISADOS E ATUALIZADOS AUTOMATICAMENTE!

        Existem diversas formas de aplicar esse padrão...

            - Mas a maioria gira em torno de um design de classes que inclui as
            interfaces Subject e Observer... É nesse modelo que vamos aplicar
            na prática aqui. 

            - Vamos implementar só o conceito

        Compreendendo Diagrama de Classes (UML) de forma resumida:

            - Linha sólida, sem seta ou com seta aberta ">"
                -> Association/Associação: relacionamento genérico e estrutural
                entre duas classes ("conhece" ou "usa" o outro objeto por meio
                de um atributo). É um acoplamento fraco e permanente
                (dura enquanto o objeto existir), diferente da Dependency,
                que é passageira.

            - Linha tracejada, com seta aberta "- - ->"
                -> Dependency/Dependência: relacionamento fraco e TRANSITÓRIO
                ("USA" o outro objeto), geralmente através de um parâmetro de
                método, variável local ou retorno de método — não como um
                atributo da classe. Se a classe usada mudar, a classe
                dependente pode ser afetada.

            - Linha sólida, com seta fechada/triangular " |> "
                -> Generalization/Herança: relacionamento de pai-filho ("É UM"),
                onde a subclasse herda atributos e comportamentos da superclasse.

            - Linha tracejada, com seta fechada/triangular " - - -|> "
                -> Realization/Implementação: uma classe implementa o contrato
                definido por uma interface ("PROMETE FAZER").

            - Linha sólida com losango VAZIO (oco) na ponta
                -> Aggregation/Agregação: relacionamento todo-parte ("TEM UM"),
                mas com acoplamento fraco — a parte pode existir independente
                do todo (ex: uma Garagem tem Carros, mas os Carros existem
                mesmo sem a Garagem).

            - Linha sólida com losango CHEIO (preenchido) na ponta
                -> Composition/Composição: relacionamento todo-parte forte
                ("TEM UM" essencial), com acoplamento forte — a parte NÃO
                existe sem o todo (ex: uma Casa tem Cômodos; se a Casa é
                destruída, os Cômodos deixam de existir).
        */

    # importando nossas interfaces
    require_once "./with_php/observer/interfaces/subjectInterface.php";            
    require_once "./with_php/observer/interfaces/observerInterface.php";
    
    # Implementando as classes concretas das interfaces

    # na diagramação do livro (use a sua cabeça HEAD FIRST) a classe concreta implementa
    # a interface Observer
    class ConcreteObserver implements Observer{

        public function __construct()
        {
            throw new \Exception('Not implemented');
        }
        

        public function update():null{
            return null; 
        }

        # Outher methods
    }
    
    # o objeto ConcreteObserver esta associada a 1* ConcreteSubjects 
    # o objeto ConcreteSubject implementar a interface Subject
    class ConcreteSubject implements Subject{

        public function __construct()
        {
            throw new \Exception('Not implemented');
        }
        
        # implementando os contratos
        public function registerObserver(): null
        {
            throw new \Exception('Not implemented');
        }
        
        public function removeObserver(): null
        {
            throw new \Exception('Not implemented');
        }
        
        public function notifyObservers(): null
        {
            throw new \Exception('Not implemented');
        }

        # Outher methods
    }

?>
<?php

/*
    Vamos implementar o padrão Observer do Head First 
    seguindo o projeto do livro: WheaterData 

    Lembre-se:
        
        -> Observer é um padrão de projeto comportamental usado quando um objeto
            precisa avisar automaticamente vários outros objetos sobre mudanças
            no seu estado.
        
        -> Ele define um relacionamento um para muitos:

            -> O Subject (sujeito) é o objeto que contém o estado e o controla.
            Assim, há um objeto com estado

            -> Os Observers (observadores), utilizam do estado mesmo que não o 
            possua. Existem muito observadores e eles contam com o Subject para
            informar quando seu estado é alterado. 

            -> Assim, existe uma relação um Subject para muitos observadores.
            como o sujeito é o único proprietário desses dados, os observadores
            dependem do sujeito para alterá-los quando os dados são alterados.
            Isso leva a um design OO mais simples e flexível, do que permitir 
            que muitos objetos controlem os mesmos dados.

            -> Observer é um padrão que respeita o princípio de projeto:
                
                "Busque desings levemente ligados entre os objetos que interagem"

                - Isso é bom porque projetos levemente permitem construir sistemas OO
                flexíveis que podem lidar com mudanças porque miniminizam a interdepedência
                entre os objetos.

*/


// vamos importar nossas interfaces para implementar nas classes que vão fazer 
// papel de subject e observer...


require_once "./with_php/observer/interfaces/subjectInterface.php";
require_once "./with_php/observer/interfaces/observerInterface.php";

interface DisplayElement{

    /*

        Criando uma interface para todos os elementos de exibição implementar.
        os elementos de exibição só precisam implementar um método display()
        isso vai seguir o padrão de projeto Strategy

        Essa interface inclui apenas o método, display(), que iremos chamar 
        quando o elemento de exibição precisa ser exibido.
    
    */

    public function display():string;
}



class WheaterData implements Subject{
    
    /*
        Essa vai ser a nossa classe concreta que vai implementar a interface Subject
        ou seja vai ser nossa class que instancializa/materializa um objeto Subject
    */

    // colocando atributos privados da classe
    private Observer $objObserver; # esse atributo vai ser o objeto a registrado na lista abaixo
    private array $observers = []; # esse atributo se inicia vazio 
    private float $temperatura;
    private float $humidity;
    private float $pressure;

    public function __construct(Observer $observer)
    {
        $this->objObserver = $observer;
    }


    /*
       #[Override] é um attribute nativo do PHP 8.3 que declara explicitamente que um 
       método (ou propriedade) em uma classe filha deve estar sobrescrevendo um 
       método/propriedade de uma classe pai ou interface.

        - Se o método marcado com #[Override] não corresponder a nenhum método de mesmo
        nome em um parent (classe pai, interface ou trait), o PHP emite um fatal error em 
        tempo de compilação. 
        
        Se corresponder, tudo funciona normalmente — o attribute não altera o comportamento
    */
    #[Override]
    public function registerObserver(Observer $objObserver):null
    {   
        # registrando o observer na ultima posição do array
        $this->observers[] = $this->objObserver;

        return null;
    }

}


?>
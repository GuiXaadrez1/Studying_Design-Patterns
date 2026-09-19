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

require_once __DIR__ . '/../interfaces/subjectInterface.php';      
require_once __DIR__ . '/../interfaces/observerInterface.php';   

interface DisplayElement{

    /*

        Criando uma interface para todos os elementos de exibição implementar.
        os elementos de exibição só precisam implementar um método display()
        isso vai seguir o padrão de projeto Strategy

        Essa interface inclui apenas o método, display(), que iremos chamar 
        quando o elemento de exibição precisa ser exibido.
    
    */

    public function display():null;
}


class WheaterData implements Subject{
    
    /*
        Essa vai ser a nossa classe concreta que vai implementar a interface Subject
        ou seja vai ser nossa class que instancializa/materializa um objeto Subject
    */
    

# --- DEFININDO ATRIBUTOS INTERNOS DA CLASSE/OBJETO
    
    /** @var Observer[] */ 
    private array $observers = []; # esse atributo se inicia vazio 
    private float $temperatura;
    private float $humidity;
    private float $pressure;

    public function __construct(
        float $temperatura = 0.0,
        float $humidity = 0.0,
        float $pressure = 0.0
    ){
        $this->temperatura = $temperatura;
        $this->humidity = $humidity;
        $this->pressure = $pressure;
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
    public function registerObserver(Observer $objObserver):null{   
        # registrando o observer na ultima posição do array
        $this->observers[] = $objObserver;

        return null;
    }

    #[Override]
    public function removeObserver(Observer $objObserver):null{   

        // Remove o Observador registrado!

        if (in_array($objObserver, $this->observers)) {
            
            $key = array_search($objObserver, $this->observers);
            
            unset($this->observers[$key]);
            
            $this->observers = array_values($this->observers); // reindexa o array
        };

        return null;
    }


    #[Override]
    public function notifyObservers():null{  

        // Notifica todos os observadores que o estado do sujeito mudou

        for($i = 0;$i < count($this->observers); ++$i){

            $observer = $this->observers[$i];
            
            $observer->update(
                $this->temperatura,
                $this->humidity,
                $this->pressure
            );
        }

        return null;
    }


    public function measurementsChanged():null{

        # chama função de notificação

        $this->notifyObservers();
        
        return null;
    }


    public function setMeasurementsChanged(
        
        float $temperatura,
        float $humidity,
        float $pressure

    ):null{

        # Atualiza os parâemtros e avisa para todos os observer
        # o estado do sujeito foi alterado!

        $this->temperatura = $temperatura;
        $this->humidity = $humidity;
        $this->pressure = $pressure;

        $this->measurementsChanged();

        return null;
    }

    // ---- outhers methods ----- 

}


# --- AGORA VAMOS DEFINIR NOSSA CLASSE DE EXIBIÇÃO

    # Podemos implementar diversas interfaces a uma classe no php
class CurrentConditionsDisplay implements Observer,DisplayElement{

    /*
        Essa classe implementa Observer para que possa receber
        as mudanças do objeto wheaterData que é o sujeito.
        Basicamente le é um observador

        Ela também implementa o DisplayElement porque nossa API
        vai exigir que todos os elementos de exibição implementem
        essa interface.
    
    */

    private float $temperatura;
    private float $humidity;
    private Subject $weatherData;

    # Injeção de Depedência por composição
    public function __construct(Subject $weatherData){
        
        $this->weatherData = $weatherData;

        # Agora vamos registrar a referência desta classe ao objeto Sujeito
        $this->weatherData->registerObserver($this);

    }

    #[Override]
    public function display(): null
    {
        /*
            Imprime apenas a temperatura atual
        */
        echo "Current conditions: " . (string) $this->temperatura . "<br>" . "degrees and " . (string) $this->humidity;
        
        return null;
    }

    #[Override]
    public function update(?float $temp, ?float $humidity, ?float $pressure): null
    {
        $this->temperatura = $temp;
        $this->humidity = $humidity;

        $this->display();

        return null;
    }

}

?>   
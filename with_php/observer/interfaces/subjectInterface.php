<?

/*

    Não temos uma diagramação de classe de uso desenhada... Mas aqui:
        - Um objeto subject pode esta assossiado a 1* observadores
    
    Observações:

        - Lembrando que PHP suporta Type Hinting com interfaces
        - Ou seja... Qualquer classe objeto que implementa a Interface
        - pode ser materializado mesmo que como entrada de um parâmetro  

*/

// importando a interface Observer para fazer de Type Hinting
require_once "./with_php/observer/interfaces/observerInterface.php";

interface Subject{
	
	// Lembrando que interfaces nao definem contratos
	// de methods privados e protegidos
	// protected | private


    /*
        Estes dois métodos utilizam um Observer como um argumento obrigatório (non-default),
        ou seja, é o Observer (objeto) a ser registrado ou removido.
    */

	public function registerObserver(Observer $objObserver):null;
    public function removeObserver(Observer $objObserver):null;

    /*
        Este é o método chamado para notificar todos os observadores 
        qaundo o estado de Subject é alterado.    
    */
    public function notifyObservers():null;

}

?>
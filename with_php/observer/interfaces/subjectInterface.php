<?

/*

Não temos uma diagramação de classe de uso desenhada... Mas aqui:
    um objeto subject pode esta assossiado a 1* observadores
    
*/

interface Subject{
	
	// Lembrando que interfaces nao definem contratos
	// de methods privados e protegidos
	// protected | private


    /**
     * Function que registrar um novo objeto observer
    */
	public function registerObserver():null;
    public function removeObserver():null;
    public function notifyObservers():null;

}

?>
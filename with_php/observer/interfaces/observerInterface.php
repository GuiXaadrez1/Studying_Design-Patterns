<?php

# Aqui criamos uma interface que a classe Concreta do Observer vai implementar

interface Observer{
	
	// Lembrando que interfaces nao definem contratos
	// de methods privados e protegidos
	// protected | private

    /**
     * Function que atualiza os objetos observadores
     */
	public function update():null;

}


?>
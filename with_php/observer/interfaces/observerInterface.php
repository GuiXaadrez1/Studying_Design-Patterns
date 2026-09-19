<?php

/*
	Aqui criamos uma interface que a classe Concreta do Observer vai implementar

	A interface Observer é implementada por todos os observadores, então todos eles 
	têm que implementar o método update().

*/

interface Observer{
	
	// Lembrando que interfaces nao definem contratos
	// de methods privados e protegidos
	// protected | private

    /*
		Function que atualiza os objetos observadores
	
		Estes são os valores de estado que os Observers recebem do Subject
		quando uma medição metereológica muda.
	*/
	public function update(
		?float $temp, # temperatura
		?float $humidity, # temperatura de humidade 
		?float $pressure, #temperatura de pressão
	):null;

}


?>
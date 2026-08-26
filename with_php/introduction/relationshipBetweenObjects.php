<?php


## TREINANDO DEPEDENCIA EM PHP OOP 

/**
 * O objetivo é entender como os objetos em OO se relacional
 * e como é passada as depedencias (recursos) de um objeto para o outro 
 * 
 * Lembre sobre: 
 * 
 *  É - UM
 *  Tem - um 
 * 
 *  Relacao genereica - Associação
 *  Relacao Todo Fraca - Agregação 
 *  Relacao Todo Forte - Composição
 * 
 * DI Injection
 * IoC - Inversao de Controle
 * 
 */


// Class que vai servir de depedência para outra!


class CalcFrete{

	// Atributos 

	protected $valor;

	public function __construct(float $valor){
		$this->valor = $valor;
	}

	// definido methods espécificos 

	public function calc():float{

		// representa a distância
		$distance = mt_rand(1,9999);

		$taxa = mt_rand(1,2);


		// realizando calculo

		return $value_frete = ($distance * $taxa)/$this->valor;

	}

}



// Class que tem depedencias passadas via entrada de parametro de um metodo
// Esse tido de relacionamento e Agragacao

class CarrinhoCompra{


	// Definindo Atributos

	function __construct(){

	}


	// Definido Methods

	// passando depedência da class CalcFrente
	// específicamente para essa funcao
	public function fazerCompra(CalcFrete $Object){

		return $Object->calc();
	}

}




$compra = new CarrinhoCompra();
$calcFrete = new CalcFrete($valor=50);

echo($compra->fazerCompra($calcFrete))

?>

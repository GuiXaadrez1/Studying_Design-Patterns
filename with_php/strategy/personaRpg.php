<?php
	
/*

	Princípios de Padrão de projeto (BÁSICO): 

	I - Identifique Aspectos volateis e encapsule 
		- e separe-os dos que permanecem iguais/fixos

	II - Programa para Interface não para implementação 
	
	III - Deprioridade para IoC e DI Injection:
		
		- Associação -> Relação Generica;
		- Agregação -> Toda Parte Fraca; 
		- Composição -> Todo Parte Forte; 

	Vamos replicar a estratégia de padrão de projeto:
		
		- Strategy
		- Ao invés de patos (Ducks) vamos simular
		- um conjunto de classes para RPG

	Conceito de Strategy: 

		- Define uma família de algoritmos que 
		encapsula cada um dels e os torna
		intercambiáveis  ou seja:
			 
			- (
				trocar uma coisa por outra;
				que pode ser trocado ou permutado
			)

		A estratégia deixa o algoritmo variar
		idenpedentemente dos clientes que o utilizam

	BrainStrom para Strategy: 

		- Comportamento;
		- Troca/permutação;
		- Depedência;
		- Interface ;
		- IoC;
		- Encapsulamento;
		- Isolamento;
		- Volátil/variável.
*/		


/*---------------------------------------------------*/


/*

	Voce tem as seguintes clsses e interfaces...
		
	Interfaces:

		- WeaponBehavior;

	Classes:

		// Classes que representa armas

		- Sword
		- Axe
		- knife

			// Relação Brow e Arrow é Tem - Um
			// Relação TODO FRACA
		- Bow 
		- Arrow
		
		// Classe que representa personalidade ou caracteristica

		- Character 

		// Classes que representam uma classes no rpg rsrsrs

		- Queen 
		- King
		- Knight 
		- Troll 

	// Observações method de atualizacao do comportamento: 

		- protected function setWeaponBehavior (
			WeaponBehavior $w 	
		){
			$this->weapon = w;
		}
	
	// Implementando a Interface Primeiro (Contrato)

	- Lembrando que PHP suporta Type Hinting com interfaces
	- Ou seja... Qualquer classe objeto que implementa a Interface
	- pode ser materializado mesmo que como entrada de um parâmetro



*/


// Vamos implementar as Interfaces Primeiro 


interface WeaponBehavior{
	
	// Lembrando que interfaces nao definem contratos
	// de methods privados e protegidos
	// protected | private

	public function useWeapon( ?bool $status):string;

}


// Agora vamos contruir a classe que impelmenta
class Knife implements WeaponBehavior{

	public function __construct(){

	}

	public function useWeapon(?bool $status=null):string{
				
		// Se o status for nulo, podemos assumir um comportamento padrão
		$active = $status ?? false; 

		if ($active) {
			
			// Lógica para usar a arma
			return "Atacando com uma Faca.";
		
		}

		return "Não esta usando Faca.";
	}

}

class Axe implements WeaponBehavior{

	public function __construct(){

	}

	public function useWeapon(?bool $status=null):string{
				
		// Se o status for nulo, podemos assumir um comportamento padrão
		$active = $status ?? false; 

		if ($active) {
			
			// Lógica para usar a arma
			return "Atacando com um Machado.";
		
		}

		return "Não esta usando Machado.";
	}

}

class Sword implements WeaponBehavior{

	public function __construct(){

	}

	public function useWeapon(?bool $status=null):string{
				
		// Se o status for nulo, podemos assumir um comportamento padrão
		$active = $status ?? false; 

		if ($active) {
			
			// Lógica para usar a arma
			return "Atacando com uma Espada.";
		
		}
		return "Não esta usando uma Espada.";
	}

}


class Arrow{
	
	public function __construct(){

	}

	public function useWeapon(?bool $status=null):bool{
				
		// Se o status for nulo, podemos assumir um comportamento padrão
		$active = $status ?? false; 

		if ($active) {
			
			// Lógica para usar a arma
			return "Atacando com Flecha.";
		
		}
		return "Não esta usando Flecha";
	}

}


// Aqui vamos Criar um Relação TODO-PARTE FRACA...
class Bow implements WeaponBehavior{

	// Podemos Explicitar que essa variavel pode ser null
	protected ?Arrow $arrow;

	public function __construct(?Arrow $objectArrow=null){
		
		$this->arrow = $objectArrow;

	}

	protected function usingArrow():bool{
		
		if ($this->arrow === null){
			return false;
		}

		return true;
	}

	public function useWeapon(?bool $status=null):string{
				
		// Se o status for nulo, podemos assumir um comportamento padrão
		$active = $status ?? false; 

		if ($active) {
			
			if ($this->usingArrow()){

				return "Atacando com Arco e Flecha.";
			}

			return "Atacando com Arco.";
		}

		return "Não esta atacando com o Arco.";

	}


}


?>


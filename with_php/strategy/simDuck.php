<?php


	/*
	
		Vamos aprender o conceito do
		padrão de projeto Strategy


		conceito: é um padrão de projeto de software comportamental que permite definir uma
		família de algoritmos, encapsular cada um deles em classes separadas e fazer com que
		seus objetos sejam intercambiáveis em tempo de execução.

		Isso significa que o padrão permite que o algoritmo varie independentemente dos
		clientes que o utilizam, promovendo a flexibilidade e a manutenção do código.
	*/


	/*
		Quando Aplicar? 

		quando você tem várias classes que diferem apenas em seu comportamento
		(variantes de um algoritmo).

		Quando você precisa alternar entre diferentes comportamentos de um objeto em tempo
		de execução.

		Para evitar grandes blocos de condicionais (if/else ou switch/case) espalhados
		pelo código para selecionar comportamentos semelhantes.

		Quando a lógica de um algoritmo deve ser isolada do código que a utiliza 
		(separação de responsabilidades).
	
	*/

	//declare(strict_type=1);


	//echo "Hello, World!";


	// interface que simula o comportamento de voar
	interface FlyBehaviador{
		
		public function fly():string;

	}


	interface QuackBehaviador{

		public function quack():string;
	}


	// implementando as classes concretas que implementa
	// a interface em seus metodos

	class FlyWithWings implements FlyBehaviador{
		
		public function fly():string{
			return "I flying!";
		}

	}


	class FlyNoWay implements FlyBehaviador{
		
		public function fly():string{
			return "don't flying!"; 
		}

	}

	class Quack implements QuackBehaviador{

		public function quack():string{

			return "Quack! Quack!";

		}

	}


	class Squeak implements QuackBehaviador{

		public function quack():string{
			
			return "Squeak! Squeak!";

		}

	}


	class MuteQuack implements QuackBehaviador{

		public function quack():string{
			return "...";
		}

	}

	// Implementando Duck que ira servir como 
	// class pai para os demais...

	class Duck {
		protected FlyWithWings|FlyNoWay|null $flyBehaviadorObject;
		protected Quack|Squeak|MuteQuack|null $quackBehaviadorObject;

		// Fazendo DI por associação
		public function __construct(
			FlyWithWings|FlyNoWay|null $flyComportament = null,
			Quack|Squeak|MuteQuack|null $quackComportament = null,
		) {
			$this->flyBehaviadorObject = $flyComportament;
			$this->quackBehaviadorObject = $quackComportament; // Removido o $ extra
		}

		public function display(bool $status = false): bool {
			if ($status == false) {
				return true;                 
			}
			return false;
		}

		public function swim(): string {
			return " swimming..."; // Corrigido o texto se quiser
		}

		public function performFly(): string {
			return $this->flyBehaviadorObject->fly();
		}

		public function performQuack(): string {
			// Removido o $ extra antes de quackBehaviadorObject
			return $this->quackBehaviadorObject->quack();
		}

		public function setFlyBehaviador($OtherObject) {
			// Removido o $ extra
			$this->flyBehaviadorObject = $OtherObject; 
		}

		public function setQuackBehaviador($OtherObject) {
			// Removido o $ extra
			$this->quackBehaviadorObject = $OtherObject;
		}
	}

	// Definindo classes que vão herdar de Duck

	class MallardDuck extends Duck {
		
		public function __construct() {
			// Opcional: já pode instanciar o pato selvagem com comportamentos padrão
			parent::__construct(new FlyWithWings(), new Quack());
		}

		public function display(bool $status = false): bool {
			// Executa o pai, inverte o valor booleano dele e já retorna
			return !parent::display($status);
		}
	}


	$pato_selvagem = new MallardDuck();


	echo $pato_selvagem->display(true);
	echo $pato_selvagem->performFly();
	echo $pato_selvagem->performQuack();

?>	

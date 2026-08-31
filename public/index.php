<?php

	# Importando a nossa classe
	include_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "router" . DIRECTORY_SEPARATOR . "RouterView.php";

	//echo "Hellow Word<br>";

	$url = $_GET['rota'] ?? '';

	#echo 'URI/URL COMPLETA: ' . $_SERVER['HTTP_HOST'] .'|'. $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'] . '<br>';
	#echo . $url;

	# aqui estou construindo dinamicamente a URI para determiando serviço
	#echo $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];

	#echo "<br>";
	#echo "PARABENS... VOCE CHEGOU ATÉ AQUI!";
	#echo "<br>";

	#echo $url; # . __DIR__  #. dirname(__DIR__);
	#echo '<br>';

	#$itens = array_diff(scandir(__DIR__), ['.', '..']);

	#$echo var_dump($itens);

	/**
	 *  Vamos instanciar nossa class RouterView
	 * que é o roteador para as Views e o mesmo vai materializar
	 * automaticamente via require_once uma página
	*/

	$rotear = new RouterView($url);

	$rotear->redirect();

?>	
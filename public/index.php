<?php


	//echo "Hellow Word<br>";


	$url = $_GET['url'] ?? '';


	echo 'URI/URL COMPLETA: ' . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'] . '<br>';
	#echo . $url;

	# aqui estou construindo dinamicamente a URI para determiando serviço
	#echo $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];

	echo "<br>";
	echo "PARABENS... VOCE CHEGOU ATÉ AQUI!"

?>	
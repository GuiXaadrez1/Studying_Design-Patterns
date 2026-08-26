<?php


	//echo "Hellow Word<br>";


	$url = $_GET['url'] ?? '';


	echo $_SERVER['REQUEST_URI'] . '<br>';
	echo $url;

	# aqui estou construindo dinamicamente a URI para determiando serviço
	#echo $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];


	echo "Parabens, você chegou até aqui!"

?>	
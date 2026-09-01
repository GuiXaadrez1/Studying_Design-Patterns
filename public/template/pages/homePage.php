<?php

    # obtém depedencias da controller homeController.php
    # para comunicação entre a view e a controller
    require_once dirname(__DIR__) . '/../controller/homeController.php';

    echo saudar(); # chama função saudar() da controller homeController.php

?>
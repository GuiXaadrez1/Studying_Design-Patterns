<?php 

// vamos importar a classe subject e observers que criamos de teste e implementar aqui
// require_once "./with_php/observer/project/WheaterData.php";

require_once __DIR__ . '/WheaterData.php';

class WheaterStation{

    public WheaterData $wheaterData;
    public CurrentConditionsDisplay $currentConditionsDisplay; 

    public function __construct()
    {
        // vamos passar depedência por composição sem a necessidade
        // de passar por construct... vamos criar diretamente no contrut
        // aclopamento forte

        $this->wheaterData = new WheaterData();
        $this->currentConditionsDisplay = new CurrentConditionsDisplay($this->wheaterData);
    }


};


function main(){
    
    $station = new WheaterStation(); 

    $station->wheaterData->setMeasurementsChanged(25.0,60.0,0.0);

    # $station->currentConditionsDisplay->display();

};


main();

?>
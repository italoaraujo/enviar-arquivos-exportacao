<?php

class LogSistema {

    var $arquivoLog;
    var $dirLog = 'logs';
	var $exibirMsg;

    function __construct($arquivoLog, $exibirMsg = false) {

        if (!file_exists($this->dirLog)) {
            mkdir($this->dirLog);
        }

        $this->arquivoLog = $this->dirLog. '/' . $arquivoLog;
		$this->exibirMsg = $exibirMsg;
    }

    function logMsg($msg) {

        $date = date('Y-m-d H:i:s');

        $msg = sprintf("[%s]: %s%s", $date, $msg, PHP_EOL);
        
        file_put_contents($this->arquivoLog, $msg, FILE_APPEND);
		
		if($this->exibirMsg){
			echo $msg. PHP_EOL;;
		}
    }
	
	function copiarArquivoLog($dirDestido){
		echo "Copiar de {$this->arquivoLog} para {$dirDestido}".PHP_EOL;
		copy($this->arquivoLog,$dirDestido);
		echo PHP_EOL;
		
	}
	

}
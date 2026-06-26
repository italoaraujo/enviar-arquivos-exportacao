<?php
echo  PHP_EOL;
require('config.php');

$dirClienteOrigem = DIR_ORIGEM_IMAGENS;
$dirClienteDestino = DIR_DESTINO_IMAGENS;

$pastaCsv = DIR_ORIGEM_DADOS;
$pastaCsvProcessados = DIR_PROCESSADOS;

$arquivoLog = date( 'Y-m-d'). '-enviar-imagens-log.txt';
$objLogSistema = new LogSistema( date( 'Y-m-d'). '-enviar-imagens-log.txt', true );
$objLogSistema->logMsg('Iniciando script');

try{
	$objMoverArquivos = new MoverArquivosFtp(FTP_SERVER, FTP_PORT, FTP_USERNAME, FTP_PASSWORD);
} catch (Exception $e) {
	$objLogSistema->logMsg($e->getMessage());
	exit();
}
function enviarArquivoComTentativas($objMoverArquivos, $objLogSistema, $arquivoOrigem, $arquivoDestino, $totalTentativas = 3)
{
	for ($tentativa = 1; $tentativa <= $totalTentativas; ++$tentativa) {
		$objLogSistema->logMsg("Tentativa {$tentativa}/{$totalTentativas} de copiar arquivo");

		try {
			if ($objMoverArquivos->copy($arquivoOrigem, $arquivoDestino)) {
				return true;
			}
		} catch (Exception $e) {
			$objLogSistema->logMsg($e->getMessage());
		}

		if ($tentativa < $totalTentativas) {
			$objLogSistema->logMsg('ERRO - Falha ao copiar arquivo. Reconectando ao FTP para tentar novamente');
			try {
				$objMoverArquivos->reconectar();
			} catch (Exception $e) {
				$objLogSistema->logMsg($e->getMessage());
			}
			sleep(2);
		}
	}

	return false;
}

function gerarCsvLotesComFalha($pastaCsv, $lotesComFalha)
{
	$arquivoCsvFalhas = $pastaCsv.DS."LISTA_LOTES_PARA_DETRAN_REENVIAR_".getTime()."_".uniqid(rand(1000, 9999)).".csv";
	if(file_put_contents($arquivoCsvFalhas, implode(PHP_EOL, $lotesComFalha).PHP_EOL) === false){
		return false;
	}

	return $arquivoCsvFalhas;
}


$lotes = array();	

try{
	if(!$objMoverArquivos->folderExists($dirClienteDestino)){
		$objMoverArquivos->mkdir($dirClienteDestino);		
	}
	
} catch (Exception $e) {
	$objLogSistema->logMsg($e->getMessage());
	exit();
}


foreach(glob($pastaCsv.DS."LISTA_LOTES_PARA_DETRAN_*.csv") as $csv){
	$totalSucesso = 0;
	$totalFalhas = 0;
	$lotesComFalha = array();
	$lotes = array();
	
	$objLogSistema->logMsg("Arquivo de csv: {$csv}" );
	
	$lotes = getLotesCsv($csv);
	$lotes = array_unique($lotes);
	$lotes = array_filter($lotes);
	
	foreach ($lotes as $lote){
		$loteFalhou = false;
		
		$nrLote = str_pad($lote,8,'0',STR_PAD_LEFT);
		$objLogSistema->logMsg("Inciando Lote: {$nrLote}" );

		$dirLoteOrigem = $dirClienteOrigem . DS . $nrLote ;
		$dirLoteDestino = $dirClienteDestino . "/" . $nrLote ;
		
		$arquivos = getArquivosLote($dirLoteOrigem); 

		if(count($arquivos) == 0){
			$objLogSistema->logMsg('ERRO - Nenhum arquivo pdf encontrado na pasta do lote');
			++$totalFalhas;
			$lotesComFalha[] = $lote;
			continue;
		}

		try{
			if(!$objMoverArquivos->folderExists($dirLoteDestino)){
				$objMoverArquivos->mkdir($dirLoteDestino);		
			}
			
			if(!$objMoverArquivos->folderExists($dirLoteDestino)){
				$objLogSistema->logMsg('ERRO - Nao conseguio cria a pasta do lote no ftp');
				++$totalFalhas;
				$lotesComFalha[] = $lote;
				$loteFalhou = true;
				continue;
			}

		} catch (Exception $e) {
		 	$objLogSistema->logMsg($e->getMessage());
			$loteFalhou = true;
			$lotesComFalha[] = $lote;
			continue;
		}
		
		
		foreach ($arquivos as $arquivoPdf){
			$objLogSistema->logMsg("Lote: {$nrLote} Arquivo: {$arquivoPdf}" );
			
			$dirArquivoPdfOrigem = $dirLoteOrigem.DS.$arquivoPdf;
		
			$dirArquivoPdfDestino = $dirLoteDestino."/".$arquivoPdf;
			
			
			# VERIFICA SE O ARQUIVO PDF DE ORIGEM EXISTE 
			if(file_exists($dirArquivoPdfOrigem)){
				
				$tamanhoArquivoOrigem = filesize($dirArquivoPdfOrigem);
				try{
					$tamanhoArquivoDestino = $objMoverArquivos->getFileSize($dirArquivoPdfDestino);
				} catch (Exception $e) {
					$objLogSistema->logMsg($e->getMessage());
					$loteFalhou = true;
					continue;
				}

				if($tamanhoArquivoOrigem != $tamanhoArquivoDestino){
					
					$objLogSistema->logMsg('Mover aquivo - '.$dirArquivoPdfOrigem . ' -> ' . $dirArquivoPdfDestino ); 

					if($tamanhoArquivoDestino != -1){
						$objLogSistema->logMsg('Arquivo no destino existe com tamanho diferente. Enviando novamente');
					}
					
					if(enviarArquivoComTentativas($objMoverArquivos, $objLogSistema, $dirArquivoPdfOrigem, $dirArquivoPdfDestino)){
						$objLogSistema->logMsg('Arquivo copiado com sucesso!');	
						++$totalSucesso;					
					} else {
						$objLogSistema->logMsg('ERRO - Erro ao copiar arquivo depois das tentativas');
						++$totalFalhas;
						$loteFalhou = true;
					}
				} else {
					++$totalSucesso;
					$objLogSistema->logMsg('O arquivo ja se encontra no destino');
				}
				
			} else {
				$objLogSistema->logMsg('ERRO - Arquivo de origem nao encontrado');
				++$totalFalhas;
				$loteFalhou = true;
			
			}
		}
	

		if($loteFalhou){
			$lotesComFalha[] = $lote;
		}
	}

	$lotesComFalha = array_unique(array_filter($lotesComFalha));

	$objLogSistema->logMsg("Mover arquivo de csv para pasta de processados: {$csv}" );
	if(!rename($csv, DIR_PROCESSADOS.DS.getTime().'_'.basename($csv) )){
		$objLogSistema->logMsg('ERRO - Erro ao mover arquivo de csv para pasta de processados');
	}

	if(count($lotesComFalha) > 0){
		$csvFalhas = gerarCsvLotesComFalha($pastaCsv, $lotesComFalha);
		if($csvFalhas){
			$objLogSistema->logMsg("Arquivo csv com lotes com falha criado: {$csvFalhas}" );
		} else {
			$objLogSistema->logMsg('ERRO - Erro ao criar arquivo csv com lotes com falha');
		}
	}

}

$objLogSistema->logMsg('Fim do script');


$objLogSistema->copiarArquivoLog(DIR_LOGS.DS.$arquivoLog );

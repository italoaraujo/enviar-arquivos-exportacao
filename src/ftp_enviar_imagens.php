<?php
echo  PHP_EOL;
require('config.php');

$dirClienteOrigem = DIR_ORIGEM_IMAGENS;
$dirClienteDestino = DIR_DESTINO_IMAGENS;

$pastaCsv = DIR_ORIGEM_DADOS;
$pastaCsvProcessados = DIR_PROCESSADOS;


$objLogSistema = new LogSistema( date( 'Y-m-d'). '-enviar-imagens-log.txt', true );
$objLogSistema->logMsg('Iniciando script');
try{
	$objMoverArquivos = new MoverArquivosFtp(FTP_SERVER, FTP_PORT, FTP_USERNAME, FTP_PASSWORD);
} catch (Exception $e) {
	$objLogSistema->logMsg($e->getMessage());
	//exit();
}
function enviarArquivoComTentativas($objMoverArquivos, $objLogSistema, $arquivoOrigem, $arquivoDestino, $totalTentativas = 3)
{
	for ($tentativa = 1; $tentativa <= $totalTentativas; ++$tentativa) {
		$objLogSistema->logMsg("Tentativa {$tentativa}/{$totalTentativas} de copiar arquivo");

		try {
			sleep(2);
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
	$arquivoCsvFalhas = $pastaCsv.DS."LISTA_LOTES_PARA_DETRAN_REENVIAR_".getTime().".csv";
	if(file_put_contents($arquivoCsvFalhas, implode(PHP_EOL, $lotesComFalha).PHP_EOL) === false){
		return false;
	}

	return $arquivoCsvFalhas;
}


$lotes = array();	



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
		$dirLoteDestino = $dirClienteDestino . DS . $nrLote ;
		
		$arquivos = getArquivosLote($dirLoteOrigem); 

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
		}
		
		if(!$loteFalhou){
			foreach ($arquivos as $arquivoPdf){
				$objLogSistema->logMsg("Lote: {$nrLote} Arquivo: {$arquivoPdf}" );
				
				$dirArquivoPdfOrigem = $dirLoteOrigem.DS.$arquivoPdf;
			
				$dirArquivoPdfDestino = $dirLoteDestino.DS.$arquivoPdf;
				
				
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
		}

		if($loteFalhou){
			$lotesComFalha[] = $lote;
		}
	}

	$lotesComFalha = array_unique(array_filter($lotesComFalha));
	$moveCsvOriginal = true;

	if(count($lotesComFalha) > 0){
		$csvFalhas = gerarCsvLotesComFalha($pastaCsv, $lotesComFalha);
		if($csvFalhas){
			$objLogSistema->logMsg("Arquivo csv com lotes com falha criado: {$csvFalhas}" );
		} else {
			$moveCsvOriginal = false;
			$objLogSistema->logMsg('ERRO - Erro ao criar arquivo csv com lotes com falha. CSV original nao foi movido');
		}
	}

	if($moveCsvOriginal){
		$objLogSistema->logMsg("Mover arquivo de csv para pasta de processados: {$csv}" );
		if(!rename($csv, DIR_PROCESSADOS.DS.getTime().'_'.basename($csv) )){
			$objLogSistema->logMsg('ERRO - Erro ao mover arquivo de csv para pasta de processados');
		}
	}
	$objLogSistema->logMsg('Fim do script');exit(); // linha temporaria , feita para executa so um arquivo por vez . Para evitar erro ao subir aquivos retroativo 
	
	
}
$objLogSistema->logMsg('Fim do script');

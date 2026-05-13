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
	exit();
}


$lotes = array();	


foreach(glob("{$pastaCsv}\\LISTA_LOTES_PARA_DETRAN_*.csv") as $csv){
	$totalSucesso = 0;
	$lotes = array();
	
	$objLogSistema->logMsg("Arquivo de csv: {$csv}" );
	
	$lotes = getLotesCsv($csv);
	$lotes = array_unique($lotes);
	$lotes = array_filter($lotes);
	
	foreach ($lotes as $lote){
		$nrLote = str_pad($lote,8,'0',STR_PAD_LEFT);
		
		$dirLoteOrigem = $dirClienteOrigem . '/' . $nrLote ;
		$dirLoteDestino = $dirClienteDestino . '/' . $nrLote ;
		
		$arquivos = getArquivosLote($dirLoteOrigem);
		
		if(!$objMoverArquivos->fileExists($dirLoteDestino)){
			$objMoverArquivos->mkdir($dirLoteDestino);		
		}
		
		if(!$objMoverArquivos->fileExists($dirLoteDestino)){
				$objLogSistema->logMsg('ERRO - Nao conseguio cria a pasta do lote no ftp');	
				continue;		
		}

		foreach ($arquivos as $arquivoPdf){
			$objLogSistema->logMsg("Lote: {$nrLote} Arquivo: {$arquivoPdf}" );
			
			$dirArquivoPdfOrigem = $dirLoteOrigem."/".$arquivoPdf;
		
			$dirArquivoPdfDestino = $dirLoteDestino.'/'.$arquivoPdf;
			 
			
			# VERIFICA SE O ARQUIVO PDF DE ORIGEM EXISTE 
			if(file_exists($dirArquivoPdfOrigem)){
				
				if(!$objMoverArquivos->fileExists($dirArquivoPdfDestino)){
					
					$objLogSistema->logMsg('Mover aquivo - '.$dirArquivoPdfOrigem . ' -> ' . $dirArquivoPdfDestino ); 
					
					if($objMoverArquivos->copy($dirArquivoPdfOrigem, $dirArquivoPdfDestino)){
						$objLogSistema->logMsg('Arquivo copiado com sucesso!');	
						++$totalSucesso;					
					} else {
						$objLogSistema->logMsg('ERRO - Erro ao copiar arquivo');
					}
					
				} else {
					++$totalSucesso;
					$objLogSistema->logMsg('O arquivo ja se encontra no destino');
				}
				
			} else {
				$objLogSistema->logMsg('ERRO - Arquivo de origem nao encontrado');
			  
			}
		}
	}
	if($totalSucesso > 0 || count($lotes) == 0 ){
		$objLogSistema->logMsg("Mover arquivo de csv para pasta de processados: {$csv}" );
		rename($csv, DIR_PROCESSADOS.DS.getTime().'_'.basename($csv) );
	}
	$objLogSistema->logMsg('Fim do script');exit(); // linha temporaria , feita para executa so um arquivo por vez . Para evitar erro ao subir aquivos retroativo 
	
	
}
$objLogSistema->logMsg('Fim do script');

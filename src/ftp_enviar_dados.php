<?php
echo  PHP_EOL;
require('config.php');


$objLogSistema = new LogSistema( date( 'Y-m-d'). '-enviar-dados-log.txt', true );
$objLogSistema->logMsg('Iniciando script');
try{
	$objMoverArquivos = new MoverArquivosFtp(FTP_SERVER, FTP_PORT, FTP_USERNAME, FTP_PASSWORD);
} catch (Exception $e) {
	$objLogSistema->logMsg($e->getMessage());
	$objLogSistema->logMsg('Fim do script');
	exit();
}


$arquivosSql = glob(DIR_ORIGEM_DADOS . DS . "*.sql");
if(count($arquivosSql) == 0){
	$objLogSistema->logMsg('Nenhum arquivo sql encontrado');
	$objLogSistema->logMsg('Fim do script');
	exit();
}	
foreach(glob(DIR_ORIGEM_DADOS . DS . "*.sql") as $arquivoSql){
	$objLogSistema->logMsg("Arquivo de sql: {$arquivoSql}" );
	
	$dirArquivoSqlDestino = DIR_DESTINO_DADOS. DS . basename($arquivoSql);
	
					
	$objLogSistema->logMsg('Mover aquivo - '.$arquivoSql . ' -> ' . $dirArquivoSqlDestino ); 
	
	if($objMoverArquivos->copy($arquivoSql, $dirArquivoSqlDestino)){
		$objLogSistema->logMsg('Arquivo copiado com sucesso!');		
	} else {
		$objLogSistema->logMsg('ERRO - Erro ao copiar arquivo');
	}
		
	$datetime = new DateTime();


	$objLogSistema->logMsg("Mover arquivo de sql para pasta de processados: {$arquivoSql}" );
	rename($arquivoSql, DIR_PROCESSADOS.DS.getTime().'_'.basename($arquivoSql) );
	
}
$objLogSistema->logMsg('Fim do script');

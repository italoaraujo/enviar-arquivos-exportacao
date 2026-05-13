<?php
ini_set("default_charset", "UTF-8");
set_time_limit(0);

//CARREGA CLASSES
function __autoload($Class){
	require("class/" . $Class . ".php");
}

require('helpers.php');

carregarEnv();

define("DS"	, DIRECTORY_SEPARATOR);

define("PROTOCOLO"				, getenv('PROTOCOLO'));

define("FTP_SERVER"				, getenv('FTP_SERVER'));
define("FTP_PORT"				, getenv('FTP_PORT'));
define("FTP_USERNAME"			, getenv('FTP_USERNAME'));
define("FTP_PASSWORD"			, getenv('FTP_PASSWORD'));

define("DIR_ORIGEM_DADOS"		, getenv('DIR_ORIGEM_DADOS'));
define("DIR_DESTINO_DADOS"		, getenv('DIR_DESTINO_DADOS'));
define("DIR_ORIGEM_IMAGENS"		, getenv('DIR_ORIGEM_IMAGENS'));
define("DIR_DESTINO_IMAGENS"	, getenv('DIR_DESTINO_IMAGENS'));
define("DIR_PROCESSADOS"		, DIR_ORIGEM_DADOS.DS.getenv('NOME_PASTA_PROCESSADOS'));



<?php

function carregarEnv($caminho = '.env') {
    // Verifica se o arquivo .env existe
    if (!file_exists($caminho)) {
        throw new Exception("Arquivo .env não encontrado!");
    }

    // Lê o arquivo linha por linha
    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {
        // Ignora linhas de comentário
        if (strpos($linha, '#') === 0) {
            continue;
        }

        // Verifica se a linha contém o caractere '='
        if (strpos($linha, '=') !== false) {
            // Separa a chave e o valor
            list($chave, $valor) = explode('=', $linha, 2);

            // Remover espaços em branco antes e depois
            $chave = trim($chave);
            $valor = trim($valor);

            // Define a variável de ambiente no PHP
            putenv("$chave=$valor");

            // Também define como variável global
            $_ENV[$chave] = $valor;
        }
    }
}

function getTime(){
	$datetime = new DateTime();
	return $datetime->format("Y-m-d_H-i-s");
}

function getLotesCsv($csv){
	$lotes = array();
	if (($handle = fopen($csv, 'r')) !== false) {
		while (($linha = fgets($handle, 1000 )) !== false) {
			$lotes[] = trim($linha);
		}
		fclose($handle); 
	}
	return $lotes;
}

function getArquivosLote($dirLote){
	$arquivos = array();
	foreach(glob($dirLote.DS."*.pdf") as $arquivo){
		$arquivos[] = basename($arquivo);
	}
	return $arquivos;
}
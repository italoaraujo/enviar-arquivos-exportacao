<?php
echo PHP_EOL;
require('config.php');

$dirClienteOrigem = DIR_ORIGEM_IMAGENS;
$dirClienteDestino = DIR_DESTINO_IMAGENS;

$pastaCsv = DIR_ORIGEM_DADOS;
$pastaCsvProcessados = DIR_PROCESSADOS;

$arquivoLog = date('Y-m-d') . '-enviar-imagens-log.txt';
$objLogSistema = new LogSistema($arquivoLog, true);
$objLogSistema->logMsg('Iniciando script');

$caminhoLogDestino = DIR_LOGS . DS . $arquivoLog;
$GLOBALS['OBJ_LOG_SISTEMA'] = $objLogSistema;
$GLOBALS['CAMINHO_LOG_DESTINO'] = $caminhoLogDestino;
$GLOBALS['OBJ_MOVER_ARQUIVOS'] = null;

function shutdownEnviaImagens()
{
	$error = error_get_last();
	if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
		if (isset($GLOBALS['OBJ_LOG_SISTEMA']) && is_object($GLOBALS['OBJ_LOG_SISTEMA'])) {
			$GLOBALS['OBJ_LOG_SISTEMA']->logMsg("ERRO FATAL PHP: [Linha {$error['line']} em {$error['file']}] {$error['message']}");
		}
	}

	if (isset($GLOBALS['OBJ_LOG_SISTEMA']) && is_object($GLOBALS['OBJ_LOG_SISTEMA'])) {
		$GLOBALS['OBJ_LOG_SISTEMA']->logMsg('Fim do script');
		if (!empty($GLOBALS['CAMINHO_LOG_DESTINO'])) {
			$GLOBALS['OBJ_LOG_SISTEMA']->copiarArquivoLog($GLOBALS['CAMINHO_LOG_DESTINO']);
		}
	}

	if (isset($GLOBALS['OBJ_MOVER_ARQUIVOS']) && is_object($GLOBALS['OBJ_MOVER_ARQUIVOS'])) {
		$GLOBALS['OBJ_MOVER_ARQUIVOS']->fecharConexao();
	}
}

register_shutdown_function('shutdownEnviaImagens');

try {
	$objMoverArquivos = new MoverArquivosFtp(FTP_SERVER, FTP_PORT, FTP_USERNAME, FTP_PASSWORD);
	$GLOBALS['OBJ_MOVER_ARQUIVOS'] = $objMoverArquivos;
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

function salvarLotesCsv($arquivoCsv, $lotes)
{
	$arquivoTmp = $arquivoCsv . '.' . uniqid('tmp_') . '.tmp';
	$conteudo = count($lotes) > 0 ? implode(PHP_EOL, $lotes) . PHP_EOL : '';
	if (file_put_contents($arquivoTmp, $conteudo) === false) {
		return false;
	}
	if (file_exists($arquivoCsv)) {
		@unlink($arquivoCsv);
	}
	$resultado = @rename($arquivoTmp, $arquivoCsv);
	if (!$resultado) {
		$resultado = (file_put_contents($arquivoCsv, $conteudo) !== false);
		@unlink($arquivoTmp);
	}
	return $resultado;
}

function adicionarLoteCsvFalhas($arquivoCsvFalhas, $lote)
{
	return (file_put_contents($arquivoCsvFalhas, $lote . PHP_EOL, FILE_APPEND) !== false);
}

function gerarCsvLotesComFalha($pastaCsv, $lotesComFalha)
{
	$arquivoCsvFalhas = $pastaCsv . DS . "LISTA_LOTES_PARA_DETRAN_REENVIAR_" . getTime() . "_" . uniqid(rand(1000, 9999)) . ".csv";
	if (file_put_contents($arquivoCsvFalhas, implode(PHP_EOL, $lotesComFalha) . PHP_EOL) === false) {
		return false;
	}

	return $arquivoCsvFalhas;
}

try {
	if (!$objMoverArquivos->folderExists($dirClienteDestino)) {
		$objMoverArquivos->mkdir($dirClienteDestino);
	}
} catch (Exception $e) {
	$objLogSistema->logMsg($e->getMessage());
	exit();
}

$arquivosCsv = glob($pastaCsv . DS . "LISTA_LOTES_PARA_DETRAN_*.csv");
if (!$arquivosCsv) {
	$arquivosCsv = array();
}

foreach ($arquivosCsv as $csv) {
	if (!file_exists($csv) || strpos(basename($csv), '.tmp') !== false) {
		continue;
	}

	$objLogSistema->logMsg("Arquivo de csv: {$csv}");

	$lotes = getLotesCsv($csv);
	$lotes = array_unique($lotes);
	$lotes = array_filter($lotes);
	$lotes = array_values($lotes);

	if (count($lotes) == 0) {
		$objLogSistema->logMsg("Arquivo de csv sem lotes pendentes: {$csv}");
		@rename($csv, DIR_PROCESSADOS . DS . getTime() . '_' . basename($csv));
		continue;
	}

	// Cria backup de segurança do CSV original completo em DIR_PROCESSADOS
	$arquivoBackup = DIR_PROCESSADOS . DS . getTime() . '_BACKUP_ORIGINAL_' . basename($csv);
	if (@copy($csv, $arquivoBackup)) {
		$objLogSistema->logMsg("Backup do CSV original salvo em: {$arquivoBackup}");
	}

	$arquivoCsvFalhas = $pastaCsv . DS . "LISTA_LOTES_PARA_DETRAN_REENVIAR_" . getTime() . "_" . uniqid(rand(1000, 9999)) . ".csv";
	$totalFalhasCsv = 0;
	$totalSucessoCsv = 0;
	$lotesPendentes = $lotes;
	$falhasConsecutivasConexao = 0;

	foreach ($lotes as $lote) {
		$loteFalhou = false;
		$falhaPorConexao = false;

		$nrLote = str_pad($lote, 8, '0', STR_PAD_LEFT);
		$objLogSistema->logMsg("Iniciando Lote: {$nrLote}");

		// Verifica se a pasta base de origem ainda está acessível (unidade de rede mapeada)
		if (!is_dir($dirClienteOrigem)) {
			$objLogSistema->logMsg("ERRO CRÍTICO - Diretório de origem das imagens não está acessível: {$dirClienteOrigem}. Verificando reconexão...");
			$restabelecido = false;
			for ($tentativaRede = 1; $tentativaRede <= 3; ++$tentativaRede) {
				sleep(5);
				if (is_dir($dirClienteOrigem)) {
					$restabelecido = true;
					$objLogSistema->logMsg("Diretório de origem restabelecido.");
					break;
				}
			}
			if (!$restabelecido) {
				$objLogSistema->logMsg("ERRO - Diretório de origem permanece inacessível. Interrompendo processamento deste CSV para preservar lotes restantes.");
				break;
			}
		}

		$dirLoteOrigem = $dirClienteOrigem . DS . $nrLote;
		$dirLoteDestino = $dirClienteDestino . "/" . $nrLote;

		$arquivos = getArquivosLote($dirLoteOrigem);

		if (count($arquivos) == 0) {
			$objLogSistema->logMsg('ERRO - Nenhum arquivo pdf encontrado na pasta do lote');
			$loteFalhou = true;
		} else {
			try {
				if (!$objMoverArquivos->folderExists($dirLoteDestino)) {
					$objMoverArquivos->mkdir($dirLoteDestino);
				}

				if (!$objMoverArquivos->folderExists($dirLoteDestino)) {
					$objLogSistema->logMsg('ERRO - Nao conseguiu criar a pasta do lote no ftp');
					$loteFalhou = true;
				}
			} catch (Exception $e) {
				$objLogSistema->logMsg($e->getMessage());
				$loteFalhou = true;
				$falhaPorConexao = true;
			}

			if (!$loteFalhou) {
				foreach ($arquivos as $arquivoPdf) {
					$objLogSistema->logMsg("Lote: {$nrLote} Arquivo: {$arquivoPdf}");

					$dirArquivoPdfOrigem = $dirLoteOrigem . DS . $arquivoPdf;
					$dirArquivoPdfDestino = $dirLoteDestino . "/" . $arquivoPdf;

					if (file_exists($dirArquivoPdfOrigem)) {
						$tamanhoArquivoOrigem = filesize($dirArquivoPdfOrigem);
						try {
							$tamanhoArquivoDestino = $objMoverArquivos->getFileSize($dirArquivoPdfDestino);
						} catch (Exception $e) {
							$objLogSistema->logMsg($e->getMessage());
							$loteFalhou = true;
							$falhaPorConexao = true;
							continue;
						}

						if ($tamanhoArquivoOrigem != $tamanhoArquivoDestino) {
							$objLogSistema->logMsg('Mover arquivo - ' . $dirArquivoPdfOrigem . ' -> ' . $dirArquivoPdfDestino);

							if ($tamanhoArquivoDestino != -1) {
								$objLogSistema->logMsg('Arquivo no destino existe com tamanho diferente. Enviando novamente');
							}

							if (enviarArquivoComTentativas($objMoverArquivos, $objLogSistema, $dirArquivoPdfOrigem, $dirArquivoPdfDestino)) {
								$objLogSistema->logMsg('Arquivo copiado com sucesso!');
							} else {
								$objLogSistema->logMsg('ERRO - Erro ao copiar arquivo depois das tentativas');
								$loteFalhou = true;
								$falhaPorConexao = true;
							}
						} else {
							$objLogSistema->logMsg('O arquivo ja se encontra no destino');
						}
					} else {
						$objLogSistema->logMsg('ERRO - Arquivo de origem nao encontrado');
						$loteFalhou = true;
					}
				}
			}
		}

		// Processamento pós-lote (INCREMENTAL)
		if ($loteFalhou) {
			$totalFalhasCsv++;
			$objLogSistema->logMsg("Lote {$nrLote} finalizado com FALHA. Gravando no arquivo de reenvio.");
			adicionarLoteCsvFalhas($arquivoCsvFalhas, $lote);

			if ($falhaPorConexao) {
				$falhasConsecutivasConexao++;
				if ($falhasConsecutivasConexao >= 3) {
					$objLogSistema->logMsg('AVISO - Multiplas falhas consecutivas de conexao com o FTP. Aguardando 30 segundos antes de tentar o proximo lote...');
					sleep(30);
					try {
						$objMoverArquivos->reconectar();
					} catch (Exception $e) {
						$objLogSistema->logMsg($e->getMessage());
					}
					$falhasConsecutivasConexao = 0;
				}
			}
		} else {
			$totalSucessoCsv++;
			$falhasConsecutivasConexao = 0;
			$objLogSistema->logMsg("Lote {$nrLote} finalizado com SUCESSO.");
		}

		// Remove o lote da lista de pendentes
		$posicao = array_search($lote, $lotesPendentes);
		if ($posicao !== false) {
			unset($lotesPendentes[$posicao]);
			$lotesPendentes = array_values($lotesPendentes);
		}

		// Atualiza o arquivo CSV em disco a cada lote
		if (count($lotesPendentes) > 0) {
			salvarLotesCsv($csv, $lotesPendentes);
		} else {
			$objLogSistema->logMsg("Todos os lotes do CSV foram processados. Movendo para pasta de processados: {$csv}");
			if (file_exists($csv)) {
				$destinoProcessado = DIR_PROCESSADOS . DS . getTime() . '_' . basename($csv);
				if (!@rename($csv, $destinoProcessado)) {
					@unlink($csv);
				}
			}
		}
	}

	if (count($lotesPendentes) > 0) {
		$objLogSistema->logMsg("Processamento do CSV interrompido. Restam " . count($lotesPendentes) . " lote(s) pendente(s) no arquivo: {$csv}");
	}

	if ($totalFalhasCsv > 0 && file_exists($arquivoCsvFalhas)) {
		$objLogSistema->logMsg("Arquivo csv com lotes com falha criado para reenvio: {$arquivoCsvFalhas}");
	} elseif (file_exists($arquivoCsvFalhas)) {
		@unlink($arquivoCsvFalhas);
	}
}

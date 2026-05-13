<?php

class MoverArquivosFtp
{

    protected $tipo, $host, $porta, $usuario, $senha, $conexecao;

    function __construct($host, $porta, $usuario, $senha)
    {
        $this->host = $host;
        $this->porta = $porta;
        $this->usuario = $usuario;
        $this->senha = $senha;

        $this->conectar();
    }
	
	protected function verificarConexao()
    {
        if (!$this->conexecao || !@ftp_raw($this->conexecao, "NOOP")) {
			echo 'tentando conecta';exit();
            $this->conectar();
        }
    }

    protected function conectar()
    {
        $this->conexecao = ftp_connect($this->host, $this->porta);

        if (!$this->conexecao) {
            throw new Exception('ERRO - Erro ao se conecta ao FTP');
        }


        $loginResult = ftp_login($this->conexecao, $this->usuario, $this->senha);


        if (!$loginResult) {
            throw new Exception('ERRO - Erro ao logar ao FTP');
        }

        ftp_pasv($this->conexecao, true);
    }
	
    public function mkdir($pastaRemota)
    {
		$this->verificarConexao();
        return ftp_mkdir($this->conexecao, $pastaRemota);
    }

    public function copy($arquivoOrigem, $arquivoDestino)
    {
		$this->verificarConexao();
        return ftp_put($this->conexecao,  $arquivoDestino, $arquivoOrigem, FTP_BINARY);
    }


    public function fileExists($pastaArquivo)
    {
		$this->verificarConexao();
        $encontrado = false;

        // Salvar configurações atuais
        $reportingLevel = error_reporting();

        // Desativar avisos para a função específica
        error_reporting($reportingLevel & ~E_WARNING);

        if (ftp_chdir($this->conexecao, $pastaArquivo) || ftp_mdtm($this->conexecao, $pastaArquivo) != -1) {
            $encontrado = true;
        }
        // Restaurar configurações
        error_reporting($reportingLevel);

        return $encontrado;
    }
	
	public function __destruct()
    {
        if ($this->conexao) {
            ftp_close($this->conexao);
        }
    }
}

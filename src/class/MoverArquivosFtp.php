<?php

class MoverArquivosFtp
{

    protected $tipo, $host, $porta, $usuario, $senha, $conexao;

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
        if (!$this->conexao || !@ftp_raw($this->conexao, "NOOP")) {
            $this->conectar();
        }
    }

    protected function conectar()
    {
        if ($this->conexao) {
            @ftp_close($this->conexao);
        }

        $this->conexao = ftp_connect($this->host, $this->porta);

        if (!$this->conexao) {
            throw new Exception('ERRO - Erro ao se conecta ao FTP');
        }


        $loginResult = ftp_login($this->conexao, $this->usuario, $this->senha);


        if (!$loginResult) {
            throw new Exception('ERRO - Erro ao logar ao FTP');
        }

        ftp_pasv($this->conexao, true);
    }

    public function reconectar()
    {
        $this->conectar();
    }
	
    public function mkdir($pastaRemota)
    {
		$this->verificarConexao();
        return ftp_mkdir($this->conexao, $pastaRemota);
    }

    public function copy($arquivoOrigem, $arquivoDestino)
    {
		$this->verificarConexao();
        //sleep(1);
        return ftp_put($this->conexao,  $arquivoDestino, $arquivoOrigem, FTP_BINARY);
    }


    public function folderExists($pasta)
    {

		$this->verificarConexao();

        $diretorioAtual = ftp_pwd($this->conexao);

        if (@ftp_chdir($this->conexao, $pasta)) {

            // volta para o diretório original
            ftp_chdir($this->conexao, $diretorioAtual);

            return true;
        }

        return false;
    }

    public function fileExists($arquivo)
    {

		$this->verificarConexao();
        return $this->getFileSize($arquivo) != -1;
    }

    public function getFileSize($arquivo)
    {

		$this->verificarConexao();
        return ftp_size($this->conexao, $arquivo);
    }
	
    
	public function __destruct()
    {
        if ($this->conexao) {
            @ftp_close($this->conexao);
        }
    }
}

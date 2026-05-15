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
        //sleep(1);
        return ftp_put($this->conexecao,  $arquivoDestino, $arquivoOrigem, FTP_BINARY);
    }


    public function folderExists($pasta)
    {

		$this->verificarConexao();

        $diretorioAtual = ftp_pwd($this->conexecao);

        if (@ftp_chdir($this->conexecao, $pasta)) {

            // volta para o diretório original
            ftp_chdir($this->conexecao, $diretorioAtual);

            return true;
        }

        return false;
    }

    public function fileExists($arquivo)
    {

		$this->verificarConexao();
        return ftp_size($this->conexecao, $arquivo) != -1;
    }
	
    
	public function __destruct()
    {
        if ($this->conexao) {
            ftp_close($this->conexao);
        }
    }
}

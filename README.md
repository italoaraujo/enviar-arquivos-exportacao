# Sistema de Envio de Arquivos para FTP (Integração DETRAN)

Este projeto é uma ferramenta desenvolvida em PHP para automatizar o envio de dados e imagens (arquivos PDF organizados por lotes) para um servidor FTP remoto. O sistema conta com tratamento de erros, logs detalhados, controle de tentativas de reenvio, e vem acompanhado de um interpretador PHP 5 portátil integrado para execução imediata em sistemas Windows, além de suporte a Docker para ambientes Linux/macOS.

---

## 📂 Estrutura do Projeto

Abaixo está a estrutura de diretórios e arquivos principais do projeto:

```text
enviar_arquivos/
├── .env                       # Arquivo de configuração de ambiente (produção/local)
├── .env-example               # Modelo para criação do arquivo .env
├── .gitignore                 # Arquivos ignorados pelo Git
├── enviar_dados.bat           # Script Windows para iniciar o envio de dados (.sql)
├── enviar_imagens.bat         # Script Windows para iniciar o envio de imagens (.pdf)
├── enviar_imagens.sh          # Script Shell/Docker para rodar o envio de imagens em Linux/macOS
├── logs/                      # Pasta local temporária para logs gerados durante as execuções
├── src/                       # Código-fonte principal do sistema em PHP
│   ├── class/                 # Classes orientadas a objetos do sistema
│   │   ├── LogSistema.php     # Gerenciamento de logs locais e cópias finais
│   │   └── MoverArquivosFtp.php# Cliente FTP personalizado com auto-reconexão e verificações
│   ├── config.php             # Arquivo de inicialização, definições globais e carregamento de dependências
│   ├── ftp_enviar_dados.php   # Lógica de envio de arquivos de dados (.sql)
│   ├── ftp_enviar_imagens.php # Lógica de leitura de lotes (CSV) e envio de imagens (.pdf)
│   ├── helpers.php            # Funções utilitárias (.env, listagem de arquivos, parsing de CSV)
│   └── php/                   # Interpretador PHP 5 portátil para Windows (contém php.exe, php.ini e DLLs)
```

---

## ⚙️ Configuração

Antes de executar os scripts, copie o arquivo `.env-example` para `.env` na raiz do projeto e preencha as variáveis de ambiente necessárias:

```ini
PROTOCOLO=ftp

# Configurações do servidor FTP
FTP_SERVER=ftp.exemplo.com.br
FTP_PORT=21
FTP_USERNAME=seu_usuario
FTP_PASSWORD=sua_senha

# Diretórios de Dados (Arquivos .sql)
DIR_ORIGEM_DADOS=C:\Caminho\Local\Para\Dados
DIR_DESTINO_DADOS=/Caminho/Remoto/Ftp/Dados

# Diretórios de Imagens (Arquivos .pdf organizados em subpastas de lotes)
DIR_ORIGEM_IMAGENS=C:\Caminho\Local\Para\Imagens
DIR_DESTINO_IMAGENS=/Caminho/Remoto/Ftp/Imagens

# Configurações do fluxo
NOME_PASTA_PROCESSADOS=arquivos_processados
NOME_PASTA_LOG=logs_processamento
```

### Detalhes das Pastas:
- **`DIR_ORIGEM_DADOS`**: Deve conter os arquivos de lote no formato `LISTA_LOTES_PARA_DETRAN_*.csv` e/ou os arquivos `.sql` de dados.
- **`DIR_ORIGEM_IMAGENS`**: Deve conter subpastas nomeadas com o número do lote (com ou sem zeros à esquerda, ex: `00012345` ou `12345`), contendo os PDFs daquele lote.
- **`DIR_PROCESSADOS`** (Criado automaticamente): Após o processamento bem-sucedido, os arquivos CSV e SQL de origem serão movidos para esta pasta, prefixados com o carimbo de data e hora para evitar duplicidade.

---

## 🚀 Como Executar

### 💻 No Windows (Utilizando o PHP portátil integrado)

O projeto inclui um interpretador PHP em `src/php`, eliminando a necessidade de instalar o PHP globalmente na máquina.

#### 1. Envio de Imagens (PDFs)
Executa a leitura dos lotes pendentes através de arquivos CSV e envia as imagens correspondentes:
- Dê dois cliques em [enviar_imagens.bat](file:///c:/Users/italo/OneDrive/projetos/enviar_arquivos/enviar_imagens.bat) na raiz do projeto.

#### 2. Envio de Dados (SQLs)
Verifica se existem arquivos `.sql` locais e realiza o envio para a pasta de destino do FTP:
- Dê dois cliques em [enviar_dados.bat](file:///c:/Users/italo/OneDrive/projetos/enviar_arquivos/enviar_dados.bat) na raiz do projeto.

---

### 🐧 No Linux / macOS (Via Docker)

Caso queira executar em ambientes Unix sem PHP instalado localmente, você pode usar o Docker. Há um script preparado para utilizar a imagem oficial do `php:5.6-cli`:

- Execute o script [enviar_imagens.sh](file:///c:/Users/italo/OneDrive/projetos/enviar_arquivos/enviar_imagens.sh):
  ```bash
  ./enviar_imagens.sh
  ```
  *(Certifique-se de dar permissão de execução: `chmod +x enviar_imagens.sh`)*

---

## 🧠 Funcionamento Detalhado dos Scripts

### 📋 Envio de Imagens (`src/ftp_enviar_imagens.php`)

1. **Leitura dos CSVs**: O script procura arquivos no padrão `LISTA_LOTES_PARA_DETRAN_*.csv` dentro de `DIR_ORIGEM_DADOS`.
2. **Varredura por Lote**:
   - Cada lote lido do CSV tem seu nome formatado com preenchimento de zeros à esquerda (ex: lote `5432` vira `00005432`).
   - O script localiza a pasta correspondente em `DIR_ORIGEM_IMAGENS/{LOTE}` e coleta todos os arquivos `.pdf`.
3. **Validação e Envio Concorrente**:
   - Cria o diretório do lote no servidor FTP caso não exista.
   - Compara o tamanho do arquivo local com o arquivo remoto (FTP).
   - Se o tamanho diferir ou o arquivo não existir remotamente, realiza o upload em modo binário (`FTP_BINARY`).
   - Possui **mecanismo de resiliência**: faz até 3 tentativas de envio por arquivo. Em caso de falha de conexão, ele executa uma reconexão FTP automática antes da nova tentativa.
4. **Tratamento de Falhas**:
   - Lotes que não contêm arquivos locais, falham ao criar a pasta no FTP ou apresentam falhas persistentes de transferência são classificados como **Lotes com Falha**.
   - Ao fim da execução, o arquivo CSV original é movido para `DIR_PROCESSADOS` com prefixo de data e hora.
   - Caso existam lotes com falha, um novo arquivo CSV `LISTA_LOTES_PARA_DETRAN_REENVIAR_[timestamp]_[uniqid].csv` é gerado na origem contendo apenas os lotes problemáticos para que possam ser reprocessados em rodadas posteriores.
5. **Logs**: Ao terminar, copia o log consolidado para o diretório de destino de logs configurado.

### 🗃️ Envio de Dados (`src/ftp_enviar_dados.php`)

1. **Varredura de SQLs**: Procura arquivos `.sql` em `DIR_ORIGEM_DADOS`.
2. **Envio Direto**: Envia cada arquivo `.sql` encontrado para o diretório `DIR_DESTINO_DADOS` do FTP.
3. **Finalização**: Move o arquivo `.sql` original para a pasta de arquivos processados, aplicando o prefixo de timestamp.

---

## 🛠️ Tecnologias Utilizadas

- **PHP 5.x / 5.6** (utilizando funções nativas da extensão FTP).
- **PHP Portátil Integrado** (para facilidade de implantação em Windows).
- **Docker** (para compatibilidade multiplataforma).
- **Dotenv customizado** (leitor leve implementado em `src/helpers.php`).

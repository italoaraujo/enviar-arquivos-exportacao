@echo off
src\php\php.exe -c src\php\php.ini -d output_buffering=0 src\ftp_enviar_imagens.php "%%I"

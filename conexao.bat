@echo off
setlocal

REM Aguarda o servidor responder
:AGUARDA
ping -n 1 192.168.11.19 >nul
if errorlevel 1 (
    timeout /t 5 /nobreak >nul
    goto AGUARDA
)

echo Verificando unidade Y:...
dir Y:\ >nul 2>&1
if errorlevel 1 (
    echo Reconectando Y:...
    net use Y: /delete /y >nul 2>&1
    net use Y: \\192.168.11.19\imagens_detran_somente_leitura "" /user:guest /persistent:no
) else (
    echo Y: OK.
)

echo Verificando unidade Z:...
dir Z:\ >nul 2>&1
if errorlevel 1 (
    echo Reconectando Z:...
    net use Z: /delete /y >nul 2>&1
    net use Z: \\192.168.11.19\exportacao "" /user:guest /persistent:no
) else (
    echo Z: OK.
)

echo.
echo Concluido.
exit /b

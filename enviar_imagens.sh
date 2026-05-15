#!/bin/bash
docker run -it --rm \
-v $(pwd):/app \
-v /mnt/stg_homo_godocs:/mnt/stg_homo_godocs \
-w /app \
php:5.6-cli php src/ftp_enviar_imagens.php
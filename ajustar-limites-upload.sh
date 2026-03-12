#!/bin/bash

# Script para ajustar limites de upload do PHP
# Execute: bash ajustar-limites-upload.sh

echo "=== Ajuste de Limites de Upload do PHP ==="
echo ""

# Encontrar php.ini
PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')

if [ -z "$PHP_INI" ]; then
    echo "❌ Não foi possível encontrar o arquivo php.ini"
    exit 1
fi

echo "📁 Arquivo php.ini encontrado: $PHP_INI"
echo ""
echo "⚠️  ATENÇÃO: Você precisa editar este arquivo manualmente!"
echo ""
echo "1. Abra o arquivo em um editor:"
echo "   nano $PHP_INI"
echo "   ou"
echo "   code $PHP_INI"
echo ""
echo "2. Procure por estas linhas (use Ctrl+W para buscar):"
echo "   - upload_max_filesize"
echo "   - post_max_size"
echo ""
echo "3. Altere os valores para:"
echo "   upload_max_filesize = 10M"
echo "   post_max_size = 30M"
echo "   max_file_uploads = 20"
echo "   max_execution_time = 300"
echo "   max_input_time = 300"
echo "   memory_limit = 256M"
echo ""
echo "4. Salve o arquivo (Ctrl+O, Enter, Ctrl+X no nano)"
echo ""
echo "5. REINICIE o servidor PHP:"
echo "   - Pare o servidor atual (Ctrl+C no terminal onde está rodando)"
echo "   - Execute novamente: php artisan serve"
echo ""
echo "6. Verifique se funcionou:"
echo "   php -r \"echo 'post_max_size: ' . ini_get('post_max_size') . PHP_EOL; echo 'upload_max_filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;\""
echo ""
echo "Você deve ver:"
echo "   post_max_size: 30M"
echo "   upload_max_filesize: 10M"
echo ""

<?php
echo "<pre>";
echo "getenv('OPENAI_API_KEY'): ";
var_dump(getenv('OPENAI_API_KEY'));

echo "\n\n\$_ENV['OPENAI_API_KEY']: ";
var_dump($_ENV['OPENAI_API_KEY'] ?? null);

echo "\n\nphpinfo():\n";
phpinfo();

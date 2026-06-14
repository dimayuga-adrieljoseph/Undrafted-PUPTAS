<?php
$file = 'storage/logs/laravel.log';
$f = fopen($file, 'r');
fseek($f, -10000, SEEK_END);
echo fread($f, 10000);
fclose($f);

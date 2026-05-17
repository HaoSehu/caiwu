<?php

chdir(__DIR__);
$output = [];
$code = 0;
exec('D:\\BtSoft\\php\\83\\php.exe artisan migrate --force --no-ansi 2>&1', $output, $code);
file_put_contents(__DIR__.'/_migrate_result.txt', "exit_code={$code}\n".implode("\n", $output));
echo "done, exit_code={$code}\n";

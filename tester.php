<?php
$config = parse_ini_file('/home/craig/git/it490-Final-Submission/getInfo.ini');

foreach ($config as $key => $value) {
    if (strpos($key, 'command') === 0) {
        echo "Running $key: $value\n";
        $output = shell_exec($value);
        echo "Output:\n" . ($output ?: "[no output returned]") . "\n\n";
    }
}
?>
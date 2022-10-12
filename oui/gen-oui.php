<?php

//download url:
// https://standards-oui.ieee.org/oui/oui.txt
// https://github.com/vcrhonek/hwdata/blob/master/oui.txt
$oui_download_url = 'https://standards-oui.ieee.org/oui/oui.txt';


if (!file_exists('oui.original')) {
    echo "[+] Downloading updated oui.txt...\n";
    $remote_file = file_get_contents($oui_download_url);
    file_put_contents('oui.original', $remote_file);
}

echo "[+] Processing oui.txt...\n";
@unlink('oui.txt');

$flag   = '(base 16)';
$total  = 0;
$output = [];
$index  = [];
foreach (file('oui.original') as $line) {
    if (strpos($line, $flag) !== false){
        $parts = explode($flag, $line);
        $id    = mb_strtoupper(trim($parts[0]));

        // repeat : 0001C8 
        // chars  : 48BCA6, 489153, 4898CA
        if (!in_array($id, $index, true)) {
            $total++;
            $index[] = $id;

            $text = ucwords(mb_strtolower($parts[1]));
            //$text = preg_replace('/[[:^print:]]/', '', $text);
            //$text = filter_var($text, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
            //$text = preg_replace('/\s+/u', ' ', $text);
            $text = str_replace(['.', ','], '', $text);
            $output[] = $id . trim($text);
        }
    }
}

sort($output);
$output[] = '';

file_put_contents('oui.txt', implode("\n", $output));
unlink('oui.original');

echo "[+] Processing complete!\n";
echo "[*] File: oui.txt - Lines: {$total}\n";

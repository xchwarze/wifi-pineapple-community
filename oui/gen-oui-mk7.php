<?php

/**
 * Generate compact OUI JSON map (OUI => Vendor).
 */
$ouiDownloadUrl = 'https://standards-oui.ieee.org/oui/oui.txt';
$sourceFile     = 'oui.original';
$outputFile     = 'oui-mk7.json';

if (!file_exists($sourceFile)) {
    $data = file_get_contents($ouiDownloadUrl);
    if ($data === false) {
        exit("Download failed\n");
    }
    file_put_contents($sourceFile, $data);
}

$flag = '(base 16)';
$map  = [];
$handle = fopen($sourceFile, 'r');
while (($line = fgets($handle)) !== false) {
    if (strpos($line, $flag) === false) {
        continue;
    }

    [$id, $vendor] = explode($flag, $line, 2);

    $id = strtoupper(trim($id));

    if (isset($map[$id])) {
        continue;
    }

    $vendor = trim($vendor);
    $vendor = str_replace(['.', ','], '', $vendor);
    $vendor = ucwords(strtolower($vendor));

    $map[$id] = $vendor;
}

fclose($handle);
unlink($sourceFile);

ksort($map, SORT_STRING);

file_put_contents(
    $outputFile,
    json_encode($map)
);

echo "Done. File: {$outputFile} - Entries: " . count($map) . "\n";

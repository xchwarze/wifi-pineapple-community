<?php

$packages = [];
$baseUrl  = 'https://storage.googleapis.com/hak5-dl.appspot.com/packages/mk6/190702/';

foreach (file('packages_clean') as $line) {
    $clean = trim($line);
    if (!empty($clean) && strpos($clean, 'Filename: ') !== false) {
    	$filename = trim(str_replace('Filename:', '', $clean));
        $packages[] = "{$baseUrl}{$filename}";
    }
}

file_put_contents('links.txt', implode("\n", $packages));

<?php

function packagesToArray($file_path) {
    //return strtok(file_get_contents($file_path), "\n\n");
    //return explode("\n\n", file_get_contents($file_path));
    return explode('Package: ', file_get_contents($file_path));
}

function generateIndex($file_path) {
    $packages = [];

    foreach (file($file_path) as $line) {
        $clean = trim($line);
        if (!empty($clean) && strpos($clean, 'Package: ') !== false) {
            $filename = trim(str_replace('Package:', '', $clean));
            if (!in_array($filename, $packages)) {
                $packages[] = $filename;
            }
        }
    }

    return $packages;
}


// analysis
$openwrt_base_count = 0;
$openwrt_core_count = 0;
$openwrt_packages_count = 0;
$missing_packages = [];

$packages                  = generateIndex('packages');
$openwrt_base_packages     = generateIndex('openwrt_base_packages');
$openwrt_core_packages     = generateIndex('openwrt_core_packages');
$openwrt_packages_packages = generateIndex('openwrt_packages_packages');

foreach ($packages as $key) {
    $status = false;

    if (in_array($key, $openwrt_base_packages)) {
        $status = true;
        $openwrt_base_count++;
        //echo "[!] {$key} found in 'openwrt_base_packages'\n";
    }

    if (in_array($key, $openwrt_core_packages)) {
        $status = true;
        $openwrt_core_count++;
        //echo "[!] {$key} found in 'openwrt_core_packages'\n";
    }

    if (in_array($key, $openwrt_packages_packages)) {
        $status = true;
        $openwrt_packages_count++;
        //echo "[!] {$key} found in 'openwrt_packages_packages'\n";
    }

    if (!$status) {
        $missing_packages[] = $key;
        echo "[+] {$key}\n";
    }
}

$missing_packages_count = count($missing_packages);



// generate new package file
$clean_packages = [];
$mk6_packages   = packagesToArray('packages');
foreach ($mk6_packages as $key) {
    $lines = explode("\n", $key);
    //$id    = trim(str_replace('Package:', '', $lines[0]));
    $id    = trim($lines[0]);
    if (!empty($id) && in_array($id, $missing_packages)) {
        // le vuelvo a sumar el separador
        $clean_packages[] = "Package: {$key}";
    }
}



// resume
echo "
Resume
============================
openwrt_base     : {$openwrt_base_count}
openwrt_core     : {$openwrt_core_count}
openwrt_packages : {$openwrt_packages_count}
missing packages : {$missing_packages_count}


Packages
============================
generate 'packages_clean'
";

file_put_contents('packages_clean', implode('', $clean_packages));
//var_dump($missing_packages);

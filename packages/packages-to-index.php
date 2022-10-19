<?php
# by DSR! from https://github.com/xchwarze/wifi-pineapple-cloner

error_reporting(E_ALL);

if (!isset($_SERVER['argv']) && !isset($argv)) {
    echo "Please enable the register_argc_argv directive in your php.ini\n";
    exit(1);
} elseif (!isset($argv)) {
    $argv = $_SERVER['argv'];
}

if (!isset($argv[1])) {
    echo "Run with \"php packages-to-index.php [FOLDER]\"\n";
    echo "    FOLDER  -> packages folder\n";
    exit(1);
}



// config
$folder   = $argv[1];



function delTree($dir) {
    $files = array_slice(scandir($dir), 2);
    foreach ($files as $file) {
        (is_dir("{$dir}/{$file}")) ? delTree("{$dir}/{$file}") : unlink("{$dir}/{$file}");
    }

    return rmdir($dir);
}

function cleanManifiest($string, $folder, $filename) {
    $blacklist = [
        'Source',
        'SourceName',
        'LicenseFiles',
        'Maintainer',
    ];

    $manifiest = [];
    foreach (explode("\n", $string) as $value) {
        $key = (explode(':', $value))[0];
        if (!empty($key) && !in_array($key, $blacklist)) {
            $manifiest[] = $value;
        }
    }

    $path = "{$folder}/{$filename}";
    $size = filesize($path);
    $hash = hash_file('sha256', $path);
    $manifiest[] = "Filename: {$filename}";
    $manifiest[] = "Size: {$size}";
    $manifiest[] = "SHA256sum: {$hash}";
    $manifiest[] = '';

    return implode("\n", $manifiest);
}



// blah
echo "[*] Iterate folder content\n";
$packagesIndex = [];
$files = array_slice(scandir($folder), 2);
foreach ($files as $item) {
    if (strpos($item, '.ipk') === false) {
        continue;
    }

    echo "    [+] {$item}\n";
    @delTree('unpack');
    @mkdir('unpack');

    try {
        $phar = new PharData("{$folder}/{$item}");
        $phar->extractTo('unpack');
        unset($phar);

        // hack for "Cannot extract ".", internal error" fix 
        /*
        $phar = new PharData('unpack/control.tar.gz', RecursiveDirectoryIterator::SKIP_DOTS);
        $phar->convertToData(Phar::ZIP);
        unset($phar);
        $zip = new ZipArchive;
        $zip->open('unpack/control.zip');
        $zip->extractTo('unpack');
        $zip->close();
        */
        shell_exec('tar -xzf unpack/control.tar.gz -C unpack --warning=no-timestamp');
    } catch (Exception $e) {
        echo "    [!!!] Error in process\n";
        var_dump($e);
        exit();

        continue;
    }

    $packagesIndex[] = cleanManifiest(file_get_contents('unpack/control'), $folder, $item);
}

@delTree('unpack');


echo "[*] Generating file: Packages\n";
file_put_contents('Packages', implode("\n", $packagesIndex));

<?php 

error_reporting(E_ALL);

if (!isset($_SERVER['argv']) && !isset($argv)) {
    echo "Please enable the register_argc_argv directive in your php.ini\n";
    exit(1);
} elseif (!isset($argv)) {
    $argv = $_SERVER['argv'];
}

if (!isset($argv[1])) {
    echo "Run with \"php sync-repos.php [TARGET] [REMOTE_SYNC]\"\n";
    echo "    TARGET        -> Module name. For remote sync set this with 'nano' or 'tetra'\n";
    echo "    REMOTE_SYNC   -> Enable sync with original pineapple modules repo\n";
    exit(1);
}


echo "\nsync mk6 packages - by DSR!\n\n";

$target      = $argv[1];
$remoteSync  = (isset($argv[2]) && filter_var($argv[2], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));

$buildDir    = getcwd() . '/build';
$srcDir      = getcwd() . '/src';
$moduleData  = json_decode(file_get_contents("{$buildDir}/modules.json"), true);

if ($remoteSync) {
    $moduleData  = json_decode(file_get_contents("https://www.wifipineapple.com/{$target}/modules"), true);

    echo "======== Packages (" . count($moduleData) . ") ========\n";
    foreach ($moduleData as $key => $value) {
        if ($value["type"] !== 'Sys') {
            echo "    [+] {$key}\n";
            $file = file_get_contents("https://www.wifipineapple.com/{$target}/modules/{$key}");
            @unlink("{$buildDir}/{$key}.tar.gz");
            file_put_contents("{$buildDir}/{$key}.tar.gz", $file);
        }
    }
} else {
    echo "======== Update Package: {$target} ========\n";
    echo "Remember compress first!: tar czf {$target}.tar.gz {$target} && mv {$target}.tar.gz ../build\n";
    echo "Doing this on Windows can actually BREAK scripts!\n\n";

    $fileName = "{$buildDir}/{$target}.tar.gz";
    $infoData = json_decode(file_get_contents("{$srcDir}/{$target}/module.info"));
    
    $module = [
        'name' => $target,
        'title' => $infoData->title,
        'version' => $infoData->version,
        'description' => $infoData->description,
        'author' => $infoData->author,
        'size' => filesize($fileName),
        'checksum' => hash_file('sha256', $fileName),
        'num_downloads' => '0',
    ];
    if (isset($infoData->system)) {
        $module['type'] = 'System';
    } elseif (isset($infoData->cliOnly)) {
        $module['type'] = 'CLI';
    } else {
        $module['type'] = 'GUI';
    }

    echo "module info:\n";
    var_dump($module);
    $moduleData[ $target ] = $module;
}

asort($moduleData);
@unlink("{$buildDir}/modules.json");
file_put_contents("{$buildDir}/modules.json", json_encode($moduleData));

echo "\n\n";
echo "Complete!";

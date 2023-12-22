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


// scripts
function processRemoteSync($target, $buildDir) {
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

    return $moduleData;
}

function updateSinglePackage($target, $srcDir, $buildDir) {
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

    return $moduleData;
}

function processAllTargets($srcDir, $buildDir) {
    $moduleData = [];
    $files = scandir($buildDir);
    foreach ($files as $fileName) {
        if (in_array($fileName, ['.', '..', 'modules.json'])) {
            continue;
        }

        echo "    [+] {$fileName}\n";
        $target = str_replace('.tar.gz', '', $fileName);
        $fileName = "{$buildDir}/{$fileName}";
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

        $moduleData[ $target ] = $module;
    }

    return $moduleData;
}


// implement...
if ($target === 'all') {
    $moduleData = processAllTargets($srcDir, $buildDir);
} elseif ($remoteSync) {
    $moduleData = processRemoteSync($target, $buildDir);
} else {
    $moduleData = updateSinglePackage($target, $srcDir, $buildDir);
}

asort($moduleData);
@unlink("{$buildDir}/modules.json");
file_put_contents("{$buildDir}/modules.json", json_encode($moduleData));

echo "\n\n";
echo "Complete!";

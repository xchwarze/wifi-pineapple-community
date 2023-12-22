<?php namespace frieren\core;
    
    /* Code modified by Frieren Auto Refactor */
    $dbConnection = "";
    $dbPath = "";
    $report = "";
    $currentClient = $_SERVER['HTTP_HOST'];
    const DATABASE = "/etc/pineapple/get.db";
    
    if ( doesLocationFileExist("/etc/pineapple/get_database_location") )
    {
        $dbPath = trim(file_get_contents("/etc/pineapple/get_database_location")) . "get.db";
        $dbConnection = new \frieren\orm\SQLite($dbPath);
    }
    else
    {
        $dbConnection = new \frieren\orm\SQLite(self::DATABASE);
        $dbPath = trim(self::DATABASE);
    }
    
    $code = $_POST["code"];
    $mac = $_POST["mac"];
    $ip = $_POST["ip"];
    $hostname = $_POST["hostname"];
    
    //echo "Mac: [" . $mac . "] IP: [" . $ip . "] Hostname: [". $hostname . "]<br>";
    //echo "<hr>";

    $timestamp = date('Y-m-d H:i:s');
    saveDataToDatabase($dbConnection, $dbPath, $mac, $ip, $hostname, $code, $timestamp);

    header("Location: ../error.php"); 
    exit(0);
?>

<style>
body {
        background-color: black;
        color:white;
}
table {
        background-color: #222;
        border-radius: 5px;
        border: 3px #555 solid;
        margin:3px;
        padding: 2px;
}
a {color: green;}
td {border: none;}
tr:nth-child(odd) {background-color: #333; }
tr:nth-child(1) {background-color: #DDD; color:#000;}
</style>

<?php namespace frieren\core;
    /* Code modified by Frieren Auto Refactor */

    function doesLocationFileExist($path)
    {
        $filename = $path;
        $found = false;
        if (file_exists($filename)) 
        {
            $found = true;
        }
        return $found;
    }


    function saveDataToDatabase($dbConnection, $dbPath, $mac, $ip, $hostname, $code, $timestamp)
    {
        $dbConnection->execLegacy("INSERT INTO info (mac, ip, hostname, info, timestamp) VALUES('%s','%s','%s','%s', '%s');", $mac, $ip, $hostname, $code, $timestamp);
    }

?>



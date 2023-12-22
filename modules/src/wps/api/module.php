<?php namespace frieren\core;


/* Code modified by Frieren Auto Refactor */

require_once('/pineapple/modules/wps/api/iwlist_parser.php');

class wps extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshStatus', 'refreshOutput', 'handleDependencies', 'handleDependenciesStatus', 'getInterfaces', 'getMonitors', 'startMonitor', 'stopMonitor', 'scanForNetworks', 'getMACInfo', 'togglewps', 'getProcesses', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory'];
    public function __construct($request)
    {
        $this->iwlistparse = new iwlist_parser();
        parent::__construct($request);
    }

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("wps.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function checkRunning($processName)
    {
        return exec("ps -A | grep {$processName} | grep -v grep") !== '' ? 1 : 0;
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/wps/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("reaver")) {
            if (file_exists('/sd/modules/wps/scripts/dependencies.sh')) {
                $this->systemHelper->execBackground("bash /sd/modules/wps/scripts/dependencies.sh install ".$this->request['destination']);
                $this->responseHandler->setData(array('success' => true));
            } else {
                $this->systemHelper->execBackground("bash /pineapple/modules/wps/scripts/dependencies.sh install ".$this->request['destination']);
                $this->responseHandler->setData(array('success' => true));
            }
        } else {
            if (file_exists('/sd/modules/wps/scripts/dependencies.sh')) {
                $this->systemHelper->execBackground("bash /sd/modules/wps/scripts/dependencies.sh remove");
                $this->responseHandler->setData(array('success' => true));
            } else {
                $this->systemHelper->execBackground("bash /pineapple/modules/wps/scripts/dependencies.sh remove");
                $this->responseHandler->setData(array('success' => true));
            }
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/wps.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/wps.progress')) {
            if (!$this->checkDeps("iwlist")) {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;

                $status = "Start";
                $statusLabel = "success";
            } else {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;

                if ($this->systemHelper->checkRunning("reaver") || $this->systemHelper->checkRunning("bully")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $status = "Start";
            $statusLabel = "success";
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "processing" => $processing));
    }

    public function togglewps()
    {
        if (!($this->systemHelper->checkRunning("reaver") || $this->systemHelper->checkRunning("bully"))) {
            $full_cmd = $this->request['command'] . " &> /pineapple/modules/wps/log/log_".time().".log";
            $lazy = $this->request['command'];
            shell_exec("echo -e \"{$full_cmd}\" > /tmp/wps.run");
            shell_exec("echo -e \"{$lazy}\" > /tmp/lazy.read");

            $this->systemHelper->execBackground("/pineapple/modules/wps/scripts/wps.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/wps/scripts/wps.sh stop");
        }
    }

    public function refreshOutput()
    {
        if ($this->checkDeps("reaver") && $this->checkDeps("bully")) {
            if ($this->systemHelper->checkRunning("reaver") || $this->systemHelper->checkRunning("bully")) {
                $path = "/pineapple/modules/wps/log";

                $latest_ctime = 0;
                $latest_filename = '';

                $d = dir($path);
                while (false !== ($entry = $d->read())) {
                    $filepath = "{$path}/{$entry}";
                    if (is_file($filepath) && filectime($filepath) > $latest_ctime) {
                        $latest_ctime = filectime($filepath);
                        $latest_filename = $entry;
                    }
                }

                if ($latest_filename != "") {
                    $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/wps/log/".$latest_filename));

                    $cmd = "cat /pineapple/modules/wps/log/".$latest_filename;

                    exec($cmd, $output);
                    if (!empty($output)) {
                        $this->responseHandler->setData(implode("\n", $output));
                    } else {
                        $this->responseHandler->setData("Empty log...");
                    }
                }
            } else {
                $this->responseHandler->setData("wps is not running...");
            }
        } else {
            $this->responseHandler->setData("wps is not installed...");
        }
    }

    public function getInterfaces()
    {
        exec("iwconfig 2> /dev/null | grep \"wlan*\" | grep -v \"mon*\" | awk '{print $1}'", $interfaceArray);

        $this->responseHandler->setData(array("interfaces" => array_reverse($interfaceArray)));
    }

    public function getMonitors()
    {
        exec("iwconfig 2> /dev/null | grep \"mon*\" | awk '{print $1}'", $monitorArray);

        $this->responseHandler->setData(array("monitors" => $monitorArray));
    }

    public function startMonitor()
    {
        exec("airmon-ng start ".$this->request['interface']);
    }

    public function stopMonitor()
    {
        exec("airmon-ng stop ".$this->request['monitor']);
    }

    public function scanForNetworks()
    {
        if ($this->request['duration'] && $this->request['monitor'] != "") {
            exec("killall airodump-ng && rm -rf /tmp/wps-*");
            $this->systemHelper->execBackground("airodump-ng -a --wps --output-format cap -w /tmp/wps ".$this->request['monitor']." &> /dev/null");
            sleep($this->request['duration']);
            exec("killall airodump-ng");
            exec("wash -f /tmp/wps-01.cap > /tmp/wps-01.wash");
        }
        
        if($this->request['monitor'] != null){
            $tempStation = substr($this->request['monitor'], 0, -3);
            exec("airmon-ng stop ".$this->request['monitor']);
            $p = $this->iwlistparse->parseScanDev($tempStation);
            $apArray = $p[$tempStation];
            exec("airmon-ng start ".$tempStation);
        } else {
            $p = $this->iwlistparse->parseScanDev($this->request['interface']);
            $apArray = $p[$this->request['interface']];
        }

        $returnArray = array();
        foreach ($apArray as $apData) {
            $accessPoint = array();
            $accessPoint['mac'] = $apData["Address"];
            $accessPoint['ssid'] = $apData["ESSID"];
            $accessPoint['channel'] = intval($apData["Channel"]);

            $frequencyData = explode(' ', $apData["Frequency"]);
            $accessPoint['frequency'] = $frequencyData[0];

            $accessPoint['signal'] = $apData["Signal level"];
            $accessPoint['quality'] = intval($apData["Quality"]);

            if ($apData["Quality"] <= 25) {
                $accessPoint['qualityLabel'] = "danger";
            } elseif ($apData["Quality"] <= 50) {
                $accessPoint['qualityLabel'] = "warning";
            } elseif ($apData["Quality"] <= 100) {
                $accessPoint['qualityLabel'] = "success";
            }

            if (exec("cat /tmp/wps_capture.lock") == $apData["Address"]) {
                $accessPoint['captureOnSelected'] = 1;
            } else {
                $accessPoint['captureOnSelected'] = 0;
            }

            if ($this->systemHelper->checkRunning("airodump-ng")) {
                $accessPoint['captureRunning'] = 1;
            } else {
                $accessPoint['captureRunning'] = 0;
            }

            if (exec("cat /tmp/wps_deauth.lock") == $apData["Address"]) {
                $accessPoint['deauthOnSelected'] = 1;
            } else {
                $accessPoint['deauthOnSelected'] = 0;
            }

            if ($this->systemHelper->checkRunning("aireplay-ng")) {
                $accessPoint['deauthRunning'] = 1;
            } else {
                $accessPoint['deauthRunning'] = 0;
            }

            if ($apData["Encryption key"] == "on") {
                $WPA = strstr($apData["IE"], "WPA Version 1");
                $WPA2 = strstr($apData["IE"], "802.11i/WPA2 Version 1");

                $auth_type = str_replace("\n", " ", $apData["Authentication Suites (1)"]);
                $auth_type = implode(' ', array_unique(explode(' ', $auth_type)));

                $cipher = $apData["Pairwise Ciphers (2)"] ? $apData["Pairwise Ciphers (2)"] : $apData["Pairwise Ciphers (1)"];
                $cipher = str_replace("\n", " ", $cipher);
                $cipher = implode(', ', array_unique(explode(' ', $cipher)));

                if ($WPA2 != "" && $WPA != "") {
                    $accessPoint['encryption'] = 'Mixed WPA/WPA2';
                } elseif ($WPA2 != "") {
                    $accessPoint['encryption'] = 'WPA2';
                } elseif ($WPA != "") {
                    $accessPoint['encryption'] = 'WPA';
                } else {
                    $accessPoint['encryption'] = 'WEP';
                }

                $accessPoint['cipher'] = $cipher;
                $accessPoint['auth'] = $auth_type;
            } else {
                $accessPoint['encryption'] = 'None';
                $accessPoint['cipher'] = '';
                $accessPoint['auth'] = '';
            }

            if ($this->request['duration'] && $this->request['monitor'] != "") {
                $accessPoint['wps'] = trim(exec("cat /tmp/wps-01.wash | tail -n +3 | grep ".$accessPoint['mac']." | awk '{ print $4; }'"));
                $accessPoint['wpsLabel'] = "success";
                
            } 
            
            if ($accessPoint['wps'] == "") {
                $accessPoint['wps'] = "No";
                $accessPoint['wpsLabel'] = "";
            }

            array_push($returnArray, $accessPoint);
        }

        exec("rm -rf /tmp/wps-*");

        $this->responseHandler->setData($returnArray);
    }

    public function getMACInfo()
    {
        $content = file_get_contents("https://api.macvendors.com/".$this->request['mac']);
        $this->responseHandler->setData(array('title' => $this->request['mac'], "output" => $content));
    }

    public function getProcesses()
    {
        $returnArray = array();

        $process = array();
        if (file_exists("/tmp/wps.run") && ($this->systemHelper->checkRunning("reaver") || $this->systemHelper->checkRunning("bully"))) {
            $args = $this->parse_args(file_get_contents("/tmp/lazy.read"));

            $process['ssid'] = $args["e"];
            $process['mac'] = $args["b"];
            $process['channel'] = $args["c"];

            if ($args["reaver"]) {
                $process['name'] = "reaver";
            } elseif ($args["bully"]) {
                $process['name'] = "bully";
            }

            array_push($returnArray, $process);
        }

        $this->responseHandler->setData($returnArray);
    }

    public function parse_args($args)
    {
        if (is_string($args)) {
            $args = str_replace(array('=', "\'", '\"'), array('= ', '&#39;', '&#34;'), $args);
            $args = str_getcsv($args, ' ', '"');
            $tmp = array();
            foreach ($args as $arg) {
                if (!empty($arg) && $arg != "&#39;" && $arg != "=" && $arg != " ") {
                    $tmp[] = str_replace(array('= ', '&#39;', '&#34;'), array('=', "'", '"'), trim($arg));
                }
            }
            $args = $tmp;
        }

        $out = array();
        $args_size = count($args);
        for ($i = 0; $i < $args_size; $i++) {
            $value = false;
            if (substr($args[$i], 0, 2) == '--') {
                $key = rtrim(substr($args[$i], 2), '=');
                $out[$key] = true;
            } elseif (substr($args[$i], 0, 1) == '-') {
                $key = rtrim(substr($args[$i], 1), '=');

                $opt = str_split($key);
                $opt_size = count($opt);
                if ($opt_size > 1) {
                    for ($n=0; $n < $opt_size; $n++) {
                        $key = $opt[$n];
                        $out[$key] = true;
                    }
                }
            } else {
                $value = $args[$i];
            }

            if (isset($key)) {
                if (isset($out[$key])) {
                    if (is_bool($out[$key])) {
                        $out[$key] = $value;
                    } else {
                        $out[$key] = trim($out[$key].' '.$value);
                    }
                } else {
                    $out[$key] = $value;
                }
            } elseif ($value) {
                $out[$value] = true;
            }
        }
        return $out;
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/wps/log/*"));

            echo '[';
            for ($i=0;$i<count($log_list);$i++) {
                $info = explode("_", basename($log_list[$i]));
                $entryDate = gmdate('Y-m-d H-i-s', $info[1]);
                $entryName = basename($log_list[$i]);

                echo json_encode(array($entryDate, $entryName));

                if ($i!=count($log_list)-1) {
                    echo ',';
                }
            }
            echo ']';
        };
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/wps/log/".$this->request['file'])));
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/wps/log/".$this->request['file']));
        exec("cat /pineapple/modules/wps/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/wps/log/".$this->request['file']);
    }
}

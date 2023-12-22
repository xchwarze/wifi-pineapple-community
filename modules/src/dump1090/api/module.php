<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class dump1090 extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'clearOutput', 'refreshStatus', 'toggledump1090', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'deleteHistory', 'downloadHistory', 'viewHistory', 'getSettings', 'setSettings', 'refreshList'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("dump1090.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/dump1090/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDep("dump1090")) {
            $this->systemHelper->execBackground("/pineapple/modules/dump1090/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/dump1090/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/dump1090.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggledump1090()
    {
        if (!$this->systemHelper->checkRunning("dump1090")) {
            $this->systemHelper->execBackground("/pineapple/modules/dump1090/scripts/dump1090.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/dump1090/scripts/dump1090.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/dump1090.progress')) {
            if (!$this->systemHelper->checkDependency("dump1090")) {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;

                $status = "Start";
                $statusLabel = "success";
                $running = false;
            } else {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;

                if ($this->systemHelper->checkRunning("dump1090")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                    $running = true;
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                    $running = false;
                }
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $status = "Start";
            $statusLabel = "success";
            $running = false;
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "processing" => $processing, "running" => $running));
    }

    public function refreshOutput()
    {
        if ($this->systemHelper->checkDependency("dump1090")) {
            if (file_exists("/tmp/dump1090_capture.log")) {
                $output = file_get_contents("/tmp/dump1090_capture.log");
                if (!empty($output)) {
                    $this->responseHandler->setData($output);
                } else {
                    $this->responseHandler->setData("dump1090 is running...");
                }
            } else {
                $this->responseHandler->setData("dump1090 is not running...");
            }
        } else {
            $this->responseHandler->setData("dump1090 is not installed...");
        }
    }

    public function clearOutput()
    {
        exec("rm -rf /tmp/dump1090_capture.log");
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/dump1090/log/*.log"));

            echo '[';
            for ($i=0;$i<count($log_list);$i++) {
                $info = explode("_", basename($log_list[$i]));
                $entryDate = gmdate('Y-m-d H-i-s', $info[1]);
                $entryName = basename($log_list[$i], ".log");

                if (file_exists("/pineapple/modules/dump1090/log/".$entryName.".csv")) {
                    echo json_encode(array($entryDate, $entryName.".log", $entryName.".csv"));
                } else {
                    echo json_encode(array($entryDate, $entryName.".log", ''));
                }

                if ($i!=count($log_list)-1) {
                    echo ',';
                }
            }
            echo ']';
        };
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/dump1090/log/".$this->request['file'])));
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/dump1090/log/".$this->request['file']));
        exec("strings /pineapple/modules/dump1090/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        $file = basename($this->request['file'], ".log");
        exec("rm -rf /pineapple/modules/dump1090/log/".$file.".*");
    }

    public function getSettings()
    {
        $settings = array(
                    'csv' => $this->systemHelper->uciGet("dump1090.settings.csv"),
                    'gain' => $this->systemHelper->uciGet("dump1090.settings.gain"),
                    'frequency' => $this->systemHelper->uciGet("dump1090.settings.frequency"),
                    'metrics' => $this->systemHelper->uciGet("dump1090.settings.metrics"),
                    'agc' => $this->systemHelper->uciGet("dump1090.settings.agc"),
                    'aggressive' => $this->systemHelper->uciGet("dump1090.settings.aggressive")
                    );
        $this->responseHandler->setData(array('settings' => $settings));
    }

    public function setSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("dump1090.settings.gain", $settings->gain);
        $this->systemHelper->uciSet("dump1090.settings.frequency", $settings->frequency);
        if ($settings->csv) {
            $this->systemHelper->uciSet("dump1090.settings.csv", 1);
        } else {
            $this->systemHelper->uciSet("dump1090.settings.csv", 0);
        }
        if ($settings->metrics) {
            $this->systemHelper->uciSet("dump1090.settings.metrics", 1);
        } else {
            $this->systemHelper->uciSet("dump1090.settings.metrics", 0);
        }
        if ($settings->agc) {
            $this->systemHelper->uciSet("dump1090.settings.agc", 1);
        } else {
            $this->systemHelper->uciSet("dump1090.settings.agc", 0);
        }
        if ($settings->aggressive) {
            $this->systemHelper->uciSet("dump1090.settings.aggressive", 1);
        } else {
            $this->systemHelper->uciSet("dump1090.settings.aggressive", 0);
        }
    }

    public function refreshList()
    {
        $this->streamFunction = function () {
            echo file_get_contents("http://127.0.0.1:9090/data.json");
        };
    }
}

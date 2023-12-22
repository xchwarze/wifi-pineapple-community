<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
//putenv('LD_LIBRARY_PATH='.getenv('LD_LIBRARY_PATH').':/sd/lib:/sd/usr/lib');
//putenv('PATH='.getenv('PATH').':/sd/usr/bin:/sd/usr/sbin');
class nmap extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'togglenmap', 'scanStatus', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("nmap.module.installed")));
    }

//    protected function getDevice()
//    {
//        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
//    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/nmap/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        error_log("handleDependencies()");
        if (!$this->systemHelper->checkDependency("nmap")) {
            $this->systemHelper->execBackground("/pineapple/modules/nmap/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/nmap/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/nmap.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function scanStatus()
    {
        if (!$this->systemHelper->checkRunning("nmap")) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function togglenmap()
    {
        if (!$this->systemHelper->checkRunning("nmap")) {
            error_log("nmap not running");
            $full_cmd = $this->request['command'] . " -oN /tmp/nmap.scan 2>&1";
            shell_exec("echo -e \"{$full_cmd}\" > /tmp/nmap.run");

            error_log("calling run script");
            $this->systemHelper->execBackground("/pineapple/modules/nmap/scripts/nmap.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/nmap/scripts/nmap.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/nmap.progress')) {
            if (!$this->systemHelper->checkDependency("nmap")) {
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

                if ($this->systemHelper->checkRunning("nmap")) {
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

        // 2143000 is the installed size of nmap.
        $internalAvailable = (disk_free_space("/") - 64000) > 2143000;

        $this->responseHandler->setData(array("device" => $device, "internalAvailable" => $internalAvailable, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->systemHelper->checkDependency("nmap")) {
            if ($this->systemHelper->checkRunning("nmap") && file_exists("/tmp/nmap.scan")) {
                $output = file_get_contents("/tmp/nmap.scan");
                if (!empty($output)) {
                    $this->responseHandler->setData($output);
                } else {
                    $this->responseHandler->setData("Empty log...");
                }
            } else {
                $this->responseHandler->setData("nmap is not running...");
            }
        } else {
            $this->responseHandler->setData("nmap is not installed...");
        }
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/nmap/scan/*"));

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

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/nmap/scan/".$this->request['file']));
        exec("cat /pineapple/modules/nmap/scan/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty scan...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/nmap/scan/".$this->request['file']);
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/nmap/scan/".$this->request['file'])));
    }
}

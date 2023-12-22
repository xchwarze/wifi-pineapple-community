<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class tcpdump extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'clearOutput', 'refreshStatus', 'toggletcpdump', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory', 'getInterfaces'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("tcpdump.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/tcpdump/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("tcpdump")) {
            $this->systemHelper->execBackground("/pineapple/modules/tcpdump/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/tcpdump/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/tcpdump.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggletcpdump()
    {
        if (!$this->systemHelper->checkRunning("tcpdump")) {
            $full_cmd = $this->request['command'] . " -w /pineapple/modules/tcpdump/dump/dump_".time().".pcap 2> /tmp/tcpdump_capture.log";
            shell_exec("echo -e \"{$full_cmd}\" > /tmp/tcpdump.run");

            $this->systemHelper->execBackground("/pineapple/modules/tcpdump/scripts/tcpdump.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/tcpdump/scripts/tcpdump.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/tcpdump.progress')) {
            if (!$this->checkDeps("tcpdump")) {
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

                if ($this->systemHelper->checkRunning("tcpdump")) {
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

    public function refreshOutput()
    {
        if ($this->checkDeps("tcpdump")) {
            if (file_exists("/tmp/tcpdump_capture.log")) {
                $output = file_get_contents("/tmp/tcpdump_capture.log");
                if (!empty($output)) {
                    $this->responseHandler->setData($output);
                } else {
                    $this->responseHandler->setData("tcpdump is running...");
                }
            } else {
                $this->responseHandler->setData("tcpdump is not running...");
            }
        } else {
            $this->responseHandler->setData("tcpdump is not installed...");
        }
    }

    public function clearOutput()
    {
        exec("rm -rf /tmp/tcpdump_capture.log");
    }

    public function getInterfaces()
    {
        $this->responseHandler->setData(array());
        exec("cat /proc/net/dev | tail -n +3 | cut -f1 -d: | sed 's/ //g'", $interfaceArray);

        foreach ($interfaceArray as $interface) {
            array_push($this->response, $interface);
        }
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/tcpdump/dump/*.pcap"));

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
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/tcpdump/dump/".$this->request['file'])));
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/tcpdump/dump/".$this->request['file']));
        exec("strings /pineapple/modules/tcpdump/dump/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty dump...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/tcpdump/dump/".$this->request['file']);
    }
}

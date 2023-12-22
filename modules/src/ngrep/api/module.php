<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class ngrep extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'togglengrep', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory', 'getInterfaces', 'getProfiles', 'showProfile', 'deleteProfile', 'saveProfileData'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("ngrep.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/ngrep/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("ngrep")) {
            $this->systemHelper->execBackground("/pineapple/modules/ngrep/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/ngrep/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/ngrep.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function togglengrep()
    {
        if (!$this->systemHelper->checkRunning("ngrep")) {
            $full_cmd = $this->request['command'] . " -O /pineapple/modules/ngrep/log/log_".time().".pcap >> /pineapple/modules/ngrep/log/log_".time().".log";
            shell_exec("echo -e \"{$full_cmd}\" > /tmp/ngrep.run");

            $this->systemHelper->execBackground("/pineapple/modules/ngrep/scripts/ngrep.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/ngrep/scripts/ngrep.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/ngrep.progress')) {
            if (!$this->checkDeps("ngrep")) {
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

                if ($this->systemHelper->checkRunning("ngrep")) {
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
        if ($this->checkDeps("ngrep")) {
            if ($this->systemHelper->checkRunning("ngrep")) {
                $path = "/pineapple/modules/ngrep/log";

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
                    $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/ngrep/log/".$latest_filename));

                    if ($this->request['filter'] != "") {
                        $filter = $this->request['filter'];

                        $cmd = "cat /pineapple/modules/ngrep/log/".$latest_filename." | ".$filter;
                    } else {
                        $cmd = "cat /pineapple/modules/ngrep/log/".$latest_filename;
                    }

                    exec($cmd, $output);
                    if (!empty($output)) {
                        $this->responseHandler->setData(implode("\n", array_reverse($output)));
                    } else {
                        $this->responseHandler->setData("Empty log...");
                    }
                }
            } else {
                $this->responseHandler->setData("ngrep is not running...");
            }
        } else {
            $this->responseHandler->setData("ngrep is not installed...");
        }
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
            $log_list = array_reverse(glob("/pineapple/modules/ngrep/log/*.pcap"));

            echo '[';
            for ($i=0;$i<count($log_list);$i++) {
                $info = explode("_", basename($log_list[$i]));
                $entryDate = gmdate('Y-m-d H-i-s', $info[1]);
                $entryName = basename($log_list[$i], ".pcap");

                echo json_encode(array($entryDate, $entryName.".log", $entryName.".pcap"));

                if ($i!=count($log_list)-1) {
                    echo ',';
                }
            }
            echo ']';
        };
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/ngrep/log/".$this->request['file'])));
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/ngrep/log/".$this->request['file']));
        exec("strings /pineapple/modules/ngrep/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        $file = basename($this->request['file'], ".pcap");
        exec("rm -rf /pineapple/modules/ngrep/log/".$file.".*");
    }

    public function getProfiles()
    {
        $this->responseHandler->setData(array());
        $profileList = array_reverse(glob("/pineapple/modules/ngrep/profiles/*"));
        array_push($this->response, array("text" => "--", "value" => "--"));
        foreach ($profileList as $profile) {
            $profileData = file_get_contents('/pineapple/modules/ngrep/profiles/'.basename($profile));
            array_push($this->response, array("text" => basename($profile), "value" => $profileData));
        }
    }

    public function showProfile()
    {
        $profileData = file_get_contents('/pineapple/modules/ngrep/profiles/'.$this->request['profile']);
        $this->responseHandler->setData(array("profileData" => $profileData));
    }

    public function deleteProfile()
    {
        exec("rm -rf /pineapple/modules/ngrep/profiles/".$this->request['profile']);
    }

    public function saveProfileData()
    {
        $filename = "/pineapple/modules/ngrep/profiles/".$this->request['profile'];
        file_put_contents($filename, $this->request['profileData']);
    }
}

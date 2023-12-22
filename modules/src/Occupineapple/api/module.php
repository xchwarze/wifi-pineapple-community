<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Occupineapple extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'togglemdk3', 'handleDependencies', 'handleDependenciesStatus', 'getInterfaces', 'getLists', 'showList', 'deleteList', 'saveListData', 'getSettings', 'setSettings', 'saveAutostartSettings', 'togglemdk3OnBoot'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("occupineapple.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/Occupineapple/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("mdk3")) {
            $this->systemHelper->execBackground("/pineapple/modules/Occupineapple/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/Occupineapple/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function togglemdk3OnBoot()
    {
        if (exec("cat /etc/rc.local | grep Occupineapple/scripts/autostart_occupineapple.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/Occupineapple/scripts/autostart_occupineapple.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/Occupineapple\/scripts\/autostart_occupineapple.sh/d' /etc/rc.local");
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/Occupineapple.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function togglemdk3()
    {
        if (!$this->systemHelper->checkRunning("mdk3")) {
            $this->systemHelper->uciSet("occupineapple.run.interface", $this->request['interface']);
            $this->systemHelper->uciSet("occupineapple.run.list", $this->request['list']);

            $this->systemHelper->execBackground("/pineapple/modules/Occupineapple/scripts/occupineapple.sh start");
        } else {
            $this->systemHelper->uciSet("occupineapple.run.interface", '');
            $this->systemHelper->uciSet("occupineapple.run.list", '');

            $this->systemHelper->execBackground("/pineapple/modules/Occupineapple/scripts/occupineapple.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/Occupineapple.progress')) {
            if (!$this->checkDeps("mdk3")) {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;

                $status = "Start";
                $statusLabel = "success";

                $bootLabelON = "default";
                $bootLabelOFF = "danger";
            } else {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;

                if ($this->systemHelper->checkRunning("mdk3")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }

                if (exec("cat /etc/rc.local | grep Occupineapple/scripts/autostart_occupineapple.sh") == "") {
                    $bootLabelON = "default";
                    $bootLabelOFF = "danger";
                } else {
                    $bootLabelON = "success";
                    $bootLabelOFF = "default";
                }
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $status = "Start";
            $statusLabel = "success";

            $bootLabelON = "default";
            $bootLabelOFF = "danger";
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "bootLabelON" => $bootLabelON, "bootLabelOFF" => $bootLabelOFF, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->checkDeps("mdk3")) {
            if ($this->systemHelper->checkRunning("mdk3")) {
                exec("cat /tmp/occupineapple.log", $output);
                if (!empty($output)) {
                    $this->responseHandler->setData(implode("\n", array_reverse($output)));
                } else {
                    $this->responseHandler->setData("Empty log...");
                }
            } else {
                $this->responseHandler->setData("Occupineapple is not running...");
            }
        } else {
            $this->responseHandler->setData("mdk3 is not installed...");
        }
    }

    public function getInterfaces()
    {
        exec("iwconfig 2> /dev/null | grep \"wlan*\" | awk '{print $1}'", $interfaceArray);

        $this->responseHandler->setData(array("interfaces" => $interfaceArray, "selected" => $this->systemHelper->uciGet("occupineapple.run.interface")));
    }

    public function getLists()
    {
        $listArray = array();
        $listList = array_reverse(glob("/pineapple/modules/Occupineapple/lists/*"));
        array_push($listArray, "--");
        foreach ($listList as $list) {
            array_push($listArray, basename($list));
        }
        $this->responseHandler->setData(array("lists" => $listArray, "selected" => $this->systemHelper->uciGet("occupineapple.run.list")));
    }

    public function showList()
    {
        $listData = file_get_contents('/pineapple/modules/Occupineapple/lists/'.$this->request['list']);
        $this->responseHandler->setData(array("listData" => $listData));
    }

    public function deleteList()
    {
        exec("rm -rf /pineapple/modules/Occupineapple/lists/".$this->request['list']);
    }

    public function saveListData()
    {
        $filename = "/pineapple/modules/Occupineapple/lists/".$this->request['list'];
        file_put_contents($filename, $this->request['listData']);
    }

    public function getSettings()
    {
        $settings = array(
                    'speed' => $this->systemHelper->uciGet("occupineapple.settings.speed"),
                    'channel' => $this->systemHelper->uciGet("occupineapple.settings.channel"),
                    'adHoc' => $this->systemHelper->uciGet("occupineapple.settings.adHoc"),
                    'wepBit' => $this->systemHelper->uciGet("occupineapple.settings.wepBit"),
                    'wpaTKIP' => $this->systemHelper->uciGet("occupineapple.settings.wpaTKIP"),
                    'wpaAES' => $this->systemHelper->uciGet("occupineapple.settings.wpaAES"),
                    'validMAC' => $this->systemHelper->uciGet("occupineapple.settings.validMAC")
                    );
        $this->responseHandler->setData(array('settings' => $settings));
    }

    public function setSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("occupineapple.settings.speed", $settings->speed);
        $this->systemHelper->uciSet("occupineapple.settings.channel", $settings->channel);
        if ($settings->adHoc) {
            $this->systemHelper->uciSet("occupineapple.settings.adHoc", 1);
        } else {
            $this->systemHelper->uciSet("occupineapple.settings.adHoc", 0);
        }
        if ($settings->wepBit) {
            $this->systemHelper->uciSet("occupineapple.settings.wepBit", 1);
        } else {
            $this->systemHelper->uciSet("occupineapple.settings.wepBit", 0);
        }
        if ($settings->wpaTKIP) {
            $this->systemHelper->uciSet("occupineapple.settings.wpaTKIP", 1);
        } else {
            $this->systemHelper->uciSet("occupineapple.settings.wpaTKIP", 0);
        }
        if ($settings->wpaAES) {
            $this->systemHelper->uciSet("occupineapple.settings.wpaAES", 1);
        } else {
            $this->systemHelper->uciSet("occupineapple.settings.wpaAES", 0);
        }
        if ($settings->validMAC) {
            $this->systemHelper->uciSet("occupineapple.settings.validMAC", 1);
        } else {
            $this->systemHelper->uciSet("occupineapple.settings.validMAC", 0);
        }
    }

    public function saveAutostartSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("occupineapple.autostart.interface", $settings->interface);
        $this->systemHelper->uciSet("occupineapple.autostart.list", $settings->list);
    }
}

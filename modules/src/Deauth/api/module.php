<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
//putenv('LD_LIBRARY_PATH='.getenv('LD_LIBRARY_PATH').':/sd/lib:/sd/usr/lib');
//putenv('PATH='.getenv('PATH').':/sd/usr/bin:/sd/usr/sbin');
class Deauth extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'togglemdk3', 'handleDependencies', 'handleDependenciesStatus', 'getInterfaces', 'scanForNetworks', 'getSettings', 'setSettings', 'saveAutostartSettings', 'togglemdk3OnBoot', 'getListsData', 'saveListsData'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("deauth.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/Deauth/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDep("mdk3")) {
            $this->systemHelper->execBackground("/pineapple/modules/Deauth/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/Deauth/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function togglemdk3OnBoot()
    {
        if (exec("cat /etc/rc.local | grep Deauth/scripts/autostart_deauth.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/Deauth/scripts/autostart_deauth.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/Deauth\/scripts\/autostart_deauth.sh/d' /etc/rc.local");
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/Deauth.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function togglemdk3()
    {
        if (!$this->systemHelper->checkRunning("mdk3")) {
            $this->systemHelper->uciSet("deauth.run.interface", $this->request['interface']);

            $this->systemHelper->execBackground("/pineapple/modules/Deauth/scripts/deauth.sh start");
        } else {
            $this->systemHelper->uciSet("deauth.run.interface", '');

            $this->systemHelper->execBackground("/pineapple/modules/Deauth/scripts/deauth.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/Deauth.progress')) {
            if (!$this->checkDep("mdk3")) {
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

                if (exec("cat /etc/rc.local | grep Deauth/scripts/autostart_deauth.sh") == "") {
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
        if ($this->systemHelper->checkDependency("mdk3")) {
            if ($this->systemHelper->checkRunning("mdk3")) {
                exec("cat /tmp/deauth.log", $output);
                if (!empty($output)) {
                    $this->responseHandler->setData(implode("\n", array_reverse($output)));
                } else {
                    $this->responseHandler->setData("Empty log...");
                }
            } else {
                $this->responseHandler->setData("Deauth is not running...");
            }
        } else {
            $this->responseHandler->setData("mdk3 is not installed...");
        }
    }

    public function getInterfaces()
    {
        exec("iwconfig 2> /dev/null | grep \"wlan*\" | awk '{print $1}'", $interfaceArray);

        $this->responseHandler->setData(array("interfaces" => $interfaceArray, "selected" => $this->systemHelper->uciGet("deauth.run.interface")));
    }

    public function scanForNetworks()
    {
        $interface = escapeshellarg($this->request['interface']);
        if (substr($interface, -4, -1) === "mon") {
            if ($interface == "'wlan1mon'") {
                exec("killall pineap");
                exec("killall pinejector");
            }
            exec("airmon-ng stop {$interface}");
            $interface = substr($interface, 0, -4) . "'";
            exec("iw dev {$interface} scan &> /dev/null");
        }
        exec("iwinfo {$interface} scan", $apScan);

        $apArray = preg_split("/^Cell/m", implode("\n", $apScan));
        $returnArray = array();
        foreach ($apArray as $apData) {
            $apData = explode("\n", $apData);
            $accessPoint = array();
            $accessPoint['mac'] = substr($apData[0], -17);
            $accessPoint['ssid'] = substr(trim($apData[1]), 8, -1);
            if (mb_detect_encoding($accessPoint['ssid'], "auto") === false) {
                continue;
            }

            $accessPoint['channel'] = intval(substr(trim($apData[2]), -2));

            $signalString = explode("  ", trim($apData[3]));
            $accessPoint['signal'] = substr($signalString[0], 8);
            $accessPoint['quality'] = substr($signalString[1], 9);

            $security = substr(trim($apData[4]), 12);
            if ($security === "none") {
                $accessPoint['security'] = "Open";
            } else {
                $accessPoint['security'] = $security;
            }

            if ($accessPoint['mac'] && trim($apData[1]) !== "ESSID: unknown") {
                array_push($returnArray, $accessPoint);
            }
        }
        $this->responseHandler->setData($returnArray);
    }

    public function getSettings()
    {
        $settings = array(
                    'speed' => $this->systemHelper->uciGet("deauth.settings.speed"),
                    'channels' => $this->systemHelper->uciGet("deauth.settings.channels"),
                    'mode' => $this->systemHelper->uciGet("deauth.settings.mode")
                    );
        $this->responseHandler->setData(array('settings' => $settings));
    }

    public function setSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("deauth.settings.speed", $settings->speed);
        $this->systemHelper->uciSet("deauth.settings.channels", $settings->channels);
        $this->systemHelper->uciSet("deauth.settings.mode", $settings->mode);
    }

    public function saveAutostartSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("deauth.autostart.interface", $settings->interface);
    }

    public function getListsData()
    {
        $blacklistData = file_get_contents('/pineapple/modules/Deauth/lists/blacklist.lst');
        $whitelistData = file_get_contents('/pineapple/modules/Deauth/lists/whitelist.lst');
        $this->responseHandler->setData(array("blacklistData" => $blacklistData, "whitelistData" => $whitelistData ));
    }

    public function saveListsData()
    {
        $filename = '/pineapple/modules/Deauth/lists/blacklist.lst';
        file_put_contents($filename, $this->request['blacklistData']);

        $filename = '/pineapple/modules/Deauth/lists/whitelist.lst';
        file_put_contents($filename, $this->request['whitelistData']);
    }
}

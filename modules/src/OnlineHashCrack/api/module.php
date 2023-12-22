<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class OnlineHashCrack extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'clearOutput', 'refreshStatus', 'handleDependencies', 'handleDependenciesStatus', 'submitWPAOnline', 'submitWPAOnlineStatus', 'getSettings', 'setSettings', 'getCapFiles'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("onlinehashcrack.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/OnlineHashCrack/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if(!$this->checkDeps("curl"))
        {
            $this->systemHelper->execBackground("/pineapple/modules/OnlineHashCrack/scripts/dependencies.sh install " . $this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        }
        else
        {
            $this->systemHelper->execBackground("/pineapple/modules/OnlineHashCrack/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/OnlineHashCrack.progress'))
        {
            $this->responseHandler->setData(array('success' => true));
        }
        else
        {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/OnlineHashCrack.progress'))
        {
            if(!$this->checkDeps("curl"))
            {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;
            }
            else
            {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;
            }
        }
        else
        {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if (file_exists("/tmp/onlinehashcrack.log"))
        {
            $output = file_get_contents("/tmp/onlinehashcrack.log");
            if(!empty($output))
                $this->responseHandler->setData($output);
            else
                $this->responseHandler->setData(" ");
        }
        else
        {
             $this->responseHandler->setData(" ");
        }
    }

    public function clearOutput()
    {
        exec("rm -rf /tmp/onlinehashcrack.log");
    }

    public function submitWPAOnlineStatus()
    {
        if (!file_exists('/tmp/OnlineHashCrack.progress'))
        {
            $this->responseHandler->setData(array('success' => true));
        }
        else
        {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function submitWPAOnline()
    {
        $this->systemHelper->execBackground("/pineapple/modules/OnlineHashCrack/scripts/submit_wpa.sh ".$this->request['file']);
        $this->responseHandler->setData(array('success' => true));
    }

    public function getSettings()
    {
        $settings = array(
                    'email' => $this->systemHelper->uciGet("onlinehashcrack.settings.email")
                    );
        $this->responseHandler->setData(array('settings' => $settings));
    }

    public function setSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("onlinehashcrack.settings.email", $settings->email);
    }

    public function getCapFiles()
    {
        exec("find -L /pineapple/modules/ -type f -name \"*.**cap\" -o -name \"*.**pcap\" -o -name \"*.**pcapng\" -o -name \"*.**hccapx\" 2>&1", $filesArray);
        $this->responseHandler->setData(array("files" => $filesArray));
    }
}
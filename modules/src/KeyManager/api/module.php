<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class KeyManager extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'clearOutput', 'refreshStatus', 'handleDependencies', 'handleDependenciesStatus', 'handleKey', 'handleKeyStatus', 'saveKnownHostsData', 'getKnownHostsData', 'addToKnownHosts', 'addToKnownHostsStatus', 'copyToRemoteHost', 'copyToRemoteHostStatus', 'getSettings'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("keymanager.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/KeyManager/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleKey()
    {
        if (!file_exists("/root/.ssh/id_rsa")) {
            $this->systemHelper->execBackground("/pineapple/modules/KeyManager/scripts/generate_key.sh");
            $this->responseHandler->setData(array('success' => true));
        } else {
            exec("rm -rf /root/.ssh/id_rsa*");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleKeyStatus()
    {
        if (!file_exists('/tmp/KeyManager_key.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function handleDependencies()
    {
        if (!$this->checkDep("ssh-keyscan")) {
            $this->systemHelper->execBackground("/pineapple/modules/KeyManager/scripts/dependencies.sh install " . $this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/KeyManager/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/KeyManager.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/KeyManager.progress')) {
            if (!$this->checkDep("ssh-keyscan")) {
                $installed = false;
                $install = "Not installed";
                $installLabel = "danger";
                $processing = false;
            } else {
                $installed = true;
                $install = "Installed";
                $installLabel = "success";
                $processing = false;
            }

            if (!file_exists('/tmp/KeyManager_key.progress')) {
                if (!file_exists("/root/.ssh/id_rsa")) {
                    $key = "Not generated";
                    $keyLabel = "danger";
                    $generated = false;
                    $generating = false;
                } else {
                    $key = "Generated";
                    $keyLabel = "success";
                    $generated = true;
                    $generating = false;
                }
            } else {
                $key = "Generating...";
                $keyLabel = "warning";
                $generated = false;
                $generating = true;
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $key = "Not generated";
            $keyLabel = "danger";
            $generating = false;
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "installed" => $installed, "key" => $key, "keyLabel" => $keyLabel, "generating" => $generating, "generated" => $generated, "install" => $install, "installLabel" => $installLabel, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if (file_exists("/tmp/keymanager.log")) {
            $output = file_get_contents("/tmp/keymanager.log");
            if (!empty($output))
                $this->responseHandler->setData($output);
            else
                $this->responseHandler->setData(" ");
        } else {
            $this->responseHandler->setData(" ");
        }
    }

    public function clearOutput()
    {
        exec("rm -rf /tmp/keymanager.log");
    }

    public function saveKnownHostsData()
    {
        $filename = '/root/.ssh/known_hosts';
        file_put_contents($filename, $this->request['knownHostsData']);
    }

    public function getKnownHostsData()
    {
        $knownHostsData = file_get_contents('/root/.ssh/known_hosts');
        $this->responseHandler->setData(array("knownHostsData" => $knownHostsData));
    }

    public function addToKnownHostsStatus()
    {
        if (!file_exists('/tmp/KeyManager.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function addToKnownHosts()
    {
        $this->systemHelper->uciSet("keymanager.settings.host", $this->request['host']);
        $this->systemHelper->uciSet("keymanager.settings.port", $this->request['port']);

        $this->systemHelper->execBackground("/pineapple/modules/KeyManager/scripts/add_host.sh");
        $this->responseHandler->setData(array('success' => true));
    }

    public function copyToRemoteHostStatus()
    {
        if (!file_exists('/tmp/KeyManager.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function copyToRemoteHost()
    {
        $this->systemHelper->uciSet("keymanager.settings.host", $this->request['host']);
        $this->systemHelper->uciSet("keymanager.settings.port", $this->request['port']);
        $this->systemHelper->uciSet("keymanager.settings.user", $this->request['user']);

        $this->systemHelper->execBackground("/pineapple/modules/KeyManager/scripts/copy_key.sh " . $this->request['password']);
        $this->responseHandler->setData(array('success' => true));
    }

    public function getSettings()
    {
        $settings = array(
            'host' => $this->systemHelper->uciGet("keymanager.settings.host"),
            'port' => $this->systemHelper->uciGet("keymanager.settings.port"),
            'user' => $this->systemHelper->uciGet("keymanager.settings.user")
        );
        $this->responseHandler->setData($settings);
    }

}

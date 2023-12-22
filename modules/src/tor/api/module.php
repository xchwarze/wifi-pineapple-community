<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Tor extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshStatus', 'handleDependencies', 'handleDependenciesStatus', 'toggletor', 'refreshHiddenServices', 'addHiddenService', 'removeHiddenService', 'addServiceForward', 'removeServiceForward'];
    private $progressFile = '/tmp/tor.progress';
    private $moduleConfigFile = '/etc/config/tor/config';
    private $dependenciesScriptFile = '/pineapple/modules/tor/scripts/dependencies.sh';

    // Error Constants
    const INVALID_NAME = 'Invalid name';
    const INVALID_PORT = 'Invalid port';
    const INVALID_DESTINATION = 'Invalid destination';

    // Display Constants
    const DANGER = 'danger';
    const WARNING = 'warning';
    const SUCCESS = 'success';

    public function success($value)
    {
        $this->responseHandler->setData(array('success' => $value));
    }

    public function error($message)
    {
        $this->responseHandler->setData(array('error' => $message));
    }

    public function isValidName($name)
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }

    public function isValidPort($port)
    {
        return preg_match('/^[0-9]+$/', $port) === 1;
    }

    public function isValidRedirectTo($redirect_to)
    {
        return preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+$/', $redirect_to) === 1;
    }

    protected function checkDependency($dependencyName)
    {
        return (exec("which {$dependencyName}") != '' &&  !file_exists($this->progressFile));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/tor/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    protected function checkRunning($processName)
    {
        return (exec("pgrep '{$processName}$'") != '');
    }

    public function handleDependencies()
    {
        $destination = "";
        if (isset($this->request['destination'])) {
            $destination = $this->request['destination'];
            if ($destination != "internal" && $destination != "sd") {
                $this->error(self::INVALID_DESTINATION);
                return;
            }
        }

        if (!$this->systemHelper->checkDependency("tor")) {
            $this->systemHelper->execBackground($this->dependenciesScriptFile . " install " . $destination);
        } else {
            $this->systemHelper->execBackground($this->dependenciesScriptFile . " remove");
        }
        $this->success(true);
    }

    public function handleDependenciesStatus()
    {
        if (file_exists($this->progressFile)) {
            $this->success(false);
        } else {
            $this->success(true);
        }
    }

    public function toggletor()
    {
        if ($this->systemHelper->checkRunning("tor")) {
            exec("/etc/init.d/tor stop");
        } else {
            exec("/etc/init.d/tor start");
        }
    }

    public function refreshStatus()
    {

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();
        $installed = false;
        $install = "Not Installed";
        $processing = false;

        if (file_exists($this->progressFile)) {
            // TOR Is installing, please wait.
            $install = "Installing...";
            $installLabel = self::WARNING;
            $processing = true;

            $status = "Not running";
            $statusLabel = self::DANGER;
        } elseif (!$this->systemHelper->checkDependency("tor")) {
            // TOR is not installed, please install.
            $installLabel = self::DANGER;

            $status = "Start";
            $statusLabel = self::DANGER;
        } else {
            // TOR is installed, please configure.
            $installed = true;
            $install = "Installed";
            $installLabel = self::SUCCESS;

            if ($this->systemHelper->checkRunning("tor")) {
                $status = "Started";
                $statusLabel = self::SUCCESS;
            } else {
                $status = "Stopped";
                $statusLabel = self::DANGER;
            }
        }

        $this->responseHandler->setData(array("device" => $device,
                                "sdAvailable" => $sdAvailable,
                                "status" => $status,
                                "statusLabel" => $statusLabel,
                                "installed" => $installed,
                                "install" => $install,
                                "installLabel" => $installLabel,
                                "processing" => $processing));
    }

    public function generateConfig()
    {
        $output = file_get_contents("/etc/config/tor/torrc");
        $output .= "\n";
        $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        foreach ($hiddenServices as $hiddenService) {
            $output .= "HiddenServiceDir /etc/config/tor/services/{$hiddenService->name}\n";
            $forwards = $hiddenService->forwards;
            foreach ($forwards as $forward) {
                $output .= "HiddenServicePort {$forward->port} {$forward->redirect_to}\n";
            }
        }
        file_put_contents("/etc/tor/torrc", $output);
    }

    public function reloadTor()
    {
        $this->generateConfig();
        //Sending SIGHUP to tor process cause config reload.
        exec("pkill -sighup tor$");
    }

    public function refreshHiddenServices()
    {
        $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        foreach ($hiddenServices as $hiddenService) {
            if (file_exists("/etc/config/tor/services/{$hiddenService->name}/hostname")) {
                $hostname = file_get_contents("/etc/config/tor/services/{$hiddenService->name}/hostname");
                $hiddenService->hostname = trim($hostname);
            }
        }
        $this->responseHandler->setData(array("hiddenServices" => $hiddenServices));
    }

    public function addHiddenService()
    {
        $name = $this->request['name'];
        if (!$this->isValidName($name)) {
            $this->error(self::INVALID_NAME);
            return;
        }

        $hiddenService = array("name" => $name, "forwards" => array() );
        $hiddenServices = array();
        if (file_exists($this->moduleConfigFile)) {
            $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        }
        array_push($hiddenServices, $hiddenService);
        file_put_contents($this->moduleConfigFile, @json_encode($hiddenServices, JSON_PRETTY_PRINT));
        $this->reloadTor();
    }

    public function removeHiddenService()
    {
        $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        foreach ($hiddenServices as $key => $hiddenService) {
            if ($hiddenService->name == $this->request['name']) {
                unset($hiddenServices[$key]);
            }
        }
        file_put_contents($this->moduleConfigFile, @json_encode($hiddenServices, JSON_PRETTY_PRINT));
        $this->reloadTor();
    }

    public function addServiceForward()
    {
        $name = $this->request['name'];
        $port = $this->request['port'];
        $redirect_to = $this->request['redirect_to'];

        if (!$this->isValidName($name)) {
            $this->error(self::INVALID_NAME);
            return;
        }
        if (!$this->isValidPort($port)) {
            $this->error(self::INVALID_PORT);
            return;
        }
        if (!$this->isValidRedirectTo($redirect_to)) {
            $this->error(self::INVALID_DESTINATION);
            return;
        }

        $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        foreach ($hiddenServices as $key => $hiddenService) {
            if ($hiddenService->name == $name) {
                $forwards = $hiddenService->forwards;
                $forward = array("port" => $port, "redirect_to" => $redirect_to);
                array_push($forwards, $forward);
                $hiddenServices[$key]->forwards = $forwards;
            }
        }
        file_put_contents($this->moduleConfigFile, @json_encode($hiddenServices, JSON_PRETTY_PRINT));

        $this->reloadTor();
    }

    public function removeServiceForward()
    {
        $name = $this->request['name'];
        $port = $this->request['port'];
        $redirect_to = $this->request['redirect_to'];

        $hiddenServices = @json_decode(file_get_contents($this->moduleConfigFile));
        foreach ($hiddenServices as $hiddenService) {
            if ($hiddenService->name == $name) {
                $forwards = $hiddenService->forwards;
                foreach ($forwards as $key => $forward) {
                    if ($forward->port == $port && $forward->redirect_to == $redirect_to) {
                        unset($forwards[$key]);
                    }
                }
                $hiddenService->forwards = $forwards;
            }
        }
        file_put_contents($this->moduleConfigFile, @json_encode($hiddenServices, JSON_PRETTY_PRINT));

        $this->reloadTor();
    }
}

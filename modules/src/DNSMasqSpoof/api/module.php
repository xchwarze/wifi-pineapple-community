<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class DNSMasqSpoof extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'toggleDNSMasqSpoof', 'handleDependencies', 'handleDependenciesStatus', 'toggleDNSMasqSpoofOnBoot', 'saveLandingPageData', 'getLandingPageData', 'saveHostsData', 'getHostsData'];

    protected function checkDep($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("dnsmasqspoof.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function checkRunning($processName)
    {
        return exec("ps w | grep {$processName} | grep -v grep") !== '' && exec("grep addn-hosts /etc/dnsmasq.conf") !== '' ? 1 : 0;
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/DNSMasqSpoof/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDep("dnsmasq")) {
            $this->systemHelper->execBackground("/pineapple/modules/DNSMasqSpoof/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/DNSMasqSpoof/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/DNSMasqSpoof.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggleDNSMasqSpoofOnBoot()
    {
        if (exec("cat /etc/rc.local | grep DNSMasqSpoof/scripts/autostart_dnsmasqspoof.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/DNSMasqSpoof/scripts/autostart_dnsmasqspoof.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/DNSMasqSpoof\/scripts\/autostart_dnsmasqspoof.sh/d' /etc/rc.local");
        }
    }

    public function toggleDNSMasqSpoof()
    {
        if (!$this->systemHelper->checkRunning("dnsmasq")) {
            $this->systemHelper->execBackground("/pineapple/modules/DNSMasqSpoof/scripts/dnsmasqspoof.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/DNSMasqSpoof/scripts/dnsmasqspoof.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/DNSMasqSpoof.progress')) {
            if (!$this->systemHelper->checkDependency("dnsmasq")) {
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

                if ($this->systemHelper->checkRunning("dnsmasq")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }

                if (exec("cat /etc/rc.local | grep DNSMasqSpoof/scripts/autostart_dnsmasqspoof.sh") == "") {
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

            $status = "Not running";
            $statusLabel = "danger";

            $bootLabelON = "default";
            $bootLabelOFF = "danger";
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "bootLabelON" => $bootLabelON, "bootLabelOFF" => $bootLabelOFF, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->systemHelper->checkDependency("dnsmasq")) {
            if ($this->systemHelper->checkRunning("dnsmasq")) {
                $this->responseHandler->setData("DNSMasq Spoof is running...");
            } else {
                $this->responseHandler->setData("DNSMasq Spoof is not running...");
            }
        } else {
            $this->responseHandler->setData("DNSMasq Spoof is not installed...");
        }
    }

    public function saveLandingPageData()
    {
        $filename = '/www/index.php';
        file_put_contents($filename, $this->request['configurationData']);
    }

    public function getLandingPageData()
    {
        $configurationData = file_get_contents('/www/index.php');
        $this->responseHandler->setData(array("configurationData" => $configurationData));
    }

    public function saveHostsData()
    {
        $filename = '/pineapple/modules/DNSMasqSpoof/hosts/dnsmasq.hosts';
        file_put_contents($filename, $this->request['configurationData']);
    }

    public function getHostsData()
    {
        $configurationData = file_get_contents('/pineapple/modules/DNSMasqSpoof/hosts/dnsmasq.hosts');
        $this->responseHandler->setData(array("configurationData" => $configurationData));
    }
}

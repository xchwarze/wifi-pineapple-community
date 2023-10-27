<?php

namespace pineapple;

class Terminal extends Module
{
    const TTYD_PATH = "/usr/bin/ttyd";
    const TTYD_SD_PATH = "/sd/usr/bin/ttyd";
    const LOG_PATH = "/pineapple/modules/Terminal/module.log";

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
            case "startTerminal":
                $this->startTerminal();
                break;
            case "stopTerminal":
                $this->stopTerminal();
                break;
            case "getStatus":
                $this->getStatus();
                break;
            case "getLog":
                $this->getLog();
                break;
        }
    }

    protected function addLog($massage)
    {
        $entry = "[" . date("Y-m-d H:i:s") . "] {$massage}\n";
        file_put_contents(self::LOG_PATH, $entry, FILE_APPEND);
    }

    protected function getTerminalPath()
    {
        if ($this->isSDAvailable() && file_exists(self::TTYD_SD_PATH)) {
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
       if ($this->checkDependency("ttyd")) {
            if (!$this->uciGet("ttyd.@ttyd[0].port")) {
                $this->uciSet("ttyd.@ttyd[0].port", "1477");
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
            $this->response = ["success" => true];
            return true;    
        }

        $action = $this->checkDependencyInstalled() ? "remove" : "install";
        $this->execBackground("/pineapple/modules/Terminal/scripts/dependencies.sh {$action}");
        $this->response = ["success" => true];
    }

    protected function getDependenciesInstallStatus()
    {
        $this->response = ["success" => !file_exists("/tmp/terminal.progress")];
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
        $status = \helper\checkRunning($terminal);
        if (!$status) {
            $command = "{$terminal} -p 1477 -i br-lan /bin/login";
            $this->execBackground($command);

            sleep(1);
            $status = \helper\checkRunning($terminal);
            if (!$status) {
                $this->addLog("Terminal could not be run! command: {$command}");
            }
        }

        $this->response = ["success" => $status];
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
        $status = \helper\checkRunning($this->getTerminalPath());
        if ($status) {
            $this->addLog("Terminal could not be stop! command: /usr/bin/pkill ttyd");
        }
        $this->response = ["success" => !$status];
    }

    protected function getStatus()
    {
        $this->response = ["status" => \helper\checkRunning($this->getTerminalPath())];
    }

    protected function getLog()
    {
        if (!file_exists(self::LOG_PATH)) {
            touch(self::LOG_PATH);
        }

        $this->response = ["moduleLog" => file_get_contents(self::LOG_PATH)];
    }
}

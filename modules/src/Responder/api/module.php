<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Responder extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'toggleResponder', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory', 'toggleResponderOnBoot', 'getInterfaces', 'saveAutostartSettings', 'getSettings', 'setSettings'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("responder.module.installed")));
    }

    protected function checkRunning($processName)
    {
        return exec("ps w | grep {$processName} | grep -v grep") !== '' ? 1 : 0;
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/Responder/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("python")) {
            $this->systemHelper->execBackground("/pineapple/modules/Responder/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/Responder/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/Responder.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggleResponderOnBoot()
    {
        if (exec("cat /etc/rc.local | grep Responder/scripts/autostart_responder.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/Responder/scripts/autostart_responder.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/Responder\/scripts\/autostart_responder.sh/d' /etc/rc.local");
        }
    }

    public function toggleResponder()
    {
        if (!$this->systemHelper->checkRunning("Responder.py")) {
            $this->systemHelper->uciSet("responder.run.interface", $this->request['interface']);

            $this->systemHelper->execBackground("/pineapple/modules/Responder/scripts/responder.sh start");
        } else {
            $this->systemHelper->uciSet("responder.run.interface", '');

            $this->systemHelper->execBackground("/pineapple/modules/Responder/scripts/responder.sh stop");
        }
    }

    public function getInterfaces()
    {
        exec("cat /proc/net/dev | tail -n +3 | cut -f1 -d: | sed 's/ //g'", $interfaceArray);

        $this->responseHandler->setData(array("interfaces" => $interfaceArray, "selected" => $this->systemHelper->uciGet("responder.run.interface")));
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/Responder.progress')) {
            if (!$this->checkDeps("python")) {
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

                if ($this->systemHelper->checkRunning("Responder.py")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }

                if (exec("cat /etc/rc.local | grep Responder/scripts/autostart_responder.sh") == "") {
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
            $verbose = false;

            $bootLabelON = "default";
            $bootLabelOFF = "danger";
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed, "install" => $install, "installLabel" => $installLabel, "bootLabelON" => $bootLabelON, "bootLabelOFF" => $bootLabelOFF, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->checkDeps("python")) {
            if ($this->systemHelper->checkRunning("Responder.py")) {
                if (file_exists("/pineapple/modules/Responder/dep/responder/logs/Responder-Session.log")) {
                    if ($this->request['filter'] != "") {
                        $filter = $this->request['filter'];

                        $cmd = "strings /pineapple/modules/Responder/dep/responder/logs/Responder-Session.log | ".$filter;
                    } else {
                        $cmd = "strings /pineapple/modules/Responder/dep/responder/logs/Responder-Session.log";
                    }

                    exec($cmd, $output);
                    if (!empty($output)) {
                        $this->responseHandler->setData(implode("\n", array_reverse($output)));
                    } else {
                        $this->responseHandler->setData("Empty log...");
                    }
                } else {
                    $this->responseHandler->setData("Empty log...");
                }
            } else {
                $this->responseHandler->setData("Responder is not running...");
            }
        } else {
            $this->responseHandler->setData("Responder is not installed...");
        }
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/Responder/log/*"));

            echo '[';
            for ($i=0;$i<count($log_list);$i++) {
                $info = explode("_", basename($log_list[$i]));
                $entryDate = gmdate('Y-m-d H-i-s', $info[1]);
                $entryName = basename($log_list[$i]);

                echo json_encode(array($entryDate, $entryName));

                if ($i!=count($log_list)-1) {
                    echo ',';
                }
            }
            echo ']';
        };
    }

    public function viewHistory()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/Responder/log/".$this->request['file']));
        exec("strings /pineapple/modules/Responder/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/Responder/log/".$this->request['file']);
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/Responder/log/".$this->request['file'])));
    }

    public function saveAutostartSettings()
    {
        $settings = $this->request['settings'];
        $this->systemHelper->uciSet("responder.autostart.interface", $settings->interface);
    }

    public function getSettings()
    {
        $settings = array(
                    'SQL' => $this->systemHelper->uciGet("responder.settings.SQL"),
                    'SMB' => $this->systemHelper->uciGet("responder.settings.SMB"),
                    'Kerberos' => $this->systemHelper->uciGet("responder.settings.Kerberos"),
                    'FTP' => $this->systemHelper->uciGet("responder.settings.FTP"),
                    'POP' => $this->systemHelper->uciGet("responder.settings.POP"),
                    'SMTP' => $this->systemHelper->uciGet("responder.settings.SMTP"),
                    'IMAP' => $this->systemHelper->uciGet("responder.settings.IMAP"),
                    'HTTP' => $this->systemHelper->uciGet("responder.settings.HTTP"),
                    'HTTPS' => $this->systemHelper->uciGet("responder.settings.HTTPS"),
                    'DNS' => $this->systemHelper->uciGet("responder.settings.DNS"),
                    'LDAP' => $this->systemHelper->uciGet("responder.settings.LDAP"),
                    'basic' => $this->systemHelper->uciGet("responder.settings.basic"),
                    'wredir' => $this->systemHelper->uciGet("responder.settings.wredir"),
                    'NBTNS' => $this->systemHelper->uciGet("responder.settings.NBTNS"),
                    'fingerprint' => $this->systemHelper->uciGet("responder.settings.fingerprint"),
                    'wpad' => $this->systemHelper->uciGet("responder.settings.wpad"),
                    'forceWpadAuth' => $this->systemHelper->uciGet("responder.settings.forceWpadAuth"),
                    'proxyAuth' => $this->systemHelper->uciGet("responder.settings.proxyAuth"),
                    'forceLmDowngrade' => $this->systemHelper->uciGet("responder.settings.forceLmDowngrade"),
                    'verbose' => $this->systemHelper->uciGet("responder.settings.verbose"),
                    'analyse' => $this->systemHelper->uciGet("responder.settings.analyse")
                    );
        $this->responseHandler->setData(array('settings' => $settings));
    }

    public function setSettings()
    {
        $settings = $this->request['settings'];
        if ($settings->SQL) {
            $this->updateSetting("SQL", 1);
        } else {
            $this->updateSetting("SQL", 0);
        }
        if ($settings->SMB) {
            $this->updateSetting("SMB", 1);
        } else {
            $this->updateSetting("SMB", 0);
        }
        if ($settings->Kerberos) {
            $this->updateSetting("Kerberos", 1);
        } else {
            $this->updateSetting("Kerberos", 0);
        }
        if ($settings->FTP) {
            $this->updateSetting("FTP", 1);
        } else {
            $this->updateSetting("FTP", 0);
        }
        if ($settings->POP) {
            $this->updateSetting("POP", 1);
        } else {
            $this->updateSetting("POP", 0);
        }
        if ($settings->SMTP) {
            $this->updateSetting("SMTP", 1);
        } else {
            $this->updateSetting("SMTP", 0);
        }
        if ($settings->IMAP) {
            $this->updateSetting("IMAP", 1);
        } else {
            $this->updateSetting("IMAP", 0);
        }
        if ($settings->HTTP) {
            $this->updateSetting("HTTP", 1);
        } else {
            $this->updateSetting("HTTP", 0);
        }
        if ($settings->HTTPS) {
            $this->updateSetting("HTTPS", 1);
        } else {
            $this->updateSetting("HTTPS", 0);
        }
        if ($settings->DNS) {
            $this->updateSetting("DNS", 1);
        } else {
            $this->updateSetting("DNS", 0);
        }
        if ($settings->LDAP) {
            $this->updateSetting("LDAP", 1);
        } else {
            $this->updateSetting("LDAP", 0);
        }

        if ($settings->basic) {
            $this->systemHelper->uciSet("responder.settings.basic", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.basic", 0);
        }
        if ($settings->wredir) {
            $this->systemHelper->uciSet("responder.settings.wredir", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.wredir", 0);
        }
        if ($settings->NBTNS) {
            $this->systemHelper->uciSet("responder.settings.NBTNS", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.NBTNS", 0);
        }
        if ($settings->fingerprint) {
            $this->systemHelper->uciSet("responder.settings.fingerprint", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.fingerprint", 0);
        }
        if ($settings->wpad) {
            $this->systemHelper->uciSet("responder.settings.wpad", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.wpad", 0);
        }
        if ($settings->forceWpadAuth) {
            $this->systemHelper->uciSet("responder.settings.forceWpadAuth", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.forceWpadAuth", 0);
        }
        if ($settings->proxyAuth) {
            $this->systemHelper->uciSet("responder.settings.proxyAuth", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.proxyAuth", 0);
        }
        if ($settings->forceLmDowngrade) {
            $this->systemHelper->uciSet("responder.settings.forceLmDowngrade", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.forceLmDowngrade", 0);
        }
        if ($settings->verbose) {
            $this->systemHelper->uciSet("responder.settings.verbose", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.verbose", 0);
        }
        if ($settings->analyse) {
            $this->systemHelper->uciSet("responder.settings.analyse", 1);
        } else {
            $this->systemHelper->uciSet("responder.settings.analyse", 0);
        }
    }

    public function updateSetting($setting, $value)
    {
        if ($value) {
            $this->systemHelper->uciSet("responder.settings.".$setting, 1);
            exec("/bin/sed -i 's/^".$setting." .*/".$setting." = On/g' /pineapple/modules/Responder/dep/responder/Responder.conf");
        } else {
            $this->systemHelper->uciSet("responder.settings.".$setting, 0);
            exec("/bin/sed -i 's/^".$setting." .*/".$setting." = Off/g' /pineapple/modules/Responder/dep/responder/Responder.conf");
        }
    }
}

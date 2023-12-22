<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class SSLsplit extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshOutput', 'refreshStatus', 'toggleSSLsplit', 'handleDependencies', 'handleDependenciesStatus', 'refreshHistory', 'viewHistory', 'deleteHistory', 'downloadHistory', 'toggleSSLsplitOnBoot', 'handleCertificate', 'handleCertificateStatus', 'saveConfigurationData', 'getConfigurationData'];

    protected function checkDeps($dependencyName)
    {
        return ($this->systemHelper->checkDependency($dependencyName) && ($this->systemHelper->uciGet("sslsplit.module.installed")));
    }

    protected function getDevice()
    {
        return trim(exec("cat /proc/cpuinfo | grep machine | awk -F: '{print $2}'"));
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/SSLsplit/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function handleCertificate()
    {
        if (!file_exists("/pineapple/modules/SSLsplit/cert/certificate.crt")) {
            $this->systemHelper->execBackground("/pineapple/modules/SSLsplit/scripts/generate_certificate.sh");
            $this->responseHandler->setData(array('success' => true));
        } else {
            exec("rm -rf /pineapple/modules/SSLsplit/cert/certificate.*");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleCertificateStatus()
    {
        if (!file_exists('/tmp/SSLsplit_certificate.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function handleDependencies()
    {
        if (!$this->checkDeps("sslsplit")) {
            $this->systemHelper->execBackground("/pineapple/modules/SSLsplit/scripts/dependencies.sh install ".$this->request['destination']);
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/SSLsplit/scripts/dependencies.sh remove");
            $this->responseHandler->setData(array('success' => true));
        }
    }

    public function handleDependenciesStatus()
    {
        if (!file_exists('/tmp/SSLsplit.progress')) {
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->responseHandler->setData(array('success' => false));
        }
    }

    public function toggleSSLsplitOnBoot()
    {
        if (exec("cat /etc/rc.local | grep SSLsplit/scripts/autostart_sslsplit.sh") == "") {
            exec("sed -i '/exit 0/d' /etc/rc.local");
            exec("echo /pineapple/modules/SSLsplit/scripts/autostart_sslsplit.sh >> /etc/rc.local");
            exec("echo exit 0 >> /etc/rc.local");
        } else {
            exec("sed -i '/SSLsplit\/scripts\/autostart_sslsplit.sh/d' /etc/rc.local");
        }
    }

    public function toggleSSLsplit()
    {
        if (!$this->systemHelper->checkRunning("sslsplit")) {
            $this->systemHelper->execBackground("/pineapple/modules/SSLsplit/scripts/sslsplit.sh start");
        } else {
            $this->systemHelper->execBackground("/pineapple/modules/SSLsplit/scripts/sslsplit.sh stop");
        }
    }

    public function refreshStatus()
    {
        if (!file_exists('/tmp/SSLsplit.progress')) {
            if (!$this->checkDeps("sslsplit")) {
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

                if ($this->systemHelper->checkRunning("sslsplit")) {
                    $status = "Stop";
                    $statusLabel = "danger";
                } else {
                    $status = "Start";
                    $statusLabel = "success";
                }

                if (exec("cat /etc/rc.local | grep SSLsplit/scripts/autostart_sslsplit.sh") == "") {
                    $bootLabelON = "default";
                    $bootLabelOFF = "danger";
                } else {
                    $bootLabelON = "success";
                    $bootLabelOFF = "default";
                }
            }

            if (!file_exists('/tmp/SSLsplit_certificate.progress')) {
                if (!file_exists("/pineapple/modules/SSLsplit/cert/certificate.crt")) {
                    $certificate = "Not generated";
                    $certificateLabel = "danger";
                    $generated = false;
                    $generating = false;
                } else {
                    $certificate = "Generated";
                    $certificateLabel = "success";
                    $generated = true;
                    $generating = false;
                }
            } else {
                $certificate = "Generating...";
                $certificateLabel = "warning";
                $generated = false;
                $generating = true;
            }
        } else {
            $installed = false;
            $install = "Installing...";
            $installLabel = "warning";
            $processing = true;

            $status = "Start";
            $statusLabel = "success";

            $bootLabelON = "default";
            $bootLabelOFF = "danger";

            $certificate = "Not generated";
            $certificateLabel = "danger";
            $generating = false;
        }

        $device = $this->systemHelper->getDevice();
        $sdAvailable = $this->systemHelper->isSDAvailable();

        $this->responseHandler->setData(array("device" => $device, "sdAvailable" => $sdAvailable, "status" => $status, "statusLabel" => $statusLabel, "installed" => $installed,
                                "certificate" => $certificate, "certificateLabel" => $certificateLabel, "generating" => $generating, "generated" => $generated,
                                "install" => $install, "installLabel" => $installLabel,
                                "bootLabelON" => $bootLabelON, "bootLabelOFF" => $bootLabelOFF, "processing" => $processing));
    }

    public function refreshOutput()
    {
        if ($this->checkDeps("sslsplit")) {
            if ($this->systemHelper->checkRunning("sslsplit")) {
                if (file_exists("/pineapple/modules/SSLsplit/connections.log")) {
                    if ($this->request['filter'] != "") {
                        $filter = $this->request['filter'];

                        $cmd = "cat /pineapple/modules/SSLsplit/connections.log"." | ".$filter;
                    } else {
                        $cmd = "cat /pineapple/modules/SSLsplit/connections.log";
                    }

                    exec($cmd, $output);
                    if (!empty($output)) {
                        $this->responseHandler->setData(implode("\n", array_reverse($output)));
                    } else {
                        $this->responseHandler->setData("Empty connections log...");
                    }
                } else {
                    $this->responseHandler->setData("No connections log...");
                }
            } else {
                $this->responseHandler->setData("SSLsplit is not running...");
            }
        } else {
            $this->responseHandler->setData("SSLsplit is not installed...");
        }
    }

    public function refreshHistory()
    {
        $this->streamFunction = function () {
            $log_list = array_reverse(glob("/pineapple/modules/SSLsplit/log/*"));

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
        $log_date = gmdate("F d Y H:i:s", filemtime("/pineapple/modules/SSLsplit/log/".$this->request['file']));
        exec("cat /pineapple/modules/SSLsplit/log/".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date));
        } else {
            $this->responseHandler->setData(array("output" => "Empty log...", "date" => $log_date));
        }
    }

    public function deleteHistory()
    {
        exec("rm -rf /pineapple/modules/SSLsplit/log/".$this->request['file']);
    }

    public function downloadHistory()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/pineapple/modules/SSLsplit/log/".$this->request['file'])));
    }

    public function saveConfigurationData()
    {
        $filename = '/pineapple/modules/SSLsplit/rules/iptables';
        file_put_contents($filename, $this->request['configurationData']);
    }

    public function getConfigurationData()
    {
        $configurationData = file_get_contents('/pineapple/modules/SSLsplit/rules/iptables');
        $this->responseHandler->setData(array("configurationData" => $configurationData));
    }
}

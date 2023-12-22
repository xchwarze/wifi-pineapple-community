<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

class HackRF extends Controller
{
    protected $endpointRoutes = ['hackrfInfo', 'hackrfInstall', 'hackrfUninstall', 'hackrfChecker', 'hackrfTransfer', 'hackrfStop', 'hackrfLog'];

    public function hackrfInfo()
    {
        exec('hackrf_info', $message);
        $message = implode("\n", $message);

        if ($message == "No HackRF boards found.") {
            $this->responseHandler->setData(array("foundBoard" => false));
        } else if ($this->systemHelper->checkDependency('hackrf_info') == false) {
            $this->responseHandler->setData(array("foundBoard" => false));
        } else {
            $this->responseHandler->setData(array("foundBoard" => true,
                "availableHackRFs" => $message));
        }
    }

    public function hackrfChecker()
    {
        if ($this->systemHelper->checkDependency('hackrf_info')) {
            $this->responseHandler->setData(array("installed" => true));
        } else {
            $this->responseHandler->setData(array("installed" => false));
        }
    }

    public function hackrfInstall()
    {
        if ($this->systemHelper->getDevice() == 'tetra') {
            $this->systemHelper->execBackground('opkg update && opkg install hackrf-mini');    
        } else {
            $this->systemHelper->execBackground('opkg update && opkg install hackrf-mini --dest sd');
        }
        exec('echo "Welcome to HackRF!" > /tmp/hackrf_log');

        $this->responseHandler->setData(array("installing" => true));
    }

    public function hackrfUninstall()
    {
        exec('opkg remove hackrf-mini');
        unlink('/tmp/hackrf_log');
        $this->responseHandler->setData(array("success" => true));
    }

    public function hackrfTransfer()
    {
        $mode         = $this->request['mode'];
        $sampleRate   = $this->request['sampleRate'];
        $centerFreq   = $this->request['centerFreq'];
        $filename     = $this->request['filename'];
        $amp          = $this->request['amp'];
        $antPower     = $this->request['antpower'];
        $txRepeat     = $this->request['txRepeat'];
        $txIfCheckbox = $this->request['txIfCheckbox'];
        $txIfGain     = $this->request['txIfGain'];
        $rxIfCheckbox = $this->request['rxIfCheckbox'];
        $rxBbCheckbox = $this->request['rxBbCheckbox'];
        $rxIfGain     = $this->request['rxIfGain'];
        $rxBbGain     = $this->request['rxBbGain'];

        if(!$sampleRate) {
            $this->responseHandler->setData(array("success" => false, "error" => "samplerate"));
        } else if(!$centerFreq) {
            $this->responseHandler->setData(array("success" => false, "error" => "centerfreq"));
        } else if(!$filename) {
            $this->responseHandler->setData(array("success" => false, "error" => "filename"));
        } else {
            if ($mode == "rx") {
                $mode = "-r";
            } else {
                $mode = "-t";
            }

            if(strpos(strtolower($sampleRate), 'k') == true) {
                $sampleRate = str_replace('k', '', $sampleRate);
                $sampleRate = (int)$sampleRate * 1000;
            } else if(strpos(strtolower($sampleRate), 'm') == true) {
                $sampleRate = str_replace('m', '', $sampleRate);
                $sampleRate = (int)$sampleRate * 1000000;
            }

            if(strpos(strtolower($centerFreq), 'khz') == true) {
                $centerFreq = str_replace('khz', '', $centerFreq);
                $centerFreq = floatval($centerFreq);
                $centerFreq = $centerFreq * 1000;
            } else if(strpos(strtolower($centerFreq), 'mhz') == true) {
                $centerFreq = str_replace('mhz', '', $centerFreq);
                $centerFreq = floatval($centerFreq);
                $centerFreq = $centerFreq * 1000000;
            } else if(strpos(strtolower($centerFreq), 'ghz') == true) {
                $centerFreq = str_replace('ghz', '', $centerFreq);
                $centerFreq = floatval($centerFreq);
                $centerFreq = $centerFreq * 1000000000;
            }

            $command = "hackrf_transfer $mode $filename -f $centerFreq -s $sampleRate";

            if ($amp) {
                $command = $command . " -a 1";
            }
            if ($antPower) {
                $command = $command . " -p 1";
            }
            if ($txRepeat) {
                $command = $command . " -R";
            }

            if ($txIfCheckbox == true && $mode == '-t' && empty($txIfGain) == false) {
                $command = $command . " -x $txIfGain";
            }

            if ($rxIfCheckbox == true && $mode == '-r' && empty($rxIfGain) == false) {
                $command = $command . " -l $rxIfGain";
            }

            if ($rxBbCheckbox == true && $mode == '-r' && empty($rxBbGain) == false) {
                $command = $command . " -g $rxBbGain";
            }

            unlink("/tmp/hackrf_log");
            $this->systemHelper->execBackground("$command > /tmp/hackrf_log 2>&1");
            $this->responseHandler->setData(array("success" => true));
        }
    }

    public function hackrfStop()
    {
        exec("killall hackrf_transfer");

        $this->responseHandler->setData(array("success" => true));
    }

    public function hackrfLog()
    {
        $log  = '/tmp/hackrf_log';
        if(file_exists($log)) {
            $log = file_get_contents($log);
            $this->responseHandler->setData(array("success" => true, "log" => $log));
        } else {
            $this->responseHandler->setData(array("success" => true, "log" => "Welcome to HackRF!"));
        }
    }
}

// 01110100 01101000 01100001 01101110 01101011 00100000 01111001 01101111 01110101 00100000 01110011 01100101 01100010 01100001 
// 01110011 01110100 01101001 01100001 01101110 00101110 0001010 01101001 00100000 01101100 01101111 01110110 01100101 00100000
// 01111001 01101111 01110101 00100000 00111100 00110011


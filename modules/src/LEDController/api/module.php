<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class LEDController extends Controller
{
    protected $endpointRoutes = ['getDeviceType', 'resetLEDs', 'getTetraYellow', 'setTetraYellow', 'getTetraBlue', 'setTetraBlue', 'getTetraRed', 'setTetraRed', 'getNanoBlue', 'setNanoBlue'];

    public function restartLEDs()
    {
        exec('/etc/init.d/led restart');
    }

    public function getDeviceType()
    {
        $device = $this->systemHelper->getDevice();

        if ($device == 'tetra') {
            $this->responseHandler->setData('tetra');
        } else {
            $this->responseHandler->setData('nano');
        }
    }

    public function getTetraYellow()
    {
        $trigger = $this->systemHelper->uciGet('system.led_eth0.trigger');

        if ($trigger == 'none') {
            $default = $this->systemHelper->uciGet('system.led_eth0.default');
            if ($default == 0) {
                $this->responseHandler->setData(array('enabled' => false, 'trigger' => $trigger));
            } elseif ($default == 1) {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
            }
        } elseif ($trigger == 'netdev') {
            $mode = $this->systemHelper->uciGet('system.led_eth0.mode');
            $interface = $this->systemHelper->uciGet('system.led_eth0.dev');
            if ($mode == 'link tx rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx rx', 'interface' => $interface));
            } elseif ($mode == 'link tx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx', 'interface' => $interface));
            } elseif ($mode == 'link rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link rx', 'interface' => $interface));
            }
        } elseif ($trigger == 'timer') {
            $delayOn = $this->systemHelper->uciGet('system.led_eth0.delayon');
            $delayOff = $this->systemHelper->uciGet('system.led_eth0.delayoff');
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                    'delayOn' => $delayOn, 'delayOff' => $delayOff));
        } else {
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
        }
    }

    public function setTetraYellow()
    {
        $enabled = $this->request['enabled'];
        $trigger = $this->request['trigger'];
        $mode = $this->request['mode'];
        $delayOn = $this->request['delayOn'];
        $delayOff = $this->request['delayOff'];
        $interface = $this->request['interface'];

        if ($enabled == true) {
            if ($trigger == 'none') {
                $this->systemHelper->uciSet('system.led_eth0.trigger', 'none');
                $this->systemHelper->uciSet('system.led_eth0.default', '1');
                $this->restartLEDs();
            } elseif ($trigger == 'netdev') {
                $this->systemHelper->uciSet('system.led_eth0.trigger', 'netdev');
                $this->systemHelper->uciSet('system.led_eth0.mode', "$mode");
                $this->systemHelper->uciSet('system.led_eth0.dev', "$interface");
                $this->restartLEDs();
            } elseif ($trigger == 'timer') {
                $this->systemHelper->uciSet('system.led_eth0.trigger', 'timer');
                $this->systemHelper->uciSet('system.led_eth0.delayon', "$delayOn");
                $this->systemHelper->uciSet('system.led_eth0.delayoff', "$delayOff");
                $this->restartLEDs();
            }
        } elseif ($enabled == false) {
            $this->systemHelper->uciSet('system.led_eth0.trigger', 'none');
            $this->systemHelper->uciSet('system.led_eth0.default', '0');
            $this->restartLEDs();
        }

        $this->responseHandler->setData(array('enabled' => $enabled, 'trigger' => $trigger,
        'mode' => $mode, 'delayOn' => $delayOn,
        'delayOff' => $delayOff, 'interface' => $interface, 'success' => true));
    }

    public function getTetraBlue()
    {
        $trigger = $this->systemHelper->uciGet('system.led_wlan0.trigger');

        if ($trigger == 'none') {
            $default = $this->systemHelper->uciGet('system.led_wlan0.default');
            if ($default == 0) {
                $this->responseHandler->setData(array('enabled' => false, 'trigger' => $trigger));
            } elseif ($default == 1) {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
            }
        } elseif ($trigger == 'netdev') {
            $mode = $this->systemHelper->uciGet('system.led_wlan0.mode');
            $interface = $this->systemHelper->uciGet('system.led_wlan0.dev');
            if ($mode == 'link tx rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx rx', 'interface' => $interface));
            } elseif ($mode == 'link tx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx', 'interface' => $interface));
            } elseif ($mode == 'link rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link rx', 'interface' => $interface));
            }
        } elseif ($trigger == 'timer') {
            $delayOn = $this->systemHelper->uciGet('system.led_wlan0.delayon');
            $delayOff = $this->systemHelper->uciGet('system.led_wlan0.delayoff');
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                    'delayOn' => $delayOn, 'delayOff' => $delayOff));
        } else {
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
        }
    }

    public function setTetraBlue()
    {
        $enabled = $this->request['enabled'];
        $trigger = $this->request['trigger'];
        $mode = $this->request['mode'];
        $delayOn = $this->request['delayOn'];
        $delayOff = $this->request['delayOff'];
        $interface = $this->request['interface'];

        if ($enabled == true) {
            if ($trigger == 'none') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'none');
                $this->systemHelper->uciSet('system.led_wlan0.default', '1');
                $this->restartLEDs();
            } elseif ($trigger == 'netdev') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'netdev');
                $this->systemHelper->uciSet('system.led_wlan0.mode', "$mode");
                $this->systemHelper->uciSet('system.led_wlan0.dev', "$interface");
                $this->restartLEDs();
            } elseif ($trigger == 'timer') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'timer');
                $this->systemHelper->uciSet('system.led_wlan0.delayon', "$delayOn");
                $this->systemHelper->uciSet('system.led_wlan0.delayoff', "$delayOff");
                $this->restartLEDs();
            }
        } elseif ($enabled == false) {
            $this->systemHelper->uciSet('system.led_wlan0.trigger', 'none');
            $this->systemHelper->uciSet('system.led_wlan0.default', '0');
            $this->restartLEDs();
        }

        $this->responseHandler->setData(array('enabled' => $enabled, 'trigger' => $trigger,
        'mode' => $mode, 'delayOn' => $delayOn,
        'delayOff' => $delayOff, 'interface' => $interface, 'success' => true));
    }

    public function getTetraRed()
    {
        $trigger = $this->systemHelper->uciGet('system.led_wlan1mon.trigger');

        if ($trigger == 'none') {
            $default = $this->systemHelper->uciGet('system.led_wlan1mon.default');
            if ($default == 0) {
                $this->responseHandler->setData(array('enabled' => false, 'trigger' => $trigger));
            } elseif ($default == 1) {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
            }
        } elseif ($trigger == 'netdev') {
            $mode = $this->systemHelper->uciGet('system.led_wlan1mon.mode');
            $interface = $this->systemHelper->uciGet('system.led_wlan1mon.dev');
            if ($mode == 'link tx rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx rx', 'interface' => $interface));
            } elseif ($mode == 'link tx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx', 'interface' => $interface));
            } elseif ($mode == 'link rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link rx', 'interface' => $interface));
            }
        } elseif ($trigger == 'timer') {
            $delayOn = $this->systemHelper->uciGet('system.led_wlan1mon.delayon');
            $delayOff = $this->systemHelper->uciGet('system.led_wlan1mon.delayoff');
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                    'delayOn' => $delayOn, 'delayOff' => $delayOff));
        } else {
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
        }
    }

    public function setTetraRed()
    {
        $enabled = $this->request['enabled'];
        $trigger = $this->request['trigger'];
        $mode = $this->request['mode'];
        $delayOn = $this->request['delayOn'];
        $delayOff = $this->request['delayOff'];
        $interface = $this->request['interface'];

        if ($enabled == true) {
            if ($trigger == 'none') {
                $this->systemHelper->uciSet('system.led_wlan1mon.trigger', 'none');
                $this->systemHelper->uciSet('system.led_wlan1mon.default', '1');
                $this->restartLEDs();
            } elseif ($trigger == 'netdev') {
                $this->systemHelper->uciSet('system.led_wlan1mon.trigger', 'netdev');
                $this->systemHelper->uciSet('system.led_wlan1mon.mode', "$mode");
                $this->systemHelper->uciSet('system.led_wlan1mon.dev', "$interface");
                $this->restartLEDs();
            } elseif ($trigger == 'timer') {
                $this->systemHelper->uciSet('system.led_wlan1mon.trigger', 'timer');
                $this->systemHelper->uciSet('system.led_wlan1mon.delayon', "$delayOn");
                $this->systemHelper->uciSet('system.led_wlan1mon.delayoff', "$delayOff");
                $this->restartLEDs();
            }
        } elseif ($enabled == false) {
            $this->systemHelper->uciSet('system.led_wlan1mon.trigger', 'none');
            $this->systemHelper->uciSet('system.led_wlan1mon.default', '0');
            $this->restartLEDs();
        }

        $this->responseHandler->setData(array('enabled' => $enabled, 'trigger' => $trigger,
        'mode' => $mode, 'delayOn' => $delayOn,
        'delayOff' => $delayOff, 'interface' => $interface, 'success' => true));
    }

    public function getNanoBlue()
    {
        $trigger = $this->systemHelper->uciGet('system.led_wlan0.trigger');

        if ($trigger == 'none') {
            $default = $this->systemHelper->uciGet('system.led_wlan0.default');
            if ($default == 0) {
                $this->responseHandler->setData(array('enabled' => false, 'trigger' => $trigger));
            } elseif ($default == 1) {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
            }
        } elseif ($trigger == 'netdev') {
            $mode = $this->systemHelper->uciGet('system.led_wlan0.mode');
            $interface = $this->systemHelper->uciGet('system.led_wlan0.dev');
            if ($mode == 'link tx rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx rx', 'interface' => $interface));
            } elseif ($mode == 'link tx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link tx', 'interface' => $interface));
            } elseif ($mode == 'link rx') {
                $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                        'mode' => 'link rx', 'interface' => $interface));
            }
        } elseif ($trigger == 'timer') {
            $delayOn = $this->systemHelper->uciGet('system.led_wlan0.delayon');
            $delayOff = $this->systemHelper->uciGet('system.led_wlan0.delayoff');
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger,
                                    'delayOn' => $delayOn, 'delayOff' => $delayOff));
        } else {
            $this->responseHandler->setData(array('enabled' => true, 'trigger' => $trigger));
        }
    }

    public function setNanoBlue()
    {
        $enabled = $this->request['enabled'];
        $trigger = $this->request['trigger'];
        $mode = $this->request['mode'];
        $delayOn = $this->request['delayOn'];
        $delayOff = $this->request['delayOff'];
        $interface = $this->request['interface'];

        if ($enabled == true) {
            if ($trigger == 'none') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'none');
                $this->systemHelper->uciSet('system.led_wlan0.default', '1');
                $this->restartLEDs();
            } elseif ($trigger == 'netdev') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'netdev');
                $this->systemHelper->uciSet('system.led_wlan0.mode', "$mode");
                $this->systemHelper->uciSet('system.led_wlan0.dev', "$interface");
                $this->restartLEDs();
            } elseif ($trigger == 'timer') {
                $this->systemHelper->uciSet('system.led_wlan0.trigger', 'timer');
                $this->systemHelper->uciSet('system.led_wlan0.delayon', "$delayOn");
                $this->systemHelper->uciSet('system.led_wlan0.delayoff', "$delayOff");
                $this->restartLEDs();
            }
        } elseif ($enabled == false) {
            $this->systemHelper->uciSet('system.led_wlan0.trigger', 'none');
            $this->systemHelper->uciSet('system.led_wlan0.default', '0');
            $this->restartLEDs();
        }

        $this->responseHandler->setData(array('enabled' => $enabled, 'trigger' => $trigger,
        'mode' => $mode, 'delayOn' => $delayOn,
        'delayOff' => $delayOff, 'interface' => $interface, 'success' => true));
    }

    public function resetLEDs()
    {
        $device = $this->systemHelper->getDevice();

        if ($device == 'tetra') {
            $this->systemHelper->uciSet('system.led_wlan0.trigger', 'netdev');
            $this->systemHelper->uciSet('system.led_wlan0.mode', 'link tx rx');
            $this->systemHelper->uciSet('system.led_wlan0.dev', 'wlan0');
            $this->systemHelper->uciSet('system.led_wlan1mon.trigger', 'netdev');
            $this->systemHelper->uciSet('system.led_wlan1mon.mode', 'link tx rx');
            $this->systemHelper->uciSet('system.led_wlan1mon.dev', 'wlan1mon');
            $this->systemHelper->uciSet('system.led_eth0.trigger', 'netdev');
            $this->systemHelper->uciSet('system.led_eth0.mode', 'link tx rx');
            $this->systemHelper->uciSet('system.led_eth0.dev', 'eth0');
            $this->restartLEDs();
            $this->responseHandler->setData(array('success' => true));
        } else {
            $this->systemHelper->uciSet('system.led_wlan0.trigger', 'netdev');
            $this->systemHelper->uciSet('system.led_wlan0.mode', 'link tx rx');
            $this->systemHelper->uciSet('system.led_wlan0.dev', 'wlan0');
            $this->restartLEDs();
            $this->responseHandler->setData(array('success' => true));
        }
    }
}

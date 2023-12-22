<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
/***
Modem Manager <api/module.php>
Written by Foxtrot <foxtrot@realloc.me>
Distributed under the MIT Licence <https://opensource.org/licenses/MIT>
***/
class ModemManager extends Controller
{
    protected $endpointRoutes = ['checkDepends', 'installDepends', 'removeDepends', 'getUSB', 'getTTYs', 'checkConnection', 'setConnection', 'unsetConnection', 'loadConfiguration', 'saveConfiguration', 'resetConfiguration'];

    public function checkDepends()
    {
        /* Check dependencies */
        if(empty($this->systemHelper->checkDependency('comgt'))) {
            $this->responseHandler->setData(array('installed' => false));
        } else {
            $this->responseHandler->setData(array('installed' => true));
        }
    }

    public function installDepends()
    {
        /* Install dependencies */
        $this->systemHelper->execBackground('opkg update && opkg install comgt wwan uqmi');    
        $this->responseHandler->setData(array("installing" => true));

    }

    public function removeDepends()
    {
        /* Remove dependencies */
        $this->systemHelper->execBackground('opkg remove comgt wwan uqmi');
        $this->responseHandler->setData(array('success' => true));
    }

    public function getUSB()
    {
        /* Execute 'lsusb' and capture its output in the $lsusb variable.
           Then split the output by its newlines. */
        exec('lsusb', $lsusb);
        $lsusb = implode("\n", $lsusb);

        $this->responseHandler->setData(array('lsusb' => $lsusb));
    }

    public function getTTYs()
    {
        exec('ls /dev/ttyUSB* && ls /dev/cdc-wdm* && ls /dev/ttyACM*', $TTYs);

        if (empty($TTYs)) {
            $this->responseHandler->setData(array('success' => false,
                                    'availableTTYs' => false));
        } else {
            $TTYs = implode("\n", $TTYs);
            $this->responseHandler->setData(array('success' => true,
                                    'availableTTYs' => $TTYs));
        }
    }

    public function checkConnection()
    {
        /* Check the connection of the wan2 interface. */
        if(file_exists('/sys/class/net/3g-wan2/carrier')) {
            $this->responseHandler->setData(array('status' => 'connected'));
            exec('iptables -t nat -A POSTROUTING -s 172.16.42.0/24 -o 3g-wan2 -j MASQUERADE');
            exec('iptables -A FORWARD -s 172.16.42.0/24 -o 3g-wan2 -j ACCEPT');
            exec('iptables -A FORWARD -d 172.16.42.0/24 -m state --state ESTABLISHED,RELATED -i 3g-wan2 -j ACCEPT');
        } else {
            $this->responseHandler->setData(array('status' => 'disconnected'));
        }

    }

    public function setConnection()
    {
        /* Set the connection of the wan2 interface. */
        $this->systemHelper->execBackground('ifup wan2');
        $this->responseHandler->setData(array('status' => 'connecting'));
    }

    public function unsetConnection()
    {
        /* Unset the connection of the wan2 interface. */
        $this->systemHelper->execBackground('ifdown wan2');
        $this->responseHandler->setData(array('status' => 'disconnected'));
    }

    public function loadConfiguration()
    {
        /* For easier code reading, assign a variable for each bit of information we require from the system.
           Read more about UCI at https://wiki.openwrt.org/doc/uci.
           For more information about the WiFi Pineapple API, visit https://wiki.wifipineapple.com. */
        $interface     = $this->systemHelper->uciGet('network.wan2.ifname');
        $protocol      = $this->systemHelper->uciGet('network.wan2.proto');
        $service       = $this->systemHelper->uciGet('network.wan2.service');
        $vendorid      = $this->systemHelper->uciGet('network.wan2.currentVID');
        $productid     = $this->systemHelper->uciGet('network.wan2.currentPID');
        $device        = $this->systemHelper->uciGet('network.wan2.device');
        $apn           = $this->systemHelper->uciGet('network.wan2.apn');
        $username      = $this->systemHelper->uciGet('network.wan2.username');
        $password      = $this->systemHelper->uciGet('network.wan2.password');
        $dns           = $this->systemHelper->uciGet('network.wan2.dns');
        $peerdns       = $this->systemHelper->uciGet('network.wan2.peerdns');
        $pppredial     = $this->systemHelper->uciGet('network.wan2.ppp_redial');
        $defaultroute  = $this->systemHelper->uciGet('network.wan2.defaultroute');
        $keepalive     = $this->systemHelper->uciGet('network.wan2.keepalive');
        $pppdoptions   = $this->systemHelper->uciGet('network.wan2.pppd_options');

        /* Now send a response inside of an array, with keys being 'interface', 'protocol' etc
           and their values being those we obtained from uciGet(). */
        $this->responseHandler->setData(array('success'      => true,
                                'interface'    => $interface,
                                'protocol'     => $protocol,
                                'service'      => $service,
                                'vendorid'     => $vendorid,
                                'productid'    => $productid,
                                'device'       => $device,
                                'apn'          => $apn,
                                'username'     => $username,
                                'password'     => $password,
                                'dns'          => $dns,
                                'peerdns'      => $peerdns,
                                'pppredial'    => $pppredial,
                                'defaultroute' => $defaultroute,
                                'keepalive'    => $keepalive,
                                'pppdoptions'  => $pppdoptions));
    }

    public function saveConfiguration()
    {
        /* In the same way as loadConfiguration(), get the desired information and assign it to a variable.
           However this time get the data that was sent with the request from the JS. */
        $interface     = $this->request['interface'];
        $protocol      = $this->request['protocol'];
        $service       = $this->request['service'];
        $vendorid      = $this->request['vendorid'];
        $productid     = $this->request['productid'];
        $device        = $this->request['device'];
        $apn           = $this->request['apn'];
        $username      = $this->request['username'];
        $password      = $this->request['password'];
        $dns           = $this->request['dns'];
        $peerdns       = $this->request['peerdns'];
        $pppredial     = $this->request['pppredial'];
        $defaultroute  = $this->request['defaultroute'];
        $keepalive     = $this->request['keepalive'];
        $pppdoptions   = $this->request['pppdoptions'];

        /* Using the APIs uciSet() function, set the UCI properties to
           what the JS request gave us. */
        $this->systemHelper->uciSet('network.wan2',              'interface');
        $this->systemHelper->uciSet('network.wan2.ifname',       $interface);
        $this->systemHelper->uciSet('network.wan2.proto',        $protocol);
        $this->systemHelper->uciSet('network.wan2.service',      $service);
        $this->systemHelper->uciSet('network.wan2.currentVID',   $vendorid);
        $this->systemHelper->uciSet('network.wan2.currentPID',   $productid);
        $this->systemHelper->uciSet('network.wan2.device',       $device);
        $this->systemHelper->uciSet('network.wan2.apn',          $apn);
        $this->systemHelper->uciSet('network.wan2.peerdns',      $peerdns);
        $this->systemHelper->uciSet('network.wan2.ppp_redial',   $pppredial);
        $this->systemHelper->uciSet('network.wan2.defaultroute', $defaultroute);
        $this->systemHelper->uciSet('network.wan2.keepalive',    $keepalive);
        $this->systemHelper->uciSet('network.wan2.pppd_options', $pppdoptions);

        if(!empty($username)) {
            $this->systemHelper->uciSet('network.wan2.username', $username);
        }
        if (!empty($password)) {
            $this->systemHelper->uciSet('network.wan2.password', $password);
        }
        if(!empty($dns)) {
            $this->systemHelper->uciSet('network.wan2.dns', $dns);
        }

        unlink("/etc/modules.d/60-usb-serial");
        exec("echo 'usbserial vendor=0x$vendorid product=0x$productid maxSize=4096' > /etc/modules.d/60-usb-serial");

        $this->responseHandler->setData(array('success' => true));
    }

    public function resetConfiguration()
    {
        /* Delete the network.wan2 section */
        exec('uci del network.wan2');
        exec('uci commit');
        unlink('/etc/modules.d/60-usb-serial');

        $this->responseHandler->setData(array('success' => true));
    }
}

<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class SignalStrength extends Controller {
	protected $endpointRoutes = ['getVersionInfo', 'getWirelessInterfaces', 'getInterfaceScan', 'getInterfaceStatus', 'toggleInterface'];

	protected function getVersionInfo() {
		$moduleInfo = @json_decode(file_get_contents("/pineapple/modules/SignalStrength/module.info"));
		$this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
	}

	protected function getInterfaceScan() {
		exec('iwlist "'.$this->request['selectedInterface'].'" scanning | egrep "Cell |Channel|Quality|ESSID"', $interfaceScan);
		$interfaceScanArray = array();
		for($x=0;$x<count($interfaceScan);$x+=5) {                                             
			$bssid = substr($interfaceScan[$x], strpos($wlan0ScanOutput[$x], ":") +29);     
			$channel = substr($interfaceScan[$x+1], strpos($interfaceScan[$x+1], ":") +1);
			$quality = substr($interfaceScan[$x+3], strpos($interfaceScan[$x+3], "=") +1, 5);
			$strength = substr($interfaceScan[$x+3], strpos($interfaceScan[$x+3], "=", strpos(interfaceScan[$x+3], "=")+1)+1);
			$essid = substr($interfaceScan[$x+4], strpos($interfaceScan[$x+4], ":") +1); 
			array_push($interfaceScanArray, array("bssid" => $bssid, "channel" => $channel, "quality" => $quality, "strength" => $strength, "essid" => $essid));
                }                                                                                        
                $this->responseHandler->setData(array('interfaceScan' => $interfaceScanArray));
	}

	protected function getWirelessInterfaces() {
		exec("iwconfig 2> /dev/null | grep \"wlan*\" | awk '{print $1}'", $interfaces);
		$this->responseHandler->setData(array('interfaces' => $interfaces));
	}

	protected function getInterfaceStatus() {
		exec("ifconfig -a | cut -c 1-19 | egrep 'wlan|UP|BROADCAST' | awk '{print $1}' | tail -n+4", $interfaceStatus);
		$interfaceStatusArray = array();
		for ($y=0;$y<count($interfaceStatus);$y+=2) {
			$interface = $interfaceStatus[$y];
			$status = "Down";
			if ($interfaceStatus[$y+1] == "UP") { $status = "Up"; }
			array_push($interfaceStatusArray, array("interface" => $interface, "status" => $status));
		}
		$this->responseHandler->setData(array('interfaceStatus' => $interfaceStatusArray));
	}

	protected function toggleInterface() {
		$toggle = ($this->request['status'] == "Down") ? 'up' : 'down';
		exec('ifconfig "'.$this->request['interface'].'" "'.$toggle.'"', $toggleResponse);
	}
}

<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class ConnectedClients extends Controller
{
	protected $endpointRoutes = ['getVersionInfo', 'getDHCPLeases', 'getBlacklist', 'getConnectedClients', 'removeMacAddress', 'addMacAddress', 'disassociateMac', 'deauthenticateMac'];

	protected function getVersionInfo() {
		$moduleInfo = @json_decode(file_get_contents("/pineapple/modules/ConnectedClients/module.info"));
		$this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
	}

	public function getDHCPLeases() {
		exec("cat /tmp/dhcp.leases", $dhcpleases);
		$this->responseHandler->setData(array('dhcpleases' => $dhcpleases));		

	}

	public function getBlacklist() {
		exec("pineapple karma list_macs", $mac_list);
		$this->responseHandler->setData(array('blacklist' => $mac_list));
	}

	public function getConnectedClients() {
		exec("iwconfig 2>/dev/null | grep IEEE | awk '{print $1}'", $wlandev);
		exec("iw dev $wlandev[0] station dump | grep Station | awk '{print $2}'", $wlan0clients);
		exec("iw dev $wlandev[1] station dump | grep Station | awk '{print $2}'", $wlan01clients);
		exec("iw dev $wlandev[2] station dump | grep Station | awk '{print $2}'", $wlan1clients);
		$this->responseHandler->setData(array('wlan0clients' => $wlan0clients, 'wlan01clients' => $wlan01clients, 'wlan1clients' => $wlan1clients, 'wlandev' => $wlandev));
	}

	public function removeMacAddress() {
		exec('pineapple karma del_mac "'.$this->request['macAddress'].'"', $removeMacResponse);
		$this->responseHandler->setData(array('removeMacResponse' => $removeMacResponse));
	}

	public function addMacAddress() {
		exec('pineapple karma add_mac "'.$this->request['macAddress'].'"', $addMacResponse);
		$this->responseHandler->setData(array('addMacResponse' => $addMacResponse));
	}

	public function disassociateMac() {
		exec('hostapd_cli disassociate "'.$this->request['macAddress'].'"', $disassociateResponse);
		$this->responseHandler->setData(array('disassociateResponse' => $disassociateResponse));
	}

	public function deauthenticateMac() {
		exec('hostapd_cli deauthenticate "'.$this->request['macAddress'].'"', $deauthenticateResponse);
		$this->responseHandler->setData(array('deauthSuccess' => 'Successful', 'deauthenticateResponse' => $deauthenticateResponse));
	}
}

<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Meterpreter extends Controller
{
  protected $endpointRoutes = ['getState', 'startMeterpreter', 'stopMeterpreter', 'enableMeterpreter', 'disableMeterpreter', 'saveConfig'];

  public function getState()
  {
    if (!file_exists("/etc/config/meterpreter")) {
      exec("touch /etc/config/meterpreter");
    }

    $this->responseHandler->setData(array(
      "success" => true,
      "running" => $this->systemHelper->checkRunning('meterpreter'),
      "enabled" => $this->systemHelper->uciGet("meterpreter.autostart"),
      "config" => $this->getConfig()
    ));
  }

  public function startMeterpreter()
  {
    $host = $this->systemHelper->uciGet("meterpreter.host");
    $port = $this->systemHelper->uciGet("meterpreter.port");
    $this->systemHelper->execBackground("meterpreter $host $port");
    $this->responseHandler->setData(array("success" => true));
  }

  public function stopMeterpreter()
  {
    exec("killall meterpreter");
    $this->responseHandler->setData(array("success" => true));
  }

  public function enableMeterpreter()
  {
    $host = $this->systemHelper->uciGet("meterpreter.host");
    $port = $this->systemHelper->uciGet("meterpreter.port");
    exec("sed -i '1i /usr/bin/pineapple/meterpreter $host $port & # inserted by meterpreter module' /etc/rc.local");
    $this->systemHelper->uciSet("meterpreter.autostart", true);
    $this->responseHandler->setData(array("success" => true));
  }

  public function disableMeterpreter()
  {
    exec("sed -i '/meterpreter/d' /etc/rc.local");
    $this->systemHelper->uciSet("meterpreter.autostart", false);
    $this->responseHandler->setData(array("success" => true));
  }

  public function getConfig()
  {
    return array(
      "host" => $this->systemHelper->uciGet("meterpreter.host"),
      "port" => $this->systemHelper->uciGet("meterpreter.port")
    );
  }

  public function saveConfig()
  {
    $args = $this->request['params'];
    $this->systemHelper->uciSet("meterpreter.host", $args->host);
    $this->systemHelper->uciSet("meterpreter.port", $args->port);
    $this->toggleMeterpreter(); //resets rc.local to new settings in autostart is enabled
    $this->responseHandler->setData(array("success" => true, "args"=> $args));
  }

  public function toggleMeterpreter()
  {
    $enabled = $this->systemHelper->uciGet("meterpreter.autostart");
    if ($enabled == "1") {
      $this->disableMeterpreter();
      $this->enableMeterpreter();
    }
  }
}

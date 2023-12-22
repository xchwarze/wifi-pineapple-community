<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class autossh extends Controller
{
  protected $endpointRoutes = ['status', 'getInfo', 'stopAutossh', 'startAutossh', 'enableAutossh', 'disableAutossh', 'readConf', 'writeConf', 'resetConf', 'createSshKey', 'deleteKey'];

// Initial Setup
  public function createSshKey()
  {
    $path = "/root/.ssh/id_rsa.autossh";
    exec("ssh-keygen -f $path -t rsa -N ''");
    if (file_exists($path)) {
      $this->responseHandler->setData(array("success" => true));
    }
  }

  public function deleteKey()
  {
    exec('rm /root/.ssh/id_rsa.autossh*');
    $this->responseHandler->setData(array("success" => true));
  }

  public function ensureKnownHosts($args)
  {
    $cmd = "ssh -o StrictHostKeyChecking=no -o PasswordAuthentication=no -p $args->port $args->user@$args->host exit";
    $this->systemHelper->execBackground($cmd);
  }

  public function getInfo()
  {
    $this->responseHandler->setData(array(
      "success" => true,
      "pubKey" => $this->safeRead('/root/.ssh/id_rsa.autossh.pub'),
      "knownHosts" => shell_exec("awk '{print $1}' /root/.ssh/known_hosts")
    ));
  }

  public function safeRead($file)
  {
    return file_exists($file) ? file_get_contents($file) : "";
  }



// Configuration
  public function readConf()
  {
    $conf = $this->parsedConfig() + array("success" => true);
    $this->responseHandler->setData($conf);
  }

  public function resetConf()
  {
    exec("cp /rom/etc/config/autossh /etc/config/autossh");
    return $this->responseHandler->setData($this->parsedConfig() + array("success" => true));
  }

  public function parsedConfig()
  {
    $uciString = "autossh.@autossh[0].ssh";
    $contents = $this->systemHelper->uciGet($uciString);
    $args = preg_split("/\s|\t|:|@|'/", $contents);
    return $this->parseArguments(array_filter($args));
  }

  public function writeConf()
  {
    $args = $this->request['data'];
    $uciString = "autossh.@autossh[0].ssh";
    $option = $this->buildOptionString($args);
    $this->ensureKnownHosts($args);
    $this->systemHelper->uciSet($uciString, $option);
    $this->responseHandler->setData(array("success" => true));
  }

  public function buildOptionString($args)
  {
    return "-i /root/.ssh/id_rsa.autossh -N -T -R $args->rport:localhost:$args->lport $args->user@$args->host -p $args->port";
  }

  public function parseArguments($args)
  {
    return array(
      "user" => $args[8],
      "host" => $args[9],
      "port" => (!$args[11]) ? "22" : $args[11],
      "rport" => $args[5],
      "lport" => $args[7],
    );
  }


  // Management

  public function status()
  {
    $this->responseHandler->setData(array(
      "success" => true,
      "isRunning" => $this->isRunning(),
      "isEnabled" => $this->isEnabled()
    ));
  }

  public function isRunning()
  {
    return $this->systemHelper->checkRunning("autossh");
  }

  public function isEnabled()
  {
    $rcFile = "/etc/rc.d/S80autossh";
    return file_exists($rcFile);
  }

  public function startAutossh()
  {
    exec("/etc/init.d/autossh start");
    $this->responseHandler->setData(array("success" => true));
  }

  public function stopAutossh()
  {
    exec("/etc/init.d/autossh stop");
    $this->responseHandler->setData(array("success" => true));
  }

  public function enableAutossh()
  {
    exec("/etc/init.d/autossh enable");
    $this->responseHandler->setData(array("success" => true));
  }

  public function disableAutossh()
  {
    exec("/etc/init.d/autossh disable");
    $this->responseHandler->setData(array("success" => true));
  }

}

<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Commander extends Controller
{
    protected $endpointRoutes = ['startCommander', 'stopCommander', 'getConfiguration', 'saveConfiguration', 'restoreDefaultConfiguration'];

    public function startCommander()
    {
        $this->systemHelper->execBackground('cd /pineapple/modules/Commander/Python && python commander.py');
        $this->responseHandler->setData(array("success" => true));
    }

    public function stopCommander()
    {
        exec('kill -9 $(pgrep -f commander)');
        $this->responseHandler->setData(array("success" => true));
    }

    public function getConfiguration()
    {
        $config = file_get_contents('/pineapple/modules/Commander/Python/commander.conf');
        $this->responseHandler->setData(array("CommanderConfiguration" => $config));
    }

    public function saveConfiguration()
    {
        $config = $this->request['CommanderConfiguration'];
        file_put_contents('/pineapple/modules/Commander/Python/commander.conf', $config);
        $this->responseHandler->setData(array("success" => true));
    }

    public function restoreDefaultConfiguration()
    {
        $defaultConfig = file_get_contents('/pineapple/modules/Commander/assets/default.conf');
        file_put_contents('/pineapple/modules/Commander/Python/commander.conf', $defaultConfig);
        $this->responseHandler->setData(array("success" => true));
    }
}
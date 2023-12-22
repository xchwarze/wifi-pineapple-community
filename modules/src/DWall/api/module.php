<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class DWall extends Controller
{
    protected $endpointRoutes = ['enable', 'disable', 'getStatus'];

    public function enable()
    {
        $this->disable();

        $this->systemHelper->execBackground("python /pineapple/modules/DWall/assets/DWall.py");
        $this->systemHelper->execBackground("/usr/sbin/http_sniffer br-lan");
        $this->responseHandler->setData(array("success" => true));
    }

    public function disable()
    {
        exec("killall http_sniffer");
        exec("kill \$(ps -aux | grep DWall | head -n1 | awk '{print $2}')");
        $this->responseHandler->setData(array("success" => true));
    }

    public function getStatus()
    {
        if (trim(exec("ps -aux | grep [D]Wall.py")) != "" && trim(exec("ps -aux | grep [h]ttp_sniffer")) != "") {
            $this->responseHandler->setData(array("running" => true));
        } else {
            $this->responseHandler->setData(array("running" => false));
        }
    }
}

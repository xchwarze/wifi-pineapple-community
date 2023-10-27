<?php namespace pineapple;

class DWall extends Module
{
    public function route()
    {
        switch ($this->request->action) {
            case 'enable':
                $this->enable();
                break;
            case 'disable':
                $this->disable();
                break;
            case 'getStatus':
                $this->getStatus();
                break;
        }
    }

    private function enable()
    {
        $this->disable();

        $this->execBackground("python /pineapple/modules/DWall/assets/DWall.py");
        $this->execBackground("/usr/sbin/http_sniffer br-lan");
        $this->response = array("success" => true);
    }

    private function disable()
    {
        exec("killall http_sniffer");
        exec("kill \$(ps -aux | grep DWall | head -n1 | awk '{print $2}')");
        $this->response = array("success" => true);
    }

    private function getStatus()
    {
        if (trim(exec("ps -aux | grep [D]Wall.py")) != "" && trim(exec("ps -aux | grep [h]ttp_sniffer")) != "") {
            $this->response = array("running" => true);
        } else {
            $this->response = array("running" => false);
        }
    }
}

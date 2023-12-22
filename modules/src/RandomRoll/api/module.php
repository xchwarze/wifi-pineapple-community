<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class RandomRoll extends Controller
{
    protected $endpointRoutes = ['checkStatus', 'startRandomRoll', 'stopRandomRoll', 'getRandomRollRolls', 'getRandomRollLogs', 'clearRandomRollLogs'];

    public function checkStatus()
    {
        $running = file_get_contents('/pineapple/modules/RandomRoll/assets/running');
        if($running == 1){
            $this->responseHandler->setData(array("running" => true));
        } else {
            $this->responseHandler->setData(array("running" => false));
        }
    }

    public function startRandomRoll()
    {
        $date = date("Ymd H:i:s -- ");
        file_put_contents("/pineapple/modules/RandomRoll/assets/logs/randomroll.log", $date . "RandomRoll Started\n", FILE_APPEND);

        foreach($this->request['selected'] as $roll){
            $title = $roll->randomRollTitle;
            $checked = $roll->randomRollChecked;
            if ($checked){
                exec('iptables -t nat -A PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
                exec('iptables -t nat -A POSTROUTING -j MASQUERADE');
                exec('mv /www/index.php /pineapple/modules/RandomRoll/assets/www/index.php');
                symlink('/pineapple/modules/RandomRoll/assets/selector.php', '/www/index.php');
                @mkdir('/www/Rolls');
                symlink("/pineapple/modules/RandomRoll/assets/Rolls/{$title}", "/www/Rolls/{$title}");
            }
        }

        file_put_contents('/pineapple/modules/RandomRoll/assets/running', '1');

        $this->responseHandler->setData(array("success" => true));
    }

    public function stopRandomRoll()
    {
        $date = date("Ymd H:i:s -- ");
        file_put_contents("/pineapple/modules/RandomRoll/assets/logs/randomroll.log", $date . "RandomRoll Stopped\n\n", FILE_APPEND);

        exec('iptables -t nat -D PREROUTING -p tcp --dport 80 -j DNAT --to-destination $(uci get network.lan.ipaddr):80');
        unlink('/www/index.php');
        exec('mv /pineapple/modules/RandomRoll/assets/www/index.php /www/index.php');
        exec('rm -rf /www/Rolls/');

        file_put_contents('/pineapple/modules/RandomRoll/assets/running', '0');

        $this->responseHandler->setData(array("success" => true));
    }

    public function getRandomRollRolls()
    {
        $rolls = array();
        
        foreach(glob("/pineapple/modules/RandomRoll/assets/Rolls/*") as $roll){
            $rollname = basename($roll);
            array_push($rolls, array("randomRollTitle" => $rollname, "randomRollChecked" => false));
        }

        $this->responseHandler->setData($rolls);
    }

    public function getRandomRollLogs()
    {
        $randomRollLogOutput = file_get_contents('/pineapple/modules/RandomRoll/assets/logs/randomroll.log');
        $this->responseHandler->setData(array("randomRollLogOutput" => $randomRollLogOutput));
    }

    public function clearRandomRollLogs()
    {
        file_put_contents("/pineapple/modules/RandomRoll/assets/logs/randomroll.log", "");
    }

}





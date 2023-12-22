<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
/* The class name must be the name of your module, without spaces. */
/* It must also extend the "Module" class. This gives your module access to API functions */
class MACInfo extends Controller
{
    protected $endpointRoutes = ['getMACInfo'];

    public function getMACInfo($mac)
    {
        $mac = $this->request['moduleMAC'];
        if($this->IsValidMAC($mac)){
            $url = "https://macvendors.co/api/" . $mac . "/JSON";
            $retJSON = file_get_contents($url);
            if($retJSON != false){
                $mInfo = json_decode($retJSON);
                if(isset($mInfo) && isset($mInfo->result) && $mInfo->result->error != ""){
                    $this->responseHandler->setData(array("success" => false, "error" => $mInfo->result->error));
                }
                else{
                    $this->responseHandler->setData(array("success" => true,
                                            "company" => $mInfo->result->company,
                                            "macprefix" => $mInfo->result->mac_prefix,
                                            "address" => $mInfo->result->address,
                                            "country" => $mInfo->result->country,
                                            "type" => $mInfo->result->type
                    ));
                }
            }
            else{ $this->responseHandler->setData(array("success" => false, "error" => "Error reading contents from: " . $url)); }
        }
        else{ $this->responseHandler->setData(array("success" => false, "error" => "Invalid MAC Address format")); }
    }
    public function IsValidMAC($mac) {
        $pregResult = preg_match('/([a-fA-F0-9]{2}[:|\-]?){6}/', $mac);
        return ($pregResult != 0 && $pregResult != NULL);
    }
}

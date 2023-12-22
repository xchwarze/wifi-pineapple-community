<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */

define('__MODULE_LOCATION__', "/pineapple/modules/SSIDManager/");
define('__SSID_FILES__', __MODULE_LOCATION__ . "SSID_Files/");
define('__MODULE_INFO__', __MODULE_LOCATION__ . "module.info");

/* The class name must be the name of your module, without spaces. */
/* It must also extend the "Module" class. This gives your module access to API functions */
class SSIDManager extends Controller
{

    protected $endpointRoutes = ['getContents', 'getSSIDFilesList', 'deleteSSIDFile', 'archivePool', 'getSSIDFile', 'downloadSSIDFile'];

    public function SSIDDirectoryPath()
    {
        if (!file_exists(__SSID_FILES__)) {
            mkdir(__SSID_FILES__, 0755, true);
        }
        return __SSID_FILES__;
    }

    public function getContents()
    {
        $moduleInfo = @json_decode(file_get_contents(__MODULE_INFO__));

        $this->responseHandler->setData(array("success" => true,
                    "version" => "version " . $moduleInfo->version,
                    "content" => ""));
    }


    public function getSSIDFilesList()
    {
        $SSIDFilesPath = $this->SSIDDirectoryPath();
        $files_list =  glob($SSIDFilesPath . '*')  ;

        for ($i=0; $i<count($files_list); $i++) {
            $files_list[$i] = basename($files_list[$i]);
        }
        $this->responseHandler->setData(array("success"=>true, "filesList"=>$files_list));
    }

    public function saveSSIDFile()
    {
        $filename = $this->SSIDDirectoryPath().$this->request['storeFileName'];
        file_put_contents($filename, $this->request['ssidPool']);
        $this->responseHandler->setData(array("success" => true));
    }

    public function downloadSSIDFile()
    {
        $filename = $this->SSIDDirectoryPath().$this->request['file'];
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile($filename)));
    }

    public function deleteSSIDFile()
    {
        unlink($this->SSIDDirectoryPath() . $this->request['file']);
        $this->responseHandler->setData(array("success" => true));
    }

    public function loadSSIDFile()
    {
        $filename = $this->SSIDDirectoryPath() . $this->request['file'];
        $fileContent = file_get_contents($filename);
        $this->responseHandler->setData(array("success" => true,"content"=>$fileContent,"fileName"=>$filename));
    }
}

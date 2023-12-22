<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class LogManager extends Controller
{
    protected $endpointRoutes = ['refreshInfo', 'refreshFilesList', 'downloadFilesList', 'deleteFilesList', 'viewModuleFile', 'deleteModuleFile', 'downloadModuleFile'];

    public function dataSize($path)
    {
        $blah = exec("/usr/bin/du -sch $path | tail -1 | awk {'print $1'}");
        return $blah;
    }

    protected function refreshInfo()
    {
        $moduleInfo = @json_decode(file_get_contents("/pineapple/modules/LogManager/module.info"));
        $this->responseHandler->setData(array('title' => $moduleInfo->title, 'version' => $moduleInfo->version));
    }

    public function downloadFilesList()
    {
        $files = $this->request['files'];

        exec("mkdir /tmp/dl/");
        foreach ($files as $file) {
            exec("cp ".$file." /tmp/dl/");
        }
        exec("cd /tmp/dl/ && tar -czf /tmp/files.tar.gz *");
        exec("rm -rf /tmp/dl/");

        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile("/tmp/files.tar.gz")));
    }

    public function deleteFilesList()
    {
        $files = $this->request['files'];

        foreach ($files as $file) {
            exec("rm -rf ".$file);
        }
    }

    public function refreshFilesList()
    {
        $modules = array();
        foreach (glob('/pineapple/modules/*/log/*') as $file) {
            $module = array();
            $module['file'] = basename($file);
            $module['path'] = $file;
            $module['size'] = $this->dataSize($file);
            $module['title'] = explode("/", dirname($file))[3];
            $module['date'] = gmdate("F d Y H:i:s", filemtime($file));
            $module['timestamp'] = filemtime($file);
            $modules[] = $module;
        }

        foreach (glob('/pineapple/modules/*/dump/*') as $file) {
            $module = array();
            $module['file'] = basename($file);
            $module['path'] = $file;
            $module['size'] = $this->dataSize($file);
            $module['title'] = explode("/", dirname($file))[3];
            $module['date'] = gmdate("F d Y H:i:s", filemtime($file));
            $module['timestamp'] = filemtime($file);
            $modules[] = $module;
        }

        foreach (glob('/pineapple/modules/*/scan/*') as $file) {
            $module = array();
            $module['file'] = basename($file);
            $module['path'] = $file;
            $module['size'] = $this->dataSize($file);
            $module['title'] = explode("/", dirname($file))[3];
            $module['date'] = gmdate("F d Y H:i:s", filemtime($file));
            $module['timestamp'] = filemtime($file);
            $modules[] = $module;
        }

        foreach (glob('/pineapple/modules/*/capture/*') as $file) {
            $module = array();
            $module['file'] = basename($file);
            $module['path'] = $file;
            $module['size'] = $this->dataSize($file);
            $module['title'] = explode("/", dirname($file))[3];
            $module['date'] = gmdate("F d Y H:i:s", filemtime($file));
            $module['timestamp'] = filemtime($file);
            $modules[] = $module;
        }

        usort($modules, create_function('$a, $b', 'if($a["timestamp"] == $b["timestamp"]) return 0; return ($a["timestamp"] > $b["timestamp"]) ? -1 : 1;'));

        $this->responseHandler->setData(array("files" => $modules));
    }

    public function viewModuleFile()
    {
        $log_date = gmdate("F d Y H:i:s", filemtime($this->request['file']));
        exec("strings ".$this->request['file'], $output);

        if (!empty($output)) {
            $this->responseHandler->setData(array("output" => implode("\n", $output), "date" => $log_date, "name" => basename($this->request['file'])));
        } else {
            $this->responseHandler->setData(array("output" => "Empty file...", "date" => $log_date, "name" => basename($this->request['file'])));
        }
    }

    public function deleteModuleFile()
    {
        exec("rm -rf ".$this->request['file']);
    }

    public function downloadModuleFile()
    {
        $this->responseHandler->setData(array("download" => $this->systemHelper->downloadFile($this->request['file'])));
    }
}

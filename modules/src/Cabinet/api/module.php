<?php namespace frieren\core;

/* Code modified by Frieren Auto Refactor */
class Cabinet extends Controller
{

	protected $endpointRoutes = ['getDirectoryContents', 'getParentDirectory', 'deleteFile', 'editFile', 'getFileContents', 'createFolder', 'download'];

	public function getDirectoryContents()
	{
		$dir = $this->request['directory'];

		$success = false;
		$contents = array();
		if (file_exists($dir)) {
			foreach (preg_grep('/^([^.])/', scandir($dir)) as $file) {
				$obj = array("name" => $file, "directory" => is_dir($dir . '/' . $file),
				"path" => realpath($dir . '/' . $file), 
				"permissions" => substr(sprintf('%o', fileperms($dir . '/' . $file)), -4),
				"size" => $this->readableFileSize($dir . '/' . $file));
				array_push($contents, $obj);
			}
			$success = true;
		}

		$this->responseHandler->setData(array("success" => $success, "contents" => $contents, "directory" => $dir));

	}

	public function getParentDirectory()
	{
		$dir = $this->request['directory'];
		$success = false;
		$parent = "";

		if (file_exists($dir)) {
			$parent = dirname($dir);
			$success = true;
		}

		$this->responseHandler->setData(array("success" => $success, "parent" => $parent));

	}

	public function deleteFile()
	{
		$f = $this->request['file'];
		$success = false;

		if (file_exists($f)) {
			exec("rm -rf " . escapeshellarg($f));
		}

		if (!file_exists($f)) {
			$success = true;
		}

		$this->responseHandler->setData(array("success" => $success));

	}

	public function editFile()
	{
		$f = $this->request['file'];
		$data = $this->request['contents'];
		$success = false;

		file_put_contents($f, $data);
		if (file_exists($f)) {
			$success = true;
		}

		$this->responseHandler->setData(array("success" => $success));
	}

	public function getFileContents()
	{
		$f = $this->request['file'];
		$success = false;
		$content = "";
		$size = "0 Bytes";

		if (file_exists($f)) {
			$success = true;
			$content = file_get_contents($f);
			$size = $this->readableFileSize($f);
		}

		$this->responseHandler->setData(array("success" => $success, "content" => $content, "size" => $size));

	}

	public function createFolder()
	{
		$dir = $this->request['directory'];
		$name = $this->request['name'];
		$success = false;

		if (!is_dir($dir . '/' . $name)) {
			$success = true;
			mkdir($dir . "/" . $name);
		}

		$this->responseHandler->setData(array("success" => $success));
	}

    /**
     * Download a file
     * @param: The path to the file to download
     * @return array : array
     */
    public function download()
    {
        $filePath = $this->request['filePath'];
        if (file_exists($filePath)) {
            $this->responseHandler->setData( array("success" => true, "message" => null, "download" => $this->systemHelper->downloadFile($filePath)) );
        } else {
            $this->responseHandler->setData( array("success" => false, "message" => "File does not exist", "download" => null) );
        }
    }

    /**
     * Get the size of a file and add a unit to the end of it.
     * @param $file: The file to get size of
     * @return string: File size plus unit. Exp: 3.14M
     */
    public function readableFileSize($file) {
        $size = filesize($file);

        if ($size == null)
            return "0 Bytes";

        if ($size < 1024) {
            return "{$size} Bytes";
        } else if ($size >= 1024 && $size < 1024*1024) {
            return round($size / 1024, 2) . "K";
        } else if ($size >= 1024*1024) {
            return round($size / (1024*1024), 2) . "M";
        }
        return "{$size} Bytes";
    }

}
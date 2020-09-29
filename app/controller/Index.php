<?php

namespace app\controller;

use app\BaseController;
use think\facade\View;

class Index extends BaseController
{

    protected $url = "http://www.uploader.com";

    public function index()
    {
        //获取已上传文件列表


        return View::fetch();
    }

    /**
     * 上传文件函数，如过上传不成功打印$_FILES数组，查看error报错信息
     * 值：0; 没有错误发生，文件上传成功。
     * 值：1; 上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值。
     * 值：2; 上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值。
     * 值：3; 文件只有部分被上传。
     * 值：4; 没有文件被上传。
     */
    public function uploadFile($folder = 'video')
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Content-type: text/html; charset=gbk32");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        set_time_limit(0);

        $targetDir = app()->getRuntimePath() . 'file_material_tmp'; //存放分片临时目录
        $uploadDir = app()->getRootPath() . 'public/uploads/' . $folder . '/' . date('Ym');

        $cleanupTargetDir = true; // Remove old files
        $maxFileAge = 5 * 3600; // Temp file age in seconds

        sleep(1);

        // Create target dir
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        // Create target dir
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        // Get a file name
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }
        $oldName = $fileName;

        $fileName = iconv('UTF-8', 'gb2312', $fileName);
        $filePath = $targetDir . '/' . $fileName;
        // Chunking might be enabled
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 1;
        // Remove old temp files
        if ($cleanupTargetDir) {
            if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 100, "message": "Failed to open temp directory111."}, "id" : "id"}');
            }
            while (($file = readdir($dir)) !== false) {
                $tmpfilePath = $targetDir . '/' . $file;
                // If temp file is current file proceed to the next
                if ($tmpfilePath == "{$filePath}_{$chunk}.part" || $tmpfilePath == "{$filePath}_{$chunk}.parttmp") {
                    continue;
                }
                // Remove temp file if it is older than the max age and is not the current file
                if (preg_match('/\.(part|parttmp)$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
                    unlink($tmpfilePath);
                }
            }
            closedir($dir);
        }
        // Open temp file
        if (!$out = fopen("{$filePath}_{$chunk}.parttmp", "wb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream222."}, "id" : "id"}');
        }
        if (!empty($_FILES)) {
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file333."}, "id" : "id"}');
            }
            // Read binary input stream and append it to temp file
            if (!$in = fopen($_FILES["file"]["tmp_name"], "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream444."}, "id" : "id"}');
            }
        } else {
            if (!$in = fopen("php://input", "rb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream555."}, "id" : "id"}');
            }
        }
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        fclose($out);
        fclose($in);
        rename("{$filePath}_{$chunk}.parttmp", "{$filePath}_{$chunk}.part");
        $index = 0;
        $done = true;
        for ($index = 0; $index < $chunks; $index++) {
            if (!file_exists("{$filePath}_{$index}.part")) {
                $done = false;
                break;
            }
        }

        if ($done) {
            $pathInfo = pathinfo($fileName);
            $hashStr = substr(md5($pathInfo['basename']), 8, 16);
            $hashName = time() . $hashStr . '.' . $pathInfo['extension'];
            $uploadPath = $uploadDir .'/'. $hashName;
            if (!$out = fopen($uploadPath, "wb")) {
                die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream666."}, "id" : "id"}');
            }
            //flock($hander,LOCK_EX)文件锁
            if (flock($out, LOCK_EX)) {
                for ($index = 0; $index < $chunks; $index++) {
                    if (!$in = fopen("{$filePath}_{$index}.part", "rb")) {
                        break;
                    }
                    while ($buff = fread($in, 4096)) {
                        fwrite($out, $buff);
                    }
                    fclose($in);
                    unlink("{$filePath}_{$index}.part");
                }
                flock($out, LOCK_UN);
            }
            fclose($out);
            $response = [
                'success' => true,
                'oldName' => $oldName,
                'filePath' => $uploadPath,
                'fileUrl' => $this->url.'/uploads/'.$folder.'/'.date('Ym').'/'. $hashName,
//                'fileSize'=>$data['size'],
                'fileSuffixes' => $pathInfo['extension'],          //文件后缀名
//                'file_id'=>$data['id'],
            ];
            return json($response);
        }

        // Return Success JSON-RPC response
        die('{"jsonrpc" : "2.0", "result" : null, "id" : "id"}');
    }

}

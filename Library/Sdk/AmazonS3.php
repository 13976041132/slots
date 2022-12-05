<?php
/**
 * AmazonS3
 */

namespace FF\Library\Sdk;

use FF\Framework\Core\FF;
use FF\Framework\Common\Code;
use Aws\S3\S3Client;

file_require(PATH_LIB . '/Vendor/Aws/aws-autoloader.php');

class AmazonS3
{
    private $s3Client;

    private $version;
    private $region;
    private $credentials;

    private $bucket;

    public function __construct($options = array())
    {
        $this->version = $options['version'];
        $this->region = $options['region'];
        $this->credentials = $options['credentials'];
        $this->bucket = $options['bucket'];
    }

    public function s3()
    {
        if ($this->s3Client) {
            return $this->s3Client;
        }

        $s3Client = new S3Client(array(
            'version' => $this->version,
            'region' => $this->region,
            'credentials' => $this->credentials,
        ));

        $this->s3Client = $s3Client;

        return $s3Client;
    }

    public function downloadObject($path, $downloadDir, $file)
    {
        $this->checkDir($path);

        $dir = $downloadDir ?: (PATH_ROOT . $path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        try {
            $result = $this->s3()->getObject(array(
                'Bucket' => $this->bucket,
                'Key' => $path . '/' . $file
            ));
            file_put_contents($dir . '/' . $file, $result['Body']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 下载文件到本地目录
     */
    public function downloadObjects($path, $downloadDir = '')
    {
        $this->checkDir($path);

        $dir = $downloadDir ?: (PATH_ROOT . $path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->s3()->downloadBucket($dir, $this->bucket, $path . '/');
    }

    /**
     * 获取对象列表
     */
    public function getObjects($dir = '')
    {
        if ($dir && $dir[0] === '/') {
            $dir = mb_substr($dir, 1);
        }

        if ($dir && $dir[strlen($dir) - 1] != '/') {
            $dir .= '/';
        }

        $client = $this->s3();
        $results = $client->getPaginator('ListObjects', array(
            'Bucket' => $this->bucket,
            "Prefix" => $dir,
            "Delimiter" => "/",
        ));

        $list = [];
        foreach ($results as $result) {
            // 目录
            if ($result['CommonPrefixes']) {
                foreach ($result['CommonPrefixes'] as $commonPrefix) {
                    $list[] = $commonPrefix['Prefix'];
                }
            }
            // 文件
            if ($result['Contents']) {
                foreach ($result['Contents'] as $object) {
                    if ($object['Key'] == $dir) continue;
                    $list[] = $object['Key'];
                }
            }
        }

        foreach ($list as &$key) {
            $key = mb_substr($key, mb_strlen($dir));
        }

        return $list;
    }

    /**
     * 上传文件
     */
    public function uploadFile($file, $savePath = null, $unique = false, &$overwrite = false)
    {
        $this->checkDir($savePath);

        $filename = $file['name'];

        if ($unique) {
            $fileKey = $this->makeUniqueFile($savePath, $filename);
        } else {
            if ($savePath) {
                $fileKey = $savePath . '/' . $filename;
            } else {
                $fileKey = $filename;
            }
            if ($this->s3()->doesObjectExist($this->bucket, $fileKey)) {
                $overwrite = true;
            }
        }

        $uploaded = false;
        for ($i = 0; $i < 3; $i++) {
            $this->s3()->putObject(array(
                'Bucket' => $this->bucket,
                'Key' => $fileKey,
                'SourceFile' => $file['tmp_name'],
                'ContentType' => $file['type'],
            ));
            if ($this->s3()->doesObjectExist($this->bucket, $fileKey)) {
                $uploaded = true;
                break;
            }
        }

        return $uploaded ? $filename : false;
    }

    /**
     * 创建目录
     */
    public function createDir($path, $dir)
    {
        $this->checkDir($path);
        if ($path) {
            $key = $path . '/' . $dir . '/';
        } else {
            $key = $dir . '/';
        }

        if ($this->s3()->doesObjectExist($this->bucket, $key)) {
            FF::throwException(Code::FAILED, '不能创建同名目录！');
        }

        $this->s3()->putObject(array(
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => '',
        ));

        return $key;
    }

    /**
     * 删除一个对象
     */
    public function deleteObject($key, $checkExist = true)
    {
        $client = $this->s3();

        if ($key[0] == '/') {
            $key = mb_substr($key, 1);
        }

        if ($checkExist) {
            if (!$client->doesObjectExist($this->bucket, $key)) {
                return false;
            }
        }

        $client->deleteObject(array(
            'Bucket' => $this->bucket,
            'Key' => $key
        ));

        return true;
    }

    /**
     * 删除目录
     */
    public function deleteDir($key, $checkExist = true)
    {
        $client = $this->s3();

        if ($key[0] == '/') {
            $key = mb_substr($key, 1);
        }

        if ($key === '') {
            return false;
        }

        if ($checkExist) {
            if (!$client->doesObjectExist($this->bucket, $key)) {
                return false;
            }
        }

        $client->deleteMatchingObjects($this->bucket, $key);

        return true;
    }

    /**
     * 保证存储文件名不重复
     */
    protected function makeUniqueFile($savePath, &$filename)
    {
        $suffix = 1;
        $fileInfo = explode('.', $filename);
        $ext = array_pop($fileInfo);
        $originName = mb_substr($filename, 0, -strlen($ext) - 1);

        while (1) {
            if ($savePath) {
                $saveFile = $savePath . '/' . $filename;
            } else {
                $saveFile = $filename;
            }
            if ($this->s3()->doesObjectExist($this->bucket, $saveFile)) {
                $filename = $originName . '_' . $suffix . '.' . $ext;
                $suffix++;
            } else {
                break;
            }
        }

        return $saveFile;
    }

    /**
     * 目录名首尾`/` 检查
     */
    protected function checkDir(&$dir)
    {
        if ($dir && $dir[0] == '/') {
            $dir = mb_substr($dir, 1);
        }

        if ($dir && mb_substr($dir, -1) == '/') {
            $dir = mb_substr($dir, 0, -1);
        }
    }
}
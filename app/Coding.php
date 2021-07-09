<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Coding
{
    private Client $client;
    private \ZipArchive $zipArchive;

    public function __construct(Client $client = null, \ZipArchive $zipArchive = null)
    {
        $this->client = $client ?? new Client();
        $this->zipArchive = $zipArchive ?? new \ZipArchive();
    }

    public function createWiki($token, $projectName, $data)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => array_merge([
                'Action' => 'CreateWiki',
                'ProjectName' => $projectName,
            ], $data),
        ]);
        return json_decode($response->getBody(), true)['Response']['Data'];
    }

    public function createUploadToken($token, $projectName, $fileName)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'CreateUploadToken',
                'ProjectName' => $projectName,
                'FileName' => $fileName,
            ],
        ]);
        $uploadToken = json_decode($response->getBody(), true)['Response']['Token'];
        preg_match_all(
            '|https://([a-z0-9\-]+)-(\d+)\.cos\.([a-z0-9\-]+)\.myqcloud\.com|',
            $uploadToken['UploadLink'],
            $matches
        );
        $uploadToken['Bucket'] = $matches[1][0] . '-' . $matches[2][0];
        $uploadToken['AppId'] = $matches[2][0];
        $uploadToken['Region'] = $matches[3][0];
        return $uploadToken;
    }

    public function createMarkdownZip($markdown, $path, $markdownFilename): bool|string
    {
        $zipFileFullPath = sys_get_temp_dir() . '/' . $markdownFilename . '-' . Str::uuid() . '.zip';
        if ($this->zipArchive->open($zipFileFullPath, \ZipArchive::CREATE) !== true) {
            Log::error("cannot open <$zipFileFullPath>");
            return false;
        }
        $this->zipArchive->addFromString($markdownFilename, $markdown);
        preg_match_all('/!\[\]\((.+)\)/', $markdown, $matches);
        if (!empty($matches)) {
            foreach ($matches[1] as $attachment) {
                $this->zipArchive->addFile($path . $attachment, $attachment);
            }
        }
        $this->zipArchive->close();
        return $zipFileFullPath;
    }

    public function upload(array $uploadToken, string $fileFullPath): bool
    {
        config(['filesystems.disks.cos.credentials.appId' => $uploadToken['AppId']]);
        config(['filesystems.disks.cos.credentials.secretId' => $uploadToken['SecretId']]);
        config(['filesystems.disks.cos.credentials.secretKey' => $uploadToken['SecretKey']]);
        config(['filesystems.disks.cos.credentials.token' => $uploadToken['UpToken']]);
        config(['filesystems.disks.cos.region' => $uploadToken['Region']]);
        config(['filesystems.disks.cos.bucket' => $uploadToken['Bucket']]);

        $disk = Storage::build(config('filesystems.disks.cos'));
        return $disk->put($uploadToken['StorageKey'], File::get($fileFullPath));
    }

    public function createWikiByZip(string $token, string $projectName, array $uploadToken, array $data)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'CreateWikiByZip',
                'ProjectName' => $projectName,
                'ParentIid' => $data['ParentIid'],
                'FileName' => $data['FileName'],
                'Key' => $uploadToken['StorageKey'],
                'Time' => $uploadToken['Time'],
                'AuthToken' => $uploadToken['AuthToken'],
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        if (isset($result['Response']['JobId'])) {
            return $result['Response'];
        } else {
            return new \Exception('createWikiByZip failed');
        }
    }

    /**
     * 获取 Wiki 导入任务的进度（API 文档未展示，其实此接口已上线）
     *
     * @param string $token
     * @param string $projectName
     * @param string $jobId
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getImportJobStatus(string $token, string $projectName, string $jobId)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'DescribeImportJobStatus',
                'ProjectName' => $projectName,
                'JobId' => $jobId,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        if (isset($result['Response']['Data']['Status'])) {
            return $result['Response']['Data']['Status'];
        } else {
            // TODO exception message
            return new \Exception('failed');
        }
    }

    public function createWikiByUploadZip(string $token, string $projectName, string $zipFileFullPath)
    {
        $zipFilename = basename($zipFileFullPath);
        $uploadToken = $this->createUploadToken(
            $token,
            $projectName,
            $zipFilename
        );
        $this->upload($uploadToken, $zipFileFullPath);
        return $this->createWikiByZip($token, $projectName, $uploadToken, [
            'ParentIid' => 0,
            'FileName' => $zipFilename,
        ]);
    }
}

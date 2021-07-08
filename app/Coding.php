<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    public function createMarkdownZip($markdown, $path, $filename): bool|string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $filename);
        $zipFileFullPath = $tmpFile . '.zip';
        rename($tmpFile, $zipFileFullPath);
        if ($this->zipArchive->open($zipFileFullPath, \ZipArchive::OVERWRITE) !== true) {
            Log::error("cannot open <$zipFileFullPath>");
            return false;
        }
        $this->zipArchive->addFromString($filename, $markdown);
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

        return Storage::disk('cos')->put(basename($fileFullPath), $fileFullPath);
    }
}

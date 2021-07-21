<?php

namespace App\Coding;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class Base
{
    protected Client $client;
    protected ZipArchive $zipArchive;

    public function __construct(Client $client = null, ZipArchive $zipArchive = null)
    {
        $this->client = $client ?? new Client();
        $this->zipArchive = $zipArchive ?? new ZipArchive();
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
}

<?php

namespace App;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

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
                'Action' => 'CreateWiki',
                'ProjectName' => $projectName,
                'FileName' => $fileName,
            ],
        ]);
        return json_decode($response->getBody(), true)['Response']['Token'];
    }

    public function createMarkdownZip($markdown, $path, $filename): bool|string
    {
        $zipFilename = tempnam(sys_get_temp_dir(), $filename);
        if ($this->zipArchive->open($zipFilename, \ZipArchive::OVERWRITE) !== true) {
            Log::error("cannot open <$zipFilename>");
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
        return $zipFilename;
    }
}

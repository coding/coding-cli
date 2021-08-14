<?php

namespace App\Coding;

class Disk extends Base
{
    /**
     * 创建网盘目录，不可重名，如已存在，仍然正常返回 id
     *
     * @param string $token
     * @param string $projectName
     * @param string $folderName
     * @param int $parentId
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function createFolder(string $token, string $projectName, string $folderName, int $parentId = 0): int
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'CreateFolder',
                'ProjectName' => $projectName,
                'FolderName' => $folderName,
                'ParentId' => $parentId,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['Data']['Id'];
    }

    /**
     * @param string $token
     * @param string $projectName
     * @param array $data
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @todo data 数组无法强类型校验内部字段，考虑用对象
     */
    public function createFile(string $token, string $projectName, array $data): array
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => array_merge([
                'Action' => 'CreateFile',
                'ProjectName' => $projectName,
            ], $data),
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['Data'];
    }

    public function uploadAttachments(string $token, string $projectName, string $dataDir, array $attachments): array
    {
        if (empty($attachments)) {
            return [];
        }
        $data = [];
        // TODO hard code folder name
        $folderId = $this->createFolder($token, $projectName, 'wiki-attachments');
        foreach ($attachments as $path => $filename) {
            $uploadToken = $this->createUploadToken(
                $token,
                $projectName,
                $filename
            );
            $filePath = $dataDir . DIRECTORY_SEPARATOR . $path;
            $result = [];
            try {
                $this->upload($uploadToken, $filePath);
                $result = $this->createFile($token, $projectName, [
                    "OriginalFileName" => $filename,
                    "MimeType" => mime_content_type($filePath),
                    "FileSize" => filesize($filePath),
                    "StorageKey" => $uploadToken['StorageKey'],
                    "Time" => $uploadToken['Time'],
                    "AuthToken" => $uploadToken['AuthToken'],
                    "FolderId" => $folderId,
                ]);
            } catch (\Exception $e) {
                // TODO laravel log
                error_log('ERROR: ' . $e->getMessage());
            }
            $data[$path] = $result;
        }
        return $data;
    }
}

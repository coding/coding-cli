<?php

namespace App\Coding;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class Wiki extends Base
{
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

    public function createMarkdownZip($markdown, $path, $markdownFilename): bool|string
    {
        $zipFileFullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $markdownFilename . '-' . Str::uuid() . '.zip';
        if ($this->zipArchive->open($zipFileFullPath, ZipArchive::CREATE) !== true) {
            Log::error("cannot open <$zipFileFullPath>");
            return false;
        }
        $this->zipArchive->addFromString($markdownFilename, $markdown);
        preg_match_all('/!\[\]\(([a-z0-9\/\._\-]+)\)/', $markdown, $matches);
        if (!empty($matches)) {
            foreach ($matches[1] as $attachment) {
                // markdown image title: ![](images/default.svg "admin")
                $tmp = explode(' ', $attachment);
                $filename = $tmp[0];
                $filepath = $path . DIRECTORY_SEPARATOR . $filename;
                if (!file_exists($filepath)) {
                    error_log("文件不存在：$filename");
                    continue;
                }
                $this->zipArchive->addFile($filepath, $filename);
            }
        }
        $this->zipArchive->close();
        return $zipFileFullPath;
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
        if (!isset($result['Response']['JobId'])) {
            return new Exception('failed');
        }
        return $result['Response'];
    }

    /**
     * 获取 Wiki 导入任务的进度（API 文档未展示，其实此接口已上线）
     *
     * @param string $token
     * @param string $projectName
     * @param string $jobId
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws Exception
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
        if (isset($result['Response']['Error']['Message'])) {
            throw new Exception($result['Response']['Error']['Message']);
        }
        return $result['Response']['Data'];
    }

    public function getImportJobStatusWithRetry(string $token, string $projectName, string $jobId, int $retry = 10)
    {
        $waitingTimes = 0;
        while (true) {
            // HACK 如果上传成功立即查询，会报错：invoke function
            sleep(1);
            try {
                $jobStatus = $this->getImportJobStatus($token, $projectName, $jobId);
                if (in_array($jobStatus['Status'], ['wait_process', 'processing']) && $waitingTimes < $retry) {
                    $waitingTimes++;
                    continue;
                }
                return $jobStatus;
            } catch (Exception $e) {
                if ($waitingTimes < 10) {
                    $waitingTimes++;
                    continue;
                }
                throw $e;
            }
            break;
        }
    }
    public function createWikiByUploadZip(string $token, string $projectName, string $zipFileFullPath, int $parentId)
    {
        $zipFilename = basename($zipFileFullPath);
        $uploadToken = $this->createUploadToken(
            $token,
            $projectName,
            $zipFilename
        );
        $this->upload($uploadToken, $zipFileFullPath);
        return $this->createWikiByZip($token, $projectName, $uploadToken, [
            'ParentIid' => $parentId,
            'FileName' => $zipFilename,
        ]);
    }

    public function getWiki(string $token, string $projectName, int $id, int $version = 1)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'DescribeWiki',
                'ProjectName' => $projectName,
                'Iid' => $id,
                'VersionId' => $version,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['Data'];
    }

    public function updateTitle(string $token, string $projectName, int $id, string $title): bool
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'ModifyWikiTitle',
                'ProjectName' => $projectName,
                'Iid' => $id,
                'Title' => $title,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['Data']['Title'] == $title;
    }

    public function replaceAttachments(string $markdown, array $codingAttachments): string
    {
        if (empty($codingAttachments)) {
            return $markdown;
        }
        $markdown .= "\n\nAttachments\n---\n\n";
        foreach ($codingAttachments as $attachmentPath => $codingAttachment) {
            $resourceCode = $codingAttachment['ResourceCode'] ?? 0;
            $filename = $codingAttachment['FileName'] ?? '此文件迁移失败';
            $markdown .= "-   #${resourceCode} ${filename}\n";
            $markdown = preg_replace(
                "|\[.*\]\(${attachmentPath}\)|",
                " #${resourceCode} `${filename}`",
                $markdown
            );
        }
        return $markdown;
    }
}

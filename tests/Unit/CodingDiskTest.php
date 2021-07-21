<?php

namespace Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;
use App\Coding\Disk;

class CodingDiskTest extends TestCase
{
    public static array $uploadToken = [
        'AuthToken' => '65e5968b5e17d5aaa3f5d33200aca2d1911fe2ad2948b47d899d46e6da1e4',
        'Provide' => 'TENCENT',
        'SecretId' => 'AKIDU-VqQm39vRar-ZrHj1UIE5u2gYJ7gWFcG2ThwFNO9eU1HbyQlZp8vVcQ99',
        'SecretKey' => 'clUYSNeg2es16EILsrF6RyCD3ss6uFLX3Xgc=',
        'UploadLink' => 'https://coding-net-dev-file-123456.cos.ap-shanghai.myqcloud.com',
        'UpToken' => 'EOlMEc2x0xbrFoL9CMy7OqDl5413654938410a360a63207e30dab4655pMKmNJ3t5M-Z8bGt',
        'StorageKey' => 'b5d0d8e0-3aca-11eb-8673-a9b6d94ca755.zip',
        'Time' => 1625579588693,
        'Bucket' => 'coding-net-dev-file-123456',
        'AppId' => '123456',
        'Region' => 'ap-shanghai',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $codingToken = $this->faker->md5;
        config(['coding.token' => $codingToken]);
        $codingTeamDomain = $this->faker->domainWord;
        config(['coding.team_domain' => $codingTeamDomain]);
        $codingProjectUri = $this->faker->slug;
        config(['coding.project_uri' => $codingProjectUri]);
    }

    public function testCreateFolder()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/CreateFolderResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $folderName = 'foo';
        $parentId = $this->faker->randomNumber();

        $clientMock = $this->getMockBuilder(Client::class)->getMock();
        $clientMock->expects($this->exactly(2))
            ->method('request')
            ->with(
                'POST',
                'https://e.coding.net/open-api',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => "token ${codingToken}",
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'Action' => 'CreateFolder',
                        'ProjectName' => $codingProjectUri,
                        'FolderName' => $folderName,
                        'ParentId' => $parentId,
                    ],
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Disk($clientMock);
        $result = $coding->createFolder($codingToken, $codingProjectUri, $folderName, $parentId);
        $this->assertTrue(is_numeric($result));

        $result = $coding->createFolder($codingToken, $codingProjectUri, $folderName, $parentId);
        $this->assertTrue(is_numeric($result));
    }

    public function testCreateFile()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/CreateFileResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $data = [
            "OriginalFileName" => "foo.pdf",
            "MimeType" => "application/pdf",
            "FileSize" => 123456,
            "StorageKey" => "b5d0d8e0-3aca-11eb-8673-a9b6d94ca755.pdf",
            "Time" => 1625579588693,
            "AuthToken" => "65e5968b5e17d5aaa3f5d33200aca2d1911fe2ad2948b47d899d46e6da1e4",
            "FolderId" => 24515861,
        ];

        $clientMock = $this->getMockBuilder(Client::class)->getMock();
        $clientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://e.coding.net/open-api',
                [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => "token ${codingToken}",
                        'Content-Type' => 'application/json'
                    ],
                    'json' => array_merge([
                        'Action' => 'CreateFile',
                        'ProjectName' => $codingProjectUri,
                    ], $data)
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Disk($clientMock);
        $result = $coding->createFile($codingToken, $codingProjectUri, $data);
        $this->assertArrayHasKey('FileId', $result);
    }
}

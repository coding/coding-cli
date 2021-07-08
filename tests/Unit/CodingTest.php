<?php

namespace Tests\Unit;

use App\Coding;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CodingTest extends TestCase
{
    public function testCreateWiki()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/createWikiResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $article = [
            'Title' => $this->faker->title,
            'Content' => $this->faker->sentence,
            'ParentIid' => $this->faker->randomNumber(),
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
                        'Action' => 'CreateWiki',
                        'ProjectName' => $codingProjectUri,
                    ], $article)
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Coding($clientMock);
        $result = $coding->createWiki($codingToken, $codingProjectUri, $article);
        $this->assertEquals(json_decode($responseBody, true)['Response']['Data'], $result);
    }

    public function testCreateUploadToken()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/createUploadTokenResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $fileName = $this->faker->word;

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
                    'json' => [
                        'Action' => 'CreateUploadToken',
                        'ProjectName' => $codingProjectUri,
                        'FileName' => $fileName,
                    ],
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Coding($clientMock);
        $result = $coding->createUploadToken($codingToken, $codingProjectUri, $fileName);
        $this->assertEquals([
            'AuthToken' => '65e5968b5e17d5aaa3f5d33200aca2d1911fe2ad2948b47d899d46e6da1e4',
            'Provide' => 'TENCENT',
            'SecretId' => 'AKIDU-VqQm39vRar-ZrHj1UIE5u2gYJ7gWFcG2ThwFNO9eU1HbyQlZp8vVcQ99',
            'SecretKey' => 'clUYSNeg2es16EILsrF6RyCD3ss6uFLX3Xgc=',
            'UploadLink' => 'https://coding-net-dev-file-123456.cos.ap-shanghai.myqcloud.com',
            'UpToken' => 'EOlMEc2x0xbrFoL9CMy7OqDl5413654938410a360a63207e30dab4655pMKmNJ3t5M-Z8bGt',
            'Time' => 1625579588693,
            'Bucket' => 'coding-net-dev-file-123456',
            'AppId' => '123456',
            'Region' => 'ap-shanghai',
        ], $result);
    }

    public function testCreateMarkdownZip()
    {
        $path = $this->dataDir . 'confluence/space1/';
        $filename = 'image-demo_65619.md';
        $markdown = file_get_contents($path . $filename);
        $coding = new Coding();
        $zipFile = $coding->createMarkdownZip($markdown, $path, $filename);

        $this->assertTrue(file_exists($zipFile));
        $zip = new \ZipArchive();
        $zip->open($zipFile);
        $this->assertEquals(3, $zip->numFiles);
        $this->assertEquals('image-demo_65619.md', $zip->getNameIndex(0));
        $this->assertEquals('attachments/65619/65624.png', $zip->getNameIndex(1));
        $this->assertEquals('attachments/65619/65623.png', $zip->getNameIndex(2));
    }

    public function testUpload()
    {
        $uploadToken = [
            'SecretId' => 'AKIDU-VqQm39vRar-ZrHj1UIE5u2gYJ7gWFcG2ThwFNO9eU1HbyQlZp8vVcQ99',
            'SecretKey' => 'clUYSNeg2es16EILsrF6RyCD3ss6uFLX3Xgc=',
            'Bucket' => 'coding-net-dev-file-123456',
            'AppId' => '123456',
            'Region' => 'ap-shanghai',
        ];
        $file = $this->faker->filePath();
        Storage::fake('cos');

        $coding = new Coding();
        $coding->upload($uploadToken, $file);

        Storage::disk('cos')->assertExists(basename($file));
    }
}

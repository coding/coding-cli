<?php

namespace Tests\Unit;

use App\Coding;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
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
                        'Action' => 'CreateWiki',
                        'ProjectName' => $codingProjectUri,
                        'FileName' => $fileName,
                    ],
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Coding($clientMock);
        $result = $coding->createUploadToken($codingToken, $codingProjectUri, $fileName);
        $this->assertEquals(json_decode($responseBody, true)['Response']['Token'], $result);
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
}

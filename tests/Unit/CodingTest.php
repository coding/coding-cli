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
}

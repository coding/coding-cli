<?php

namespace Tests\Unit;

use App\Coding\Issue;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class CodingIssueTest extends TestCase
{
    public function testCreate()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/CreateIssueResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $data = [
            'Type' => 'REQUIREMENT',
            'Name' => $this->faker->title,
            'Priority' => $this->faker->randomElement([0, 1, 2, 3]),
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
                        'Action' => 'CreateIssue',
                        'ProjectName' => $codingProjectUri,
                    ], $data)
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Issue($clientMock);
        $result = $coding->create($codingToken, $codingProjectUri, $data);
        $this->assertEquals(json_decode($responseBody, true)['Response']['Issue'], $result);
    }
}

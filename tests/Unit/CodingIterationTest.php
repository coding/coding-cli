<?php

namespace Tests\Unit;

use App\Coding\Issue;
use App\Coding\Iteration;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class CodingIterationTest extends TestCase
{
    public function testCreateSuccess()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/CreateIterationResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;
        $data = [
            'Name' => $this->faker->title,
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
                        'Action' => 'CreateIteration',
                        'ProjectName' => $codingProjectUri,
                    ], $data)
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new Iteration($clientMock);
        $result = $coding->create($codingToken, $codingProjectUri, $data);
        $this->assertEquals(json_decode($responseBody, true)['Response']['Iteration'], $result);
    }

    public function testGenerateName()
    {
        $startAt = Carbon::parse('2021-10-20');
        $endAt = Carbon::parse('2021-10-30');
        $result = Iteration::generateName($startAt, $endAt);
        $this->assertEquals("2021/10/20-10/30 迭代", $result);

        $startAt = Carbon::parse('2021-12-27');
        $endAt = Carbon::parse('2022-01-07');
        $result = Iteration::generateName($startAt, $endAt);
        $this->assertEquals("2021/12/27-2022/01/07 迭代", $result);
    }
}

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
        $responseBody = '{
           "Response" : {
              "Data" : {
                 "CanMaintain" : true,
                 "CanRead" : true,
                 "Content" : "foo foo",
                 "CreatedAt" : 1625214079010,
                 "Creator" : {
                    "Avatar" : "https://coding-net-production-static-ci.codehub.cn/2cb665a3-aa00-2b6df3e33edc.jpg",
                    "Email" : "",
                    "GlobalKey" : "KMRnIKgzbV",
                    "Id" : 183478,
                    "Name" : "sinkcup",
                    "Phone" : "",
                    "RequestId" : "",
                    "Status" : "ACTIVE",
                    "TeamId" : 0
                 },
                 "CreatorId" : 0,
                 "CurrentUserRoleId" : 0,
                 "CurrentVersion" : 1,
                 "Editor" : {
                    "Avatar" : "https://coding-net-production-static-ci.codehub.cn/2cb665a3--aa00-2b6df3e33edc.jpg",
                    "Email" : "",
                    "GlobalKey" : "KMRnIKgzbV",
                    "Id" : 183478,
                    "Name" : "sinkcup",
                    "Phone" : "",
                    "RequestId" : "",
                    "Status" : "ACTIVE",
                    "TeamId" : 0
                 },
                 "EditorId" : 0,
                 "HistoriesCount" : 1,
                 "HistoryId" : 2707176,
                 "Html" : "<p>foo foo</p>",
                 "Id" : 1325288,
                 "Iid" : 27,
                 "LastVersion" : 1,
                 "Msg" : "",
                 "Order" : 2,
                 "ParentIid" : 0,
                 "ParentShared" : false,
                 "ParentVisibleRange" : "PUBLIC",
                 "Path" : "27",
                 "Title" : "foo by curl",
                 "UpdatedAt" : 1625214079014,
                 "VisibleRange" : "INHERIT"
              },
              "RequestId" : "a50c8805-8e1f-fc4d-f965-855e5a3cf709"
           }
        }';
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
        $this->assertEquals(json_decode($responseBody, true), $result);
    }
}

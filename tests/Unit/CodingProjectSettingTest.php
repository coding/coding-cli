<?php

namespace Tests\Unit;

use App\Coding\ProjectSetting;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class CodingProjectSettingTest extends TestCase
{
    public function testGetIssueTypesSuccess()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/DescribeProjectIssueTypeListResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;

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
                        'Action' => 'DescribeProjectIssueTypeList',
                        'ProjectName' => $codingProjectUri,
                    ])
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new ProjectSetting($clientMock);
        $result = $coding->getIssueTypes($codingToken, $codingProjectUri);
        $this->assertEquals(json_decode($responseBody, true)['Response']['IssueTypes'], $result);
    }

    public function testGetIssueTypeStatusSuccess()
    {
        $responseBody = file_get_contents($this->dataDir . 'coding/DescribeProjectIssueStatusListResponse.json');
        $codingToken = $this->faker->md5;
        $codingProjectUri = $this->faker->slug;

        $issueType = $this->faker->randomElement(['DEFECT', 'REQUIREMENT', 'MISSION', 'EPIC', 'SUB_TASK']);
        $issueTypeId = $this->faker->randomNumber();
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
                        'Action' => 'DescribeProjectIssueStatusList',
                        'ProjectName' => $codingProjectUri,
                        'IssueType' => $issueType,
                        'IssueTypeId' => $issueTypeId,
                    ])
                ]
            )
            ->willReturn(new Response(200, [], $responseBody));
        $coding = new ProjectSetting($clientMock);
        $result = $coding->getIssueTypeStatus($codingToken, $codingProjectUri, $issueType, $issueTypeId);
        $this->assertEquals(json_decode($responseBody, true)['Response']['ProjectIssueStatusList'], $result);
    }
}

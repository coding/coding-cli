<?php

namespace App\Coding;

class ProjectSetting extends Base
{
    public function getIssueTypes($token, $projectName)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'DescribeProjectIssueTypeList',
                'ProjectName' => $projectName,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['IssueTypes'];
    }

    public function getIssueTypeStatus(string $token, string $projectName, string $issueType, int $issueTypeId)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'Action' => 'DescribeProjectIssueStatusList',
                'ProjectName' => $projectName,
                'IssueType' => $issueType,
                'IssueTypeId' => $issueTypeId,
            ],
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['ProjectIssueStatusList'];
    }
}
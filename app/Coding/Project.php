<?php

namespace App\Coding;

use Exception;

class Project extends Base
{
    public function getIssueTypes($token, $projectName)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => array_merge([
                'Action' => 'DescribeProjectIssueTypeList',
                'ProjectName' => $projectName,
            ]),
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['IssueTypes'];
    }
}

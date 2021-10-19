<?php

namespace App\Coding;

use Exception;

class Issue extends Base
{
    public function create($token, $projectName, $data)
    {
        $response = $this->client->request('POST', 'https://e.coding.net/open-api', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "token ${token}",
                'Content-Type' => 'application/json'
            ],
            'json' => array_merge([
                'Action' => 'CreateIssue',
                'ProjectName' => $projectName,
            ], $data),
        ]);
        $result = json_decode($response->getBody(), true);
        if (isset($result['Response']['Error']['Message'])) {
            throw new Exception($result['Response']['Error']['Message']);
        }
        return $result['Response']['Issue'];
    }
}

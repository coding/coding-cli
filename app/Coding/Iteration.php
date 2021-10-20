<?php

namespace App\Coding;

class Iteration extends Base
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
                'Action' => 'CreateIteration',
                'ProjectName' => $projectName,
            ], $data),
        ]);
        $result = json_decode($response->getBody(), true);
        return $result['Response']['Iteration'];
    }
}

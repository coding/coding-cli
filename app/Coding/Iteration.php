<?php

namespace App\Coding;

use Carbon\Carbon;
use Exception;

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
        if (isset($result['Response']['Error']['Message'])) {
            throw new Exception($result['Response']['Error']['Message']);
        }
        return $result['Response']['Iteration'];
    }

    public static function generateName(Carbon $startAt, Carbon $endAt): string
    {
        $endFormat = $startAt->year == $endAt->year ? 'm/d' : 'Y/m/d';
        return $startAt->format('Y/m/d') . '-' . $endAt->format($endFormat) . ' 迭代';
    }
}

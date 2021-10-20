<?php

namespace App\Coding;

use Carbon\Carbon;

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

    public static function generateName(Carbon $startAt, Carbon $endAt): string
    {
        $endFormat = $startAt->year == $endAt->year ? 'm/d' : 'Y/m/d';
        return $startAt->format('Y/m/d') . '-' . $endAt->format($endFormat) . ' 迭代';
    }
}

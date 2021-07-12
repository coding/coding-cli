<?php

namespace App\Commands;

use App\Coding;

trait WithCoding
{
    protected string $codingProjectUri;
    protected string $codingTeamDomain;
    protected string $codingToken;
    protected Coding $coding;

    private function setCodingApi(): void
    {
        $codingTeamDomain = $this->option('coding_team_domain');
        if (is_null($codingTeamDomain)) {
            $codingTeamDomain = config('coding.team_domain') ?? $this->ask('CODING 团队域名：');
        }
        $this->codingTeamDomain = str_replace('.coding.net', '', $codingTeamDomain);

        $this->codingProjectUri = $this->option('coding_project_uri');
        if (is_null($this->codingProjectUri)) {
            $this->codingProjectUri = config('coding.project_uri') ?? $this->ask('CODING 项目标识：');
        }

        $this->codingToken = $this->option('coding_token');
        if (is_null($this->codingToken)) {
            $this->codingToken = config('coding.token') ?? $this->ask('CODING Token：');
        }
    }
}

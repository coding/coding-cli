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
        if ($this->option('coding_team_domain')) {
            $codingTeamDomain = $this->option('coding_team_domain');
        } else {
            $codingTeamDomain = config('coding.team_domain') ?? $this->ask('CODING 团队域名：');
        }
        $this->codingTeamDomain = str_replace('.coding.net', '', $codingTeamDomain);

        if ($this->option('coding_project_uri')) {
            $this->codingProjectUri = $this->option('coding_project_uri');
        } else {
            $this->codingProjectUri = config('coding.project_uri') ?? $this->ask('CODING 项目标识：');
        }

        if ($this->option('coding_token')) {
            $this->codingToken = $this->option('coding_token');
        } else {
            $this->codingToken = config('coding.token') ?? $this->ask('CODING Token：');
        }
    }
}

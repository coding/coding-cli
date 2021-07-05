# CODING cli

[![CI](https://github.com/Coding/coding-cli/actions/workflows/ci.yml/badge.svg?branch=php)](https://github.com/Coding/coding-cli/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/Coding/coding-cli/branch/php/graph/badge.svg?token=Su2WCy3Yfg)](https://codecov.io/gh/Coding/coding-cli)
[![docker hub](https://img.shields.io/docker/automated/ecoding/coding-cli)](https://hub.docker.com/r/ecoding/coding-cli)

CODING cli 基于 [Laravel Zero](https://laravel-zero.com/)。

## Docker 方式运行

```shell
docker run -t ecoding/coding-cli
docker run -t ecoding/coding-cli wiki:import --help
docker run -t --env CODING_IMPORT_PROVIDER=Confluence --env CONFLUENCE_USERNAME=admin ecoding/coding-cli wiki:import
```

![docker run coding cli](https://user-images.githubusercontent.com/4971414/124300830-8e5afa80-db91-11eb-8032-fc6e7f7f063d.png)

## 非 Docker 方式运行

要求：PHP 8.0 或更高版本

访问「[CODING 公共制品库](https://coding-public.coding.net/public-artifacts/public/downloads/coding.phar/version/6352163/list)」，下载后在命令行中执行。

在 Linux/macOS 中，建议重命名，并放到系统目录：

```shell
chmod +x coding.phar
sudo mv coding.phar /usr/local/bin/coding
coding list
```

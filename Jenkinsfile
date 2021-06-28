pipeline {
  agent {
    docker {
      reuseNode 'true'
      registryUrl 'https://coding-public-docker.pkg.coding.net'
      image 'public/docker/php:8.0'
    }
  }
  stages {
    stage('检出') {
    steps {
      checkout([
        $class: 'GitSCM',
        branches: [[name: GIT_BUILD_REF]],
        userRemoteConfigs: [[
          url: GIT_REPO_URL,
          credentialsId: CREDENTIALS_ID
        ]]])
      }
    }
    stage('打包') {
      steps {
        script {
          if (env.TAG_NAME ==~ /.*/ ) {
            BUILD_VERSION = "${env.TAG_NAME}"
          } else if (env.MR_SOURCE_BRANCH ==~ /.*/ ) {
            BUILD_VERSION = "dev-${env.MR_RESOURCE_ID}-${env.GIT_COMMIT_SHORT}"
          } else {
            BUILD_VERSION = "dev-${env.BRANCH_NAME.replace('/', '-')}-${env.GIT_COMMIT_SHORT}"
          }
        }

        sh 'composer install'
        sh "php coding app:build --build-version=${BUILD_VERSION}"
      }
    }
    stage('上传到制品库') {
      steps {
        sh 'mv builds/coding builds/coding.phar'
        dir ('builds') {
          codingArtifactsGeneric(files: 'coding.phar', repoName: 'downloads', version: "${BUILD_VERSION}")
        }
      }
    }
  }
}

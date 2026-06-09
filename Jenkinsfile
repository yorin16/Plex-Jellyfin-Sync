pipeline {
    agent any

    environment {
        APP_DIR = '/mnt/user/appdata/media-sync'
        REPO_URL = 'https://github.com/yorin16/Plex-Jellyfin-Sync.git'
    }

    stages {
        stage('Checkout') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'github-token',
                    usernameVariable: 'GIT_USER',
                    passwordVariable: 'GIT_TOKEN'
                )]) {
                    sh """
                        if [ -d "${APP_DIR}/.git" ]; then
                            git -C ${APP_DIR} pull https://${GIT_USER}:${GIT_TOKEN}@github.com/yorin16/Plex-Jellyfin-Sync.git main
                        else
                            mkdir -p ${APP_DIR}
                            git clone https://${GIT_USER}:${GIT_TOKEN}@github.com/yorin16/Plex-Jellyfin-Sync.git ${APP_DIR}
                        fi
                    """
                }
            }
        }

        stage('Deploy') {
            steps {
                sh "docker compose -f ${APP_DIR}/docker-compose.yml up --build -d"
            }
        }

        stage('Health Check') {
            steps {
                script {
                    sleep(15)
                    sh "curl -fs http://localhost:8085/api/dashboard || exit 1"
                    echo '✓ App is up'
                }
            }
        }
    }

    post {
        success { echo '✓ Deployment complete' }
        failure { echo '✗ Deployment failed' }
    }
}

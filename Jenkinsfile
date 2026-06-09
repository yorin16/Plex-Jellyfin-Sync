pipeline {
    agent any

    environment {
        // Unraid: app files live under appdata, not /opt
        APP_DIR = '/mnt/user/appdata/media-sync'
    }

    stages {
        stage('Checkout') {
            steps {
                sh """
                    if [ -d "${APP_DIR}/.git" ]; then
                        git -C ${APP_DIR} pull origin main
                    else
                        git clone https://github.com/yorin/Plex-Jellyfin-Sync.git ${APP_DIR}
                    fi
                """
            }
        }

        stage('Build & Deploy') {
            steps {
                dir("${APP_DIR}") {
                    // Docker builds everything — Vue (npm inside container), PHP image.
                    // No Node.js or Composer needed on this machine.
                    sh 'docker compose up --build -d'
                }
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

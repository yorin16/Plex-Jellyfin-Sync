pipeline {
    agent any

    stages {
        stage('Deploy') {
            steps {
                // Jenkins already checked out the repo to ${WORKSPACE} — just deploy from there
                sh "docker compose -f ${WORKSPACE}/docker-compose.yml --project-directory ${WORKSPACE} up --build -d"
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

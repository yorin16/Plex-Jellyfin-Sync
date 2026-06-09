pipeline {
    agent any

    stages {
        stage('Deploy') {
            steps {
                withCredentials([string(credentialsId: 'portainer-webhook', variable: 'WEBHOOK_URL')]) {
                    sh 'curl -X POST "$WEBHOOK_URL"'
                }
            }
        }
    }

    post {
        success { echo '✓ Deployment triggered — Portainer is pulling and redeploying' }
        failure { echo '✗ Failed to trigger deployment' }
    }
}

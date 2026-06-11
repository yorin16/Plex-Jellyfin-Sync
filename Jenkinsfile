pipeline {
    agent any

    environment {
        PORTAINER_URL    = 'http://10.0.0.208:9000'
        PORTAINER_ENDPOINT = '1'
        STACK_NAME       = 'plex-jellyfin-sync'  // match the stack name in Portainer exactly
    }

    stages {
        stage('Deploy') {
            steps {
                withCredentials([string(credentialsId: 'portainer-webhook', variable: 'WEBHOOK_URL')]) {
                    sh 'curl -X POST "$WEBHOOK_URL"'
                }
                echo 'Webhook triggered — Portainer is pulling and redeploying...'
            }
        }

        stage('Wait for containers') {
            steps {
                withCredentials([string(credentialsId: 'portainer-api-token', variable: 'PORTAINER_TOKEN')]) {
                    timeout(time: 10, unit: 'MINUTES') {
                        sh '''
                            FILTER="%7B%22label%22%3A%5B%22com.docker.compose.project%3D${STACK_NAME}%22%5D%7D"
                            URL="${PORTAINER_URL}/api/endpoints/${PORTAINER_ENDPOINT}/docker/containers/json?all=1&filters=${FILTER}"
                            TIMEOUT=600
                            ELAPSED=0
                            INTERVAL=10

                            echo "Polling Portainer for stack: $STACK_NAME"
                            sleep 8

                            while [ "$ELAPSED" -lt "$TIMEOUT" ]; do
                                RESULT=$(curl -sf -H "X-API-Key: $PORTAINER_TOKEN" "$URL")
                                if [ -z "$RESULT" ] || [ "$RESULT" = "null" ]; then
                                    echo "[${ELAPSED}s] Could not reach Portainer API"
                                else
                                    TOTAL=$(echo "$RESULT" | grep -o '"State":"[^"]*"' | wc -l | tr -d ' ')
                                    RUNNING=$(echo "$RESULT" | grep -o '"State":"running"' | wc -l | tr -d ' ')
                                    echo "[${ELAPSED}s] ${RUNNING}/${TOTAL} running"
                                    if [ "$TOTAL" -gt 0 ] && [ "$RUNNING" -eq "$TOTAL" ]; then
                                        echo "All containers are running."
                                        exit 0
                                    fi
                                fi
                                sleep "$INTERVAL"
                                ELAPSED=$((ELAPSED + INTERVAL))
                            done

                            echo "Timed out waiting for containers to start."
                            exit 1
                        '''
                    }
                }
            }
        }
    }

    post {
        success { echo '✓ All containers running' }
        failure { echo '✗ Deployment failed or timed out' }
    }
}

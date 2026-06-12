pipeline {
    agent any

    environment {
        PORTAINER_URL = 'http://10.0.0.208:9001'
        STACK_NAME    = 'media-sync'
    }

    stages {
        stage('Build') {
            steps {
                // Rebuild all images from the updated source. Docker's layer cache
                // means only changed layers are rebuilt — COPY backend/ invalidates
                // automatically whenever any PHP/config file changes.
                sh 'docker compose build'
            }
        }

        stage('Deploy') {
            steps {
                // Restart containers with the newly built images.
                sh 'docker compose up -d'
                echo 'Containers started with updated images.'
            }
        }

        stage('Verify') {
            steps {
                withCredentials([string(credentialsId: 'portainer-api-token', variable: 'PORTAINER_TOKEN')]) {
                    timeout(time: 10, unit: 'MINUTES') {
                        sh '''
                            ENDPOINT_ID=$(curl -sf -H "X-API-Key: $PORTAINER_TOKEN" \
                                "${PORTAINER_URL}/api/endpoints" | grep -o '"Id":[0-9]*' | head -1 | grep -o '[0-9]*')
                            if [ -z "$ENDPOINT_ID" ]; then
                                echo "Failed to discover Portainer endpoint ID"
                                exit 1
                            fi
                            echo "Using Portainer endpoint ID: $ENDPOINT_ID"

                            FILTER="%7B%22label%22%3A%5B%22com.docker.compose.project%3D${STACK_NAME}%22%5D%7D"
                            URL="${PORTAINER_URL}/api/endpoints/${ENDPOINT_ID}/docker/containers/json?all=1&filters=${FILTER}"
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

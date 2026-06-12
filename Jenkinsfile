pipeline {
    agent any

    environment {
        STACK_NAME = 'media-sync'
    }

    stages {
        stage('Build') {
            steps {
                // docker compose plugin is not inside the Jenkins container; use the
                // standalone binary installed in the persistent Jenkins home volume.
                // .env is gitignored — copy from the stable host path before building.
                sh '''
                    export PATH="$PATH:/var/jenkins_home/bin"
                    cp /var/jenkins_home/.env .env
                    docker-compose -p media-sync build
                '''
            }
        }

        stage('Deploy') {
            steps {
                sh '''
                    export PATH="$PATH:/var/jenkins_home/bin"
                    cp /var/jenkins_home/.env .env
                    docker-compose -p media-sync up -d --force-recreate
                '''
                echo 'Containers started with updated images.'
            }
        }

        stage('Verify') {
            steps {
                timeout(time: 10, unit: 'MINUTES') {
                    sh '''
                        export PATH="$PATH:/var/jenkins_home/bin"
                        TIMEOUT=600
                        ELAPSED=0
                        INTERVAL=10

                        sleep 8

                        while [ "$ELAPSED" -lt "$TIMEOUT" ]; do
                            TOTAL=$(docker ps -a --filter "label=com.docker.compose.project=${STACK_NAME}" --format '{{.Status}}' | wc -l | tr -d ' ')
                            RUNNING=$(docker ps --filter "label=com.docker.compose.project=${STACK_NAME}" --filter "status=running" --format '{{.Status}}' | wc -l | tr -d ' ')
                            echo "[${ELAPSED}s] ${RUNNING}/${TOTAL} running"
                            if [ "$TOTAL" -gt 0 ] && [ "$RUNNING" -eq "$TOTAL" ]; then
                                echo "All containers are running."
                                exit 0
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

    post {
        success { echo '✓ All containers running' }
        failure { echo '✗ Deployment failed or timed out' }
    }
}

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
                        sh '''python3 << 'PYEOF'
import json, urllib.request, urllib.parse, os, sys, time

portainer_url = os.environ["PORTAINER_URL"]
endpoint      = os.environ["PORTAINER_ENDPOINT"]
stack_name    = os.environ["STACK_NAME"]
token         = os.environ["PORTAINER_TOKEN"]

filters = json.dumps({"label": ["com.docker.compose.project=" + stack_name]})
url = (portainer_url + "/api/endpoints/" + endpoint
       + "/docker/containers/json?all=1&filters=" + urllib.parse.quote(filters))
headers = {"X-API-Key": token}

print("Polling Portainer for stack: " + stack_name, flush=True)
time.sleep(8)  # give Portainer a moment to start pulling

timeout  = 600
elapsed  = 0
interval = 10

while elapsed < timeout:
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req, timeout=10) as resp:
            containers = json.loads(resp.read())
        total   = len(containers)
        running = sum(1 for c in containers if c.get("State") == "running")
        states  = ", ".join(
            (c.get("Names", ["?"])[0].lstrip("/") + " [" + c.get("State", "?") + "]")
            for c in containers
        )
        print("[{}s] {}/{} running — {}".format(elapsed, running, total, states), flush=True)
        if total > 0 and running == total:
            print("All containers are running.", flush=True)
            sys.exit(0)
    except Exception as e:
        print("[{}s] API error: {}".format(elapsed, e), flush=True)
    time.sleep(interval)
    elapsed += interval

print("Timed out waiting for containers to start.", flush=True)
sys.exit(1)
PYEOF
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

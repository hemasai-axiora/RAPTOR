import urllib.request
import json

req = urllib.request.Request(
    "https://api.github.com/repos/hemasai-axiora/RAPTOR/actions/runs?per_page=5",
    headers={"Accept": "application/vnd.github.v3+json", "User-Agent": "Mozilla/5.0"}
)
r = urllib.request.urlopen(req)
data = json.loads(r.read().decode())
for run in data.get('workflow_runs', []):
    print(f"Run {run['id']}: {run['status']} - {run['conclusion']} - {run['name'][:40]} ({run['created_at']})")
    print(f"  Head commit: {run.get('head_commit', {}).get('message', '')[:60]}")

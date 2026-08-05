import urllib.request
import json
import sys

data_bytes = sys.stdin.buffer.read()
data = json.loads(data_bytes)
for job in data.get('jobs', []):
    print(f"Job: {job['name']} - {job['status']} - {job['conclusion']}")
    for step in job.get('steps', []):
        print(f"  Step: {step['name']} - {step['conclusion']}")

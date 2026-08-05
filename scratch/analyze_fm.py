import re
import sys
sys.stdout.reconfigure(encoding='utf-8')

with open('scratch/fm_full.html', encoding='utf-8') as f:
    html = f.read()

# The HTML is minified/obfuscated, look for API calls differently
# Search for any string containing 'action'
action_values = re.findall(r'action["\s:=]+(["\'])([^"\']+)\1', html)
print('Action values:', action_values[:20])

# Look for FormData patterns
formdata = re.findall(r'FormData[^;]+;', html)
print('FormData:', formdata[:10])

# Look for XMLHttpRequest or fetch
xhr_patterns = re.findall(r'\.open\([^)]+\)', html)
print('XHR open:', xhr_patterns[:10])

# Find the upload related JS
upload_regions = []
for match in re.finditer(r'upload', html, re.IGNORECASE):
    start = max(0, match.start() - 50)
    end = min(len(html), match.end() + 200)
    region = html[start:end].replace('\n', ' ').strip()
    if region not in upload_regions:
        upload_regions.append(region)

print('\nUpload regions (first 5):')
for r in upload_regions[:5]:
    print(r[:200])
    print('---')

# Look for the new file creation
for keyword in ['newFile', 'createFile', 'new_file', 'savefile', 'editfile']:
    idx = html.lower().find(keyword.lower())
    if idx > -1:
        print(f'\nFound "{keyword}" at {idx}:')
        print(html[max(0,idx-100):idx+400].replace('\n',' ')[:400])

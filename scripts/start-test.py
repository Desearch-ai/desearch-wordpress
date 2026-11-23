#!/usr/bin/env python3
"""Create only this task's disposable WordPress runtime."""
import os
import secrets
import subprocess
import sys
import time
from pathlib import Path

root = Path(__file__).resolve().parents[1]
runtime = os.environ.get('DESEARCH_TEST_RUNTIME', 'current')
images = {'current': 'wordpress:7.1.4-php8.3-apache', 'minimum': 'wordpress:php7.4'}
if runtime not in images:
    raise SystemExit('DESEARCH_TEST_RUNTIME must be current or minimum')
def run(args):
    result = subprocess.run(args, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError('Docker/WP-CLI setup failed; inspect the task containers.')
    return result.stdout.strip()

for name in ('t1124-wp', 't1124-db'):
    if subprocess.run(['docker', 'container', 'inspect', name], capture_output=True).returncode == 0:
        raise SystemExit('Existing task container: ' + name + '. Reuse or remove explicitly.')
password = secrets.token_urlsafe(24)
run(['docker', 'network', 'create', 't1124-net'])
run(['docker', 'volume', 'create', 't1124-site'])
run(['docker', 'run', '-d', '--name', 't1124-db', '--network', 't1124-net', '-e', 'MARIADB_RANDOM_ROOT_PASSWORD=1', '-e', 'MARIADB_DATABASE=wordpress', '-e', 'MARIADB_USER=wordpress', '-e', 'MARIADB_PASSWORD='+password, 'mariadb:11.4'])
run(['docker', 'run', '-d', '--name', 't1124-wp', '--network', 't1124-net', '-p', '127.0.0.1:18824:80', '-v', 't1124-site:/var/www/html', '-e', 'WORDPRESS_DB_HOST=t1124-db', '-e', 'WORDPRESS_DB_USER=wordpress', '-e', 'WORDPRESS_DB_NAME=wordpress', '-e', 'WORDPRESS_DB_PASSWORD='+password, '-e', 'WORDPRESS_DEBUG=1', '-e', "WORDPRESS_CONFIG_EXTRA=define('WP_DEBUG_DISPLAY', false);", images[runtime]])
for attempt in range(30):
    if subprocess.run([sys.executable, str(root/'scripts/wp-test.py'), 'db', 'check'], capture_output=True).returncode == 0:
        break
    time.sleep(1)
else:
    raise SystemExit('Disposable database not ready in 30 seconds')
run([sys.executable, str(root/'scripts/wp-test.py'), 'core', 'install', '--url=http://127.0.0.1:18824', '--title=Desearch QA', '--admin_user=t1124-admin', '--admin_email=qa@example.invalid', '--skip-email', '--admin_password='+secrets.token_urlsafe(32)])
if runtime == 'minimum':
    run([sys.executable, str(root/'scripts/wp-test.py'), 'core', 'update', '--version=6.6.2', '--force'])
    run([sys.executable, str(root/'scripts/wp-test.py'), 'core', 'update-db'])
print('Disposable WordPress installed at http://127.0.0.1:18824; no credentials printed.')

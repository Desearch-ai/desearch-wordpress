#!/usr/bin/env python3
"""Run tests with the Apache container's PHP, including WordPress admin APIs."""
import os
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
test = sys.argv[1] if len(sys.argv) == 2 else ''
if test not in ('smoke.php', 'live.php', 'multisite.php'):
    raise SystemExit('Choose smoke.php, live.php, or multisite.php')
subprocess.run(['docker', 'cp', str(root/'tests'), 't1124-wp:/tmp/desearch-tests'], check=True)
args = ['docker', 'exec', '--user', 'www-data']
if 'DESEARCH_API_KEY' in os.environ:
    args += ['-e', 'DESEARCH_API_KEY']
code = '$_SERVER["HTTP_HOST"] = "127.0.0.1:18824"; $_SERVER["REMOTE_ADDR"] = "127.0.0.1"; $_SERVER["REQUEST_URI"] = "/wp-admin/tools.php?page=desearch"; $_SERVER["SCRIPT_FILENAME"] = "/var/www/html/wp-admin/tools.php"; $_SERVER["PHP_SELF"] = "/wp-admin/tools.php"; define("WP_ADMIN", true); require "/var/www/html/wp-load.php"; require "/var/www/html/wp-admin/includes/admin.php"; require $argv[1];'
args += ['t1124-wp', 'php', '-r', code, '/tmp/desearch-tests/'+test]
raise SystemExit(subprocess.call(args))

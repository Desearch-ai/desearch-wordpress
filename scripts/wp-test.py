#!/usr/bin/env python3
"""WP-CLI helper for the task's isolated Docker site; forwards key via environment only."""
import json
import os
from pathlib import Path
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
config = json.loads(subprocess.check_output(["docker", "inspect", "t1124-wp"]))[0]
environment = os.environ.copy()
args = ["docker", "run", "--rm", "-i", "--user", "33:33", "--network", "t1124-net", "--volumes-from", "t1124-wp", "-v", str(root) + ":/work:ro"]
for entry in config["Config"]["Env"]:
    name, _, value = entry.partition("=")
    if name.startswith("WORDPRESS_"):
        environment[name] = value
        args += ["-e", name]
if "DESEARCH_API_KEY" in environment:
    args += ["-e", "DESEARCH_API_KEY"]
args += ["wordpress:cli-php8.3", "wp"] + sys.argv[1:]
raise SystemExit(subprocess.call(args, env=environment))

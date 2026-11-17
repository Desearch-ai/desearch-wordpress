#!/usr/bin/env python3
"""Build a deterministic, source-only WordPress ZIP (no network or credentials)."""
import hashlib
from pathlib import Path
import zipfile

root = Path(__file__).resolve().parents[1]
version = "0.1.0"
output = root / "dist" / f"desearch-{version}.zip"
output.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sorted((root / "desearch").rglob("*")):
        if not source.is_file():
            continue
        info = zipfile.ZipInfo(source.relative_to(root).as_posix(), (2026, 11, 18, 0, 0, 0))
        info.compress_type = zipfile.ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
print(f"{hashlib.sha256(output.read_bytes()).hexdigest()}  {output.name}")

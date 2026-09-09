#!/usr/bin/env python3
"""Preserve original release bytes and observed proof without duplicating build trees."""
import hashlib
import json
import pathlib
import shutil
import sys

source, destination = map(pathlib.Path, sys.argv[1:])
if destination.exists():
    raise ValueError('Refusing to replace an evidence envelope.')
destination.mkdir(parents=True)
if source.is_dir():
    for item in source.iterdir():
        if item.is_symlink():
            raise ValueError('Evidence must not contain links.')
        if item.is_file():
            shutil.copyfile(item, destination/item.name)
        elif item.name in {'publisher-assets', 'build-evidence'}:
            target = destination/item.name
            target.mkdir()
            for file in item.iterdir():
                if file.is_symlink():
                    raise ValueError('Evidence must not contain links.')
                if file.is_file():
                    shutil.copyfile(file, target/file.name)
inventory = {str(p.relative_to(destination)): hashlib.sha256(p.read_bytes()).hexdigest()
             for p in sorted(destination.rglob('*')) if p.is_file()}
(destination/'evidence-files.json').write_text(json.dumps(inventory, indent=2) + '\n')

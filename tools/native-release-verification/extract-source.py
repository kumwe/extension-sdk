#!/usr/bin/env python3
"""Extract an original native source archive only after strict path and file checks."""
import pathlib
import sys
import tarfile


def extract(source, destination, prefix):
    destination = pathlib.Path(destination)
    if destination.exists():
        raise ValueError('Refusing to reuse a source extraction directory.')
    files = []
    names = set()
    total = 0
    with tarfile.open(source, 'r:gz') as archive:
        for member in archive.getmembers():
            name = member.name.rstrip('/')
            parts = pathlib.PurePosixPath(name).parts
            if (not name or name.startswith('/') or '\\' in name or '..' in parts
                    or name != pathlib.PurePosixPath(name).as_posix() or name in names
                    or any(ord(c) < 32 for c in name)):
                raise ValueError('Unsafe or duplicate native archive path: ' + name)
            names.add(name)
            if parts[0] != prefix or not (member.isdir() or member.isfile()):
                raise ValueError('Wrong root, link or special archive member: ' + name)
            if any(p in {'.git', 'node_modules', 'build', 'engine-build', 'modules', '.libs'} for p in parts):
                raise ValueError('Native archive contains build residue: ' + name)
            total += member.size
            if len(names) > 25000 or total > 300_000_000 or member.size > 50_000_000:
                raise ValueError('Native archive exceeds the extraction budget.')
            if member.isfile():
                data = archive.extractfile(member).read()
                if len(data) != member.size:
                    raise ValueError('Truncated native source member.')
                files.append((name, data, member.mode))
    if not files:
        raise ValueError('Empty native source archive.')
    destination.mkdir(parents=True)
    for name, data, mode in files:
        target = destination / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(data)
        target.chmod(0o755 if mode & 0o111 else 0o644)
    print(f'Extracted {len(files)} regular source files ({total} bytes); no links or special entries.')


if __name__ == '__main__':
    extract(*sys.argv[1:])

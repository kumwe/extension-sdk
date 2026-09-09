#!/usr/bin/env python3
import importlib.util
import io
import pathlib
import sys
import tarfile
import tempfile
import unittest

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('extract_source', pathlib.Path(__file__).with_name('extract-source.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class SourceArchiveTests(unittest.TestCase):
    def archive(self, directory, entries):
        source = pathlib.Path(directory, 'source.tar.gz')
        with tarfile.open(source, 'w:gz') as tar:
            for name, kind, value in entries:
                info = tarfile.TarInfo(name)
                info.type = kind
                info.mode = 0o755
                if kind == tarfile.REGTYPE:
                    info.size = len(value)
                    tar.addfile(info, io.BytesIO(value))
                else:
                    info.linkname = value
                    tar.addfile(info)
        return source

    def test_regular_source_and_executable_mode(self):
        with tempfile.TemporaryDirectory() as directory:
            source = self.archive(directory, [('kumwe-engine/tools/test.sh', tarfile.REGTYPE, b'#!/bin/sh\n')])
            destination = pathlib.Path(directory, 'unpacked')
            module.extract(source, destination, 'kumwe-engine')
            actual = destination/'kumwe-engine/tools/test.sh'
            self.assertEqual(actual.read_bytes(), b'#!/bin/sh\n')
            self.assertEqual(actual.stat().st_mode & 0o777, 0o755)
            with self.assertRaises(ValueError):
                module.extract(source, destination, 'kumwe-engine')

    def test_malicious_and_incomplete_archives(self):
        for entries in [
            [], [('other/file', tarfile.REGTYPE, b'x')],
            [('../file', tarfile.REGTYPE, b'x')], [('/kumwe-engine/file', tarfile.REGTYPE, b'x')],
            [('kumwe-engine/a/../file', tarfile.REGTYPE, b'x')],
            [('kumwe-engine/a\\file', tarfile.REGTYPE, b'x')],
            [('kumwe-engine/link', tarfile.SYMTYPE, '/etc/passwd')],
            [('kumwe-engine/link', tarfile.LNKTYPE, 'kumwe-engine/file')],
            [('kumwe-engine/fifo', tarfile.FIFOTYPE, '')],
            [('kumwe-engine/modules/stale.so', tarfile.REGTYPE, b'x')],
            [('kumwe-engine/file', tarfile.REGTYPE, b'x'), ('kumwe-engine/file', tarfile.REGTYPE, b'x')],
        ]:
            with self.subTest(entries=entries), tempfile.TemporaryDirectory() as directory:
                source = self.archive(directory, entries)
                destination = pathlib.Path(directory, 'unpacked')
                with self.assertRaises(ValueError):
                    module.extract(source, destination, 'kumwe-engine')
                self.assertFalse(destination.exists(), 'Rejected archive was partially extracted.')


if __name__ == '__main__':
    unittest.main()

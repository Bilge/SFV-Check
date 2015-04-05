#!/usr/bin/env python
import sys
import zlib
from pathlib import Path

if len(sys.argv) < 2:
    print('Usage:', sys.argv[0], '<sfv_file>', file=sys.stderr)

    sys.exit(1)

sfv_file = sys.argv[1]

try:
    sfv = open(sfv_file)
except PermissionError:
    print('Cannot read "%s".' % sfv_file, file=sys.stderr)

    sys.exit(1)

# Initialize counters.
ok = fail = miss = 0

for line in sfv:
    # Skip comments.
    if line[0] == ';': continue

    filename, _, crc = line.rstrip().rpartition(' ')

    if not (filename and crc): continue

    # File is located relative to SFV file.
    file = Path(sfv_file).parent / filename

    print('Checking "%s"... ' % filename, end='')

    if not file.exists:
        miss += 1
        print('MISSING')

        continue

    with file.open('rb') as f:
        hash = format(zlib.crc32(f.read()), '08x')

    if hash == crc.lower():
        ok += 1
        print('OK')
    else:
        fail += 1
        print('FAILED (our hash', hash, 'does not match', crc + ')')

sfv.close()

print("\nSummary:", ok, 'passed,', fail, 'failed,', miss, 'missing.')

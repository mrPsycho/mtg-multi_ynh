#!/usr/bin/env python3
"""Assert every locale in conf/index.php's STRINGS table defines the same keys."""

import re
import sys

src = open(sys.argv[1], encoding="utf-8").read()
block = src[src.index("const STRINGS"): src.index("function h(")]

tables = {
    m.group(1): sorted(re.findall(r"^\s+'(\w+)'\s+=>", m.group(2), re.M))
    for m in re.finditer(r"^    '(\w\w)' => \[(.*?)^    \],", block, re.S | re.M)
}

if len(tables) < 2:
    sys.exit(f"only found {len(tables)} translation table(s)")

reference = tables["en"]
status = 0
for lang, keys in tables.items():
    missing = set(reference) - set(keys)
    extra = set(keys) - set(reference)
    if missing or extra:
        status = 1
        print(f"{lang}: missing={sorted(missing)} extra={sorted(extra)}", file=sys.stderr)

sys.exit(status)

# Fork of PurrPHP/yaml

This repository is a fork of [PurrPHP/yaml](https://github.com/PurrPHP/yaml). It is the base
that the polyspec packages depend on: they require `purrphp/yaml` from this repository, at the
tags `v<upstream version>-p<n>`, and do not assume that upstream accepts the fixes below.

## What it fixes

- `CoreSchema` resolves plain scalars by the tag resolution table of YAML 1.2.2, section 10.3.2
  (<https://yaml.org/spec/1.2.2/#1032-tag-resolution>), exactly:
  - only the unsigned forms `0o[0-7]+` and `0x[0-9a-fA-F]+` are octal and hexadecimal
    integers; signed forms such as `+0x1` and `-0o7` are strings;
  - `[-+]?[0-9]+` is an integer, leading zeros included (`0777` is 777, not 777.0);
  - only `null|Null|NULL|~` and the empty scalar are null, only `true|True|TRUE` and
    `false|False|FALSE` are booleans, and only the listed cases of `.inf` and `.nan` are
    floats; other letter cases such as `nULL` and `tRUE` are strings.
- The parser reads a block mapping entry with an explicit key (YAML 1.2.2, 8.2.2): the key follows
  `? ` on its line or is the block node below a lone `?`, and the value follows `: ` on the next
  line of the mapping's indentation, or is the block node below a lone `:`; an entry without a `:`
  line has a null value. Upstream refuses every explicit key.
- The dumper writes each character outside c-printable (YAML 1.2.2, 5.1), and a byte order mark,
  as the escape of its code point in a double-quoted scalar, whatever the style of the node:
  upstream wrote the characters U+0080 to U+009F as the escape of their first UTF-8 byte, left a
  byte order mark and the characters above U+00A0 raw, and wrote a plain or single-quoted scalar
  that holds such a character, which no reader accepts.
- The dumper writes a plain scalar that starts with `-`, `?` or `:` plain when a character other
  than a space follows (YAML 1.2.2, 7.3.3, ns-plain-first), so `-7` and `-0.0` stay numbers;
  upstream single-quoted every such scalar, which a reader takes as a string.

Released as `v1.0.0-p1`, on top of upstream 1.0.0; `v1.0.0-p2` adds the explicit key and the
dumper escapes, and `v1.0.0-p3` the plain scalars after an indicator.

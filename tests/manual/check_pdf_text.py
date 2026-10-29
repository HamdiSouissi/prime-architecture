#!/usr/bin/env python3
"""Check exact logical phrases in text extracted from a PDF with MuPDF.

This diagnostic does not prove copy/paste works in every PDF viewer. Use only
synthetic PDFs when sharing output; expected phrases are echoed on failure.
"""

import argparse
import sys
import unicodedata
from pathlib import Path


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('pdf', type=Path)
    parser.add_argument('--expect', action='append', required=True, help='Exact phrase expected in the extracted text; repeat for each case')
    args = parser.parse_args()
    try:
        import pymupdf
    except ImportError:
        parser.error('PyMuPDF is required: python3 -m pip install PyMuPDF')

    with pymupdf.open(args.pdf) as document:
        extracted = unicodedata.normalize('NFC', '\n'.join(page.get_text() for page in document))
    missing = [phrase for phrase in args.expect if unicodedata.normalize('NFC', phrase) not in extracted]
    if missing:
        for phrase in missing:
            print(f'Phrase not found exactly: {phrase!r}', file=sys.stderr)
        return 1
    print(f'All {len(args.expect)} expected phrases found with MuPDF; other PDF readers may differ.')
    return 0


if __name__ == '__main__':
    sys.exit(main())

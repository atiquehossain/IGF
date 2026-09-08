# Third-party notices

This file records the third-party component introduced for structural inspection of applicant PDF files. It supplements, and does not replace, the license files shipped with installed Composer packages.

## Smalot PDF Parser

- Package: `smalot/pdfparser`
- Locked version: `v2.12.5`
- License: GNU Lesser General Public License, version 3.0 (`LGPL-3.0`)
- Project/source: <https://github.com/smalot/pdfparser/tree/v2.12.5>
- Installed license text: `vendor/smalot/pdfparser/LICENSE.txt`

The application calls this library from an isolated PHP worker to inspect untrusted PDF structure. Preserve this notice and the package’s complete `LICENSE.txt` in any distributed application artifact. If a distribution process removes Composer metadata or package documentation, it must add back the complete LGPL-3.0 text and continue to satisfy the license’s source, modification, reverse-engineering-for-debugging, and relinking requirements as applicable to that distribution.

Run `composer licenses --format=json` for the complete dependency-license inventory; this notice is not a substitute for reviewing the licenses of every production dependency.

## geoBoundaries Bangladesh ADM1 boundaries

- Dataset: geoBoundaries gbOpen, Bangladesh administrative level 1 (`BGD-ADM1-32408957`)
- Represented year: 2015
- Pinned source commit: `9469f09592ced973a3448cf66b6100b741b64c0d`
- Source: <https://github.com/wmgeolab/geoBoundaries/tree/9469f09592ced973a3448cf66b6100b741b64c0d/releaseData/gbOpen/BGD/ADM1>
- Attribution license applied to this derivative: Creative Commons Attribution 4.0 International (`CC BY 4.0`)

The locally bundled map in `resources/data/bangladesh-divisions.json` is a topology-preserving simplified and projected derivative. Coordinates were fitted to a local SVG view box and rounded; the English source spellings “Chittagong”, “Rajshani”, and “Barisal” were normalized to “Chattogram”, “Rajshahi”, and “Barishal”. geoBoundaries and its contributors do not endorse Ignite Global Foundation or this application.

## geoBoundaries Bangladesh ADM2 boundaries

- Dataset: geoBoundaries gbOpen, Bangladesh administrative level 2 (`BGD-ADM2-16705992`)
- Boundary source: Bangladesh Bureau of Statistics (BBS) and OCHA ROAP
- Represented year: 2020
- Pinned source commit: `9469f09592ced973a3448cf66b6100b741b64c0d`
- Source: <https://github.com/wmgeolab/geoBoundaries/tree/9469f09592ced973a3448cf66b6100b741b64c0d/releaseData/gbOpen/BGD/ADM2>
- License: Creative Commons Attribution 3.0 Intergovernmental Organisations (`CC BY 3.0 IGO`)

The locally bundled district map in `resources/data/bangladesh-districts.json` is a topology-preserving simplified and projected derivative. It retains all 64 district features, uses weighted shared-arc simplification with shape preservation, fits the result to a local SVG view box, and normalizes legacy English district spellings. geoBoundaries, BBS, OCHA ROAP, and their contributors do not endorse Ignite Global Foundation or this application.

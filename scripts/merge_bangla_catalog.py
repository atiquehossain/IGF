#!/usr/bin/env python3
"""Carry reviewed Bangla copy into a fresh catalog and apply review overrides.

The script is intentionally database-free. The Laravel catalog command remains
the authority that validates placeholders, HTML structure, row freshness, and
the final import plan before any content is written.
"""

from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
from typing import Any


SCHEMA = "igf-bangla-translation-catalog/v1"


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("--existing", type=Path, required=True)
    parser.add_argument("--template", type=Path, required=True)
    parser.add_argument("--review", type=Path, action="append", default=[])
    parser.add_argument(
        "--allow-review-supersede",
        action="store_true",
        help="Allow a later review file to deliberately replace an earlier decision.",
    )
    parser.add_argument("--output", type=Path, required=True)
    return parser.parse_args()


def load_json(path: Path) -> Any:
    with path.resolve(strict=True).open(encoding="utf-8") as handle:
        return json.load(handle)


def catalog_entries(catalog: Any, label: str) -> dict[str, dict[str, Any]]:
    if not isinstance(catalog, dict) or catalog.get("schema") != SCHEMA:
        raise ValueError(f"{label} is not a supported Bangla catalog")
    if catalog.get("source_locale") != "en" or catalog.get("target_locale") != "bn":
        raise ValueError(f"{label} must translate en into bn")
    entries = catalog.get("entries")
    if not isinstance(entries, list):
        raise ValueError(f"{label} entries must be a list")

    indexed: dict[str, dict[str, Any]] = {}
    for entry in entries:
        if not isinstance(entry, dict) or not isinstance(entry.get("key"), str):
            raise ValueError(f"{label} contains an invalid entry")
        key = entry["key"]
        if key in indexed:
            raise ValueError(f"{label} contains duplicate key {key}")
        indexed[key] = entry
    return indexed


def review_entries(path: Path) -> dict[str, dict[str, Any]]:
    review = load_json(path)
    if not isinstance(review, dict):
        raise ValueError(f"Review {path} must be a key-to-override object")

    for key, override in review.items():
        if not isinstance(key, str) or not isinstance(override, dict):
            raise ValueError(f"Review {path} contains an invalid override")
        if not isinstance(override.get("translation"), str):
            raise ValueError(f"Review {path} override {key} has no translation")
        if not isinstance(override.get("preserve_source"), bool):
            raise ValueError(f"Review {path} override {key} has no preserve_source decision")
    return review


def main() -> int:
    args = arguments()
    existing = load_json(args.existing)
    template = load_json(args.template)
    existing_by_key = catalog_entries(existing, "Existing catalog")
    template_by_key = catalog_entries(template, "Fresh template")

    carried = 0
    suggested = 0
    for key, entry in template_by_key.items():
        previous = existing_by_key.get(key)
        if (
            previous is not None
            and previous.get("source") == entry.get("source")
            and previous.get("source_hash") == entry.get("source_hash")
            and isinstance(previous.get("translation"), str)
            and previous["translation"].strip()
        ):
            entry["translation"] = previous["translation"]
            entry["preserve_source"] = previous.get("preserve_source") is True
            carried += 1
        elif isinstance(entry.get("suggested_translation"), str) and entry["suggested_translation"].strip():
            entry["translation"] = entry["suggested_translation"].strip()
            entry["preserve_source"] = False
            suggested += 1

    applied: dict[str, dict[str, Any]] = {}
    superseded = 0
    for review_path in args.review:
        for key, override in review_entries(review_path).items():
            if key not in template_by_key:
                raise ValueError(f"Review {review_path} references unknown key {key}")
            previous = applied.get(key)
            if previous is not None and previous != override:
                if not args.allow_review_supersede:
                    raise ValueError(f"Conflicting review overrides for key {key}")
                superseded += 1
            applied[key] = override

    for key, override in applied.items():
        template_by_key[key]["translation"] = override["translation"].strip()
        template_by_key[key]["preserve_source"] = override["preserve_source"]

    missing = sum(
        1
        for entry in template_by_key.values()
        if not isinstance(entry.get("translation"), str) or not entry["translation"].strip()
    )
    if missing:
        raise ValueError(f"Merged catalog still has {missing} blank translations")

    output = args.output.resolve()
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = output.with_suffix(output.suffix + ".tmp")
    temporary.write_text(
        json.dumps(template, ensure_ascii=False, indent=2) + os.linesep,
        encoding="utf-8",
    )
    os.replace(temporary, output)
    print(
        f"Merged {len(template_by_key)} rows: {carried} carried, "
        f"{suggested} fresh suggestions, {len(applied)} reviewed overrides, "
        f"{superseded} deliberately superseded decisions."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

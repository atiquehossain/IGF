#!/usr/bin/env python3
"""Prepare a machine-assisted Bangla review catalog with an offline NLLB model.

This utility never touches the application database. Its output must still pass
the PHP catalog validator and receive a human review before import.
"""

from __future__ import annotations

import argparse
import json
import os
import re
from pathlib import Path

import ctranslate2
from transformers import AutoTokenizer


BN_RE = re.compile(r"[\u0980-\u09ff]")
HTML_TAG_RE = re.compile(r"</?[A-Za-z][^>]*>")
PLACEHOLDER_RE = re.compile(
    r":[A-Za-z_][A-Za-z0-9_]*"
    r"|\{\{\s*[A-Za-z_][A-Za-z0-9_]*\s*\}\}"
    r"|(?<!\{)\{[A-Za-z_][A-Za-z0-9_]*\}(?!\})"
    r"|%(?:\d+\$)?[bcdeEfFgGosuxX]"
)
ENTITY_RE = re.compile(r"&(?:[A-Za-z][A-Za-z0-9]+|#[0-9]+|#x[0-9A-Fa-f]+);")
URL_EMAIL_RE = re.compile(
    r"(?:https?://|www\.)\S+|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}"
)

PRESERVE_EXACT = {
    "bKash",
    "Nagad",
    "Visa",
    "Mastercard",
    "American Express",
    "SSLCommerz",
    "PayPal",
    "Facebook",
    "Instagram",
    "LinkedIn",
    "YouTube",
    "WhatsApp",
    "X",
    "EN",
    "BN",
    "BDT",
    "PDF",
    "PNG",
    "JPG",
    "JPEG",
    "MP3",
    "MP4",
    "FAQ",
    "FAQs",
    "ID",
    "URL",
    "CSS",
    "SEO",
    "API",
    "CV",
    "NGO",
    "RJSC",
    "NBR",
}

PROTECTED_TERMS = sorted(
    {
        "Ignite Global Foundation": "ইগনাইট গ্লোবাল ফাউন্ডেশন",
        "Ignite Foundation": "ইগনাইট ফাউন্ডেশন",
        "Ignite School": "ইগনাইট স্কুল",
        "Ignite": "ইগনাইট",
        "SSLCommerz": "SSLCommerz",
        "American Express": "American Express",
        "Mastercard": "Mastercard",
        "bKash": "bKash",
        "Nagad": "Nagad",
        "Visa": "Visa",
        "YouTube": "YouTube",
        "Facebook": "Facebook",
        "Instagram": "Instagram",
        "LinkedIn": "LinkedIn",
        "PayPal": "PayPal",
    }.items(),
    key=lambda item: len(item[0]),
    reverse=True,
)
PROTECTED_LOOKUP = dict(PROTECTED_TERMS)
PROTECTED_RE = re.compile(
    "(" + "|".join(re.escape(source) for source, _ in PROTECTED_TERMS) + ")"
)
BOUNDARY_RE = re.compile(
    "(" + "|".join(
        [
            HTML_TAG_RE.pattern,
            PLACEHOLDER_RE.pattern,
            ENTITY_RE.pattern,
            URL_EMAIL_RE.pattern,
            PROTECTED_RE.pattern[1:-1],
        ]
    ) + ")"
)
SENTENCE_RE = re.compile(r"(?<=[.!?;:])\s+|\n+")


def arguments() -> argparse.Namespace:
    parser = argparse.ArgumentParser()
    parser.add_argument("catalog", type=Path)
    parser.add_argument("--model", type=Path, required=True)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--batch-size", type=int, default=24)
    return parser.parse_args()


def preserve_source(source: str) -> bool:
    plain = HTML_TAG_RE.sub("", source).strip()
    return (
        plain in PRESERVE_EXACT
        or bool(URL_EMAIL_RE.fullmatch(plain))
        or not re.search(r"[A-Za-z\u0980-\u09ff]", plain)
        or (BN_RE.search(plain) is not None and re.search(r"[A-Za-z]", plain) is None)
    )


def protected_piece(piece: str) -> str | None:
    if (
        HTML_TAG_RE.fullmatch(piece)
        or PLACEHOLDER_RE.fullmatch(piece)
        or ENTITY_RE.fullmatch(piece)
        or URL_EMAIL_RE.fullmatch(piece)
    ):
        return piece
    return PROTECTED_LOOKUP.get(piece)


def split_long_text(text: str, limit: int = 340) -> list[str]:
    if len(text) <= limit:
        return [text]
    sentences = SENTENCE_RE.split(text)
    chunks: list[str] = []
    current = ""
    for sentence in sentences:
        if len(current) + len(sentence) + 1 <= limit:
            current = f"{current} {sentence}".strip()
            continue
        if current:
            chunks.append(current)
        while len(sentence) > limit:
            cut = sentence.rfind(" ", 0, limit)
            if cut < limit // 2:
                cut = limit
            chunks.append(sentence[:cut].strip())
            sentence = sentence[cut:].strip()
        current = sentence
    if current:
        chunks.append(current)
    return chunks


def translatable_units(source: str) -> list[str]:
    units: list[str] = []
    for piece in BOUNDARY_RE.split(source):
        if not piece or protected_piece(piece) is not None:
            continue
        core = piece.strip()
        if not core or not re.search(r"[A-Za-z]", core):
            continue
        units.extend(split_long_text(core))
    return units


def translate_units(
    units: list[str], model: Path, batch_size: int
) -> dict[str, str]:
    tokenizer = AutoTokenizer.from_pretrained(
        str(model), src_lang="eng_Latn", local_files_only=True
    )
    translator = ctranslate2.Translator(
        str(model), device="cpu", compute_type="int8"
    )
    translated: dict[str, str] = {}
    for start in range(0, len(units), batch_size):
        batch = units[start : start + batch_size]
        token_batches = [
            tokenizer.convert_ids_to_tokens(tokenizer(text).input_ids) for text in batch
        ]
        results = translator.translate_batch(
            token_batches,
            target_prefix=[["ben_Beng"] for _ in batch],
            beam_size=4,
            max_decoding_length=512,
        )
        for source, result in zip(batch, results, strict=True):
            hypothesis = result.hypotheses[0]
            if hypothesis and hypothesis[0] == "ben_Beng":
                hypothesis = hypothesis[1:]
            value = tokenizer.decode(
                tokenizer.convert_tokens_to_ids(hypothesis), skip_special_tokens=True
            ).strip()
            translated[source] = value
        print(f"Translated {min(start + len(batch), len(units))}/{len(units)} units", flush=True)
    return translated


def rebuild(source: str, translations: dict[str, str]) -> str:
    output: list[str] = []
    for piece in BOUNDARY_RE.split(source):
        if piece == "":
            continue
        protected = protected_piece(piece)
        if protected is not None:
            output.append(protected)
            continue
        core = piece.strip()
        if not core or not re.search(r"[A-Za-z]", core):
            output.append(piece)
            continue
        leading = piece[: len(piece) - len(piece.lstrip())]
        trailing = piece[len(piece.rstrip()) :]
        chunks = split_long_text(core)
        output.append(leading + " ".join(translations[chunk] for chunk in chunks) + trailing)
    return "".join(output).strip()


def improve_terminology(value: str) -> str:
    replacements = {
        "জাকাত": "যাকাত",
        "সাদাকাহ": "সদকা",
        "সদকাহ": "সদকা",
        "কুরবানী": "কোরবানি",
        "কুরবানি": "কোরবানি",
        "রমাদান": "রমজান",
        "ইফতার খাবার": "ইফতার",
        "বাংলাদেশী": "বাংলাদেশি",
        "ওয়েব সাইট": "ওয়েবসাইট",
        "ওয়েবসাইটটি": "ওয়েবসাইটটি",
    }
    for source, target in replacements.items():
        value = value.replace(source, target)
    value = re.sub(
        r"(?<![\u0980-\u09ff])দান করুন(?![\u0980-\u09ff])",
        "অনুদান দিন",
        value,
    )
    value = re.sub(
        r"(?<![\u0980-\u09ff])দান(?![\u0980-\u09ff])", "অনুদান", value
    )
    return value


def main() -> int:
    args = arguments()
    catalog_path = args.catalog.resolve(strict=True)
    model_path = args.model.resolve(strict=True)
    output_path = (args.output or args.catalog).resolve()
    catalog = json.loads(catalog_path.read_text(encoding="utf-8"))
    entries = catalog.get("entries", [])

    units = sorted(
        {
            unit
            for entry in entries
            if not preserve_source(str(entry.get("source", "")))
            and not str(entry.get("suggested_translation", "")).strip()
            for unit in translatable_units(str(entry.get("source", "")))
        }
    )
    translations = translate_units(units, model_path, args.batch_size)

    preserved = 0
    suggested = 0
    for entry in entries:
        source = str(entry.get("source", ""))
        suggestion = str(entry.get("suggested_translation", "")).strip()
        if suggestion:
            entry["translation"] = suggestion
            entry["preserve_source"] = False
            suggested += 1
        elif preserve_source(source):
            entry["translation"] = source.strip()
            entry["preserve_source"] = True
            preserved += 1
        else:
            translated = improve_terminology(rebuild(source, translations))
            if translated == source.strip() or BN_RE.search(translated) is None:
                entry["translation"] = source.strip()
                entry["preserve_source"] = True
                preserved += 1
            else:
                entry["translation"] = translated
                entry["preserve_source"] = False

    output_path.parent.mkdir(parents=True, exist_ok=True)
    temporary = output_path.with_suffix(output_path.suffix + ".tmp")
    temporary.write_text(
        json.dumps(catalog, ensure_ascii=False, indent=2) + os.linesep,
        encoding="utf-8",
    )
    os.replace(temporary, output_path)
    print(
        f"Prepared {len(entries)} entries ({suggested} curated suggestions, "
        f"{preserved} explicitly preserved sources)."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

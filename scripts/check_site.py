#!/usr/bin/env python3
"""Check local links and required assets on the static GitHub Pages website."""
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlparse, unquote
import sys

ROOT = Path(__file__).resolve().parents[1]

class PageParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []
        self.ids = set()
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if attrs.get("id"):
            self.ids.add(attrs["id"])
        if tag in {"a", "link", "script", "img"}:
            attr = "href" if tag in {"a", "link"} else "src"
            if attrs.get(attr):
                self.links.append((attrs[attr], tag))

pages = sorted(ROOT.glob("*.html"))
errors = []
if not pages:
    errors.append("Keine HTML-Seiten im Repository-Hauptverzeichnis gefunden.")

parsed = {}
for page in pages:
    parser = PageParser()
    try:
        parser.feed(page.read_text(encoding="utf-8"))
        parsed[page.name] = parser
    except (OSError, UnicodeError) as exc:
        errors.append(f"{page.name}: Datei kann nicht gelesen werden: {exc}")

for page in pages:
    parser = parsed.get(page.name)
    if not parser:
        continue
    for raw, tag in parser.links:
        raw = raw.strip()
        if not raw or raw.startswith(("http://", "https://", "mailto:", "tel:", "javascript:", "data:")):
            continue
        parts = urlparse(raw)
        path = unquote(parts.path)
        if not path:
            if parts.fragment and parts.fragment not in parser.ids:
                errors.append(f"{page.name}: Sprungziel #{parts.fragment} fehlt.")
            continue
        target = (page.parent / path).resolve()
        if not target.exists():
            errors.append(f"{page.name}: lokaler Link/Asset fehlt: {raw}")
            continue
        if parts.fragment and target.suffix.lower() == ".html":
            target_parser = parsed.get(target.name)
            if target_parser and parts.fragment not in target_parser.ids:
                errors.append(f"{page.name}: Sprungziel {raw} fehlt in {target.name}.")

required = ["index.html", "verein.html", "strecken.html", "onroad.html", "offroad.html",
            "termine.html", "ergebnisse.html", "reglement.html", "kontakt.html",
            "mitglied-werden.html", "galerie.html", "downloads.html", "live.html",
            "impressum.html", "datenschutz.html", "assets/css/site.css", "assets/js/site.js"]
for item in required:
    if not (ROOT / item).is_file():
        errors.append(f"Pflichtdatei fehlt: {item}")

if errors:
    print("Website-Prüfung fehlgeschlagen:")
    for error in errors:
        print(f" - {error}")
    sys.exit(1)

print(f"Website-Prüfung erfolgreich: {len(pages)} HTML-Seiten und lokale Links/Assets geprüft.")

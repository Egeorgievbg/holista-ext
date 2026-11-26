"""Local sanity check for the Holista extension metadata.

Mirrors tests/unit/.../OrganizationMetadataTest.php so mistakes are caught
before pushing, on a machine without a PHP runtime. CI remains the authority.
"""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RES = ROOT / "src/files/custom/Espo/Modules/Holista/Resources"
ENTITY = "HolistaOrganization"

failures = []


def load(path: Path):
    if not path.is_file():
        failures.append(f"missing file: {path.relative_to(ROOT)}")
        return None
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        failures.append(f"invalid JSON {path.relative_to(ROOT)}: {exc}")
        return None


def check(condition, message):
    if not condition:
        failures.append(message)


scope = load(RES / f"metadata/scopes/{ENTITY}.json")
if scope:
    for key in ("entity", "object", "acl", "tab"):
        check(scope.get(key) is True, f"scope.{key} must be true")
    check(scope.get("module") == "Holista", "scope.module must be Holista")

defs = load(RES / f"metadata/entityDefs/{ENTITY}.json") or {}
fields = defs.get("fields", {})
links = defs.get("links", {})

check(fields.get("name", {}).get("required") is True, "name must be required")
for name, field in fields.items():
    if name == "name":
        continue
    check("required" not in field, f'field "{name}" must not be required')

for name in ("legacyId", "sourceSystem", "sourceRecordKey", "migrationBatchId", "sourceRef", "qaStatus"):
    check(name in fields, f"missing provenance field: {name}")

for name in fields:
    check(not re.search(r"eik|bulstat|registration|vat|uin", name, re.I),
          f'legal-identity field "{name}" is forbidden by ADR-002')

for name, field in fields.items():
    if field.get("type") != "link":
        continue
    check(name in links, f'link field "{name}" has no links entry')
    if name in links:
        check(field.get("entity") == links[name].get("entity"), f'"{name}" entity mismatch')
        check(field.get("foreign") == links[name].get("foreign"), f'"{name}" foreign mismatch')

account = load(RES / "metadata/entityDefs/Account.json") or {}
acc_links = (account.get("links") or {}).get("group", {})
acc_fields = (account.get("fields") or {}).get("group", {})
check(links.get("accounts", {}).get("type") == "hasMany", "accounts must be hasMany")
check(links.get("accounts", {}).get("foreign") == "group", "accounts.foreign must be group")
check(acc_links.get("type") == "belongsTo", "Account.group must be belongsTo")
check(acc_links.get("entity") == ENTITY, "Account.group must point at HolistaOrganization")
check(acc_links.get("foreign") == "accounts", "Account.group.foreign must be accounts")
check(acc_fields.get("type") == "link", "Account.group field must be a link")

declared = set(fields) | {"id"}
for layout in ("list", "detail"):
    rows = load(RES / f"metadata/layouts/{ENTITY}/{layout}.json") or []
    for row in rows:
        for cell in (row if isinstance(row, list) else [row]):
            name = cell.get("name") if isinstance(cell, dict) else None
            check(name is not None, f"layout {layout} has a cell without a name")
            check(name in declared, f'layout {layout} references undeclared field "{name}"')

for locale in ("bg_BG", "en_US"):
    tr = load(RES / f"i18n/{locale}/{ENTITY}.json") or {}
    for name in fields:
        check(name in (tr.get("fields") or {}), f'{locale} missing label for field "{name}"')
    for option in fields.get("organizationKind", {}).get("options", []):
        check(option in ((tr.get("options") or {}).get("organizationKind") or {}),
              f'{locale} missing label for option "{option}"')

if failures:
    print(f"FAIL ({len(failures)})")
    for item in failures:
        print("  -", item)
    sys.exit(1)

print(f"OK: {len(fields)} fields, {len(links)} links, 2 layouts, 2 locales, 0 failures")

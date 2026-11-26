"""Non-interactive equivalent of espocrm/ext-template `init.php`.

init.php reads three prompts from stdin; this script takes the same three values
as arguments and performs the identical placeholder replacement and renames so the
scaffold is reproducible in CI and on machines without a PHP binary.
"""

import argparse
import json
import shutil
from pathlib import Path

FILES = [
    "package.json",
    "extension.json",
    "jsconfig.json",
    "tsconfig.json",
    "config-default.json",
    "phpstan.neon",
    "composer.json",
    "README.md",
    "src/files/custom/Espo/Modules/MyModuleName/Resources/module.json",
    ".github/workflows/test.yml.disabled",
]

CLIENT_JSON = """{
  "scriptList": [
      "__APPEND__",
      "client/custom/modules/{@nameHyphen}/lib/init.js"
  ]
}
"""


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--root", required=True, type=Path)
    ap.add_argument("--name", required=True)
    ap.add_argument("--description", required=True)
    ap.add_argument("--author", required=True)
    args = ap.parse_args()

    root = args.root.resolve()
    name_label = args.name
    name = name_label[0].upper() + name_label[1:]
    name_hyphen = "".join(("-" + c.lower()) if c.isupper() and i else c.lower()
                          for i, c in enumerate(name))
    description = args.description if args.description.endswith(".") else args.description + "."

    mapping = {
        "{@name}": name,
        "{@nameHyphen}": name_hyphen,
        "{@nameLabel}": name_label,
        "{@description}": description,
        "{@author}": args.author,
        "{@bundled}": "true",
        "{@jsTranspiled}": "true",
    }

    for rel in FILES:
        path = root / rel
        text = path.read_text(encoding="utf-8")
        for key, value in mapping.items():
            text = text.replace(key, value)
        path.write_text(text, encoding="utf-8")

    client_dir = root / "src/files/custom/Espo/Modules/MyModuleName/Resources/metadata/app"
    client_dir.mkdir(parents=True, exist_ok=True)
    client_file = client_dir / "client.json"
    text = CLIENT_JSON
    for key, value in mapping.items():
        text = text.replace(key, value)
    client_file.write_text(text, encoding="utf-8")

    shutil.move(str(root / "src/files/custom/Espo/Modules/MyModuleName"),
                str(root / "src/files/custom/Espo/Modules" / name))
    shutil.move(str(root / "src/files/client/custom/modules/my-module-name"),
                str(root / "src/files/client/custom/modules" / name_hyphen))
    shutil.move(str(root / "tests/unit/Espo/Modules/MyModuleName"),
                str(root / "tests/unit/Espo/Modules" / name))
    shutil.move(str(root / "tests/integration/Espo/Modules/MyModuleName"),
                str(root / "tests/integration/Espo/Modules" / name))

    print(json.dumps({"name": name, "nameHyphen": name_hyphen,
                      "description": description, "author": args.author}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

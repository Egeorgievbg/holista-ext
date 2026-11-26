# Holista — EspoCRM extension

Domain model for the Holista field-sales operation, packaged as an installable EspoCRM extension.
Built from the official [`espocrm/ext-template`](https://github.com/espocrm/ext-template) and pinned
to the EspoCRM release that actually runs in production.

| | |
|---|---|
| Module | `Holista` |
| Accepted EspoCRM | `>=10.1.4 <11.0.0` |
| PHP | `>=8.4 <8.6` |
| Target instance | EspoCRM 10.1.4 / PHP 8.4.28 / MariaDB 11.4 |

## Why an extension

The customization currently lives directly in `custom/Espo/Custom/` on the server. That works, but it
has no tests, no review and no deployable artifact — a drift between the repo copy and the live
filesystem has already happened once. This repo makes those changes reviewable: metadata is the
source, CI proves the invariants, and the build produces a ZIP that can be installed on the instance.

Decision record: ADR-001 option A in the Holista CRM working repo (`docs/P0-DISCOVERY-v3.md`).

## Scope

Implemented here:

| Phase | Deliverable | State |
|---|---|---|
| P1 | `HolistaOrganization` — a chain group, explicitly **not** a legal entity; `Account.group` link (1:N) | done |
| P1 | Provenance fields on the entity (`legacyId`, `sourceSystem`, `sourceRecordKey`, `migrationBatchId`, `sourceRef`, `qaStatus`) | done on `HolistaOrganization` |
| P1 | CI: static analysis + unit tests on a pinned EspoCRM checkout | done |

Deliberately **not** here yet: pharmacy site aliases, contacts, orders, routes, maps, AI intake.
Each arrives as its own PR.

## Domain rules encoded in the metadata

- A chain group is not a legal entity. There is no ЕИК / VAT / registration field on
  `HolistaOrganization`; legal identity belongs on `Account.legalName` and is only ever copied from a
  verified source. `tools/check_metadata.py` and `OrganizationMetadataTest` both fail if such a field
  appears.
- Only `name` is required. Missing data stays `null`, never `0` and never an empty string.
- Every field is translated in `bg_BG` and `en_US`; the tests fail on a missing label.
- Layouts may only reference declared fields, so a rename cannot silently blank a column.

## Develop

```bash
npm install                          # extension tooling
cp config-default.json config.json   # then adjust database credentials
npm run all                          # download the pinned EspoCRM, install it in ./site, install this extension
npm run sync                         # copy src/ into the running dev instance after a change
npm run extension                    # build the installable ZIP into ./build
```

Unit tests and static analysis (needs `composer install` once):

```bash
composer install
vendor/bin/phpunit tests/unit
vendor/bin/phpstan
```

Without a PHP runtime, the same metadata invariants can be checked with:

```bash
python tools/check_metadata.py
```

## Install on an instance

1. Build the ZIP: `npm run extension`.
2. EspoCRM → Administration → Extensions → upload → Install.
3. Run Administration → Rebuild, then clear cache.

On the Holista deployment `custom/`, `client/custom/` and `data/` are symlinked into the persistent
volume, so an installed extension survives a redeploy.

## Repository layout

```
src/files/custom/Espo/Modules/Holista/     PHP module + Resources/metadata (the actual model)
src/files/client/custom/modules/holista/   frontend module
src/scripts/                               AfterInstall / AfterUninstall hooks
tests/unit/Espo/Modules/Holista/           PHPUnit: metadata invariants
tests/integration/Espo/Modules/Holista/    integration tests (need a built instance)
tools/check_metadata.py                    dependency-free metadata checker
tools/scaffold.py                          reproducible record of the template initialization
```

## License

MIT — see [LICENSE](LICENSE).

# Maintenance scripts for wiki instance management

Three CLI maintenance scripts are available for managing wiki farm instances directly from the
command line. Each script runs the same ProcessManager process steps as the corresponding REST API
endpoint, and prints live step-by-step progress to stdout.

---

## CreateInstance.php

Creates a new wiki farm instance.

**Runs the same steps as the REST API `POST /wikifarm/instances/{path}` endpoint:**
1. `create-instance-vault` – creates the instance directory
2. `install-instance` – installs MediaWiki into the vault
3. `copy-user` – copies the specified user as administrator
4. `run-update` – runs MediaWiki update.php
5. `copy-template` *(optional)* – imports a template wiki export
6. `run-post-instance-creation-commands` – executes any configured post-creation commands

### Usage

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CreateInstance.php --path=<path> [options]
```

### Options

| Option | Required | Description |
|---|---|---|
| `--path` | **yes** | URL path / identifier for the new instance (e.g. `mywiki`) |
| `--display-name` | no | Human-readable display name. Defaults to the value of `--path` |
| `--lang` | no | Language code for the new wiki (e.g. `de`). Defaults to the farm's configured language |
| `--template` | no | Template name to import after installation (see the farm templating system) |
| `--user` | no | Username to copy into the new instance as administrator. Defaults to `WikiSysop` |
| `--metadata` | no | JSON object of metadata key/value pairs (see below) |
| `--config` | no | JSON object of MediaWiki config overrides to store for the instance (see below) |

### Examples

Minimal – only the path is required:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CreateInstance.php --path=mywiki
```

With display name and language:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CreateInstance.php \
  --path=mywiki \
  --display-name="My Wiki" \
  --lang=de \
  --user=WikiSysop
```

With template, metadata and config overrides:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CreateInstance.php \
  --path=mywiki \
  --display-name="My Wiki" \
  --template=default \
  --metadata='{"group":"sales","keywords":["crm","finance"]}' \
  --config='{"wgSitename":"My Wiki"}'
```

---

## ArchiveInstance.php

Archives (or purges) an existing wiki farm instance.

**Runs the same steps as the REST API `DELETE /wikifarm/instances/{path}` endpoint.**

- A *complete* instance (status `ready`, `suspended`, or `maintenance`) is **archived**:
  1. `run-pre-instance-deletion-commands` – executes pre-deletion hooks
  2. `archive-instance` – marks the instance as archived and removes it from active routing
  3. `run-post-instance-deletion-commands` – executes post-deletion hooks
- An *incomplete* instance (status `initializing` or `installed`) is **purged** instead:
  1. `purge-instance` – removes the partially created instance
  2. `run-post-instance-deletion-commands`

### Usage

```bash
php extensions/BlueSpiceWikiFarm/maintenance/ArchiveInstance.php --instance=<path-or-id>
```

### Options

| Option | Required | Description |
|---|---|---|
| `--instance` | **yes** | Path or internal ID of the instance to archive |

### Examples

Archive by path:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/ArchiveInstance.php --instance=mywiki
```

Archive by internal instance ID:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/ArchiveInstance.php --instance=a1b2c3d4
```

---

## CopyInstance.php

Copies (clones) an existing wiki farm instance into a new one.

The source instance must have status `ready`. The new instance gets its own path, database, and
vault directory; all wiki content is copied from the source.

**Runs the same steps as the REST API `POST /wikifarm/instances/{path}/clone` endpoint:**
1. `create-instance-vault`
2. `install-instance`
3. `copy-instance-data` – copies the source instance's database and file content
4. `copy-user`
5. `run-update`
6. `copy-template` *(optional)*
7. `run-post-instance-creation-commands`

### Usage

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CopyInstance.php \
  --source=<source-path-or-id> --path=<new-path> [options]
```

### Options

| Option | Required | Description |
|---|---|---|
| `--source` | **yes** | Path or internal ID of the source instance to copy |
| `--path` | **yes** | URL path / identifier for the new instance (e.g. `mywiki-copy`) |
| `--display-name` | no | Display name for the new instance. Defaults to the value of `--path` |
| `--lang` | no | Language code for the new wiki. Defaults to the farm's configured language |
| `--template` | no | Template name to import after installation |
| `--user` | no | Username to copy into the new instance as administrator. Defaults to `WikiSysop` |
| `--metadata` | no | JSON object of metadata key/value pairs (see below) |
| `--config` | no | JSON object of MediaWiki config overrides for the new instance (see below) |

### Examples

Minimal copy:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CopyInstance.php \
  --source=mywiki \
  --path=mywiki-copy
```

Copy with a new display name and language override:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CopyInstance.php \
  --source=mywiki \
  --path=mywiki-de \
  --display-name="My Wiki (DE)" \
  --lang=de
```

Copy with metadata and config:

```bash
php extensions/BlueSpiceWikiFarm/maintenance/CopyInstance.php \
  --source=mywiki \
  --path=mywiki-staging \
  --display-name="My Wiki (Staging)" \
  --user=WikiSysop \
  --metadata='{"group":"staging"}' \
  --config='{"wgSitename":"My Wiki Staging"}'
```

---

## Common options

### `--metadata`

A JSON object passed as a string. Supported keys:

| Key | Type | Description |
|---|---|---|
| `group` | string | Assigns the instance to a farm group |
| `keywords` | array of strings | Tags for the instance |
| `desc` | string | Short description |
| `pinned` | boolean | Pins the instance in the farm overview |

Example:

```bash
--metadata='{"group":"hr","keywords":["onboarding","policy"],"desc":"HR wiki","pinned":true}'
```

### `--config`

A JSON object of MediaWiki global variable overrides that are persisted in the instance record and
applied via the farm's dynamic configuration system. Only set values that genuinely need to differ
from the farm defaults.

Example:

```bash
--config='{"wgSitename":"My Wiki","wgLogo":"/images/custom-logo.png"}'
```

---

## Exit codes

All scripts exit with code `0` on success and a non-zero code on failure (invalid arguments,
process registration failure, or a process step error).

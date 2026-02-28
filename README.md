# PHP Status Agent

A tiny, framework-agnostic PHP status endpoint for Linux VPS/server/WSL.

It exposes **host metrics** as JSON and ships with an optional zero-build dashboard.

## What it provides

Private (full) metrics:

* hostname
* uptime
* load average
* CPU (cores + model)
* memory
* swap
* disk usage

Public (safe) overview:

* uptime
* load average
* memory usage percent
* disk `/` usage percent

The goal is to be easy to run, easy to understand, and easy to embed into any PHP project.

---

## Requirements

* PHP **8.1+**
* Composer

---

## Install

```bash
composer install
```

---

## Run locally (recommended)

```bash
php -S 127.0.0.1:9000 public/index.php
```

Open:

* Dashboard: `http://127.0.0.1:9000/`
* Public JSON: `http://127.0.0.1:9000/public`
* Private JSON: `http://127.0.0.1:9000/status`

---

## Security defaults

`/status` is **protected by default**.

You must set `STATUS_AGENT_TOKEN` and call it using a Bearer token.

Create a `.env` file (do not commit it):

```env
STATUS_AGENT_TOKEN=change-me
```

Then:

```bash
curl -s -H "Authorization: Bearer change-me" http://127.0.0.1:9000/status
```

If `STATUS_AGENT_TOKEN` is missing, `/status` returns a JSON error to prevent accidental public exposure.

### Optional: open `/status` (not recommended)

For local experiments only, you can explicitly allow `/status` without a token:

```env
STATUS_AGENT_STATUS_OPEN=1
```

---

## Public endpoint

`/public` is meant for embedding on public pages (limited fields, no sensitive details):

```bash
curl -s http://127.0.0.1:9000/public
```

---

## Example responses

### `/public`

```json
{
  "ok": true,
  "schema_version": 1,
  "public": {
    "uptime_seconds": 30148,
    "uptime_human": "8h 22m",
    "loadavg": [0.04, 0.12, 0.12],
    "memory_percent": 25,
    "memory": {
      "used_percent": 25,
      "used_bytes": 2055086080,
      "total_bytes": 8299728896
    },
    "disk_root_percent": 1,
    "disk_root": {
      "used_percent": 1,
      "used_bytes": 15997956096,
      "total_bytes": 1081101176832
    }
  },
  "timestamp": 1700000000
}
```

### `/status`

```json
{
  "ok": true,
  "schema_version": 1,
  "host": {
    "hostname": "server-01",
    "uptime_seconds": 12345,
    "loadavg": [0.12, 0.20, 0.18],
    "cpu": {
      "cores": 4,
      "model": "Example CPU Model"
    },
    "memory": {
      "total_bytes": 8589934592,
      "used_bytes": 2147483648,
      "free_bytes": 6442450944,
      "used_percent": 25,
      "total_mb": 8192,
      "used_mb": 2048,
      "free_mb": 6144
    },
    "swap": {
      "total_bytes": 1073741824,
      "used_bytes": 0,
      "free_bytes": 1073741824,
      "used_percent": 0,
      "total_mb": 1024,
      "used_mb": 0,
      "free_mb": 1024
    },
    "disk": [
      {
        "mount": "/",
        "total_bytes": 42949672960,
        "used_bytes": 12884901888,
        "free_bytes": 30064771072,
        "used_percent": 30,
        "total_gb": 40,
        "used_gb": 12,
        "free_gb": 28
      }
    ]
  },
  "timestamp": 1700000000
}
```

Notes:

* For integrations, prefer the `*_bytes` and `used_percent` fields.
* `*_mb` and `*_gb` are kept for compatibility.

---

## Environment variables

* `STATUS_AGENT_TOKEN` (required for `/status` unless you set open mode)
* `STATUS_AGENT_STATUS_OPEN` (optional, default `0`)
* `STATUS_AGENT_PUBLIC_REFRESH` (optional, dashboard refresh seconds; default `10`, min `3`, max `120`)

---

## License

MIT

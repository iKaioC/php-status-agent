# PHP Status Agent

A tiny, framework-agnostic PHP endpoint that exposes **host machine status** as JSON (Linux VPS/server/WSL):

* hostname
* uptime
* load average
* CPU (cores + model)
* memory
* swap
* disk usage

The goal is to be easy to run, easy to understand, and easy to integrate into any PHP project.

---

## Requirements

* PHP **8.1+**
* Composer

---

## Run locally (recommended)

Inside the project folder:

```bash
composer install
php -S 127.0.0.1:9000 public/index.php
```

Open:

* `http://127.0.0.1:9000/status`

---

## Test with curl

With the server running:

```bash
curl -s http://127.0.0.1:9000/status
```

Pretty print (optional):

```bash
curl -s http://127.0.0.1:9000/status | jq
```

> If you don't have `jq`, just remove `| jq`.

---

## Security note

By default, the command above binds to **127.0.0.1**, meaning only your own machine can access it.

✅ This is the recommended mode for development.

If you plan to expose this endpoint publicly (e.g. via Nginx or an open port), add authentication (token) and/or restrict access by IP first.

> Token authentication is planned for a next version.

---

## Example response

```json
{
  "ok": true,
  "host": {
    "hostname": "server-01",
    "uptime_seconds": 12345,
    "loadavg": [0.12, 0.20, 0.18],
    "cpu": {
      "cores": 4,
      "model": "Example CPU Model"
    },
    "memory": {
      "total_mb": 2048,
      "used_mb": 900,
      "free_mb": 1148
    },
    "swap": {
      "total_mb": 1024,
      "used_mb": 0,
      "free_mb": 1024
    },
    "disk": [
      {
        "mount": "/",
        "total_gb": 40,
        "used_gb": 12,
        "free_gb": 28
      }
    ]
  },
  "timestamp": 1700000000
}
```

---

## Roadmap

* Token authentication (Bearer)
* Select which fields to return (e.g. `?fields=cpu,memory`)
* Service checks (nginx/php-fpm/mariadb)
* HTTP checks (website status/latency)
* Dockerfile + deploy examples

---

## License

MIT

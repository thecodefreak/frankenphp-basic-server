# php-basic-webserver

Minimal FrankenPHP-based container for a throw-away PHP web server.

## Requirements

- Docker
- Docker Compose

## Usage

Build and run:

```bash
docker compose up --build
```

Then open:

- http://localhost/

## Layout

- `Dockerfile` - image build
- `php.ini` - PHP config (optional)
- `app/public` - document root

## Notes

- This runs plain HTTP only. Put under a reverse proxy or update compose file and Dockerfile for auto ssl
- The `./app` directory is bind-mounted into the container
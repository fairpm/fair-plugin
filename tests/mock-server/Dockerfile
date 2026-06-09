FROM php:8.0-cli-alpine

COPY tests/mock-server/index.php /app/index.php
COPY tests/fixtures/ /app/fixtures/
WORKDIR /app
EXPOSE 8080

HEALTHCHECK --interval=2s --timeout=3s --retries=15 \
	CMD php -r "echo file_get_contents('http://localhost:8080/health');" | grep -q ok

CMD ["php", "-S", "0.0.0.0:8080", "index.php"]

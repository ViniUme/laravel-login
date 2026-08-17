docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/app" \
    -w /app \
    composer:2.9 \
    composer install --ignore-platform-reqs

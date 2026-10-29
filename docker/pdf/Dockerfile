FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends chromium ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /service
COPY docker/pdf/package*.json ./
RUN npm ci --omit=dev --ignore-scripts --no-audit --no-fund
COPY docker/pdf/server.mjs ./
USER node
ENV HOME=/tmp
CMD ["node", "server.mjs"]

FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends chromium ca-certificates \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /service
COPY docker/pdf/package*.json ./
RUN npm ci --omit=dev --ignore-scripts --no-audit --no-fund \
    && node -e "const f=require('node:fs'),z=require('node:zlib'); require('node:stream/promises').pipeline(f.createReadStream('node_modules/@sparticuz/chromium/bin/chromium.br'),z.createBrotliDecompress(),f.createWriteStream('/service/chromium')).catch(e=>{console.error(e);process.exit(1)})" \
    && chmod 755 /service/chromium \
    && rm -rf node_modules/@sparticuz/chromium/bin
COPY docker/pdf/server.mjs ./
USER node
ENV HOME=/tmp
CMD ["node", "server.mjs"]

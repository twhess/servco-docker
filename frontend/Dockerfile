# frontend/Dockerfile
FROM node:20-alpine

# Create app directory
WORKDIR /app

# Install deps first (better layer caching)
COPY package*.json ./
# If you use pnpm or yarn, swap these commands accordingly
RUN npm ci

# Copy the rest of the frontend
COPY . .

# Quasar dev server default port (often 9000) or Vite (often 5173)
# You're probably using 9000 or 8080 depending on config—expose both safely.
EXPOSE 9000 8080 5173

# IMPORTANT: bind to 0.0.0.0 so it is reachable from outside container
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

# Avatar Storage Setup Guide

This document covers the setup required for user avatar uploads to work correctly in both local development (Docker) and production (AWS LAMP) environments.

## Overview

User avatars are stored in Laravel's public storage directory (`storage/app/public/avatars/`) and served via a symlink from `public/storage`. The frontend constructs URLs dynamically based on the environment.

## Architecture

```
Backend Storage:
storage/app/public/avatars/  <- Actual files stored here
public/storage/              <- Symlink to storage/app/public

Database:
users.avatar = "avatars/filename.jpg"  (relative path)

Frontend URL Construction:
- Development: http://localhost:8080/storage/avatars/filename.jpg
- Production:  /storage/avatars/filename.jpg (relative to domain)
```

## Local Development Setup (Docker)

### 1. Docker Compose Configuration

The nginx container needs access to the backend storage volume. In `docker-compose.yml`:

```yaml
nginx:
  image: nginx:alpine
  container_name: myapp-nginx
  ports:
    - "8080:80"
  volumes:
    - ./backend:/var/www/html:ro
    - backend_storage:/var/www/html/storage:ro  # <-- Required for avatar access
    - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
  depends_on:
    - backend
```

### 2. Create Storage Symlink

After starting containers, create the Laravel storage symlink:

```bash
# Run as root to avoid permission issues in Docker
docker exec -u root myapp-backend bash -c "ln -sf /var/www/html/storage/app/public /var/www/html/public/storage"
```

Or via artisan (may have permission issues in Docker):

```bash
docker exec myapp-backend php artisan storage:link
```

### 3. Verify Setup

```bash
# Check symlink exists
docker exec myapp-nginx sh -c "ls -la /var/www/html/public/"
# Should show: storage -> /var/www/html/storage/app/public

# Check avatars directory is accessible
docker exec myapp-nginx sh -c "ls -la /var/www/html/storage/app/public/avatars/"

# Test URL accessibility
curl -I http://localhost:8080/storage/avatars/test.jpg
# Should return 200 OK (if file exists) or 404 (if not)
# Should NOT return 403 Forbidden
```

### 4. Restart Containers

If you modified docker-compose.yml, restart the affected containers:

```bash
docker-compose up -d nginx
```

## Production Setup (AWS LAMP)

### 1. Create Storage Symlink

SSH into your AWS server and run:

```bash
cd /var/www/html  # or your Laravel root directory
php artisan storage:link
```

This creates `public/storage` -> `storage/app/public`

### 2. Set Directory Permissions

```bash
# Ensure storage directory is writable
chmod -R 775 storage
chown -R www-data:www-data storage

# Ensure public/storage symlink is readable
chmod -R 755 public
```

### 3. Apache Configuration

Ensure Apache follows symlinks. In your virtual host or `.htaccess`:

```apache
<Directory /var/www/html/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 4. Verify Setup

```bash
# Check symlink
ls -la /var/www/html/public/ | grep storage
# Should show: storage -> /var/www/html/storage/app/public

# Test URL
curl -I https://yourdomain.com/storage/avatars/test.jpg
```

## Frontend URL Construction

The `UserAvatar.vue` component handles URL construction automatically:

```typescript
// Storage base URL - use backend server URL in dev, relative path in prod
const storageBaseUrl = computed(() => {
  const apiBaseUrl = process.env.API_BASE_URL || '/api';
  // If API_BASE_URL is a full URL (like http://localhost:8080/api), extract the base
  if (apiBaseUrl.startsWith('http')) {
    return apiBaseUrl.replace(/\/api$/, '');
  }
  // In production, storage is served from the same origin
  return '';
});

const avatarUrl = computed(() => {
  if (!props.avatar) return null;
  if (props.avatar.startsWith('preset:')) return null;
  return `${storageBaseUrl.value}/storage/${props.avatar}`;
});
```

### Environment Configuration

In `quasar.config.ts`:

```typescript
env: {
  API_BASE_URL: ctx.dev ? 'http://localhost:8080/api' : '/api',
}
```

- **Development**: `API_BASE_URL = http://localhost:8080/api` → Storage URL: `http://localhost:8080/storage/...`
- **Production**: `API_BASE_URL = /api` → Storage URL: `/storage/...` (same domain)

### Cross-Domain Production Setup

If your frontend and API are on different domains in production:

1. Set `API_BASE_URL` environment variable before building:
   ```bash
   API_BASE_URL=https://api.yourdomain.com/api npm run build
   ```

2. Or modify `quasar.config.ts`:
   ```typescript
   env: {
     API_BASE_URL: ctx.dev ? 'http://localhost:8080/api' : 'https://api.yourdomain.com/api',
   }
   ```

## Backend API Endpoints

### Upload Avatar (Profile)
```
POST /api/profile/avatar
Content-Type: multipart/form-data
Body: avatar (file)
```

### Upload Avatar (Admin - any user)
```
POST /api/users/{id}/avatar
Content-Type: multipart/form-data
Body: avatar (file)
```

### Set Preset Avatar (Admin)
```
POST /api/users/{id}/avatar/preset
Content-Type: application/json
Body: { "preset": "initials:primary" }
```

### Delete Avatar (Profile)
```
DELETE /api/profile/avatar
```

### Delete Avatar (Admin)
```
DELETE /api/users/{id}/avatar
```

## Preset Avatar Format

Preset avatars are stored as strings in the database with the `preset:` prefix:

- `preset:initials:primary` - User initials with primary color background
- `preset:initials:secondary` - User initials with secondary color
- `preset:icon:engineering:amber-9` - Engineering icon with amber background
- `preset:solid:blue-6` - Solid blue background

The `UserAvatar.vue` component parses these and renders them appropriately without hitting the storage.

## Troubleshooting

### 403 Forbidden on Avatar URLs

**Cause**: Storage symlink not accessible or nginx/Apache can't follow symlinks.

**Docker Fix**:
```bash
# Ensure storage volume is mounted in nginx container
# Check docker-compose.yml has: backend_storage:/var/www/html/storage:ro

# Recreate symlink
docker exec -u root myapp-backend bash -c "ln -sf /var/www/html/storage/app/public /var/www/html/public/storage"

# Restart nginx
docker-compose restart nginx
```

**LAMP Fix**:
```bash
# Check Apache config allows FollowSymLinks
# Check file permissions
chmod -R 755 public/storage
```

### Avatar Shows as Blue Circle (Default Initials)

**Cause**: Avatar URL not resolving correctly.

**Debug**:
1. Check browser Network tab for the avatar URL request
2. Verify the URL format matches expected pattern
3. Check if `authStore.user.avatar` has the correct value
4. Test the URL directly in browser

### Avatar Not Updating After Upload

**Cause**: Store not being updated with new avatar path.

**Fix**: Ensure the upload response updates the relevant store/state:
```typescript
// In ProfilePage.vue
if (authStore.user) {
  authStore.user.avatar = response.data.user.avatar;
}

// In UserDetailPage.vue
if (user.value) {
  user.value.avatar = response.data.user.avatar;
}
```

## Files Reference

- **Frontend Components**:
  - `frontend/app/src/components/UserAvatar.vue` - Avatar display component
  - `frontend/app/src/components/AvatarSelector.vue` - Upload/preset selection component
  - `frontend/app/src/pages/ProfilePage.vue` - Profile page with avatar
  - `frontend/app/src/pages/UserDetailPage.vue` - User detail with avatar editor

- **Backend**:
  - `backend/app/Http/Controllers/AuthController.php` - Avatar upload endpoints
  - `backend/routes/api.php` - API route definitions

- **Configuration**:
  - `docker-compose.yml` - Docker volume configuration
  - `docker/nginx/default.conf` - Nginx configuration
  - `frontend/app/quasar.config.ts` - Frontend build configuration

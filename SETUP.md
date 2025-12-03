# Quick Setup Guide

## Prerequisites Check

1. **PHP 8.2+** - Currently you have PHP 8.1.6. Laravel 11 requires 8.2+
   - Upgrade PHP or use `--ignore-platform-reqs` (development only)

2. **PostgreSQL** - Ensure PostgreSQL is running and create the database:
   ```sql
   CREATE DATABASE chat_app;
   CREATE USER chat_app_user WITH PASSWORD 'secret';
   GRANT ALL PRIVILEGES ON DATABASE chat_app TO chat_app_user;
   ```

3. **Pusher Account** - Sign up at https://pusher.com and get your credentials

## Installation Steps

### Backend

```bash
cd backend

# Install dependencies (use --ignore-platform-reqs if PHP < 8.2)
composer install --ignore-platform-reqs

# Update .env with your PostgreSQL and Pusher credentials
# (Already configured with placeholders)

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Start server
php artisan serve
```

Backend runs on: `http://localhost:8000`

### Frontend

```bash
cd frontend

# Install dependencies
npm install

# Create .env file (copy from .env.example)
# Update with your Pusher credentials

# Start dev server
npm run dev
```

Frontend runs on: `http://localhost:3000`

## Environment Variables

### Backend (.env)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chat_app
DB_USERNAME=chat_app_user
DB_PASSWORD=secret

BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

### Frontend (.env)
```env
NUXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
NUXT_PUBLIC_PUSHER_KEY=your_app_key
NUXT_PUBLIC_PUSHER_CLUSTER=mt1
```

## Testing the Application

1. Start both servers (backend on :8000, frontend on :3000)
2. Visit `http://localhost:3000`
3. Register a new account
4. Create or join a room
5. Send messages - they should appear in real-time!

## Troubleshooting

- **CORS errors**: Check `SANCTUM_STATEFUL_DOMAINS` in backend `.env`
- **Broadcasting not working**: Verify Pusher credentials match in both `.env` files
- **Database errors**: Ensure PostgreSQL is running and credentials are correct
- **PHP version errors**: Use `--ignore-platform-reqs` or upgrade PHP to 8.2+


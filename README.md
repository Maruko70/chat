# Chat App - Real-time Chat Application

A production-ready monorepo-style real-time chat application built with Laravel 11 (backend) and Nuxt 3 (frontend).

## Tech Stack

### Backend
- **Laravel 11** (PHP 8.2+)
- **PostgreSQL** database
- **Laravel Sanctum** for API authentication
- **Pusher** for real-time broadcasting
- **Laravel Broadcasting** for event broadcasting

### Frontend
- **Nuxt 3** with TypeScript
- **Vue 3** with Composition API
- **Pinia** for state management
- **Tailwind CSS** for styling
- **PrimeVue 4** for UI components
- **Laravel Echo** + **Pusher JS** for real-time updates

## Project Structure

```
chat-app/
├── backend/          # Laravel 11 application
├── frontend/         # Nuxt 3 application
└── README.md         # This file
```

## Prerequisites

- **PHP 8.2+** (Note: Current system has PHP 8.1.6, upgrade required for Laravel 11)
- **Composer** (PHP package manager)
- **Node.js 18+** and **npm**
- **PostgreSQL** database server
- **Pusher account** (for real-time features)

## Setup Instructions

### 1. Backend Setup

#### Install Dependencies
```bash
cd backend
composer install
```

#### Configure Environment
The `.env` file has been pre-configured with placeholders. Update the following values:

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

#### Generate Application Key
```bash
php artisan key:generate
```

#### Run Migrations
```bash
php artisan migrate
```

#### Start Laravel Development Server
```bash
php artisan serve
```

The backend will be available at `http://localhost:8000`

### 2. Frontend Setup

#### Install Dependencies
```bash
cd frontend
npm install
```

#### Configure Environment
Create a `.env` file in the `frontend/` directory:

```env
NUXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
NUXT_PUBLIC_PUSHER_KEY=your_app_key
NUXT_PUBLIC_PUSHER_CLUSTER=mt1
```

Replace `your_app_key` and `mt1` with your actual Pusher credentials.

#### Start Nuxt Development Server
```bash
npm run dev
```

The frontend will be available at `http://localhost:3000`

## Development Commands

### Backend (Laravel)
```bash
cd backend

# Run migrations
php artisan migrate

# Start development server
php artisan serve

# Clear cache
php artisan cache:clear
php artisan config:clear
```

### Frontend (Nuxt)
```bash
cd frontend

# Start development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

## API Endpoints

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user (requires auth)
- `GET /api/user` - Get current user (requires auth)

### Rooms
- `GET /api/chat` - List all rooms (requires auth)
- `GET /api/chat/{id}` - Get room details (requires auth)
- `POST /api/chat` - Create a new room (requires auth)
- `PUT /api/chat/{id}` - Update room (requires auth, admin only)

### Messages
- `GET /api/chat/{roomId}/messages` - Get messages for a room (requires auth, paginated)
- `POST /api/chat/{roomId}/messages` - Send a message (requires auth)

### Broadcasting
- `POST /api/broadcasting/auth` - Authenticate for private channels (requires auth)

## Database Schema

### Users
- `id`, `name`, `email`, `password`, `avatar_url`, `timestamps`

### Rooms
- `id`, `name`, `slug`, `is_public`, `settings` (JSON), `timestamps`

### Messages
- `id`, `room_id`, `user_id`, `content`, `meta` (JSON), `timestamps`

### Room User (Pivot)
- `id`, `room_id`, `user_id`, `role` (member/admin), `timestamps`

## Real-time Features

The application uses Laravel Broadcasting with Pusher for real-time message delivery:

1. When a user sends a message, it's saved to the database
2. A `MessageSent` event is dispatched and broadcast to the `private-room.{roomId}` channel
3. All users subscribed to that room receive the message in real-time via Laravel Echo

## Authentication Flow

1. User registers/logs in via the frontend
2. Backend returns a Sanctum token
3. Token is stored in Pinia store and localStorage
4. All subsequent API requests include the token in the `Authorization: Bearer {token}` header
5. For broadcasting, the token is sent to `/api/broadcasting/auth` endpoint

## Production Deployment

### Backend
1. Set `APP_ENV=production` in `.env`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Ensure PostgreSQL is properly configured
5. Set up proper Pusher credentials

### Frontend
1. Build the application: `npm run build`
2. Set environment variables for production
3. Deploy the `.output` directory to your server
4. Configure reverse proxy (nginx/Apache) if needed

## Troubleshooting

### PHP Version Issue
If you encounter errors related to PHP version:
- Laravel 11 requires PHP 8.2+
- Upgrade PHP or use `--ignore-platform-reqs` flag with Composer (not recommended for production)

### CORS Issues
- Ensure `SANCTUM_STATEFUL_DOMAINS` in backend `.env` includes your frontend URL
- Check that CORS middleware is properly configured in `bootstrap/app.php`

### Broadcasting Not Working
- Verify Pusher credentials are correct in both backend and frontend `.env` files
- Check that the broadcasting driver is set to `pusher`
- Ensure the user is authenticated when subscribing to private channels

## License

This project is open-sourced software licensed under the MIT license.


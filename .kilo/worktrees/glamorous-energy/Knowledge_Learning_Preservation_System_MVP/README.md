# Knowledge Learning Preservation System (KLPS) — PHP/MySQL MVP

## Included in this starter
- PHP + MySQL connection using PDO
- Admin and Employee login roles
- Employee dashboard
- Admin dashboard
- Knowledge repository
- Full-text knowledge search
- AI chat interface with persistent conversation history
- Knowledge contribution listing
- Knowledge version, bookmark, rating, comment, notification, and activity-log database tables prepared for the next implementation phase
- Departments and knowledge categories

## Requirements
- XAMPP (Apache + MySQL)
- PHP 8.1+ recommended
- MySQL 8+ recommended

## Setup
1. Copy the `klps_mvp` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin.
4. Import `database/schema.sql`.
5. Check `config/config.php` and update the DB credentials if needed.
6. Open:
   http://localhost/klps_mvp/

## Demo accounts
Admin:
admin@klps.local
Password123!

Employee:
employee@klps.local
Password123!

Change these credentials before real deployment.

## AI API
The AI interface is intentionally provider-neutral in this first version.
Set these constants in `config/config.php`:
- AI_API_URL
- AI_API_KEY
- AI_MODEL

Then replace the placeholder response in `employee/ai_chat.php` with the provider-specific API request.

## Recommended next development order
1. Real AI API integration
2. Knowledge creation/editing
3. Similar-problem retrieval before AI response
4. Document/file uploads
5. Bookmarks, ratings, comments
6. Notifications
7. Contribution ranking
8. Version history UI
9. Admin management CRUD
10. Reports and analytics
11. Security hardening and production deployment

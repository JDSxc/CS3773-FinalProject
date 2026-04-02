# PHP Account Management 

## What the files are (features)
- User registration
- Login with username or email
- Logout
- Customer account update page
- Admin user management page
  - Create users
  - Edit users
  - Delete users
  - Change roles between `customer` and `admin`

## Folder structure
- `config/db.php` - MySQL PDO connection (make sure to change this to match your specifics)
- `includes/auth.php` - sessions, auth guards, helper functions
- `register.php` - create customer account
- `login.php` - sign in
- `logout.php` - sign out
- `account.php` - customer profile update page
- `admin/users.php` - admin CRUD for users

## How to run in XAMPP
1. Put the `account_management` folder inside `C:\xampp\htdocs\CS3773-FinalProject` so the project folder.
2. Start Apache and MySQL in XAMPP.
3. Import the SQL file into phpMyAdmin so the `ecommerce` database and tables exist.
4. Make sure `config/db.php` uses the correct DB name, username, and password.
5. Visit:
   - `http://localhost/account_management/register.php`
   - `http://localhost/account_management/login.php`

## Note about admin access
To change an account to admin after registering, run this in phpMyAdmin:

```sql
UPDATE users
SET user_role = 'admin'
WHERE email = 'youremail@example.com';
```

## Security info
- Prepared statements with PDO
- Password hashing with `password_hash()` and `password_verify()`
- Session-based authentication
- Basic role-based access control
- Output escaping with `htmlspecialchars()`

## Match schema
This code uses the existing `users` table fields:
- `user_id`
- `first_name`
- `last_name`
- `email`
- `username`
- `user_pass`
- `user_role`
- `created`

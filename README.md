# Residence Register System

A comprehensive residence registration system built with PHP, PDO, and Tailwind CSS. This system manages residence registrations with role-based access control for different administrative levels.

## Features

### User Roles
- **Super Administrator**: Full system access, can view all data and manage all users
- **Administrator**: Can manage users and view all residence data
- **Ward Executive Officer (WEO)**: Can register and manage residences
- **Village Executive Officer (VEO)**: Can register and manage residences

### Core Functionality
- Role-based authentication and authorization
- User management (Admin can register new users)
- Residence registration and management
- Professional UI with Tailwind CSS
- Responsive design
- Data validation and security measures

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- XAMPP/WAMP/LAMP (recommended for development)

### Setup Instructions

1. **Clone or download the project files** to your web server directory (e.g., `htdocs` for XAMPP)

2. **Create the database**:
   - Open phpMyAdmin or MySQL command line
   - Import the `database.sql` file to create the database and tables
   - Or run the SQL commands manually

3. **Configure database connection**:
   - Edit `config/database.php`
   - Update the database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'residence_register';
     $username = 'root';
     $password = '';
     ```

4. **Set up the web server**:
   - Ensure your web server is running
   - Navigate to `http://localhost/dist` (or your configured domain)

## Default Login Credentials

- **Super Administrator**:
  - Username: `superadmin`
  - Password: `password`

- **Administrator**:
  - Username: `admin`
  - Password: `password`

## System Structure

### Database Tables
- `users`: Stores user information and roles
- `residences`: Stores residence registration data

### File Structure
```
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── functions.php         # Helper functions
│   ├── header.php           # Common header layout
│   └── footer.php           # Common footer layout
├── add_residence.php        # Add new residence form
├── add_user.php            # Add new user form
├── dashboard.php           # Main dashboard
├── index.php              # Entry point
├── login.php              # Login page
├── logout.php             # Logout handler
├── profile.php            # User profile management
├── reports.php            # System reports (Super Admin only)
├── residences.php         # Residence listing
├── unauthorized.php       # Access denied page
├── users.php              # User management
└── database.sql           # Database schema
```

## Usage

### For Super Administrators
- Full access to all system features
- Can view comprehensive reports
- Can manage all users and residences
- Can access system statistics

### For Administrators
- Can manage users (except super admin)
- Can view all residence data
- Can register new residences
- Access to user management features

### For WEO/VEO
- Can register new residences
- Can view their own registered residences
- Can update their profile
- Limited to their own data

## Key Features

### Security
- Password hashing using PHP's `password_hash()`
- Input sanitization and validation
- SQL injection prevention with PDO prepared statements
- Session-based authentication

### User Interface
- Modern, responsive design with Tailwind CSS
- Professional sidebar navigation
- Role-based menu items
- Interactive forms with validation
- Alert messages for user feedback

### Data Management
- Complete CRUD operations for residences
- User management with role assignment
- Data validation and error handling
- Search and filter capabilities

## Customization

### Adding New Roles
1. Update the `role` enum in the database
2. Add role display name in `includes/functions.php`
3. Update role checks in relevant files
4. Add role-specific menu items in `includes/header.php`

### Styling
- The system uses Tailwind CSS for styling
- Customize colors and layout in the header file
- Modify component styles as needed

### Database Schema
- Add new fields to tables as needed
- Update forms and validation accordingly
- Modify queries to include new fields

## Troubleshooting

### Common Issues
1. **Database Connection Error**: Check database credentials in `config/database.php`
2. **Permission Denied**: Ensure proper file permissions on web server
3. **Login Issues**: Verify default credentials or check database for user data
4. **Page Not Found**: Check web server configuration and file paths

### Debug Mode
- Enable PHP error reporting for development
- Check web server error logs
- Verify database connection and table structure

## Support

For technical support or customization requests, please refer to the system documentation or contact the development team.

## License

This project is developed for internal use. Please ensure compliance with your organization's policies and regulations.

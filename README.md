# PSC Issues - Presswick Sailing Club Issue Reporting System

A mobile-first web application for reporting and managing problems at Presswick Sailing Club. Built with PHP, MySQL, and vanilla JavaScript for simplicity and reliability.

## 🌟 Features

### Core Functionality
- **Issue Reporting**: Report problems with title, details, urgency levels, and image uploads
- **Image Management**: Upload up to 4 images per problem (5MB each, JPG/PNG/GIF)
- **Urgency Classification**: 6-level urgency system (Safety-Critical to Monitoring)
- **Status Tracking**: Open/Resolved status with automatic timestamping
- **User Roles**: Admin and User roles with appropriate permissions

### User Experience
- **Mobile-First Design**: Responsive layout optimized for mobile devices
- **Progressive Web App (PWA)**: Can be installed as an app on mobile devices
- **Clean Interface**: Professional color-coded urgency badges and status indicators
- **Floating Action Button**: Quick access to problem reporting
- **Real-time Validation**: Client and server-side form validation

### Administration
- **User Management**: Create users, reset passwords, view activity
- **Admin Dashboard**: Statistics, recent problems, user overview
- **Permission Control**: Role-based access to features
- **Bulk Operations**: Filter and sort problems by various criteria

### Security
- **Password Hashing**: bcrypt with `password_hash()` and `password_verify()`
- **CSRF Protection**: Token-based protection on all forms
- **SQL Injection Prevention**: Prepared statements throughout
- **File Security**: Upload restrictions and secure file handling
- **Session Security**: Secure session management

## 🚀 Quick Start

### Requirements
- **Web Server**: Apache with mod_rewrite
- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB
- **Extensions**: PDO, PDO_MySQL, GD, JSON

### Installation

1. **Download/Clone** the project to your web server directory:
   ```
   /xampp/htdocs/PSCReport/
   ```

2. **Import Database**:
   - Start MySQL/MariaDB
   - Import `database/schema.sql` in phpMyAdmin
   - Database `psc_issues` will be created with admin user

3. **Configure Database** (if needed):
   - Edit `config/database.php` with your MySQL credentials
   - Default settings work with XAMPP

4. **Run Setup**:
   - Visit `http://localhost/PSCReport/setup.php`
   - Follow the setup wizard
   - Delete `setup.php` when complete

5. **Login**:
   - Username: `jamiebriggs`
   - Password: `PSC1`
   - You'll be prompted to change the password

## 📱 Usage

### For Users
1. **Login** with your credentials
2. **Report Problems** using the "+" floating button
3. **View Problems** on the dashboard with filtering options
4. **Upload Images** by drag-and-drop or file selection
5. **Track Status** of your reported problems

### For Administrators
1. **Access Admin Dashboard** from the user menu
2. **Create Users** with auto-generated usernames and passwords
3. **Reset Passwords** for regular users (not admins)
4. **View Statistics** and manage all problems
5. **Monitor Activity** through the dashboard

## 🏗️ Architecture

### File Structure
```
PSCReport/
├── admin/                  # Admin-only pages
│   ├── dashboard.php      # Main admin dashboard
│   └── users/             # User management
├── api/                   # JSON API endpoints
├── assets/                # Static assets
│   ├── css/style.css     # Main stylesheet
│   └── js/app.js         # JavaScript functionality
├── config/               # Configuration files
├── database/             # SQL schemas and updates
├── includes/             # PHP includes and utilities
├── problems/             # Problem management pages
├── uploads/              # File uploads (with security)
└── index.php            # Main dashboard
```

### Database Schema

#### Users Table
- Primary key, full name, username (auto-generated)
- Role (ADMIN/USER), password hash, activity status
- Password change requirements, login tracking

#### Problems Table
- Title, details, status (OPEN/RESOLVED)
- Urgency tags (SET field), image URLs (JSON)
- Reporter, resolver, timestamps

### Urgency Levels
1. **Safety-Critical** (Red) - Immediate attention required
2. **High (Blocks Use)** (Orange) - Prevents normal operation
3. **Medium (Workaround)** (Yellow) - Has workaround available
4. **Low (Minor)** (Green) - Minor inconvenience
5. **Cosmetic** (Gray) - Appearance/aesthetic issues
6. **Monitoring** (Teal) - Watch for patterns/trends

## 🔧 Configuration

### Environment Settings
Edit `config/database.php` for:
- Database connection details
- Upload directory paths
- File size and type restrictions
- Application constants

### Security Settings
- CSRF tokens on all forms
- Secure session configuration
- File upload restrictions
- Access control via `.htaccess`

### Customization
- Modify `assets/css/style.css` for styling
- Update `manifest.json` for PWA settings
- Adjust urgency levels in `includes/utils.php`
- Change file upload limits in configuration

## 🛡️ Security Features

- **Input Validation**: Server and client-side validation
- **Output Escaping**: All dynamic content escaped
- **File Security**: Upload type/size restrictions, unique filenames
- **Access Control**: Role-based permissions
- **Session Security**: Regeneration, secure cookies
- **Database Security**: Prepared statements, input sanitization

## 📊 Default Credentials

**Admin Account:**
- Username: `jamiebriggs`
- Password: `PSC1` (must change on first login)

**New User Pattern:**
- Username: Generated from full name (no spaces, lowercase)
- Password: `PSC{user_id}` (e.g., PSC2, PSC3)
- Must change password on first login

## 🔄 Updates & Maintenance

### Database Updates
- Use `database/update_*.sql` files for schema changes
- Always backup before applying updates

### File Permissions
- Ensure `uploads/` directory is writable (755)
- Protect sensitive files via `.htaccess`

### Backup Strategy
- Regular database backups
- Include uploaded images in backup routine
- Test restore procedures periodically

## 🐛 Troubleshooting

### Common Issues

**Login Problems:**
- Verify database connection
- Check user credentials in database
- Clear browser cache/cookies

**File Upload Issues:**
- Check PHP upload limits
- Verify directory permissions
- Confirm file types are allowed

**Database Errors:**
- Check MySQL service status
- Verify connection credentials
- Review error logs

### Debug Mode
- Check PHP error logs
- Enable display_errors for development
- Use browser developer tools for JavaScript issues

## 🚀 Future Enhancements

### Planned Features
- Email notifications for critical issues
- Problem assignment and workflow
- File storage migration to cloud (S3)
- Advanced reporting and analytics
- Mobile push notifications
- Integration with maintenance systems

### API Expansion
- RESTful API for mobile apps
- Webhook support for integrations
- Bulk import/export functionality

## 📄 License

This project is developed for Presswick Sailing Club. All rights reserved.

## 👥 Support

For technical support or feature requests, contact the development team or your system administrator.

---

**Version**: 1.0.0  
**Last Updated**: September 2025  
**Developed For**: Presswick Sailing Club
# 🛡️ Pehredar - Guard Management System

A comprehensive web-based solution for managing security personnel, clients, and support tickets with an intuitive interface and robust features.

![Project Banner](https://via.placeholder.com/1200x400/1a237e/ffffff?text=Pehredar+Guard+Management+System)

## ✨ Features

### 👥 User Management
- Create and manage user accounts (Admins, Supervisors, Guards)
- Role-based access control with JWT authentication
- User activity tracking and audit logs
- Profile management with photo uploads

### 🏢 Client & Site Management
- Onboard and manage client organizations
- Site and location management
- Contract and service level tracking
- Client portal access with separate authentication

### 🎫 Ticketing System
- Create and track support tickets
- Assign tickets to staff members
- Priority and status tracking
- Internal notes and communication system

### 📊 Advanced Reporting
- Attendance and shift reports
- Salary calculation and disbursement
- Incident reporting with photo documentation
- Performance analytics and dashboards
- Exportable reports (PDF, Excel)

### 💰 Salary Management
- Automated salary calculations
- Advance salary system
- Deduction management
- Salary slip generation
- Bulk salary operations

### 📱 Mobile App Support
- RESTful APIs for mobile applications
- QR code generation for check-ins
- Photo upload and activity tracking
- Real-time notifications

### 🔧 Additional Features
- **JWT Authentication**: Secure token-based authentication
- **Role-Based Access Control**: Differentiated access for Admins, Guards, and Clients
- **Employee Management**: Complete employee lifecycle management
- **Client Onboarding**: Streamlined client registration and management
- **Ticket Management**: Comprehensive support ticket system
- **Real-time Activity Tracking**: GPS-based activity logging with photos
- **Dynamic Settings**: Configurable company details and branding
- **PDF Generation**: ID cards, salary slips, and reports
- **Cache Management**: Optimized database queries and caching
- **License Management**: Built-in license validation system

## 🚀 Getting Started

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache/Nginx)
- Composer
- XAMPP/WAMP/LAMP stack

### Installation

1. **Clone the repository**
   ```bash
   git clone git@github.com:aakash688/Pehredar.git
   cd Pehredar
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Setup Database**
   - Create a new MySQL database
   - Import the database schema:
     ```bash
     mysql -u username -p database_name < Final.sql
     ```

4. **Configure Application**
   - Copy `config-sample.php` to `config-local.php`
   - Update database credentials and application settings
   - Generate JWT secret: `php generate_jwt_secret.php`

5. **Set Permissions**
   ```bash
   chmod -R 755 uploads/
   chmod -R 755 cache/
   chmod -R 755 logs/
   ```

6. **Access the Application**
   - Navigate to `http://localhost/Patrol/` in your browser
   - Follow the installation wizard

## 📁 Project Structure

```
Patrol/
├── actions/                 # Controller files for various operations
├── admin/                   # Admin panel functionality
├── adminpannel/            # Admin panel templates and APIs
├── api/                    # API endpoints
├── cache/                  # Application cache
├── helpers/                # Helper classes and utilities
├── mobileappapis/          # Mobile app API endpoints
├── schema/                 # Database schema files
├── templates/              # PDF and email templates
├── UI/                     # User interface files
├── uploads/                # File uploads directory
├── vendor/                 # Composer dependencies
└── config.php              # Main configuration file
```

## 🔧 Configuration

### Database Configuration
Update `config-local.php` with your database credentials:
```php
$config = [
    'installed' => true,
    'base_url' => 'http://localhost/Patrol/',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'your_database',
        'user' => 'your_username',
        'pass' => 'your_password'
    ],
    // ... other settings
];
```

### JWT Configuration
Generate a secure JWT secret:
```bash
php generate_jwt_secret.php
```

## 📱 Mobile App Integration

The system includes comprehensive mobile APIs for:
- Guard check-in/check-out
- Activity logging with photos
- Ticket management
- Client notifications
- Real-time updates

## 🔒 Security Features

- JWT-based authentication
- Role-based access control
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- Secure file uploads
- Audit logging

## 📊 API Documentation

### Authentication Endpoints
- `POST /mobileappapis/guards/login.php` - Guard login
- `POST /mobileappapis/clients/login.php` - Client login

### Activity Management
- `POST /mobileappapis/guards/checkin.php` - Check-in
- `POST /mobileappapis/guards/checkout.php` - Check-out
- `POST /mobileappapis/guards/activity.php` - Log activity

### Ticket Management
- `GET /mobileappapis/tickets/list.php` - List tickets
- `POST /mobileappapis/tickets/create.php` - Create ticket
- `PUT /mobileappapis/tickets/update.php` - Update ticket

## 🛠️ Development

### Code Structure
- **MVC Pattern**: Controllers in `actions/`, models in `models/`
- **Helper Classes**: Utility functions in `helpers/`
- **API Layer**: RESTful endpoints in `mobileappapis/`
- **Database Layer**: Schema files in `schema/`

### Adding New Features
1. Create controller in `actions/`
2. Add database schema in `schema/`
3. Create API endpoints in `mobileappapis/`
4. Update UI components in `UI/`

## 📝 Recent Updates

### Version 2.0
- Enhanced salary management system
- Improved mobile app APIs
- Advanced reporting features
- Better security implementation
- Optimized database queries

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 📧 Support

For support and queries:
- Create an issue on GitHub
- Contact: aakashsingh688@gmail.com
- Documentation: [Wiki](https://github.com/aakash688/Pehredar/wiki)

## 🏆 Credits

Built with ❤️ by Aakash Singh

---

**Pehredar** - Your trusted partner in security management

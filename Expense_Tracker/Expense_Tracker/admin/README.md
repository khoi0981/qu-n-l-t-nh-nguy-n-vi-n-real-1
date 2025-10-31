# Environmental Protection Project Administration

## Overview
This project is designed to manage an environmental protection initiative, providing an administrative interface for managing volunteers, events, and news related to the project.

## Features
- User authentication for secure access to the admin panel.
- Dashboard displaying key statistics and quick links.
- Management interfaces for:
  - Volunteers: View, add, edit, and delete volunteer records.
  - Events: Create, update, and delete events related to the project.
  - News: Manage news articles, including addition, editing, and deletion.

## Project Structure
```
env-protection-admin
├── config
│   └── db.php               # Database connection settings
├── public
│   ├── index.php            # Entry point of the application
│   ├── login.php            # User login form and processing
│   ├── logout.php           # User logout handling
│   ├── dashboard.php        # Admin dashboard overview
│   ├── manage_volunteers.php # Volunteer management interface
│   ├── manage_events.php     # Event management interface
│   ├── manage_news.php       # News management interface
│   └── assets
│       ├── css
│       │   └── style.css     # CSS styles for public pages
│       └── js
│           └── main.js       # JavaScript functionality for public pages
├── includes
│   ├── header.php            # Header section HTML
│   ├── footer.php            # Footer section HTML
│   └── auth.php              # User authentication logic
├── sql
│   └── schema.sql            # SQL statements for database schema
└── README.md                 # Project documentation
```

## Setup Instructions
1. Clone the repository to your local machine.
2. Navigate to the project directory.
3. Configure the database connection in `config/db.php`.
4. Import the database schema from `sql/schema.sql` into your database.
5. Access the application via `public/index.php`.

## Usage
- Visit the login page to authenticate as an administrator.
- Once logged in, you will be redirected to the dashboard.
- Use the navigation links to manage volunteers, events, and news articles.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License.
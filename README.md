# Physio DB Forms Application

## Description

This application is a web-based system for managing patient information and various physiotherapy assessment forms. It allows clinicians to record patient data, fill out evaluation forms, and store this information in a MySQL database.

## Prerequisites

Before you begin, ensure you have the following installed:

*   **PHP**: Version 7.4 or higher (including the `pdo_mysql` extension).
*   **MySQL**: Version 5.7 or higher.
*   **Web Server**: Apache or Nginx (or any other web server that can serve PHP applications).
*   **Git**: For cloning the repository.

## Installation Steps

1.  **Clone the Repository:**
    ```bash
    git clone <repository_url>
    cd <repository_directory>
    ```
    Replace `<repository_url>` with the actual URL of the repository and `<repository_directory>` with your desired local directory name.

2.  **Set Up the Database:**
    *   Ensure your MySQL server is running.
    *   Create the database and its tables using the provided SQL schema file. You can do this using the MySQL command-line client or a GUI tool like phpMyAdmin. The schema file is `physio_db_schema.sql`.
        ```bash
        mysql -u your_mysql_user -p < physio_db_schema.sql
        ```
        Enter your MySQL user's password when prompted. This will create the `physio_db` database and all necessary tables.

3.  **Configure Application Database Connection:**
    The application needs to know how to connect to your MySQL database. Credentials can be set via environment variables (recommended for production) or directly in `includes/db_config.php` (for development).

    *   **Environment Variables (Recommended):**
        Set the following environment variables in your web server configuration (e.g., Apache `.htaccess` or `httpd.conf`, Nginx `fastcgi_param`) or your system environment:
        *   `DB_HOST`: Your database host (e.g., `localhost` or `127.0.0.1`).
        *   `DB_NAME`: The database name (should be `physio_db` as created by the script).
        *   `DB_USER`: Your MySQL username.
        *   `DB_PASSWORD`: Your MySQL password.

    *   **Direct Configuration (Development Only):**
        If you are not using environment variables, you can edit the fallback values in `includes/db_config.php`:
        ```php
        // Example for local development if environment variables are not set:
        define('DB_HOST', getenv('DB_HOST') ?: 'your_localhost'); // Replace 'your_localhost'
        define('DB_USER', getenv('DB_USER') ?: 'your_db_user');   // Replace 'your_db_user'
        define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'your_db_password'); // Replace 'your_db_password'
        define('DB_NAME', getenv('DB_NAME') ?: 'physio_db');
        ```
        **Important:** Do not commit actual credentials to version control if hardcoded in this file.

4.  **Web Server Configuration:**
    *   Configure your web server's document root to point to the root directory of the cloned project (where `index.php` is located).
    *   **Apache Example (Virtual Host):**
        ```apache
        <VirtualHost *:80>
            ServerName yourdomain.local
            DocumentRoot /path/to/your/repository_directory

            <Directory /path/to/your/repository_directory>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
            </Directory>

            ErrorLog ${APACHE_LOG_DIR}/error.log
            CustomLog ${APACHE_LOG_DIR}/access.log combined
        </VirtualHost>
        ```
        Ensure `mod_rewrite` is enabled if the application uses URL rewriting (though the current structure seems to rely on direct PHP file access).
    *   **Nginx Example (Server Block):**
        ```nginx
        server {
            listen 80;
            server_name yourdomain.local;
            root /path/to/your/repository_directory;

            index index.php index.html index.htm;

            location / {
                try_files $uri $uri/ /index.php?$query_string;
            }

            location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass unix:/var/run/php/phpX.Y-fpm.sock; // Adjust to your PHP-FPM version
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
            }
        }
        ```

5.  **File Permissions (If Needed):**
    Ensure the web server has read access to all project files. If there are any directories where the application needs to write data (e.g., for uploads, though not currently a feature), those would need write permissions for the web server user (e.g., `www-data`, `apache`). Currently, no such specific write permissions seem necessary beyond standard session handling by PHP.

## Running the Application

Once the installation and configuration are complete, you should be able to access the application by navigating to the configured server name or IP address in your web browser (e.g., `http://yourdomain.local` or `http://localhost/repository_directory/` depending on your setup).

The main entry point is `index.php`.

## Troubleshooting

*   **Database Connection Errors:**
    *   Double-check your database credentials in `includes/db_config.php` or your environment variables.
    *   Ensure the MySQL server is running and accessible from the web server.
    *   Verify the `physio_db` database and its tables were created correctly by `physio_db_schema.sql`.
*   **PHP Errors:**
    *   Check your web server's PHP error logs for detailed error messages. The location of these logs varies depending on your OS and web server configuration.
*   **Page Not Found (404 Errors):**
    *   Verify your web server's document root is correctly pointing to the project directory.
    *   Ensure URL rewriting (if applicable for your setup, though not strictly required by the current app structure) is configured correctly.
*   **Form Submission Issues:**
    *   Use your browser's developer tools (Network tab) to inspect the request and response when submitting forms. This can help identify issues with data being sent or errors returned by `php/save_submission.php`.

OptiMeal 🍏🛒
Smart Diet & Shopping List Generator with Automated Supermarket Discount Integration

📖 About the Project
OptiMeal is a dynamic web application designed to solve a common real-world problem: maintaining a strict macronutrient diet (such as a high-protein body recomposition plan) can be financially demanding.

Unlike standard calorie trackers or discount aggregators, OptiMeal bridges both worlds. It calculates personalized daily nutritional targets and automatically generates a smart shopping list by cross-referencing required ingredients with real-time, scraped supermarket discounts.

This project was developed as a high school graduation thesis (Maturita 2027), demonstrating full-stack development, database architecture, and background task automation.

✨ Key Features
Nutritional Targeting: Users can set specific daily macronutrient goals (e.g., 2400 kcal, 160g protein).

Automated Web Scraping: A standalone background script (update_slevy.php) runs via CRON jobs to extract digital flyer data from supermarket websites, completely automating the pricing database.

Smart Shopping List: The algorithm prioritizes discounted items (SQL JOIN logic) and explicitly directs the user to the most cost-effective stores for their groceries.

Modern UI & Personalization: A fully responsive frontend featuring a native Theme Switcher (Dark/Light/System) utilizing localStorage, and a Language Switcher (EN/CZ) powered by PHP sessions.

Exam Safe Mode: Includes a dedicated admin feature to inject static demo data, ensuring a flawless presentation during offline or restricted-network defense scenarios.

🛠️ Built With
Backend: PHP 8.x

Database: MySQL (Strictly using PDO & Prepared Statements for security)

Frontend: HTML5, CSS3, Bootstrap 5

Automation: Web Scraping (PHP cURL, DOMDocument) & Server CRON Jobs

Version Control: Git & GitHub

🚀 Getting Started
Prerequisites
A local server environment like XAMPP or a standard PHP web hosting.

PHP 8.0 or higher.

MySQL database.

Installation
Clone the repository:

Bash
git clone https://github.com/yourusername/OptiMeal.git
Import the database schema:

Open phpMyAdmin and create a new database named optimeal.

Import the database/schema.sql file to create the potraviny, akce_obchody, and uzivatele tables.

Configure the database connection:

Rename config/db.example.php to config/db.php.

Update the PDO credentials (host, dbname, username, password) to match your environment.

Set up the CRON job (Production):

Point your server's CRON to execute scripts/update_slevy.php periodically (e.g., weekly on Wednesdays at 03:00 AM).

🛡️ Security
This application implements strict security measures against common web vulnerabilities. All database interactions are handled via PDO prepared statements to prevent SQL Injection, and htmlspecialchars() is enforced on frontend outputs to prevent Cross-Site Scripting (XSS).

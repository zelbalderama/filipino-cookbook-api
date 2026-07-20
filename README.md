# Filipino Cookbook API

A simple secured REST API for managing and viewing Filipino food recipes. The API is built with **PHP**, **Slim Framework**, and **MySQL**.

## Features

- View all Filipino food records
- Search for food by name
- View a specific food by ID
- View available food categories
- View available ingredients
- Add a new food record
- Bearer token protection for all `/api` endpoints

## Requirements

Before running the project, install the following:

- [XAMPP](https://www.apachefriends.org/)
- PHP 8 or later
- Composer
- Postman or another API testing application

## Installation and Setup

### 1. Place the project inside the XAMPP `htdocs` folder

The project folder should be located here:

```text
C:\xampp\htdocs\filipino-cookbook-api
```

When cloning from GitHub, use:

```bash
cd C:\xampp\htdocs
git clone https://github.com/YOUR-USERNAME/filipino-cookbook-api.git
cd filipino-cookbook-api
```

Replace `YOUR-USERNAME` with the GitHub username of the repository owner.

### 2. Install the PHP dependencies

Open Command Prompt inside the project folder and run:

```bash
composer install
```

When Composer is not installed globally, run:

```bash
C:\xampp\php\php.exe composer.phar install
```

### 3. Set up the database

1. Open the **XAMPP Control Panel**.
2. Start **MySQL**.
3. Open phpMyAdmin:

```text
http://localhost/phpmyadmin
```

4. Create a new database named:

```text
filipino_cookbook_api
```

5. Open the newly created database.
6. Select the **Import** tab.
7. Import the `.sql` database file found inside the project's `db` folder.

The default database settings used by the project are:

```text
Host: localhost
Database: filipino_cookbook_api
Username: root
Password: none
```

## Running the API

Start the API manually through Command Prompt.

Open Command Prompt inside:

```text
C:\xampp\htdocs\filipino-cookbook-api
```

Then run:

```bash
C:\xampp\php\php.exe -S localhost:8080 -t public
```

When the server starts successfully, open:

```text
http://localhost:8080
```

To stop the server, return to Command Prompt and press:

```text
Ctrl + C
```

## API Authentication

All endpoints beginning with `/api` require a Bearer token.

Add this header in Postman:

```text
Authorization: Bearer dmmmsu-cookbook-token-2026
```

The home endpoint `/` is public and does not require a token.

> The included token is intended for demonstration or school-project use. For production use, store secrets in environment variables instead of directly in the source code.

## Available Endpoints

| Method | Endpoint | Description | Authentication |
|---|---|---|---|
| `GET` | `/` | Displays the API welcome message | Not required |
| `GET` | `/api/foods` | Returns all food records | Required |
| `GET` | `/api/foods/{id}` | Returns one food using its numeric ID | Required |
| `GET` | `/api/foods/search/{name}` | Searches for food by name | Required |
| `GET` | `/api/categories` | Returns all food categories | Required |
| `GET` | `/api/ingredients` | Returns all ingredients | Required |
| `POST` | `/api/foods` | Adds a new food record | Required |

## Example Requests

### View all foods

```http
GET http://localhost:8080/api/foods
Authorization: Bearer dmmmsu-cookbook-token-2026
```

### Search for a food

```http
GET http://localhost:8080/api/foods/search/adobo
Authorization: Bearer dmmmsu-cookbook-token-2026
```

### View a food by ID

```http
GET http://localhost:8080/api/foods/1
Authorization: Bearer dmmmsu-cookbook-token-2026
```

### Add a new food

Use the following URL and method in Postman:

```http
POST http://localhost:8080/api/foods
```

Add these headers:

```text
Authorization: Bearer dmmmsu-cookbook-token-2026
Content-Type: application/json
```

Example JSON body:

```json
{
  "food_name": "Chicken Adobo",
  "category_id": 1,
  "origin_id": 1,
  "instructions": "Combine the ingredients, simmer until the chicken is tender, and serve with rice.",
  "ingredient_ids": [1, 2, 3]
}
```

Use existing category, origin, and ingredient IDs from the database.

## Common Problems

### Database connection failed

Make sure that:

- MySQL is running in XAMPP.
- The database is named `filipino_cookbook_api`.
- The SQL file was imported successfully.
- The MySQL username is `root` and the password is empty, unless the source code was updated.

### Composer dependencies are missing

Run:

```bash
composer install
```

### Port 8080 is already in use

Run the API using another port, such as:

```bash
C:\xampp\php\php.exe -S localhost:8081 -t public
```

Then access:

```text
http://localhost:8081
```

## Project Structure

```text
filipino-cookbook-api/
├── db/                 # SQL database file
├── public/             # Public API entry point
│   └── index.php
├── vendor/             # Composer dependencies
├── composer.json       # PHP package configuration
├── composer.lock       # Locked package versions
└── README.md           # Project setup and usage guide
```

## Notes

- Keep MySQL running while using the API.
- Keep the Command Prompt server window open while testing the endpoints.
- Use Postman to test endpoints that require headers or JSON request bodies.
- Do not upload passwords, private tokens, or `.env` files to a public repository.

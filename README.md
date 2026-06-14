# 🌍 Travel Booking System

A full-stack travel booking web application built using **PHP, JavaScript, and MySQL**. Users can search and book flights/hotels, manage reservations, and view booking history through a clean and responsive interface.

---

## ✨ Features

* User Registration & Login
* Flight & Hotel Search
* Booking System with Confirmation
* User Dashboard
* Admin Panel
* Booking History
* Payment Simulation
* Responsive UI
* Dynamic Search & Filtering

---

## 🛠 Tech Stack

### Frontend

* HTML
* CSS / Bootstrap
* JavaScript
* AJAX

### Backend

* PHP

### Database

* MySQL

### Optional Integrations

* [Amadeus API](https://developers.amadeus.com/?utm_source=chatgpt.com)
* [Stripe Sandbox](https://stripe.com/docs/testing?utm_source=chatgpt.com)

---

## 📂 System Modules

### 👤 User Side

* Register/Login
* Search Flights & Hotels
* Book Reservations
* View Booking History
* Manage Profile

### 🛠 Admin Side

* Manage Users
* Manage Flights & Hotels
* View All Bookings
* Update Booking Status

---

## 🗄 Database Tables

* users
* flights
* hotels
* bookings
* payments

---

## 🚀 Installation

1. Clone the repository

```bash
git clone https://github.com/yourusername/travel-booking-system.git
```

2. Move the project folder to:

```plaintext id="olakp8"
htdocs/ (XAMPP)
```

3. Import the MySQL database

* Open phpMyAdmin
* Create a database
* Import the provided `.sql` file

4. Configure database connection

```php
$conn = mysqli_connect("localhost", "root", "", "travel_booking");
```

5. Start Apache & MySQL in XAMPP

6. Open in browser

```plaintext id="olakp9"
http://localhost/travel-booking-system
```

---

## 📸 Screenshots

<img width="1920" height="909" alt="image" src="https://github.com/user-attachments/assets/8f5bb774-fd47-4283-ade6-a3eec10ff6f8" />
<img width="1906" height="918" alt="image" src="https://github.com/user-attachments/assets/aa231e78-385b-4fd7-a876-62506a6f80b6" />
<img width="1903" height="919" alt="image" src="https://github.com/user-attachments/assets/d3cf6fa7-d8e2-4b77-8e0c-0d9ff8be00e2" />



---

## 🎯 Purpose

This project was created as a portfolio project to showcase:

* Full-Stack Web Development
* Authentication System
* CRUD Operations
* Database Management
* API Integration
* Real-World Booking Workflow

---

## 🔥 Future Improvements

* Real API Integration
* Online Payments
* Email Confirmation
* PDF Booking Receipt (DONE✅)
* Booking Analytics Dashboard
* Dynamic Pricing System

---

## 👨‍💻 Author

Developed by **mark mark**

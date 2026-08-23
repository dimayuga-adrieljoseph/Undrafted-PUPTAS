<p align="center">
    <img src="puptas/public/assets/images/pup_logo.png" alt="PUP Logo" style="width: 150px;">
</p>

<h1 align="center" style="color: white;">PUP Taguig Admission System (PUPTAS)</h1>

<p align="center"><em>Seamless, Smooth, and Streamlined Admissions</em></p>
<p align="center">
    <a href="mailto:admissionteam@puptas.online">
        <img src="https://img.icons8.com/ios-glyphs/30/800000/email.png" alt="Email">
    </a>
    <a href="https://puptas.online/">
        <img src="https://img.icons8.com/ios-filled/30/800000/domain.png" alt="Website">
    </a>
    <br>
    <strong>Developed by the JAMS Team, Enhanced by Team Undrafted</strong>
</p>

---

## 📘 About PUPT Admission System

The **PUP Taguig Admission System (PUPTAS)** is a web-based platform designed to simplify the student admission process. It automates tasks such as account creation, document submission, and communication, making the process more efficient for both applicants and staff.

**Secure, reliable, and easy-to-navigate.**

[Visit the PUPT Admission System's official website here.](https://puptas.online/)

---

## 🔑 Sample Credentials

Use the following credentials for demo access:

- **Username/Email:**
- **Password:**

---

## ✨ Features

**📂 Document Management and Verification**

Effortlessly manage and verify documents. Applicants submit required files online, while admin staff and evaluators can verify submissions, ensuring safe storage and faster processing.

**🔔 Real-Time Notifications**

Automated email alerts keep applicants informed on key updates like interview schedules, pending requirements, and admission confirmations, reducing miscommunication.

**📊 Application Tracking**

Applicants can monitor their status in real-time—from submission to evaluation—boosting transparency and user satisfaction while minimizing the need for manual follow-ups.

**📅 Interview Scheduling**

Interviewers can schedule appointments directly within the system. Applicants receive automatic notifications to ensure clarity in scheduling.

**📈 Report Generation**

Admins can generate reports detailing the number of applications, pending evaluations, scheduled interviews, and overall admission progress—optimizing decision-making and tracking.

---

## ⚙️ Local Development Setup

### Prerequisites

Make sure the following are installed before proceeding:

| Tool | Minimum Version | Download |
|------|----------------|---------|
| PHP | 8.2+ | https://www.php.net/downloads |
| Composer | 2.x | https://getcomposer.org |
| Node.js | 20+ | https://nodejs.org |
| npm | 10+ | Included with Node.js |
| MySQL | 8.0+ | https://dev.mysql.com/downloads |
| Git | latest | https://git-scm.com |

> **Optional:** Docker Desktop (for the containerized setup below)

---

### Method 1 — Standard Local Setup

**1. Clone the repository**
`ash
git clone https://github.com/your-org/Undrafted-PUPTAS.git
cd Undrafted-PUPTAS/puptas
`

**2. Install PHP dependencies**
`ash
composer install
`

**3. Install Node.js dependencies**
`ash
npm install
`

**4. Configure environment**
`ash
cp .env.example .env
php artisan key:generate
`
Then open .env and fill in at minimum:
- DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- APP_URL

**5. Set up the database**

Create a MySQL database named puptas, then run:
`ash
php artisan migrate
php artisan db:seed   # optional: seed sample data
`

**6. Generate Passport keys (OAuth2)**
`ash
php artisan passport:keys
`

**7. Create storage symlink**
`ash
php artisan storage:link
`

**8. Run the development servers**

Open three separate terminals:
`ash
# Terminal 1 — Laravel backend
php artisan serve

# Terminal 2 — Vite frontend (hot reload)
npm run dev

# Terminal 3 — Queue worker (for emails and background jobs)
php artisan queue:listen --queue=high,emails,default
`

Visit: **http://localhost:8000**

---

### Method 2 — Docker Compose (Recommended)

Runs the full stack (app + MySQL + Redis + worker + scheduler) with one command.

**1. Clone and configure**
`ash
git clone https://github.com/your-org/Undrafted-PUPTAS.git
cd Undrafted-PUPTAS
cp puptas/.env.example puptas/.env
# Edit puptas/.env — set APP_KEY and any required service keys
`

**2. Start all services**
`ash
docker compose up -d
`

**3. Run migrations inside the container**
`ash
docker compose exec app php artisan migrate
docker compose exec app php artisan passport:keys
docker compose exec app php artisan storage:link
`

Visit: **http://localhost:8080**

**Useful Docker commands:**
`ash
docker compose logs -f app        # Tail app logs
docker compose down               # Stop all services
docker compose down -v            # Stop and remove volumes (full reset)
`

---

### Key Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| APP_KEY | Laravel encryption key | *(generated)* |
| APP_ENV | Environment mode | local |
| DB_HOST | MySQL host | 127.0.0.1 |
| DB_DATABASE | Database name | puptas |
| DB_USERNAME | Database user | oot |
| DB_PASSWORD | Database password | *(empty)* |
| REDIS_HOST | Redis host | 127.0.0.1 |
| MAIL_MAILER | Mail driver | log *(safe for local)* |
| RESEND_API_KEY | Resend email API key | *(required for emails)* |
| FILESYSTEM_DISK | Storage driver | local |

> See .env.example for the full list of all environment variables.

---

## 🐳 Infrastructure Overview

| File | Purpose |
|------|---------|
| puptas/Dockerfile | Main app image (PHP 8.4 + Apache, multi-stage) |
| puptas/Dockerfile.worker | Queue worker image |
| puptas/Dockerfile.scheduler | Task scheduler image |
| docker-compose.yml | Local multi-service orchestration |
| ailway.json | Production deployment config (Railway) |
| ailway.worker.json | Worker service deployment config |
| ailway.scheduler.json | Scheduler service deployment config |

---

## 👥 Meet Team Undrafted

| Name                     | GitHub Profile |
| ------------------------ | -------------- |
| Dimayuga, Adriel Joseph  |                |
| Managbanag, John Mark    |                |
| Manicio, Dion            |                |
| Dazo, Rollan             |                |

---

## 🌐 Connect with Us

<p align="center">
  <!-- add info here -->
</p>

---

## 🛠️ Technologies Used

Here are the modern technologies we used to build PUPTAS:

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/SCSS-CC6699?style=for-the-badge&logo=sass&logoColor=white" alt="SCSS">
  <img src="https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white" alt="HTML5">
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/VS%20Code-007ACC?style=for-the-badge&logo=visual-studio-code&logoColor=white" alt="VS Code">
  <img src="https://img.shields.io/badge/Postman-FF6C37?style=for-the-badge&logo=postman&logoColor=white" alt="Postman">
  <img src="https://img.shields.io/badge/Hostinger-5333ED?style=for-the-badge&logo=hostinger&logoColor=white" alt="Hostinger">
  <img src="https://img.shields.io/badge/GitHub-181717?style=for-the-badge&logo=github&logoColor=white" alt="GitHub">
  <img src="https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
  <img src="https://img.shields.io/badge/Tesseract-5DAB44?style=for-the-badge&logo=tesseract&logoColor=white" alt="Tesseract OCR">
</p>

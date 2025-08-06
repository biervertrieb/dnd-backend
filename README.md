# 🧙 DnD Journal & Loot Tracker Backend

This is the backend server for a nerdy Dungeons & Dragons journal and group loot tracker app.  
It provides a RESTful JSON API built with [Slim Framework](https://www.slimframework.com/), no database required.

---

## 🚀 Installation

### ✅ Requirements

- PHP ≥ 8.1
- [Composer](https://getcomposer.org/) installed
- Git installed
- Optional: [Xdebug](https://xdebug.org/) for debugging
- Optional: PHPUnit for testing

---

### 📥 1. Clone the repository

```bash
git clone git@github.com:your-username/dnd-backend.git
cd dnd-backend
```

> Or use HTTPS:
>
> ```bash
> git clone https://github.com/your-username/dnd-backend.git
> ```

---

### 📦 2. Install dependencies

```bash
composer install
```

---

### 🗃️ 3. Create data storage folder

```bash
mkdir -p data
chmod 775 data
```

---

### 🌐 4. Start the development server

```bash
php -S localhost:8080 -t public
```

Then visit: [http://localhost:8080](http://localhost:8080)

---

### 🧪 5. Run tests

```bash
./vendor/bin/phpunit
```

---

## 🧱 Project Structure

```
.
├── public/         # Entry point (index.php)
├── src/            # App logic (e.g. JournalService.php)
├── tests/          # PHPUnit tests
├── data/           # JSON storage (ignored by Git)
├── vendor/         # Composer dependencies
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 🛠 Tech Stack

- **Slim Framework** – Lightweight REST API routing
- **PHPUnit** – Unit testing
- **File-based JSON storage** – No database required

---

## 💡 Roadmap (Planned Features)

- [x] Journal entries (Markdown support + internal linking)
- [ ] Group loot tracking
- [ ] User roles: DM / Player with visibility rules
- [ ] Admin panel
- [ ] Authentication

---

## 🧙 License

MIT – do what you want, just don’t claim you wrote it all 😄

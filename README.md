# Recycling Incentive App - Backend API

This is the central REST API for the **Recycling Incentive App**, a **Tubitak 2209-A** research project.

It serves as the single source of truth, handling all business logic, data persistence, and verification rules for the ecosystem.

---

## ⚙️ Core Responsibilities

- **Authentication:** Managed via **Laravel Sanctum**. Handles secure user registration, login, and token management for both Mobile users and Admins.
- **Recycling Logic:**
    - Validates recycling sessions (geo-fencing checks, timeout rules).
    - Processes item submissions and flags duplicates for review.
    - Calculates points and updates user balances.
- **File Storage:** Handles uploads of **Proof Photos** for flagged transactions using Laravel's Storage facade.
- **Data Management:** Stores all resources (Bins, Items, Categories, Users) in a relational database.

---

## 🛠️ Tech Stack

- **Laravel 12**
- **PostgreSQL**
- **Sanctum** (Auth)

---

## 🔗 Links & Related Repositories

- 📦 **Mobile App (Expo):** https://github.com/Beytullahp42/recycling-incentive-app-expo
- 📦 **Admin Dashboard:** https://github.com/Beytullahp42/recycling-incentive-app-admin-frontend

- 🌐 **Live API Base URL:** https://ria-backend.beytullahp.com

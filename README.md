# 🎓 Smart Event Management System

A full-stack college event management platform built with **Oracle Database**, **PHP (OCI8)**, and a vanilla **HTML/CSS/JS** frontend. The system automates event creation, student registration, attendance tracking, expense monitoring, feedback collection, and real-time analytics — all driven by Oracle triggers and relational logic.

---

## 📋 Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Database Schema](#database-schema)
- [Triggers](#triggers)
- [Sequences](#sequences)
- [Views](#views)
- [Project Structure](#project-structure)
- [Setup & Installation](#setup--installation)
- [Default Credentials](#default-credentials)
- [Seed Data](#seed-data)
- [API Reference](#api-reference)
- [System Flow](#system-flow)
- [Known Notes](#known-notes)

---

## Overview

The Smart Event Management System centralizes the full lifecycle of college events. Two types of users interact with the system:

- **Admin** — creates events, manages coordinators, tracks budgets and analytics
- **Student** — registers for events, marks attendance, submits feedback, views personalized recommendations

All business logic (capacity enforcement, analytics updates, coordinator validation, budget guardrails) lives inside the Oracle database as triggers, ensuring consistency regardless of which frontend or API layer is used.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Database | Oracle XE (11g/21c) |
| Backend | PHP 8.x with OCI8 extension |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Server | Apache (XAMPP recommended) |
| Protocol | REST-style JSON APIs over HTTP |

---

## Features

### Core
- **Role-based login** — separate flows for Admin and Student
- **Event management** — Admin can create, update, and delete events with category, venue, budget, and coordinator assignment
- **Student registration** — students register for events; duplicates are prevented at the DB level
- **Attendance tracking** — Admin marks Present/Absent per event; a row is auto-created on registration
- **Expense tracking** — Admin logs expenses per event; triggers enforce budget limits
- **Feedback system** — students rate events (1–5) with optional comments; only attendees who were marked Present can submit feedback

### Intelligent Features
- **Event recommendations** — powered by the `STUDENT_EVENT_RECOMMENDATION` view, which matches a student's interest tags against event categories and excludes already-registered events
- **Real-time analytics** — `EVENT_ANALYTICS` table is automatically maintained by triggers; tracks `AttendanceScore`, `BudgetEfficiency`, and a composite `SuccessScore`
- **Coordinator validation** — a DB trigger ensures a coordinator can only be created from an existing student record
- **Capacity enforcement** — registration is blocked at the DB level when a venue is full

---

## Database Schema

### Reference / Master Tables

| Table | Primary Key | Key Columns |
|---|---|---|
| `"ADMIN"` | `AdminID VARCHAR2(5)` | `Name`, `Email`, `Password` |
| `DEPARTMENT` | `DepartmentID VARCHAR2(5)` | `DepartmentName` |
| `VENUE` | `VenueID VARCHAR2(5)` | `VenueName`, `Capacity` |
| `EVENTCATEGORY` | `CategoryID VARCHAR2(6)` | `CategoryName` |

> ⚠️ `ADMIN` is a reserved word in Oracle. It must always be referenced as `"ADMIN"` (double-quoted) in SQL.

### Core Entity Tables

| Table | Primary Key | Key Columns |
|---|---|---|
| `STUDENT` | `StudentID VARCHAR2(6)` | `Name`, `DepartmentID (FK)`, `Year`, `DOB` |
| `COORDINATOR` | `CoordinatorID VARCHAR2(5)` | `Name`, `DepartmentID (FK)`, `CreatedByAdminID (FK)` |
| `EVENT` | `EventID VARCHAR2(5)` | `EventName`, `EventDate`, `BudgetAllocated`, `CategoryID (FK)`, `VenueID (FK)`, `CoordinatorID (FK)` |

### Transaction Tables

| Table | Primary Key | Key Columns |
|---|---|---|
| `REGISTRATION` | `RegistrationID VARCHAR2(6)` | `StudentID (FK)`, `EventID (FK)` — UNIQUE together |
| `ATTENDANCE` | `AttendanceID VARCHAR2(6)` | `StudentID (FK)`, `EventID (FK)`, `Status` (PRESENT/ABSENT) |
| `EXPENSE` | `ExpenseID VARCHAR2(6)` | `EventID (FK)`, `Amount` |
| `FEEDBACK` | `FeedbackID VARCHAR2(6)` | `StudentID (FK)`, `EventID (FK)`, `Rating` (1–5), `Comments (CLOB)` |

### Analytical / Intelligence Tables

| Table | Primary Key | Key Columns |
|---|---|---|
| `EVENT_ANALYTICS` | `AnalyticsID VARCHAR2(10)` | `EventID (FK, UNIQUE)`, `SuccessScore`, `BudgetEfficiency`, `AttendanceScore` |
| `STUDENT_INTEREST` | `InterestID VARCHAR2(6)` | `StudentID (FK)`, `InterestTag` |

### CHECK Constraints (Allowed Values)

| Table | Column | Allowed Values |
|---|---|---|
| `DEPARTMENT` | `DepartmentName` | CSE, ECE, ELEC, BIO, PHY, CHEM, MEC, CIVIL |
| `EVENTCATEGORY` | `CategoryName` | MATH, PAINTING, DANCING, SINGING, GAMES, SPORTS, BUSINESS |
| `VENUE` | `VenueName` | Quadrangle, AB5 2nd Floor, AB5 Ground Floor, AB4, AB3, Student Plaza, College Ground |
| `ATTENDANCE` | `Status` | PRESENT, ABSENT |
| `FEEDBACK` | `Rating` | 1 – 5 |

---

## Triggers

| Trigger Name | Fires On | Type | Purpose |
|---|---|---|---|
| `trg_auto_attendance` | `AFTER INSERT ON REGISTRATION` | Row | Auto-inserts an ABSENT attendance row when a student registers |
| `trg_capacity_check` | `BEFORE INSERT ON REGISTRATION` | Row | Blocks registration when venue capacity is reached |
| `trg_check_expense` | `BEFORE INSERT OR UPDATE ON EXPENSE` | Row | Prevents zero or negative expense amounts |
| `trg_budget_limit` | `BEFORE INSERT ON EXPENSE` | Row | Blocks expense if it would exceed the event's allocated budget |
| `trg_coord_check` | `BEFORE INSERT ON COORDINATOR` | Row | Ensures new coordinator exists as a student |
| `trg_create_analytics` | `AFTER INSERT ON EVENT` | Row | Creates an `EVENT_ANALYTICS` row (all scores = 0) for every new event |
| `TRG_UPDATE_BUDGET_EFFICIENCY` | `AFTER INSERT ON EXPENSE` | Statement | Recalculates `BudgetEfficiency` for all events after any expense is added |
| `TRG_UPDATE_FEEDBACK` | `AFTER INSERT ON FEEDBACK` | Statement | Recalculates `SuccessScore` using the formula below |
| `trg_update_attendance_score` | `AFTER UPDATE OF Status ON ATTENDANCE` | Statement | Recalculates `AttendanceScore` (% present) after any attendance update |
| `trg_feedback_check` | `BEFORE INSERT ON FEEDBACK` | Row | Prevents feedback submission if student was not marked PRESENT |

### SuccessScore Formula

```
SuccessScore = (AttendanceScore × 0.6)
             + ((100 − BudgetEfficiency) × 0.3)
             + (AVG(Rating) × 10 × 0.1)
```

---

## Sequences

| Sequence | Used For |
|---|---|
| `SEQ_ATT` | `ATTENDANCE.AttendanceID` |
| `EXPENSE_SEQ` | `EXPENSE.ExpenseID` |
| `EVENT_SEQ` | General event ID generation (start: 1001) |

---

## Views

### `STUDENT_EVENT_RECOMMENDATION`

Recommendation engine powered by pure SQL. For each student, surfaces events whose category matches one of their interest tags, excludes events they have already registered for, and ranks results by popularity (registration count).

```sql
SELECT StudentID, EventID, EventName, CategoryName, popularity
FROM STUDENT_EVENT_RECOMMENDATION
WHERE StudentID = 'S0001'
ORDER BY popularity DESC;
```

---

## Project Structure

```
proj/
├── backend/
│   ├── db_connect.php          -- Oracle OCI8 connection factory
│   ├── login.php               -- Student & Admin authentication
│   ├── add_event.php           -- Create / Update / Delete events
│   ├── add_student.php         -- Register new students
│   ├── add_coordinator.php     -- Promote student to coordinator
│   ├── events.php              -- List / filter events
│   ├── register_event.php      -- Student registers for event
│   ├── mark_attendance.php     -- Admin marks attendance
│   ├── add_expense.php         -- Log expense for event
│   ├── feedback.php            -- Submit event feedback
│   ├── my_events.php           -- Student's registered events
│   ├── get_analytics.php       -- Overview / analytics / popular events
│   ├── get_budget.php          -- Budget usage per event
│   ├── get_categories.php      -- List event categories
│   ├── get_venues.php          -- List venues
│   ├── get_coordinators.php    -- List coordinators
│   ├── get_students.php        -- List students
│   ├── departments.php         -- List departments
│   ├── recommendations.php     -- Fetch recommendations for a student
│   └── students.php            -- Student listing
├── frontend/
│   ├── login.html
│   ├── student_dashboard.html
│   ├── admin_dashboard.html
│   ├── events.html
│   └── recommendations.html
└── assets/
    ├── css/style.css
    └── javascript/app.js
```

---

## Setup & Installation

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP stack)
- Oracle XE 11g or 21c installed locally
- PHP OCI8 extension enabled in `php.ini`

### Steps

**1. Oracle setup**

```sql
-- Connect as SYSTEM or your schema user in SQL*Plus / SQL Developer
-- Then run the full schema script:
@smart_event_management.sql
```

**2. Configure the database connection**

Open `backend/db_connect.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '1521');
define('DB_SID',  'XE');       // your Oracle SID or Service Name
define('DB_USER', 'system');   // your Oracle username
define('DB_PASS', 'student');  // your Oracle password
```

**3. Deploy to XAMPP**

Copy the `proj/` folder to your XAMPP `htdocs/` directory:

```
C:\xampp\htdocs\proj\
```

**4. Enable OCI8 in PHP**

In `php.ini`, uncomment:

```ini
extension=oci8_12c  ; or extension=oci8 depending on your PHP version
```

Restart Apache after saving.

**5. Open in browser**

```
http://localhost/proj/frontend/login.html
```

---

## Default Credentials

### Admin Login
| Email | Password |
|---|---|
| admin@manipal.edu | admin123 |
| events@manipal.edu | event123 |

### Student Login
Students log in with their **Student ID** and **Date of Birth** (format: `YYYY-MM-DD`).

| Student ID | Name | DOB |
|---|---|---|
| S0001 | Rahul | 2004-05-10 |
| S0002 | Ananya | 2005-07-12 |
| S0003 | Kiran | 2003-03-22 |
| S0004 | Megha | 2004-11-01 |
| S0005 | Arjun | 2002-09-15 |
| S0006 | Sneha | 2003-12-18 |
| S0007 | Rohit | 2004-06-30 |
| S0008 | Priya | 2005-01-25 |

---

## Seed Data

The SQL script inserts the following reference data automatically:

- **2 Admins**
- **8 Departments** (CSE, ECE, ELEC, BIO, PHY, CHEM, MEC, CIVIL)
- **7 Venues** (Quadrangle → 500, College Ground → 1000, etc.)
- **7 Event Categories** (MATH, PAINTING, DANCING, SINGING, GAMES, SPORTS, BUSINESS)
- **8 Students**
- **3 Coordinators** (promoted from students Rahul, Ananya, Kiran)
- **14 Events** across all categories (April 10–23, 2026)
- **16 Student Interest records** (2 interests per student)

---

## API Reference

All endpoints accept and return `application/json`. Base path: `/proj/backend/`

| Endpoint | Method | Description |
|---|---|---|
| `login.php` | POST | `{ role, student_id, dob }` or `{ role, email, password }` |
| `events.php` | GET | List all events with venue and category details |
| `add_event.php` | POST | `action: add/update/delete` + event fields |
| `add_student.php` | POST | `{ name, department_id, year, dob }` |
| `add_coordinator.php` | POST | `{ student_id, department_id, admin_id }` |
| `register_event.php` | POST | `{ student_id, event_id }` |
| `mark_attendance.php` | POST | `{ event_id, records: [{student_id, status}] }` |
| `add_expense.php` | POST | `{ event_id, amount }` |
| `feedback.php` | POST | `{ student_id, event_id, rating, comments }` |
| `my_events.php` | GET | `?student_id=S0001` |
| `get_analytics.php` | GET | `?type=overview\|analytics\|popular\|feedback\|category_dist\|attendance_list` |
| `get_budget.php` | GET | Budget usage for all events |
| `recommendations.php` | GET | `?student_id=S0001` |
| `get_categories.php` | GET | All event categories |
| `get_venues.php` | GET | All venues |
| `get_coordinators.php` | GET | All coordinators |
| `departments.php` | GET | All departments |

---

## System Flow

```
Student Login (StudentID + DOB)
        ↓
    Dashboard
    ├── Browse Events
    │       ↓ Register
    │   REGISTRATION insert
    │       ↓ (trigger)
    │   ATTENDANCE row auto-created (Status = ABSENT)
    │
    ├── My Events — view registered events + attendance status
    │
    ├── Feedback — only if Status = PRESENT
    │       ↓ (trigger)
    │   EVENT_ANALYTICS.SuccessScore updated
    │
    └── Recommendations — events matching interests, sorted by popularity

Admin Login (Email + Password)
        ↓
    Dashboard
    ├── Manage Events (add / edit / delete)
    │       ↓ (trigger on INSERT)
    │   EVENT_ANALYTICS row auto-created
    │
    ├── Manage Students & Coordinators
    ├── Mark Attendance
    │       ↓ (trigger on UPDATE)
    │   EVENT_ANALYTICS.AttendanceScore updated
    │
    ├── Log Expenses
    │       ↓ (trigger on INSERT)
    │   Budget limit enforced → BudgetEfficiency updated
    │
    └── View Analytics & Budget Reports
```

---

## Known Notes

- **`ADMIN` is a reserved word** in Oracle and must be quoted as `"ADMIN"` in all SQL statements. The PHP backend handles this correctly in `login.php`.
- **`trg_auto_attendance`** generates its `AttendanceID` as `'AT' || TO_CHAR(SYSDATE,'HH24MISS')`, which can collide on bulk inserts within the same second. For production, replace with `SEQ_ATT.NEXTVAL`.
- **`TRG_UPDATE_BUDGET_EFFICIENCY` and `TRG_UPDATE_FEEDBACK`** are statement-level triggers. They recalculate analytics for all events after any insert, which is safe for a college-scale dataset.
- The `trg_budget_limit` trigger is a strict guard — any expense that would exceed the allocated budget is rejected with `ORA-20003`. Adjust or disable it if you need to allow over-budget scenarios.
- EventIDs follow a category-prefix convention: `MA` (Math), `PA` (Painting), `DA` (Dancing), `ST` (Singing), `GA` (Games), `SP` (Sports), `BU` (Business).

---

*Smart Event Management System — College Database Project*

# Self-Service Printing System — REST API Specification (v1)

Base URL: `/api/v1`

All responses follow standard envelopes:
- Success: `{"success": true, "data": { ... }, "message": "..."}`
- Error: `{"success": false, "error": {"code": "ERROR_CODE", "message": "Human readable message", "details": { ... }}}`

---

## Table of Contents
1. [System & Status](#1-system--status)
2. [Document Upload & Processing](#2-document-upload--processing)
3. [Pricing Engine](#3-pricing-engine)
4. [Print Job Submission & Tracking](#4-print-job-submission--tracking)
5. [Payment & Coin Acceptor](#5-payment--coin-acceptor)
6. [Hardware Agent Interface](#6-hardware-agent-interface)
7. [Admin Authentication](#7-admin-authentication)
8. [Admin Dashboard & Analytics](#8-admin-dashboard--analytics)
9. [Admin Transactions & Earnings](#9-admin-transactions--earnings)
10. [Admin Pricing Configuration](#10-admin-pricing-configuration)
11. [Admin Maintenance Mode](#11-admin-maintenance-mode)
12. [Announcements](#12-announcements)
13. [Standard Error Codes](#13-standard-error-codes)

---

## 1. System & Status

### 1.1 Get System Status
Returns the operational health of the printing kiosk, including maintenance state, printer availability, and active announcements.

- **Endpoint**: `GET /api/v1/system/status`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "status": "operational",
    "is_operational": true,
    "printer": {
      "status": "online",
      "is_ready": true,
      "paper": {
        "A4": "available",
        "Letter": "available",
        "Legal": "available"
      },
      "ink": {
        "black": 85,
        "cyan": 70,
        "magenta": 65,
        "yellow": 80
      },
      "current_job_id": null
    },
    "maintenance_enabled": false,
    "maintenance_message": null,
    "active_announcements": [],
    "timestamp": "2026-08-29T10:00:00+00:00"
  }
}
```

### 1.2 Get Printer Status
Returns real-time status of the physical printer.

- **Endpoint**: `GET /api/v1/printer/status`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "status": "online",
    "is_ready": true,
    "paper": {
      "A4": "available",
      "Letter": "available",
      "Legal": "available"
    },
    "ink": {
      "black": 85,
      "cyan": 70,
      "magenta": 65,
      "yellow": 80
    },
    "current_job_id": null,
    "error_message": null
  }
}
```

---

## 2. Document Upload & Processing

### 2.1 Upload File
Uploads a document for printing and initializes or binds to a session.

- **Endpoint**: `POST /api/v1/files`
- **Auth**: None (Middleware: `CheckMaintenanceMode`, `CheckPrinterAvailable`)
- **Headers**: `Content-Type: multipart/form-data`
- **Body**:
  - `file`: File (required, max 50MB; formats: `.pdf`, `.docx`, `.pptx`, `.jpg`, `.jpeg`, `.png`, `.heic`, `.webp`)
  - `session_id`: String (optional UUID)
- **Response**: `201 Created`
```json
{
  "success": true,
  "data": {
    "id": "file_uuid",
    "session_id": "session_uuid",
    "original_name": "project_report.pdf",
    "file_type": "pdf",
    "mime_type": "application/pdf",
    "file_size": 245120,
    "page_count": 5,
    "status": "ready",
    "preview_available": true,
    "created_at": "2026-08-29T10:01:00+00:00"
  }
}
```

### 2.2 Get File Status
Retrieves metadata and processing status of an uploaded file.

- **Endpoint**: `GET /api/v1/files/{file_id}`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "id": "file_uuid",
    "session_id": "session_uuid",
    "original_name": "project_report.pdf",
    "file_type": "pdf",
    "page_count": 5,
    "status": "ready",
    "preview_available": true
  }
}
```

### 2.3 Get File Previews
Returns preview URLs for each page of the uploaded document.

- **Endpoint**: `GET /api/v1/files/{file_id}/preview`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "file_id": "file_uuid",
    "page_count": 5,
    "status": "ready",
    "previews": [
      {
        "page": 1,
        "url": "/storage/previews/file_uuid_page_1.png"
      }
    ]
  }
}
```

---

## 3. Pricing Engine

### 3.1 Calculate Authoritative Print Price
Calculates the exact server-side price breakdown and verifies printer compatibility.

- **Endpoint**: `POST /api/v1/print/price`
- **Auth**: None
- **Body** (JSON):
```json
{
  "file_id": "file_uuid",
  "copies": 2,
  "paper_size": "A4",
  "paper_type": "bond",
  "orientation": "portrait",
  "color_mode": "bw",
  "duplex": true,
  "adjustments": {
    "fit_to_page": true,
    "scale": 100
  }
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "file_id": "file_uuid",
    "pages": 5,
    "copies": 2,
    "paper_size": "A4",
    "paper_type": "bond",
    "color_mode": "bw",
    "duplex": true,
    "breakdown": {
      "base_per_page": 2.00,
      "paper_type_surcharge": 0.00,
      "paper_size_surcharge": 0.00,
      "cost_per_page": 2.00,
      "duplex_discount_per_copy": 1.00,
      "per_copy_subtotal": 9.00,
      "subtotal": 18.00
    },
    "total": 18.00,
    "currency": "PHP",
    "is_supported": true,
    "validation_errors": []
  }
}
```

---

## 4. Print Job Submission & Tracking

### 4.1 Submit Print Job
Submits a print job with full configuration. Supports idempotency keys to prevent duplicate billing and queuing.

- **Endpoint**: `POST /api/v1/print/jobs`
- **Auth**: None (Middleware: `CheckMaintenanceMode`, `CheckPrinterAvailable`)
- **Body** (JSON):
```json
{
  "session_id": "session_uuid",
  "file_id": "file_uuid",
  "copies": 1,
  "paper_size": "A4",
  "paper_type": "bond",
  "orientation": "portrait",
  "color_mode": "bw",
  "duplex": false,
  "idempotency_key": "unique-kiosk-request-key-12345"
}
```
- **Response**: `201 Created` (or `200 OK` if duplicate idempotent request)
```json
{
  "success": true,
  "data": {
    "id": "job_uuid",
    "session_id": "session_uuid",
    "file_id": "file_uuid",
    "status": "queued",
    "copies": 1,
    "paper_size": "A4",
    "paper_type": "bond",
    "color_mode": "bw",
    "duplex": false,
    "page_count": 5,
    "pages_completed": 0,
    "total_price": "10.00",
    "payment_status": "pending",
    "created_at": "2026-08-29T10:02:00+00:00"
  }
}
```

### 4.2 Get Print Job Status
Queries current printing progress.

- **Endpoint**: `GET /api/v1/print/jobs/{job_id}`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "id": "job_uuid",
    "status": "printing",
    "pages_completed": 3,
    "progress": 60,
    "total_price": "10.00",
    "payment_status": "paid"
  }
}
```

---

## 5. Payment & Coin Acceptor

### 5.1 Get Session Payment Status
Returns the financial state of a printing session for the kiosk screen.

- **Endpoint**: `GET /api/v1/payment/{session_id}`
- **Auth**: None
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "session_id": "session_uuid",
    "status": "partial",
    "required_amount": 10.00,
    "inserted_amount": 5.00,
    "remaining_amount": 5.00,
    "is_paid": false,
    "currency": "PHP"
  }
}
```

---

## 6. Hardware Agent Interface

Hardware integration endpoints used by the physical print kiosk agent / coin acceptor daemon.

### 6.1 Record Coin / Bill Insertion (Webhook)
Called by the physical coin acceptor microcontroller or local serial service when a coin is accepted.

- **Endpoint**: `POST /api/v1/agent/payment/confirm`
- **Auth**: None or Kiosk Secret Token
- **Body** (JSON):
```json
{
  "session_id": "session_uuid",
  "amount": 5.00,
  "source": "coin"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "session_id": "session_uuid",
    "status": "paid",
    "required_amount": 5.00,
    "inserted_amount": 5.00,
    "remaining_amount": 0.00,
    "is_paid": true,
    "currency": "PHP"
  },
  "message": "Payment of PHP 5 confirmed."
}
```

### 6.2 Get Pending Print Jobs
Polls pending print jobs for the CUPS or Windows spooler driver.

- **Endpoint**: `GET /api/v1/agent/jobs/pending`
- **Response**: `200 OK`

### 6.3 Update Print Job Progress / Status
Called by the hardware agent to report page printing progress or completion.

- **Endpoint**: `PUT /api/v1/agent/jobs/{job_id}/status`
- **Body** (JSON):
```json
{
  "status": "completed",
  "pages_completed": 5,
  "error_message": null
}
```
- **Response**: `200 OK`

---

## 7. Admin Authentication

### 7.1 Admin Login
Authenticates an administrator and issues a Laravel Sanctum Bearer Token.

- **Endpoint**: `POST /api/v1/admin/login`
- **Body** (JSON):
```json
{
  "email": "admin@printing.local",
  "password": "password"
}
```
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "token": "1|sanctum_plain_text_token...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "name": "Kiosk Administrator",
      "email": "admin@printing.local",
      "role": "admin"
    }
  },
  "message": "Authenticated successfully."
}
```

### 7.2 Admin Logout
Revokes the current Sanctum token.

- **Endpoint**: `POST /api/v1/admin/logout`
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `200 OK`

---

## 8. Admin Dashboard & Analytics

### 8.1 Get Dashboard Metrics
Returns daily, weekly, monthly, and lifetime earnings, total transactions, total pages printed, printer telemetry, and maintenance status.

- **Endpoint**: `GET /api/v1/admin/dashboard`
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `200 OK`
```json
{
  "success": true,
  "data": {
    "earnings": {
      "daily": 450.00,
      "weekly": 2800.00,
      "monthly": 11500.00,
      "total": 45200.00,
      "currency": "PHP"
    },
    "total_transactions": 1420,
    "total_pages_printed": 3890,
    "active_jobs": 0,
    "printer": {
      "status": "online",
      "is_ready": true
    },
    "maintenance": {
      "enabled": false,
      "message": null
    },
    "recent_transactions": []
  }
}
```

---

## 9. Admin Transactions & Earnings

### 9.1 Filter and Search Transactions
Filter transactions by date, amount, pages, and file type with pagination and sorting.

- **Endpoint**: `GET /api/v1/admin/transactions`
- **Headers**: `Authorization: Bearer <token>`
- **Query Parameters**:
  - `date_from` (YYYY-MM-DD)
  - `date_to` (YYYY-MM-DD)
  - `file_type` (pdf, docx, etc.)
  - `amount_min` (numeric)
  - `amount_max` (numeric)
  - `pages_min` (integer)
  - `pages_max` (integer)
  - `sort`: `created_at_desc` (default), `created_at_asc`, `amount_desc`, `amount_asc`, `pages_desc`, `pages_asc`
  - `per_page`: 15 (default)
  - `page`: 1
- **Response**: `200 OK`

### 9.2 Get Transaction Details
- **Endpoint**: `GET /api/v1/admin/transactions/{id}`
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `200 OK`

---

## 10. Admin Pricing Configuration

### 10.1 Get Pricing Matrix
- **Endpoint**: `GET /api/v1/admin/pricing`
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `200 OK`

### 10.2 Update Pricing Matrix
Updates prices for page types, paper formats, and discounts. Automatically refreshes cache and creates audit log.

- **Endpoint**: `PUT /api/v1/admin/pricing`
- **Headers**: `Authorization: Bearer <token>`
- **Body** (JSON):
```json
{
  "pricing": {
    "bw_a4": 2.50,
    "color_a4": 6.00,
    "paper_glossy": 6.00,
    "duplex_discount": 0.50
  }
}
```
- **Response**: `200 OK`

---

## 11. Admin Maintenance Mode

### 11.1 Get Maintenance Status
- **Endpoint**: `GET /api/v1/admin/maintenance`
- **Headers**: `Authorization: Bearer <token>`
- **Response**: `200 OK`

### 11.2 Toggle Maintenance Mode
- **Endpoint**: `PUT /api/v1/admin/maintenance`
- **Headers**: `Authorization: Bearer <token>`
- **Body** (JSON):
```json
{
  "enabled": true,
  "message": "Routine maintenance in progress. The kiosk will reopen at 1:00 PM."
}
```
- **Response**: `200 OK`

---

## 12. Announcements

### 12.1 Get Public Active Announcements
- **Endpoint**: `GET /api/v1/announcements`
- **Auth**: None
- **Response**: `200 OK`

### 12.2 Admin Announcement Management
- `GET /api/v1/admin/announcements` — List all (active & inactive)
- `POST /api/v1/admin/announcements` — Create announcement (`title`, `content`, `type: info|warning|danger`, `is_active`)
- `PUT /api/v1/admin/announcements/{id}` — Update announcement
- `DELETE /api/v1/admin/announcements/{id}` — Delete announcement

---

## 13. Standard Error Codes

| HTTP Status | Error Code | Description |
|---|---|---|
| 400 | `BAD_REQUEST` | Malformed request or missing parameters |
| 401 | `UNAUTHORIZED` | Authentication required or invalid token |
| 401 | `INVALID_CREDENTIALS` | Incorrect email or password on login |
| 403 | `FORBIDDEN` | Admin privileges required |
| 404 | `NOT_FOUND` | Resource (file, job, session, transaction) not found |
| 405 | `METHOD_NOT_ALLOWED` | Unsupported HTTP method |
| 422 | `VALIDATION_ERROR` | Request validation failed; check `error.details` |
| 429 | `TOO_MANY_REQUESTS` | Rate limit exceeded |
| 503 | `MAINTENANCE_MODE` | Kiosk is undergoing maintenance; uploads blocked |
| 503 | `PRINTER_UNAVAILABLE` | Printer is offline, empty on paper, or error state |
| 500 | `SERVER_ERROR` | Internal server error |

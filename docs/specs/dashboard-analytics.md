# Dashboard & Analytics Upgrade Specification

## Overview
Add cash flow forecasting, MRR/ARR tracking, client health scoring, overdue aging buckets, and project profitability to the existing chart/analytics infrastructure.

## New Calculated Fields (ChartCalculations trait)

### MRR - Monthly Recurring Revenue
Query active recurring invoices (status=ACTIVE, remaining_cycles=-1 or >0), normalize amounts to monthly using frequency multiplier.

### ARR
MRR * 12

### Churned MRR
Recurring invoices that moved to PAUSED or COMPLETED status within the date range.

### Overdue Aging Buckets
```sql
SUM(CASE WHEN DATEDIFF(now, due_date) BETWEEN 1 AND 30 THEN balance ELSE 0 END) as bucket_0_30,
SUM(CASE WHEN DATEDIFF(now, due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END) as bucket_31_60,
SUM(CASE WHEN DATEDIFF(now, due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END) as bucket_61_90,
SUM(CASE WHEN DATEDIFF(now, due_date) > 90 THEN balance ELSE 0 END) as bucket_90_plus
```

### Cash Flow Forecast
Project next 90 days based on outstanding invoices by due_date, recurring invoice next_send_dates, and recurring expenses.

### Frequency to Monthly Multiplier
```
daily=30, weekly=4.33, two_weeks=2.17, monthly=1, quarterly=0.333, annually=0.083
```

## Client Health Score Service

New: `app/Services/Client/ClientHealthService.php`

Factors (out of 100):
- Payment timeliness (0-30 points)
- Revenue trend (0-25 points)
- Outstanding balance health (0-25 points)
- Engagement recency (0-20 points)

Grade: A (80+), B (60+), C (40+), D (20+), F (<20)

## Project Profitability

Revenue from invoices minus time cost and expenses per project. Includes budget utilization %.

## New API Endpoints

```
POST /api/v1/charts/mrr
POST /api/v1/charts/overdue_aging
POST /api/v1/charts/cash_forecast
POST /api/v1/charts/project_profit
POST /api/v1/charts/client_health
```

## Files to Modify/Create

- `app/Services/Chart/ChartCalculations.php` - New calculation methods
- `app/Services/Chart/ChartService.php` - Register new fields
- `app/Http/Controllers/ChartController.php` - New endpoints
- `routes/api.php` - Register routes
- `app/Services/Client/ClientHealthService.php` - **New file**

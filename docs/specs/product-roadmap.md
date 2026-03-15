# Invoice Ninja Product Upgrade Roadmap

## Analysis Date: March 2026

## Current State Assessment

Invoice Ninja is a **broad** product covering invoicing, payments, expenses, projects, tasks, vendors, quotes, credits, purchase orders, recurring billing, subscriptions, and basic reporting. It has ~80+ models and integrates with 15+ payment gateways. The breadth is impressive, but it tries to do many things without doing any one thing exceptionally well.

### What exists today:

| Area | Current Features | Depth |
|------|-----------------|-------|
| **Invoicing** | Invoices, quotes, credits, recurring invoices, purchase orders, e-invoicing (PEPPOL) | **Strong** |
| **Payments** | 15+ gateways (Stripe, PayPal, Braintree, Square, GoCardless, etc.) | **Strong** |
| **Client Management** | Basic contacts, addresses, industry/size, custom fields, client portal | **Shallow** |
| **Projects** | Name, due date, budgeted hours, task rate, color | **Very shallow** |
| **Tasks** | Time tracking, statuses, billable/non-billable, link to invoices | **Basic** |
| **Expenses** | Tracking, categories, recurring expenses, bank integration | **Moderate** |
| **Reporting** | AR, P&L, tax, client balance, sales reports, CSV exports | **Basic** |
| **Proposals** | Model exists but is essentially a **stub** - no real fields | **Non-existent** |
| **CRM** | No pipeline, no deals, no lead tracking, no activity timeline per client | **Non-existent** |
| **Automation** | Webhooks (65 events), schedulers, email reminders | **Basic** |

---

## Strategic Pillars

### PILLAR 1: CRM - Turn Clients into Relationships

- Phase 1A: Client Activity Timeline & Notes
- Phase 1B: Deals/Opportunities Pipeline
- Phase 1C: Lead Management
- Phase 1D: Communication Hub

### PILLAR 2: Project Management - From Time Tracker to Delivery Tool

- Phase 2A: Task Enhancement (priorities, due dates, checklists)
- Phase 2B: Project Views & Tracking (Kanban, Gantt)
- Phase 2C: Resource Management
- Phase 2D: Client Portal for Projects

### PILLAR 3: Intelligence & Automation - Make the Product Smarter

- Phase 3A: Dashboard & Analytics Upgrade
- Phase 3B: Workflow Orchestrator (replaces simple automation rules)
- Phase 3C: AI-Powered Features

---

## Priority Build Order

| # | Feature | Effort | Dependencies |
|---|---------|--------|--------------|
| 1 | Task Enhancement (priorities, due dates, checklists) | 2-3 days | None |
| 2 | Dashboard & Analytics Upgrade | 3-5 days | None (parallel with #1) |
| 3 | Project Views (Kanban, Gantt, Milestones) | 1 week | Task Enhancement (#1) |
| 4 | Deals Pipeline | 1-2 weeks | None |
| 5 | Workflow Orchestrator | 2-3 weeks | Deals Pipeline (#4) |

---

## Competitive Positioning

**"The only tool freelancers and small agencies need to run their business."**

- **vs FreshBooks/Wave** - deeper project management and CRM, not just invoicing
- **vs HubSpot CRM** - integrated billing (they have none), lower cost, self-hostable
- **vs Monday/Asana** - native invoicing from project work, time-to-invoice pipeline
- **vs Harvest** - full business suite, not just time tracking + invoicing

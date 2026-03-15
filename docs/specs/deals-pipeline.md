# Deals/Opportunities Pipeline Specification

## Overview
A new Deal entity with pipeline stages, amounts, probabilities, and expected close dates. Deals convert to Quotes or Invoices when won.

## Database

### deal_stages table
- id, company_id, user_id, name, color, stage_order
- is_closeable (marks "won" stages), is_lost (marks "lost" stages)
- Timestamps + soft deletes

### deals table
- id, company_id, user_id, assigned_user_id, client_id, stage_id
- quote_id (linked quote), invoice_id (linked invoice)
- name, number, value, probability (0-100%), expected_close_date, actual_close_date
- description, private_notes, custom_value1-4
- stage_order (position within stage), timestamps + soft deletes

### Default Stages
Qualification, Proposal, Negotiation, Won (is_closeable), Lost (is_lost)

## Deal Service

- `convertToQuote()` - Creates Quote from Deal data, links via deal.quote_id
- `convertToInvoice()` - Creates Invoice from Deal data, links via deal.invoice_id
- `markWon(stage)` - Sets stage, actual_close_date, probability=100
- `markLost(stage)` - Sets stage, actual_close_date, probability=0

## Pipeline Analytics

- `getPipelineValue()` - Open deals grouped by stage with weighted values
- `getDealVelocity()` - Average days from creation to close for won deals

## API Routes

```
Route::resource('deals', DealController::class);
Route::post('deals/bulk', [DealController::class, 'bulk']);
Route::post('deals/sort', [DealController::class, 'sort']);
Route::post('deals/{deal}/convert_to_quote', ...);
Route::post('deals/{deal}/convert_to_invoice', ...);
Route::resource('deal_stages', DealStageController::class);
```

## Webhook Events
EVENT_CREATE_DEAL=66, EVENT_UPDATE_DEAL=67, EVENT_DELETE_DEAL=68,
EVENT_ARCHIVE_DEAL=69, EVENT_RESTORE_DEAL=70, EVENT_DEAL_WON=71, EVENT_DEAL_LOST=72

## ~20 new files following standard entity pattern

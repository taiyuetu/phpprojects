-- Migration: Remove customer_id from leads table (Mode B)
-- Leads are now independent entities, not linked to customers.
-- When converting a lead, a customer is auto-created.

USE crm_db;

-- Remove the foreign key constraint first
ALTER TABLE leads DROP FOREIGN KEY leads_ibfk_1;

-- Remove the customer_id column
ALTER TABLE leads DROP COLUMN customer_id;

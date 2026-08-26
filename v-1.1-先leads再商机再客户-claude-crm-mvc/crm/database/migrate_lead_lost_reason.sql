-- Migration: Add lost reason to leads and support reactivation
-- 1. Add lost_reason field to leads table
-- 2. Add lost_at timestamp for tracking when lead was lost

USE crm_db;

-- Add lost_reason and lost_at columns to leads table
ALTER TABLE leads 
    ADD COLUMN lost_reason VARCHAR(50) NULL AFTER status,
    ADD COLUMN lost_at TIMESTAMP NULL AFTER lost_reason;

-- Add index for querying lost leads
ALTER TABLE leads ADD INDEX idx_leads_lost (lost_reason);

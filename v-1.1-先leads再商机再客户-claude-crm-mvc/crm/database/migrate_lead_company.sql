-- Migration: Add company field to leads table
-- Run: mysql -u root -p crm_db < migrate_lead_company.sql

ALTER TABLE leads
    ADD COLUMN company VARCHAR(150) NULL COMMENT '公司名称' AFTER title;

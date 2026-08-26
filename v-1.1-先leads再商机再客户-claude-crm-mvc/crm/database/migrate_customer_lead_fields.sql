-- Migration: Add lead-origin fields and conversion_time to customers table
-- Run: mysql -u root -p crm_db < migrate_customer_lead_fields.sql

ALTER TABLE customers
    ADD COLUMN whatsapp               VARCHAR(100)    NULL COMMENT 'WhatsApp信息' AFTER phone,
    ADD COLUMN facebook               VARCHAR(255)    NULL COMMENT 'Facebook主页' AFTER whatsapp,
    ADD COLUMN tiktok                 VARCHAR(255)    NULL COMMENT 'TikTok频道' AFTER facebook,
    ADD COLUMN website                VARCHAR(255)    NULL COMMENT '官方网站' AFTER tiktok,
    ADD COLUMN source_country         VARCHAR(80)     NULL COMMENT '来源国家' AFTER website,
    ADD COLUMN source_city            VARCHAR(80)     NULL COMMENT '来源城市' AFTER source_country,
    ADD COLUMN first_purchase_from_china TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否第一次从中国采购' AFTER source_city,
    ADD COLUMN has_import_capability  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '是否有进口能力' AFTER first_purchase_from_china,
    ADD COLUMN conversion_time       DATETIME        NULL COMMENT '客户新建/转化时间' AFTER has_import_capability;

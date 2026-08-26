-- Migration: Add extra fields to leads table
-- Run: mysql -u root -p crm_db < migrate_lead_extra_fields.sql

ALTER TABLE leads
    ADD COLUMN lead_time              DATETIME        NULL COMMENT '线索时间' AFTER contact_email,
    ADD COLUMN whatsapp               VARCHAR(100)    NULL COMMENT 'WhatsApp信息' AFTER lead_time,
    ADD COLUMN phone                  VARCHAR(40)     NULL COMMENT '电话号码' AFTER whatsapp,
    ADD COLUMN facebook               VARCHAR(255)    NULL COMMENT 'Facebook主页' AFTER phone,
    ADD COLUMN tiktok                 VARCHAR(255)    NULL COMMENT 'TikTok频道' AFTER facebook,
    ADD COLUMN website                VARCHAR(255)    NULL COMMENT '官方网站' AFTER tiktok,
    ADD COLUMN source_country         VARCHAR(80)     NULL COMMENT '来源国家' AFTER website,
    ADD COLUMN source_city            VARCHAR(80)     NULL COMMENT '来源城市' AFTER source_country,
    ADD COLUMN address                VARCHAR(255)    NULL COMMENT '具体地址' AFTER source_city,
    ADD COLUMN first_purchase_from_china TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '是否第一次从中国采购' AFTER address,
    ADD COLUMN has_import_capability  TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '是否有进口能力' AFTER first_purchase_from_china;

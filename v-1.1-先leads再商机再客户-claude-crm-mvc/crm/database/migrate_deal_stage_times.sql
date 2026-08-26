-- Migration: Add stage timestamp fields to deals table
-- Run: mysql -u root -p crm_db < migrate_deal_stage_times.sql

ALTER TABLE deals
    ADD COLUMN stage_open_at        DATETIME NULL COMMENT '进入进行中时间' AFTER close_date,
    ADD COLUMN stage_proposal_at    DATETIME NULL COMMENT '进入方案阶段时间' AFTER stage_open_at,
    ADD COLUMN stage_negotiation_at DATETIME NULL COMMENT '进入谈判中时间' AFTER stage_proposal_at,
    ADD COLUMN stage_closed_won_at  DATETIME NULL COMMENT '成交时间' AFTER stage_negotiation_at,
    ADD COLUMN stage_closed_lost_at DATETIME NULL COMMENT '丢单时间' AFTER stage_closed_won_at;

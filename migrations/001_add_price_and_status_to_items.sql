-- Migration: Add status to items and price to stock_in table
-- Version: 1.0
-- Date: 2025-07-02

-- Add status field to items table  
ALTER TABLE items 
ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER low_stock_threshold;

-- Add updated_at timestamp field for better tracking
ALTER TABLE items 
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Add price per unit to stock_in table (supplier-specific pricing)
ALTER TABLE stock_in
ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0.00 AFTER quantity;

-- Add total cost field to stock_in for easier calculations
ALTER TABLE stock_in
ADD COLUMN total_cost DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED AFTER unit_price;

-- Update existing items to have active status
UPDATE items SET status = 'active' WHERE status IS NULL;

-- Update existing stock_in records to have 0.00 unit_price (will need manual update)
UPDATE stock_in SET unit_price = 0.00 WHERE unit_price IS NULL;

-- Create indexes for better performance
CREATE INDEX idx_items_status ON items(status);
CREATE INDEX idx_stock_in_unit_price ON stock_in(unit_price);
CREATE INDEX idx_stock_in_total_cost ON stock_in(total_cost);
CREATE INDEX idx_stock_in_date_price ON stock_in(date, unit_price);

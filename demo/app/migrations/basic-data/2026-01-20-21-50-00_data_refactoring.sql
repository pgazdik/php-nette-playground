-- Update status from 'new' to 'draft' in notification_msg table
UPDATE notification_msg 
SET status = 'draft' 
WHERE status = 'new';
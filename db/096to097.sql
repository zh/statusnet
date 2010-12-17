-- Add indexes for sorting changes in 0.9.7
-- Allows sorting public timeline by timestamp efficiently
alter table notice add index notice_created_id_is_local_idx (created,id,is_local);

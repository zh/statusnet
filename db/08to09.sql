alter table notice
     modify column content text comment 'update content';

alter table message
     modify column content text comment 'message content';

alter table profile
     modify column bio text comment 'descriptive biography';


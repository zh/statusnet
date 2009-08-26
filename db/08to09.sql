alter table notice
     modify column content text comment 'update content';

alter table message
     modify column content text comment 'message content';

alter table profile
     modify column bio text comment 'descriptive biography';

alter table user_group
     modify column description text comment 'group description';

alter table file_oembed
     add column mimetype varchar(50) comment 'mime type of resource';

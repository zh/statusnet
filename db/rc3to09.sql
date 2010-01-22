create table queue_item_new (
    id integer auto_increment primary key comment 'unique identifier',
    frame blob not null comment 'data: object reference or opaque string',
    transport varchar(8) not null comment 'queue for what? "email", "jabber", "sms", "irc", ...',
    created datetime not null comment 'date this record was created',
    claimed datetime comment 'date this item was claimed',

    index queue_item_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

insert into queue_item_new (frame,transport,created,claimed)
    select notice_id,transport,created,claimed from queue_item;
alter table queue_item rename to queue_item_old;
alter table queue_item_new rename to queue_item;


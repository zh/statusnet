alter table user_group
    add uri varchar(255) unique key comment 'universal identifier',
    add mainpage varchar(255) comment 'page for group info to link to',
    drop index nickname;

create table local_group (

   group_id integer primary key comment 'group represented' references user_group (id),
   nickname varchar(64) unique key comment 'group represented',

   created datetime not null comment 'date this record was created',
   modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

insert into local_group (group_id, nickname, created, modified)
    select id, nickname, created, modified from user_group;

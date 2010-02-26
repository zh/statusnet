alter table oauth_application
    modify column name varchar(255) not null unique key comment 'name of the application',
    modify column access_type tinyint default 0 comment 'access type, bit 1 = read, bit 2 = write';

alter table user_group
add column uri varchar(255) unique key comment 'universal identifier',
add column mainpage varchar(255) comment 'page for group info to link to',
drop index nickname;

create table conversation (
     id integer auto_increment primary key comment 'unique identifier',
     uri varchar(225) unique comment 'URI of the conversation',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table local_group (
    group_id integer primary key comment 'group represented' references user_group (id),
    nickname varchar(64) unique key comment 'group represented',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

insert into local_group (group_id, nickname, created)
select id, nickname, created from user_group;


-- SQL commands to update an 0.8.x version of Laconica
-- to 0.9.x.

--these are just comments
/*
alter table notice
     modify column content text comment 'update content';

alter table message
     modify column content text comment 'message content';

alter table profile
     modify column bio text comment 'descriptive biography';

alter table user_group
     modify column description text comment 'group description';
*/

alter table file_oembed
     add column mimetype varchar(50) /*comment 'mime type of resource'*/;

create table config (

    section varchar(32) /* comment 'configuration section'*/,
    setting varchar(32) /* comment 'configuration setting'*/,
    value varchar(255) /* comment 'configuration value'*/,

    primary key (section, setting)

);

create table user_role (

    user_id integer not null /* comment 'user having the role'*/ references "user" (id),
    role    varchar(32) not null /* comment 'string representing the role'*/,
    created timestamp /* not null comment 'date the role was granted'*/,

    primary key (user_id, role)

);

create table login_token (
    user_id integer not null /* comment 'user owning this token'*/ references user (id),
    token char(32) not null /* comment 'token useable for logging in'*/,
    created timestamp not null DEFAULT CURRENT_TIMESTAMP /* comment 'date this record was created'*/,
    modified timestamp /* comment 'date this record was modified'*/,

    constraint primary key (user_id)
);


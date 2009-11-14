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

create table config (

    section varchar(32) comment 'configuration section',
    setting varchar(32) comment 'configuration setting',
    value varchar(255) comment 'configuration value',

    constraint primary key (section, setting)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_role (

    user_id integer not null comment 'user having the role' references user (id),
    role    varchar(32) not null comment 'string representing the role',
    created datetime not null comment 'date the role was granted',

    constraint primary key (user_id, role)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table login_token (
    user_id integer not null comment 'user owning this token' references user (id),
    token char(32) not null comment 'token useable for logging in',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

alter table fave
    drop index fave_user_id_idx,
    add index fave_user_id_idx (user_id,modified);

alter table subscription
    drop index subscription_subscriber_idx,
    add index subscription_subscriber_idx (subscriber,created),
    drop index subscription_subscribed_idx,
    add index subscription_subscribed_idx (subscribed,created);

alter table notice
    drop index notice_profile_id_idx,
    add index notice_profile_id_idx (profile_id,created,id);

alter table notice
     modify column content text comment 'update content',
     add column lat decimal(10,7) comment 'latitude',
     add column lon decimal(10,7) comment 'longitude',
     add column location_id integer comment 'location id if possible',
     add column location_ns integer comment 'namespace for location',
     add column repeat_of integer comment 'notice this is a repeat of' references notice (id),
     drop index notice_profile_id_idx,
     add index notice_profile_id_idx (profile_id,created,id),
     add index notice_repeatof_idx (repeat_of);

alter table message
     modify column content text comment 'message content';

alter table profile
     modify column bio text comment 'descriptive biography',
     add column lat decimal(10,7) comment 'latitude',
     add column lon decimal(10,7) comment 'longitude',
     add column location_id integer comment 'location id if possible',
     add column location_ns integer comment 'namespace for location';

alter table user_group
     modify column description text comment 'group description';

alter table file_oembed
     add column mimetype varchar(50) comment 'mime type of resource';

alter table fave
    drop index fave_user_id_idx,
    add index fave_user_id_idx (user_id,modified);

alter table subscription
    drop index subscription_subscriber_idx,
    add index subscription_subscriber_idx (subscriber,created),
    drop index subscription_subscribed_idx,
    add index subscription_subscribed_idx (subscribed,created);

create table deleted_notice (

    id integer primary key comment 'identity of notice',
    profile_id integer not null comment 'author of the notice',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    created datetime not null comment 'date the notice record was created',
    deleted datetime not null comment 'date the notice record was created',

    index deleted_notice_profile_id_idx (profile_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table config (

    section varchar(32) comment 'configuration section',
    setting varchar(32) comment 'configuration setting',
    value varchar(255) comment 'configuration value',

    constraint primary key (section, setting)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table profile_role (

    profile_id integer not null comment 'account having the role' references profile (id),
    role    varchar(32) not null comment 'string representing the role',
    created datetime not null comment 'date the role was granted',

    constraint primary key (profile_id, role)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table location_namespace (

    id integer primary key comment 'identity for this namespace',
    description varchar(255) comment 'description of the namespace',
    created datetime not null comment 'date the record was created',
    modified timestamp comment 'date this record was modified'

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table login_token (
    user_id integer not null comment 'user owning this token' references user (id),
    token char(32) not null comment 'token useable for logging in',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table user_location_prefs (
    user_id integer not null comment 'user who has the preference' references user (id),
    share_location tinyint default 1 comment 'Whether to share location data',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

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


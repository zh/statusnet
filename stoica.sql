/* local and remote users have profiles */

create table profile (
    id integer auto_increment primary key comment 'unique identifier',
    nickname varchar(64) not null comment 'nickname or username',
    fullname varchar(255) comment 'display name',
    profileurl varchar(255) comment 'URL, cached so we dont regenerate',
    homepage varchar(255) comment 'identifying URL',
    bio varchar(140) comment 'descriptive biography',
    location varchar(255) comment 'physical location',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index profile_nickname_idx (nickname)
);

/* local users */

create table user (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    nickname varchar(64) unique key comment 'nickname or username, duped in profile',
    password varchar(255) comment 'salted password, can be null for OpenID users',
    email varchar(255) unique key comment 'email address for password recovery etc.',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
);

/* remote people */

create table remote_profile (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    url varchar(255) unique key comment 'URL we use for updates from this profile (distinct from "home page" url)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
);

create table subscription (
    subscriber integer not null comment 'profile listening',
    subscribed integer not null comment 'profile being listened to',
    token varchar(255) comment 'authorization token',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (subscriber, subscribed),
    index subscription_subscriber_idx (subscriber),
    index subscription_subscribed_idx (subscribed)
);

create table notice (
    id integer auto_increment primary key comment 'unique identifier',
    profile_id integer not null comment 'who made the update' references profile (id),
    content varchar(140) comment 'update content',
    /* XXX: cache rendered content. */
    url varchar(255) comment 'URL of any attachment (image, video, bookmark, whatever)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index notice_profile_id_idx (profile_id)
);
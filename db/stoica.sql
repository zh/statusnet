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

create table avatar (
    profile_id integer not null comment 'foreign key to profile table' references profile (id),
    original boolean default false comment 'uploaded by user or generated?',
    width integer not null comment 'image width',
    height integer not null comment 'image height',
    mediatype varchar(32) not null comment 'file type',
    filename varchar(255) null comment 'local filename, if local',
    url varchar(255) unique key comment 'avatar location',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    
    constraint primary key (profile_id, width, height),
    index avatar_profile_id_idx (profile_id)
);

/* local users */

create table user (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    nickname varchar(64) unique key comment 'nickname or username, duped in profile',
    password varchar(255) comment 'salted password, can be null for OpenID users',
    email varchar(255) unique key comment 'email address for password recovery etc.',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
);

/* remote people */

create table remote_profile (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    postnoticeurl varchar(255) comment 'URL we use for posting notices',
    updateprofileurl varchar(255) comment 'URL we use for updates to this profile',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
);

create table subscription (
    subscriber integer not null comment 'profile listening',
    subscribed integer not null comment 'profile being listened to',
    token varchar(255) comment 'authorization token',
    secret varchar(255) comment 'token secret',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (subscriber, subscribed),
    index subscription_subscriber_idx (subscriber),
    index subscription_subscribed_idx (subscribed)
);

create table notice (
    id integer auto_increment primary key comment 'unique identifier',
    profile_id integer not null comment 'who made the update' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    content varchar(140) comment 'update content',
    /* XXX: cache rendered content. */
    url varchar(255) comment 'URL of any attachment (image, video, bookmark, whatever)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index notice_profile_id_idx (profile_id)
);
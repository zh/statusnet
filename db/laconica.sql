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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

/* local users */

create table user (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    nickname varchar(64) unique key comment 'nickname or username, duped in profile',
    password varchar(255) comment 'salted password, can be null for OpenID users',
    email varchar(255) unique key comment 'email address for password recovery etc.',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB;

/* remote people */

create table remote_profile (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    postnoticeurl varchar(255) comment 'URL we use for posting notices',
    updateprofileurl varchar(255) comment 'URL we use for updates to this profile',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;

/* tables for OAuth */

create table consumer (
    consumer_key varchar(255) primary key comment 'unique identifier, root URL',
    seed char(32) not null comment 'seed for new tokens by this consumer',
    
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB;

create table token (
    consumer_key varchar(255) not null comment 'unique identifier, root URL' references consumer (consumer_key),
    tok char(32) not null comment 'identifying value',
    secret char(32) not null comment 'secret value',
    type tinyint not null default 0 comment 'request or access',
    state tinyint default 0 comment 'for requests; 0 = initial, 1 = authorized, 2 = used',
    
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    
    constraint primary key (consumer_key, tok)
) ENGINE=InnoDB;

create table nonce (
    consumer_key varchar(255) not null comment 'unique identifier, root URL',
    tok char(32) not null comment 'identifying value',
    nonce char(32) not null comment 'nonce',
    ts datetime not null comment 'timestamp sent',
    
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    
    constraint primary key (consumer_key, tok, nonce),
    constraint foreign key (consumer_key, tok) references token (consumer_key, tok)
) ENGINE=InnoDB;

/* One-to-many relationship of user to openid_url */

create table user_openid (
    canonical varchar(255) primary key comment 'Canonical true URL',
    display varchar(255) not null unique key comment 'URL for viewing, may be different from canonical',
    user_id integer not null comment 'user owning this URL' references user (id),
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    
    index user_openid_user_id_idx (user_id)
) ENGINE=InnoDB;

/* These are used by JanRain OpenID library */

create table oid_associations (
    server_url BLOB,
    handle VARCHAR(255),
    secret BLOB,
    issued INTEGER,
    lifetime INTEGER,
    assoc_type VARCHAR(64),
    PRIMARY KEY (server_url(255), handle)
) ENGINE=InnoDB;

create table oid_nonces (
    server_url VARCHAR(2047),
    timestamp INTEGER,
    salt CHAR(40),
    UNIQUE (server_url(255), timestamp, salt)
) ENGINE=InnoDB;

create table confirm_email (
    code varchar(32) not null primary key comment 'good random code',
    user_id integer not null comment 'user who requested confirmation' references user (id),
    email varchar(255) not null comment 'email address for password recovery etc.',
    modified timestamp comment 'date this record was modified'
);

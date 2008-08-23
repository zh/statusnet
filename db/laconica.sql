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

    index profile_nickname_idx (nickname),
    FULLTEXT(nickname, fullname, location, bio, homepage)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table sms_carrier (
    id integer auto_increment primary key comment 'primary key for SMS carrier',
    name varchar(64) unique key comment 'name of the carrier',
    email_pattern varchar(255) not null comment 'sprintf pattern for making an email address from a phone number',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* local users */

create table user (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    nickname varchar(64) unique key comment 'nickname or username, duped in profile',
    password varchar(255) comment 'salted password, can be null for OpenID users',
    email varchar(255) unique key comment 'email address for password recovery etc.',
    incomingemail varchar(255) unique key comment 'email address for post-by-email',
    emailnotifysub tinyint default 1 comment 'Notify by email of subscriptions',
    emailmicroid tinyint default 1 comment 'whether to publish email microid',
    language varchar(50) comment 'preferred language',
    timezone varchar(50) comment 'timezone',
    emailpost tinyint default 1 comment 'Post by email',
    jabber varchar(255) unique key comment 'jabber ID for notices',
    jabbernotify tinyint default 0 comment 'whether to send notices to jabber',
    jabberreplies tinyint default 0 comment 'whether to send notices to jabber on replies',
    jabbermicroid tinyint default 1 comment 'whether to publish xmpp microid',
    updatefrompresence tinyint default 0 comment 'whether to record updates from Jabber presence notices',
    sms varchar(64) unique key comment 'sms phone number',
    carrier integer comment 'foreign key to sms_carrier' references sms_carrier (id),
    smsnotify tinyint default 0 comment 'whether to send notices to SMS',
    smsreplies tinyint default 0 comment 'whether to send notices to SMS on replies',
    smsemail varchar(255) comment 'built from sms and carrier',
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    autosubscribe tinyint default 0 comment 'automatically subscribe to users who subscribe to us',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index user_smsemail_idx (smsemail)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* remote people */

create table remote_profile (
    id integer primary key comment 'foreign key to profile table' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    postnoticeurl varchar(255) comment 'URL we use for posting notices',
    updateprofileurl varchar(255) comment 'URL we use for updates to this profile',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

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
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table notice (

    id integer auto_increment primary key comment 'unique identifier',
    profile_id integer not null comment 'who made the update' references profile (id),
    uri varchar(255) unique key comment 'universally unique identifier, usually a tag URI',
    content varchar(140) comment 'update content',
    rendered text comment 'HTML version of the content',
    url varchar(255) comment 'URL of any attachment (image, video, bookmark, whatever)',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    reply_to integer comment 'notice replied to (usually a guess)' references notice (id),
    is_local tinyint default 0 comment 'notice was generated by a user',
    source varchar(32) comment 'source of comment, like "web", "im", or "clientname"',

    index notice_profile_id_idx (profile_id),
    index notice_created_idx (created),
    FULLTEXT(content)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

create table notice_source (
     code varchar(32) primary key not null comment 'source code',
     name varchar(255) not null comment 'name of the source',
     url varchar(255) not null comment 'url to link to',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table reply (

    notice_id integer not null comment 'notice that is the reply' references notice (id),
    profile_id integer not null comment 'profile replied to' references profile (id),
    modified timestamp not null comment 'date this record was modified',
    replied_id integer comment 'notice replied to (not used, see notice.reply_to)',

    constraint primary key (notice_id, profile_id),
    index reply_notice_id_idx (notice_id),
    index reply_profile_id_idx (profile_id),
    index reply_replied_id_idx (replied_id)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table fave (

    notice_id integer not null comment 'notice that is the favorite' references notice (id),
    user_id integer not null comment 'user who likes this notice' references user (id),
    modified timestamp not null comment 'date this record was modified',

    constraint primary key (notice_id, user_id),
    index fave_notice_id_idx (notice_id),
    index fave_user_id_idx (user_id),
    index fave_modified_idx (modified)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* tables for OAuth */

create table consumer (
    consumer_key varchar(255) primary key comment 'unique identifier, root URL',
    seed char(32) not null comment 'seed for new tokens by this consumer',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table token (
    consumer_key varchar(255) not null comment 'unique identifier, root URL' references consumer (consumer_key),
    tok char(32) not null comment 'identifying value',
    secret char(32) not null comment 'secret value',
    type tinyint not null default 0 comment 'request or access',
    state tinyint default 0 comment 'for requests; 0 = initial, 1 = authorized, 2 = used',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (consumer_key, tok)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table nonce (
    consumer_key varchar(255) not null comment 'unique identifier, root URL',
    tok char(32) not null comment 'identifying value',
    nonce char(32) not null comment 'nonce',
    ts datetime not null comment 'timestamp sent',

    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    constraint primary key (consumer_key, tok, nonce),
    constraint foreign key (consumer_key, tok) references token (consumer_key, tok)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* One-to-many relationship of user to openid_url */

create table user_openid (
    canonical varchar(255) primary key comment 'Canonical true URL',
    display varchar(255) not null unique key comment 'URL for viewing, may be different from canonical',
    user_id integer not null comment 'user owning this URL' references user (id),
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',

    index user_openid_user_id_idx (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* These are used by JanRain OpenID library */

create table oid_associations (
    server_url BLOB,
    handle VARCHAR(255) character set latin1,
    secret BLOB,
    issued INTEGER,
    lifetime INTEGER,
    assoc_type VARCHAR(64),
    PRIMARY KEY (server_url(255), handle)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oid_nonces (
    server_url VARCHAR(2047),
    timestamp INTEGER,
    salt CHAR(40),
    UNIQUE (server_url(255), timestamp, salt)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table confirm_address (
    code varchar(32) not null primary key comment 'good random code',
    user_id integer not null comment 'user who requested confirmation' references user (id),
    address varchar(255) not null comment 'address (email, Jabber, SMS, etc.)',
    address_extra varchar(255) not null comment 'carrier ID, for SMS',
    address_type varchar(8) not null comment 'address type ("email", "jabber", "sms")',
    claimed datetime comment 'date this was claimed for queueing',
    sent datetime comment 'date this was sent for queueing',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table remember_me (
    code varchar(32) not null primary key comment 'good random code',
    user_id integer not null comment 'user who is logged in' references user (id),
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table queue_item (

    notice_id integer not null comment 'notice queued' references notice (id),
    transport varchar(8) not null comment 'queue for what? "email", "jabber", "sms", "irc", ...',
    created datetime not null comment 'date this record was created',
    claimed datetime comment 'date this item was claimed',

    constraint primary key (notice_id, transport),
    index queue_item_created_idx (created)

) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* Hash tags */
create table notice_tag (
    tag varchar( 64 ) not null comment 'hash tag associated with this notice',
    notice_id integer not null comment 'notice tagged' references notice (id),
    created datetime not null comment 'date this record was created',

    constraint primary key (tag, notice_id),
    index notice_tag_created_idx (created)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

/* Synching with foreign services */

create table foreign_service (
     id int not null primary key comment 'numeric key for service',
     name varchar(32) not null unique key comment 'name of the service',
     description varchar(255) comment 'description',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table foreign_user (
     id int not null comment 'unique numeric key on foreign service',
     service int not null comment 'foreign key to service' references foreign_service(id),
     uri varchar(255) not null unique key comment 'identifying URI',
     nickname varchar(255) comment 'nickname on foreign service',
     user_id int comment 'link to user on this system, if exists' references user (id), 
     credentials varchar(255) comment 'authc credentials, typically a password',
     created datetime not null comment 'date this record was created',
     modified timestamp comment 'date this record was modified',
     
     constraint primary key (id, service),
     index foreign_user_user_id_idx (user_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table foreign_subscription (
     service int not null comment 'service where relationship happens' references foreign_service(id),
     subscriber int not null comment 'subscriber on foreign service' references foreign_user (id),
     subscribed int not null comment 'subscribed user' references foreign_user (id),
     created datetime not null comment 'date this record was created',
     
     constraint primary key (service, subscriber, subscribed),
     index foreign_subscription_subscriber_idx (subscriber),
     index foreign_subscription_subscribed_idx (subscribed)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table invitation (
     code varchar(32) not null primary key comment 'random code for an invitation',
     user_id int not null comment 'who sent the invitation' references user (id),
     address varchar(255) not null comment 'invitation sent to',
     address_type varchar(8) not null comment 'address type ("email", "jabber", "sms")',
     
     index invitation_address_idx (address, address_type),
     index invitation_user_id_idx (user_id)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_bin;

     
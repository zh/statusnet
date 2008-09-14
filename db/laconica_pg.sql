/* local and remote users have profiles */

create table profile (
    id serial primary key /* comment 'unique identifier' */,
    nickname varchar(64) not null /* comment 'nickname or username' */,
    fullname varchar(255) /* comment 'display name' */,
    profileurl varchar(255) /* comment 'URL, cached so we dont regenerate' */,
    homepage varchar(255) /* comment 'identifying URL' */,
    bio varchar(140) /* comment 'descriptive biography' */,
    location varchar(255) /* comment 'physical location' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,

    textsearch tsvector
);
create index profile_nickname_idx on profile using btree(nickname);

create table avatar (
    profile_id integer not null /* comment 'foreign key to profile table' */ references profile (id) ,
    original integer default 0 /* comment 'uploaded by user or generated?' */,
    width integer not null /* comment 'image width' */,
    height integer not null /* comment 'image height' */,
    mediatype varchar(32) not null /* comment 'file type' */,
    filename varchar(255) null /* comment 'local filename, if local' */,
    url varchar(255) unique /* comment 'avatar location' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,

    primary key(profile_id, width, height)
);
create index avatar_profile_id_idx on avatar using btree(profile_id);

create table sms_carrier (
    id serial primary key /* comment 'primary key for SMS carrier' */,
    name varchar(64) unique /* comment 'name of the carrier' */,
    email_pattern varchar(255) not null /* comment 'sprintf pattern for making an email address from a phone number' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified ' */
);

/* local users */

create table "user" (
    id integer primary key /* comment 'foreign key to profile table' */ references profile (id) ,
    nickname varchar(64) unique /* comment 'nickname or username, duped in profile' */,
    password varchar(255) /* comment 'salted password, can be null for OpenID users' */,
    email varchar(255) unique /* comment 'email address for password recovery etc.' */,
    incomingemail varchar(255) unique /* comment 'email address for post-by-email' */,
    emailnotifysub integer default 1 /* comment 'Notify by email of subscriptions' */,
    emailmicroid integer default 1 /* comment 'whether to publish email microid' */,
    language varchar(50) /* comment 'preferred language' */,
    timezone varchar(50) /* comment 'timezone' */,
    emailpost integer default 1 /* comment 'Post by email' */,
    jabber varchar(255) unique /* comment 'jabber ID for notices' */,
    jabbernotify integer default 0 /* comment 'whether to send notices to jabber' */,
    jabberreplies integer default 0 /* comment 'whether to send notices to jabber on replies' */,
    jabbermicroid integer default 1 /* comment 'whether to publish xmpp microid' */,
    updatefrompresence integer default 0 /* comment 'whether to record updates from Jabber presence notices' */,
    sms varchar(64) unique /* comment 'sms phone number' */,
    carrier integer /* comment 'foreign key to sms_carrier' */ references sms_carrier (id) ,
    smsnotify integer default 0 /* comment 'whether to send notices to SMS' */,
    smsreplies integer default 0 /* comment 'whether to send notices to SMS on replies' */,
    smsemail varchar(255) /* comment 'built from sms and carrier' */,
    uri varchar(255) unique /* comment 'universally unique identifier, usually a tag URI' */,
    autosubscribe integer default 0 /* comment 'automatically subscribe to users who subscribe to us' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */

);
create index user_smsemail_idx on "user" using btree(smsemail);

/* remote people */

create table remote_profile (
    id integer primary key /* comment 'foreign key to profile table' */ references profile (id) ,
    uri varchar(255) unique /* comment 'universally unique identifier, usually a tag URI' */,
    postnoticeurl varchar(255) /* comment 'URL we use for posting notices' */,
    updateprofileurl varchar(255) /* comment 'URL we use for updates to this profile' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */
);

create table subscription (
    subscriber integer not null /* comment 'profile listening' */,
    subscribed integer not null /* comment 'profile being listened to' */,
    token varchar(255) /* comment 'authorization token' */,
    secret varchar(255) /* comment 'token secret' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,

    primary key (subscriber, subscribed)
);
create index subscription_subscriber_idx on subscription using btree(subscriber);
create index subscription_subscribed_idx on subscription using btree(subscribed);

create table notice (

    id serial primary key /* comment 'unique identifier' */,
    profile_id integer not null /* comment 'who made the update' */ references profile (id) ,
    uri varchar(255) unique /* comment 'universally unique identifier, usually a tag URI' */,
    content varchar(140) /* comment 'update content' */,
    rendered text /* comment 'HTML version of the content' */,
    url varchar(255) /* comment 'URL of any attachment (image, video, bookmark, whatever)' */,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,
    reply_to integer /* comment 'notice replied to (usually a guess)' */ references notice (id) ,
    is_local integer default 0 /* comment 'notice was generated by a user' */,
    source varchar(32) /* comment 'source of comment, like "web", "im", or "clientname"' */

/*    FULLTEXT(content) */
);
create index notice_profile_id_idx on notice using btree(profile_id);
create index notice_created_idx on notice using btree(created);

create table notice_source (
     code varchar(32) primary key not null /* comment 'source code' */,
     name varchar(255) not null /* comment 'name of the source' */,
     url varchar(255) not null /* comment 'url to link to' */,
     created timestamp not null /* comment 'date this record was created' */,
     modified timestamp /* comment 'date this record was modified' */
);

create table reply (

    notice_id integer not null /* comment 'notice that is the reply' */ references notice (id) ,
    profile_id integer not null /* comment 'profile replied to' */ references profile (id) ,
    modified timestamp not null default 'now' /* comment 'date this record was modified' */,
    replied_id integer /* comment 'notice replied to (not used, see notice.reply_to)' */,

    primary key (notice_id, profile_id)

);
create index reply_notice_id_idx on reply using btree(notice_id);
create index reply_profile_id_idx on reply using btree(profile_id);
create index reply_replied_id_idx on reply using btree(replied_id);

create table fave (

    notice_id integer not null /* comment 'notice that is the favorite' */ references notice (id),
    user_id integer not null /* comment 'user who likes this notice' */ references "user" (id) ,
    modified timestamp not null /* comment 'date this record was modified' */,

    primary key (notice_id, user_id)

);
create index fave_notice_id_idx on fave using btree(notice_id);
create index fave_user_id_idx on fave using btree(user_id);
create index fave_modified_idx on fave using btree(modified);

/* tables for OAuth */

create table consumer (
    consumer_key varchar(255) primary key /* comment 'unique identifier, root URL' */,
    seed char(32) not null /* comment 'seed for new tokens by this consumer' */,

    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */
);

create table token (
    consumer_key varchar(255) not null /* comment 'unique identifier, root URL' */ references consumer (consumer_key),
    tok char(32) not null /* comment 'identifying value' */,
    secret char(32) not null /* comment 'secret value' */,
    type integer not null default 0 /* comment 'request or access' */,
    state integer default 0 /* comment 'for requests; 0 = initial, 1 = authorized, 2 = used' */,

    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,

    primary key (consumer_key, tok)
);

create table nonce (
    consumer_key varchar(255) not null /* comment 'unique identifier, root URL' */,
    tok char(32) not null /* comment 'identifying value' */,
    nonce char(32) not null /* comment 'nonce' */,
    ts timestamp not null /* comment 'timestamp sent' */,

    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */,

    primary key (consumer_key, tok, nonce),
    foreign key (consumer_key, tok) references token (consumer_key, tok)
);

/* One-to-many relationship of user to openid_url */

create table user_openid (
    canonical varchar(255) primary key /* comment 'Canonical true URL' */,
    display varchar(255) not null unique /* comment 'URL for viewing, may be different from canonical' */,
    user_id integer not null /* comment 'user owning this URL' */ references "user" (id) ,
    created timestamp not null /* comment 'date this record was created' */,
    modified timestamp /* comment 'date this record was modified' */

);
create index user_openid_user_id_idx on user_openid using btree(user_id);

/* These are used by JanRain OpenID library */

create table oid_associations (
    server_url varchar(2047),
    handle varchar(255),
    secret bytea,
    issued integer,
    lifetime integer,
    assoc_type varchar(64),
    primary key (server_url, handle)
);

create table oid_nonces (
    server_url varchar(2047),
    "timestamp" integer,
    salt character(40),
    unique (server_url, "timestamp", salt)
);

create table confirm_address (
    code varchar(32) not null primary key /* comment 'good random code' */,
    user_id integer not null /* comment 'user who requested confirmation' */ references "user" (id),
    address varchar(255) not null /* comment 'address (email, Jabber, SMS, etc.)' */,
    address_extra varchar(255) not null /* comment 'carrier ID, for SMS' */,
    address_type varchar(8) not null /* comment 'address type ("email", "jabber", "sms")' */,
    claimed timestamp /* comment 'date this was claimed for queueing' */,
    sent timestamp /* comment 'date this was sent for queueing' */,
    modified timestamp /* comment 'date this record was modified' */
);

create table remember_me (
    code varchar(32) not null primary key /* comment 'good random code' */,
    user_id integer not null /* comment 'user who is logged in' */ references "user" (id),
    modified timestamp /* comment 'date this record was modified' */
);

create table queue_item (

    notice_id integer not null /* comment 'notice queued' */ references notice (id) ,
    transport varchar(8) not null /* comment 'queue for what? "email", "jabber", "sms", "irc", ...' */,
    created timestamp not null /* comment 'date this record was created' */,
    claimed timestamp /* comment 'date this item was claimed' */,

    primary key (notice_id, transport)

);
create index queue_item_created_idx on queue_item using btree(created);

/* Hash tags */
create table notice_tag (
    tag varchar( 64 ) not null /* comment 'hash tag associated with this notice' */,
    notice_id integer not null /* comment 'notice tagged' */ references notice (id) ,
    created timestamp not null /* comment 'date this record was created' */,

    primary key (tag, notice_id)
);
create index notice_tag_created_idx on notice_tag using btree(created);

/* Synching with foreign services */

create table foreign_service (
     id int not null primary key /* comment 'numeric key for service' */,
     name varchar(32) not null unique /* comment 'name of the service' */,
     description varchar(255) /* comment 'description' */,
     created timestamp not null /* comment 'date this record was created' */,
     modified timestamp /* comment 'date this record was modified' */
);

create table foreign_user (
     id int not null /* comment 'unique numeric key on foreign service' */,
     service int not null /* comment 'foreign key to service' */ references foreign_service(id) ,
     uri varchar(255) not null unique /* comment 'identifying URI' */,
     nickname varchar(255) /* comment 'nickname on foreign service' */,
     user_id int /* comment 'link to user on this system, if exists' */ references "user" (id),  
     credentials varchar(255) /* comment 'authc credentials, typically a password' */,
     created timestamp not null /* comment 'date this record was created' */,
     modified timestamp /* comment 'date this record was modified' */,
     
     primary key (id, service)
);
create index foreign_user_user_id_idx on foreign_user using btree(user_id);

create table foreign_subscription (
     service int not null /* comment 'service where relationship happens' */ references foreign_service(id) ,
     subscriber int not null /* comment 'subscriber on foreign service' */ ,
     subscribed int not null /* comment 'subscribed user' */ ,
     created timestamp not null /* comment 'date this record was created' */,
     
     primary key (service, subscriber, subscribed)
);
create index foreign_subscription_subscriber_idx on foreign_subscription using btree(subscriber);
create index foreign_subscription_subscribed_idx on foreign_subscription using btree(subscribed);

/* Textsearch stuff */

create index textsearch_idx on profile using gist(textsearch);
create index noticecontent_idx on notice using gist(to_tsvector('english',content));
create trigger textsearchupdate before insert or update on profile for each row
execute procedure tsvector_update_trigger(textsearch, 'pg_catalog.english', nickname, fullname, location, bio, homepage);


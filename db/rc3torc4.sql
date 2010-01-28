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

alter table consumer
    add consumer_secret varchar(255) not null comment 'secret value';

alter table token
    add verifier varchar(255) comment 'verifier string for OAuth 1.0a',
    add verified_callback varchar(255) comment 'verified callback URL for OAuth 1.0a';

create table oauth_application (
    id integer auto_increment primary key comment 'unique identifier',
    owner integer not null comment 'owner of the application' references profile (id),
    consumer_key varchar(255) not null comment 'application consumer key' references consumer (consumer_key),
    name varchar(255) not null comment 'name of the application',
    description varchar(255) comment 'description of the application',
    icon varchar(255) not null comment 'application icon',
    source_url varchar(255) comment 'application homepage - used for source link',
    organization varchar(255) comment 'name of the organization running the application',
    homepage varchar(255) comment 'homepage for the organization',
    callback_url varchar(255) comment 'url to redirect to after authentication',
    type tinyint default 0 comment 'type of app, 1 = browser, 2 = desktop',
    access_type tinyint default 0 comment 'default access type, bit 1 = read, bit 2 = write',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified'
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;

create table oauth_application_user (
    profile_id integer not null comment 'user of the application' references profile (id),
    application_id integer not null comment 'id of the application' references oauth_application (id),
    access_type tinyint default 0 comment 'access type, bit 1 = read, bit 2 = write, bit 3 = revoked',
    token varchar(255) comment 'request or access token',
    created datetime not null comment 'date this record was created',
    modified timestamp comment 'date this record was modified',
    constraint primary key (profile_id, application_id)
) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_bin;


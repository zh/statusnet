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

create table profile_role (

    profile_id integer not null /* comment 'account having the role'*/ references profile (id),
    role    varchar(32) not null /* comment 'string representing the role'*/,
    created timestamp /* not null comment 'date the role was granted'*/,

    primary key (profile_id, role)

);

create table location_namespace (

    id integer /*comment 'identity for this namespace'*/,
    description text /* comment 'description of the namespace'*/ ,
    created integer not null /*comment 'date the record was created*/ ,
   /* modified timestamp comment 'date this record was modified',*/
    primary key (id)

);

create table login_token (
    user_id integer not null /* comment 'user owning this token'*/ references "user" (id),
    token char(32) not null /* comment 'token useable for logging in'*/,
    created timestamp not null DEFAULT CURRENT_TIMESTAMP /* comment 'date this record was created'*/,
    modified timestamp /* comment 'date this record was modified'*/,

    primary key (user_id)
);

DROP index fave_user_id_idx;
CREATE index fave_user_id_idx on fave (user_id,modified);

DROP index subscription_subscriber_idx;
CREATE index subscription_subscriber_idx ON subscription (subscriber,created);

DROP index subscription_subscribed_idx;
CREATE index subscription_subscribed_idx ON subscription (subscribed,created);

DROP index notice_profile_id_idx;
CREATE index notice_profile_id_idx ON notice (profile_id,created,id);

ALTER TABLE notice ADD COLUMN lat decimal(10, 7) /* comment 'latitude'*/;
ALTER TABLE notice ADD COLUMN lon decimal(10,7) /* comment 'longitude'*/;
ALTER TABLE notice ADD COLUMN location_id integer /* comment 'location id if possible'*/ ;
ALTER TABLE notice ADD COLUMN location_ns integer /* comment 'namespace for location'*/;
ALTER TABLE notice ADD COLUMN repeat_of integer /* comment 'notice this is a repeat of' */ references notice (id);

ALTER TABLE profile ADD COLUMN lat decimal(10,7) /*comment 'latitude'*/ ;
ALTER TABLE profile ADD COLUMN lon decimal(10,7) /*comment 'longitude'*/;
ALTER TABLE profile ADD COLUMN location_id integer /* comment 'location id if possible'*/;
ALTER TABLE profile ADD COLUMN location_ns integer /* comment 'namespace for location'*/;

